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
            if (!Admin::isPrivilege(Config::get("privileges/MANAGE_WEBSITE")) && !Admin::isPrivilege(Config::get("privileges/CONTACT_FORM"))) die();
        }


        private function update_contracts()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $contracts1 = Filter::POST("contract1");
            $contracts2 = Filter::POST("contract2");

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            $adata = UserManager::LoginData("admin");

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyUP = strtoupper($lkey);
                $contract1 = isset($contracts1[$lkey]) ? $contracts1[$lkey] : false;
                $contract2 = isset($contracts2[$lkey]) ? $contracts2[$lkey] : false;

                $data = ___("constants", false, $lkey);
                $change = 0;

                if ($contract1 != ___("constants/contract1", false, $lkey)) {
                    $data["contract1"] = $contract1;
                    $change++;
                    User::addAction($adata["id"], "alteration", "changed-contract1", ['lang' => $lkeyUP]);
                }

                if ($contract2 != ___("constants/contract2", false, $lkey)) {
                    $data["contract2"] = $contract2;
                    $change++;
                    User::addAction($adata["id"], "alteration", "changed-contract2", ['lang' => $lkeyUP]);
                }

                if ($change) {
                    $data_export = Utility::array_export($data, ['pwith' => true]);
                    FileManager::file_write(LANG_DIR . $lkey . DS . "constants.php", $data_export);
                }

            }

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success19"),
                'redirect' => $this->AdminCRLink("manage-website-1", ["pages"]),
            ]);
        }


        private function add_new_blog_category()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $slug = Filter::init("GET/slug", "route");
            $lang = Filter::init("GET/lang", "route");
            if ($slug && $lang && ___("package/permalink", false, $lang)) {
                $check = $this->model->category_route_check($slug, $lang, "articles");
                if ($check)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/manage-website/error5"),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $sub_titles = Filter::POST("sub_title");
            $hbackground = Filter::FILES("hbackground");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $parent = (int)Filter::init("POST/parent", "numbers");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $status = Filter::init("POST/status", "letters");

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $route = isset($routes[$lkey]) ? Filter::html_clear($routes[$lkey]) : false;
                $sub_title = isset($sub_titles[$lkey]) ? Filter::html_clear($sub_titles[$lkey]) : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/manage-website/error2", ['{lang}' => strtoupper($lkey)]),
                    ]));


                if (___("package/permalink", false, $lkey)) {
                    $route = !$route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->category_route_check($route, $lkey, "articles");
                        if ($check)
                            die(Utility::jencode([
                                'status'      => "error",
                                'lang'        => $lkey,
                                'route_check' => true,
                                'route'       => $route,
                                'for'         => "input[name='route[" . $lkey . "]']",
                            ]));
                    } else $route = 0;
                } else $route = 0;

                $lopt = [];

                if ($title)
                    $lang_data[$lkey] = [
                        'owner_id'        => 0,
                        'lang'            => $lkey,
                        'title'           => $title,
                        'route'           => $route,
                        'sub_title'       => $sub_title,
                        'content'         => $content,
                        'seo_title'       => $seo_title,
                        'seo_keywords'    => $seo_keywords,
                        'seo_description' => $seo_description,
                        'options'         => $lopt ? Utility::jencode($lopt) : '',
                    ];

            }

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
                $hthmbsizing = Config::get("pictures/header-background/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($hbackground, [
                    'image-upload' => true,
                    'folder'       => $hfolder,
                    'width'        => $hsizing["width"],
                    'height'       => $hsizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='hbackground']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, $hthmbsizing["width"], $hthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
            }

            if (!$lang_data) die("Error! #1");

            $options = Utility::jencode([]);

            $insert = $this->model->insert_category([
                'status'  => $status,
                'parent'  => $parent,
                'rank'    => $rank,
                'type'    => "articles",
                'options' => $options,
                'ctime'   => DateManager::Now(),
            ]);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error4"),
                ]));

            if (isset($hpicture) && $hpicture) $this->model->insert_picture("category", $insert, "header-background", $hpicture);

            foreach ($lang_data as $key => $data) {
                $data["owner_id"] = $insert;
                if (!$data["route"]) $data["route"] = $insert;
                $this->model->insert_category_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-article-category", [
                'name' => $lang_data[$locall]["title"],
                'id'   => $insert,
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success5"),
                'redirect' => $this->AdminCRLink("manage-website-2", ["blogs", "categories"]),
            ]);
        }


        private function add_new_reference_category()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $slug = Filter::init("GET/slug", "route");
            $lang = Filter::init("GET/lang", "route");
            if ($slug && $lang && ___("package/permalink", false, $lang)) {
                $check = $this->model->category_route_check($slug, $lang, "references");
                if ($check)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/manage-website/error5"),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $sub_titles = Filter::POST("sub_title");
            $hbackground = Filter::FILES("hbackground");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $parent = (int)Filter::init("POST/parent", "numbers");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $status = Filter::init("POST/status", "letters");

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $route = isset($routes[$lkey]) ? Filter::html_clear($routes[$lkey]) : false;
                $sub_title = isset($sub_titles[$lkey]) ? Filter::html_clear($sub_titles[$lkey]) : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/manage-website/error2", ['{lang}' => strtoupper($lkey)]),
                    ]));


                if (___("package/permalink", false, $lkey)) {
                    $route = !$route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->category_route_check($route, $lkey, "references");
                        if ($check)
                            die(Utility::jencode([
                                'status'      => "error",
                                'lang'        => $lkey,
                                'route_check' => true,
                                'route'       => $route,
                                'for'         => "input[name='route[" . $lkey . "]']",
                            ]));
                    } else $route = 0;
                } else $route = 0;

                $lopt = [];

                if ($title)
                    $lang_data[$lkey] = [
                        'owner_id'        => 0,
                        'lang'            => $lkey,
                        'title'           => $title,
                        'route'           => $route,
                        'sub_title'       => $sub_title,
                        'content'         => $content,
                        'seo_title'       => $seo_title,
                        'seo_keywords'    => $seo_keywords,
                        'seo_description' => $seo_description,
                        'options'         => $lopt ? Utility::jencode($lopt) : '',
                    ];

            }

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
                $hthmbsizing = Config::get("pictures/header-background/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($hbackground, [
                    'image-upload' => true,
                    'folder'       => $hfolder,
                    'width'        => $hsizing["width"],
                    'height'       => $hsizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='hbackground']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, $hthmbsizing["width"], $hthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
            }

            if (!$lang_data) die("Error! #1");

            $options = Utility::jencode([]);

            $insert = $this->model->insert_category([
                'status'  => $status,
                'parent'  => $parent,
                'rank'    => $rank,
                'type'    => "references",
                'options' => $options,
                'ctime'   => DateManager::Now(),
            ]);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error4"),
                ]));

            if (isset($hpicture) && $hpicture) $this->model->insert_picture("category", $insert, "header-background", $hpicture);

            foreach ($lang_data as $key => $data) {
                $data["owner_id"] = $insert;
                if (!$data["route"]) $data["route"] = $insert;
                $this->model->insert_category_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-reference-category", [
                'name' => $lang_data[$locall]["title"],
                'id'   => $insert,
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success5"),
                'redirect' => $this->AdminCRLink("manage-website-2", ["references", "categories"]),
            ]);
        }


        private function add_normal_page()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $slug = Filter::init("GET/slug", "route");
            $lang = Filter::init("GET/lang", "route");
            if ($slug && $lang && ___("package/permalink", false, $lang)) {
                $check = $this->model->page_route_check($slug, $lang);
                if ($check)
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='route[" . $lang . "]']",
                        'message' => __("admin/manage-website/error1"),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $contents = Filter::POST("content");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $status = Filter::init("POST/status", "letters");
            $category = Filter::init("POST/category", "rnumbers");
            $sidebar = Filter::init("POST/sidebar", "numbers");
            if ($sidebar) $sidebar = "disable";
            else $sidebar = '';
            $invisibility = Filter::init("POST/invisibility", "numbers");
            if ($invisibility) $visibility = "invisible";
            else $visibility = "visible";
            $hbackground = Filter::FILES("hbackground");
            $list_image = Filter::FILES("list_image");

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $route = isset($routes[$lkey]) ? Filter::html_clear($routes[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;


                $lopt = [];

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/manage-website/error2", ['{lang}' => $lkeyup]),
                    ]));

                if (___("package/permalink", false, $lkey)) {
                    $route = !$route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->page_route_check($route, $lkey);
                        if ($check)
                            die(Utility::jencode([
                                'status'      => "error",
                                'lang'        => $lkey,
                                'route_check' => true,
                                'route'       => $route,
                                'for'         => "input[name='route[" . $lkey . "]']",
                            ]));
                    } else $route = 0;
                } else $route = 0;

                $lang_data[$lkey] = [
                    'owner_id'        => 0,
                    'lang'            => $lkey,
                    'title'           => $title,
                    'route'           => $route,
                    'content'         => $content,
                    'seo_title'       => $seo_title,
                    'seo_keywords'    => $seo_keywords,
                    'seo_description' => $seo_description,
                    'options'         => $lopt ? Utility::jencode($lopt) : '',
                ];
            }

            $options = [];

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
                $hthmbsizing = Config::get("pictures/header-background/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($hbackground, [
                    'image-upload' => true,
                    'folder'       => $hfolder,
                    'width'        => $hsizing["width"],
                    'height'       => $hsizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='hbackground']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, $hthmbsizing["width"], $hthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
            }

            if ($list_image) {
                Helper::Load(["Uploads", "Image"]);
                $sfolder = Config::get("pictures/page-normal/folder");
                $ssizing = Config::get("pictures/page-normal/list/sizing");
                $sthmbsizing = Config::get("pictures/page-normal/list/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($list_image, [
                    'image-upload' => true,
                    'folder'       => $sfolder,
                    'width'        => $ssizing["width"],
                    'height'       => $ssizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='list_image']",
                        'message' => __("admin/manage-website/error2", ['{error}' => $upload->error]),
                    ]));
                $limgpicture = current($upload->operands);
                $limgpicture = $limgpicture["file_path"];
                Image::set($sfolder . $limgpicture, $sfolder . "thumb" . DS, false, $sthmbsizing["width"], $sthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
            }

            $set_data = [
                'type'       => "normal",
                'ctime'      => DateManager::Now(),
                'status'     => $status,
                'category'   => $category,
                'visibility' => $visibility,
                'sidebar'    => $sidebar,
                'options'    => Utility::jencode($options),
            ];

            $insert = $this->model->insert_page($set_data);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error4"),
                ]));

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $data["owner_id"] = $insert;
                    if (!$data["route"]) $data["route"] = $insert;
                    $this->model->insert_page_lang($data);
                }
            }

            if (isset($hpicture) && $hpicture) $this->model->insert_picture("page_normal", $insert, "header-background", $hpicture);
            if (isset($limgpicture) && $limgpicture) $this->model->insert_picture("page_normal", $insert, "cover", $limgpicture);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-normal-page", [
                'id'   => $insert,
                'name' => $lang_data[$locall]["title"],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success1-normal"),
                'redirect' => $this->AdminCRLink("manage-website-1", ["pages"]),
            ]);

        }


        private function add_news_page()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $slug = Filter::init("GET/slug", "route");
            $lang = Filter::init("GET/lang", "route");
            if ($slug && $lang && ___("package/permalink", false, $lang)) {
                $check = $this->model->page_route_check($slug, $lang);
                if ($check)
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='route[" . $lang . "]']",
                        'message' => __("admin/manage-website/error1"),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $contents = Filter::POST("content");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $status = Filter::init("POST/status", "letters");
            $category = Filter::init("POST/category", "rnumbers");
            $hide_comments = (int)Filter::init("POST/hide_comments", "numbers");
            $sidebar = Filter::init("POST/sidebar", "numbers");
            if ($sidebar) $sidebar = "disable";
            else $sidebar = '';
            $visibility = Filter::init("POST/visibility", "numbers");
            if ($visibility) $visibility = "visible";
            else $visibility = "invisible";
            $visible_to_user = (int)Filter::init("POST/visible_to_user", "numbers");
            $hbackground = Filter::FILES("hbackground");
            $list_image = Filter::FILES("list_image");

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $route = isset($routes[$lkey]) ? Filter::html_clear($routes[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;


                $lopt = [];

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/manage-website/error2", ['{lang}' => $lkeyup]),
                    ]));

                if (___("package/permalink", false, $lkey)) {
                    $route = !$route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->page_route_check($route, $lkey);
                        if ($check)
                            die(Utility::jencode([
                                'status'      => "error",
                                'lang'        => $lkey,
                                'route_check' => true,
                                'route'       => $route,
                                'for'         => "input[name='route[" . $lkey . "]']",
                            ]));
                    } else $route = 0;
                } else $route = 0;

                $lang_data[$lkey] = [
                    'owner_id'        => 0,
                    'lang'            => $lkey,
                    'title'           => $title,
                    'route'           => $route,
                    'content'         => $content,
                    'seo_title'       => $seo_title,
                    'seo_keywords'    => $seo_keywords,
                    'seo_description' => $seo_description,
                    'options'         => $lopt ? Utility::jencode($lopt) : '',
                ];
            }

            $options = [
                'hide_comments' => $hide_comments,
            ];

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
                $hthmbsizing = Config::get("pictures/header-background/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($hbackground, [
                    'image-upload' => true,
                    'folder'       => $hfolder,
                    'width'        => $hsizing["width"],
                    'height'       => $hsizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='hbackground']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, $hthmbsizing["width"], $hthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
            }

            if ($list_image) {
                Helper::Load(["Uploads", "Image"]);
                $sfolder = Config::get("pictures/page-news/folder");
                $ssizing = Config::get("pictures/page-news/list/sizing");
                $sthmbsizing = Config::get("pictures/page-news/list/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($list_image, [
                    'image-upload' => true,
                    'folder'       => $sfolder,
                    'width'        => $ssizing["width"],
                    'height'       => $ssizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='list_image']",
                        'message' => __("admin/manage-website/error2", ['{error}' => $upload->error]),
                    ]));
                $limgpicture = current($upload->operands);
                $limgpicture = $limgpicture["file_path"];
                Image::set($sfolder . $limgpicture, $sfolder . "thumb" . DS, false, $sthmbsizing["width"], $sthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
            }

            $set_data = [
                'type'            => "news",
                'ctime'           => DateManager::Now(),
                'status'          => $status,
                'category'        => $category,
                'visibility'      => $visibility,
                'visible_to_user' => $visible_to_user,
                'sidebar'         => $sidebar,
                'options'         => Utility::jencode($options),
            ];

            $insert = $this->model->insert_page($set_data);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error4"),
                ]));

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $data["owner_id"] = $insert;
                    if (!$data["route"]) $data["route"] = $insert;
                    $this->model->insert_page_lang($data);
                }
            }

            if (isset($hpicture) && $hpicture) $this->model->insert_picture("page_news", $insert, "header-background", $hpicture);
            if (isset($limgpicture) && $limgpicture) $this->model->insert_picture("page_news", $insert, "cover", $limgpicture);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-news-page", [
                'id'   => $insert,
                'name' => $lang_data[$locall]["title"],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success1-news"),
                'redirect' => $this->AdminCRLink("manage-website-1", ["news"]),
            ]);

        }


        private function add_blog_page()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $slug = Filter::init("GET/slug", "route");
            $lang = Filter::init("GET/lang", "route");
            if ($slug && $lang && ___("package/permalink", false, $lang)) {
                $check = $this->model->page_route_check($slug, $lang);
                if ($check)
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='route[" . $lang . "]']",
                        'message' => __("admin/manage-website/error1"),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $contents = Filter::POST("content");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $status = Filter::init("POST/status", "letters");
            $category = Filter::init("POST/category", "rnumbers");
            $hide_comments = (int)Filter::init("POST/hide_comments", "numbers");
            $sidebar = Filter::init("POST/sidebar", "numbers");
            if ($sidebar) $sidebar = "disable";
            else $sidebar = '';
            $invisibility = Filter::init("POST/invisibility", "numbers");
            if ($invisibility) $visibility = "invisible";
            else $visibility = "visible";
            $hbackground = Filter::FILES("hbackground");
            $list_image = Filter::FILES("list_image");

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $route = isset($routes[$lkey]) ? Filter::html_clear($routes[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;


                $lopt = [];

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/manage-website/error2", ['{lang}' => $lkeyup]),
                    ]));

                if (___("package/permalink", false, $lkey)) {
                    $route = !$route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->page_route_check($route, $lkey);
                        if ($check)
                            die(Utility::jencode([
                                'status'      => "error",
                                'lang'        => $lkey,
                                'route_check' => true,
                                'route'       => $route,
                                'for'         => "input[name='route[" . $lkey . "]']",
                            ]));
                    } else $route = 0;
                } else $route = 0;

                $lang_data[$lkey] = [
                    'owner_id'        => 0,
                    'lang'            => $lkey,
                    'title'           => $title,
                    'route'           => $route,
                    'content'         => $content,
                    'seo_title'       => $seo_title,
                    'seo_keywords'    => $seo_keywords,
                    'seo_description' => $seo_description,
                    'options'         => $lopt ? Utility::jencode($lopt) : '',
                ];
            }

            $options = [
                'hide_comments' => $hide_comments,
            ];

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
                $hthmbsizing = Config::get("pictures/header-background/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($hbackground, [
                    'image-upload' => true,
                    'folder'       => $hfolder,
                    'width'        => $hsizing["width"],
                    'height'       => $hsizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='hbackground']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, $hthmbsizing["width"], $hthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
            }

            if ($list_image) {
                Helper::Load(["Uploads", "Image"]);
                $sfolder = Config::get("pictures/page-articles/folder");
                $ssizing = Config::get("pictures/page-articles/list/sizing");
                $sthmbsizing = Config::get("pictures/page-articles/list/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($list_image, [
                    'image-upload' => true,
                    'folder'       => $sfolder,
                    'width'        => $ssizing["width"],
                    'height'       => $ssizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='list_image']",
                        'message' => __("admin/manage-website/error2", ['{error}' => $upload->error]),
                    ]));
                $limgpicture = current($upload->operands);
                $limgpicture = $limgpicture["file_path"];
                Image::set($sfolder . $limgpicture, $sfolder . "thumb" . DS, false, $sthmbsizing["width"], $sthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
            }

            $set_data = [
                'type'       => "articles",
                'ctime'      => DateManager::Now(),
                'status'     => $status,
                'category'   => $category,
                'visibility' => $visibility,
                'sidebar'    => $sidebar,
                'options'    => Utility::jencode($options),
            ];

            $insert = $this->model->insert_page($set_data);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error4"),
                ]));

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $data["owner_id"] = $insert;
                    if (!$data["route"]) $data["route"] = $insert;
                    $this->model->insert_page_lang($data);
                }
            }

            if (isset($hpicture) && $hpicture) $this->model->insert_picture("page_articles", $insert, "header-background", $hpicture);
            if (isset($limgpicture) && $limgpicture) $this->model->insert_picture("page_articles", $insert, "cover", $limgpicture);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-articles-page", [
                'id'   => $insert,
                'name' => $lang_data[$locall]["title"],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success1-articles"),
                'redirect' => $this->AdminCRLink("manage-website-1", ["blogs"]),
            ]);
        }


        private function add_reference_page()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $slug = Filter::init("GET/slug", "route");
            $lang = Filter::init("GET/lang", "route");
            if ($slug && $lang && ___("package/permalink", false, $lang)) {
                $check = $this->model->page_route_check($slug, $lang);
                if ($check)
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='route[" . $lang . "]']",
                        'message' => __("admin/manage-website/error1"),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $contents = Filter::POST("content");
            $featured_infos = Filter::POST("featured-info");
            $technical_infos = Filter::POST("technical-info");
            $website = Filter::init("POST/website", "hclear");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $status = Filter::init("POST/status", "letters");
            $category = Filter::init("POST/category");
            $invisibility = Filter::init("POST/invisibility", "numbers");
            if ($invisibility) $visibility = "invisible";
            else $visibility = "visible";
            $rank = Filter::init("POST/rank", "numbers");
            $hbackground = Filter::FILES("hbackground");
            $list_image = Filter::FILES("list_image");
            $mockup_image = Filter::FILES("mockup_image");

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $route = isset($routes[$lkey]) ? Filter::html_clear($routes[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $featured_info = isset($featured_infos[$lkey]) ? $featured_infos[$lkey] : false;
                $technical_info = isset($technical_infos[$lkey]) ? $technical_infos[$lkey] : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;


                $lopt = [
                    'featured-info'  => $featured_info,
                    'technical-info' => $technical_info,
                ];

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/manage-website/error2", ['{lang}' => $lkeyup]),
                    ]));

                if (___("package/permalink", false, $lkey)) {
                    $route = !$route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->page_route_check($route, $lkey);
                        if ($check)
                            die(Utility::jencode([
                                'status'      => "error",
                                'lang'        => $lkey,
                                'route_check' => true,
                                'route'       => $route,
                                'for'         => "input[name='route[" . $lkey . "]']",
                            ]));
                    } else $route = 0;
                } else $route = 0;

                $lang_data[$lkey] = [
                    'owner_id'        => 0,
                    'lang'            => $lkey,
                    'title'           => $title,
                    'route'           => $route,
                    'content'         => $content,
                    'seo_title'       => $seo_title,
                    'seo_keywords'    => $seo_keywords,
                    'seo_description' => $seo_description,
                    'options'         => $lopt ? Utility::jencode($lopt) : '',
                ];
            }

            $options = [
                'website' => $website,
            ];

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
                $hthmbsizing = Config::get("pictures/header-background/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($hbackground, [
                    'image-upload' => true,
                    'folder'       => $hfolder,
                    'width'        => $hsizing["width"],
                    'height'       => $hsizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='hbackground']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, $hthmbsizing["width"], $hthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
            }

            if ($list_image) {
                Helper::Load(["Uploads", "Image"]);
                $sfolder = Config::get("pictures/page-references/folder");
                $ssizing = Config::get("pictures/page-references/list/sizing");
                $sthmbsizing = Config::get("pictures/page-references/list/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($list_image, [
                    'image-upload' => true,
                    'folder'       => $sfolder,
                    'width'        => $ssizing["width"],
                    'height'       => $ssizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='list_image']",
                        'message' => __("admin/manage-website/error2", ['{error}' => $upload->error]),
                    ]));
                $limgpicture = current($upload->operands);
                $limgpicture = $limgpicture["file_path"];
                Image::set($sfolder . $limgpicture, $sfolder . "thumb" . DS, false, $sthmbsizing["width"], $sthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
            }

            if ($mockup_image) {
                Helper::Load(["Uploads", "Image"]);
                $sfolder = Config::get("pictures/page-references/folder");
                $ssizing = Config::get("pictures/page-references/mockup/sizing");
                $sthmbsizing = Config::get("pictures/page-references/mockup/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($mockup_image, [
                    'image-upload' => true,
                    'folder'       => $sfolder,
                    'width'        => $ssizing["width"],
                    'height'       => $ssizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='mockup_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $mimgpicture = current($upload->operands);
                $mimgpicture = $mimgpicture["file_path"];
                Image::set($sfolder . $mimgpicture, $sfolder . "thumb" . DS, false, $sthmbsizing["width"], $sthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
            }

            if ($category) {
                $categories = $category;
                $category = $categories[0];
            } else {
                $categories = '';
                $category = 0;
            }

            $set_data = [
                'type'       => "references",
                'ctime'      => DateManager::Now(),
                'status'     => $status,
                'category'   => $category,
                'categories' => $categories ? implode(",", $categories) : '',
                'visibility' => $visibility,
                'rank'       => $rank,
                'options'    => Utility::jencode($options),
            ];

            $insert = $this->model->insert_page($set_data);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error4"),
                ]));

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $data["owner_id"] = $insert;
                    if (!$data["route"]) $data["route"] = $insert;
                    $this->model->insert_page_lang($data);
                }
            }

            if (isset($hpicture) && $hpicture) $this->model->insert_picture("page_references", $insert, "header-background", $hpicture);
            if (isset($limgpicture) && $limgpicture) $this->model->insert_picture("page_references", $insert, "cover", $limgpicture);
            if (isset($mimgpicture) && $mimgpicture) $this->model->insert_picture("page_references", $insert, "mockup", $mimgpicture);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-references-page", [
                'id'   => $insert,
                'name' => $lang_data[$locall]["title"],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success1-references"),
                'redirect' => $this->AdminCRLink("manage-website-1", ["references"]),
            ]);

        }


        private function add_slide()
        {
            $this->takeDatas("language");

            Helper::Load(["Money"]);

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $titles = Filter::POST("title");
            $descriptions = Filter::POST("description");
            $links = Filter::POST("link");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $status = Filter::init("POST/status", "letters");
            $picture = Filter::FILES("picture");
            $video = Filter::FILES("video");
            $video_duration = Filter::init("POST/video_duration", "amount");
            $video_duration = Money::deformatter($video_duration);


            if (!$picture)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error6"),
                ]));

            if ($video && !$video_duration)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error12"),
                ]));

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $description = isset($descriptions[$lkey]) ? $descriptions[$lkey] : false;
                $link = isset($links[$lkey]) ? $links[$lkey] : false;

                $lang_data[$lkey] = [
                    'owner_id'    => 0,
                    'lang'        => $lkey,
                    'title'       => $title,
                    'description' => $description,
                    'link'        => $link,
                ];
            }

            $extra = [];


            if ($picture) {
                Helper::Load(["Uploads", "Image"]);
                $folder = Config::get("pictures/slides/folder");
                $sizing = Config::get("pictures/slides/sizing");
                $thmbsizing = Config::get("pictures/slides/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($picture, [
                    'image-upload' => true,
                    'folder'       => $folder,
                    'width'        => $sizing["width"],
                    'height'       => $sizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='picture']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $picture = current($upload->operands);
                $picture = $picture["file_path"];
                Image::set($folder . $picture, $folder . "thumb" . DS, false, $thmbsizing["width"], $thmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
            }

            if ($video) {
                Helper::Load(["Uploads"]);
                $folder = Config::get("pictures/slides/folder");
                $upload = Helper::get("Uploads");
                $upload->init($video, [
                    'image-upload' => false,
                    'folder'       => $folder,
                    'allowed-ext'  => "mp4",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='video']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $video = current($upload->operands);
                $video = $video["file_path"];
                $extra["video"] = [
                    'file'     => $video,
                    'duration' => $video_duration,
                ];
            }

            $set_data = [
                'ctime'  => DateManager::Now(),
                'status' => $status,
                'rank'   => $rank,
                'extra'  => $extra ? Utility::jencode($extra) : '',
            ];

            $insert = $this->model->insert_slide($set_data);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error4"),
                ]));

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $data["owner_id"] = $insert;
                    $this->model->insert_slide_lang($data);
                }
            }

            if (isset($picture) && $picture) $this->model->insert_picture("slides", $insert, "main-image", $picture);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-slide", [
                'id'   => $insert,
                'name' => $lang_data[$locall]["title"],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success9"),
                'redirect' => $this->AdminCRLink("manage-website-1", ["slides"]),
            ]);

        }


        private function add_cfeedback()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $full_name = Filter::init("POST/full_name", "hclear");
            $company_name = Filter::init("POST/company_name", "hclear");
            $email = Filter::init("POST/email", "email");
            $messages = Filter::POST("message");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $status = Filter::init("POST/status", "letters");
            $picture = Filter::FILES("picture");


            if (Validation::isEmpty($full_name))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name=full_name]",
                    'message' => __("admin/manage-website/error7"),
                ]));

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $message = isset($messages[$lkey]) ? $messages[$lkey] : false;

                if (Validation::isEmpty($message))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "textarea[name='message[" . $lkey . "]']",
                        'message' => __("admin/manage-website/error8", ['{lang}' => $lkeyup]),
                    ]));


                $lang_data[$lkey] = [
                    'owner_id' => 0,
                    'lang'     => $lkey,
                    'message'  => $message,
                ];
            }

            if ($picture) {
                Helper::Load(["Uploads", "Image"]);
                $folder = Config::get("pictures/customer-feedback/folder");
                $sizing = Config::get("pictures/customer-feedback/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($picture, [
                    'image-upload' => true,
                    'folder'       => $folder,
                    'width'        => $sizing["width"],
                    'height'       => $sizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='picture']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $picture = current($upload->operands);
                $picture = $picture["file_path"];
            }

            $set_data = [
                'full_name'    => $full_name,
                'company_name' => $company_name,
                'email'        => $email,
                'ctime'        => DateManager::Now(),
                'status'       => $status,
                'rank'         => $rank,
            ];

            $insert = $this->model->insert_cfeedback($set_data);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error4"),
                ]));

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $data["owner_id"] = $insert;
                    $this->model->insert_cfeedback_lang($data);
                }
            }

            if (isset($picture) && $picture) $this->model->insert_picture("customer_feedback", $insert, "image", $picture);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-cfeedback", [
                'id'   => $insert,
                'name' => implode(" - ", [$full_name, $company_name]),
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success12"),
                'redirect' => $this->AdminCRLink("manage-website-1", ["cfeedbacks"]),
            ]);

        }


        private function add_menu()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $type = Filter::init("POST/type", "route");
            $icon = Filter::init("POST/icon", "hclear");
            $onlyCa = (int)Filter::init("POST/onlyCa", "numbers");
            $page = Filter::init("POST/page", "route", "\/");
            $target = (int)Filter::init("POST/target", "numbers");
            $titles = Filter::POST("title");
            $links = Filter::POST("link");
            $megas = Filter::POST("mega");
            $tags = Filter::POST("tag");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];
            $header_type = Config::get("theme/header-type");


            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $link = isset($links[$lkey]) ? Filter::html_clear($links[$lkey]) : false;
                $mega = isset($megas[$lkey]) ? $megas[$lkey] : false;
                $tag = isset($tags[$lkey]) ? $tags[$lkey] : false;


                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/manage-website/error2", ['{lang}' => $lkeyup]),
                    ]));

                $extra = [];

                if ($mega) $extra["mega"]["header_" . $header_type] = $mega;
                if (isset($tag["name"]) && $tag["name"]) $extra["tag"] = $tag;

                $lang_data[$lkey] = [
                    'owner_id' => 0,
                    'lang'     => $lkey,
                    'title'    => $title,
                    'link'     => $link,
                    'extra'    => $extra ? Utility::jencode($extra) : '',
                ];
            }

            $set_data = [
                'type'   => $type,
                'icon'   => $icon,
                'onlyCa' => $onlyCa,
                'target' => $target,
                'page'   => $page,
                'status' => "inactive",
            ];

            $insert = $this->model->insert_menu($set_data);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error4"),
                ]));

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $data["owner_id"] = $insert;
                    $this->model->insert_menu_lang($data);
                }
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-menu", [
                'id'   => $insert,
                'name' => $titles[$locall],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/manage-website/success15"),
                'type'    => $type,
            ]);

        }


        private function edit_normal_page()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $page = $this->model->get_page($id);
            if (!$page) die();


            $slug = Filter::init("GET/slug", "route");
            $lang = Filter::init("GET/lang", "route");
            if ($slug && $lang && ___("package/permalink", false, $lang)) {
                $check = $this->model->page_route_check($slug, $lang);
                if ($check && $check != $page["id"])
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='route[" . $lang . "]']",
                        'message' => __("admin/manage-website/error1"),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $contents = Filter::POST("content");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $status = Filter::init("POST/status", "letters");
            $category = Filter::init("POST/category", "rnumbers");
            $sidebar = Filter::init("POST/sidebar", "numbers");
            if ($sidebar) $sidebar = "disable";
            elseif (!$sidebar && (!$page["sidebar"] || !$page["sidebar"] == "disable")) $sidebar = 'enable';
            else $sidebar = '';
            $invisibility = Filter::init("POST/invisibility", "numbers");
            if ($invisibility) $visibility = "invisible";
            else $visibility = "visible";
            $hbackground = Filter::FILES("hbackground");
            $list_image = Filter::FILES("list_image");

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $route = isset($routes[$lkey]) ? Filter::html_clear($routes[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;


                $lopt = [];

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/manage-website/error2", ['{lang}' => $lkeyup]),
                    ]));

                $ldata = $this->model->get_page_lang($id, $lkey);

                if (___("package/permalink", false, $lkey)) {
                    $route = !$route || $id == $route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->page_route_check($route, $lkey);
                        if ($check && $check != $page["id"])
                            die(Utility::jencode([
                                'status'      => "error",
                                'lang'        => $lkey,
                                'route_check' => true,
                                'route'       => $route,
                                'for'         => "input[name='route[" . $lkey . "]']",
                            ]));
                    } else $route = $id;
                } else $route = $id;

                $lang_data[$lkey] = [
                    'id'              => $ldata ? $ldata["id"] : 0,
                    'owner_id'        => $id,
                    'lang'            => $lkey,
                    'title'           => $title,
                    'route'           => $route,
                    'content'         => $content,
                    'seo_title'       => $seo_title,
                    'seo_keywords'    => $seo_keywords,
                    'seo_description' => $seo_description,
                    'options'         => $lopt ? Utility::jencode($lopt) : '',
                ];
            }

            $options = [];

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
                $hthmbsizing = Config::get("pictures/header-background/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($hbackground, [
                    'image-upload' => true,
                    'folder'       => $hfolder,
                    'width'        => $hsizing["width"],
                    'height'       => $hsizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='hbackground']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, $hthmbsizing["width"], $hthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
                $before_pic = $this->model->get_picture("page_" . $page["type"], $id, "header-background");
                if ($before_pic) {
                    FileManager::file_delete($hfolder . $before_pic);
                    FileManager::file_delete($hfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("page_" . $page["type"], $id, "header-background");
                }
                $this->model->insert_picture("page_" . $page["type"], $id, "header-background", $hpicture);
            }

            if ($list_image) {
                Helper::Load(["Uploads", "Image"]);
                $sfolder = Config::get("pictures/page-" . $page["type"] . "/folder");
                $ssizing = Config::get("pictures/page-" . $page["type"] . "/list/sizing");
                $sthmbsizing = Config::get("pictures/page-" . $page["type"] . "/list/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($list_image, [
                    'image-upload' => true,
                    'folder'       => $sfolder,
                    'width'        => $ssizing["width"],
                    'height'       => $ssizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='list_image']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $limgpicture = current($upload->operands);
                $limgpicture = $limgpicture["file_path"];
                Image::set($sfolder . $limgpicture, $sfolder . "thumb" . DS, false, $sthmbsizing["width"], $sthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
                $before_pic = $this->model->get_picture("page_" . $page["type"], $id, "cover");
                if ($before_pic) {
                    FileManager::file_delete($sfolder . $before_pic);
                    FileManager::file_delete($sfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("page_" . $page["type"], $id, "cover");
                }
                $this->model->insert_picture("page_" . $page["type"], $id, "cover", $limgpicture);
            }

            $set_data = [
                'status'     => $status,
                'category'   => $category,
                'sidebar'    => $sidebar,
                'visibility' => $visibility,
                'options'    => Utility::jencode($options),
            ];

            $this->model->set_page($id, $set_data);

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $lang_id = $data["id"];
                    unset($data["id"]);
                    if ($lang_id) $this->model->set_page_lang($lang_id, $data);
                    if (!$lang_id) $this->model->insert_page_lang($data);
                }
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-" . $page["type"] . "-page", [
                'id'   => $id,
                'name' => $page["title"],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success3-" . $page["type"]),
                'redirect' => $this->AdminCRLink("manage-website-1", ["pages"]),
            ]);
        }


        private function edit_news_page()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $page = $this->model->get_page($id);
            if (!$page) die();


            $slug = Filter::init("GET/slug", "route");
            $lang = Filter::init("GET/lang", "route");
            if ($slug && $lang && ___("package/permalink", false, $lang)) {
                $check = $this->model->page_route_check($slug, $lang);
                if ($check && $check != $page["id"])
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='route[" . $lang . "]']",
                        'message' => __("admin/manage-website/error1"),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $contents = Filter::POST("content");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $status = Filter::init("POST/status", "letters");
            $category = Filter::init("POST/category", "rnumbers");
            $hide_comments = (int)Filter::init("POST/hide_comments", "numbers");
            $sidebar = Filter::init("POST/sidebar", "numbers");
            if ($sidebar) $sidebar = "disable";
            elseif (!$sidebar && (!$page["sidebar"] || !$page["sidebar"] == "disable")) $sidebar = 'enable';
            else $sidebar = '';
            $visibility = Filter::init("POST/visibility", "numbers");
            if ($visibility) $visibility = "visible";
            else $visibility = "invisible";
            $visible_to_user = (int)Filter::init("POST/visible_to_user", "numbers");
            $hbackground = Filter::FILES("hbackground");
            $list_image = Filter::FILES("list_image");

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $route = isset($routes[$lkey]) ? Filter::html_clear($routes[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;


                $lopt = [];

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/manage-website/error2", ['{lang}' => $lkeyup]),
                    ]));

                $ldata = $this->model->get_page_lang($id, $lkey);

                if (___("package/permalink", false, $lkey)) {
                    $route = !$route || $id == $route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->page_route_check($route, $lkey);
                        if ($check && $check != $page["id"])
                            die(Utility::jencode([
                                'status'      => "error",
                                'lang'        => $lkey,
                                'route_check' => true,
                                'route'       => $route,
                                'for'         => "input[name='route[" . $lkey . "]']",
                            ]));
                    } else $route = $id;
                } else $route = $id;

                $lang_data[$lkey] = [
                    'id'              => $ldata ? $ldata["id"] : 0,
                    'owner_id'        => $id,
                    'lang'            => $lkey,
                    'title'           => $title,
                    'route'           => $route,
                    'content'         => $content,
                    'seo_title'       => $seo_title,
                    'seo_keywords'    => $seo_keywords,
                    'seo_description' => $seo_description,
                    'options'         => $lopt ? Utility::jencode($lopt) : '',
                ];
            }

            $options = [
                'hide_comments' => $hide_comments,
            ];

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
                $hthmbsizing = Config::get("pictures/header-background/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($hbackground, [
                    'image-upload' => true,
                    'folder'       => $hfolder,
                    'width'        => $hsizing["width"],
                    'height'       => $hsizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='hbackground']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, $hthmbsizing["width"], $hthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
                $before_pic = $this->model->get_picture("page_" . $page["type"], $id, "header-background");
                if ($before_pic) {
                    FileManager::file_delete($hfolder . $before_pic);
                    FileManager::file_delete($hfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("page_" . $page["type"], $id, "header-background");
                }
                $this->model->insert_picture("page_" . $page["type"], $id, "header-background", $hpicture);
            }

            if ($list_image) {
                Helper::Load(["Uploads", "Image"]);
                $sfolder = Config::get("pictures/page-" . $page["type"] . "/folder");
                $ssizing = Config::get("pictures/page-" . $page["type"] . "/list/sizing");
                $sthmbsizing = Config::get("pictures/page-" . $page["type"] . "/list/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($list_image, [
                    'image-upload' => true,
                    'folder'       => $sfolder,
                    'width'        => $ssizing["width"],
                    'height'       => $ssizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='list_image']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $limgpicture = current($upload->operands);
                $limgpicture = $limgpicture["file_path"];
                Image::set($sfolder . $limgpicture, $sfolder . "thumb" . DS, false, $sthmbsizing["width"], $sthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
                $before_pic = $this->model->get_picture("page_" . $page["type"], $id, "cover");
                if ($before_pic) {
                    FileManager::file_delete($sfolder . $before_pic);
                    FileManager::file_delete($sfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("page_" . $page["type"], $id, "cover");
                }
                $this->model->insert_picture("page_" . $page["type"], $id, "cover", $limgpicture);
            }

            $set_data = [
                'status'          => $status,
                'category'        => $category,
                'sidebar'         => $sidebar,
                'visibility'      => $visibility,
                'visible_to_user' => $visible_to_user,
                'options'         => Utility::jencode($options),
            ];

            $this->model->set_page($id, $set_data);

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $lang_id = $data["id"];
                    unset($data["id"]);
                    if ($lang_id) $this->model->set_page_lang($lang_id, $data);
                    if (!$lang_id) $this->model->insert_page_lang($data);
                }
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-" . $page["type"] . "-page", [
                'id'   => $id,
                'name' => $page["title"],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success3-" . $page["type"]),
                'redirect' => $this->AdminCRLink("manage-website-1", ["news"]),
            ]);
        }


        private function edit_blog_page()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $page = $this->model->get_page($id);
            if (!$page) die();


            $slug = Filter::init("GET/slug", "route");
            $lang = Filter::init("GET/lang", "route");
            if ($slug && $lang && ___("package/permalink", false, $lang)) {
                $check = $this->model->page_route_check($slug, $lang);
                if ($check && $check != $page["id"])
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='route[" . $lang . "]']",
                        'message' => __("admin/manage-website/error1"),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $contents = Filter::POST("content");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $status = Filter::init("POST/status", "letters");
            $category = Filter::init("POST/category", "rnumbers");
            $hide_comments = (int)Filter::init("POST/hide_comments", "numbers");
            $sidebar = Filter::init("POST/sidebar", "numbers");
            if ($sidebar) $sidebar = "disable";
            elseif (!$sidebar && (!$page["sidebar"] || !$page["sidebar"] == "disable")) $sidebar = 'enable';
            else $sidebar = '';
            $invisibility = Filter::init("POST/invisibility", "numbers");
            if ($invisibility) $visibility = "invisible";
            else $visibility = "visible";
            $hbackground = Filter::FILES("hbackground");
            $list_image = Filter::FILES("list_image");

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $route = isset($routes[$lkey]) ? Filter::html_clear($routes[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;


                $lopt = [];

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/manage-website/error2", ['{lang}' => $lkeyup]),
                    ]));

                $ldata = $this->model->get_page_lang($id, $lkey);

                if (___("package/permalink", false, $lkey)) {
                    $route = !$route || $id == $route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->page_route_check($route, $lkey);
                        if ($check && $check != $page["id"])
                            die(Utility::jencode([
                                'status'      => "error",
                                'lang'        => $lkey,
                                'route_check' => true,
                                'route'       => $route,
                                'for'         => "input[name='route[" . $lkey . "]']",
                            ]));
                    } else $route = $id;
                } else $route = $id;

                $lang_data[$lkey] = [
                    'id'              => $ldata ? $ldata["id"] : 0,
                    'owner_id'        => $id,
                    'lang'            => $lkey,
                    'title'           => $title,
                    'route'           => $route,
                    'content'         => $content,
                    'seo_title'       => $seo_title,
                    'seo_keywords'    => $seo_keywords,
                    'seo_description' => $seo_description,
                    'options'         => $lopt ? Utility::jencode($lopt) : '',
                ];
            }

            $options = [
                'hide_comments' => $hide_comments,
            ];

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
                $hthmbsizing = Config::get("pictures/header-background/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($hbackground, [
                    'image-upload' => true,
                    'folder'       => $hfolder,
                    'width'        => $hsizing["width"],
                    'height'       => $hsizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='hbackground']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, $hthmbsizing["width"], $hthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
                $before_pic = $this->model->get_picture("page_" . $page["type"], $id, "header-background");
                if ($before_pic) {
                    FileManager::file_delete($hfolder . $before_pic);
                    FileManager::file_delete($hfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("page_" . $page["type"], $id, "header-background");
                }
                $this->model->insert_picture("page_" . $page["type"], $id, "header-background", $hpicture);
            }

            if ($list_image) {
                Helper::Load(["Uploads", "Image"]);
                $sfolder = Config::get("pictures/page-" . $page["type"] . "/folder");
                $ssizing = Config::get("pictures/page-" . $page["type"] . "/list/sizing");
                $sthmbsizing = Config::get("pictures/page-" . $page["type"] . "/list/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($list_image, [
                    'image-upload' => true,
                    'folder'       => $sfolder,
                    'width'        => $ssizing["width"],
                    'height'       => $ssizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='list_image']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $limgpicture = current($upload->operands);
                $limgpicture = $limgpicture["file_path"];
                Image::set($sfolder . $limgpicture, $sfolder . "thumb" . DS, false, $sthmbsizing["width"], $sthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
                $before_pic = $this->model->get_picture("page_" . $page["type"], $id, "cover");
                if ($before_pic) {
                    FileManager::file_delete($sfolder . $before_pic);
                    FileManager::file_delete($sfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("page_" . $page["type"], $id, "cover");
                }
                $this->model->insert_picture("page_" . $page["type"], $id, "cover", $limgpicture);
            }

            $set_data = [
                'status'     => $status,
                'category'   => $category,
                'sidebar'    => $sidebar,
                'visibility' => $visibility,
                'options'    => Utility::jencode($options),
            ];

            $this->model->set_page($id, $set_data);

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $lang_id = $data["id"];
                    unset($data["id"]);
                    if ($lang_id) $this->model->set_page_lang($lang_id, $data);
                    if (!$lang_id) $this->model->insert_page_lang($data);
                }
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-" . $page["type"] . "-page", [
                'id'   => $id,
                'name' => $page["title"],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success3-" . $page["type"]),
                'redirect' => $this->AdminCRLink("manage-website-1", ["blogs"]),
            ]);
        }


        private function edit_blog_category()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $category = $this->model->get_category($id);
            if (!$category) die();

            $slug = Filter::init("GET/slug", "route");
            $lang = Filter::init("GET/lang", "route");
            if ($slug && $lang && ___("package/permalink", false, $lang)) {
                $check = $this->model->category_route_check($slug, $lang, "articles");
                if ($check && $check != $category["id"])
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/manage-website/error5"),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $sub_titles = Filter::POST("sub_title");
            $hbackground = Filter::FILES("hbackground");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $parent = (int)Filter::init("POST/parent", "numbers");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $status = Filter::init("POST/status", "letters");

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $route = isset($routes[$lkey]) ? Filter::html_clear($routes[$lkey]) : false;
                $sub_title = isset($sub_titles[$lkey]) ? Filter::html_clear($sub_titles[$lkey]) : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/manage-website/error2", ['{lang}' => strtoupper($lkey)]),
                    ]));

                if (___("package/permalink", false, $lkey)) {
                    $route = !$route || $id == $route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->category_route_check($route, $lkey, "articles");
                        if ($check && $check != $category["id"])
                            die(Utility::jencode([
                                'status'      => "error",
                                'lang'        => $lkey,
                                'route_check' => true,
                                'route'       => $route,
                                'for'         => "input[name='route[" . $lkey . "]']",
                            ]));
                    } else $route = $id;
                } else $route = $id;

                $ldata = $this->model->get_category_wlang($id, $lkey);

                $lopt = [];

                if ($title)
                    $lang_data[$lkey] = [
                        'id'              => $ldata ? $ldata["id"] : 0,
                        'owner_id'        => $category["id"],
                        'lang'            => $lkey,
                        'title'           => $title,
                        'route'           => $route ? $route : $id,
                        'sub_title'       => $sub_title,
                        'content'         => $content,
                        'seo_title'       => $seo_title,
                        'seo_keywords'    => $seo_keywords,
                        'seo_description' => $seo_description,
                        'options'         => $lopt ? Utility::jencode($lopt) : '',
                    ];
            }

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
                $hthmbsizing = Config::get("pictures/header-background/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($hbackground, [
                    'image-upload' => true,
                    'folder'       => $hfolder,
                    'width'        => $hsizing["width"],
                    'height'       => $hsizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='hbackground']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, $hthmbsizing["width"], $hthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
                $before_pic = $this->model->get_picture("category", $id, "header-background");
                if ($before_pic) {
                    FileManager::file_delete($hfolder . $before_pic);
                    FileManager::file_delete($hfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("category", $id, "header-background");
                }
                $this->model->insert_picture("category", $id, "header-background", $hpicture);
            }


            if (!$lang_data) die("Error! #1");

            $options = Utility::jencode([]);

            $update = $this->model->set_category($id, [
                'status'  => $status,
                'parent'  => $parent,
                'rank'    => $rank,
                'options' => $options,
            ]);

            if (!$update)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error4"),
                ]));

            foreach ($lang_data as $data) {
                $data_id = $data["id"];
                unset($data["id"]);
                if ($data_id)
                    $this->model->set_category_lang($data_id, $data);
                else
                    $this->model->insert_category_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-article-category", [
                'name' => $category["title"],
                'id'   => $id,
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success7"),
                'redirect' => $this->AdminCRLink("manage-website-2", ["blogs", "categories"]),
            ]);
        }


        private function edit_reference_page()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $page = $this->model->get_page($id);
            if (!$page) die();


            $slug = Filter::init("GET/slug", "route");
            $lang = Filter::init("GET/lang", "route");
            if ($slug && $lang && ___("package/permalink", false, $lang)) {
                $check = $this->model->page_route_check($slug, $lang);
                if ($check && $check != $page["id"])
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='route[" . $lang . "]']",
                        'message' => __("admin/manage-website/error1"),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $contents = Filter::POST("content");
            $featured_infos = Filter::POST("featured-info");
            $technical_infos = Filter::POST("technical-info");
            $website = Filter::init("POST/website", "hclear");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $status = Filter::init("POST/status", "letters");
            $category = Filter::init("POST/category");
            $invisibility = Filter::init("POST/invisibility", "numbers");
            if ($invisibility) $visibility = "invisible";
            else $visibility = "visible";
            $rank = Filter::init("POST/rank", "numbers");
            $hbackground = Filter::FILES("hbackground");
            $list_image = Filter::FILES("list_image");
            $mockup_image = Filter::FILES("mockup_image");

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $route = isset($routes[$lkey]) ? Filter::html_clear($routes[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $featured_info = isset($featured_infos[$lkey]) ? $featured_infos[$lkey] : false;
                $technical_info = isset($technical_infos[$lkey]) ? $technical_infos[$lkey] : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;


                $lopt = [
                    'featured-info'  => $featured_info,
                    'technical-info' => $technical_info,
                ];

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/manage-website/error2", ['{lang}' => $lkeyup]),
                    ]));

                $ldata = $this->model->get_page_lang($id, $lkey);

                if (___("package/permalink", false, $lkey)) {
                    $route = !$route || $id == $route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->page_route_check($route, $lkey);
                        if ($check && $check != $page["id"])
                            die(Utility::jencode([
                                'status'      => "error",
                                'lang'        => $lkey,
                                'route_check' => true,
                                'route'       => $route,
                                'for'         => "input[name='route[" . $lkey . "]']",
                            ]));
                    } else $route = $id;
                } else $route = $id;

                $lang_data[$lkey] = [
                    'id'              => $ldata ? $ldata["id"] : 0,
                    'owner_id'        => $id,
                    'lang'            => $lkey,
                    'title'           => $title,
                    'route'           => $route,
                    'content'         => $content,
                    'seo_title'       => $seo_title,
                    'seo_keywords'    => $seo_keywords,
                    'seo_description' => $seo_description,
                    'options'         => $lopt ? Utility::jencode($lopt) : '',
                ];
            }

            $options = [
                'website' => $website,
            ];

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
                $hthmbsizing = Config::get("pictures/header-background/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($hbackground, [
                    'image-upload' => true,
                    'folder'       => $hfolder,
                    'width'        => $hsizing["width"],
                    'height'       => $hsizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='hbackground']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, $hthmbsizing["width"], $hthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
                $before_pic = $this->model->get_picture("page_" . $page["type"], $id, "header-background");
                if ($before_pic) {
                    FileManager::file_delete($hfolder . $before_pic);
                    FileManager::file_delete($hfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("page_" . $page["type"], $id, "header-background");
                }
                $this->model->insert_picture("page_" . $page["type"], $id, "header-background", $hpicture);
            }

            if ($list_image) {
                Helper::Load(["Uploads", "Image"]);
                $sfolder = Config::get("pictures/page-" . $page["type"] . "/folder");
                $ssizing = Config::get("pictures/page-" . $page["type"] . "/list/sizing");
                $sthmbsizing = Config::get("pictures/page-" . $page["type"] . "/list/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($list_image, [
                    'image-upload' => true,
                    'folder'       => $sfolder,
                    'width'        => $ssizing["width"],
                    'height'       => $ssizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='list_image']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $limgpicture = current($upload->operands);
                $limgpicture = $limgpicture["file_path"];
                Image::set($sfolder . $limgpicture, $sfolder . "thumb" . DS, false, $sthmbsizing["width"], $sthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
                $before_pic = $this->model->get_picture("page_" . $page["type"], $id, "cover");
                if ($before_pic) {
                    FileManager::file_delete($sfolder . $before_pic);
                    FileManager::file_delete($sfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("page_" . $page["type"], $id, "cover");
                }
                $this->model->insert_picture("page_" . $page["type"], $id, "cover", $limgpicture);
            }

            if ($mockup_image) {
                Helper::Load(["Uploads", "Image"]);
                $sfolder = Config::get("pictures/page-" . $page["type"] . "/folder");
                $ssizing = Config::get("pictures/page-" . $page["type"] . "/mockup/sizing");
                $sthmbsizing = Config::get("pictures/page-" . $page["type"] . "/mockup/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($mockup_image, [
                    'image-upload' => true,
                    'folder'       => $sfolder,
                    'width'        => $ssizing["width"],
                    'height'       => $ssizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='list_image']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $mimgpicture = current($upload->operands);
                $mimgpicture = $mimgpicture["file_path"];
                Image::set($sfolder . $mimgpicture, $sfolder . "thumb" . DS, false, $sthmbsizing["width"], $sthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
                $before_pic = $this->model->get_picture("page_" . $page["type"], $id, "mockup");
                if ($before_pic) {
                    FileManager::file_delete($sfolder . $before_pic);
                    FileManager::file_delete($sfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("page_" . $page["type"], $id, "mockup");
                }
                $this->model->insert_picture("page_" . $page["type"], $id, "mockup", $mimgpicture);
            }

            if ($category) {
                $categories = $category;
                $category = $categories[0];
            } else {
                $categories = '';
                $category = 0;
            }

            $set_data = [
                'status'     => $status,
                'category'   => $category,
                'categories' => $categories ? implode(",", $categories) : '',
                'visibility' => $visibility,
                'rank'       => $rank,
                'options'    => Utility::jencode($options),
            ];

            $this->model->set_page($id, $set_data);

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $lang_id = $data["id"];
                    unset($data["id"]);
                    if ($lang_id) $this->model->set_page_lang($lang_id, $data);
                    if (!$lang_id) $this->model->insert_page_lang($data);
                }
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-" . $page["type"] . "-page", [
                'id'   => $id,
                'name' => $page["title"],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success3-" . $page["type"]),
                'redirect' => $this->AdminCRLink("manage-website-1", ["references"]),
            ]);
        }


        private function edit_reference_category()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $category = $this->model->get_category($id);
            if (!$category) die();

            $slug = Filter::init("GET/slug", "route");
            $lang = Filter::init("GET/lang", "route");
            if ($slug && $lang && ___("package/permalink", false, $lang)) {
                $check = $this->model->category_route_check($slug, $lang, "references");
                if ($check && $check != $category["id"])
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/manage-website/error5"),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $sub_titles = Filter::POST("sub_title");
            $hbackground = Filter::FILES("hbackground");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $parent = (int)Filter::init("POST/parent", "numbers");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $status = Filter::init("POST/status", "letters");

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $route = isset($routes[$lkey]) ? Filter::html_clear($routes[$lkey]) : false;
                $sub_title = isset($sub_titles[$lkey]) ? Filter::html_clear($sub_titles[$lkey]) : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/manage-website/error2", ['{lang}' => strtoupper($lkey)]),
                    ]));

                if (___("package/permalink", false, $lkey)) {
                    $route = !$route || $id == $route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->category_route_check($route, $lkey, "references");
                        if ($check && $check != $category["id"])
                            die(Utility::jencode([
                                'status'      => "error",
                                'lang'        => $lkey,
                                'route_check' => true,
                                'route'       => $route,
                                'for'         => "input[name='route[" . $lkey . "]']",
                            ]));
                    } else $route = $id;
                } else $route = $id;

                $ldata = $this->model->get_category_wlang($id, $lkey);

                $lopt = [];

                if ($title)
                    $lang_data[$lkey] = [
                        'id'              => $ldata ? $ldata["id"] : 0,
                        'owner_id'        => $category["id"],
                        'lang'            => $lkey,
                        'title'           => $title,
                        'route'           => $route ? $route : $id,
                        'sub_title'       => $sub_title,
                        'content'         => $content,
                        'seo_title'       => $seo_title,
                        'seo_keywords'    => $seo_keywords,
                        'seo_description' => $seo_description,
                        'options'         => $lopt ? Utility::jencode($lopt) : '',
                    ];
            }

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
                $hthmbsizing = Config::get("pictures/header-background/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($hbackground, [
                    'image-upload' => true,
                    'folder'       => $hfolder,
                    'width'        => $hsizing["width"],
                    'height'       => $hsizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='hbackground']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, $hthmbsizing["width"], $hthmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
                $before_pic = $this->model->get_picture("category", $id, "header-background");
                if ($before_pic) {
                    FileManager::file_delete($hfolder . $before_pic);
                    FileManager::file_delete($hfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("category", $id, "header-background");
                }
                $this->model->insert_picture("category", $id, "header-background", $hpicture);
            }


            if (!$lang_data) die("Error! #1");

            $options = Utility::jencode([]);

            $update = $this->model->set_category($id, [
                'status'  => $status,
                'parent'  => $parent,
                'rank'    => $rank,
                'options' => $options,
            ]);

            if (!$update)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error4"),
                ]));

            foreach ($lang_data as $data) {
                $data_id = $data["id"];
                unset($data["id"]);
                if ($data_id)
                    $this->model->set_category_lang($data_id, $data);
                else
                    $this->model->insert_category_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-reference-category", [
                'name' => $category["title"],
                'id'   => $id,
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success7"),
                'redirect' => $this->AdminCRLink("manage-website-2", ["references", "categories"]),
            ]);
        }


        private function edit_slide()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $slide = $this->model->get_slide($id);
            if (!$slide) die();

            if ($slide["extra"]) $slide["extra"] = $slide["extra"] ? Utility::jdecode($slide["extra"], true) : [];

            Helper::Load(["Money"]);

            $titles = Filter::POST("title");
            $descriptions = Filter::POST("description");
            $links = Filter::POST("link");
            $status = Filter::init("POST/status", "letters");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $picture = Filter::FILES("picture");
            $video = Filter::FILES("video");
            $video_duration = Filter::init("POST/video_duration", "amount");
            $video_duration = Money::deformatter($video_duration);

            if ($video && !$video_duration)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error12"),
                ]));


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $description = isset($descriptions[$lkey]) ? $descriptions[$lkey] : false;
                $link = isset($links[$lkey]) ? $links[$lkey] : false;

                $ldata = $this->model->get_slide_lang($id, $lkey);

                $lang_data[$lkey] = [
                    'id'          => $ldata ? $ldata["id"] : 0,
                    'owner_id'    => $id,
                    'lang'        => $lkey,
                    'title'       => $title,
                    'description' => $description,
                    'link'        => $link,
                ];
            }

            $extra = $slide["extra"];

            if (!$extra) $extra = [];


            if ((isset($extra["video"]) || $video) && $video_duration) $extra["video"]["duration"] = $video_duration;
            elseif (isset($extra["video"]["duration"])) unset($extra["video"]["duration"]);

            if ($picture) {
                Helper::Load(["Uploads", "Image"]);
                $folder = Config::get("pictures/slides/folder");
                $sizing = Config::get("pictures/slides/sizing");
                $thmbsizing = Config::get("pictures/slides/thumb");
                $upload = Helper::get("Uploads");
                $upload->init($picture, [
                    'image-upload' => true,
                    'folder'       => $folder,
                    'width'        => $sizing["width"],
                    'height'       => $sizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='picture']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $picture = current($upload->operands);
                $picture = $picture["file_path"];
                Image::set($folder . $picture, $folder . "thumb" . DS, false, $thmbsizing["width"], $thmbsizing["height"], [
                    'folder-date-detect' => true,
                ]);
                $before_pic = $this->model->get_picture("slides", $id, "main-image");
                if ($before_pic) {
                    FileManager::file_delete($folder . $before_pic);
                    FileManager::file_delete($folder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("slides", $id, "main-image");
                }
                $this->model->insert_picture("slides", $id, "main-image", $picture);
            }

            if ($video) {
                Helper::Load(["Uploads"]);
                $folder = Config::get("pictures/slides/folder");
                $upload = Helper::get("Uploads");
                $upload->init($video, [
                    'image-upload' => false,
                    'folder'       => $folder,
                    'allowed-ext'  => "mp4",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='video']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));

                $video = current($upload->operands);
                $video = $video["file_path"];
                if (isset($extra["video"]["file"])) FileManager::file_delete($folder . $extra["video"]["file"]);
                $extra["video"]["file"] = $video;
            }

            $set_data = [
                'rank'   => $rank,
                'status' => $status,
                'extra'  => $extra ? Utility::jencode($extra) : '',
            ];

            $this->model->set_slide($id, $set_data);

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $lang_id = $data["id"];
                    unset($data["id"]);
                    if ($lang_id) $this->model->set_slide_lang($lang_id, $data);
                    if (!$lang_id) $this->model->insert_slide_lang($data);
                }
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-slide", [
                'id'   => $id,
                'name' => $slide["title"],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success11"),
                'redirect' => $this->AdminCRLink("manage-website-1", ["slides"]),
            ]);
        }

        private function delete_slide_video()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $slide = $this->model->get_slide($id);
            if (!$slide) die();

            if ($slide["extra"]) $slide["extra"] = $slide["extra"] ? Utility::jdecode($slide["extra"], true) : [];

            $extra = $slide["extra"];

            $folder = Config::get("pictures/slides/folder");

            if (isset($extra["video"]["duration"])) unset($extra["video"]["duration"]);
            if (isset($extra["video"]["file"])) FileManager::file_delete($folder . $extra["video"]["file"]);
            if (isset($extra["video"])) unset($extra["video"]);

            $set_data = ['extra' => $extra ? Utility::jencode($extra) : ''];

            $this->model->set_slide($id, $set_data);


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-slide", [
                'id'   => $id,
                'name' => $slide["title"],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status' => "successful",
            ]);
        }


        private function edit_cfeedback()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $cfeedback = $this->model->get_cfeedback($id);
            if (!$cfeedback) die();


            $full_name = Filter::init("POST/full_name", "hclear");
            $company_name = Filter::init("POST/company_name", "hclear");
            $email = Filter::init("POST/email", "email");
            $messages = Filter::POST("message");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $status = Filter::init("POST/status", "letters");
            $picture = Filter::FILES("picture");


            if (Validation::isEmpty($full_name))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name=full_name]",
                    'message' => __("admin/manage-website/error7"),
                ]));

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $message = isset($messages[$lkey]) ? $messages[$lkey] : false;

                if (Validation::isEmpty($message))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "textarea[name='message[" . $lkey . "]']",
                        'message' => __("admin/manage-website/error8", ['{lang}' => $lkeyup]),
                    ]));


                $ldata = $this->model->get_cfeedback_lang($id, $lkey);

                $lang_data[$lkey] = [
                    'id'       => $ldata ? $ldata["id"] : 0,
                    'owner_id' => $id,
                    'lang'     => $lkey,
                    'message'  => $message,
                ];
            }

            if ($picture) {
                Helper::Load(["Uploads", "Image"]);
                $folder = Config::get("pictures/customer-feedback/folder");
                $sizing = Config::get("pictures/customer-feedback/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($picture, [
                    'image-upload' => true,
                    'folder'       => $folder,
                    'width'        => $sizing["width"],
                    'height'       => $sizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='picture']",
                        'message' => __("admin/manage-website/error3", ['{error}' => $upload->error]),
                    ]));
                $picture = current($upload->operands);
                $picture = $picture["file_path"];

                $before_pic = $this->model->get_picture("customer_feedback", $id, "image");
                if ($before_pic) {
                    FileManager::file_delete($folder . $before_pic);
                    $this->model->delete_picture("customer_feedback", $id, "image");
                }
                $this->model->insert_picture("customer_feedback", $id, "image", $picture);
            }

            $set_data = [
                'full_name'    => $full_name,
                'company_name' => $company_name,
                'email'        => $email,
                'rank'         => $rank,
                'status'       => $status,
            ];

            $this->model->set_cfeedback($id, $set_data);

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $lang_id = $data["id"];
                    unset($data["id"]);
                    if ($lang_id) $this->model->set_cfeedback_lang($lang_id, $data);
                    if (!$lang_id) $this->model->insert_cfeedback_lang($data);
                }
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-cfeedback", [
                'id'   => $id,
                'name' => $cfeedback["name"],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success14"),
                'redirect' => $this->AdminCRLink("manage-website-1", ["cfeedbacks"]),
            ]);
        }


        private function edit_menu()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            $menu = $this->model->get_menu($id);
            if (!$menu) die();

            $page = Filter::init("POST/page", "route", "\/");
            $target = (int)Filter::init("POST/target", "numbers");
            $icon = Filter::init("POST/icon", "hclear");
            $onlyCa = (int)Filter::init("POST/onlyCa", "numbers");
            $titles = Filter::POST("title");
            $links = Filter::POST("link");
            $megas = Filter::POST("mega");
            $tags = Filter::POST("tag");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];
            $header_type = Config::get("theme/header-type");

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $link = isset($links[$lkey]) ? Filter::html_clear($links[$lkey]) : false;
                $mega = isset($megas[$lkey]) ? $megas[$lkey] : false;
                $tag = isset($tags[$lkey]) ? $tags[$lkey] : false;

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/manage-website/error2", ['{lang}' => $lkeyup]),
                    ]));


                $ldata = $this->model->get_menu_lang($id, $lkey);

                $extra = $ldata["extra"] ? Utility::jdecode($ldata["extra"], true) : [];

                if ($mega) $extra["mega"]["header_" . $header_type] = $mega;
                elseif (isset($extra["mega"]["header_" . $header_type])) unset($extra["mega"]["header_" . $header_type]);

                if (isset($tag["name"]) && $tag["name"]) $extra["tag"] = $tag;
                elseif (isset($extra["tag"])) unset($extra["tag"]);

                $lang_data[$lkey] = [
                    'id'       => $ldata ? $ldata["id"] : 0,
                    'owner_id' => $id,
                    'lang'     => $lkey,
                    'title'    => $title,
                    'link'     => $link,
                    'extra'    => $extra ? Utility::jencode($extra) : '',
                ];
            }

            $set_data = [
                'icon'   => $icon,
                'target' => $target,
                'page'   => $page,
                'onlyCa' => $onlyCa,
            ];

            $this->model->set_menu($id, $set_data);

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $lang_id = $data["id"];
                    unset($data["id"]);
                    if ($lang_id) $this->model->set_menu_lang($lang_id, $data);
                    if (!$lang_id) $this->model->insert_menu_lang($data);
                }
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-menu", [
                'id'   => $id,
                'name' => $menu["title"],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/manage-website/success17"),
                'type'    => $menu["type"],
                'stat'    => $menu["status"],
            ]);
        }


        private function delete_page()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) return false;

            $page = $this->model->get_page($id);
            if (!$page) return false;

            $delete = $this->model->delete_page($page["type"], $id);

            if (!$delete)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error4"),
                ]));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "delete", "deleted-" . $page["type"] . "-page", [
                'id'   => $id,
                'name' => $page["title"],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/manage-website/success2-" . $page["type"]),
            ]);
        }


        private function delete_slide()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) return false;

            $slide = $this->model->get_slide($id);
            if (!$slide) return false;

            $extra = $slide["extra"] ? Utility::jdecode($slide["extra"], true) : [];

            if (isset($extra["video"]["file"]))
                FileManager::file_delete(Config::get("pictures/slides/folder") . $extra["video"]["file"]);

            $delete = $this->model->delete_slide($id);

            if (!$delete)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error4"),
                ]));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "delete", "deleted-slide", [
                'id'   => $id,
                'name' => $slide["title"],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/manage-website/success10"),
            ]);
        }


        private function delete_cfeedback()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) return false;

            $cfeedback = $this->model->get_cfeedback($id);
            if (!$cfeedback) return false;

            $delete = $this->model->delete_cfeedback($id);

            if (!$delete)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error4"),
                ]));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "delete", "deleted-cfeedback", [
                'id'   => $id,
                'name' => $cfeedback["name"],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/manage-website/success13"),
            ]);
        }


        private function delete_message()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = Filter::POST("id");
            if (!is_array($id)) $id = [$id];
            if (!$id) die();

            $id_size = sizeof($id);

            if ($id_size > 1) {
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

            $deleted = 0;

            $adata = UserManager::LoginData("admin");

            foreach ($id as $k => $v) {
                $v = (int)Filter::numbers($v);
                $delete = $this->model->delete_message($v);
                if ($delete) {
                    $deleted++;

                    User::addAction($adata["id"], "delete", "deleted-contact-message", [
                        'id' => $v,
                    ]);
                }
            }

            if (!$deleted)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error4"),
                ]));


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/manage-website/success20"),
            ]);
        }


        private function read_message()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) return false;

            $message = $this->model->get_message($id);
            if (!$message) return false;

            $this->model->set_message($id, ['unread' => 1]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "delete", "readed-contact-message", [
                'id' => $id,
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);
        }


        private function reply_message()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) return false;

            $message = $this->model->get_message($id);
            if (!$message) return false;

            $msg = Filter::POST("message");


            if (Validation::isEmpty($msg))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='subject']",
                    'message' => __("admin/manage-website/error11"),
                ]));


            $adata = UserManager::LoginData("admin");
            $adata = array_merge($adata, User::getInfo($adata["id"], ["signature"]));

            Helper::Load(["Notification"]);

            $send = Notification::contact_form_admin_reply([
                'visitor_name'      => $message["full_name"],
                'visitor_message'   => $message["message"],
                'signature'         => $adata["signature"],
                'admin_message'     => $msg,
                'message_send_date' => DateManager::format(Config::get("options/date-format") . " - H:i", $message["cdate"]),
                'email'             => $message["email"],
                'phone'             => $message["phone"],
                'lang'              => $message["lang"],
            ]);

            if ($send != "OK")
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/manage-website/error9", ['{error}' => print_r($send, true)]),
                ]));

            User::addAction($adata["id"], "sent", "replied-contact-message", [
                'id'            => $message["id"],
                'visitor_name'  => $message["full_name"],
                'visitor_email' => $message["email"],
            ]);

            $this->model->set_message($id, ['unread' => 1, 'admin_message' => $msg]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/manage-website/success21"),
            ]);

        }


        private function delete_category()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) return false;
            $cat = $this->model->get_category($id);
            if (!$cat) return false;


            $sub = $this->model->get_category_sub($id);
            $categories = array_merge([$id], $sub);

            foreach ($categories as $category) $this->model->delete_category($category);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "delete", "deleted-article-category", [
                'id'   => $id,
                'name' => $cat["title"],
            ]);

            self::$cache->clear();

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/manage-website/success6")]);
        }


        private function delete_menu()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) return false;
            $menu = $this->model->get_menu($id);
            if (!$menu) return false;


            $sub = $this->model->get_menu_sub($id);
            $menus = array_merge([$id], $sub);

            foreach ($menus as $mid) $this->model->delete_menu($mid);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "delete", "deleted-" . $menu["type"] . "-menu", [
                'id'   => $id,
                'name' => $menu["title"],
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/manage-website/success16"),
                'type'    => $menu["type"],
                'stat'    => $menu["status"],
            ]);
        }


        private function delete_page_hbackground()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) return false;

            $page = $this->model->get_page($id);
            if (!$page) return false;

            $hfolder = Config::get("pictures/header-background/folder");
            $before_pic = $this->model->get_picture("page_" . $page["type"], $id, "header-background");
            if ($before_pic) {
                FileManager::file_delete($hfolder . $before_pic);
                FileManager::file_delete($hfolder . "thumb" . DS . $before_pic);
                $this->model->delete_picture("page_" . $page["type"], $id, "header-background");

                self::$cache->clear();

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "deleted", "deleted-" . $page["type"] . "-page-header-background", [
                    'id'   => $id,
                    'name' => $page["title"],
                ]);
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/manage-website/success4")]);


        }


        private function delete_page_cover()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) return false;

            $page = $this->model->get_page($id);
            if (!$page) return false;

            $folder = Config::get("pictures/page-" . $page["type"] . "/folder");
            $before_pic = $this->model->get_picture("page_" . $page["type"], $id, "cover");
            if ($before_pic) {
                FileManager::file_delete($folder . $before_pic);
                FileManager::file_delete($folder . "thumb" . DS . $before_pic);
                $this->model->delete_picture("page_" . $page["type"], $id, "cover");

                self::$cache->clear();

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "deleted", "deleted-" . $page["type"] . "-page-cover", [
                    'id'   => $id,
                    'name' => $page["title"],
                ]);
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/manage-website/success4")]);
        }


        private function delete_page_mockup()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) return false;

            $page = $this->model->get_page($id);
            if (!$page) return false;

            $folder = Config::get("pictures/page-" . $page["type"] . "/folder");
            $before_pic = $this->model->get_picture("page_" . $page["type"], $id, "mockup");
            if ($before_pic) {
                FileManager::file_delete($folder . $before_pic);
                FileManager::file_delete($folder . "thumb" . DS . $before_pic);
                $this->model->delete_picture("page_" . $page["type"], $id, "mockup");

                self::$cache->clear();

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "deleted", "deleted-" . $page["type"] . "-page-mockup", [
                    'id'   => $id,
                    'name' => $page["title"],
                ]);
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/manage-website/success4")]);
        }


        private function delete_cfeedback_image()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) return false;

            $cfeedback = $this->model->get_cfeedback($id);
            if (!$cfeedback) return false;

            $folder = Config::get("pictures/customer-feedback/folder");
            $before_pic = $this->model->get_picture("customer_feedback", $id, "image");
            if ($before_pic) {
                FileManager::file_delete($folder . $before_pic);
                $this->model->delete_picture("customer_feedback", $id, "image");

                self::$cache->clear();

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "deleted", "deleted-cfeedback-image", [
                    'id'   => $id,
                    'name' => $cfeedback["name"],
                ]);
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/manage-website/success4")]);
        }


        private function delete_category_hbackground()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) return false;

            $category = $this->model->get_category($id);
            if (!$category) return false;

            $hfolder = Config::get("pictures/header-background/folder");
            $before_pic = $this->model->get_picture("category", $id, "header-background");
            if ($before_pic) {
                FileManager::file_delete($hfolder . $before_pic);
                FileManager::file_delete($hfolder . "thumb" . DS . $before_pic);
                $this->model->delete_picture("category", $id, "header-background");

                self::$cache->clear();

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "deleted", "deleted-article-category-header-background", [
                    'id'   => $id,
                    'name' => $category["title"],
                ]);
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/manage-website/success4")]);


        }


        private function settings_news()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $comment_embed_code = Filter::init("POST/comment_embed_code");

            $config_sets = [];

            if ($comment_embed_code != Config::get("options/news-comment-embed-code"))
                $config_sets["options"]["news-comment-embed-code"] = $comment_embed_code;


            if ($config_sets) {

                $changes = 0;

                if (isset($config_sets["options"])) {
                    $options_result = Config::set("options", $config_sets["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                    if ($write) $changes++;
                }

                if ($changes) {
                    $adata = UserManager::LoginData("admin");
                    User::addAction($adata["id"], "alteration", "changed-news-settings");
                }
            }

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success8"),
                'redirect' => $this->AdminCRLink("manage-website-1", ["news"]),
            ]);


        }


        private function settings_blog()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $comment_embed_code = Filter::init("POST/comment_embed_code");

            $config_sets = [];

            if ($comment_embed_code != Config::get("options/blog-comment-embed-code"))
                $config_sets["options"]["blog-comment-embed-code"] = $comment_embed_code;


            if ($config_sets) {

                $changes = 0;

                if (isset($config_sets["options"])) {
                    $options_result = Config::set("options", $config_sets["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                    if ($write) $changes++;
                }

                if ($changes) {
                    $adata = UserManager::LoginData("admin");
                    User::addAction($adata["id"], "alteration", "changed-news-settings");
                }
            }

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/manage-website/success8"),
                'redirect' => $this->AdminCRLink("manage-website-1", ["blogs"]),
            ]);
        }


        private function get_menu()
        {

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) return false;

            $get = $this->model->get_menu($id);
            if (!$get) return false;

            $result = [
                'type'   => $get["type"],
                'status' => $get["status"],
                'target' => $get["target"],
                'page'   => $get["page"],
                'parent' => $get["parent"],
                'icon'   => $get["icon"],
                'onlyCa' => $get["onlyCa"],
            ];

            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $header_type = Config::get("theme/header-type");

            foreach ($lang_list as $lang) {
                $data = $this->model->get_menu_lang($id, $lang["key"]);
                if ($data) {
                    $extra = isset($data["extra"]) && $data["extra"] ? Utility::jdecode($data["extra"], true) : [];

                    $mega = isset($extra["mega"]["header_" . $header_type]) ? $extra["mega"]["header_" . $header_type] : '';
                    $tag = isset($extra["tag"]["name"]) && $extra["tag"]["name"] ? $extra["tag"] : false;

                    $result["values"][$lang["key"]]["title"] = $data["title"];
                    $result["values"][$lang["key"]]["link"] = $data["link"];
                    $result["values"][$lang["key"]]["mega"] = $mega;
                    if ($tag) $result["values"][$lang["key"]]["tag"] = $tag;

                }
            }

            echo Utility::jencode($result);
        }


        private function set_menu_ranking()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $type = Filter::init("POST/type", "route");
            $active = Filter::POST("active");
            $inactive = Filter::POST("inactive");

            $datas = [];


            function menu_loop($arr = [], $parent = 0, $status = '')
            {
                $result = [];

                foreach ($arr as $k => $item) {
                    $result[] = [
                        'id'     => $item["id"],
                        'parent' => $parent,
                        'rank'   => $k,
                        'status' => $status,
                    ];
                    if (isset($item["children"]))
                        foreach (menu_loop($item["children"], $item["id"], $status) as $row) array_push($result, $row);
                }
                return $result;
            }

            if ($active) $datas = array_merge($datas, menu_loop($active, 0, "active"));
            if ($inactive) $datas = array_merge($datas, menu_loop($inactive, 0, "inactive"));

            if ($datas) {
                foreach ($datas as $data) {
                    $this->model->set_menu($data["id"], [
                        'parent' => $data["parent"],
                        'rank'   => $data["rank"],
                        'status' => $data["status"],
                    ]);
                    //echo $data["id"]." => ".$data["status"]."\n";
                }
            }

            self::$cache->clear();

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-menu-ranking");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/manage-website/success18"),
            ]);

        }


        private function operationMain($operation)
        {

            if ($operation == "get_menu")
                return $this->get_menu();

            if ($operation == "update_contracts" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->update_contracts();

            if ($operation == "set_menu_ranking" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->set_menu_ranking();

            if ($operation == "settings_news" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->settings_news();

            if ($operation == "settings_blog" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->settings_blog();

            if ($operation == "add_new_blog_category" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->add_new_blog_category();

            if ($operation == "add_new_reference_category" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->add_new_reference_category();

            if ($operation == "add_normal_page" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->add_normal_page();

            if ($operation == "add_news_page" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->add_news_page();

            if ($operation == "add_blog_page" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->add_blog_page();

            if ($operation == "add_reference_page" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->add_reference_page();

            if ($operation == "add_slide" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->add_slide();

            if ($operation == "add_cfeedback" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->add_cfeedback();

            if ($operation == "add_menu" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->add_menu();

            if ($operation == "edit_normal_page" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->edit_normal_page();

            if ($operation == "edit_news_page" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->edit_news_page();

            if ($operation == "edit_blog_page" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->edit_blog_page();

            if ($operation == "edit_blog_category" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->edit_blog_category();

            if ($operation == "edit_reference_page" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->edit_reference_page();

            if ($operation == "edit_reference_category" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->edit_reference_category();

            if ($operation == "edit_slide" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->edit_slide();

            if ($operation == "delete_slide_video" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->delete_slide_video();

            if ($operation == "edit_cfeedback" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->edit_cfeedback();

            if ($operation == "edit_menu" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->edit_menu();

            if ($operation == "delete_page" && Admin::isPrivilege(["MANAGE_WEBSITE_DELETE"]))
                return $this->delete_page();

            if ($operation == "delete_slide" && Admin::isPrivilege(["MANAGE_WEBSITE_DELETE"]))
                return $this->delete_slide();

            if ($operation == "delete_cfeedback" && Admin::isPrivilege(["MANAGE_WEBSITE_DELETE"]))
                return $this->delete_cfeedback();

            if ($operation == "delete_menu" && Admin::isPrivilege(["MANAGE_WEBSITE_DELETE"]))
                return $this->delete_menu();

            if ($operation == "delete_page_hbackground" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->delete_page_hbackground();

            if ($operation == "delete_page_cover" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->delete_page_cover();

            if ($operation == "delete_page_mockup" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->delete_page_mockup();

            if ($operation == "delete_cfeedback_image" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->delete_cfeedback_image();

            if ($operation == "delete_category_hbackground" && Admin::isPrivilege(["MANAGE_WEBSITE_OPERATION"]))
                return $this->delete_category_hbackground();

            if ($operation == "delete_category" && Admin::isPrivilege(["MANAGE_WEBSITE_DELETE"]))
                return $this->delete_category();

            if ($operation == "delete_message" && Admin::isPrivilege(["CONTACT_FORM_DELETE"]))
                return $this->delete_message();

            if ($operation == "read_message" && Admin::isPrivilege(["CONTACT_FORM_OPERATION"]))
                return $this->read_message();

            if ($operation == "reply_message" && Admin::isPrivilege(["CONTACT_FORM_OPERATION"]))
                return $this->reply_message();

            echo "Not found operation: " . $operation;
        }


        private function pageMain($name = '')
        {
            if ($name == "pages" && Admin::isPrivilege(Config::get("privileges/MANAGE_WEBSITE")))
                return $this->pages();
            if ($name == "news" && Admin::isPrivilege(Config::get("privileges/MANAGE_WEBSITE"))) return $this->news();
            if ($name == "blogs" && Admin::isPrivilege(Config::get("privileges/MANAGE_WEBSITE"))) return $this->blogs();
            if ($name == "references" && Admin::isPrivilege(Config::get("privileges/MANAGE_WEBSITE"))) return $this->references();
            if ($name == "slides" && Admin::isPrivilege(Config::get("privileges/MANAGE_WEBSITE"))) return $this->slides();
            if ($name == "cfeedbacks" && Admin::isPrivilege(Config::get("privileges/MANAGE_WEBSITE"))) return $this->cfeedbacks();
            if ($name == "messages" && Admin::isPrivilege(Config::get("privileges/CONTACT_FORM"))) return $this->messages();
            if ($name == "menus" && Admin::isPrivilege(Config::get("privileges/MANAGE_WEBSITE"))) return $this->menus();
            echo "Not found main: " . $name;
        }


        private function slides()
        {
            $param = isset($this->params[1]) ? $this->params[1] : false;

            if ($param == "listing.json") return $this->slides_listing_json();
            if ($param == "create") return $this->slides_create();
            if ($param == "edit") return $this->slides_edit();
            return $this->slides_listing();
        }


        private function slides_listing()
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
                'controller' => $this->AdminCRLink("manage-website-1", ["slides"]),
                'create'     => $this->AdminCRLink("manage-website-2", ["slides", "create"]),
                'ajax'       => $this->AdminCRLink("manage-website-2", ["slides", "listing.json"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-slides-list"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-slides-list"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->view->chose("admin")->render("slides-list", $this->data);
        }


        private function slides_listing_json()
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

            if (!Validation::isEmpty(Filter::GET("sSearch"))) $searches['word'] = Filter::init("GET/sSearch", "hclear");

            $filteredList = $this->model->get_slides($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_slides_total($searches);
            $listTotal = $this->model->get_slides_total();

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                Helper::Load("Money");

                $privOperation = Admin::isPrivilege("MANAGE_WEBSITE_OPERATION");
                $privDelete = Admin::isPrivilege("MANAGE_WEBSITE_DELETE");

                if ($filteredList) {
                    $this->addData("privOperation", $privOperation);
                    $this->addData("privDelete", $privDelete);
                    $this->addData("list", $filteredList);

                    $output["aaData"] = $this->view->chose("admin")->render("ajax-slides-list", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function slides_create()
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
                'controller' => $this->AdminCRLink("manage-website-2", ["slides", "create"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-slides-create"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["slides"]),
                'title' => __("admin/manage-website/breadcrumb-slides-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-slides-create"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $folder = Config::get("pictures/slides/folder");

            $pictureDeft = Utility::image_link_determiner("default.jpg", $folder);
            $this->addData("getPictureDeft", $pictureDeft);


            $this->view->chose("admin")->render("add-slide", $this->data);
        }


        private function slides_edit()
        {

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) return false;

            $slide = $this->model->get_slide($id);
            if (!$slide) return false;

            $GLOBALS["slide"] = $slide;

            $this->addData("slide", $slide);

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

            $page_title = $slide["title"];
            if (!$page_title) $page_title = ___("needs/untitled");

            $this->addData("page_title", $page_title);

            $links = [
                'controller' => $this->AdminCRLink("manage-website-2", ["slides", "edit"]) . "?id=" . $id,
            ];

            $this->addData("links", $links);

            $this->addData("meta", [
                'title' => __("admin/manage-website/meta-slides-edit", ['{title}' => $page_title]),
            ]);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["slides"]),
                'title' => __("admin/manage-website/breadcrumb-slides-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-slides-edit", ['{title}' => $page_title]),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("functions", [
                'get_slide_with_lang' => function ($lang) {
                    return $this->model->get_slide_lang($GLOBALS["slide"]["id"], $lang);
                },
            ]);

            $folder = Config::get("pictures/slides/folder");

            $picture = $this->model->get_picture("slides", $slide["id"], "main-image");
            if ($picture)
                $picture = Utility::image_link_determiner($picture, $folder . "thumb" . DS);
            $pictureDeft = Utility::image_link_determiner("default.jpg", $folder);
            $this->addData("getPicture", $picture);
            $this->addData("getPictureDeft", $pictureDeft);

            $this->view->chose("admin")->render("edit-slide", $this->data);
        }


        private function cfeedbacks()
        {
            $param = isset($this->params[1]) ? $this->params[1] : false;

            if ($param == "listing.json") return $this->cfeedbacks_listing_json();
            if ($param == "create") return $this->cfeedbacks_create();
            if ($param == "edit") return $this->cfeedbacks_edit();
            return $this->cfeedbacks_listing();
        }


        private function cfeedbacks_listing()
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
                'controller' => $this->AdminCRLink("manage-website-1", ["cfeedbacks"]),
                'create'     => $this->AdminCRLink("manage-website-2", ["cfeedbacks", "create"]),
                'ajax'       => $this->AdminCRLink("manage-website-2", ["cfeedbacks", "listing.json"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-cfeedbacks-list"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-cfeedbacks-list"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->view->chose("admin")->render("cfeedbacks-list", $this->data);
        }


        private function cfeedbacks_listing_json()
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

            if (!Validation::isEmpty(Filter::GET("sSearch"))) $searches['word'] = Filter::init("GET/sSearch", "hclear");

            $filteredList = $this->model->get_cfeedbacks($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_cfeedbacks_total($searches);
            $listTotal = $this->model->get_cfeedbacks_total();

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                Helper::Load("Money");

                $privOperation = Admin::isPrivilege("MANAGE_WEBSITE_OPERATION");
                $privDelete = Admin::isPrivilege("MANAGE_WEBSITE_DELETE");

                $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                $situations = $situations["cfeedbacks"];

                if ($filteredList) {
                    $this->addData("privOperation", $privOperation);
                    $this->addData("privDelete", $privDelete);
                    $this->addData("situations", $situations);
                    $this->addData("list", $filteredList);

                    $output["aaData"] = $this->view->chose("admin")->render("ajax-cfeedbacks-list", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function cfeedbacks_create()
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
                'controller' => $this->AdminCRLink("manage-website-2", ["cfeedbacks", "create"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-cfeedbacks-create"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["cfeedbacks"]),
                'title' => __("admin/manage-website/breadcrumb-cfeedbacks-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-cfeedbacks-create"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $folder = Config::get("pictures/customer-feedback/folder");

            $pictureDeft = Utility::image_link_determiner("default.jpg", $folder);
            $this->addData("getPictureDeft", $pictureDeft);


            $this->view->chose("admin")->render("add-cfeedback", $this->data);
        }


        private function cfeedbacks_edit()
        {

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) return false;

            $cfeedback = $this->model->get_cfeedback($id);
            if (!$cfeedback) return false;

            $GLOBALS["cfeedback"] = $cfeedback;

            $this->addData("cfeedback", $cfeedback);

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

            $page_title = $cfeedback["name"];

            $this->addData("page_title", $page_title);

            $links = [
                'controller' => $this->AdminCRLink("manage-website-2", ["cfeedbacks", "edit"]) . "?id=" . $id,
            ];

            $this->addData("links", $links);

            $this->addData("meta", [
                'title' => __("admin/manage-website/meta-cfeedbacks-edit", ['{title}' => $page_title]),
            ]);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["cfeedbacks"]),
                'title' => __("admin/manage-website/breadcrumb-cfeedbacks-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-cfeedbacks-edit", ['{title}' => $page_title]),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("functions", [
                'get_cfeedback_with_lang' => function ($lang) {
                    return $this->model->get_cfeedback_lang($GLOBALS["cfeedback"]["id"], $lang);
                },
            ]);

            $folder = Config::get("pictures/customer-feedback/folder");

            $picture = $this->model->get_picture("customer_feedback", $cfeedback["id"], "image");
            if ($picture)
                $picture = Utility::image_link_determiner($picture, $folder);
            $pictureDeft = Utility::image_link_determiner("default.jpg", $folder);
            $this->addData("getPicture", $picture);
            $this->addData("getPictureDeft", $pictureDeft);

            $this->view->chose("admin")->render("edit-cfeedback", $this->data);
        }


        private function messages()
        {
            $param = isset($this->params[1]) ? $this->params[1] : false;

            if ($param == "listing.json") return $this->messages_listing_json();
            return $this->messages_listing();
        }


        private function messages_listing()
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
                'controller' => $this->AdminCRLink("manage-website-1", ["messages"]),
                'ajax'       => $this->AdminCRLink("manage-website-2", ["messages", "listing.json"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-messages-list"));

            $breadcrumbs = [

                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],

            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-messages-list"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            Helper::Load("Tickets");

            $predefined_replies = [];
            $lang_list = $this->getData("lang_list");

            foreach ($lang_list as $l) {
                $lk = $l["key"];
                $predefined_replies[$lk] = Tickets::get_predefined_replies($lk);
            }
            $this->addData("predefined_replies", $predefined_replies);


            $this->view->chose("admin")->render("contact-messages", $this->data);
        }


        private function messages_listing_json()
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

            if (!Validation::isEmpty(Filter::GET("sSearch"))) $searches['word'] = Filter::init("GET/sSearch", "hclear");

            $filteredList = $this->model->get_messages($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_messages_total($searches);
            $listTotal = $this->model->get_messages_total();

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {

                $privOperation = Admin::isPrivilege("CONTACT_FORM_OPERATION");
                $privDelete = Admin::isPrivilege("CONTACT_FORM_DELETE");

                if ($filteredList) {
                    $this->addData("privOperation", $privOperation);
                    $this->addData("privDelete", $privDelete);
                    $this->addData("list", $filteredList);

                    $output["aaData"] = $this->view->chose("admin")->render("ajax-contact-messages", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function menus()
        {
            $param = isset($this->params[1]) ? $this->params[1] : false;

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
                'controller' => $this->AdminCRLink("manage-website-1", ["menus"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-menus"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-menus"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $header_menus_active = $this->model->get_menus("header", "active");
            $header_menus_inactive = $this->model->get_menus("header", "inactive");
            $footer_menus_active = $this->model->get_menus("footer", "active");
            $footer_menus_inactive = $this->model->get_menus("footer", "inactive");
            $pages_sidebar_active = $this->model->get_menus("pages-sidebar", "active");
            $pages_sidebar_inactive = $this->model->get_menus("pages-sidebar", "inactive");

            $clientArea_active = $this->model->get_menus("clientArea", "active");
            $clientArea_inactive = $this->model->get_menus("clientArea", "inactive");

            $mobile_active = $this->model->get_menus("mobile", "active");
            $mobile_inactive = $this->model->get_menus("mobile", "inactive");

            $t_cg_f = TEMPLATE_DIR . DS . "website" . DS . Config::get("theme/name") . DS . "theme-config.php";
            $t_cg = file_exists($t_cg_f) ? include $t_cg_f : [];
            $t_h_t = isset($t_cg['settings']['header-type']) ? $t_cg['settings']['header-type'] : false;

            if ($t_h_t && Config::get("theme/header-type") != $t_h_t) {
                $t_cg['settings']['header-type'] = Config::get("theme/header-type");
                $var_export = Utility::array_export($t_cg, ['pwith' => true]);
                FileManager::file_write($t_cg_f, $var_export);
            }


            $this->addData("header_menus", [
                'active'   => $header_menus_active,
                'inactive' => $header_menus_inactive,
            ]);

            $this->addData("footer_menus", [
                'active'   => $footer_menus_active,
                'inactive' => $footer_menus_inactive,
            ]);

            $this->addData("mobile_menus", [
                'active'   => $mobile_active,
                'inactive' => $mobile_inactive,
            ]);

            $this->addData("clientArea_menus", [
                'active'   => $clientArea_active,
                'inactive' => $clientArea_inactive,
            ]);

            $this->addData("pages_sidebar", [
                'active'   => $pages_sidebar_active,
                'inactive' => $pages_sidebar_inactive,
            ]);

            $this->addData("select_pages", $this->model->select_pages());

            $this->view->chose("admin")->render("menus", $this->data);
        }


        private function pages_listing_json($type = '')
        {
            $limit = 10;
            $output = [];
            $aColumns = array();

            if (!($type == "normal" || $type == "news" || $type == "articles" || $type == "references")) return false;

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

            $filteredList = $this->model->get_pages($type, $searches, $orders, $start, $end);
            $filterTotal = $this->model->get_pages_total($type, $searches);
            $listTotal = $this->model->get_pages_total($type);

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                Helper::Load("Money");

                $privOperation = Admin::isPrivilege("MANAGE_WEBSITE_OPERATION");
                $privDelete = Admin::isPrivilege("MANAGE_WEBSITE_DELETE");

                if ($filteredList) {
                    $this->addData("privOperation", $privOperation);
                    $this->addData("privDelete", $privDelete);
                    $this->addData("list", $filteredList);

                    if ($type == "articles") $type = "blog";

                    $template = $type == "normal" ? "ajax-pages-list" : "ajax-" . $type . "-list";
                    $output["aaData"] = $this->view->chose("admin")->render($template, $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function categories_json($type = '')
        {
            $limit = 10;
            $output = [];
            $aColumns = array();

            if (!($type == "articles" || $type == "references")) return false;

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

            $filteredList = $this->model->get_categories($type, $searches, $orders, $start, $end);
            $filterTotal = $this->model->get_categories_total($type, $searches);
            $listTotal = $this->model->get_categories_total($type);

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                Helper::Load("Money");

                $privOperation = Admin::isPrivilege("MANAGE_WEBSITE_OPERATION");
                $privDelete = Admin::isPrivilege("MANAGE_WEBSITE_DELETE");

                $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                $situations = $situations["categories"];


                if ($filteredList) {
                    $this->addData("privOperation", $privOperation);
                    $this->addData("privDelete", $privDelete);
                    $this->addData("situations", $situations);
                    $this->addData("list", $filteredList);
                    if ($type == "articles") $type = "blog";
                    if ($type == "references") $type = "reference";
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-" . $type . "-categories", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function pages()
        {
            $param = isset($this->params[1]) ? $this->params[1] : false;

            if ($param == "listing.json") return $this->pages_listing_json("normal");
            if ($param == "contracts") return $this->contracts();
            if ($param == "create") return $this->pages_create();
            if ($param == "edit") return $this->pages_edit();

            return $this->pages_listing();
        }


        private function pages_listing()
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
                'controller' => $this->AdminCRLink("manage-website-1", ["pages"]),
                'create'     => $this->AdminCRLink("manage-website-2", ["pages", "create"]),
                'ajax'       => $this->AdminCRLink("manage-website-2", ["pages", "listing.json"]),
                'contracts'  => $this->AdminCRLink("manage-website-2", ["pages", "contracts"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-pages-list"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-pages-list"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->view->chose("admin")->render("pages-list", $this->data);
        }


        private function contracts()
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
                'controller' => $this->AdminCRLink("manage-website-2", ["pages", "contracts"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-pages-contracts"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["pages"]),
                'title' => __("admin/manage-website/breadcrumb-pages-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-pages-contracts"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->view->chose("admin")->render("contracts", $this->data);
        }


        private function pages_create()
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
                'controller' => $this->AdminCRLink("manage-website-2", ["pages", "create"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-pages-create"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["pages"]),
                'title' => __("admin/manage-website/breadcrumb-pages-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-pages-create"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $header_folder = Config::get("pictures/header-background/folder");

            $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
            $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);


            $this->view->chose("admin")->render("add-page", $this->data);
        }


        private function pages_edit()
        {

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) return false;

            $page = $this->model->get_page($id);
            if (!$page) return false;

            $GLOBALS["page"] = $page;

            $this->addData("page", $page);

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
                'controller' => $this->AdminCRLink("manage-website-2", ["pages", "edit"]) . "?id=" . $id,
            ];

            $this->addData("links", $links);

            $this->addData("meta", [
                'title' => __("admin/manage-website/meta-pages-edit", ['{title}' => $page["title"]]),
            ]);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["pages"]),
                'title' => __("admin/manage-website/breadcrumb-pages-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-pages-edit", ['{title}' => $page["title"]]),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("functions", [
                'get_page_with_lang' => function ($lang) {
                    return $this->model->get_page_lang($GLOBALS["page"]["id"], $lang);
                },
            ]);

            $header_folder = Config::get("pictures/header-background/folder");
            $folder = Config::get("pictures/page-" . $page["type"] . "/folder");

            $header_picture = $this->model->get_picture("page_" . $page["type"], $page["id"], "header-background");
            if ($header_picture)
                $header_picture = Utility::image_link_determiner($header_picture, $header_folder . "thumb" . DS);
            $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
            $this->addData("getHeaderBackground", $header_picture);
            $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);

            $listimg_picture = $this->model->get_picture("page_" . $page["type"], $page["id"], "cover");
            if ($listimg_picture)
                $listimg_picture = Utility::image_link_determiner($listimg_picture, $folder);
            $listimgDeft = Utility::image_link_determiner("default.jpg", $folder);
            $this->addData("getListImageDeft", $listimgDeft);
            $this->addData("getListImage", $listimg_picture);

            $this->view->chose("admin")->render("edit-page", $this->data);
        }


        private function news()
        {
            $param = isset($this->params[1]) ? $this->params[1] : false;

            if ($param == "listing.json") return $this->pages_listing_json("news");
            if ($param == "create") return $this->news_create();
            if ($param == "edit") return $this->news_edit();

            return $this->news_listing();
        }


        private function news_listing()
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
                'controller' => $this->AdminCRLink("manage-website-1", ["news"]),
                'create'     => $this->AdminCRLink("manage-website-2", ["news", "create"]),
                'ajax'       => $this->AdminCRLink("manage-website-2", ["news", "listing.json"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-news-list"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-news-list"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("settings", [
                'comment-embed-code' => Config::get("options/news-comment-embed-code"),
            ]);

            $this->view->chose("admin")->render("news-list", $this->data);
        }


        private function news_create()
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
                'controller' => $this->AdminCRLink("manage-website-2", ["news", "create"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-news-create"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["news"]),
                'title' => __("admin/manage-website/breadcrumb-news-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-news-create"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $header_folder = Config::get("pictures/header-background/folder");
            $folder = Config::get("pictures/page-news/folder");

            $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
            $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);

            $listimgDeft = Utility::image_link_determiner("default.jpg", $folder);
            $this->addData("getListImageDeft", $listimgDeft);


            $this->view->chose("admin")->render("add-news", $this->data);
        }


        private function news_edit()
        {

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) return false;

            $page = $this->model->get_page($id);
            if (!$page) return false;

            $GLOBALS["page"] = $page;

            $this->addData("page", $page);

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
                'controller' => $this->AdminCRLink("manage-website-2", ["news", "edit"]) . "?id=" . $id,
            ];

            $this->addData("links", $links);

            $this->addData("meta", [
                'title' => __("admin/manage-website/meta-news-edit", ['{title}' => $page["title"]]),
            ]);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["news"]),
                'title' => __("admin/manage-website/breadcrumb-news-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-news-edit", ['{title}' => $page["title"]]),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("functions", [
                'get_page_with_lang' => function ($lang) {
                    return $this->model->get_page_lang($GLOBALS["page"]["id"], $lang);
                },
            ]);

            $header_folder = Config::get("pictures/header-background/folder");
            $folder = Config::get("pictures/page-" . $page["type"] . "/folder");

            $header_picture = $this->model->get_picture("page_" . $page["type"], $page["id"], "header-background");
            if ($header_picture)
                $header_picture = Utility::image_link_determiner($header_picture, $header_folder . "thumb" . DS);
            $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
            $this->addData("getHeaderBackground", $header_picture);
            $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);

            $listimg_picture = $this->model->get_picture("page_" . $page["type"], $page["id"], "cover");
            if ($listimg_picture)
                $listimg_picture = Utility::image_link_determiner($listimg_picture, $folder);
            $listimgDeft = Utility::image_link_determiner("default.jpg", $folder);
            $this->addData("getListImageDeft", $listimgDeft);
            $this->addData("getListImage", $listimg_picture);

            $this->view->chose("admin")->render("edit-news", $this->data);
        }


        private function blogs()
        {
            $param = isset($this->params[1]) ? $this->params[1] : false;

            if ($param == "categories.json") return $this->categories_json("articles");
            if ($param == "listing.json") return $this->pages_listing_json("articles");
            if ($param == "create") return $this->blogs_create();
            if ($param == "edit") return $this->blogs_edit();
            if ($param == "categories") return $this->blogs_categories();
            if ($param == "create-category") return $this->blogs_create_category();
            if ($param == "edit-category") return $this->blogs_edit_category();

            return $this->blogs_listing();
        }


        private function blogs_listing()
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
                'controller' => $this->AdminCRLink("manage-website-1", ["blogs"]),
                'create'     => $this->AdminCRLink("manage-website-2", ["blogs", "create"]),
                'ajax'       => $this->AdminCRLink("manage-website-2", ["blogs", "listing.json"]),
                'categories' => $this->AdminCRLink("manage-website-2", ["blogs", "categories"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-blogs-list"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-blogs-list"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("settings", [
                'comment-embed-code' => Config::get("options/blog-comment-embed-code"),
            ]);

            $this->view->chose("admin")->render("blogs-list", $this->data);
        }


        private function blogs_categories()
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
                'controller' => $this->AdminCRLink("manage-website-2", ["blogs", "categories"]),
                'create'     => $this->AdminCRLink("manage-website-2", ["blogs", "create-category"]),
                'ajax'       => $this->AdminCRLink("manage-website-2", ["blogs", "categories.json"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-blogs-categories"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["blogs"]),
                'title' => __("admin/manage-website/breadcrumb-blogs-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-blogs-categories"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->view->chose("admin")->render("blogs-categories", $this->data);
        }


        private function blogs_create()
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
                'controller' => $this->AdminCRLink("manage-website-2", ["blogs", "create"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-blogs-create"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["blogs"]),
                'title' => __("admin/manage-website/breadcrumb-blogs-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-blogs-create"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $header_folder = Config::get("pictures/header-background/folder");
            $folder = Config::get("pictures/page-articles/folder");

            $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
            $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);

            $listimgDeft = Utility::image_link_determiner("default.jpg", $folder);
            $this->addData("getListImageDeft", $listimgDeft);

            $this->addData("categories", $this->model->get_select_categories("articles"));


            $this->view->chose("admin")->render("add-blog", $this->data);
        }


        private function blogs_create_category()
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
                'controller' => $this->AdminCRLink("manage-website-2", ["blogs", "create-category"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-blogs-create-category"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["blogs"]),
                'title' => __("admin/manage-website/breadcrumb-blogs-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-2", ["blogs", "categories"]),
                'title' => __("admin/manage-website/breadcrumb-blogs-categories"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-blogs-create-category"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $header_folder = Config::get("pictures/header-background/folder");

            $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
            $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);

            $this->addData("categories", $this->model->get_select_categories("articles"));

            $this->view->chose("admin")->render("add-blog-category", $this->data);
        }


        private function blogs_edit()
        {

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) return false;

            $page = $this->model->get_page($id);
            if (!$page) return false;

            $GLOBALS["page"] = $page;

            $this->addData("page", $page);

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
                'controller' => $this->AdminCRLink("manage-website-2", ["blogs", "edit"]) . "?id=" . $id,
            ];

            $this->addData("links", $links);

            $this->addData("meta", [
                'title' => __("admin/manage-website/meta-blogs-edit", ['{title}' => $page["title"]]),
            ]);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["blogs"]),
                'title' => __("admin/manage-website/breadcrumb-blogs-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-blogs-edit", ['{title}' => $page["title"]]),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("functions", [
                'get_page_with_lang' => function ($lang) {
                    return $this->model->get_page_lang($GLOBALS["page"]["id"], $lang);
                },
            ]);

            $header_folder = Config::get("pictures/header-background/folder");
            $folder = Config::get("pictures/page-" . $page["type"] . "/folder");

            $header_picture = $this->model->get_picture("page_" . $page["type"], $page["id"], "header-background");
            if ($header_picture)
                $header_picture = Utility::image_link_determiner($header_picture, $header_folder . "thumb" . DS);
            $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
            $this->addData("getHeaderBackground", $header_picture);
            $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);

            $listimg_picture = $this->model->get_picture("page_" . $page["type"], $page["id"], "cover");
            if ($listimg_picture)
                $listimg_picture = Utility::image_link_determiner($listimg_picture, $folder);
            $listimgDeft = Utility::image_link_determiner("default.jpg", $folder);
            $this->addData("getListImageDeft", $listimgDeft);
            $this->addData("getListImage", $listimg_picture);

            $this->addData("categories", $this->model->get_select_categories("articles"));

            $this->view->chose("admin")->render("edit-blog", $this->data);
        }


        private function blogs_edit_category()
        {

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) return false;

            $category = $this->model->get_category($id);
            if (!$category) return false;

            $GLOBALS["category"] = $category;

            $this->addData("cat", $category);

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
                'controller' => $this->AdminCRLink("manage-website-2", ["blogs", "edit-category"]) . "?id=" . $id,
            ];

            $this->addData("links", $links);

            $this->addData("meta", [
                'title' => __("admin/manage-website/meta-blogs-edit-category", ['{title}' => $category["title"]]),
            ]);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["blogs"]),
                'title' => __("admin/manage-website/breadcrumb-blogs-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-2", ["blogs", "categories"]),
                'title' => __("admin/manage-website/breadcrumb-blogs-categories"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-blogs-edit-category", ['{title}' => $category["title"]]),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("functions", [
                'get_category_with_lang' => function ($lang) {
                    return $this->model->get_category_wlang($GLOBALS["category"]["id"], $lang);
                },
            ]);

            $header_folder = Config::get("pictures/header-background/folder");

            $header_picture = $this->model->get_picture("category", $category["id"], "header-background");
            if ($header_picture)
                $header_picture = Utility::image_link_determiner($header_picture, $header_folder . "thumb" . DS);
            $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
            $this->addData("getHeaderBackground", $header_picture);
            $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);


            $this->addData("categories", $this->model->get_select_categories("articles"));

            $this->view->chose("admin")->render("edit-blog-category", $this->data);
        }


        private function references()
        {
            $param = isset($this->params[1]) ? $this->params[1] : false;

            if ($param == "categories.json") return $this->categories_json("references");
            if ($param == "listing.json") return $this->pages_listing_json("references");
            if ($param == "create") return $this->references_create();
            if ($param == "edit") return $this->references_edit();
            if ($param == "categories") return $this->references_categories();
            if ($param == "create-category") return $this->references_create_category();
            if ($param == "edit-category") return $this->references_edit_category();

            return $this->references_listing();
        }


        private function references_listing()
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
                'controller' => $this->AdminCRLink("manage-website-1", ["references"]),
                'create'     => $this->AdminCRLink("manage-website-2", ["references", "create"]),
                'ajax'       => $this->AdminCRLink("manage-website-2", ["references", "listing.json"]),
                'categories' => $this->AdminCRLink("manage-website-2", ["references", "categories"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-references-list"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-references-list"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("settings", [
                'comment-embed-code' => Config::get("options/reference-comment-embed-code"),
            ]);

            $this->view->chose("admin")->render("references-list", $this->data);
        }


        private function references_categories()
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
                'controller' => $this->AdminCRLink("manage-website-2", ["references", "categories"]),
                'create'     => $this->AdminCRLink("manage-website-2", ["references", "create-category"]),
                'ajax'       => $this->AdminCRLink("manage-website-2", ["references", "categories.json"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-references-categories"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["references"]),
                'title' => __("admin/manage-website/breadcrumb-references-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-references-categories"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->view->chose("admin")->render("references-categories", $this->data);
        }


        private function references_create()
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
                'controller' => $this->AdminCRLink("manage-website-2", ["references", "create"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-references-create"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["references"]),
                'title' => __("admin/manage-website/breadcrumb-references-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-references-create"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $header_folder = Config::get("pictures/header-background/folder");
            $folder = Config::get("pictures/page-references/folder");

            $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
            $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);

            $listimgDeft = Utility::image_link_determiner("default.jpg", $folder);
            $this->addData("getListImageDeft", $listimgDeft);

            $mockupDeft = Utility::image_link_determiner("mockup-default.jpg", $folder);
            $this->addData("getMockupImageDeft", $mockupDeft);

            $this->addData("categories", $this->model->get_select_categories("references"));


            $this->view->chose("admin")->render("add-reference", $this->data);
        }


        private function references_create_category()
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
                'controller' => $this->AdminCRLink("manage-website-2", ["references", "create-category"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/manage-website/meta-references-create-category"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["references"]),
                'title' => __("admin/manage-website/breadcrumb-references-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-2", ["references", "categories"]),
                'title' => __("admin/manage-website/breadcrumb-references-categories"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-references-create-category"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $header_folder = Config::get("pictures/header-background/folder");

            $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
            $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);

            $this->addData("categories", $this->model->get_select_categories("articles"));

            $this->view->chose("admin")->render("add-reference-category", $this->data);
        }


        private function references_edit()
        {

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) return false;

            $page = $this->model->get_page($id);
            if (!$page) return false;

            $GLOBALS["page"] = $page;

            $this->addData("page", $page);

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
                'controller' => $this->AdminCRLink("manage-website-2", ["references", "edit"]) . "?id=" . $id,
            ];

            $this->addData("links", $links);

            $this->addData("meta", [
                'title' => __("admin/manage-website/meta-references-edit", ['{title}' => $page["title"]]),
            ]);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["references"]),
                'title' => __("admin/manage-website/breadcrumb-references-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-references-edit", ['{title}' => $page["title"]]),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("functions", [
                'get_page_with_lang' => function ($lang) {
                    return $this->model->get_page_lang($GLOBALS["page"]["id"], $lang);
                },
            ]);

            $header_folder = Config::get("pictures/header-background/folder");
            $folder = Config::get("pictures/page-" . $page["type"] . "/folder");

            $header_picture = $this->model->get_picture("page_" . $page["type"], $page["id"], "header-background");
            if ($header_picture)
                $header_picture = Utility::image_link_determiner($header_picture, $header_folder . "thumb" . DS);
            $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
            $this->addData("getHeaderBackground", $header_picture);
            $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);

            $listimg_picture = $this->model->get_picture("page_" . $page["type"], $page["id"], "cover");
            if ($listimg_picture)
                $listimg_picture = Utility::image_link_determiner($listimg_picture, $folder);
            $listimgDeft = Utility::image_link_determiner("default.jpg", $folder);
            $this->addData("getListImageDeft", $listimgDeft);
            $this->addData("getListImage", $listimg_picture);

            $mockup_picture = $this->model->get_picture("page_" . $page["type"], $page["id"], "mockup");
            if ($mockup_picture)
                $mockup_picture = Utility::image_link_determiner($mockup_picture, $folder);
            $mockupDeft = Utility::image_link_determiner("mockup-default.jpg", $folder);
            $this->addData("getMockupImageDeft", $mockupDeft);
            $this->addData("getMockupImage", $mockup_picture);


            $this->addData("categories", $this->model->get_select_categories("references"));

            $this->view->chose("admin")->render("edit-reference", $this->data);
        }


        private function references_edit_category()
        {

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) return false;

            $category = $this->model->get_category($id);
            if (!$category) return false;

            $GLOBALS["category"] = $category;

            $this->addData("cat", $category);

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
                'controller' => $this->AdminCRLink("manage-website-2", ["references", "edit-category"]) . "?id=" . $id,
            ];

            $this->addData("links", $links);

            $this->addData("meta", [
                'title' => __("admin/manage-website/meta-references-edit-category", ['{title}' => $category["title"]]),
            ]);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-1", ["references"]),
                'title' => __("admin/manage-website/breadcrumb-references-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("manage-website-2", ["references", "categories"]),
                'title' => __("admin/manage-website/breadcrumb-references-categories"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/manage-website/breadcrumb-references-edit-category", ['{title}' => $category["title"]]),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("functions", [
                'get_category_with_lang' => function ($lang) {
                    return $this->model->get_category_wlang($GLOBALS["category"]["id"], $lang);
                },
            ]);

            $header_folder = Config::get("pictures/header-background/folder");

            $header_picture = $this->model->get_picture("category", $category["id"], "header-background");
            if ($header_picture)
                $header_picture = Utility::image_link_determiner($header_picture, $header_folder . "thumb" . DS);
            $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
            $this->addData("getHeaderBackground", $header_picture);
            $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);


            $this->addData("categories", $this->model->get_select_categories("articles"));

            $this->view->chose("admin")->render("edit-reference-category", $this->data);
        }


        public function main()
        {

            if (Filter::POST("operation")) return $this->operationMain(Filter::init("POST/operation", "route"));
            if (Filter::GET("operation")) return $this->operationMain(Filter::init("GET/operation", "route"));

            $page = isset($this->params[0]) ? $this->params[0] : false;
            return $this->pageMain($page);
        }

    }