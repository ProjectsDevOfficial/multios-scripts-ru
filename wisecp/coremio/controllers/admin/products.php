<?php
    defined('CORE_FOLDER') or exit('You can not get in here!');

    class Controller extends Controllers
    {
        protected $params, $data = [], $type;


        public function __construct($arg = [])
        {
            parent::__construct();
            $this->params = $arg['params'];

            if (!UserManager::LoginCheck("admin")) {
                Utility::redirect($this->AdminCRLink("sign-in"));
                die();
            }
            Helper::Load("Admin");
            if (!Admin::isPrivilege(Config::get("privileges/PRODUCTS"))) die();

            Helper::Load("Products");
        }


        private function tldlist_docs_id()
        {
            $db_name = Config::get("database/name");

            $last_id = $this->model->db->query('SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = "' . $db_name . '" AND TABLE_NAME = "tldlist_docs"');
            $last_id = $this->model->db->getAssoc($last_id);
            $last_id = $last_id["AUTO_INCREMENT"];

            return $last_id;
        }


        private function domain_whois_query()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $domain = Filter::init("POST/domain", "domain");
            $domain = str_replace([":", "/"], '', $domain);

            if (!$domain) exit(Utility::jencode([
                'status'  => "error",
                'message' => "Invalid Domain",
            ]));

            $parse = Utility::domain_parser("https://" . $domain);

            if (!$parse) exit("Invalid parse domain");

            $sld = $parse["host"];
            $tld = $parse["tld"];

            Helper::Load("Registrar");

            $whois = Registrar::get_whois($sld, $tld);
            if (!isset($whois["raw"])) exit(Utility::jencode([
                'status'  => "error",
                'message' => "Data could not be received.",
            ]));


            echo Utility::jencode([
                'status'        => "successful",
                'domain_status' => $whois["status"],
                'data'          => $whois["raw"],
            ]);


        }


        private function delete_product($type)
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");

            Helper::Load("Products");

            $product_data = Products::get($type, $id);

            if ($this->model->delete_product($type, $id)) Hook::run("ProductDeleted", $product_data);


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "delete", "deleted-product", [
                'type' => $type,
                'id'   => $id,
            ]);

            self::$cache->clear();

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success1")]);
        }


        private function copy_product($type)
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load("Products");

            $id = (int)Filter::init("POST/id", "numbers");

            $new_p_id = $this->model->copy_product($type, $id);

            Hook::run("ProductCreated", Products::get($type, $new_p_id));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "copy-product", [
                'type' => $type,
                'id'   => $id,
            ]);

            self::$cache->clear();

            echo Utility::jencode(['status' => "successful"]);
        }


        private function delete_category($type)
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");

            $sub = $this->model->get_category_sub($id);
            $categories = array_merge([$id], $sub);

            foreach ($categories as $category) {
                $this->model->delete_category($category);
                Hook::run("ProductCategoryDeleted", $category);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "delete", "deleted-product-category", [
                'type' => $type,
                'id'   => $id,
            ]);

            self::$cache->clear();

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success1")]);
        }


        private function add_new_hosting_category()
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
                $check = $this->model->category_route_check($slug, $lang, "products");
                if ($check)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error23", ['{lang}' => strtoupper($lang)]),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $sub_titles = Filter::POST("sub_title");
            $hbackground = Filter::FILES("hbackground");
            $icon_image = Filter::FILES("icon_image");
            $color = Filter::init("POST/color", "letters_numbers");
            $icon = Filter::init("POST/icon", "hclear");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $faqs = Filter::POST("faq");
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
                $faqx = isset($faqs[$lkey]) ? $faqs[$lkey] : false;
                $faq = $faqx ? [] : null;
                if ($faqx) {
                    $size = sizeof($faqx["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_title = $faqx["title"][$i];
                        $f_desc = $faqx["description"][$i];
                        if ($f_title)
                            $faq[] = [
                                'title'       => $f_title,
                                'description' => $f_desc,
                            ];

                    }
                }
                if ($faq) $faq = Utility::jencode($faq);
                else $faq = '';

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error1", ['{lang}' => strtoupper($lkey)]),
                    ]));

                if (___("package/permalink", false, $lkey)) {
                    $route = !$route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->category_route_check($route, $lkey, "products");
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
                        'faq'             => $faq,
                        'options'         => null,
                    ];

            }

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
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
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, 331, 100, [
                    'folder-date-detect' => true,
                ]);
            }


            if ($icon_image) {
                Helper::Load(["Uploads"]);
                $ifolder = Config::get("pictures/category-icon/folder");
                $isizing = Config::get("pictures/category-icon/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($icon_image, [
                    'image-upload' => true,
                    'folder'       => $ifolder,
                    'width'        => $isizing["width"],
                    'height'       => $isizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='icon_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $ipicture = current($upload->operands);
                $ipicture = $ipicture["file_path"];
            }

            if (!$lang_data) die("Error! #1");

            $options = Utility::jencode([
                'color' => $color ? "#" . $color : '',
                'icon'  => $icon,
            ]);

            $p_data = [
                'status'  => $status,
                'parent'  => $parent,
                'rank'    => $rank,
                'type'    => "products",
                'kind'    => "hosting",
                'options' => $options,
                'ctime'   => DateManager::Now(),
            ];

            $insert = $this->model->insert_category($p_data);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error3"),
                ]));

            if (isset($hpicture) && $hpicture) $this->model->insert_picture("category", $insert, "header-background", $hpicture);
            if (isset($ipicture) && $ipicture) $this->model->insert_picture("category", $insert, "icon", $ipicture);
            foreach ($lang_data as $key => $data) {
                $data["owner_id"] = $insert;
                if (!$data["route"]) $data["route"] = $insert;
                $lang_data[$key] = $data;
                $this->model->insert_category_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-product-category", [
                'name' => $lang_data[$locall]["title"],
                'id'   => $insert,
            ]);

            $p_data["id"] = $insert;
            Hook::run("ProductCategoryCreated", ['data' => $p_data, 'languages' => $lang_data]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success2"),
                'redirect' => $this->AdminCRLink("products-2", ["hosting", "categories"]),
            ]);

        }


        private function edit_hosting_category()
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
                $check = $this->model->category_route_check($slug, $lang, "products");
                if ($check && $check != $category["id"])
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error23", ['{lang}' => strtoupper($lang)]),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $sub_titles = Filter::POST("sub_title");
            $hbackground = Filter::FILES("hbackground");
            $icon_image = Filter::FILES("icon_image");
            $color = Filter::init("POST/color", "letters_numbers");
            $icon = Filter::init("POST/icon", "hclear");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $faqs = Filter::POST("faq");
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
                $faqx = isset($faqs[$lkey]) ? $faqs[$lkey] : false;
                $faq = $faqx ? [] : null;
                if ($faqx) {
                    $size = sizeof($faqx["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_title = $faqx["title"][$i];
                        $f_desc = $faqx["description"][$i];
                        if ($f_title)
                            $faq[] = [
                                'title'       => $f_title,
                                'description' => $f_desc,
                            ];
                    }
                }
                if ($faq) $faq = Utility::jencode($faq);
                else $faq = '';

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error1", ['{lang}' => strtoupper($lkey)]),
                    ]));


                if (___("package/permalink", false, $lkey)) {
                    $route = !$route || $id == $route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->category_route_check($route, $lkey, "products");
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
                        'faq'             => $faq,
                        'options'         => null,
                    ];
            }

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
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
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, 331, 100, [
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

            if ($icon_image) {
                Helper::Load(["Uploads"]);
                $ifolder = Config::get("pictures/category-icon/folder");
                $isizing = Config::get("pictures/category-icon/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($icon_image, [
                    'image-upload' => true,
                    'folder'       => $ifolder,
                    'width'        => $isizing["width"],
                    'height'       => $isizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='icon_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $ipicture = current($upload->operands);
                $ipicture = $ipicture["file_path"];
                $before_pic = $this->model->get_picture("category", $id, "icon");
                if ($before_pic) {
                    FileManager::file_delete($ifolder . $before_pic);
                    $this->model->delete_picture("category", $id, "icon");
                }
                $this->model->insert_picture("category", $id, "icon", $ipicture);
            }

            if (!$lang_data) die("Error! #1");

            $options = Utility::jencode([
                'color' => $color ? "#" . $color : '',
                'icon'  => $icon,
            ]);

            $p_data = [
                'status'  => $status,
                'parent'  => $parent,
                'rank'    => $rank,
                'options' => $options,
            ];

            $update = $this->model->set_category($id, $p_data);

            if (!$update)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error4"),
                ]));

            $lang_data_x = [];
            foreach ($lang_data as $_k => $data) {
                $lang_data_x[$_k] = $data;
                $data_id = $data["id"];
                unset($data["id"]);
                if ($data_id)
                    $this->model->set_category_lang($data_id, $data);
                else
                    $this->model->insert_category_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-product-category", [
                'name' => $category["title"],
                'id'   => $id,
            ]);

            $p_data["id"] = $id;
            Hook::run("ProductCategoryModified", ['data' => $p_data, 'languages' => $lang_data_x]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success3"),
                'redirect' => $this->AdminCRLink("products-2", ["hosting", "categories"]),
            ]);
        }


        private function add_new_server_category()
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
                $check = $this->model->category_route_check($slug, $lang, "products");
                if ($check)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error23", ['{lang}' => strtoupper($lang)]),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $sub_titles = Filter::POST("sub_title");
            $hbackground = Filter::FILES("hbackground");
            $icon_image = Filter::FILES("icon_image");
            $color = Filter::init("POST/color", "letters_numbers");
            $list_template = (int)Filter::init("POST/list_template", "numbers");
            $icon = Filter::init("POST/icon", "hclear");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $faqs = Filter::POST("faq");
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
                $faqx = isset($faqs[$lkey]) ? $faqs[$lkey] : false;
                $faq = $faqx ? [] : null;
                if ($faqx) {
                    $size = sizeof($faqx["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_title = $faqx["title"][$i];
                        $f_desc = $faqx["description"][$i];
                        if ($f_title)
                            $faq[] = [
                                'title'       => $f_title,
                                'description' => $f_desc,
                            ];

                    }
                }
                if ($faq) $faq = Utility::jencode($faq);
                else $faq = '';

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error1", ['{lang}' => strtoupper($lkey)]),
                    ]));


                if (___("package/permalink", false, $lkey)) {
                    $route = !$route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->category_route_check($route, $lkey, "products");
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
                        'faq'             => $faq,
                        'options'         => null,
                    ];

            }

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
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
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, 331, 100, [
                    'folder-date-detect' => true,
                ]);
            }


            if ($icon_image) {
                Helper::Load(["Uploads"]);
                $ifolder = Config::get("pictures/category-icon/folder");
                $isizing = Config::get("pictures/category-icon/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($icon_image, [
                    'image-upload' => true,
                    'folder'       => $ifolder,
                    'width'        => $isizing["width"],
                    'height'       => $isizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='icon_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $ipicture = current($upload->operands);
                $ipicture = $ipicture["file_path"];
            }

            if (!$lang_data) die("Error! #1");

            $options = Utility::jencode([
                'color'         => $color ? "#" . $color : '',
                'list_template' => $list_template,
                'icon'          => $icon,
            ]);

            $p_data = [
                'status'  => $status,
                'parent'  => $parent,
                'rank'    => $rank,
                'type'    => "products",
                'kind'    => "server",
                'options' => $options,
                'ctime'   => DateManager::Now(),
            ];

            $insert = $this->model->insert_category($p_data);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error3"),
                ]));

            if (isset($hpicture) && $hpicture) $this->model->insert_picture("category", $insert, "header-background", $hpicture);
            if (isset($ipicture) && $ipicture) $this->model->insert_picture("category", $insert, "icon", $ipicture);
            foreach ($lang_data as $key => $data) {
                $data["owner_id"] = $insert;
                if (!$data["route"]) $data["route"] = $insert;
                $lang_data[$key] = $data;
                $this->model->insert_category_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-product-category", [
                'name' => $lang_data[$locall]["title"],
                'id'   => $insert,
            ]);


            $p_data["id"] = $insert;
            Hook::run("ProductCategoryCreated", ['data' => $p_data, 'languages' => $lang_data]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success2"),
                'redirect' => $this->AdminCRLink("products-2", ["server", "categories"]),
            ]);

        }


        private function edit_server_category()
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
                $check = $this->model->category_route_check($slug, $lang, "products");
                if ($check && $check != $category["id"])
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error23", ['{lang}' => strtoupper($lang)]),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $sub_titles = Filter::POST("sub_title");
            $hbackground = Filter::FILES("hbackground");
            $icon_image = Filter::FILES("icon_image");
            $color = Filter::init("POST/color", "letters_numbers");
            $list_template = (int)Filter::init("POST/list_template", "numbers");
            $icon = Filter::init("POST/icon", "hclear");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $faqs = Filter::POST("faq");
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
                $faqx = isset($faqs[$lkey]) ? $faqs[$lkey] : false;
                $faq = $faqx ? [] : null;
                if ($faqx) {
                    $size = sizeof($faqx["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_title = $faqx["title"][$i];
                        $f_desc = $faqx["description"][$i];
                        if ($f_title)
                            $faq[] = [
                                'title'       => $f_title,
                                'description' => $f_desc,
                            ];

                    }
                }
                if ($faq) $faq = Utility::jencode($faq);
                else $faq = '';

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error1", ['{lang}' => strtoupper($lkey)]),
                    ]));

                if (___("package/permalink", false, $lkey)) {
                    $route = !$route || $id == $route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->category_route_check($route, $lkey, "products");
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
                        'faq'             => $faq,
                        'options'         => null,
                    ];
            }

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
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
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, 331, 100, [
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

            if ($icon_image) {
                Helper::Load(["Uploads"]);
                $ifolder = Config::get("pictures/category-icon/folder");
                $isizing = Config::get("pictures/category-icon/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($icon_image, [
                    'image-upload' => true,
                    'folder'       => $ifolder,
                    'width'        => $isizing["width"],
                    'height'       => $isizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='icon_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $ipicture = current($upload->operands);
                $ipicture = $ipicture["file_path"];
                $before_pic = $this->model->get_picture("category", $id, "icon");
                if ($before_pic) {
                    FileManager::file_delete($ifolder . $before_pic);
                    $this->model->delete_picture("category", $id, "icon");
                }
                $this->model->insert_picture("category", $id, "icon", $ipicture);
            }


            if (!$lang_data) die("Error! #1");

            $options = Utility::jencode([
                'color'         => $color ? "#" . $color : '',
                'list_template' => $list_template,
                'icon'          => $icon,
            ]);

            $p_data = [
                'status'  => $status,
                'parent'  => $parent,
                'rank'    => $rank,
                'options' => $options,
            ];

            $update = $this->model->set_category($id, $p_data);

            if (!$update)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error4"),
                ]));

            $lang_data_x = [];
            foreach ($lang_data as $_k => $data) {
                $lang_data_x[$_k] = $data;
                $data_id = $data["id"];
                unset($data["id"]);
                if ($data_id)
                    $this->model->set_category_lang($data_id, $data);
                else
                    $this->model->insert_category_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-product-category", [
                'name' => $category["title"],
                'id'   => $id,
            ]);


            $p_data["id"] = $id;
            Hook::run("ProductCategoryModified", ['data' => $p_data, 'languages' => $lang_data_x]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success3"),
                'redirect' => $this->AdminCRLink("products-2", ["server", "categories"]),
            ]);
        }


        private function delete_category_hbackground()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            $category = $this->model->get_category($id);
            if (!$category) die();

            $hfolder = Config::get("pictures/header-background/folder");
            $before_pic = $this->model->get_picture("category", $id, "header-background");
            if ($before_pic) {
                FileManager::file_delete($hfolder . $before_pic);
                FileManager::file_delete($hfolder . "thumb" . DS . $before_pic);
                $this->model->delete_picture("category", $id, "header-background");

                self::$cache->clear();

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "deleted", "deleted-category-header-background", [
                    'id'   => $id,
                    'name' => $category["title"],
                ]);
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success4")]);

        }


        private function delete_product_hbackground($type = '')
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            if ($type == "software")
                $product = $this->model->get_product_software($id);
            else
                $product = $this->model->get_product($id);
            if (!$product) die();

            $hfolder = Config::get("pictures/header-background/folder");
            if ($type == "software") {
                $before_pic = $this->model->get_picture("page_software", $id, "header-background");
                if ($before_pic) {
                    FileManager::file_delete($hfolder . $before_pic);
                    FileManager::file_delete($hfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("page_software", $id, "header-background");
                }

                self::$cache->clear();

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "deleted", "deleted-product-header-background", [
                    'id'   => $id,
                    'name' => $product["title"],
                ]);
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success4")]);

        }


        private function delete_product_cover($type = '')
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            if ($type == "software")
                $product = $this->model->get_product_software($id);
            else
                $product = $this->model->get_product($id);
            if (!$product) die();

            if ($type == "software") {
                $folder = Config::get("pictures/software/folder");
                $before_pic = $this->model->get_picture("page_software", $id, "cover");
                if ($before_pic) {
                    FileManager::file_delete($folder . $before_pic);
                    FileManager::file_delete($folder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("page_software", $id, "cover");
                }
            } else {
                $folder = Config::get("pictures/products/folder");
                $before_pic = $this->model->get_picture("product", $id, "cover");
                if ($before_pic) {
                    FileManager::file_delete($folder . $before_pic);
                    FileManager::file_delete($folder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("product", $id, "cover");
                }
            }

            self::$cache->clear();

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "deleted", "deleted-product-cover", [
                'id'   => $id,
                'name' => $product["title"],
            ]);

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success4")]);

        }

        private function delete_product_order_image($type = '')
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            if ($type == "software")
                $product = $this->model->get_product_software($id);
            else
                $product = $this->model->get_product($id);
            if (!$product) die();

            if ($type == "software") {
                $folder = Config::get("pictures/software/folder");
                $before_pic = $this->model->get_picture("software", $id, "order");
                if ($before_pic) {
                    FileManager::file_delete($folder . $before_pic);
                    FileManager::file_delete($folder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("software", $id, "order");
                }
            } else {
                $folder = Config::get("pictures/products/folder");
                $before_pic = $this->model->get_picture("product", $id, "order");
                if ($before_pic) {
                    FileManager::file_delete($folder . $before_pic);
                    FileManager::file_delete($folder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("product", $id, "order");
                }
            }

            self::$cache->clear();

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "deleted", "deleted-product-order-image", [
                'id'   => $id,
                'name' => $product["title"],
            ]);

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success4")]);

        }


        private function delete_product_mockup($type = '')
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            if ($type == "software")
                $product = $this->model->get_product_software($id);
            else
                $product = $this->model->get_product($id);
            if (!$product) die();

            if ($type == "software") {
                $folder = Config::get("pictures/software/folder");
                $before_pic = $this->model->get_picture("page_software", $id, "mockup");
                if ($before_pic) {
                    FileManager::file_delete($folder . $before_pic);
                    FileManager::file_delete($folder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("page_software", $id, "mockup");
                }

                self::$cache->clear();

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "deleted", "deleted-product-mockup", [
                    'id'   => $id,
                    'name' => $product["title"],
                ]);
            }
            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success4")]);
        }


        private function delete_category_icon_image()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();


            $ifolder = Config::get("pictures/category-icon/folder");
            $before_pic = $this->model->get_picture("category", $id, "icon");
            if ($before_pic) {
                FileManager::file_delete($ifolder . $before_pic);
                $this->model->delete_picture("category", $id, "icon");
                self::$cache->clear();
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success4")]);

        }


        private function delete_product_download_file()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            $type = Filter::init("POST/type", "letters");
            if (!$id) die();

            $product = $this->model->get_product($id, $type);

            if (!$product) die();

            $options = $product["options"] ? Utility::jdecode($product["options"], true) : [];

            if (isset($options["download_file"]) && $options["download_file"]) {
                $download_file = $options["download_file"];

                unset($options["download_file"]);

                $folder = RESOURCE_DIR . "uploads" . DS . "products" . DS;
                FileManager::file_delete($folder . $download_file);

                if ($type == "software") {
                    $this->model->set_page($id, [
                        'options' => Utility::jencode($options),
                    ]);
                } else {
                    $this->model->set_product($id, [
                        'options' => Utility::jencode($options),
                    ]);
                }

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "deleted", "deleted-product-download-file", [
                    'id'   => $id,
                    'name' => $product["title"],
                ]);
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success31")]);
        }


        private function get_shared_server_mdata()
        {

            $this->takeDatas("language");

            $server_id = (int)Filter::init("POST/server_id", "numbers");
            $product_id = (int)Filter::init("POST/product_id", "numbers");
            if (!$server_id) die();
            Helper::Load("Products");
            $server = Products::get_server($server_id);
            if (!$server) die();
            $type = $server["type"];
            if ($type == "none") die();

            Modules::Load("Servers", $type);

            $module = $type . "_Module";
            $module = new $module($server);

            if ($product_id) $product = Products::get(($module->config["type"] == "virtualization" ? "server" : "hosting"), $product_id);

            $data = [
                'module'  => $module,
                'product' => $product_id && isset($product) && $product ? $product : false,
            ];
            if ($module->config["type"] == "virtualization")
                echo Modules::getPage("Servers", $server["type"], "create-form-elements", $data);
            else
                echo Modules::getPage("Servers", $server["type"], "create-account-form-elements", $data);

        }

        private function get_module_product_detail()
        {

            $this->takeDatas("language");

            $get_module = (string)Filter::init("POST/module", "route");
            $product_id = (int)Filter::init("POST/product_id", "numbers");
            if (!$get_module || $get_module == "none") die();
            Helper::Load("Products");

            if ($product_id) $product = Products::get("special", $product_id);

            $getModules = Modules::Load("Product", $get_module);

            if (!$getModules) die("Module not found");

            $module = new $get_module();

            $data = [
                'module'  => $module,
                'product' => $product_id && isset($product) && $product ? $product : false,
            ];

            echo Modules::getPage("Product", $get_module, "product-detail", $data);
        }


        private function add_new_addon()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $status = Filter::init("POST/status", "letters");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $requirements = Filter::POST("requirements");
            $requirements = implode(",", $requirements ? $requirements : []);
            $category = (int)Filter::init("POST/category", "numbers");
            $mcategory = Filter::init("POST/mcategory", "letters_numbers", "_");
            $names = Filter::POST("name");
            $descriptions = Filter::POST("description");
            $types = Filter::POST("type");
            $optionss = Filter::POST("options");
            $m_purchasess = Filter::POST("multiple_purchases");
            $compulsoryy = Filter::POST("compulsory");
            $show_by_pp_x = Filter::POST("show_by_pp");
            $min_x = Filter::POST("min");
            $max_x = Filter::POST("max");
            $override_ucurr = Filter::init("POST/override_usrcurrency", "rnumbers");
            $product_link = Filter::init("POST/product_link", "hclear");

            if (!$category) die();

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $product_type_link = '';
            $product_id_link = 0;

            if ($product_link) $product_link = explode("/", $product_link);
            if ($product_link && sizeof($product_link) == 2) {
                $product_type_link = $product_link[0];
                $product_id_link = $product_link[1];
                $optionss = [];
            }


            $lang_data = [];

            Helper::Load("Money");

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $name = Filter::html_clear($names[$lkey]);
                $description = Filter::html_clear($descriptions[$lkey]);
                $type = isset($types[$lkey]) ? Filter::letters($types[$lkey]) : false;
                $options = isset($optionss[$lkey]) ? $optionss[$lkey] : [];
                $m_purchases = isset($m_purchasess[$lkey]) ? Filter::numbers($m_purchasess[$lkey]) : 0;
                $compulsory = isset($compulsoryy[$lkey]) ? Filter::numbers($compulsoryy[$lkey]) : 0;
                $show_by_pp = isset($show_by_pp_x[$lkey]) ? Filter::numbers($show_by_pp_x[$lkey]) : 0;
                $min = isset($min_x[$lkey]) ? Filter::numbers($min_x[$lkey]) : 0;
                $max = isset($max_x[$lkey]) ? Filter::numbers($max_x[$lkey]) : 0;

                $properties = [];

                if ($compulsory) $properties['compulsory'] = $compulsory;
                if ($m_purchases) $properties['multiple_purchases'] = $m_purchases;
                if ($show_by_pp) $properties['show_by_pp'] = $show_by_pp;
                if ($min) $properties['min'] = $min;
                if ($max) $properties['max'] = $max;


                if (Validation::isEmpty($name))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "#field-name-" . $lkey,
                        'message' => __("admin/products/error5", ['{lang}' => strtoupper($lkey)]),
                    ]));

                if (Validation::isEmpty($type))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error8", ['{lang}' => strtoupper($lkey)]),
                    ]));

                $opts = [];
                $size = $options ? sizeof($options["name"]) - 1 : 0;

                if ($options) {
                    for ($i = 0; $i <= $size; $i++) {
                        $opt_name = Filter::html_clear($options["name"][$i]);
                        $opt_period_time = (int)Filter::numbers($options["period_time"][$i]);
                        $opt_period = Filter::letters($options["period"][$i]);
                        $opt_amount = Filter::amount($options["amount"][$i]);
                        $opt_cid = (int)Filter::numbers($options["cid"][$i]);
                        $opt_amount = Money::deformatter($opt_amount, $opt_cid);
                        $opt_module = $options["module"][$i];

                        if ($opt_module) $opt_module = Utility::jdecode($opt_module, true);


                        $opts[] = [
                            'id'          => $i,
                            'name'        => $opt_name,
                            'period'      => $opt_period,
                            'period_time' => $opt_period_time,
                            'amount'      => $opt_amount,
                            'cid'         => $opt_cid,
                            'module'      => $opt_module,
                        ];

                    }
                }

                if (!$product_link && !$opts)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error6", ['{lang}' => strtoupper($lkey)]),
                    ]));

                $lang_data[$lkey] = [
                    'owner_id'    => 0,
                    'lang'        => $lkey,
                    'name'        => $name,
                    'description' => $description,
                    'type'        => $type,
                    'properties'  => $properties ? Utility::jencode($properties) : '',
                    'options'     => Utility::jencode($opts),
                    'lid'         => $size,
                ];
            }

            $insert = $this->model->insert_addon([
                'category'             => $category,
                'mcategory'            => $mcategory,
                'product_type_link'    => $product_type_link,
                'product_id_link'      => $product_id_link,
                'status'               => $status,
                'rank'                 => $rank,
                'override_usrcurrency' => $override_ucurr,
                'requirements'         => $requirements,
            ]);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error7"),
                ]));

            foreach ($lang_data as $data) {
                $data["owner_id"] = $insert;
                $this->model->insert_addon_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-product-addon", [
                'id'   => $insert,
                'name' => $lang_data[$locall]["name"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success5"),
                'redirect' => $this->AdminCRLink("products-2", ["addons", "edit-category"]) . "?id=" . $category,
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


            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $addon = $this->model->get_addon($id);
            if (!$addon) die();


            $status = Filter::init("POST/status", "letters");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $category = (int)Filter::init("POST/category", "numbers");
            $mcategory = Filter::init("POST/mcategory", "letters_numbers", "_");
            $requirements = Filter::POST("requirements");
            $requirements = implode(",", $requirements ? $requirements : []);
            $names = Filter::POST("name");
            $descriptions = Filter::POST("description");
            $types = Filter::POST("type");
            $optionss = Filter::POST("options");
            $m_purchasess = Filter::POST("multiple_purchases");
            $compulsoryy = Filter::POST("compulsory");
            $show_by_pp_x = Filter::POST("show_by_pp");
            $min_x = Filter::POST("min");
            $max_x = Filter::POST("max");
            $override_ucurr = Filter::init("POST/override_usrcurrency", "rnumbers");
            $product_link = Filter::init("POST/product_link", "hclear");


            if (!$category) die();

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $product_type_link = '';
            $product_id_link = 0;

            if ($product_link) $product_link = explode("/", $product_link);
            if ($product_link && sizeof($product_link) == 2) {
                $product_type_link = $product_link[0];
                $product_id_link = $product_link[1];
                $optionss = [];
            }


            $lang_data = [];

            Helper::Load("Money");

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $name = Filter::html_clear($names[$lkey]);
                $description = Filter::html_clear($descriptions[$lkey]);
                $type = isset($types[$lkey]) ? Filter::letters($types[$lkey]) : false;
                $options = isset($optionss[$lkey]) ? $optionss[$lkey] : [];
                $m_purchases = isset($m_purchasess[$lkey]) ? Filter::numbers($m_purchasess[$lkey]) : 0;
                $compulsory = isset($compulsoryy[$lkey]) ? Filter::numbers($compulsoryy[$lkey]) : 0;
                $show_by_pp = isset($show_by_pp_x[$lkey]) ? Filter::numbers($show_by_pp_x[$lkey]) : 0;
                $min = isset($min_x[$lkey]) ? Filter::numbers($min_x[$lkey]) : 0;
                $max = isset($max_x[$lkey]) ? Filter::numbers($max_x[$lkey]) : 0;

                $properties = [];

                if ($m_purchases) $properties['multiple_purchases'] = $m_purchases;
                if ($compulsory) $properties['compulsory'] = $compulsory;
                if ($show_by_pp) $properties['show_by_pp'] = $show_by_pp;
                if ($min) $properties['min'] = $min;
                if ($max) $properties['max'] = $max;
                $addonl = $this->model->get_addon_wlang($id, $lkey);
                $lid = $addonl ? $addonl["lid"] : -1;

                if (Validation::isEmpty($name))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "#field-name-" . $lkey,
                        'message' => __("admin/products/error5", ['{lang}' => strtoupper($lkey)]),
                    ]));

                if (Validation::isEmpty($type))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error8", ['{lang}' => strtoupper($lkey)]),
                    ]));


                $opts = [];
                $size = $options ? sizeof($options["name"]) - 1 : 0;

                if (isset($options["module"]) && $options["module"]) {

                }

                if ($options) {

                    for ($i = 0; $i <= $size; $i++) {
                        $opt_id = isset($options["id"][$i]) ? Filter::numbers($options["id"][$i]) : false;
                        if (!Validation::isInt($opt_id) && !$opt_id) {
                            $lid++;
                            $opt_id = $lid;
                        }
                        $opt_name = Filter::html_clear($options["name"][$i]);
                        $opt_period_time = (int)Filter::numbers($options["period_time"][$i]);
                        $opt_period = Filter::letters($options["period"][$i]);
                        $opt_amount = Filter::amount($options["amount"][$i]);
                        $opt_cid = (int)Filter::numbers($options["cid"][$i]);
                        $opt_amount = Money::deformatter($opt_amount, $opt_cid);
                        $opt_module = $options["module"][$i];

                        if ($opt_module) $opt_module = Utility::jdecode($opt_module, true);

                        $opts[] = [
                            'id'          => $opt_id,
                            'name'        => $opt_name,
                            'period'      => $opt_period,
                            'period_time' => $opt_period_time,
                            'amount'      => $opt_amount,
                            'cid'         => $opt_cid,
                            'module'      => $opt_module,
                        ];
                    }
                }

                if (!$product_link && !$opts)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error6", ['{lang}' => strtoupper($lkey)]),
                    ]));

                $lang_data[$lkey] = [
                    'id'          => $addonl ? $addonl["id"] : 0,
                    'owner_id'    => $id,
                    'lang'        => $lkey,
                    'name'        => $name,
                    'description' => $description,
                    'type'        => $type,
                    'properties'  => $properties ? Utility::jencode($properties) : '',
                    'options'     => Utility::jencode($opts),
                    'lid'         => $lid,
                ];
            }

            $this->model->set_addon($id, [
                'category'             => $category,
                'mcategory'            => $mcategory,
                'product_type_link'    => $product_type_link,
                'product_id_link'      => $product_id_link,
                'status'               => $status,
                'rank'                 => $rank,
                'override_usrcurrency' => $override_ucurr,
                'requirements'         => $requirements,
            ]);

            foreach ($lang_data as $data) {
                $data_id = $data["id"];
                unset($data["id"]);
                if ($data_id)
                    $this->model->set_addon_lang($data_id, $data);
                else
                    $this->model->insert_addon_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "changed-product-addon", [
                'id'   => $id,
                'name' => $addon["name"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success7"),
                'redirect' => $this->AdminCRLink("products-2", ["addons", "edit-category"]) . "?id=" . $category,
            ]);
        }


        private function delete_addon()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            $delete = $this->model->delete_addon($id);

            if ($delete) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "delete", "deleted-product-addon", [
                    'id' => $id,
                ]);
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success6")]);
        }


        private function add_new_addon_category()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $title = Filter::init("POST/title", "hclear");

            if (Validation::isEmpty($title)) die();

            $locall = Config::get("general/local");

            $insert = $this->model->insert_category([
                'type' => "addon",
            ]);

            if (!$insert) die();

            $this->model->insert_category_lang([
                'owner_id' => $insert,
                'lang'     => $locall,
                'title'    => $title,
            ]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-addon-category", [
                'id'   => $insert,
                'name' => $title,
            ]);

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success8")]);
        }


        private function delete_addon_category()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");

            $this->model->delete_category($id);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "delete", "deleted-product-addon-category", [
                'id' => $id,
            ]);

            self::$cache->clear();

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success1")]);
        }


        private function get_addon_category()
        {
            $this->takeDatas("language");

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            $category = $this->model->get_category($id);
            if (!$category) die();

            $result = ['status' => "successful"];
            $result["id"] = $category["id"];
            $result["title"] = $category["title"];

            echo Utility::jencode($result);
        }


        private function edit_addon_category()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            $category = $this->model->get_category($id);
            if (!$category) die();

            $title = Filter::init("POST/title", "hclear");

            if (Validation::isEmpty($title)) die();

            $locall = Config::get("general/local");

            $lcategory = $this->model->get_category_wlang($id, $locall);

            $this->model->set_category_lang($lcategory["id"], [
                'title' => $title,
            ]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-addon-category", [
                'id'   => $id,
                'name' => $category["title"],
            ]);

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success9")]);

        }


        private function add_new_requirement()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $status = Filter::init("POST/status", "letters");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $category = (int)Filter::init("POST/category", "numbers");
            $mcategory = Filter::init("POST/mcategory", "letters_numbers", "_");
            $names = Filter::POST("name");
            $descriptions = Filter::POST("description");
            $types = Filter::POST("type");
            $compulsoryy = Filter::POST("compulsory");
            $optionss = Filter::POST("options");

            if (!$category) die();

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $name = Filter::html_clear($names[$lkey]);
                $description = Filter::html_clear($descriptions[$lkey]);
                $type = isset($types[$lkey]) ? Filter::letters($types[$lkey]) : false;
                $compulsory = isset($compulsoryy[$lkey]) ? Filter::numbers($compulsoryy[$lkey]) : false;
                $properties = [];
                $options = $optionss[$lkey];

                if (Validation::isEmpty($name))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "#field-name-" . $lkey,
                        'message' => __("admin/products/error22", ['{lang}' => strtoupper($lkey)]),
                    ]));

                if (Validation::isEmpty($type))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error8", ['{lang}' => strtoupper($lkey)]),
                    ]));

                $properties["compulsory"] = $compulsory;

                $opts = [];
                $size = sizeof($options["name"]) - 1;

                for ($i = 0; $i <= $size; $i++) {
                    $opt_name = Filter::html_clear($options["name"][$i]);
                    $opt_mkey = Filter::html_clear($options["mkey"][$i]);

                    if (!Validation::isEmpty($opt_name)) {
                        $opts[] = [
                            'id'   => $i,
                            'name' => $opt_name,
                            'mkey' => $opt_mkey,
                        ];
                    }
                }

                $lang_data[$lkey] = [
                    'owner_id'    => 0,
                    'lang'        => $lkey,
                    'name'        => $name,
                    'description' => $description,
                    'type'        => $type,
                    'properties'  => $properties ? Utility::jencode($properties) : '',
                    'options'     => $opts ? Utility::jencode($opts) : '',
                    'lid'         => $size,
                ];
            }


            $m_co_names = Filter::init("POST/module_co_names");
            if ($m_co_names) {
                $new_m_co_names = [];
                foreach ($m_co_names as $k => $v) if ($v) $new_m_co_names[$k] = Filter::html_clear($v);
                $m_co_names = $new_m_co_names;
            }

            $insert = $this->model->insert_requirement([
                'category'        => $category,
                'mcategory'       => $mcategory,
                'status'          => $status,
                'rank'            => $rank,
                'module_co_names' => $m_co_names ? Utility::jencode($m_co_names) : '',
            ]);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error21"),
                ]));

            foreach ($lang_data as $data) {
                $data["owner_id"] = $insert;
                $this->model->insert_requirement_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-product-requirement", [
                'id'   => $insert,
                'name' => $lang_data[$locall]["name"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success16"),
                'redirect' => $this->AdminCRLink("products-2", ["requirements", "edit-category"]) . "?id=" . $category,
            ]);
        }


        private function edit_requirement()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $requirement = $this->model->get_requirement($id);
            if (!$requirement) die();


            $status = Filter::init("POST/status", "letters");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $category = (int)Filter::init("POST/category", "numbers");
            $mcategory = Filter::init("POST/mcategory", "letters_numbers", "_");
            $names = Filter::POST("name");
            $descriptions = Filter::POST("description");
            $types = Filter::POST("type");
            $compulsoryy = Filter::POST("compulsory");
            $optionss = Filter::POST("options");


            if (!$category) die();

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $name = Filter::html_clear($names[$lkey]);
                $description = Filter::html_clear($descriptions[$lkey]);
                $type = isset($types[$lkey]) ? Filter::letters($types[$lkey]) : false;
                $compulsory = isset($compulsoryy[$lkey]) ? Filter::numbers($compulsoryy[$lkey]) : false;
                $options = isset($optionss[$lkey]) ? $optionss[$lkey] : [];
                $properties = [];
                $requirementl = $this->model->get_requirement_wlang($id, $lkey);
                $lid = $requirementl ? $requirementl["lid"] : -1;

                if (Validation::isEmpty($name))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "#field-name-" . $lkey,
                        'message' => __("admin/products/error22", ['{lang}' => strtoupper($lkey)]),
                    ]));

                if (Validation::isEmpty($type))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error8", ['{lang}' => strtoupper($lkey)]),
                    ]));

                $properties["compulsory"] = $compulsory;

                $opts = [];
                $size = $options ? sizeof($options["name"]) - 1 : 0;

                if ($options) {
                    for ($i = 0; $i <= $size; $i++) {
                        $opt_id = isset($options["id"][$i]) ? Filter::numbers($options["id"][$i]) : false;
                        if (!Validation::isInt($opt_id) && !$opt_id) {
                            $lid++;
                            $opt_id = $lid;
                        }
                        $opt_name = Filter::html_clear($options["name"][$i]);
                        $opt_mkey = Filter::html_clear($options["mkey"][$i]);

                        $opts[] = [
                            'id'   => $opt_id,
                            'name' => $opt_name,
                            'mkey' => $opt_mkey,
                        ];
                    }
                }

                $lang_data[$lkey] = [
                    'id'          => $requirementl ? $requirementl["id"] : 0,
                    'owner_id'    => $id,
                    'lang'        => $lkey,
                    'name'        => $name,
                    'description' => $description,
                    'type'        => $type,
                    'properties'  => Utility::jencode($properties),
                    'options'     => Utility::jencode($opts),
                    'lid'         => $lid,
                ];
            }

            $m_co_names = Filter::init("POST/module_co_names");
            if ($m_co_names) {
                $new_m_co_names = [];
                foreach ($m_co_names as $k => $v) if ($v) $new_m_co_names[$k] = Filter::html_clear($v);
                $m_co_names = $new_m_co_names;
            }

            $this->model->set_requirement($id, [
                'category'        => $category,
                'mcategory'       => $mcategory,
                'status'          => $status,
                'rank'            => $rank,
                'module_co_names' => $m_co_names ? Utility::jencode($m_co_names) : '',
            ]);

            foreach ($lang_data as $data) {
                $data_id = $data["id"];
                unset($data["id"]);
                if ($data_id)
                    $this->model->set_requirement_lang($data_id, $data);
                else
                    $this->model->insert_requirement_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "changed-product-requirement", [
                'id'   => $id,
                'name' => $requirement["name"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success17"),
                'redirect' => $this->AdminCRLink("products-2", ["requirements", "edit-category"]) . "?id=" . $category,
            ]);
        }


        private function delete_requirement()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            $delete = $this->model->delete_requirement($id);

            if ($delete) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "delete", "deleted-product-requirement", [
                    'id' => $id,
                ]);
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success18")]);
        }


        private function add_new_requirement_category()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $title = Filter::init("POST/title", "hclear");

            if (Validation::isEmpty($title)) die();

            $locall = Config::get("general/local");

            $insert = $this->model->insert_category([
                'type' => "requirement",
            ]);

            if (!$insert) die();

            $this->model->insert_category_lang([
                'owner_id' => $insert,
                'lang'     => $locall,
                'title'    => $title,
            ]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-requirement-category", [
                'id'   => $insert,
                'name' => $title,
            ]);

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success8")]);
        }


        private function delete_requirement_category()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");

            $this->model->delete_category($id);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "delete", "deleted-product-requirement-category", [
                'id' => $id,
            ]);

            self::$cache->clear();

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success1")]);
        }


        private function get_requirement_category()
        {
            $this->takeDatas("language");

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            $category = $this->model->get_category($id);
            if (!$category) die();

            $result = ['status' => "successful"];
            $result["id"] = $category["id"];
            $result["title"] = $category["title"];

            echo Utility::jencode($result);
        }


        private function edit_requirement_category()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            $category = $this->model->get_category($id);
            if (!$category) die();

            $title = Filter::init("POST/title", "hclear");

            if (Validation::isEmpty($title)) die();

            $locall = Config::get("general/local");

            $lcategory = $this->model->get_category_wlang($id, $locall);

            $this->model->set_category_lang($lcategory["id"], [
                'title' => $title,
            ]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-requirement-category", [
                'id'   => $id,
                'name' => $category["title"],
            ]);

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success9")]);

        }


        private function add_new_hosting()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $titles = Filter::POST("title");
            $featuress = Filter::POST("features");
            $status = Filter::init("POST/status", "letters");
            $category = Filter::init("POST/category", "numbers");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $olpu = Filter::init("POST/order_limit_per_user", "numbers");
            $hide_domain = Filter::init("POST/hide-domain", "numbers");
            $subdomains = Filter::init("POST/subdomains", "hclear");
            $invisibility = Filter::init("POST/invisibility", "numbers");
            if ($invisibility) $visibility = "invisible";
            else $visibility = "visible";
            $external_links = Filter::POST("external_link");
            $popular = (bool)Filter::init("POST/popular", "numbers");
            $ctoc_s_t = (bool)(int)Filter::init("POST/ctoc-service-transfer", "numbers");
            $ctoc_s_t_l = Filter::init("POST/ctoc-service-transfer-limit", "numbers");
            $notes = Filter::init("POST/notes", "dtext");
            $server_id = Filter::init("POST/server_id", "numbers");
            $server_group_id = Filter::init("POST/server_group_id", "numbers");
            $module_data = Filter::POST("module_data");
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
            $addons = Filter::POST("addons");
            $requirements = Filter::POST("requirements");
            $prices = Filter::POST("prices");
            $dns = Filter::POST("dns");
            $override_usrcurrency = (int)Filter::init("POST/override_usrcurrency", "rnumbers");
            $taxexempt = (int)Filter::init("POST/taxexempt", "rnumbers");
            $auto_install = (int)Filter::init("POST/auto_install", "rnumbers");
            $upgradeable_ps = Filter::init("POST/upgradeable-products");
            $affiliate_disable = (int)Filter::init("POST/affiliate_disable", "rnumbers");
            $affiliate_rate = Filter::init("POST/affiliate_rate", "amount");
            $affiliate_rate = str_replace(",", ".", $affiliate_rate);
            if ($affiliate_rate == '') $affiliate_rate = 0;
            $r_s_h = Filter::init("POST/renewal_selection_hide", "numbers");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $features = isset($featuress[$lkey]) ? $featuress[$lkey] : false;
                $external_link = isset($external_links[$lkey]) ? $external_links[$lkey] : false;

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error9", ['{lang}' => $lkeyup]),
                    ]));

                $lang_data[$lkey] = [
                    'owner_id' => 0,
                    'lang'     => $lkey,
                    'title'    => $title,
                    'features' => $features,
                    'options'  => Utility::jencode([
                        'external_link' => $external_link,
                    ]),
                ];
            }

            $options = [
                'panel_type'             => $panel_type,
                'panel_link'             => $panel_link,
                'disk_limit'             => $disk_limit === '' ? "unlimited" : $disk_limit,
                'bandwidth_limit'        => $bandwidth_limit === '' ? "unlimited" : $bandwidth_limit,
                'email_limit'            => $email_limit === '' ? "unlimited" : $email_limit,
                'database_limit'         => $database_limit === '' ? "unlimited" : $database_limit,
                'addons_limit'           => $addons_limit === '' ? "unlimited" : $addons_limit,
                'subdomain_limit'        => $subdomain_limit === '' ? "unlimited" : $subdomain_limit,
                'ftp_limit'              => $ftp_limit === '' ? "unlimited" : $ftp_limit,
                'park_limit'             => $park_limit === '' ? "unlimited" : $park_limit,
                'max_email_per_hour'     => $max_email_per_hour === '' ? "unlimited" : $max_email_per_hour,
                'cpu_limit'              => $cpu_limit ? $cpu_limit : null,
                'server_features'        => $server_features ? $server_features : null,
                'dns'                    => $dns ? $dns : false,
                'renewal_selection_hide' => $r_s_h,
                'hide_domain'            => $hide_domain,
                'auto_install'           => $auto_install,
                'order_limit_per_user'   => $olpu,
            ];

            if ($popular) $options["popular"] = $popular;

            $options["server_group_id"] = $server_group_id;

            if ($server_id) {
                Helper::Load("Products");
                $server = Products::get_server($server_id);
                if ($server) {
                    $module = $server["type"];
                    if (!is_array($module_data)) $module_data = [];
                    $options["server_id"] = $server_id;
                    $options["panel_type"] = $server["type"];
                }
            }

            if ($ctoc_s_t)
                $options["ctoc-service-transfer"] = [
                    'status' => $ctoc_s_t,
                    'limit'  => $ctoc_s_t_l,
                ];


            $product_data = [
                'type'                 => "hosting",
                'ctime'                => DateManager::Now(),
                'status'               => $status,
                'category'             => $category,
                'rank'                 => $rank,
                'override_usrcurrency' => $override_usrcurrency,
                'taxexempt'            => $taxexempt,
                'visibility'           => $visibility,
                'options'              => Utility::jencode($options),
                'notes'                => $notes,
                'subdomains'           => $subdomains,
            ];
            $product_data['module'] = isset($module) && $module ? $module : '';
            $product_data['module_data'] = $module_data ? Utility::jencode($module_data) : '';
            $product_data['addons'] = $addons ? implode(",", $addons) : '';
            $product_data['requirements'] = $requirements ? implode(",", $requirements) : '';
            $product_data['upgradeable_products'] = $upgradeable_ps ? implode(",", $upgradeable_ps) : '';
            $product_data['affiliate_disable'] = $affiliate_disable;
            $product_data['affiliate_rate'] = $affiliate_rate;

            $prices_data = [];
            if ($prices) {
                Helper::Load("Money");
                $size = sizeof($prices["period"]) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $time = isset($prices["time"][$i]) ? Filter::numbers($prices["time"][$i]) : 1;
                    if (!$time) $time = 1;
                    $period = isset($prices["period"][$i]) ? Filter::letters($prices["period"][$i]) : false;
                    $amount = isset($prices["amount"][$i]) ? $prices["amount"][$i] : 0;
                    $setup = isset($prices["setup"][$i]) ? $prices["setup"][$i] : 0;
                    $cid = isset($prices["cid"][$i]) ? Filter::numbers($prices["cid"][$i]) : 0;
                    if ($amount) $amount = Money::deformatter($amount, $cid);
                    else $amount = 0;
                    if ($setup) $setup = Money::deformatter($setup, $cid);
                    else $setup = 0;
                    $discount = isset($prices["discount"][$i]) ? $prices["discount"][$i] : 0;
                    $rank = $i;
                    if ($time && $period && $cid) {
                        $prices_data[] = [
                            'owner'    => "products",
                            'owner_id' => 0,
                            'type'     => "periodicals",
                            'period'   => $period,
                            'time'     => $time,
                            'amount'   => $amount,
                            'setup'    => $setup,
                            'cid'      => $cid,
                            'discount' => $discount,
                            'rank'     => $rank,
                        ];
                    }
                }
            }

            if (!$prices_data)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error11"),
                ]));

            $insert = $this->model->insert_product($product_data);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error10"),
                ]));

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $data["owner_id"] = $insert;
                    $this->model->insert_product_lang($data);
                }
            }

            if ($prices_data) {
                foreach ($prices_data as $data) {
                    $data["owner_id"] = $insert;
                    $this->model->insert_price($data);
                }
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-product", [
                'type' => "hosting",
                'id'   => $insert,
                'name' => $lang_data[$locall]["title"],
            ]);

            Helper::Load("Products");

            Hook::run("ProductCreated", Products::get("hosting", $insert));

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success10"),
                'redirect' => $this->AdminCRLink("products", ["hosting"]),
            ]);

        }


        private function edit_hosting()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $product = $this->model->get_product($id);
            if (!$product) die();

            $poptions = $product["options"] ? Utility::jdecode($product["options"], true) : [];

            $titles = Filter::POST("title");
            $featuress = Filter::POST("features");
            $status = Filter::init("POST/status", "letters");
            $category = Filter::init("POST/category", "numbers");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $olpu = Filter::init("POST/order_limit_per_user", "numbers");
            $hide_domain = Filter::init("POST/hide-domain", "numbers");
            $subdomains = Filter::init("POST/subdomains", "hclear");
            $invisibility = Filter::init("POST/invisibility", "numbers");
            if ($invisibility) $visibility = "invisible";
            else $visibility = "visible";
            $external_links = Filter::init("POST/external_link");
            $ctoc_s_t = (bool)(int)Filter::init("POST/ctoc-service-transfer", "numbers");
            $ctoc_s_t_l = Filter::init("POST/ctoc-service-transfer-limit", "numbers");
            $popular = (bool)Filter::init("POST/popular", "numbers");
            $notes = Filter::init("POST/notes", "dtext");
            $server_id = Filter::init("POST/server_id", "numbers");
            $server_group_id = Filter::init("POST/server_group_id", "numbers");
            $module_data = Filter::POST("module_data");
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
            $addons = Filter::POST("addons");
            $requirements = Filter::POST("requirements");
            $prices = Filter::POST("prices");
            $delete_prices = ltrim(Filter::init("POST/delete_prices", "hclear"), ",");
            $dns = Filter::POST("dns");
            $override_usrcurrency = (int)Filter::init("POST/override_usrcurrency", "rnumbers");
            $taxexempt = (int)Filter::init("POST/taxexempt", "rnumbers");
            $auto_install = (int)Filter::init("POST/auto_install", "rnumbers");
            $upgradeable_ps = Filter::init("POST/upgradeable-products");
            $affiliate_disable = (int)Filter::init("POST/affiliate_disable", "rnumbers");
            $affiliate_rate = Filter::init("POST/affiliate_rate", "amount");
            $affiliate_rate = str_replace(",", ".", $affiliate_rate);
            if ($affiliate_rate == '') $affiliate_rate = 0;
            $r_s_h = Filter::init("POST/renewal_selection_hide", "numbers");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $features = isset($featuress[$lkey]) ? $featuress[$lkey] : false;
                $e_link = isset($external_links[$lkey]) ? Filter::html_clear($external_links[$lkey]) : '';

                $l_options = [
                    'external_link' => $e_link,
                ];


                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error9", ['{lang}' => $lkeyup]),
                    ]));

                $ldata = $this->model->get_product_wlang($id, $lkey);

                $lang_data[$lkey] = [
                    'id'       => $ldata ? $ldata["id"] : 0,
                    'owner_id' => $product["id"],
                    'lang'     => $lkey,
                    'title'    => $title,
                    'features' => $features,
                    'options'  => $l_options ? Utility::jencode($l_options) : '',
                ];
            }

            $options = [
                'panel_type'             => $panel_type,
                'panel_link'             => $panel_link,
                'disk_limit'             => $disk_limit === '' ? "unlimited" : $disk_limit,
                'bandwidth_limit'        => $bandwidth_limit === '' ? "unlimited" : $bandwidth_limit,
                'email_limit'            => $email_limit === '' ? "unlimited" : $email_limit,
                'database_limit'         => $database_limit === '' ? "unlimited" : $database_limit,
                'addons_limit'           => $addons_limit === '' ? "unlimited" : $addons_limit,
                'subdomain_limit'        => $subdomain_limit === '' ? "unlimited" : $subdomain_limit,
                'ftp_limit'              => $ftp_limit === '' ? "unlimited" : $ftp_limit,
                'park_limit'             => $park_limit === '' ? "unlimited" : $park_limit,
                'max_email_per_hour'     => $max_email_per_hour === '' ? "unlimited" : $max_email_per_hour,
                'cpu_limit'              => $cpu_limit ? $cpu_limit : null,
                'server_features'        => $server_features ? $server_features : null,
                'dns'                    => $dns ? $dns : false,
                'renewal_selection_hide' => $r_s_h,
                'auto_install'           => $auto_install,
                'hide_domain'            => $hide_domain,
                'order_limit_per_user'   => $olpu,
            ];

            if ($popular) $options["popular"] = $popular;

            $options["server_group_id"] = $server_group_id;

            if ($server_id) {
                Helper::Load("Products");
                $server = Products::get_server($server_id);
                if ($server) {
                    $module = $server["type"];
                    $options["server_id"] = $server_id;
                    $options["panel_type"] = $server["type"];
                }
            }

            if (($ctoc_s_t && !isset($poptions["ctoc-service-transfer"])) || (isset($poptions["ctoc-service-transfer"]) && ($ctoc_s_t != $poptions["ctoc-service-transfer"]["status"] || $ctoc_s_t_l != $poptions["ctoc-service-transfer"]["limit"])))
                $options["ctoc-service-transfer"] = ['status' => $ctoc_s_t, 'limit' => $ctoc_s_t_l];


            $product_data = [
                'status'               => $status,
                'category'             => $category,
                'rank'                 => $rank,
                'override_usrcurrency' => $override_usrcurrency,
                'taxexempt'            => $taxexempt,
                'visibility'           => $visibility,
                'options'              => Utility::jencode($options),
                'notes'                => $notes,
                'subdomains'           => $subdomains,
            ];
            $product_data['module'] = isset($module) && $module ? $module : '';
            $product_data['addons'] = $addons ? implode(",", $addons) : '';
            $product_data['requirements'] = $requirements ? implode(",", $requirements) : '';
            $product_data['upgradeable_products'] = $upgradeable_ps ? implode(",", $upgradeable_ps) : '';
            $product_data['affiliate_disable'] = $affiliate_disable;
            $product_data['affiliate_rate'] = $affiliate_rate;

            if ($module_data) $product_data['module_data'] = Utility::jencode($module_data);

            $delete_prices = $delete_prices ? explode(",", $delete_prices) : [];
            foreach ($delete_prices as $del) $this->model->delete_price($del);
            $prices_data = [];
            if ($prices) {
                Helper::Load("Money");
                $size = sizeof($prices["period"]) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $pid = isset($prices["id"][$i]) ? Filter::numbers($prices["id"][$i]) : 0;
                    $time = isset($prices["time"][$i]) ? Filter::numbers($prices["time"][$i]) : 1;
                    if (!$time) $time = 1;
                    $period = isset($prices["period"][$i]) ? Filter::letters($prices["period"][$i]) : false;
                    $amount = isset($prices["amount"][$i]) ? $prices["amount"][$i] : 0;
                    $setup = isset($prices["setup"][$i]) ? $prices["setup"][$i] : 0;
                    $cid = isset($prices["cid"][$i]) ? Filter::numbers($prices["cid"][$i]) : 0;
                    if ($amount) $amount = Money::deformatter($amount, $cid);
                    else $amount = 0;
                    if ($setup) $setup = Money::deformatter($setup, $cid);
                    else $setup = 0;
                    $discount = isset($prices["discount"][$i]) ? $prices["discount"][$i] : 0;
                    $rank = $i;
                    if ($time && $period && $cid) {
                        $prices_data[] = [
                            'id'       => $pid,
                            'owner'    => "products",
                            'owner_id' => $id,
                            'type'     => "periodicals",
                            'period'   => $period,
                            'time'     => $time,
                            'amount'   => $amount,
                            'setup'    => $setup,
                            'cid'      => $cid,
                            'discount' => $discount,
                            'rank'     => $rank,
                        ];
                    }
                }
            }

            if (!$prices_data)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error11"),
                ]));

            $this->model->set_product($id, $product_data);


            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $lang_id = $data["id"];
                    unset($data["id"]);
                    if ($lang_id) $this->model->set_product_lang($lang_id, $data);
                    if (!$lang_id) $this->model->insert_product_lang($data);
                }
            }

            if ($prices_data) {
                foreach ($prices_data as $data) {
                    $data_id = $data["id"];
                    unset($data["id"]);
                    if ($data_id) $this->model->set_price($data_id, $data);
                    if (!$data_id) $this->model->insert_price($data);
                }
            }
            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-product", [
                'type' => "hosting",
                'id'   => $id,
                'name' => $product["title"],
            ]);

            Helper::Load("Products");

            Hook::run("ProductModified", Products::get("hosting", $id));

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success11"),
                'redirect' => $this->AdminCRLink("products", ["hosting"]),
            ]);
        }


        private function add_new_server()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $titles = Filter::POST("title");
            $featuress = Filter::POST("features");
            $locations = Filter::POST("location");
            $status = Filter::init("POST/status", "letters");
            $category = Filter::init("POST/category", "numbers");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $olpu = Filter::init("POST/order_limit_per_user", "numbers");
            $stock = Filter::init("POST/stock", "numbers");
            $invisibility = Filter::init("POST/invisibility", "numbers");
            if ($invisibility) $visibility = "invisible";
            else $visibility = "visible";
            $external_links = Filter::POST("external_link");
            $popular = (bool)Filter::init("POST/popular", "numbers");
            $ctoc_s_t = (bool)(int)Filter::init("POST/ctoc-service-transfer", "numbers");
            $ctoc_s_t_l = Filter::init("POST/ctoc-service-transfer-limit", "numbers");
            $order_image = Filter::FILES("order_image");
            $notes = Filter::init("POST/notes", "dtext");
            $processor = Filter::init("POST/processor", "hclear");
            $ram = Filter::init("POST/ram", "hclear");
            $disk_space = Filter::init("POST/disk-space", "hclear");
            $bandwidth = Filter::init("POST/bandwidth", "hclear");
            $raid = Filter::init("POST/raid", "hclear");
            $addons = Filter::POST("addons");
            $requirements = Filter::POST("requirements");
            $prices = Filter::POST("prices");
            $override_usrcurrency = (int)Filter::init("POST/override_usrcurrency", "rnumbers");
            $taxexempt = (int)Filter::init("POST/taxexempt", "rnumbers");
            $auto_install = (int)Filter::init("POST/auto_install", "rnumbers");
            $server_group_id = Filter::init("POST/server_group_id", "numbers");
            $server_id = Filter::init("POST/server_id", "numbers");
            $module_data = Filter::POST("module_data");
            $upgradeable_ps = Filter::init("POST/upgradeable-products");
            $affiliate_disable = (int)Filter::init("POST/affiliate_disable", "rnumbers");
            $affiliate_rate = Filter::init("POST/affiliate_rate", "amount");
            $affiliate_rate = str_replace(",", ".", $affiliate_rate);
            if ($affiliate_rate == '') $affiliate_rate = 0;
            $r_s_h = Filter::init("POST/renewal_selection_hide", "numbers");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $features = isset($featuress[$lkey]) ? $featuress[$lkey] : false;
                $location = isset($locations[$lkey]) ? $locations[$lkey] : false;
                $external_link = isset($external_links[$lkey]) ? $external_links[$lkey] : false;

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error9", ['{lang}' => $lkeyup]),
                    ]));

                $lopt = [
                    'location'      => $location,
                    'external_link' => $external_link,
                ];

                $lang_data[$lkey] = [
                    'owner_id' => 0,
                    'lang'     => $lkey,
                    'title'    => $title,
                    'features' => $features,
                    'options'  => Utility::jencode($lopt),
                ];
            }

            $options = [
                'popular'                => $popular,
                'auto_install'           => $auto_install,
                'renewal_selection_hide' => $r_s_h,
                'order_limit_per_user'   => $olpu,
            ];

            if ($processor) $options['processor'] = $processor;
            if ($ram) $options['ram'] = $ram;
            if ($disk_space) $options['disk-space'] = $disk_space;
            if ($bandwidth) $options['bandwidth'] = $bandwidth;
            if ($raid) $options['raid'] = $raid;

            if ($ctoc_s_t)
                $options["ctoc-service-transfer"] = [
                    'status' => $ctoc_s_t,
                    'limit'  => $ctoc_s_t_l,
                ];


            if ($order_image) {
                Helper::Load(["Uploads", "Image"]);
                $pfolder = Config::get("pictures/products/folder");
                $osizing = Config::get("pictures/products/order/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($order_image, [
                    'image-upload' => true,
                    'folder'       => $pfolder,
                    'width'        => $osizing["width"],
                    'height'       => $osizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='order_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $orimgpicture = current($upload->operands);
                $orimgpicture = $orimgpicture["file_path"];
            }


            $options["server_group_id"] = $server_group_id;

            if ($server_id) {
                Helper::Load("Products");
                $server = Products::get_server($server_id);
                if ($server) {
                    $module = $server["type"];
                    if (!is_array($module_data)) $module_data = [];
                    $options["server_id"] = $server_id;
                }
            }

            $product_data = [
                'type'                 => "server",
                'ctime'                => DateManager::Now(),
                'status'               => $status,
                'category'             => $category,
                'rank'                 => $rank,
                'stock'                => $stock,
                'override_usrcurrency' => $override_usrcurrency,
                'taxexempt'            => $taxexempt,
                'visibility'           => $visibility,
                'options'              => Utility::jencode($options),
                'notes'                => $notes,
            ];

            $product_data['module'] = isset($module) && $module ? $module : '';
            $product_data['module_data'] = $module_data ? Utility::jencode($module_data) : '';
            $product_data['addons'] = $addons ? implode(",", $addons) : '';
            $product_data['requirements'] = $requirements ? implode(",", $requirements) : '';
            $product_data['upgradeable_products'] = $upgradeable_ps ? implode(",", $upgradeable_ps) : '';
            $product_data['affiliate_disable'] = $affiliate_disable;
            $product_data['affiliate_rate'] = $affiliate_rate;


            $prices_data = [];
            if ($prices) {
                Helper::Load("Money");
                $size = sizeof($prices["period"]) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $time = isset($prices["time"][$i]) ? Filter::numbers($prices["time"][$i]) : 1;
                    if (!$time) $time = 1;
                    $period = isset($prices["period"][$i]) ? Filter::letters($prices["period"][$i]) : false;
                    $amount = isset($prices["amount"][$i]) ? $prices["amount"][$i] : 0;
                    $setup = isset($prices["setup"][$i]) ? $prices["setup"][$i] : 0;
                    $cid = isset($prices["cid"][$i]) ? Filter::numbers($prices["cid"][$i]) : 0;
                    if ($amount) $amount = Money::deformatter($amount, $cid);
                    else $amount = 0;
                    if ($setup) $setup = Money::deformatter($setup, $cid);
                    else $setup = 0;
                    $discount = isset($prices["discount"][$i]) ? $prices["discount"][$i] : 0;
                    $rank = $i;
                    if ($time && $period && $cid) {
                        $prices_data[] = [
                            'owner'    => "products",
                            'owner_id' => 0,
                            'type'     => "periodicals",
                            'period'   => $period,
                            'time'     => $time,
                            'amount'   => $amount,
                            'setup'    => $setup,
                            'cid'      => $cid,
                            'discount' => $discount,
                            'rank'     => $rank,
                        ];
                    }
                }
            }

            if (!$prices_data)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error11"),
                ]));

            $insert = $this->model->insert_product($product_data);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error10"),
                ]));

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $data["owner_id"] = $insert;
                    $this->model->insert_product_lang($data);
                }
            }

            if ($prices_data) {
                foreach ($prices_data as $data) {
                    $data["owner_id"] = $insert;
                    $this->model->insert_price($data);
                }
            }

            if (isset($orimgpicture) && $orimgpicture) $this->model->insert_picture("product", $insert, "order", $orimgpicture);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-product", [
                'type' => "server",
                'id'   => $insert,
                'name' => $lang_data[$locall]["title"],
            ]);

            Helper::Load("Products");

            Hook::run("ProductCreated", Products::get("server", $insert));

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success19"),
                'redirect' => $this->AdminCRLink("products", ["server"]),
            ]);

        }


        private function edit_server()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $product = $this->model->get_product($id);
            if (!$product) die();


            $poptions = Utility::jdecode($product["options"], true);

            $titles = Filter::POST("title");
            $featuress = Filter::POST("features");
            $locations = Filter::POST("location");
            $status = Filter::init("POST/status", "letters");
            $category = Filter::init("POST/category", "numbers");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $olpu = Filter::init("POST/order_limit_per_user", "numbers");
            $stock = Filter::init("POST/stock", "numbers");
            $invisibility = Filter::init("POST/invisibility", "numbers");
            if ($invisibility) $visibility = "invisible";
            else $visibility = "visible";
            $external_links = Filter::POST("external_link");
            $popular = (bool)Filter::init("POST/popular", "numbers");
            $ctoc_s_t = (bool)(int)Filter::init("POST/ctoc-service-transfer", "numbers");
            $ctoc_s_t_l = Filter::init("POST/ctoc-service-transfer-limit", "numbers");
            $order_image = Filter::FILES("order_image");
            $notes = Filter::init("POST/notes", "dtext");
            $processor = Filter::init("POST/processor", "hclear");
            $ram = Filter::init("POST/ram", "hclear");
            $disk_space = Filter::init("POST/disk-space", "hclear");
            $bandwidth = Filter::init("POST/bandwidth", "hclear");
            $raid = Filter::init("POST/raid", "hclear");
            $addons = Filter::POST("addons");
            $requirements = Filter::POST("requirements");
            $prices = Filter::POST("prices");
            $delete_prices = ltrim(Filter::init("POST/delete_prices", "hclear"), ",");
            $override_usrcurrency = (int)Filter::init("POST/override_usrcurrency", "rnumbers");
            $taxexempt = (int)Filter::init("POST/taxexempt", "rnumbers");
            $auto_install = (int)Filter::init("POST/auto_install", "rnumbers");
            $server_id = Filter::init("POST/server_id", "numbers");
            $server_group_id = Filter::init("POST/server_group_id", "numbers");
            $module_data = Filter::POST("module_data");
            $upgradeable_ps = Filter::init("POST/upgradeable-products");
            $affiliate_disable = (int)Filter::init("POST/affiliate_disable", "rnumbers");
            $affiliate_rate = Filter::init("POST/affiliate_rate", "amount");
            $affiliate_rate = str_replace(",", ".", $affiliate_rate);
            if ($affiliate_rate == '') $affiliate_rate = 0;
            $r_s_h = Filter::init("POST/renewal_selection_hide", "numbers");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $features = isset($featuress[$lkey]) ? $featuress[$lkey] : false;
                $location = isset($locations[$lkey]) ? $locations[$lkey] : false;
                $external_link = isset($external_links[$lkey]) ? $external_links[$lkey] : false;

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error9", ['{lang}' => $lkeyup]),
                    ]));

                $ldata = $this->model->get_product_wlang($id, $lkey);

                $lopt = [
                    'location'      => $location,
                    'external_link' => $external_link,
                ];

                $lang_data[$lkey] = [
                    'id'       => $ldata ? $ldata["id"] : 0,
                    'owner_id' => $product["id"],
                    'lang'     => $lkey,
                    'title'    => $title,
                    'features' => $features,
                    'options'  => Utility::jencode($lopt),
                ];
            }

            $options = [
                'popular'                => $popular,
                'auto_install'           => $auto_install,
                'renewal_selection_hide' => $r_s_h,
                'order_limit_per_user'   => $olpu,
            ];

            if ($processor) $options['processor'] = $processor;
            if ($ram) $options['ram'] = $ram;
            if ($disk_space) $options['disk-space'] = $disk_space;
            if ($bandwidth) $options['bandwidth'] = $bandwidth;
            if ($raid) $options['raid'] = $raid;

            if (($ctoc_s_t && !isset($poptions["ctoc-service-transfer"])) || (isset($poptions["ctoc-service-transfer"]) && ($ctoc_s_t != $poptions["ctoc-service-transfer"]["status"] || $ctoc_s_t_l != $poptions["ctoc-service-transfer"]["limit"])))
                $options["ctoc-service-transfer"] = ['status' => $ctoc_s_t, 'limit' => $ctoc_s_t_l];


            if ($order_image) {
                Helper::Load(["Uploads", "Image"]);
                $pfolder = Config::get("pictures/products/folder");
                $osizing = Config::get("pictures/products/order/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($order_image, [
                    'image-upload' => true,
                    'folder'       => $pfolder,
                    'width'        => $osizing["width"],
                    'height'       => $osizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='order_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $orimgpicture = current($upload->operands);
                $orimgpicture = $orimgpicture["file_path"];
                $before_pic = $this->model->get_picture("product", $id, "order");
                if ($before_pic) {
                    FileManager::file_delete($pfolder . $before_pic);
                    FileManager::file_delete($pfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("product", $id, "order");
                }
                $this->model->insert_picture("product", $id, "order", $orimgpicture);
            }

            $options["server_group_id"] = $server_group_id;

            if ($server_id) {
                Helper::Load("Products");
                $server = Products::get_server($server_id);
                if ($server) {
                    $module = $server["type"];
                    $options["server_id"] = $server_id;
                }
            }


            $product_data = [
                'status'               => $status,
                'category'             => $category,
                'rank'                 => $rank,
                'stock'                => $stock,
                'override_usrcurrency' => $override_usrcurrency,
                'taxexempt'            => $taxexempt,
                'visibility'           => $visibility,
                'options'              => Utility::jencode($options),
                'notes'                => $notes,
            ];
            $product_data['module'] = isset($module) && $module ? $module : '';
            $product_data['addons'] = $addons ? implode(",", $addons) : '';
            $product_data['requirements'] = $requirements ? implode(",", $requirements) : '';
            $product_data['upgradeable_products'] = $upgradeable_ps ? implode(",", $upgradeable_ps) : '';
            $product_data['affiliate_disable'] = $affiliate_disable;
            $product_data['affiliate_rate'] = $affiliate_rate;

            if ($module_data) $product_data['module_data'] = Utility::jencode($module_data);


            $delete_prices = $delete_prices ? explode(",", $delete_prices) : [];
            foreach ($delete_prices as $del) $this->model->delete_price($del);
            $prices_data = [];
            if ($prices) {
                Helper::Load("Money");
                $size = sizeof($prices["period"]) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $pid = isset($prices["id"][$i]) ? Filter::numbers($prices["id"][$i]) : 0;
                    $time = isset($prices["time"][$i]) ? Filter::numbers($prices["time"][$i]) : 1;
                    if (!$time) $time = 1;
                    $period = isset($prices["period"][$i]) ? Filter::letters($prices["period"][$i]) : false;
                    $amount = isset($prices["amount"][$i]) ? $prices["amount"][$i] : 0;
                    $setup = isset($prices["setup"][$i]) ? $prices["setup"][$i] : 0;
                    $cid = isset($prices["cid"][$i]) ? Filter::numbers($prices["cid"][$i]) : 0;
                    if ($amount) $amount = Money::deformatter($amount, $cid);
                    else $amount = 0;

                    if ($setup) $setup = Money::deformatter($setup, $cid);
                    else $setup = 0;
                    $discount = isset($prices["discount"][$i]) ? $prices["discount"][$i] : 0;
                    $rank = $i;
                    if ($time && $period && $cid) {
                        $prices_data[] = [
                            'id'       => $pid,
                            'owner'    => "products",
                            'owner_id' => $id,
                            'type'     => "periodicals",
                            'period'   => $period,
                            'time'     => $time,
                            'amount'   => $amount,
                            'setup'    => $setup,
                            'cid'      => $cid,
                            'discount' => $discount,
                            'rank'     => $rank,
                        ];
                    }
                }
            }

            if (!$prices_data)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error11"),
                ]));

            $this->model->set_product($id, $product_data);


            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $lang_id = $data["id"];
                    unset($data["id"]);
                    if ($lang_id) $this->model->set_product_lang($lang_id, $data);
                    if (!$lang_id) $this->model->insert_product_lang($data);
                }
            }

            if ($prices_data) {
                foreach ($prices_data as $data) {
                    $data_id = $data["id"];
                    unset($data["id"]);
                    if ($data_id) $this->model->set_price($data_id, $data);
                    if (!$data_id) $this->model->insert_price($data);
                }
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-product", [
                'type' => "server",
                'id'   => $id,
                'name' => $product["title"],
            ]);

            Helper::Load("Products");

            Hook::run("ProductModified", Products::get("server", $id));

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/succcess20"),
                'redirect' => $this->AdminCRLink("products", ["server"]),
            ]);
        }


        private function test_shared_server_connect()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if ($id) {
                $server = $this->model->get_shared_server($id);
            }


            $type = Filter::init("POST/type", "route");
            $ip = Filter::init("POST/ip", "hclear");
            $name = Filter::init("POST/name", "hclear");
            $username = Filter::init("POST/username", "hclear");
            $password = Filter::init("POST/password", "password");
            $access_hash = Filter::POST("access-hash");
            $secure = Filter::init("POST/secure", "numbers");
            $port = Filter::init("POST/port", "numbers");

            if ($password == "*****" && isset($server) && $server)
                $password = Crypt::decode($server["password"], Config::get("crypt/user"));

            if (Validation::isEmpty($type))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='type']",
                    'message' => __("admin/products/error12"),
                ]));

            if (Validation::isEmpty($ip))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='ip']",
                    'message' => __("admin/products/error13"),
                ]));

            if (Validation::isEmpty($username))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='username']",
                    'message' => __("admin/products/error14"),
                ]));

            if (Validation::isEmpty($password) && Validation::isEmpty($access_hash))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='password']",
                    'message' => __("admin/products/error15"),
                ]));

            $modules = Modules::Load("Servers", $type);
            if (!$modules) die();
            $module = $type . "_Module";
            if (!class_exists($module)) die("Module Class Not Found");
            $module = new $module([
                'name'        => $name,
                'ip'          => $ip,
                'port'        => $port,
                'username'    => $username,
                'password'    => $password,
                'access_hash' => $access_hash,
                'secure'      => $secure,
            ]);

            if ($module->config["server-info-checker"]) {
                $check = $module->testConnect();
                if (!$check)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error16", ['{error}' => $module->error]),
                    ]));
            }

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/products/success12"),
            ]);
        }


        private function edit_hosting_shared_server_group()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load("Money");

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $server = $this->model->get_shared_server_group($id);
            if (!$server) die();

            $name = Filter::init("POST/name", "hclear");
            $fill_type = (int)Filter::init("POST/fill_type", "numbers");
            $servers = Filter::init("POST/servers");


            if (Validation::isEmpty($name))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/shared-server-tx17"),
                ]));

            Helper::Load("Products");

            $type = '';
            $module = '';

            if ($servers && is_array($servers)) {
                foreach ($servers as $k => $s) {
                    $s = (int)$s;
                    $server = Products::get_server($s);
                    if (!$server) {
                        unset($servers[$k]);
                        continue;
                    }

                    if ($server["maxaccounts"] < 1)
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("admin/products/shared-server-tx29"),
                        ]));

                    $m = Modules::Load("Servers", $server["type"], true);
                    $mc = $m["config"];
                    $mc_type = $mc["type"] == "hosting" ? "hosting" : "server";

                    if (!$type) $type = $mc_type;
                    if (!$module) $module = $server["type"];

                    if ($type != $mc_type || $module != $server["type"])
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("admin/products/shared-server-tx21"),
                        ]));

                }
            }


            if (!is_array($servers) || sizeof($servers) < 1)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/shared-server-tx20"),
                ]));


            $servers = is_array($servers) && $servers ? implode(",", $servers) : '';


            $this->model->set_shared_server_group($id, [
                'type'      => $type,
                'name'      => $name,
                'fill_type' => $fill_type,
                'servers'   => $servers,
            ]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-hosting-shared-server-group", [
                'name' => $name,
                'id'   => $id,
            ]);

            Hook::run("ProductServerGroupModified", $this->model->get_shared_server_group($id));

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/shared-server-tx16"),
                'redirect' => $this->AdminCRLink("products-2", ['hosting', 'shared-server-groups']),
            ]);
        }


        private function add_new_hosting_shared_server_group()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load("Money");

            $name = Filter::init("POST/name", "hclear");
            $fill_type = (int)Filter::init("POST/fill_type", "numbers");
            $servers = Filter::init("POST/servers");

            if (Validation::isEmpty($name))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/shared-server-tx17"),
                ]));

            Helper::Load("Products");

            $type = '';
            $module = '';

            if ($servers && is_array($servers)) {
                foreach ($servers as $k => $s) {
                    $s = (int)$s;
                    $server = Products::get_server($s);
                    if (!$server) {
                        unset($servers[$k]);
                        continue;
                    }
                    $m = Modules::Load("Servers", $server["type"], true);
                    $mc = $m["config"];
                    $mc_type = $mc["type"] == "hosting" ? "hosting" : "server";

                    if (!$type) $type = $mc_type;
                    if (!$module) $module = $server["type"];

                    if ($type != $mc_type || $module != $server["type"])
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("admin/products/shared-server-tx21"),
                        ]));

                }
            }


            if (!is_array($servers) || sizeof($servers) < 1)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/shared-server-tx20"),
                ]));


            $servers = is_array($servers) && $servers ? implode(",", $servers) : '';


            $insert = $this->model->insert_shared_server_group([
                'type'      => $type,
                'name'      => $name,
                'fill_type' => $fill_type,
                'servers'   => $servers,
            ]);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Something went wrong.",
                ]));


            Hook::run("ProductServerGroupCreated", $this->model->get_shared_server_group($insert));


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-hosting-shared-server-group", [
                'name' => $name,
                'id'   => $insert,
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/shared-server-tx15"),
                'redirect' => $this->AdminCRLink("products-2", ['hosting', 'shared-server-groups']),
            ]);
        }


        private function add_new_hosting_shared_server()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load("Money");

            $name = Filter::init("POST/name", "hclear");
            $ns1 = Filter::init("POST/ns1", "domain");
            $ns2 = Filter::init("POST/ns2", "domain");
            $ns3 = Filter::init("POST/ns3", "domain");
            $ns4 = Filter::init("POST/ns4", "domain");
            $maxaccounts = (int)Filter::init("POST/maxaccounts", "numbers");
            $full_alert = (int)Filter::init("POST/full_alert", "numbers");
            $cost_price = Filter::init("POST/cost_price", "amount");
            $cost_currency = Filter::init("POST/cost_currency", "numbers");
            $cost_price = Money::deformatter($cost_price, $cost_currency);


            $type = Filter::init("POST/type", "route");
            $ip = Filter::init("POST/ip", "hclear");
            $username = Filter::init("POST/username", "hclear");
            $password = Filter::init("POST/password", "password");
            $port = (int)Filter::init("POST/port", "numbers");
            $secure = (int)Filter::init("POST/secure", "numbers");
            $updowngrade_remove_server = Filter::init("POST/updowngrade_remove_server", "letters");
            $updowngrade_remove_server_day = Filter::init("POST/updowngrade_remove_server_day", "numbers");
            $access_hash = Filter::POST("access-hash");


            if (Validation::isEmpty($name))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='name']",
                    'message' => __("admin/products/error17"),
                ]));

            if (Validation::isEmpty($name))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='name']",
                    'message' => __("admin/products/error17"),
                ]));


            if (Validation::isEmpty($type))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='type']",
                    'message' => __("admin/products/error12"),
                ]));

            if (Validation::isEmpty($ip))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='ip']",
                    'message' => __("admin/products/error13"),
                ]));

            if (Validation::isEmpty($username))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='username']",
                    'message' => __("admin/products/error14"),
                ]));

            if (Validation::isEmpty($password) && Validation::isEmpty($access_hash))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='password']",
                    'message' => __("admin/products/error15"),
                ]));

            $modules = Modules::Load("Servers", $type);
            if (!$modules) die();
            $module = $type . "_Module";
            if (!class_exists($module)) die("Module Class Not Found");
            $module = new $module([
                'name'        => $name,
                'ip'          => $ip,
                'port'        => $port,
                'username'    => $username,
                'password'    => $password,
                'access_hash' => $access_hash,
                'secure'      => $secure,
            ]);


            if ($module->config["server-info-checker"]) {
                $check = $module->testConnect();
                if (!$check)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error16", ['{error}' => $module->error]),
                    ]));
            }


            $ipCheck = $this->model->check_shared_server_ip($ip, $username);

            if ($ipCheck)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name=ip]",
                    'message' => __("admin/products/error20"),
                ]));

            if ($updowngrade_remove_server == "then")
                $updowngrade_remove_server = "then|" . $updowngrade_remove_server_day;
            elseif (!$updowngrade_remove_server)
                $updowngrade_remove_server = "none";

            $insert = $this->model->insert_shared_server([
                'type'                      => $type,
                'name'                      => $name,
                'ns1'                       => $ns1,
                'ns2'                       => $ns2,
                'ns3'                       => $ns3,
                'ns4'                       => $ns4,
                'maxaccounts'               => $maxaccounts,
                'full_alert'                => $full_alert,
                'cost_price'                => $cost_price,
                'cost_currency'             => $cost_currency,
                'ip'                        => $ip,
                'username'                  => $username,
                'password'                  => Crypt::encode($password, Config::get("crypt/user")),
                'access_hash'               => $access_hash,
                'secure'                    => $secure,
                'port'                      => $port,
                'updowngrade_remove_server' => $updowngrade_remove_server,
            ]);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error19"),
                ]));

            Helper::Load("Products");

            Hook::run("ProductServerCreated", Products::get_server($insert));


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-hosting-shared-server", [
                'name' => $name,
                'id'   => $insert,
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success13"),
                'redirect' => $this->AdminCRLink("products-2", ['hosting', 'shared-servers']),
            ]);
        }


        private function delete_shared_server()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            $server = $this->model->get_shared_server($id);
            if (!$server) die();

            $server_data = Products::get_server($id);
            if (!$server_data) return false;

            $services = $this->model->db->select("id")->from("users_products AS usp");
            $services->where("JSON_UNQUOTE(JSON_EXTRACT(usp.options,'$.server_id'))", "=", $id, "&&");
            $services->where("usp.status", "!=", "cancelled");
            $services = $services->build() ? $services->rowCounter() : 0;

            if ($services > 0)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error36"),
                ]));

            $this->model->delete_shared_server($id);

            Hook::run("ProductServerDeleted", $server_data);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "delete", "deleted-shared-server", [
                'id'   => $id,
                'name' => $server["name"],
                'ip'   => $server["ip"],
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/products/success14"),
            ]);
        }


        private function delete_shared_server_group()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            $server = $this->model->get_shared_server_group($id);
            if (!$server) die();

            $find_products = $this->model->find_server_group_in_products($id);

            if ($find_products)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/shared-server-tx22"),
                ]));

            $this->model->delete_shared_server_group($id);

            Hook::run("ProductServerGroupDeleted", $server);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "delete", "deleted-shared-server-group", [
                'id'   => $id,
                'name' => $server["name"],
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/invoices/success2-delete"),
            ]);
        }


        private function edit_hosting_shared_server()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load("Money");

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $server = $this->model->get_shared_server($id);
            if (!$server) die();

            $name = Filter::init("POST/name", "hclear");
            $ns1 = Filter::init("POST/ns1", "domain");
            $ns2 = Filter::init("POST/ns2", "domain");
            $ns3 = Filter::init("POST/ns3", "domain");
            $ns4 = Filter::init("POST/ns4", "domain");
            $maxaccounts = (int)Filter::init("POST/maxaccounts", "numbers");
            $full_alert = (int)Filter::init("POST/full_alert", "numbers");
            $cost_price = Filter::init("POST/cost_price", "amount");
            $cost_currency = Filter::init("POST/cost_currency", "numbers");
            $cost_price = Money::deformatter($cost_price, $cost_currency);
            $status = Filter::init("POST/status", "letters");


            $type = Filter::init("POST/type", "route");
            $ip = Filter::init("POST/ip", "hclear");
            $username = Filter::init("POST/username", "hclear");
            $password = Filter::init("POST/password", "password");
            $access_hash = Filter::POST("access-hash");
            $secure = (int)Filter::init("POST/secure", "numbers");
            $port = (int)Filter::init("POST/port", "numbers");
            $updowngrade_remove_server = Filter::init("POST/updowngrade_remove_server", "letters");
            $updowngrade_remove_server_day = Filter::init("POST/updowngrade_remove_server_day", "numbers");

            if (Validation::isEmpty($name))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='name']",
                    'message' => __("admin/products/error17"),
                ]));

            if (Validation::isEmpty($name))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='name']",
                    'message' => __("admin/products/error17"),
                ]));


            if (Validation::isEmpty($type))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='type']",
                    'message' => __("admin/products/error12"),
                ]));

            if (Validation::isEmpty($ip))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='ip']",
                    'message' => __("admin/products/error13"),
                ]));

            if (Validation::isEmpty($username))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='username']",
                    'message' => __("admin/products/error14"),
                ]));

            if (Validation::isEmpty($password) && Validation::isEmpty($access_hash))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='password']",
                    'message' => __("admin/products/error15"),
                ]));

            $check_sg = $this->model->check_server_in_group($id);
            if ($maxaccounts < 1 && $check_sg)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/shared-server-tx28", ['{name}' => $check_sg]),
                ]));


            $modules = Modules::Load("Servers", $type);
            if (!$modules) die();
            $module = $type . "_Module";
            if (!class_exists($module)) die("Module Class Not Found");
            $module = new $module([
                'name'        => $name,
                'ip'          => $ip,
                'port'        => $port,
                'username'    => $username,
                'password'    => $password == "*****" ? Crypt::decode($server["password"], Config::get("crypt/user")) : $password,
                'access_hash' => $access_hash,
                'secure'      => $secure,
            ]);

            if ($module->config["server-info-checker"] && ($server["ip"] != $ip || $password != "*****")) {
                $check = $module->testConnect();
                if (!$check)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error16", ['{error}' => $module->error]),
                    ]));
            }

            if ($ip != $server["ip"]) {
                $ipCheck = $this->model->check_shared_server_ip($ip, $username);

                if ($ipCheck)
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name=ip]",
                        'message' => __("admin/products/error20"),
                    ]));
            }

            if ($updowngrade_remove_server == "then")
                $updowngrade_remove_server = "then|" . $updowngrade_remove_server_day;
            elseif (!$updowngrade_remove_server)
                $updowngrade_remove_server = "none";

            $this->model->set_shared_server($id, [
                'type'                      => $type,
                'name'                      => $name,
                'ns1'                       => $ns1,
                'ns2'                       => $ns2,
                'ns3'                       => $ns3,
                'ns4'                       => $ns4,
                'full_alert'                => $full_alert,
                'cost_price'                => $cost_price,
                'cost_currency'             => $cost_currency,
                'maxaccounts'               => $maxaccounts,
                'ip'                        => $ip,
                'username'                  => $username,
                'password'                  => $password != "*****" ? Crypt::encode($password, Config::get("crypt/user")) : $server["password"],
                'access_hash'               => $access_hash,
                'secure'                    => $secure,
                'port'                      => $port,
                'updowngrade_remove_server' => $updowngrade_remove_server,
                'status'                    => $status,
            ]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-hosting-shared-server", [
                'name' => $name,
                'id'   => $id,
            ]);

            Hook::run("ProductServerModified", Products::get_server($id));

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success15"),
                'redirect' => $this->AdminCRLink("products-2", ['hosting', 'shared-servers']),
            ]);
        }


        private function hosting_shared_server_import()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $server = $this->model->get_shared_server($id);
            if (!$server) die();
            $type = Filter::POST("type");

            if ($type == "server") {

                $list = Filter::POST("list");

                if (!$list)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error34"),
                    ]));

                Helper::Load(["Orders", "Products", "Money"]);

                $imported = false;
                $imports = [];

                $module_name = $server["type"];


                Modules::Load("Servers", $module_name);

                $selectorModule = $module_name . "_Module";
                if (!class_exists($selectorModule))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => "Corrupted module file",
                    ]));

                $server_pw = Crypt::decode($server["password"], Config::get("crypt/user"));
                $serverInfo = $server;
                $serverInfo["password"] = $server_pw;
                $module = new $selectorModule($serverInfo);

                if (!method_exists($module, "list_vps"))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => "Corrupted module file",
                    ]));

                $list_vps = $module->list_vps();
                if (!$list_vps)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => $module->error,
                    ]));


                foreach ($list as $k => $v) {
                    $list_data = $list_vps[$k] ?? [];
                    if (!$list_data) continue;
                    $hostname = $list_data["hostname"] ?? '';
                    $ip = $list_data["ip"] ?? '';
                    $assigned_ips = $list_data["assigned_ips"] ?? [];
                    $login = $list_data["login"] ?? [];
                    $status = $list_data["status"] ?? "active";
                    $add_options = $list_data["add_options"] ?? [];
                    $cdate = $v["cdate"];
                    $duedate = $v["duedate"];
                    $user_id = isset($v["user_id"]) ? $v["user_id"] : 0;
                    $product_id = isset($v["product_id"]) ? $v["product_id"] : 0;
                    $period = isset($v["period"]) ? $v["period"] : 0;

                    if ($user_id && $product_id) {
                        if ($this->model->sync_server($server, $list_data["sync_terms"])) continue;

                        $udata = User::getData($user_id, "lang", "array");
                        $ulang = $udata["lang"];
                        $product = Products::get("server", $product_id, $ulang);
                        $price = $product["price"][$period];
                        if (!$duedate) $duedate = DateManager::next_date([$cdate, $price["period"] => $price["time"]]);

                        $import = Orders::import_server($user_id, [
                            'module'       => $type,
                            'hostname'     => $hostname,
                            'ip'           => $ip,
                            'assigned_ips' => $assigned_ips,
                            'login'        => $login,
                            'server_id'    => $server["id"],
                            'access_data'  => $list_data["access_data"],
                            'add_options'  => $add_options,
                        ], $cdate, $duedate, $product, $price, $status);
                        if ($import) {
                            $imports[] = $import["name"] . " (#" . $import["id"] . ")";
                            $imported = true;
                        }
                    }
                }

                if (!$imported)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error34"),
                    ]));

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "hosting-shared-server-data-were-imported", [
                    'module'    => $server["type"],
                    'hostname'  => $server["name"],
                    'server_ip' => $server["ip"],
                    'id'        => $id,
                    'imported'  => implode(", ", $imports),
                ]);

            } else {
                $type = $server["type"];


                $accounts = Filter::POST("accounts");

                if (!$accounts)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error34"),
                    ]));

                Helper::Load(["Orders", "Products", "Money"]);

                $imported = false;
                $imports = [];

                foreach ($accounts as $k => $v) {
                    $domain = $v["domain"];
                    $username = $k;
                    $cdate = $v["cdate"];
                    $duedate = $v["duedate"];
                    $user_id = isset($v["user_id"]) ? $v["user_id"] : 0;
                    $product_id = isset($v["product_id"]) ? $v["product_id"] : 0;
                    $period = isset($v["period"]) ? $v["period"] : 0;

                    if ($username && $user_id && $product_id) {
                        if ($this->model->sync_hosting($domain, $username, $server)) continue;

                        $udata = User::getData($user_id, "lang", "array");
                        $ulang = $udata["lang"];
                        $product = Products::get("hosting", $product_id, $ulang);
                        $price = $product["price"][$period];
                        if (!$duedate) $duedate = DateManager::next_date([$cdate, $price["period"] => $price["time"]]);

                        $import = Orders::import_hosting($user_id, [
                            'module'    => $type,
                            'domain'    => $domain,
                            'username'  => $username,
                            'server_id' => $server["id"],
                        ], $cdate, $duedate, $product, $price);
                        if ($import) {
                            $imports[] = $import["name"] . " (#" . $import["id"] . ")";
                            $imported = true;
                        }
                    }
                }

                if (!$imported)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error34"),
                    ]));

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "hosting-shared-server-accounts-were-imported", [
                    'module'    => $type,
                    'hostname'  => $server["name"],
                    'server_ip' => $server["ip"],
                    'id'        => $id,
                    'imported'  => implode(", ", $imports),
                ]);
            }


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/products/success39"),
            ]);
        }


        private function update_hosting_settings()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $titles = Filter::POST("title");
            $sub_titles = Filter::POST("sub_title");
            $colors = Filter::POST("color");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $faqs = Filter::POST("faq");
            $upgrading = (int)Filter::init("POST/upgrading", "numbers");
            $allow_sub_hosting = (int)Filter::init("POST/allow_sub_hosting", "numbers");
            $ctoc_s_t = (bool)(int)Filter::init("POST/ctoc-service-transfer", "numbers");
            $ctoc_s_t_l = Filter::init("POST/ctoc-service-transfer-limit", "numbers");

            $icon_image = Filter::FILES("icon_image");
            $icon = Filter::init("POST/icon", "hclear");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $sub_title = isset($sub_titles[$lkey]) ? Filter::html_clear($sub_titles[$lkey]) : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $color = isset($colors[$lkey]) ? $colors[$lkey] : false;
                $faqx = isset($faqs[$lkey]) ? $faqs[$lkey] : false;
                $faq = $faqx ? [] : null;
                if ($faqx) {
                    $size = sizeof($faqx["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_title = $faqx["title"][$i];
                        $f_desc = $faqx["description"][$i];
                        if ($f_title)
                            $faq[] = [
                                'title'       => $f_title,
                                'description' => $f_desc,
                            ];

                    }
                }

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error1", ['{lang}' => strtoupper($lkey)]),
                    ]));

                $data_p['category-hosting'] = [];
                $data_p2 = $data_p;
                $data_m['meta-hosting'] = [];
                $data_p['category-hosting']["title"] = $title;
                $data_p['category-hosting']["sub_title"] = $sub_title;
                $data_p['category-hosting']["content"] = $content;
                $data_p['category-hosting']["faq"] = null;
                $data_p['category-hosting']["options"] = null;
                $data_p2['category-hosting']["options"] = [
                    'color' => $color ? "#" . $color : '',
                ];
                $data_p2['category-hosting']["faq"] = $faq;
                $data_m['meta-hosting']["title"] = $seo_title;
                $data_m['meta-hosting']["keywords"] = $seo_keywords;
                $data_m['meta-hosting']["description"] = $seo_description;

                $data = Bootstrap::$lang->get("constants", $lkey);
                $data = array_replace_recursive($data, $data_p);
                $data = array_replace_recursive($data, $data_p2);

                $data2 = Bootstrap::$lang->get_cm("website/products", false, $lkey);
                $data2 = array_replace_recursive($data2, $data_m);

                $data_export = Utility::array_export($data, ['pwith' => true]);
                $data2_export = Utility::array_export($data2, ['pwith' => true]);


                FileManager::file_write(LANG_DIR . $lkey . DS . "constants.php", $data_export);
                FileManager::file_write(LANG_DIR . $lkey . DS . "cm" . DS . "website" . DS . "products.php", $data2_export);
            }

            $config_sets = [];


            if ($upgrading != Config::get("options/product-upgrade/hosting"))
                $config_sets["options"]["product-upgrade"]["hosting"] = $upgrading;

            if ($allow_sub_hosting != Config::get("options/allow-sub-hosting"))
                $config_sets["options"]["allow-sub-hosting"] = $allow_sub_hosting;

            if ($ctoc_s_t != Config::get("options/ctoc-service-transfer/hosting/status"))
                $config_sets["options"]["ctoc-service-transfer"]["hosting"]["status"] = $ctoc_s_t;

            if ($ctoc_s_t_l != Config::get("options/ctoc-service-transfer/hosting/limit"))
                $config_sets["options"]["ctoc-service-transfer"]["hosting"]["limit"] = $ctoc_s_t_l;


            if ($icon != Config::get("options/category-icon/hosting"))
                $config_sets["options"]["category-icon"]["hosting"] = $icon;

            if ($icon_image) {
                Helper::Load(["Uploads"]);
                $ifolder = Config::get("pictures/category-icon/folder");
                $isizing = Config::get("pictures/category-icon/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($icon_image, [
                    'image-upload' => true,
                    'folder'       => $ifolder,
                    'width'        => $isizing["width"],
                    'height'       => $isizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='icon_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $ipicture = current($upload->operands);
                $ipicture = $ipicture["file_path"];
                $before_pic = $this->model->get_picture("category", 1, "icon");
                if ($before_pic) {
                    FileManager::file_delete($ifolder . $before_pic);
                    $this->model->delete_picture("category", 1, "icon");
                }
                $this->model->insert_picture("category", 1, "icon", $ipicture);
            }


            if ($config_sets) {
                if (isset($config_sets["options"])) {
                    $options_result = Config::set("options", $config_sets["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                }
            }


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-product-group", [
                'id' => "hosting",
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success21"),
                'redirect' => $this->AdminCRLink("products", ["hosting"]),
            ]);
        }


        private function update_server_settings()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $titles = Filter::POST("title");
            $sub_titles = Filter::POST("sub_title");
            $colors = Filter::POST("color");
            $list_templates = Filter::POST("list_template");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $faqs = Filter::POST("faq");
            $upgrading = (int)Filter::init("POST/upgrading", "numbers");
            $hidsein = (bool)(int)Filter::init("POST/hidsein", "numbers");
            $ctoc_s_t = (bool)(int)Filter::init("POST/ctoc-service-transfer", "numbers");
            $ctoc_s_t_l = Filter::init("POST/ctoc-service-transfer-limit", "numbers");
            $icon_image = Filter::FILES("icon_image");
            $icon = Filter::init("POST/icon", "hclear");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $sub_title = isset($sub_titles[$lkey]) ? Filter::html_clear($sub_titles[$lkey]) : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $color = isset($colors[$lkey]) ? $colors[$lkey] : false;
                $list_template = isset($list_templates[$lkey]) ? $list_templates[$lkey] : false;
                $faqx = isset($faqs[$lkey]) ? $faqs[$lkey] : false;
                $faq = $faqx ? [] : null;
                if ($faqx) {
                    $size = sizeof($faqx["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_title = $faqx["title"][$i];
                        $f_desc = $faqx["description"][$i];
                        if ($f_title)
                            $faq[] = [
                                'title'       => $f_title,
                                'description' => $f_desc,
                            ];

                    }
                }

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error1", ['{lang}' => strtoupper($lkey)]),
                    ]));

                $data_p['category-server'] = [];
                $data_p2 = $data_p;
                $data_m['meta-server'] = [];
                $data_p['category-server']["title"] = $title;
                $data_p['category-server']["sub_title"] = $sub_title;
                $data_p['category-server']["content"] = $content;
                $data_p['category-server']["faq"] = null;
                $data_p['category-server']["options"] = null;
                $data_p2['category-server']["options"] = [
                    'color'         => $color ? "#" . $color : '',
                    'list_template' => $list_template,
                ];
                $data_p2['category-server']["faq"] = $faq;
                $data_m['meta-server']["title"] = $seo_title;
                $data_m['meta-server']["keywords"] = $seo_keywords;
                $data_m['meta-server']["description"] = $seo_description;

                $data = Bootstrap::$lang->get("constants", $lkey);
                $data = array_replace_recursive($data, $data_p);
                $data = array_replace_recursive($data, $data_p2);

                $data2 = Bootstrap::$lang->get_cm("website/products", false, $lkey);
                $data2 = array_replace_recursive($data2, $data_m);

                $data_export = Utility::array_export($data, ['pwith' => true]);
                $data2_export = Utility::array_export($data2, ['pwith' => true]);


                FileManager::file_write(LANG_DIR . $lkey . DS . "constants.php", $data_export);
                FileManager::file_write(LANG_DIR . $lkey . DS . "cm" . DS . "website" . DS . "products.php", $data2_export);
            }


            $config_sets = [];


            if ($upgrading != Config::get("options/product-upgrade/server"))
                $config_sets["options"]["product-upgrade"]["server"] = $upgrading;


            if ($hidsein != Config::get("options/hidsein"))
                $config_sets["options"]["hidsein"] = $hidsein;


            if ($ctoc_s_t != Config::get("options/ctoc-service-transfer/server/status"))
                $config_sets["options"]["ctoc-service-transfer"]["server"]["status"] = $ctoc_s_t;

            if ($ctoc_s_t_l != Config::get("options/ctoc-service-transfer/server/limit"))
                $config_sets["options"]["ctoc-service-transfer"]["server"]["limit"] = $ctoc_s_t_l;

            if ($icon != Config::get("options/category-icon/server"))
                $config_sets["options"]["category-icon"]["server"] = $icon;


            if ($icon_image) {
                Helper::Load(["Uploads"]);
                $ifolder = Config::get("pictures/category-icon/folder");
                $isizing = Config::get("pictures/category-icon/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($icon_image, [
                    'image-upload' => true,
                    'folder'       => $ifolder,
                    'width'        => $isizing["width"],
                    'height'       => $isizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='icon_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $ipicture = current($upload->operands);
                $ipicture = $ipicture["file_path"];
                $before_pic = $this->model->get_picture("category", 2, "icon");
                if ($before_pic) {
                    FileManager::file_delete($ifolder . $before_pic);
                    $this->model->delete_picture("category", 2, "icon");
                }
                $this->model->insert_picture("category", 2, "icon", $ipicture);
            }


            if ($config_sets) {
                if (isset($config_sets["options"])) {
                    $options_result = Config::set("options", $config_sets["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                }
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-product-group", [
                'id' => "server",
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success21"),
                'redirect' => $this->AdminCRLink("products", ["server"]),
            ]);
        }


        private function update_sms_settings()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $titles = Filter::POST("title");
            $sub_titles = Filter::POST("sub_title");
            $colors = Filter::POST("color");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $faqs = Filter::POST("faq");
            $op_notesx = Filter::POST("op_notes");
            $ctoc_s_t = (bool)(int)Filter::init("POST/ctoc-service-transfer", "numbers");
            $ctoc_s_t_l = Filter::init("POST/ctoc-service-transfer-limit", "numbers");

            $icon_image = Filter::FILES("icon_image");
            $icon = Filter::init("POST/icon", "hclear");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $sub_title = isset($sub_titles[$lkey]) ? Filter::html_clear($sub_titles[$lkey]) : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $color = isset($colors[$lkey]) ? $colors[$lkey] : false;
                $faqx = isset($faqs[$lkey]) ? $faqs[$lkey] : false;
                $op_notesxx = isset($op_notesx[$lkey]) ? $op_notesx[$lkey] : false;

                $faq = $faqx ? [] : null;
                if ($faqx) {
                    $size = sizeof($faqx["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_title = $faqx["title"][$i];
                        $f_desc = $faqx["description"][$i];
                        if ($f_title)
                            $faq[] = [
                                'title'       => $f_title,
                                'description' => $f_desc,
                            ];

                    }
                }

                $op_notes = $op_notesxx ? [] : null;
                if ($op_notesxx) {
                    $size = sizeof($op_notesxx["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $op_title = $op_notesxx["title"][$i];
                        $op_desc = $op_notesxx["description"][$i];
                        if ($op_title)
                            $op_notes[] = [
                                'title'       => $op_title,
                                'description' => $op_desc,
                            ];
                    }
                }

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error1", ['{lang}' => strtoupper($lkey)]),
                    ]));

                $data_p['category-sms'] = [];
                $data_p2 = $data_p;
                $data_m['meta-sms'] = [];
                $data_p['category-sms']["title"] = $title;
                $data_p['category-sms']["sub_title"] = $sub_title;
                $data_p['category-sms']["content"] = $content;
                $data_p['category-sms']["faq"] = null;
                $data_p['category-sms']["op_notes"] = null;
                $data_p['category-sms']["options"] = null;
                $data_p2['category-sms']["options"] = [
                    'color' => $color ? "#" . $color : '',
                ];
                $data_p2['category-sms']["faq"] = $faq;
                $data_p2['category-sms']["op_notes"] = $op_notes;
                $data_m['meta-sms']["title"] = $seo_title;
                $data_m['meta-sms']["keywords"] = $seo_keywords;
                $data_m['meta-sms']["description"] = $seo_description;

                $data = Bootstrap::$lang->get("constants", $lkey);
                $data = array_replace_recursive($data, $data_p);
                $data = array_replace_recursive($data, $data_p2);

                $data2 = Bootstrap::$lang->get_cm("website/products", false, $lkey);
                $data2 = array_replace_recursive($data2, $data_m);

                $data_export = Utility::array_export($data, ['pwith' => true]);
                $data2_export = Utility::array_export($data2, ['pwith' => true]);


                FileManager::file_write(LANG_DIR . $lkey . DS . "constants.php", $data_export);
                FileManager::file_write(LANG_DIR . $lkey . DS . "cm" . DS . "website" . DS . "products.php", $data2_export);
            }

            $config_sets = [];

            if ($ctoc_s_t != Config::get("options/ctoc-service-transfer/sms/status"))
                $config_sets["options"]["ctoc-service-transfer"]["sms"]["status"] = $ctoc_s_t;

            if ($ctoc_s_t_l != Config::get("options/ctoc-service-transfer/sms/limit"))
                $config_sets["options"]["ctoc-service-transfer"]["sms"]["limit"] = $ctoc_s_t_l;

            if ($icon != Config::get("options/category-icon/sms"))
                $config_sets["options"]["category-icon"]["sms"] = $icon;

            if ($icon_image) {
                Helper::Load(["Uploads"]);
                $ifolder = Config::get("pictures/category-icon/folder");
                $isizing = Config::get("pictures/category-icon/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($icon_image, [
                    'image-upload' => true,
                    'folder'       => $ifolder,
                    'width'        => $isizing["width"],
                    'height'       => $isizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='icon_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $ipicture = current($upload->operands);
                $ipicture = $ipicture["file_path"];
                $before_pic = $this->model->get_picture("category", 6, "icon");
                if ($before_pic) {
                    FileManager::file_delete($ifolder . $before_pic);
                    $this->model->delete_picture("category", 6, "icon");
                }
                $this->model->insert_picture("category", 6, "icon", $ipicture);
            }


            if ($config_sets) {
                if (isset($config_sets["options"])) {
                    $options_result = Config::set("options", $config_sets["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                }
            }


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-product-group", [
                'id' => "sms",
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success21"),
                'redirect' => $this->AdminCRLink("products", ["sms"]),
            ]);
        }


        private function add_new_group()
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
                $check = $this->model->category_route_check($slug, $lang, "products");
                if ($check)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error23", ['{lang}' => strtoupper($lang)]),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $sub_titles = Filter::POST("sub_title");
            $hbackground = Filter::FILES("hbackground");
            $color = Filter::init("POST/color", "letters_numbers");
            $upgrading = Filter::init("POST/upgrading", "numbers");
            $list_template = (int)Filter::init("POST/list_template", "numbers");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $faqs = Filter::POST("faq");
            $columnss = Filter::POST("columns");
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
                        'message' => __("admin/products/error24", ['{lang}' => strtoupper($lkey)]),
                    ]));

                $faqx = isset($faqs[$lkey]) ? $faqs[$lkey] : false;
                $faq = $faqx ? [] : null;
                if ($faqx) {
                    $size = sizeof($faqx["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_title = $faqx["title"][$i];
                        $f_desc = $faqx["description"][$i];
                        if ($f_title)
                            $faq[] = [
                                'title'       => $f_title,
                                'description' => $f_desc,
                            ];

                    }
                }
                if ($faq) $faq = Utility::jencode($faq);
                else $faq = '';


                $columnx = isset($columnss[$lkey]) ? $columnss[$lkey] : false;
                $columns = $columnx ? [] : null;
                $c_id = 0;
                if ($columnx) {
                    $size = sizeof($columnx["name"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $c_name = $columnx["name"][$i];
                        if (!Validation::isEmpty($c_name)) {
                            $c_id++;
                            $columns[] = [
                                'id'   => $c_id,
                                'name' => $c_name,
                            ];
                        }
                    }
                }

                $optionsl = [];

                $optionsl["columns"] = $columns;
                $optionsl["columns_lid"] = $c_id;

                if (___("package/permalink", false, $lkey)) {
                    $route = !$route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->category_route_check($route, $lkey, "products");
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
                        'faq'             => $faq,
                        'options'         => $optionsl ? Utility::jencode($optionsl) : '',
                    ];

            }

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
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
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, 331, 100, [
                    'folder-date-detect' => true,
                ]);
            }

            if (!$lang_data) die("Error! #1");

            $options = Utility::jencode([
                'color'         => $color ? "#" . $color : '',
                'upgrading'     => $upgrading ? true : false,
                'list_template' => $list_template,
            ]);

            $insert = $this->model->insert_category([
                'status'  => $status,
                'type'    => "products",
                'kind'    => "special",
                'options' => $options,
                'ctime'   => DateManager::Now(),
            ]);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error3"),
                ]));

            if (isset($hpicture) && $hpicture) $this->model->insert_picture("category", $insert, "header-background", $hpicture);
            foreach ($lang_data as $key => $data) {
                $data["owner_id"] = $insert;
                if (!$data["route"]) $data["route"] = $insert;
                $this->model->insert_category_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-product-group", [
                'name' => $lang_data[$locall]["title"],
                'id'   => $insert,
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success22"),
                'redirect' => $this->AdminCRLink("products", ["special-" . $insert]),
            ]);
        }


        private function update_special_settings()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $param = $this->params[0];
            $exp = explode("-", $param);
            $id = (int)$exp[1];
            $category = $this->model->get_category($id);
            if (!$category) die("Not found group");


            $slug = Filter::init("GET/slug", "route");
            $lang = Filter::init("GET/lang", "route");
            if ($slug && $lang && ___("package/permalink", false, $lang)) {
                $check = $this->model->category_route_check($slug, $lang, "products");
                if ($check && $check != $category["id"])
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error23", ['{lang}' => strtoupper($lang)]),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $sub_titles = Filter::POST("sub_title");
            $hbackground = Filter::FILES("hbackground");
            $color = Filter::init("POST/color", "letters_numbers");
            $upgrading = Filter::init("POST/upgrading", "numbers");
            $ctoc_s_t = (bool)(int)Filter::init("POST/ctoc-service-transfer", "numbers");
            $ctoc_s_t_l = Filter::init("POST/ctoc-service-transfer-limit", "numbers");
            $list_template = (int)Filter::init("POST/list_template", "numbers");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $faqs = Filter::POST("faq");
            $columnss = Filter::POST("columns");
            $status = Filter::init("POST/status", "letters");

            $icon_image = Filter::FILES("icon_image");
            $icon = Filter::init("POST/icon", "hclear");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $ldata = $this->model->get_category_wlang($id, $lkey);
                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $route = isset($routes[$lkey]) ? Filter::html_clear($routes[$lkey]) : false;
                $sub_title = isset($sub_titles[$lkey]) ? Filter::html_clear($sub_titles[$lkey]) : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $faqx = isset($faqs[$lkey]) ? $faqs[$lkey] : false;
                $faq = $faqx ? [] : null;
                if ($faqx) {
                    $size = sizeof($faqx["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_title = $faqx["title"][$i];
                        $f_desc = $faqx["description"][$i];
                        if ($f_title)
                            $faq[] = [
                                'title'       => $f_title,
                                'description' => $f_desc,
                            ];

                    }
                }
                if ($faq) $faq = Utility::jencode($faq);
                else $faq = '';

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error1", ['{lang}' => strtoupper($lkey)]),
                    ]));


                $optionsl = $ldata["options"] ? Utility::jdecode($ldata["options"], true) : [];
                $columnx = isset($columnss[$lkey]) ? $columnss[$lkey] : false;
                $columns = $columnx ? [] : null;
                $columns_lid = isset($optionsl["columns_lid"]) ? $optionsl["columns_lid"] : 0;
                if ($columnx) {
                    $size = sizeof($columnx["name"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $c_id = isset($columnx["id"][$i]) ? $columnx["id"][$i] : 0;
                        if (!$c_id) {
                            $columns_lid++;
                            $c_id = $columns_lid;
                        }
                        $c_name = $columnx["name"][$i];
                        $columns[] = [
                            'id'   => $c_id,
                            'name' => $c_name,
                        ];
                    }
                }

                $optionsl["columns"] = $columns;
                $optionsl["columns_lid"] = isset($c_id) ? $c_id : 0;

                if (___("package/permalink", false, $lkey)) {
                    $route = !$route || $id == $route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->category_route_check($route, $lkey, "products");
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
                        'faq'             => $faq,
                        'options'         => $optionsl ? Utility::jencode($optionsl) : '',
                    ];
            }

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
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
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, 331, 100, [
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

            if ($icon_image) {
                Helper::Load(["Uploads"]);
                $ifolder = Config::get("pictures/category-icon/folder");
                $isizing = Config::get("pictures/category-icon/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($icon_image, [
                    'image-upload' => true,
                    'folder'       => $ifolder,
                    'width'        => $isizing["width"],
                    'height'       => $isizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='icon_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $ipicture = current($upload->operands);
                $ipicture = $ipicture["file_path"];
                $before_pic = $this->model->get_picture("category", $id, "icon");
                if ($before_pic) {
                    FileManager::file_delete($ifolder . $before_pic);
                    $this->model->delete_picture("category", $id, "icon");
                }
                $this->model->insert_picture("category", $id, "icon", $ipicture);
            }


            if (!$lang_data) die("Error! #1");


            $options = Utility::jencode([
                'color'                 => $color ? "#" . $color : '',
                'icon'                  => $icon,
                'upgrading'             => $upgrading ? true : false,
                'ctoc-service-transfer' => [
                    'status' => $ctoc_s_t,
                    'limit'  => $ctoc_s_t_l,
                ],
                'list_template'         => $list_template,
            ]);

            $update = $this->model->set_category($id, [
                'status'  => $status,
                'options' => $options,
            ]);

            if (!$update)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error4"),
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
            User::addAction($adata["id"], "alteration", "changed-product-category", [
                'name' => $category["title"],
                'id'   => $id,
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success23"),
                'redirect' => $this->AdminCRLink("products", ["special-" . $id]),
            ]);

        }


        private function add_new_special_category()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $param = $this->params[0];
            $exp = explode("-", $param);
            $id = (int)$exp[1];
            $group = $this->model->get_category($id);
            if (!$group) die("Not found group");

            $slug = Filter::init("GET/slug", "route");
            $lang = Filter::init("GET/lang", "route");
            if ($slug && $lang && ___("package/permalink", false, $lang)) {
                $check = $this->model->category_route_check($slug, $lang, "products");
                if ($check)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error23", ['{lang}' => strtoupper($lang)]),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $sub_titles = Filter::POST("sub_title");
            $hbackground = Filter::FILES("hbackground");
            $icon_image = Filter::FILES("icon_image");
            $color = Filter::init("POST/color", "letters_numbers");
            $list_template = (int)Filter::init("POST/list_template", "numbers");
            $icon = Filter::init("POST/icon", "hclear");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $faqs = Filter::POST("faq");
            $columnss = Filter::POST("columns");
            $parent = (int)Filter::init("POST/parent", "numbers");
            if (!$parent) $parent = $group["id"];
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
                $faqx = isset($faqs[$lkey]) ? $faqs[$lkey] : false;
                $faq = $faqx ? [] : null;
                if ($faqx) {
                    $size = sizeof($faqx["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_title = $faqx["title"][$i];
                        $f_desc = $faqx["description"][$i];
                        if ($f_title)
                            $faq[] = [
                                'title'       => $f_title,
                                'description' => $f_desc,
                            ];

                    }
                }
                if ($faq) $faq = Utility::jencode($faq);
                else $faq = '';

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error1", ['{lang}' => strtoupper($lkey)]),
                    ]));


                $columnx = isset($columnss[$lkey]) ? $columnss[$lkey] : false;
                $columns = $columnx ? [] : null;
                $c_id = 0;
                if ($columnx) {
                    $size = sizeof($columnx["name"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $c_name = $columnx["name"][$i];
                        if (!Validation::isEmpty($c_name)) {
                            $c_id++;
                            $columns[] = [
                                'id'   => $c_id,
                                'name' => $c_name,
                            ];
                        }
                    }
                }

                $optionsl = [];

                $optionsl["columns"] = $columns;
                $optionsl["columns_lid"] = $c_id;

                if (___("package/permalink", false, $lkey)) {
                    $route = !$route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->category_route_check($route, $lkey, "products");
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
                        'faq'             => $faq,
                        'options'         => $optionsl ? Utility::jencode($optionsl) : '',
                    ];

            }

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
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
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, 331, 100, [
                    'folder-date-detect' => true,
                ]);
            }


            if ($icon_image) {
                Helper::Load(["Uploads"]);
                $ifolder = Config::get("pictures/category-icon/folder");
                $isizing = Config::get("pictures/category-icon/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($icon_image, [
                    'image-upload' => true,
                    'folder'       => $ifolder,
                    'width'        => $isizing["width"],
                    'height'       => $isizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='icon_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $ipicture = current($upload->operands);
                $ipicture = $ipicture["file_path"];
            }

            if (!$lang_data) die("Error! #1");

            $options = Utility::jencode([
                'color'         => $color ? "#" . $color : '',
                'icon'          => $icon,
                'list_template' => $list_template,
            ]);

            $p_data = [
                'status'  => $status,
                'parent'  => $parent,
                'rank'    => $rank,
                'type'    => "products",
                'kind'    => "special",
                'kind_id' => $group["id"],
                'options' => $options,
                'ctime'   => DateManager::Now(),
            ];

            $insert = $this->model->insert_category($p_data);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error3"),
                ]));

            if (isset($hpicture) && $hpicture) $this->model->insert_picture("category", $insert, "header-background", $hpicture);
            if (isset($ipicture) && $ipicture) $this->model->insert_picture("category", $insert, "icon", $ipicture);
            foreach ($lang_data as $key => $data) {
                $data["owner_id"] = $insert;
                if (!$data["route"]) $data["route"] = $insert;
                $lang_data[$key] = $data;
                $this->model->insert_category_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-product-category", [
                'name' => $lang_data[$locall]["title"],
                'id'   => $insert,
            ]);

            $p_data["id"] = $insert;
            Hook::run("ProductCategoryCreated", ['data' => $p_data, 'languages' => $lang_data]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success2"),
                'redirect' => $this->AdminCRLink("products-2", ["special-" . $group["id"], "categories"]),
            ]);

        }


        private function edit_special_category()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $param = $this->params[0];
            $exp = explode("-", $param);
            $id = (int)$exp[1];
            $group = $this->model->get_category($id);
            if (!$group) die("Not found group");

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $category = $this->model->get_category($id);
            if (!$category) die();

            $slug = Filter::init("GET/slug", "route");
            $lang = Filter::init("GET/lang", "route");
            if ($slug && $lang && ___("package/permalink", false, $lang)) {
                $check = $this->model->category_route_check($slug, $lang, "products");
                if ($check && $check != $category["id"])
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error23", ['{lang}' => strtoupper($lang)]),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $sub_titles = Filter::POST("sub_title");
            $hbackground = Filter::FILES("hbackground");
            $icon_image = Filter::FILES("icon_image");
            $color = Filter::init("POST/color", "letters_numbers");
            $list_template = (int)Filter::init("POST/list_template", "numbers");
            $icon = Filter::init("POST/icon", "hclear");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $faqs = Filter::POST("faq");
            $columnss = Filter::POST("columns");
            $parent = (int)Filter::init("POST/parent", "numbers");
            if (!$parent) $parent = $group["id"];
            $rank = (int)Filter::init("POST/rank", "numbers");
            $status = Filter::init("POST/status", "letters");

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $ldata = $this->model->get_category_wlang($id, $lkey);
                $optionsl = isset($ldata["options"]) ? Utility::jdecode($ldata["options"], true) : [];
                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $route = isset($routes[$lkey]) ? Filter::html_clear($routes[$lkey]) : false;
                $sub_title = isset($sub_titles[$lkey]) ? Filter::html_clear($sub_titles[$lkey]) : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $faqx = isset($faqs[$lkey]) ? $faqs[$lkey] : false;
                $faq = $faqx ? [] : null;
                if ($faqx) {
                    $size = sizeof($faqx["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_title = $faqx["title"][$i];
                        $f_desc = $faqx["description"][$i];
                        if ($f_title)
                            $faq[] = [
                                'title'       => $f_title,
                                'description' => $f_desc,
                            ];

                    }
                }
                if ($faq) $faq = Utility::jencode($faq);
                else $faq = '';

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error1", ['{lang}' => strtoupper($lkey)]),
                    ]));


                $columnx = isset($columnss[$lkey]) ? $columnss[$lkey] : false;
                $columns = $columnx ? [] : null;
                $columns_lid = isset($optionsl["columns_lid"]) ? $optionsl["columns_lid"] : 0;
                if ($columnx) {
                    $size = sizeof($columnx["name"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $c_id = isset($columnx["id"][$i]) ? $columnx["id"][$i] : 0;
                        if (!$c_id) {
                            $columns_lid++;
                            $c_id = $columns_lid;
                        }
                        $c_name = $columnx["name"][$i];
                        $columns[] = [
                            'id'   => $c_id,
                            'name' => $c_name,
                        ];
                    }
                }

                $optionsl["columns"] = $columns;
                $optionsl["columns_lid"] = $columns_lid;


                if (___("package/permalink", false, $lkey)) {
                    $route = !$route || $id == $route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->category_route_check($route, $lkey, "products");
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
                        'faq'             => $faq,
                        'options'         => $optionsl ? Utility::jencode($optionsl) : '',
                    ];
            }

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
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
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, 331, 100, [
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

            if ($icon_image) {
                Helper::Load(["Uploads"]);
                $ifolder = Config::get("pictures/category-icon/folder");
                $isizing = Config::get("pictures/category-icon/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($icon_image, [
                    'image-upload' => true,
                    'folder'       => $ifolder,
                    'width'        => $isizing["width"],
                    'height'       => $isizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='icon_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $ipicture = current($upload->operands);
                $ipicture = $ipicture["file_path"];
                $before_pic = $this->model->get_picture("category", $id, "icon");
                if ($before_pic) {
                    FileManager::file_delete($ifolder . $before_pic);
                    $this->model->delete_picture("category", $id, "icon");
                }
                $this->model->insert_picture("category", $id, "icon", $ipicture);
            }


            if (!$lang_data) die("Error! #1");

            $options = Utility::jencode([
                'color'         => $color ? "#" . $color : '',
                'list_template' => $list_template,
                'icon'          => $icon,
            ]);

            $p_data = [
                'status'  => $status,
                'parent'  => $parent,
                'rank'    => $rank,
                'options' => $options,
            ];

            $update = $this->model->set_category($id, $p_data);

            if (!$update)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error4"),
                ]));


            $lang_data_x = [];
            foreach ($lang_data as $_k => $data) {
                $lang_data_x[$_k] = $data;
                $data_id = $data["id"];
                unset($data["id"]);
                if ($data_id)
                    $this->model->set_category_lang($data_id, $data);
                else
                    $this->model->insert_category_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-product-category", [
                'name' => $category["title"],
                'id'   => $id,
            ]);

            $p_data["id"] = $id;
            Hook::run("ProductCategoryModified", ['data' => $p_data, 'languages' => $lang_data_x]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success3"),
                'redirect' => $this->AdminCRLink("products-2", ["special-" . $group["id"], "categories"]),
            ]);

        }


        private function add_new_special()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $param = $this->params[0];
            $exp = explode("-", $param);
            $id = (int)$exp[1];
            $group = $this->model->get_category($id);
            if (!$group) die("Not found group");

            $titles = Filter::POST("title");
            $featuress = Filter::POST("features");
            $bbutton_names = Filter::POST("buy_button_name");
            $status = Filter::init("POST/status", "letters");
            $category = Filter::init("POST/category", "numbers");
            if (!$category) $category = $group["id"];
            $rank = (int)Filter::init("POST/rank", "numbers");
            $olpu = Filter::init("POST/order_limit_per_user", "numbers");
            $stock = Filter::init("POST/stock", "numbers");

            if ($category == $group["id"]) {
                $opt = $group["options"] ? Utility::jdecode($group["options"], true) : [];
            } else {
                $cat = $this->model->get_category($category);
                if ($cat) {
                    $opt = $cat["options"] ? Utility::jdecode($cat["options"], true) : [];
                }
            }

            $invisibility = Filter::init("POST/invisibility", "numbers");
            if ($invisibility) $visibility = "invisible";
            else $visibility = "visible";
            $external_links = Filter::POST("external_link");
            $delivery_title_nms = Filter::POST("delivery-title-name");
            $delivery_title_dsc = Filter::POST("delivery-title-description");
            $popular = (bool)Filter::init("POST/popular", "numbers");
            $ctoc_s_t = (bool)(int)Filter::init("POST/ctoc-service-transfer", "numbers");
            $ctoc_s_t_l = Filter::init("POST/ctoc-service-transfer-limit", "numbers");
            $notes = Filter::init("POST/notes", "dtext");
            $addons = Filter::POST("addons");
            $requirements = Filter::POST("requirements");
            $prices = Filter::POST("prices");
            $override_usrcurrency = (int)Filter::init("POST/override_usrcurrency", "rnumbers");
            $taxexempt = (int)Filter::init("POST/taxexempt", "rnumbers");
            $download_file = Filter::FILES("download-file");
            $order_image = Filter::FILES("order_image");
            $module = Filter::init("POST/module", "letters_numbers", "_-");
            $module_data = Filter::POST("module_data");
            $auto_install = (int)Filter::init("POST/auto_install", "rnumbers");
            $show_domain = (int)Filter::init("POST/show_domain", "rnumbers");
            $subdomains = Filter::init("POST/subdomains", "hclear");
            $upgradeable_ps = Filter::init("POST/upgradeable-products");
            $affiliate_disable = (int)Filter::init("POST/affiliate_disable", "rnumbers");
            $affiliate_rate = Filter::init("POST/affiliate_rate", "amount");
            $affiliate_rate = str_replace(",", ".", $affiliate_rate);
            if ($affiliate_rate == '') $affiliate_rate = 0;
            $r_s_h = Filter::init("POST/renewal_selection_hide", "numbers");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $features = isset($featuress[$lkey]) ? $featuress[$lkey] : false;
                $bbuttonnm = isset($bbutton_names[$lkey]) ? $bbutton_names[$lkey] : false;
                $external_link = isset($external_links[$lkey]) ? $external_links[$lkey] : false;
                $delivery_title_name = isset($delivery_title_nms[$lkey]) ? $delivery_title_nms[$lkey] : false;
                $delivery_title_description = isset($delivery_title_dsc[$lkey]) ? $delivery_title_dsc[$lkey] : false;

                if ($features && is_array($features)) {
                    $features = Utility::jencode($features);
                }

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error25", ['{lang}' => $lkeyup]),
                    ]));

                $lopt = [
                    'buy_button_name'            => $bbuttonnm,
                    'external_link'              => $external_link,
                    'delivery_title_name'        => $delivery_title_name,
                    'delivery_title_description' => $delivery_title_description,
                ];

                $lang_data[$lkey] = [
                    'owner_id' => 0,
                    'lang'     => $lkey,
                    'title'    => $title,
                    'features' => $features,
                    'options'  => $lopt ? Utility::jencode($lopt) : '',
                ];
            }

            $options = [
                'popular'                => $popular,
                'auto_install'           => $auto_install,
                'show_domain'            => $show_domain,
                'renewal_selection_hide' => $r_s_h,
                'order_limit_per_user'   => $olpu,
            ];

            if ($ctoc_s_t)
                $options["ctoc-service-transfer"] = [
                    'status' => $ctoc_s_t,
                    'limit'  => $ctoc_s_t_l,
                ];

            if ($download_file) {
                Helper::Load(["Uploads"]);
                $folder = RESOURCE_DIR . DS . "uploads" . DS . "products" . DS;
                $upload = Helper::get("Uploads");
                $upload->init($download_file, [
                    'folder'    => $folder,
                    'file-name' => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "#download-file",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $dwfile = current($upload->operands);
                $dwfile = $dwfile["file_path"];
                $options["download_file"] = $dwfile;
            }


            if ($order_image) {
                Helper::Load(["Uploads", "Image"]);
                $pfolder = Config::get("pictures/products/folder");
                $osizing = Config::get("pictures/products/order/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($order_image, [
                    'image-upload' => true,
                    'folder'       => $pfolder,
                    'width'        => $osizing["width"],
                    'height'       => $osizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='order_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $orimgpicture = current($upload->operands);
                $orimgpicture = $orimgpicture["file_path"];
            }


            $product_data = [
                'type'                 => "special",
                'type_id'              => $group["id"],
                'ctime'                => DateManager::Now(),
                'status'               => $status,
                'category'             => $category,
                'rank'                 => $rank,
                'stock'                => $stock,
                'override_usrcurrency' => $override_usrcurrency,
                'taxexempt'            => $taxexempt,
                'visibility'           => $visibility,
                'options'              => Utility::jencode($options),
                'notes'                => $notes,
                'module'               => $module ? $module : "none",
                'module_data'          => $module_data ? Utility::jencode($module_data) : '',
                'subdomains'           => $subdomains,
            ];
            $product_data['addons'] = $addons ? implode(",", $addons) : '';
            $product_data['requirements'] = $requirements ? implode(",", $requirements) : '';
            $product_data['upgradeable_products'] = $upgradeable_ps ? implode(",", $upgradeable_ps) : '';
            $product_data['affiliate_disable'] = $affiliate_disable;
            $product_data['affiliate_rate'] = $affiliate_rate;


            $prices_data = [];
            if ($prices) {
                Helper::Load("Money");
                $size = sizeof($prices["period"]) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $time = isset($prices["time"][$i]) ? Filter::numbers($prices["time"][$i]) : 1;
                    if (!$time) $time = 1;
                    $period = isset($prices["period"][$i]) ? Filter::letters($prices["period"][$i]) : false;
                    $amount = isset($prices["amount"][$i]) ? $prices["amount"][$i] : 0;
                    $setup = isset($prices["setup"][$i]) ? $prices["setup"][$i] : 0;
                    $cid = isset($prices["cid"][$i]) ? Filter::numbers($prices["cid"][$i]) : 0;
                    if ($amount) $amount = Money::deformatter($amount, $cid);
                    else $amount = 0;

                    if ($setup) $setup = Money::deformatter($setup, $cid);
                    else $setup = 0;
                    $discount = isset($prices["discount"][$i]) ? $prices["discount"][$i] : 0;
                    $rank = $i;
                    if ($time && $period && $cid) {
                        $prices_data[] = [
                            'owner'    => "products",
                            'owner_id' => 0,
                            'type'     => "periodicals",
                            'period'   => $period,
                            'time'     => $time,
                            'amount'   => $amount,
                            'setup'    => $setup,
                            'cid'      => $cid,
                            'discount' => $discount,
                            'rank'     => $rank,
                        ];
                    }
                }
            }

            if (!$prices_data)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error11"),
                ]));

            $insert = $this->model->insert_product($product_data);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error10"),
                ]));

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $data["owner_id"] = $insert;
                    $this->model->insert_product_lang($data);
                }
            }

            if ($prices_data) {
                foreach ($prices_data as $data) {
                    $data["owner_id"] = $insert;
                    $this->model->insert_price($data);
                }
            }

            if (isset($orimgpicture) && $orimgpicture) $this->model->insert_picture("product", $insert, "order", $orimgpicture);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-product", [
                'type' => "special",
                'id'   => $insert,
                'name' => $lang_data[$locall]["title"],
            ]);

            Helper::Load("Products");
            Hook::run("ProductCreated", Products::get("special", $insert));


            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success24"),
                'redirect' => $this->AdminCRLink("products", ["special-" . $group["id"]]),
            ]);
        }


        private function edit_special()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $param = $this->params[0];
            $exp = explode("-", $param);
            $id = (int)$exp[1];
            $group = $this->model->get_category($id);
            if (!$group) die("Not found group");

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $product = $this->model->get_product($id);
            if (!$product) die();

            $titles = Filter::POST("title");
            $featuress = Filter::POST("features");
            $bbutton_names = Filter::POST("buy_button_name");
            $status = Filter::init("POST/status", "letters");
            $category = Filter::init("POST/category", "numbers");
            if (!$category) $category = $group["id"];
            $rank = (int)Filter::init("POST/rank", "numbers");
            $olpu = Filter::init("POST/order_limit_per_user", "numbers");
            $stock = Filter::init("POST/stock", "numbers");
            $invisibility = Filter::init("POST/invisibility", "numbers");
            if ($invisibility) $visibility = "invisible";
            else $visibility = "visible";
            $external_links = Filter::POST("external_link");
            $delivery_title_nms = Filter::POST("delivery-title-name");
            $delivery_title_dsc = Filter::POST("delivery-title-description");

            $popular = (bool)Filter::init("POST/popular", "numbers");
            $ctoc_s_t = (bool)(int)Filter::init("POST/ctoc-service-transfer", "numbers");
            $ctoc_s_t_l = Filter::init("POST/ctoc-service-transfer-limit", "numbers");
            $order_image = Filter::FILES("order_image");
            $notes = Filter::init("POST/notes", "dtext");
            $addons = Filter::POST("addons");
            $requirements = Filter::POST("requirements");
            $prices = Filter::POST("prices");
            $delete_prices = ltrim(Filter::init("POST/delete_prices", "hclear"), ",");
            $override_usrcurrency = (int)Filter::init("POST/override_usrcurrency", "rnumbers");
            $taxexempt = (int)Filter::init("POST/taxexempt", "rnumbers");
            $download_file = Filter::FILES("download-file");
            $module = Filter::init("POST/module", "letters_numbers", "_-");
            $module_data = Filter::POST("module_data");
            $auto_install = (int)Filter::init("POST/auto_install", "rnumbers");
            $show_domain = (int)Filter::init("POST/show_domain", "rnumbers");
            $subdomains = Filter::init("POST/subdomains", "hclear");
            $upgradeable_ps = Filter::init("POST/upgradeable-products");
            $affiliate_disable = (int)Filter::init("POST/affiliate_disable", "rnumbers");
            $affiliate_rate = Filter::init("POST/affiliate_rate", "amount");
            $affiliate_rate = str_replace(",", ".", $affiliate_rate);
            if ($affiliate_rate == '') $affiliate_rate = 0;
            $r_s_h = Filter::init("POST/renewal_selection_hide", "numbers");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $features = isset($featuress[$lkey]) ? $featuress[$lkey] : false;
                $bbuttonnm = isset($bbutton_names[$lkey]) ? $bbutton_names[$lkey] : false;
                $external_link = isset($external_links[$lkey]) ? $external_links[$lkey] : false;
                $delivery_title_name = isset($delivery_title_nms[$lkey]) ? $delivery_title_nms[$lkey] : false;
                $delivery_title_description = isset($delivery_title_dsc[$lkey]) ? $delivery_title_dsc[$lkey] : false;

                if ($features && is_array($features)) {
                    $features = Utility::jencode($features);
                }

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error9", ['{lang}' => $lkeyup]),
                    ]));

                $ldata = $this->model->get_product_wlang($id, $lkey);

                $lopt = [
                    'buy_button_name'            => $bbuttonnm,
                    'external_link'              => $external_link,
                    'delivery_title_name'        => $delivery_title_name,
                    'delivery_title_description' => $delivery_title_description,
                ];

                $lang_data[$lkey] = [
                    'id'       => $ldata ? $ldata["id"] : 0,
                    'owner_id' => $product["id"],
                    'lang'     => $lkey,
                    'title'    => $title,
                    'features' => $features,
                    'options'  => $lopt ? Utility::jencode($lopt) : '',
                ];
            }

            $options = Utility::jdecode($product["options"], true);
            $poptions = $options;


            $options['order_limit_per_user'] = $olpu;
            $options['renewal_selection_hide'] = $r_s_h;

            $options['popular'] = $popular;
            $options['auto_install'] = $auto_install;
            $options['show_domain'] = $show_domain;

            if (($ctoc_s_t && !isset($poptions["ctoc-service-transfer"])) || (isset($poptions["ctoc-service-transfer"]) && ($ctoc_s_t != $poptions["ctoc-service-transfer"]["status"] || $ctoc_s_t_l != $poptions["ctoc-service-transfer"]["limit"])))
                $options["ctoc-service-transfer"] = ['status' => $ctoc_s_t, 'limit' => $ctoc_s_t_l];


            if ($download_file) {
                Helper::Load(["Uploads"]);
                $folder = RESOURCE_DIR . DS . "uploads" . DS . "products" . DS;
                $upload = Helper::get("Uploads");
                $upload->init($download_file, [
                    'folder'    => $folder,
                    'file-name' => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "#download-file",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $dwfile = current($upload->operands);
                $dwfile = $dwfile["file_path"];
                $p_options = Utility::jdecode($product["options"], true);
                if (isset($p_options["download_file"]) && $p_options["download_file"])
                    FileManager::file_delete($folder . $p_options["download_file"]);
                $options["download_file"] = $dwfile;
            }

            if ($order_image) {
                Helper::Load(["Uploads", "Image"]);
                $pfolder = Config::get("pictures/products/folder");
                $osizing = Config::get("pictures/products/order/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($order_image, [
                    'image-upload' => true,
                    'folder'       => $pfolder,
                    'width'        => $osizing["width"],
                    'height'       => $osizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='order_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $orimgpicture = current($upload->operands);
                $orimgpicture = $orimgpicture["file_path"];
                Image::set($pfolder . $orimgpicture, $pfolder . "thumb" . DS, false, false, false, [
                    'folder-date-detect' => true,
                ]);
                $before_pic = $this->model->get_picture("product", $id, "order");
                if ($before_pic) {
                    FileManager::file_delete($pfolder . $before_pic);
                    FileManager::file_delete($pfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("product", $id, "order");
                }
                $this->model->insert_picture("product", $id, "order", $orimgpicture);
            }

            $product_data = [
                'status'               => $status,
                'category'             => $category,
                'rank'                 => $rank,
                'stock'                => $stock,
                'override_usrcurrency' => $override_usrcurrency,
                'taxexempt'            => $taxexempt,
                'visibility'           => $visibility,
                'options'              => Utility::jencode($options),
                'notes'                => $notes,
                'subdomains'           => $subdomains,
                'module'               => $module ? $module : "none",
            ];

            if ($module_data) $product_data['module_data'] = Utility::jencode($module_data);


            $product_data['addons'] = $addons ? implode(",", $addons) : '';
            $product_data['requirements'] = $requirements ? implode(",", $requirements) : '';
            $product_data['upgradeable_products'] = $upgradeable_ps ? implode(",", $upgradeable_ps) : '';

            $product_data['affiliate_disable'] = $affiliate_disable;
            $product_data['affiliate_rate'] = $affiliate_rate;


            $delete_prices = $delete_prices ? explode(",", $delete_prices) : [];
            foreach ($delete_prices as $del) $this->model->delete_price($del);
            $prices_data = [];
            if ($prices) {
                Helper::Load("Money");
                $size = sizeof($prices["period"]) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $pid = isset($prices["id"][$i]) ? Filter::numbers($prices["id"][$i]) : 0;
                    $time = isset($prices["time"][$i]) ? Filter::numbers($prices["time"][$i]) : 1;
                    if (!$time) $time = 1;
                    $period = isset($prices["period"][$i]) ? Filter::letters($prices["period"][$i]) : false;
                    $amount = isset($prices["amount"][$i]) ? $prices["amount"][$i] : 0;
                    $setup = isset($prices["setup"][$i]) ? $prices["setup"][$i] : 0;
                    $cid = isset($prices["cid"][$i]) ? Filter::numbers($prices["cid"][$i]) : 0;
                    if ($amount) $amount = Money::deformatter($amount, $cid);
                    else $amount = 0;

                    if ($setup) $setup = Money::deformatter($setup, $cid);
                    else $setup = 0;
                    $discount = isset($prices["discount"][$i]) ? $prices["discount"][$i] : 0;
                    $rank = $i;
                    if ($time && $period && $cid) {
                        $prices_data[] = [
                            'id'       => $pid,
                            'owner'    => "products",
                            'owner_id' => $id,
                            'type'     => "periodicals",
                            'period'   => $period,
                            'time'     => $time,
                            'amount'   => $amount,
                            'setup'    => $setup,
                            'cid'      => $cid,
                            'discount' => $discount,
                            'rank'     => $rank,
                        ];
                    }
                }
            }

            if (!$prices_data)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error11"),
                ]));

            $this->model->set_product($id, $product_data);


            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $lang_id = $data["id"];
                    unset($data["id"]);
                    if ($lang_id) $this->model->set_product_lang($lang_id, $data);
                    if (!$lang_id) $this->model->insert_product_lang($data);
                }
            }

            if ($prices_data) {
                foreach ($prices_data as $data) {
                    $data_id = $data["id"];
                    unset($data["id"]);
                    if ($data_id) $this->model->set_price($data_id, $data);
                    if (!$data_id) $this->model->insert_price($data);
                }
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-product", [
                'type' => "special",
                'id'   => $id,
                'name' => $product["title"],
            ]);

            Helper::Load("Products");
            Hook::run("ProductModified", Products::get("special", $id));


            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success25"),
                'redirect' => $this->AdminCRLink("products", ["special-" . $group["id"]]),
            ]);
        }


        private function delete_group()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = Filter::init("POST/id", "numbers");
            $group = $this->model->get_category($id);
            if (!$group) die("Not found group");

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

            $sub = $this->model->get_category_sub($id);
            $categories = array_merge([$id], $sub);

            foreach ($categories as $category) {
                $this->model->delete_category($category);
                $this->model->delete_product("special", 0, $category);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "delete", "deleted-product-group", [
                'name' => $group["title"],
                'id'   => $id,
            ]);

            self::$cache->clear();

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success26")]);
        }


        private function add_new_sms()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $titles = Filter::POST("title");
            $featuress = Filter::POST("features");
            $status = Filter::init("POST/status", "letters");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $olpu = Filter::init("POST/order_limit_per_user", "numbers");
            $amount = Filter::init("POST/amount", "amount");
            $cid = Filter::init("POST/cid", "rnumbers");
            $invisibility = Filter::init("POST/invisibility", "numbers");
            if ($invisibility) $visibility = "invisible";
            else $visibility = "visible";
            $external_links = Filter::POST("external_link");
            $popular = (bool)Filter::init("POST/popular", "numbers");
            $ctoc_s_t = (bool)(int)Filter::init("POST/ctoc-service-transfer", "numbers");
            $ctoc_s_t_l = Filter::init("POST/ctoc-service-transfer-limit", "numbers");
            $notes = Filter::init("POST/notes", "dtext");
            $override_usrcurrency = (int)Filter::init("POST/override_usrcurrency", "rnumbers");
            $taxexempt = (int)Filter::init("POST/taxexempt", "rnumbers");
            $module = Filter::init("POST/module", "route");
            $affiliate_disable = (int)Filter::init("POST/affiliate_disable", "rnumbers");
            $affiliate_rate = Filter::init("POST/affiliate_rate", "amount");
            $affiliate_rate = str_replace(",", ".", $affiliate_rate);
            if ($affiliate_rate == '') $affiliate_rate = 0;
            $r_s_h = Filter::init("POST/renewal_selection_hide", "numbers");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $features = isset($featuress[$lkey]) ? $featuress[$lkey] : false;
                $external_link = isset($external_links[$lkey]) ? $external_links[$lkey] : false;

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error9", ['{lang}' => $lkeyup]),
                    ]));

                $lang_data[$lkey] = [
                    'owner_id' => 0,
                    'lang'     => $lkey,
                    'title'    => $title,
                    'features' => $features,
                    'options'  => Utility::jencode([
                        'external_link' => $external_link,
                    ]),
                ];
            }

            $options = [
                'renewal_selection_hide' => $r_s_h,
                'order_limit_per_user'   => $olpu,
            ];

            if ($popular) $options["popular"] = $popular;

            if ($ctoc_s_t)
                $options["ctoc-service-transfer"] = [
                    'status' => $ctoc_s_t,
                    'limit'  => $ctoc_s_t_l,
                ];

            $product_data = [
                'type'                 => "sms",
                'ctime'                => DateManager::Now(),
                'status'               => $status,
                'rank'                 => $rank,
                'override_usrcurrency' => $override_usrcurrency,
                'taxexempt'            => $taxexempt,
                'visibility'           => $visibility,
                'options'              => $options ? Utility::jencode($options) : '',
                'module'               => $module,
                'notes'                => $notes,
            ];

            $product_data['affiliate_disable'] = $affiliate_disable;
            $product_data['affiliate_rate'] = $affiliate_rate;

            $insert = $this->model->insert_product($product_data);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error10"),
                ]));

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $data["owner_id"] = $insert;
                    $this->model->insert_product_lang($data);
                }
            }

            $this->model->insert_price([
                'owner'    => "products",
                'owner_id' => $insert,
                'type'     => "sale",
                'amount'   => $amount,
                'cid'      => $cid,
            ]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-product", [
                'type' => "sms",
                'id'   => $insert,
                'name' => $lang_data[$locall]["title"],
            ]);

            Helper::Load("Products");
            Hook::run("ProductCreated", Products::get("sms", $insert));


            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success27"),
                'redirect' => $this->AdminCRLink("products", ["sms"]),
            ]);

        }


        private function edit_sms()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $product = $this->model->get_product($id);
            if (!$product) die();


            $poptions = Utility::jdecode($product["options"], true);
            $titles = Filter::POST("title");
            $featuress = Filter::POST("features");
            $status = Filter::init("POST/status", "letters");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $olpu = Filter::init("POST/order_limit_per_user", "numbers");
            $amount = Filter::init("POST/amount", "amount");
            $cid = Filter::init("POST/cid", "rnumbers");
            $invisibility = Filter::init("POST/invisibility", "numbers");
            if ($invisibility) $visibility = "invisible";
            else $visibility = "visible";
            $external_links = Filter::POST("external_link");
            $popular = (bool)Filter::init("POST/popular", "numbers");
            $ctoc_s_t = (bool)(int)Filter::init("POST/ctoc-service-transfer", "numbers");
            $ctoc_s_t_l = Filter::init("POST/ctoc-service-transfer-limit", "numbers");
            $notes = Filter::init("POST/notes", "dtext");
            $override_usrcurrency = (int)Filter::init("POST/override_usrcurrency", "rnumbers");
            $taxexempt = (int)Filter::init("POST/taxexempt", "rnumbers");
            $module = Filter::init("POST/module", "route");

            $affiliate_disable = (int)Filter::init("POST/affiliate_disable", "rnumbers");
            $affiliate_rate = Filter::init("POST/affiliate_rate", "amount");
            $affiliate_rate = str_replace(",", ".", $affiliate_rate);
            if ($affiliate_rate == '') $affiliate_rate = 0;

            $r_s_h = Filter::init("POST/renewal_selection_hide", "numbers");

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $features = isset($featuress[$lkey]) ? $featuress[$lkey] : false;
                $external_link = isset($external_links[$lkey]) ? $external_links[$lkey] : false;

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error9", ['{lang}' => $lkeyup]),
                    ]));

                $ldata = $this->model->get_product_wlang($id, $lkey);

                $lang_data[$lkey] = [
                    'id'       => $ldata ? $ldata["id"] : 0,
                    'owner_id' => $product["id"],
                    'lang'     => $lkey,
                    'title'    => $title,
                    'features' => $features,
                    'options'  => Utility::jencode([
                        'external_link' => $external_link,
                    ]),
                ];
            }

            $options = [
                'renewal_selection_hide' => $r_s_h,
                'order_limit_per_user'   => $olpu,
            ];

            if ($popular) $options["popular"] = $popular;


            if (($ctoc_s_t && !isset($poptions["ctoc-service-transfer"])) || (isset($poptions["ctoc-service-transfer"]) && ($ctoc_s_t != $poptions["ctoc-service-transfer"]["status"] || $ctoc_s_t_l != $poptions["ctoc-service-transfer"]["limit"])))
                $options["ctoc-service-transfer"] = ['status' => $ctoc_s_t, 'limit' => $ctoc_s_t_l];

            $product_data = [
                'status'               => $status,
                'rank'                 => $rank,
                'override_usrcurrency' => $override_usrcurrency,
                'taxexempt'            => $taxexempt,
                'visibility'           => $visibility,
                'options'              => $options ? Utility::jencode($options) : '',
                'module'               => $module,
                'notes'                => $notes,
            ];

            $product_data['affiliate_disable'] = $affiliate_disable;
            $product_data['affiliate_rate'] = $affiliate_rate;

            $this->model->set_product($id, $product_data);


            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $lang_id = $data["id"];
                    unset($data["id"]);
                    if ($lang_id) $this->model->set_product_lang($lang_id, $data);
                    if (!$lang_id) $this->model->insert_product_lang($data);
                }
            }

            Helper::Load("Money");

            if ($amount) $amount = Money::deformatter($amount, $cid);
            $get_price = $this->model->get_price("sale", "products", $id);
            if ($get_price)
                $this->model->set_price($get_price["id"], [
                    'amount' => $amount,
                    'cid'    => $cid,
                ]);


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-product", [
                'type' => "sms",
                'id'   => $id,
                'name' => $product["title"],
            ]);

            Helper::Load("Products");
            Hook::run("ProductModified", Products::get("sms", $id));


            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success28"),
                'redirect' => $this->AdminCRLink("products", ["sms"]),
            ]);
        }


        private function update_tld_list()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            if (function_exists("ini_get")) {
                $limit = (int)ini_get('max_input_vars');
                if ($limit > 0) {
                    $count = $this->model->db->select("COUNT(id) AS count")->from('tldlist');
                    $count = $count->build() ? $count->getObject()->count : 0;
                    $count *= 16;
                    if ($count > $limit)
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => ___("needs/insufficient-max-input-vars"),
                        ]));
                }
            }

            $values = Filter::POST("values");
            $from = Filter::init("POST/from", "letters");


            Helper::Load("Money");


            if ($values && is_array($values)) {
                $size = sizeof($values["id"]) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $id = $values["id"][$i];
                    $name = isset($values["name"][$id]) ? Filter::html_clear($values["name"][$id]) : false;
                    $module = isset($values["module"][$id]) ? (string)Filter::route($values["module"][$id]) : "none";
                    $status = isset($values["status"][$id]) ? Filter::numbers($values["status"][$id]) : 0;
                    $regCost = isset($values["register-cost"][$id]) ? Filter::amount($values["register-cost"][$id]) : 0;
                    $renCost = isset($values["renewal-cost"][$id]) ? Filter::amount($values["renewal-cost"][$id]) : 0;
                    $traCost = isset($values["transfer-cost"][$id]) ? Filter::amount($values["transfer-cost"][$id]) : 0;
                    $currency = isset($values["currency"][$id]) ? Filter::numbers($values["currency"][$id]) : false;
                    $regPrice = isset($values["register-price"][$id]) ? Filter::amount($values["register-price"][$id]) : 0;
                    $renPrice = isset($values["renewal-price"][$id]) ? Filter::amount($values["renewal-price"][$id]) : 0;
                    $traPrice = isset($values["transfer-price"][$id]) ? Filter::amount($values["transfer-price"][$id]) : 0;
                    $promo_status = isset($values["promo-status"][$id]) ? $values["promo-status"][$id] : '0';
                    $promo_regPrice = isset($values["promo-register-price"][$id]) ? Filter::amount($values["promo-register-price"][$id]) : 0;
                    $promo_traPrice = isset($values["promo-transfer-price"][$id]) ? Filter::amount($values["promo-transfer-price"][$id]) : 0;
                    $promo_duedate = isset($values["promo-duedate"][$id]) ? $values["promo-duedate"][$id] : '';
                    if (!$promo_duedate) $promo_duedate = substr(DateManager::ata(), 0, 10);

                    if (substr($name, 0, 1) == ".") $name = substr($name, 1);
                    $name = Utility::strtolower($name);

                    $aff_disable = (int)isset($values["affiliate-disable"][$id]) ? $values["affiliate-disable"][$id] : 0;
                    $aff_rate = isset($values["affiliate-rate"][$id]) ? $values["affiliate-rate"][$id] : 0;
                    $aff_rate = str_replace(",", ".", $aff_rate);
                    if ($aff_rate == '') $aff_rate = 0;


                    if ($status) $status = "active";
                    else $status = "inactive";

                    if (!Validation::isEmpty($name)) {
                        if ($id) {
                            $reg_price = $this->model->get_price("register", "tld", $id);
                            $ren_price = $this->model->get_price("renewal", "tld", $id);
                            $tra_price = $this->model->get_price("transfer", "tld", $id);

                            $updt = $this->model->set_tld($id, [
                                'name'                 => $name,
                                'status'               => $status,
                                'module'               => $module,
                                'register_cost'        => Money::deformatter($regCost, $currency),
                                'renewal_cost'         => Money::deformatter($renCost, $currency),
                                'transfer_cost'        => Money::deformatter($traCost, $currency),
                                'promo_status'         => (int)$promo_status,
                                'promo_register_price' => Money::deformatter($promo_regPrice, $currency),
                                'promo_transfer_price' => Money::deformatter($promo_traPrice, $currency),
                                'promo_duedate'        => $promo_duedate,
                                'currency'             => $currency,
                                'affiliate_disable'    => $aff_disable,
                                'affiliate_rate'       => $aff_rate,
                            ]);

                            if ($reg_price)
                                $this->model->set_price($reg_price["id"], [
                                    'amount' => Money::deformatter($regPrice, $currency),
                                    'cid'    => $currency,
                                ]);
                            else
                                $this->model->insert_price([
                                    'type'     => "register",
                                    'owner'    => "tld",
                                    'owner_id' => $id,
                                    'amount'   => Money::deformatter($regPrice, $currency),
                                    'setup'    => 0,
                                    'cid'      => $currency,
                                ]);

                            if ($ren_price)
                                $this->model->set_price($ren_price["id"], [
                                    'amount' => Money::deformatter($renPrice, $currency),
                                    'setup'  => 0,
                                    'cid'    => $currency,
                                ]);
                            else
                                $this->model->insert_price([
                                    'type'     => "renewal",
                                    'owner'    => "tld",
                                    'owner_id' => $id,
                                    'amount'   => Money::deformatter($renPrice, $currency),
                                    'setup'    => 0,
                                    'cid'      => $currency,
                                ]);

                            if ($tra_price)
                                $this->model->set_price($tra_price["id"], [
                                    'amount' => Money::deformatter($traPrice, $currency),
                                    'setup'  => 0,
                                    'cid'    => $currency,
                                ]);
                            else
                                $this->model->insert_price([
                                    'type'     => "transfer",
                                    'owner'    => "tld",
                                    'owner_id' => $id,
                                    'amount'   => Money::deformatter($traPrice, $currency),
                                    'setup'    => 0,
                                    'cid'      => $currency,
                                ]);

                            Hook::run("ProductModified", Products::get("domain", $id));
                        }
                    }
                }
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-domain-list");

            self::$cache->clear();

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/products/success29"),
            ]);
        }

        private function update_tld_adjustments()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $values = Filter::POST("values");

            if ($values && is_array($values)) {
                $size = sizeof($values["id"]) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $id = $values["id"][$i];
                    $min_years = isset($values["min_years"][$id]) ? (int)Filter::numbers($values["min_years"][$id]) : 1;
                    $max_years = isset($values["max_years"][$id]) ? (int)Filter::numbers($values["max_years"][$id]) : 10;
                    $currency = isset($values["currency"][$id]) ? (int)Filter::numbers($values["currency"][$id]) : 0;
                    $dns_manage = isset($values["dns-manage"][$id]) ? (int)Filter::numbers($values["dns-manage"][$id]) : 0;
                    $w_privacy = isset($values["whois-privacy"][$id]) ? (int)Filter::numbers($values["whois-privacy"][$id]) : 0;
                    $epp_code = isset($values["epp-code"][$id]) ? (int)Filter::numbers($values["epp-code"][$id]) : 0;
                    $paperwork = isset($values["paperwork"][$id]) ? (int)Filter::numbers($values["paperwork"][$id]) : 0;

                    $rank = (string)$i;

                    if ($id) {
                        $this->model->set_tld($id, [
                            'min_years'     => $min_years,
                            'max_years'     => $max_years,
                            'currency'      => $currency,
                            'dns_manage'    => $dns_manage,
                            'whois_privacy' => $w_privacy,
                            'epp_code'      => $epp_code,
                            'paperwork'     => $paperwork,
                            'rank'          => $rank,
                        ]);

                        $reg_price = Products::get_price('register', 'tld', $id);
                        $ren_price = Products::get_price('renewal', 'tld', $id);
                        $tra_price = Products::get_price('transfer', 'tld', $id);

                        if ($reg_price) Products::set_price($reg_price['id'], ['cid' => $currency]);
                        if ($ren_price) Products::set_price($ren_price['id'], ['cid' => $currency]);
                        if ($tra_price) Products::set_price($tra_price['id'], ['cid' => $currency]);

                        Hook::run("ProductModified", Products::get("domain", $id));
                    }

                }
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-domain-list");

            self::$cache->clear();

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/products/success29"),
            ]);
        }

        private function add_new_tld()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Money", "Products"]);

            $name = Filter::init("POST/name", "hclear");
            $status = (int)Filter::init("POST/status", "numbers");
            $dns_manage = (int)Filter::init("POST/dns_manage", "numbers");
            $w_privacy = (int)Filter::init("POST/whois_privacy", "numbers");
            $epp_code = (int)Filter::init("POST/epp_code", "numbers");
            $paperwork = (int)Filter::init("POST/paperwork", "numbers");
            $module = Filter::init("POST/module", "route");
            $regCost = Filter::init("POST/register_cost", "amount");
            $renCost = Filter::init("POST/renewal_cost", "amount");
            $traCost = Filter::init("POST/transfer_cost", "amount");
            $currency = (int)Filter::init("POST/currency", "numbers");
            $regPrice = Filter::init("POST/register_price", "amount");
            $renPrice = Filter::init("POST/renewal_price", "amount");
            $traPrice = Filter::init("POST/transfer_price", "amount");
            $promo_status = (string)(int)Filter::init("POST/promo_status", "numbers");
            $promo_regPrice = Filter::init("POST/promo_register_price", "amount");
            $promo_traPrice = Filter::init("POST/promo_transfer_price", "amount");
            $promo_duedate = Filter::init("POST/promo_duedate", "hclear");
            if (!$promo_duedate) $promo_duedate = substr(DateManager::ata(), 0, 10);
            if ($status) $status = "active";
            else $status = "inactive";
            $affiliate_disable = (int)Filter::init("POST/affiliate_disable", "rnumbers");
            $affiliate_rate = Filter::init("POST/affiliate_rate", "amount");
            $affiliate_rate = str_replace(",", ".", $affiliate_rate);
            if ($affiliate_rate == '') $affiliate_rate = 0;

            if (substr($name, 0, 1) == ".") $name = substr($name, 1);
            $name = Utility::strtolower($name);

            if (!$module) $module = 'none';

            if (Validation::isEmpty($name))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error35"),
                ]));

            $previously_check = $this->model->db->select("id")->from("tldlist")->where("name", "=", $name);
            if ($previously_check->build())
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error38"),
                ]));

            $last_rank = $this->model->db->select("rank")->from("tldlist")->order_by("rank DESC")->limit(1);
            $last_rank = $last_rank->build() ? $last_rank->getObject()->rank : 0;


            $insert = $this->model->insert_domain([
                'cdate'                => DateManager::Now(),
                'status'               => $status,
                'name'                 => $name,
                'rank'                 => $last_rank + 1,
                'dns_manage'           => $dns_manage,
                'whois_privacy'        => $w_privacy,
                'epp_code'             => $epp_code,
                'paperwork'            => $paperwork,
                'module'               => $module,
                'register_cost'        => Money::deformatter($regCost, $currency),
                'renewal_cost'         => Money::deformatter($renCost, $currency),
                'transfer_cost'        => Money::deformatter($traCost, $currency),
                'promo_status'         => $promo_status,
                'promo_register_price' => Money::deformatter($promo_regPrice, $currency),
                'promo_transfer_price' => Money::deformatter($promo_traPrice, $currency),
                'promo_duedate'        => $promo_duedate,
                'currency'             => $currency,
                'affiliate_disable'    => $affiliate_disable,
                'affiliate_rate'       => $affiliate_rate,
            ]);

            $this->model->insert_price([
                'type'     => "register",
                'owner'    => "tld",
                'owner_id' => $insert,
                'amount'   => Money::deformatter($regPrice, $currency),
                'cid'      => $currency,
            ]);

            $this->model->insert_price([
                'type'     => "renewal",
                'owner'    => "tld",
                'owner_id' => $insert,
                'amount'   => Money::deformatter($renPrice, $currency),
                'cid'      => $currency,
            ]);

            $this->model->insert_price([
                'type'     => "transfer",
                'owner'    => "tld",
                'owner_id' => $insert,
                'amount'   => Money::deformatter($traPrice, $currency),
                'cid'      => $currency,
            ]);

            Hook::run("ProductCreated", Products::get("domain", $insert));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-domain-list");

            self::$cache->clear();

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/products/success42"),
            ]);

        }


        private function deleteSelected_tlds()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $adata = UserManager::LoginData("admin");

            $ids = Filter::POST("ids");
            if (!$ids || !is_array($ids)) return false;

            if ($ids) {
                foreach ($ids as $id) {
                    $id = (int)Filter::numbers($id);
                    $product_data = Products::get("domain", $id);
                    $del = $this->model->delete_domain($id);
                    if ($del) {
                        User::addAction($adata["id"], "deleted", "deleted-domain", [
                            'id' => $id,
                        ]);
                        Hook::run("ProductDeleted", $product_data);
                    }
                }
            }


            self::$cache->clear();

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/products/success38"),
            ]);

        }


        private function update_domain_settings()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Products"]);

            $profit_rate = Filter::init("POST/profit_rate", "amount");
            $titles = Filter::POST("title");
            $sub_titles = Filter::POST("sub_title");
            $slogans = Filter::POST("slogan");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $faqs = Filter::POST("faq");
            $affiliate_disable = (bool)(int)Filter::init("POST/affiliate_disable", "numbers");

            $ctoc_s_t = (bool)(int)Filter::init("POST/ctoc-service-transfer", "numbers");
            $ctoc_s_t_l = Filter::init("POST/ctoc-service-transfer-limit", "numbers");

            $ns1 = Filter::init("POST/ns1", "hclear");
            $ns2 = Filter::init("POST/ns2", "hclear");
            $ns3 = Filter::init("POST/ns3", "hclear");
            $ns4 = Filter::init("POST/ns4", "hclear");

            $home_spotlights = Filter::POST("home-spotlight");
            $page_spotlights = Filter::POST("page-spotlight");
            $whois_privacy_amount = (float)Filter::init("POST/wprivacy_amount", "amount");
            $whois_privacy_cid = (int)Filter::init("POST/wprivacy_cid", "numbers");
            $override_usrcurrency = (int)Filter::init("POST/override_usrcurrency", "rnumbers");
            $hide_hosting = (int)Filter::init("POST/hide-hosting", "rnumbers");
            $home_widget = (int)Filter::init("POST/domain-dashboard-widget", "rnumbers");
            $post = Filter::POST();

            $icon_image = Filter::FILES("icon_image");
            $icon = Filter::init("POST/icon", "hclear");


            $options_sets = [];

            if (isset($post["profit_rate"])) {

                $profit_rate = str_replace(",", ".", $profit_rate);
                if (stristr($profit_rate, "."))
                    $profit_rate = (float)$profit_rate;
                else
                    $profit_rate = (int)$profit_rate;

                if ($profit_rate != Config::get("options/domain-profit-rate"))
                    $options_sets["domain-profit-rate"] = $profit_rate;

                if ($options_sets) {
                    $sets = Config::set("options", $options_sets);
                    $export = Utility::array_export($sets, ['pwith' => true]);
                    FileManager::file_write(CONFIG_DIR . "options.php", $export);
                }

                /*
                if(!Products::auto_define_domain_prices($profit_rate) && Products::$error)
                    die(Utility::jencode([
                        'status' => "error",
                        'message' => Products::$error,
                    ]));
                */
                Helper::Load(["Products", "Money"]);

                $list = $this->model->db->select("id,register_cost,renewal_cost,transfer_cost,currency")->from("tldlist");
                if ($list->build()) {
                    foreach ($list->fetch_assoc() as $row) {
                        $pid = $row["id"];

                        $reg_price = Products::get_price("register", "tld", $pid);
                        $ren_price = Products::get_price("renewal", "tld", $pid);
                        $tra_price = Products::get_price("transfer", "tld", $pid);

                        $cost_cid = $row["currency"];
                        $tld_cid = $cost_cid;

                        $register_cost = $row["register_cost"];
                        $renewal_cost = $row["renewal_cost"];
                        $transfer_cost = $row["transfer_cost"];


                        $reg_profit = Money::get_discount_amount($register_cost, $profit_rate);
                        $ren_profit = Money::get_discount_amount($renewal_cost, $profit_rate);
                        $tra_profit = Money::get_discount_amount($transfer_cost, $profit_rate);

                        $register_sale = round(($register_cost + $reg_profit), 4);
                        $renewal_sale = round(($renewal_cost + $ren_profit), 4);
                        $transfer_sale = round(($transfer_cost + $tra_profit), 4);


                        Products::set_price($reg_price["id"], [
                            'amount' => $register_sale,
                            'cid'    => $tld_cid,
                        ]);

                        Products::set_price($tra_price["id"], [
                            'amount' => $transfer_sale,
                            'cid'    => $tld_cid,
                        ]);

                        Products::set_price($ren_price["id"], [
                            'amount' => $renewal_sale,
                            'cid'    => $tld_cid,
                        ]);

                    }
                }


                self::$cache->clear();


                die(Utility::jencode([
                    'status'  => "successful",
                    'message' => __("admin/products/success41"),
                ]));
            }

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $sub_title = isset($sub_titles[$lkey]) ? Filter::html_clear($sub_titles[$lkey]) : false;
                $slogan = isset($slogans[$lkey]) ? $slogans[$lkey] : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $faqx = isset($faqs[$lkey]) ? $faqs[$lkey] : false;
                $home_spotlight = isset($home_spotlights[$lkey]) ? $home_spotlights[$lkey] : false;
                $page_spotlight = isset($page_spotlights[$lkey]) ? $page_spotlights[$lkey] : false;
                $faq = $faqx ? [] : null;
                if ($faqx) {
                    $size = sizeof($faqx["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_title = $faqx["title"][$i];
                        $f_desc = $faqx["description"][$i];
                        if ($f_title)
                            $faq[] = [
                                'title'       => $f_title,
                                'description' => $f_desc,
                            ];

                    }
                }

                $data_p = [];
                $data_p2 = $data_p;
                $data_m['meta'] = [];

                $blocks_sets = [];
                $blocks_sets2 = [];


                $blocks_sets["home-domain-check"]["items"] = null;
                $blocks_sets2["home-domain-check"]["items"] = $home_spotlight;


                $data_p["spotlight-tlds"] = null;
                $data_p2["spotlight-tlds"] = $page_spotlight;

                $data_p["header-title"] = $title;
                $data_p["header-description"] = $sub_title;
                $data_p["slogan"]["content"] = $slogan;
                $data_p["faq"] = null;
                $data_p2["faq"] = $faq;
                $data_m['meta']["title"] = $seo_title;
                $data_m['meta']["keywords"] = $seo_keywords;
                $data_m['meta']["description"] = $seo_description;

                $data = Bootstrap::$lang->get_cm("website/domain", false, $lkey);
                $data = array_replace_recursive($data, $data_p);
                $data = array_replace_recursive($data, $data_p2);
                $data = array_replace_recursive($data, $data_m);
                $data_export = Utility::array_export($data, ['pwith' => true]);
                FileManager::file_write(LANG_DIR . $lkey . DS . "cm" . DS . "website" . DS . "domain.php", $data_export);

                $data = Bootstrap::$lang->get("blocks", $lkey);
                $data = array_replace_recursive($data, $blocks_sets);
                $data = array_replace_recursive($data, $blocks_sets2);
                $data_export = Utility::array_export($data, ['pwith' => true]);
                FileManager::file_write(LANG_DIR . $lkey . DS . "blocks.php", $data_export);

                FileManager::file_write(LANG_DIR . $lkey . DS . "domain-content.html", $content);

            }

            if ($ctoc_s_t != Config::get("options/ctoc-service-transfer/domain/status"))
                $options_sets["ctoc-service-transfer"]["domain"]["status"] = $ctoc_s_t;

            if ($ctoc_s_t_l != Config::get("options/ctoc-service-transfer/domain/limit"))
                $options_sets["ctoc-service-transfer"]["domain"]["limit"] = $ctoc_s_t_l;


            $options_sets["domain-whois-privacy"]["amount"] = $whois_privacy_amount;
            $options_sets["domain-whois-privacy"]["cid"] = $whois_privacy_cid;
            $options_sets["domain-override-user-currency"] = $override_usrcurrency;
            $options_sets["domain-hide-hosting"] = $hide_hosting;
            $options_sets["domain-dashboard-widget"] = $home_widget;
            $options_sets["disable-affiliate-domain"] = $affiliate_disable;

            if ($ns1 != Config::get("options/ns-addresses/ns1"))
                $options_sets["ns-addresses"]["ns1"] = $ns1;

            if ($ns2 != Config::get("options/ns-addresses/ns2"))
                $options_sets["ns-addresses"]["ns2"] = $ns2;

            if ($ns3 != Config::get("options/ns-addresses/ns3"))
                $options_sets["ns-addresses"]["ns3"] = $ns3;

            if ($ns4 != Config::get("options/ns-addresses/ns4"))
                $options_sets["ns-addresses"]["ns4"] = $ns4;

            if ($icon != Config::get("options/category-icon/domain"))
                $options_sets["category-icon"]["domain"] = $icon;


            if ($icon_image) {
                Helper::Load(["Uploads"]);
                $ifolder = Config::get("pictures/category-icon/folder");
                $isizing = Config::get("pictures/category-icon/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($icon_image, [
                    'image-upload' => true,
                    'folder'       => $ifolder,
                    'width'        => $isizing["width"],
                    'height'       => $isizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='icon_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $ipicture = current($upload->operands);
                $ipicture = $ipicture["file_path"];
                $before_pic = $this->model->get_picture("category", 3, "icon");
                if ($before_pic) {
                    FileManager::file_delete($ifolder . $before_pic);
                    $this->model->delete_picture("category", 3, "icon");
                }
                $this->model->insert_picture("category", 3, "icon", $ipicture);
            }


            $sets = Config::set("options", $options_sets);
            $export = Utility::array_export($sets, ['pwith' => true]);
            FileManager::file_write(CONFIG_DIR . "options.php", $export);


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-product-group", [
                'id' => "domain",
            ]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success21"),
                'redirect' => $this->AdminCRLink("products", ["domain"]),
            ]);
        }


        private function update_international_sms_automation_settings()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $auto_update = (bool)(int)Filter::init("POST/auto_update", "numbers");
            $primary_currency = (int)Filter::init("POST/primary-currency", "numbers");
            $profit_rate = (int)Filter::init("POST/profit_rate", "numbers");
            $post = Filter::POST();

            $sms_sets = [];
            $cron_sets = Config::get("cronjobs");

            if (isset($post["profit_rate"])) {
                if ($profit_rate != Config::get("sms/profit-rate"))
                    $sms_sets["profit-rate"] = $profit_rate;
            } else {
                if ($primary_change = $primary_currency != Config::get("sms/primary-currency"))
                    $sms_sets["primary-currency"] = $primary_currency;
                $cron_sets["tasks"]["auto-intl-sms-prices"]["status"] = $auto_update;
            }

            if ($sms_sets) {
                $sms_sets = Config::set("sms", $sms_sets);
                $export = Utility::array_export($sms_sets, ['pwith' => true]);
                FileManager::file_write(CONFIG_DIR . "sms.php", $export);
            }

            if ($cron_sets) {
                $cron_sets = Config::set("cronjobs", $cron_sets);
                $export = Utility::array_export($cron_sets, ['pwith' => true]);
                FileManager::file_write(CONFIG_DIR . "cronjobs.php", $export);
            }

            if ($primary_change) $this->update_international_sms_costs(false);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-intl-sms-automation-settings");

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success30"),
                'redirect' => $primary_change ? $this->AdminCRLink("products", ["international-sms"]) : '',
            ]);

        }


        private function update_international_sms()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $values = Filter::POST("values");

            $sms = Config::get("sms");
            $sms_sets = [];

            Helper::Load("Money");

            foreach ($values as $cc => $value) {
                $cid = (int)$value["cid"];
                $cost = str_replace(",", ".", $value["cost"]);
                $amount = str_replace(",", ".", $value["amount"]);
                $status = (bool)(int)$value["status"];

                $sms_sets["country-prices"][$cc] = [
                    'status' => $status,
                    'cost'   => $cost,
                    'amount' => $amount,
                    'cid'    => $cid,
                ];

            }

            if ($sms_sets) {
                $sms_sets = Config::set("sms", $sms_sets);
                $export = Utility::array_export($sms_sets, ['pwith' => true]);
                FileManager::file_write(CONFIG_DIR . "sms.php", $export);
            }

            self::$cache->clear("currencies");

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-intl-sms-country-prices");

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success32")]);
        }


        private function update_international_sms_costs($print = true)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $sms = Config::get("sms");
            $sms_sets = [];

            Helper::Load("Money");

            $mname = Config::get("modules/sms-intl");
            if ($mname == '' || $mname == 'none')
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error26"),
                ]));

            $module = Modules::Load("SMS", $mname);
            if (!class_exists($mname))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error26"),
                ]));

            $currency = 0;

            if (!isset($module["config"]["supported-currencies"])) die();
            $currs = $module["config"]["supported-currencies"];
            foreach ($currs as $curr) {
                $getCurr = Money::Currency($curr);
                if ($getCurr) {
                    if ($getCurr["status"] == "active") {
                        if (!$currency) $currency = $getCurr["id"];
                    }
                }
            }

            $primaryC = Config::get("sms/primary-currency");

            if (!$currency)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error27", ['{currencies}' => implode(", ", $currs)]),
                ]));

            $getCurr = Money::Currency($currency);

            $module = new $mname();
            if (!method_exists($module, "get_prices")) die();

            $prices = $module->get_prices();
            if (!$prices)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error28", ['{error}' => $module->getError()]),
                ]));

            $profit_rate = $sms["profit-rate"];
            foreach ($prices as $cc => $price) {
                $val = $price[$getCurr["code"]];
                $calc = $val + Money::get_tax_amount($val, $profit_rate);
                $cid = $getCurr["id"];
                if ($primaryC) {
                    $cid = $primaryC;
                    $val = Money::exChange($val, $getCurr["id"], $primaryC);
                    $calc = Money::exChange($calc, $getCurr["id"], $primaryC);
                }
                $sms_sets["country-prices"][$cc]["cid"] = $cid;
                $sms_sets["country-prices"][$cc]["cost"] = $val;
                $sms_sets["country-prices"][$cc]["amount"] = $calc;
            }

            if ($sms_sets) {
                $sms_sets = Config::set("sms", $sms_sets);
                $export = Utility::array_export($sms_sets, ['pwith' => true]);
                FileManager::file_write(CONFIG_DIR . "sms.php", $export);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-intl-sms-country-prices");

            if ($print)
                echo Utility::jencode([
                    'status'   => "successful",
                    'message'  => __("admin/products/success32"),
                    'redirect' => $this->AdminCRLink("products", ["international-sms"]),
                ]);
        }


        private function update_international_sms_settings()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $titles = Filter::POST("title");
            $sub_titles = Filter::POST("sub_title");
            $slogans = Filter::POST("slogan");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $faqs = Filter::POST("faq");

            $icon_image = Filter::FILES("icon_image");
            $icon = Filter::init("POST/icon", "hclear");

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $sub_title = isset($sub_titles[$lkey]) ? Filter::html_clear($sub_titles[$lkey]) : false;
                $slogan = isset($slogans[$lkey]) ? $slogans[$lkey] : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $faqx = isset($faqs[$lkey]) ? $faqs[$lkey] : false;
                $faq = $faqx ? [] : null;
                if ($faqx) {
                    $size = sizeof($faqx["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_title = $faqx["title"][$i];
                        $f_desc = $faqx["description"][$i];
                        if ($f_title)
                            $faq[] = [
                                'title'       => $f_title,
                                'description' => $f_desc,
                            ];

                    }
                }

                $data_p = [];
                $data_p2 = $data_p;
                $data_m['meta-introduction'] = [];
                $data_p["introduction-header-title"] = $title;
                $data_p["introduction-header-description"] = $sub_title;
                $data_p["introduction-slogan"] = $slogan;
                $data_p["introduction-content"] = $content;
                $data_p["introduction-faq"] = null;
                $data_p2["introduction-faq"] = $faq;
                $data_m['meta-introduction']["title"] = $seo_title;
                $data_m['meta-introduction']["keywords"] = $seo_keywords;
                $data_m['meta-introduction']["description"] = $seo_description;

                $data = Bootstrap::$lang->get_cm("website/account_sms", false, $lkey);
                $data = array_replace_recursive($data, $data_p);
                $data = array_replace_recursive($data, $data_p2);
                $data = array_replace_recursive($data, $data_m);
                $data_export = Utility::array_export($data, ['pwith' => true]);

                FileManager::file_write(LANG_DIR . $lkey . DS . "cm" . DS . "website" . DS . "account_sms.php", $data_export);
            }

            $config_sets = [];

            if ($icon != Config::get("options/category-icon/intl-sms"))
                $config_sets["options"]["category-icon"]["intl-sms"] = $icon;


            if ($icon_image) {
                Helper::Load(["Uploads"]);
                $ifolder = Config::get("pictures/category-icon/folder");
                $isizing = Config::get("pictures/category-icon/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($icon_image, [
                    'image-upload' => true,
                    'folder'       => $ifolder,
                    'width'        => $isizing["width"],
                    'height'       => $isizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='icon_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $ipicture = current($upload->operands);
                $ipicture = $ipicture["file_path"];
                $before_pic = $this->model->get_picture("category", 7, "icon");
                if ($before_pic) {
                    FileManager::file_delete($ifolder . $before_pic);
                    $this->model->delete_picture("category", 7, "icon");
                }
                $this->model->insert_picture("category", 7, "icon", $ipicture);
            }


            if ($config_sets) {
                if (isset($config_sets["options"])) {
                    $options_result = Config::set("options", $config_sets["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                }
            }


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-product-group", [
                'id' => "international-sms",
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success21"),
                'redirect' => $this->AdminCRLink("products", ["international-sms"]),
            ]);
        }


        private function add_new_software_category()
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
                $check = $this->model->category_route_check($slug, $lang, "software");
                if ($check)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error23", ['{lang}' => strtoupper($lang)]),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $sub_titles = Filter::POST("sub_title");
            $hbackground = Filter::FILES("hbackground");
            $icon_image = Filter::FILES("icon_image");
            $icon = Filter::init("POST/icon", "hclear");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $hcontents = Filter::POST("html-content");
            $faqs = Filter::POST("faq");
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
                $hcontent = isset($hcontents[$lkey]) ? $hcontents[$lkey] : false;
                $faqx = isset($faqs[$lkey]) ? $faqs[$lkey] : false;
                $faq = $faqx ? [] : null;
                if ($faqx) {
                    $size = sizeof($faqx["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_title = $faqx["title"][$i];
                        $f_desc = $faqx["description"][$i];
                        if ($f_title)
                            $faq[] = [
                                'title'       => $f_title,
                                'description' => $f_desc,
                            ];

                    }
                }
                if ($faq) $faq = Utility::jencode($faq);
                else $faq = '';

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error1", ['{lang}' => strtoupper($lkey)]),
                    ]));


                if (___("package/permalink", false, $lkey)) {
                    $route = !$route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->category_route_check($route, $lkey, "software");
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
                if ($hcontent) $lopt["html-content"] = $hcontent;

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
                        'faq'             => $faq,
                        'options'         => $lopt ? Utility::jencode($lopt) : '',
                    ];

            }

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
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
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, 331, 100, [
                    'folder-date-detect' => true,
                ]);
            }


            if ($icon_image) {
                Helper::Load(["Uploads"]);
                $ifolder = Config::get("pictures/category-icon/folder");
                $isizing = Config::get("pictures/category-icon/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($icon_image, [
                    'image-upload' => true,
                    'folder'       => $ifolder,
                    'width'        => $isizing["width"],
                    'height'       => $isizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='icon_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $ipicture = current($upload->operands);
                $ipicture = $ipicture["file_path"];
            }

            if (!$lang_data) die("Error! #1");

            $options = Utility::jencode([
                'icon' => $icon,
            ]);

            $p_data = [
                'status'  => $status,
                'parent'  => $parent,
                'rank'    => $rank,
                'type'    => "software",
                'kind'    => "",
                'options' => $options,
                'ctime'   => DateManager::Now(),
            ];

            $insert = $this->model->insert_category($p_data);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error3"),
                ]));

            if (isset($hpicture) && $hpicture) $this->model->insert_picture("category", $insert, "header-background", $hpicture);
            if (isset($ipicture) && $ipicture) $this->model->insert_picture("category", $insert, "icon", $ipicture);
            foreach ($lang_data as $key => $data) {
                $data["owner_id"] = $insert;
                if (!$data["route"]) $data["route"] = $insert;
                $lang_data[$key] = $data;
                $this->model->insert_category_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-product-category", [
                'name' => $lang_data[$locall]["title"],
                'id'   => $insert,
            ]);

            $p_data["id"] = $insert;
            Hook::run("ProductCategoryCreated", ['data' => $p_data, 'languages' => $lang_data]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success2"),
                'redirect' => $this->AdminCRLink("products-2", ["software", "categories"]),
            ]);
        }


        private function edit_software_category()
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
                $check = $this->model->category_route_check($slug, $lang, "software");
                if ($check && $check != $category["id"])
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error23", ['{lang}' => strtoupper($lang)]),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $sub_titles = Filter::POST("sub_title");
            $hbackground = Filter::FILES("hbackground");
            $icon_image = Filter::FILES("icon_image");
            $icon = Filter::init("POST/icon", "hclear");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $hcontents = Filter::POST("html-content");
            $faqs = Filter::POST("faq");
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
                $hcontent = isset($hcontents[$lkey]) ? $hcontents[$lkey] : false;
                $faqx = isset($faqs[$lkey]) ? $faqs[$lkey] : false;
                $faq = $faqx ? [] : null;
                if ($faqx) {
                    $size = sizeof($faqx["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_title = $faqx["title"][$i];
                        $f_desc = $faqx["description"][$i];
                        if ($f_title)
                            $faq[] = [
                                'title'       => $f_title,
                                'description' => $f_desc,
                            ];

                    }
                }
                if ($faq) $faq = Utility::jencode($faq);
                else $faq = '';

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error1", ['{lang}' => strtoupper($lkey)]),
                    ]));

                if (___("package/permalink", false, $lkey)) {
                    $route = !$route || $id == $route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->category_route_check($route, $lkey, "software");
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
                if ($hcontent) $lopt["html-content"] = $hcontent;

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
                        'faq'             => $faq,
                        'options'         => $lopt ? Utility::jencode($lopt) : '',
                    ];
            }

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
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
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, 331, 100, [
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

            if ($icon_image) {
                Helper::Load(["Uploads"]);
                $ifolder = Config::get("pictures/category-icon/folder");
                $isizing = Config::get("pictures/category-icon/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($icon_image, [
                    'image-upload' => true,
                    'folder'       => $ifolder,
                    'width'        => $isizing["width"],
                    'height'       => $isizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='icon_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $ipicture = current($upload->operands);
                $ipicture = $ipicture["file_path"];
                $before_pic = $this->model->get_picture("category", $id, "icon");
                if ($before_pic) {
                    FileManager::file_delete($ifolder . $before_pic);
                    $this->model->delete_picture("category", $id, "icon");
                }
                $this->model->insert_picture("category", $id, "icon", $ipicture);
            }


            if (!$lang_data) die("Error! #1");

            $options = Utility::jencode([
                'icon' => $icon,
            ]);

            $p_data = [
                'status'  => $status,
                'parent'  => $parent,
                'rank'    => $rank,
                'options' => $options,
            ];

            $update = $this->model->set_category($id, $p_data);

            if (!$update)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error4"),
                ]));

            $lang_data_x = [];
            foreach ($lang_data as $_k => $data) {
                $lang_data_x[$_k] = $data;
                $data_id = $data["id"];
                unset($data["id"]);
                if ($data_id)
                    $this->model->set_category_lang($data_id, $data);
                else
                    $this->model->insert_category_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-product-category", [
                'name' => $category["title"],
                'id'   => $id,
            ]);

            $p_data["id"] = $id;
            Hook::run("ProductCategoryModified", ['data' => $p_data, 'languages' => $lang_data_x]);

            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success3"),
                'redirect' => $this->AdminCRLink("products-2", ["software", "categories"]),
            ]);
        }


        private function add_new_software()
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
                        'message' => __("admin/products/error29", ['{lang}' => strtoupper($lang)]),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $demo_link = Filter::init("POST/demo-link", "hclear");
            $demo_admin_link = Filter::init("POST/demo-admin-link", "hclear");
            $download_link = Filter::init("POST/download-link", "hclear");
            $short_featuress = Filter::POST("short-features");
            $contents = Filter::POST("content");
            $requirementss = Filter::POST("requirement");
            $install_instrus = Filter::POST("installation-instructions");
            $versionss = Filter::POST("versions");
            $tags1 = Filter::POST("tag1");
            $tags2 = Filter::POST("tag2");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $status = Filter::init("POST/status", "letters");
            $category = Filter::init("POST/category");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $olpu = Filter::init("POST/order_limit_per_user", "numbers");
            $auto_approval = Filter::init("POST/auto-approval", "numbers");
            $hide_domain = Filter::init("POST/hide-domain", "numbers");
            $subdomains = Filter::init("POST/subdomains", "hclear");
            $hide_hosting = Filter::init("POST/hide-hosting", "numbers");
            $invisibility = Filter::init("POST/invisibility", "numbers");
            if ($invisibility) $visibility = "invisible";
            else $visibility = "visible";
            $external_link = Filter::init("POST/external_link", "hclear");
            $popular = (bool)Filter::init("POST/popular", "numbers");
            $change_domain = (bool)Filter::init("POST/change-domain", "numbers");
            $ctoc_s_t = (bool)(int)Filter::init("POST/ctoc-service-transfer", "numbers");
            $ctoc_s_t_l = Filter::init("POST/ctoc-service-transfer-limit", "numbers");
            $notes = Filter::init("POST/notes", "dtext");
            $addons = Filter::POST("addons");
            $requirements = Filter::POST("requirements");
            $prices = Filter::POST("prices");
            $feature_blockss = Filter::POST("feature-block");
            $override_usrcurrency = (int)Filter::init("POST/override_usrcurrency", "rnumbers");
            $taxexempt = (int)Filter::init("POST/taxexempt", "rnumbers");
            $download_file = Filter::FILES("download-file");
            $hbackground = Filter::FILES("hbackground");
            $list_image = Filter::FILES("list_image");
            $mockup_image = Filter::FILES("mockup_image");
            $order_image = Filter::FILES("order_image");
            $affiliate_disable = (int)Filter::init("POST/affiliate_disable", "rnumbers");
            $affiliate_rate = Filter::init("POST/affiliate_rate", "amount");
            $affiliate_rate = str_replace(",", ".", $affiliate_rate);
            if ($affiliate_rate == '') $affiliate_rate = 0;
            $r_s_h = Filter::init("POST/renewal_selection_hide", "numbers");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $route = isset($routes[$lkey]) ? Filter::html_clear($routes[$lkey]) : false;
                $short_features = isset($short_featuress[$lkey]) ? $short_featuress[$lkey] : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $requirementsl = isset($requirementss[$lkey]) ? $requirementss[$lkey] : false;
                $installins = isset($install_instrus[$lkey]) ? $install_instrus[$lkey] : false;
                $versions = isset($versionss[$lkey]) ? $versionss[$lkey] : false;
                $tag1 = isset($tags1[$lkey]) ? $tags1[$lkey] : false;
                $tag2 = isset($tags2[$lkey]) ? $tags2[$lkey] : false;
                $fblocks = isset($feature_blockss[$lkey]) ? $feature_blockss[$lkey] : false;
                $feature_blocks = $fblocks ? [] : null;
                if ($fblocks) {
                    $size = sizeof($fblocks["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_icon = $fblocks["icon"][$i];
                        $f_title = $fblocks["title"][$i];
                        $f_desc = $fblocks["description"][$i];
                        $f_dd_desc = $fblocks["detailed-description"][$i];
                        if ($f_title)
                            $feature_blocks[] = [
                                'icon'                 => $f_icon,
                                'title'                => $f_title,
                                'description'          => $f_desc,
                                'detailed-description' => $f_dd_desc,
                            ];

                    }
                }
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;


                $lopt = [];

                if ($feature_blocks) $lopt["feature_blocks"] = $feature_blocks;
                if ($short_features) $lopt["short_features"] = $short_features;
                if ($requirementsl) $lopt["requirements"] = $requirementsl;
                if ($installins) $lopt["installation_instructions"] = $installins;
                if ($versions) $lopt["versions"] = $versions;
                if ($tag1) $lopt["tag1"] = $tag1;
                if ($tag2) $lopt["tag2"] = $tag2;

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error9", ['{lang}' => $lkeyup]),
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
                'popular'                => $popular,
                'external_link'          => $external_link,
                'demo_link'              => $demo_link,
                'demo_admin_link'        => $demo_admin_link,
                'download_link'          => $download_link,
                'auto_approval'          => $auto_approval,
                'hide_domain'            => $hide_domain,
                'hide_hosting'           => $hide_hosting,
                'renewal_selection_hide' => $r_s_h,
                'order_limit_per_user'   => $olpu,
            ];

            if ($change_domain) $options['change-domain'] = $change_domain;

            if ($ctoc_s_t)
                $options["ctoc-service-transfer"] = [
                    'status' => $ctoc_s_t,
                    'limit'  => $ctoc_s_t_l,
                ];

            if ($download_file) {
                Helper::Load(["Uploads"]);
                $folder = RESOURCE_DIR . DS . "uploads" . DS . "products" . DS;
                $upload = Helper::get("Uploads");
                $upload->init($download_file, [
                    'folder'    => $folder,
                    'file-name' => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "#download-file",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $dwfile = current($upload->operands);
                $dwfile = $dwfile["file_path"];
                $options["download_file"] = $dwfile;
            }

            if ($order_image) {
                Helper::Load(["Uploads", "Image"]);
                $pfolder = Config::get("pictures/products/folder");
                $osizing = Config::get("pictures/products/order/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($order_image, [
                    'image-upload' => true,
                    'folder'       => $pfolder,
                    'width'        => $osizing["width"],
                    'height'       => $osizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='order_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $orimgpicture = current($upload->operands);
                $orimgpicture = $orimgpicture["file_path"];
            }

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
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
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, 331, 100, [
                    'folder-date-detect' => true,
                ]);
            }

            if ($list_image) {
                Helper::Load(["Uploads", "Image"]);
                $sfolder = Config::get("pictures/software/folder");
                $ssizing = Config::get("pictures/software/list/sizing");
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
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $limgpicture = current($upload->operands);
                $limgpicture = $limgpicture["file_path"];
                Image::set($sfolder . $limgpicture, $sfolder . "thumb" . DS, false, 350, 350, [
                    'folder-date-detect' => true,
                ]);
            }

            if ($mockup_image) {
                Helper::Load(["Uploads", "Image"]);
                $sfolder = Config::get("pictures/software/folder");
                $ssizing = Config::get("pictures/software/mockup/sizing");
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
                Image::set($sfolder . $mimgpicture, $sfolder . "thumb" . DS, false, 350, 350, [
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


            $product_data = [
                'type'                 => "software",
                'ctime'                => DateManager::Now(),
                'status'               => $status,
                'category'             => $category,
                'categories'           => $categories ? implode(",", $categories) : '',
                'rank'                 => $rank,
                'override_usrcurrency' => $override_usrcurrency,
                'taxexempt'            => $taxexempt,
                'visibility'           => $visibility,
                'options'              => Utility::jencode($options),
                'notes'                => $notes,
                'subdomains'           => $subdomains,
            ];
            $product_data['addons'] = $addons ? implode(",", $addons) : '';
            $product_data['requirements'] = $requirements ? implode(",", $requirements) : '';
            $product_data['affiliate_disable'] = $affiliate_disable;
            $product_data['affiliate_rate'] = $affiliate_rate;


            $prices_data = [];
            if ($prices) {
                Helper::Load("Money");
                $size = sizeof($prices["period"]) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $time = isset($prices["time"][$i]) ? Filter::numbers($prices["time"][$i]) : 1;
                    if (!$time) $time = 1;
                    $period = isset($prices["period"][$i]) ? Filter::letters($prices["period"][$i]) : false;
                    $amount = isset($prices["amount"][$i]) ? $prices["amount"][$i] : 0;
                    $setup = isset($prices["setup"][$i]) ? $prices["setup"][$i] : 0;
                    $cid = isset($prices["cid"][$i]) ? Filter::numbers($prices["cid"][$i]) : 0;
                    if ($amount) $amount = Money::deformatter($amount, $cid);
                    else $amount = 0;
                    if ($setup) $setup = Money::deformatter($setup, $cid);
                    else $setup = 0;
                    $discount = isset($prices["discount"][$i]) ? $prices["discount"][$i] : 0;
                    $rank = $i;
                    if ($time && $period && $cid) {
                        $prices_data[] = [
                            'owner'    => "softwares",
                            'owner_id' => 0,
                            'type'     => "periodicals",
                            'period'   => $period,
                            'time'     => $time,
                            'amount'   => $amount,
                            'setup'    => $setup,
                            'cid'      => $cid,
                            'discount' => $discount,
                            'rank'     => $rank,
                        ];
                    }
                }
            }

            if (!$prices_data)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error11"),
                ]));

            $insert = $this->model->insert_product_software($product_data);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error10"),
                ]));

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $data["owner_id"] = $insert;
                    if (!$data["route"]) $data["route"] = $insert;
                    $this->model->insert_product_software_lang($data);
                }
            }

            if ($prices_data) {
                foreach ($prices_data as $data) {
                    $data["owner_id"] = $insert;
                    $this->model->insert_price($data);
                }
            }

            if (isset($orimgpicture) && $orimgpicture) $this->model->insert_picture("software", $insert, "order", $orimgpicture);

            if (isset($hpicture) && $hpicture) $this->model->insert_picture("page_software", $insert, "header-background", $hpicture);
            if (isset($limgpicture) && $limgpicture) $this->model->insert_picture("page_software", $insert, "cover", $limgpicture);
            if (isset($mimgpicture) && $mimgpicture) $this->model->insert_picture("page_software", $insert, "mockup", $mimgpicture);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-product", [
                'type' => "software",
                'id'   => $insert,
                'name' => $lang_data[$locall]["title"],
            ]);

            Helper::Load("Products");
            Hook::run("ProductCreated", Products::get("software", $insert));


            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success33"),
                'redirect' => $this->AdminCRLink("products", ["software"]),
            ]);
        }


        private function edit_software()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $product = $this->model->get_product_software($id);
            if (!$product) die();

            $poptions = Utility::jdecode($product["options"], true);


            $slug = Filter::init("GET/slug", "route");
            $lang = Filter::init("GET/lang", "route");
            if ($slug && $lang && ___("package/permalink", false, $lang)) {
                $check = $this->model->page_route_check($slug, $lang);
                if ($check && $check != $product["id"])
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/error29", ['{lang}' => strtoupper($lang)]),
                    ]));
                else
                    die(Utility::jencode(['status' => "successful"]));
            }

            if (!Filter::isPOST()) die();

            $titles = Filter::POST("title");
            $routes = Filter::POST("route");
            $demo_link = Filter::init("POST/demo-link", "hclear");
            $demo_admin_link = Filter::init("POST/demo-admin-link", "hclear");
            $download_link = Filter::init("POST/download-link", "hclear");
            $short_featuress = Filter::POST("short-features");
            $contents = Filter::POST("content");
            $requirementss = Filter::POST("requirement");
            $install_instrus = Filter::POST("installation-instructions");
            $versionss = Filter::POST("versions");
            $tags1 = Filter::POST("tag1");
            $tags2 = Filter::POST("tag2");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $status = Filter::init("POST/status", "letters");
            $category = Filter::init("POST/category");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $olpu = Filter::init("POST/order_limit_per_user", "numbers");
            $auto_approval = Filter::init("POST/auto-approval", "numbers");
            $hide_domain = Filter::init("POST/hide-domain", "numbers");
            $subdomains = Filter::init("POST/subdomains", "hclear");
            $hide_hosting = Filter::init("POST/hide-hosting", "numbers");
            $invisibility = Filter::init("POST/invisibility", "numbers");
            if ($invisibility) $visibility = "invisible";
            else $visibility = "visible";
            $external_link = Filter::init("POST/external_link", "hclear");
            $popular = (bool)Filter::init("POST/popular", "numbers");
            $ctoc_s_t = (bool)(int)Filter::init("POST/ctoc-service-transfer", "numbers");
            $ctoc_s_t_l = Filter::init("POST/ctoc-service-transfer-limit", "numbers");
            $change_domain = (bool)Filter::init("POST/change-domain", "numbers");
            $notes = Filter::init("POST/notes", "dtext");
            $addons = Filter::POST("addons");
            $requirements = Filter::POST("requirements");
            $prices = Filter::POST("prices");
            $delete_prices = ltrim(Filter::init("POST/delete_prices", "hclear"), ",");
            $feature_blockss = Filter::POST("feature-block");
            $override_usrcurrency = (int)Filter::init("POST/override_usrcurrency", "rnumbers");
            $taxexempt = (int)Filter::init("POST/taxexempt", "rnumbers");
            $download_file = Filter::FILES("download-file");
            $hbackground = Filter::FILES("hbackground");
            $list_image = Filter::FILES("list_image");
            $mockup_image = Filter::FILES("mockup_image");
            $order_image = Filter::FILES("order_image");
            $affiliate_disable = (int)Filter::init("POST/affiliate_disable", "rnumbers");
            $affiliate_rate = Filter::init("POST/affiliate_rate", "amount");
            $affiliate_rate = str_replace(",", ".", $affiliate_rate);
            if ($affiliate_rate == '') $affiliate_rate = 0;
            $r_s_h = Filter::init("POST/renewal_selection_hide", "numbers");
            $l_type = Filter::init("POST/license_type", "letters");
            $l_k_prefix = Filter::init("POST/key_prefix", "route");
            $l_k_length = Filter::init("POST/key_length", "rnumbers");
            $l_k_l = Filter::init("POST/key_l", "rnumbers");
            $l_k_u = Filter::init("POST/key_u", "rnumbers");
            $l_k_d = Filter::init("POST/key_d", "rnumbers");
            $l_k_s = Filter::init("POST/key_s", "rnumbers");
            $l_k_dashes = Filter::init("POST/key_dashes", "rnumbers");
            $l_k_variables = Filter::init("POST/variable_name");
            $l_k_variable_names = Filter::init("POST/parameter_name");
            $l_k_match_s = Filter::init("POST/match");
            $l_k_clientArea_s = Filter::init("POST/clientArea");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $lkeyup = strtoupper($lkey);

                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $route = isset($routes[$lkey]) ? Filter::html_clear($routes[$lkey]) : false;
                $short_features = isset($short_featuress[$lkey]) ? $short_featuress[$lkey] : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $requirementsl = isset($requirementss[$lkey]) ? $requirementss[$lkey] : false;
                $installins = isset($install_instrus[$lkey]) ? $install_instrus[$lkey] : false;
                $versions = isset($versionss[$lkey]) ? $versionss[$lkey] : false;
                $tag1 = isset($tags1[$lkey]) ? $tags1[$lkey] : false;
                $tag2 = isset($tags2[$lkey]) ? $tags2[$lkey] : false;
                $fblocks = isset($feature_blockss[$lkey]) ? $feature_blockss[$lkey] : false;
                $feature_blocks = $fblocks ? [] : null;
                if ($fblocks) {
                    $size = sizeof($fblocks["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_icon = $fblocks["icon"][$i];
                        $f_title = $fblocks["title"][$i];
                        $f_desc = $fblocks["description"][$i];
                        $f_dd_desc = $fblocks["detailed-description"][$i];
                        if ($f_title)
                            $feature_blocks[] = [
                                'icon'                 => $f_icon,
                                'title'                => $f_title,
                                'description'          => $f_desc,
                                'detailed-description' => $f_dd_desc,
                            ];

                    }
                }
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;


                $lopt = [];

                if ($feature_blocks) $lopt["feature_blocks"] = $feature_blocks;
                if ($short_features) $lopt["short_features"] = $short_features;
                if ($requirementsl) $lopt["requirements"] = $requirementsl;
                if ($installins) $lopt["installation_instructions"] = $installins;
                if ($versions) $lopt["versions"] = $versions;
                if ($tag1) $lopt["tag1"] = $tag1;
                if ($tag2) $lopt["tag2"] = $tag2;

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/products/error9", ['{lang}' => $lkeyup]),
                    ]));

                $ldata = $this->model->get_product_software_wlang($id, $lkey);

                if (___("package/permalink", false, $lkey)) {
                    $route = !$route || $id == $route ? Filter::permalink($title) : $route;
                    $rcheck = Filter::permalink_check($route);
                    if ($route && $rcheck) {
                        $check = $this->model->page_route_check($route, $lkey);
                        if ($check && $check != $product["id"])
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

            $p_options = Utility::jdecode($product["options"], true);


            $options = [
                'popular'                => $popular,
                'external_link'          => $external_link,
                'demo_link'              => $demo_link,
                'demo_admin_link'        => $demo_admin_link,
                'download_link'          => $download_link,
                'auto_approval'          => $auto_approval,
                'hide_domain'            => $hide_domain,
                'hide_hosting'           => $hide_hosting,
                'renewal_selection_hide' => $r_s_h,
                'order_limit_per_user'   => $olpu,
            ];

            $options = array_merge($poptions, $options);

            if (($change_domain && !isset($poptions["change-domain"])) || (isset($poptions["change-domain"]) && $change_domain != $poptions["change-domain"])) $options["change-domain"] = $change_domain;

            if (($ctoc_s_t && !isset($poptions["ctoc-service-transfer"])) || (isset($poptions["ctoc-service-transfer"]) && ($ctoc_s_t != $poptions["ctoc-service-transfer"]["status"] || $ctoc_s_t_l != $poptions["ctoc-service-transfer"]["limit"])))
                $options["ctoc-service-transfer"] = ['status' => $ctoc_s_t, 'limit' => $ctoc_s_t_l];

            if (isset($p_options["download_file"]))
                $options["download_file"] = $p_options["download_file"];

            if ($download_file) {
                Helper::Load(["Uploads"]);
                $folder = RESOURCE_DIR . DS . "uploads" . DS . "products" . DS;
                $upload = Helper::get("Uploads");
                $upload->init($download_file, [
                    'folder'    => $folder,
                    'file-name' => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "#download-file",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $dwfile = current($upload->operands);
                $dwfile = $dwfile["file_path"];
                if (isset($p_options["download_file"]) && $p_options["download_file"])
                    FileManager::file_delete($folder . $p_options["download_file"]);
                $options["download_file"] = $dwfile;
            }

            if ($order_image) {
                Helper::Load(["Uploads", "Image"]);
                $pfolder = Config::get("pictures/products/folder");
                $osizing = Config::get("pictures/products/order/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($order_image, [
                    'image-upload' => true,
                    'folder'       => $pfolder,
                    'width'        => $osizing["width"],
                    'height'       => $osizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='order_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $orimgpicture = current($upload->operands);
                $orimgpicture = $orimgpicture["file_path"];
                $before_pic = $this->model->get_picture("software", $id, "order");
                if ($before_pic) {
                    FileManager::file_delete($pfolder . $before_pic);
                    FileManager::file_delete($pfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("software", $id, "order");
                }
                $this->model->insert_picture("software", $id, "order", $orimgpicture);
            }

            if ($hbackground) {
                Helper::Load(["Uploads", "Image"]);
                $hfolder = Config::get("pictures/header-background/folder");
                $hsizing = Config::get("pictures/header-background/sizing");
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
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $hpicture = current($upload->operands);
                $hpicture = $hpicture["file_path"];
                Image::set($hfolder . $hpicture, $hfolder . "thumb" . DS, false, 331, 100, [
                    'folder-date-detect' => true,
                ]);
                $before_pic = $this->model->get_picture("page_software", $id, "header-background");
                if ($before_pic) {
                    FileManager::file_delete($hfolder . $before_pic);
                    FileManager::file_delete($hfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("page_software", $id, "header-background");
                }
                $this->model->insert_picture("page_software", $id, "header-background", $hpicture);
            }

            if ($list_image) {
                Helper::Load(["Uploads", "Image"]);
                $sfolder = Config::get("pictures/software/folder");
                $ssizing = Config::get("pictures/software/list/sizing");
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
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $limgpicture = current($upload->operands);
                $limgpicture = $limgpicture["file_path"];
                Image::set($sfolder . $limgpicture, $sfolder . "thumb" . DS, false, 350, 350, [
                    'folder-date-detect' => true,
                ]);
                $before_pic = $this->model->get_picture("page_software", $id, "cover");
                if ($before_pic) {
                    FileManager::file_delete($sfolder . $before_pic);
                    FileManager::file_delete($sfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("page_software", $id, "cover");
                }
                $this->model->insert_picture("page_software", $id, "cover", $limgpicture);
            }

            if ($mockup_image) {
                Helper::Load(["Uploads", "Image"]);
                $sfolder = Config::get("pictures/software/folder");
                $ssizing = Config::get("pictures/software/mockup/sizing");
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
                Image::set($sfolder . $mimgpicture, $sfolder . "thumb" . DS, false, 350, 350, [
                    'folder-date-detect' => true,
                ]);
                $before_pic = $this->model->get_picture("page_software", $id, "mockup");
                if ($before_pic) {
                    FileManager::file_delete($sfolder . $before_pic);
                    FileManager::file_delete($sfolder . "thumb" . DS . $before_pic);
                    $this->model->delete_picture("page_software", $id, "mockup");
                }
                $this->model->insert_picture("page_software", $id, "mockup", $mimgpicture);
            }


            if ($category) {
                $categories = $category;
                $category = $categories[0];
            } else {
                $categories = '';
                $category = 0;
            }


            $options["license_type"] = $l_type;
            $options["key_prefix"] = $l_k_prefix;
            $options["key_length"] = $l_k_length;
            $options["key_l"] = $l_k_l;
            $options["key_u"] = $l_k_u;
            $options["key_d"] = $l_k_d;
            $options["key_s"] = $l_k_s;
            $options["key_dashes"] = $l_k_dashes;

            $parameters = [
                'ip' => [
                    'match'      => isset($l_k_match_s['ip']) ? (int)$l_k_match_s['ip'] : 0,
                    'clientArea' => isset($l_k_clientArea_s['ip']) ? (int)$l_k_clientArea_s['ip'] : 0,
                ],
            ];

            if ($l_k_variables) {
                foreach ($l_k_variables as $l_k_v) {
                    $l_k_v = Filter::letters_numbers($l_k_v, "_");
                    $v_name = isset($l_k_variable_names[$l_k_v]) ? Filter::html_clear($l_k_variable_names[$l_k_v]) : '';
                    if (Validation::isEmpty($v_name)) continue;

                    $match = isset($l_k_match_s[$l_k_v]) ? (int)Filter::numbers($l_k_match_s[$l_k_v]) : 0;
                    $clientArea = isset($l_k_clientArea_s[$l_k_v]) ? (int)Filter::numbers($l_k_clientArea_s[$l_k_v]) : 0;

                    $parameters[$l_k_v] = [
                        'name'       => $v_name,
                        'match'      => $match,
                        'clientArea' => $clientArea,
                    ];
                }
            }

            $options["license_parameters"] = $parameters;

            $product_data = [
                'status'               => $status,
                'category'             => $category,
                'categories'           => $categories ? implode(",", $categories) : '',
                'rank'                 => $rank,
                'override_usrcurrency' => $override_usrcurrency,
                'taxexempt'            => $taxexempt,
                'visibility'           => $visibility,
                'options'              => Utility::jencode($options),
                'notes'                => $notes,
                'subdomains'           => $subdomains,
            ];
            $product_data['addons'] = $addons ? implode(",", $addons) : '';
            $product_data['requirements'] = $requirements ? implode(",", $requirements) : '';
            $product_data['affiliate_disable'] = $affiliate_disable;
            $product_data['affiliate_rate'] = $affiliate_rate;

            $delete_prices = $delete_prices ? explode(",", $delete_prices) : [];
            foreach ($delete_prices as $del) $this->model->delete_price($del);
            $prices_data = [];
            if ($prices) {
                Helper::Load("Money");
                $size = sizeof($prices["period"]) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $pid = isset($prices["id"][$i]) ? Filter::numbers($prices["id"][$i]) : 0;
                    $time = isset($prices["time"][$i]) ? Filter::numbers($prices["time"][$i]) : 1;
                    if (!$time) $time = 1;
                    $period = isset($prices["period"][$i]) ? Filter::letters($prices["period"][$i]) : false;
                    $amount = isset($prices["amount"][$i]) ? $prices["amount"][$i] : 0;
                    $setup = isset($prices["setup"][$i]) ? $prices["setup"][$i] : 0;
                    $cid = isset($prices["cid"][$i]) ? Filter::numbers($prices["cid"][$i]) : 0;
                    if ($amount) $amount = Money::deformatter($amount, $cid);
                    else $amount = 0;
                    if ($setup) $setup = Money::deformatter($setup, $cid);
                    else $setup = 0;
                    $discount = isset($prices["discount"][$i]) ? $prices["discount"][$i] : 0;
                    $rank = $i;
                    if ($time && $period && $cid) {
                        $prices_data[] = [
                            'id'       => $pid,
                            'owner'    => "softwares",
                            'owner_id' => $id,
                            'type'     => "periodicals",
                            'period'   => $period,
                            'time'     => $time,
                            'amount'   => $amount,
                            'setup'    => $setup,
                            'cid'      => $cid,
                            'discount' => $discount,
                            'rank'     => $rank,
                        ];
                    }
                }
            }

            if (!$prices_data)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error11"),
                ]));

            $this->model->set_product_software($id, $product_data);

            if ($lang_data) {
                foreach ($lang_data as $data) {
                    $lang_id = $data["id"];
                    unset($data["id"]);
                    if ($lang_id) $this->model->set_product_software_lang($lang_id, $data);
                    if (!$lang_id) $this->model->insert_product_software_lang($data);
                }
            }

            if ($prices_data) {
                foreach ($prices_data as $data) {
                    $data_id = $data["id"];
                    unset($data["id"]);
                    if ($data_id) $this->model->set_price($data_id, $data);
                    if (!$data_id) $this->model->insert_price($data);
                }
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-product", [
                'type' => "software",
                'id'   => $id,
                'name' => $product["title"],
            ]);

            Helper::Load("Products");
            Hook::run("ProductModified", Products::get("software", $id));


            self::$cache->clear();

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success34"),
                'redirect' => $this->AdminCRLink("products", ["software"]),
            ]);
        }


        private function update_software_settings()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $titles = Filter::POST("title");
            $sub_titles = Filter::POST("sub_title");
            $seo_titles = Filter::POST("seo_title");
            $seo_keywordss = Filter::POST("seo_keywords");
            $seo_descriptions = Filter::POST("seo_description");
            $contents = Filter::POST("content");
            $features = Filter::POST("features");
            $requirementss = Filter::POST("requirements");
            $lerrors = Filter::POST("license-error");
            $faqs = Filter::POST("faq");

            $change_domain = (bool)(int)Filter::init("POST/change-domain", "numbers");
            $change_domain_l = Filter::init("POST/change-domain-limit", "numbers");
            $ctoc_s_t = (bool)(int)Filter::init("POST/ctoc-service-transfer", "numbers");
            $ctoc_s_t_l = Filter::init("POST/ctoc-service-transfer-limit", "numbers");

            $icon_image = Filter::FILES("icon_image");
            $icon = Filter::init("POST/icon", "hclear");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;
                $sub_title = isset($sub_titles[$lkey]) ? Filter::html_clear($sub_titles[$lkey]) : false;
                $seo_title = isset($seo_titles[$lkey]) ? Filter::html_clear($seo_titles[$lkey]) : false;
                $seo_keywords = isset($seo_keywordss[$lkey]) ? Filter::html_clear($seo_keywordss[$lkey]) : false;
                $seo_description = isset($seo_descriptions[$lkey]) ? Filter::html_clear($seo_descriptions[$lkey]) : false;
                $content = isset($contents[$lkey]) ? $contents[$lkey] : false;
                $requirements = isset($requirementss[$lkey]) ? $requirementss[$lkey] : false;
                $license_error = isset($lerrors[$lkey]) ? $lerrors[$lkey] : false;
                $faqx = isset($faqs[$lkey]) ? $faqs[$lkey] : false;
                $faq = $faqx ? [] : null;
                if ($faqx) {
                    $size = sizeof($faqx["title"]) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $f_title = $faqx["title"][$i];
                        $f_desc = $faqx["description"][$i];
                        if ($f_title)
                            $faq[] = [
                                'title'       => $f_title,
                                'description' => $f_desc,
                            ];

                    }
                }

                $data_p['category-software'] = [];
                $data_p2 = $data_p;
                $data_m['meta'] = [];
                $data_p['category-software']["title"] = $title;
                $data_p['category-software']["sub_title"] = $sub_title;
                $data_p['category-software']["content"] = $content;
                $data_p['category-software']["requirements"] = $requirements;
                $data_p['category-software']["faq"] = null;
                $data_p2['category-software']["faq"] = $faq;
                $data_m['meta']["title"] = $seo_title;
                $data_m['meta']["keywords"] = $seo_keywords;
                $data_m['meta']["description"] = $seo_description;

                $data = Bootstrap::$lang->get("constants", $lkey);
                $data = array_replace_recursive($data, $data_p);
                $data = array_replace_recursive($data, $data_p2);

                $data2 = Bootstrap::$lang->get_cm("website/softwares", false, $lkey);
                $data2 = array_replace_recursive($data2, $data_m);

                $data_export = Utility::array_export($data, ['pwith' => true]);
                $data2_export = Utility::array_export($data2, ['pwith' => true]);

                FileManager::file_write(LANG_DIR . $lkey . DS . "constants.php", $data_export);
                FileManager::file_write(LANG_DIR . $lkey . DS . "cm" . DS . "website" . DS . "softwares.php", $data2_export);
                FileManager::file_write(LANG_DIR . $lkey . DS . "license-error.html", $license_error);
            }

            $config_sets = [];

            if ($change_domain != Config::get("options/software-change-domain/status"))
                $config_sets["options"]["software-change-domain"]["status"] = $change_domain;

            if ($change_domain_l != Config::get("options/software-change-domain/limit"))
                $config_sets["options"]["software-change-domain"]["limit"] = $change_domain_l;


            if ($ctoc_s_t != Config::get("options/ctoc-service-transfer/software/status"))
                $config_sets["options"]["ctoc-service-transfer"]["software"]["status"] = $ctoc_s_t;

            if ($ctoc_s_t_l != Config::get("options/ctoc-service-transfer/software/limit"))
                $config_sets["options"]["ctoc-service-transfer"]["software"]["limit"] = $ctoc_s_t_l;

            if ($icon != Config::get("options/category-icon/software"))
                $config_sets["options"]["category-icon"]["software"] = $icon;

            $t_file = TEMPLATE_DIR . "website" . DS . Config::get("theme/name") . DS . "inc" . DS . "software-features.php";

            FileManager::file_write($t_file, $features);


            if ($icon_image) {
                Helper::Load(["Uploads"]);
                $ifolder = Config::get("pictures/category-icon/folder");
                $isizing = Config::get("pictures/category-icon/sizing");
                $upload = Helper::get("Uploads");
                $upload->init($icon_image, [
                    'image-upload' => true,
                    'folder'       => $ifolder,
                    'width'        => $isizing["width"],
                    'height'       => $isizing["height"],
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='icon_image']",
                        'message' => __("admin/products/error2", ['{error}' => $upload->error]),
                    ]));
                $ipicture = current($upload->operands);
                $ipicture = $ipicture["file_path"];
                $before_pic = $this->model->get_picture("category", 4, "icon");
                if ($before_pic) {
                    FileManager::file_delete($ifolder . $before_pic);
                    $this->model->delete_picture("category", 4, "icon");
                }
                $this->model->insert_picture("category", 4, "icon", $ipicture);
            }


            if ($config_sets) {
                if (isset($config_sets["options"])) {
                    $options_result = Config::set("options", $config_sets["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                }
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-product-group", [
                'id' => "software",
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/success21"),
                'redirect' => $this->AdminCRLink("products", ["software"]),
            ]);
        }


        private function update_intl_sms_origin()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $oid = (int)Filter::init("POST/oid", "numbers");
            if (!$oid) return false;

            Helper::Load(["Notification"]);

            $origin = $this->model->get_origin($oid);

            $name = Filter::init("POST/name", "hclear");
            $status = Filter::POST("status");
            $status_msg = Filter::POST("status_msg");
            $keys = array_keys($status);

            if ($keys) {
                foreach ($keys as $key) {
                    $prereg = $this->model->get_origin_prereg($key);
                    $stat = $status[$key];
                    $statmsg = $status_msg[$key];

                    if ($stat != $prereg["status"] || $statmsg != $prereg["status_msg"]) {
                        $this->model->set_origin_prereg($key, [
                            'status'     => $stat,
                            'status_msg' => $statmsg,
                        ]);


                        if ($stat == "active")
                            Notification::sms_intl_origin_has_been_approved($origin["user_id"], $name, $prereg["ccode"]);
                        elseif ($stat == "inactive")
                            Notification::sms_intl_origin_has_been_inactivated($origin["user_id"], $name, $statmsg, $prereg["ccode"]);


                    }

                }
            }

            $this->model->set_origin($oid, ['name' => $name]);

            $adata = UserManager::LoginData("admin");

            User::addAction($adata["id"], "alteration", "changed-intl-sms-origin", ['id' => $oid, 'name' => $name]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/products/success36"),
            ]);
        }


        private function delete_intl_sms_origin()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $oid = (int)Filter::init("POST/id", "numbers");
            if (!$oid) return false;

            $this->model->delete_intl_sms_origin($oid);

            $adata = UserManager::LoginData("admin");

            User::addAction($adata["id"], "deleted", "deleted-intl-sms-origin", ['id' => $oid]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/products/success37"),
            ]);
        }


        private function delete_software_license_error()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");

            Helper::Load("Events");

            Events::delete($id);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "delete", "deleted-software-license-error", [
                'id' => $id,
            ]);

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/products/success40")]);
        }


        private function get_intl_sms_report()
        {
            $id = (int)Filter::init("POST/id", "numbers");
            $reportd = $this->model->get_intl_report($id);
            if (!$reportd) die("Error 1");

            if ($reportd["data"] == null) die("Error 2");
            $reportd["data"] = Utility::jdecode($reportd["data"], true);

            if (!isset($reportd["data"]["module"]) || !$reportd["data"]["module"]) die("Error 3");

            Modules::Load("SMS", $reportd["data"]["module"]);
            $mname = $reportd["data"]["module"];
            $sms = new $mname();

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


        private function module_controller()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $module = Filter::init("REQUEST/module", "route");
            $controller = Filter::init("REQUEST/controller", "route");
            if ($module) {
                Modules::Load("Product", $module);
                $class = new $module;
                if (method_exists($class, 'use_controller')) return $class->use_controller($controller);
                return Modules::getController("Product", $module, $controller);
            }
        }


        private function apply_products($type = '')
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $ids = Filter::init("POST/ids");
            $selection = Filter::init("POST/selection", "letters_numbers");
            $group = Filter::init("POST/group");
            $changes = 0;

            if (str_starts_with($type, "special-")) $type = "special";

            foreach ($ids as $id) {
                $product = Products::get($type, $id);
                if ($selection == 'active' || $selection == 'inactive') {
                    if ($product && $product["status"] != $selection) {
                        $apply = Products::set($type, $id, ['status' => $selection]);
                        if ($apply) $changes++;
                    }
                } elseif ($selection == 'move') {
                    if ($this->model->move_product($type, $id, $group))
                        $changes++;
                } elseif ($selection == 'delete') {
                    $this->model->delete_product($type, $id);
                    $changes++;
                }
            }


            if ($changes)
                echo Utility::jencode([
                    'status'  => "successful",
                    'message' => __("admin/tickets/success15"),
                ]);
            else
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/invoices/error13"),
                ]);

            $a_data = UserManager::LoginData("admin");

            User::addAction($a_data["id"], 'alteration', 'bulk-update-products', [
                'operation' => __("admin/products/list-apply-to-selected-" . $selection, false, Config::get("general/local")),
                'ids'       => $ids,
            ]);
        }


        private function ajax_domain_docs()
        {
            $limit = 10;
            $output = [];
            $aColumns = array('', 'name', '', '', 'rank', '');

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

            $filteredList = $this->model->get_domain_docs($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_domain_docs_total($searches);
            $listTotal = $this->model->get_domain_docs_total();

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                Helper::Load("Money");

                $privOperation = Admin::isPrivilege("PRODUCTS_OPERATION");

                $i = 0;
                if ($filteredList) {

                    $types = [
                        'text'   => __("admin/products/domain-docs-tx5"),
                        'file'   => __("admin/products/domain-docs-tx6"),
                        'select' => __("admin/products/domain-docs-tx9"),
                    ];

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $situations = $situations["products"];

                    foreach ($filteredList as $row) {
                        $i++;
                        $item = [];

                        array_push($item, $i);

                        $id = $row["tld"];


                        array_push($item, '<input type="checkbox" onchange="if($(\'.selected-item:not(:checked)\').length==0) $(\'#allSelect\').prop(\'checked\',true); else $(\'#allSelect\').prop(\'checked\',false);" class="checkbox-custom selected-item" id="doc-' . $id . '-select" value="' . $id . '"><label for="doc-' . $id . '-select" class="checkbox-custom-label"></label>');

                        array_push($item, $row["tld"]);
                        /*
                        array_push($item,$row["title"]);
                        array_push($item,$types[$row["type"]]);
                        array_push($item,$situations[$row["status"]]);

                        */


                        $opeations = '<a href="' . $this->AdminCRLink("products-2", ["domain", "edit-doc"]) . '?id=' . $id . '" class="sbtn" data-tooltip="' . ___("needs/button-edit") . '"><i class="fa fa-edit"></i></a>';
                        if ($privOperation) {
                            $opeations .= ' <a href="javascript:deleteDoc(\'' . $id . '\');" data-tooltip="' . __("admin/products/delete") . '" class="red sbtn" id="delete_' . $id . '"><i class="fa fa-trash-o" aria-hidden="true"></i></a>';
                        }
                        array_push($item, $opeations);
                        $output["aaData"][] = $item;
                    }
                }
            }

            echo Utility::jencode($output);
        }


        private function add_domain_doc()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $tld = Filter::init("POST/tld", "domain");
            $tld = strtolower($tld);
            $tld = trim($tld, ".");
            $descriptions = Filter::init("POST/description");
            $description_values = [];


            $check_previously = $this->model->db->select("id")->from("tldlist_docs");
            $check_previously->where("tld", "=", $tld);
            $check_previously = $check_previously->build();

            if ($check_previously) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/domain-docs-tx17"),
                ]);
                return false;
            }

            if (Utility::strlen($tld) < 2) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/domain-docs-tx10"),
                ]);
                return false;
            }


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $docs = Filter::init("POST/docs");
            $ids = array_keys($docs);

            $added = [];
            $updated = [];
            $removed = [];


            if ($descriptions) {
                foreach ($descriptions as $lk => $v) {
                    if (Utility::strlen(Filter::html_clear($v)) > 3) {
                        $description_values[$lk] = $v;
                    }
                }
            }


            if ($ids) {
                foreach ($ids as $sort_num => $id) {
                    $type = $docs[$id]["type"] ?? "text";
                    $allowed_ext = $docs[$id]["allowed_ext"] ?? '';
                    $max_file_size = $docs[$id]["max_file_size"] ?? 3;
                    $names = $docs[$id]["name"] ?? [];
                    $options = $docs[$id]["options"] ?? [];
                    $options_names = $options["name"] ?? [];
                    $options_values = [];
                    $lang_data = [];


                    if (!in_array($type, ['text', 'file', 'select'])) {
                        echo Utility::jencode([
                            'status'  => "error",
                            'message' => "Invalid Type",
                        ]);
                        return false;
                    }

                    foreach ($lang_list as $l) {
                        $lk = $l["key"];
                        $d_name = Filter::html_clear($names[$lk]);
                        if ($d_name) $lang_data[$lk] = ['name' => $d_name];
                    }


                    if ($type == "file") {
                        if (!$max_file_size) $max_file_size = 3;
                        if (!$allowed_ext) $allowed_ext = "jpg,jpeg,png,gif,pdf,zip,rar,xlsx,doc,docx,csv";
                        $options_values["max_file_size"] = $max_file_size;
                        $options_values["allowed_ext"] = $allowed_ext;
                    } elseif ($type == "select") {
                        $opt_size = sizeof($options_names[$locall]);
                        for ($i = 0; $i <= $opt_size; $i++) {
                            foreach ($lang_list as $l) {
                                $lk = $l["key"];
                                $op_name = Filter::html_clear($options_names[$lk][$i] ?? '');

                                if ($op_name) $options_values[$i][$lk]["name"] = $op_name;
                            }
                        }
                    }


                    $add_available = sizeof($lang_data) > 0;

                    if ($type == "select" && sizeof($options_values) < 1) $add_available = false;


                    if ($add_available)
                        $added[] = [
                            'tld'       => $tld,
                            'type'      => $type,
                            'options'   => sizeof($options_values) > 0 ? Utility::jencode($options_values) : '',
                            'sortnum'   => $sort_num,
                            'languages' => sizeof($lang_data) > 0 ? Utility::jencode($lang_data) : '',
                        ];
                }
            }


            if (!$added) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/domain-docs-tx18"),
                ]);
                return false;
            }

            foreach ($added as $a) {
                $this->model->db->insert("tldlist_docs", $a);
            }


            if ($description_values) {
                $this->model->db
                    ->update("tldlist", ['required_docs_info' => Utility::jencode($description_values)])
                    ->where("name", "=", $tld)
                    ->save();
            }


            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/domain-docs-tx11"),
                'redirect' => $this->AdminCRLink("products-2", ["domain", "docs"]),
            ]);

            $adata = UserManager::LoginData("admin");

            User::addAction($adata["id"], 'added', "domain-doc-created");


        }

        private function edit_domain_doc()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $idn = Filter::init("POST/id", "domain");
            $tld = Filter::init("POST/tld", "domain");
            $tld = strtolower($tld);
            $tld = trim($tld, ".");
            $descriptions = Filter::init("POST/description");
            $description_values = [];


            if ($idn != $tld) {
                $check_previously = $this->model->db->select("id")->from("tldlist_docs");
                $check_previously->where("tld", "=", $tld);
                $check_previously = $check_previously->build();

                if ($check_previously) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/products/domain-docs-tx17"),
                    ]);
                    return false;
                }
            }


            if (Utility::strlen($tld) < 2) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/domain-docs-tx10"),
                ]);
                return false;
            }


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $docs = Filter::init("POST/docs");
            $ids = array_keys($docs);

            $added = [];
            $updated = [];
            $removed = [];


            if ($descriptions) {
                foreach ($descriptions as $lk => $v) {
                    if (Utility::strlen(Filter::html_clear($v)) > 3) {
                        $description_values[$lk] = $v;
                    }
                }
            }


            if ($ids) {

                $saved_docs = $this->model->db->select("id")->from("tldlist_docs");
                $saved_docs->where("tld", "=", $idn);
                $saved_docs = $saved_docs->build() ? $saved_docs->fetch_assoc() : [];
                if ($saved_docs) {
                    foreach ($saved_docs as $r) {
                        if (!in_array($r['id'], $ids)) $removed[] = $r['id'];
                    }
                }


                foreach ($ids as $sort_num => $id) {
                    $type = $docs[$id]["type"] ?? "text";
                    $allowed_ext = $docs[$id]["allowed_ext"] ?? '';
                    $max_file_size = $docs[$id]["max_file_size"] ?? 3;
                    $names = $docs[$id]["name"] ?? [];
                    $options = $docs[$id]["options"] ?? [];
                    $options_names = $options["name"] ?? [];
                    $options_values = [];
                    $lang_data = [];


                    if (!in_array($type, ['text', 'file', 'select'])) {
                        echo Utility::jencode([
                            'status'  => "error",
                            'message' => "Invalid Type",
                        ]);
                        return false;
                    }

                    foreach ($lang_list as $l) {
                        $lk = $l["key"];
                        $d_name = Filter::html_clear($names[$lk]);
                        if ($d_name) $lang_data[$lk] = ['name' => $d_name];
                    }


                    if ($type == "file") {
                        if (!$max_file_size) $max_file_size = 3;
                        if (!$allowed_ext) $allowed_ext = "jpg,jpeg,png,gif,pdf,zip,rar,xlsx,doc,docx,csv";
                        $options_values["max_file_size"] = $max_file_size;
                        $options_values["allowed_ext"] = $allowed_ext;
                    } elseif ($type == "select") {
                        $opt_size = sizeof($options_names[$locall]);
                        for ($i = 0; $i <= $opt_size; $i++) {
                            foreach ($lang_list as $l) {
                                $lk = $l["key"];
                                $op_name = Filter::html_clear($options_names[$lk][$i] ?? '');

                                if ($op_name) $options_values[$i][$lk]["name"] = $op_name;
                            }
                        }
                    }


                    $add_available = sizeof($lang_data) > 0;

                    if ($type == "select" && sizeof($options_values) < 1) $add_available = false;


                    $check_added = $this->model->db->select("id")->from("tldlist_docs");
                    $check_added->where("id", "=", $id);
                    $check_added = $check_added->build();


                    if ($add_available) {
                        if ($check_added)
                            $updated[$id] = [
                                'tld'       => $tld,
                                'type'      => $type,
                                'options'   => sizeof($options_values) > 0 ? Utility::jencode($options_values) : '',
                                'sortnum'   => $sort_num,
                                'languages' => sizeof($lang_data) > 0 ? Utility::jencode($lang_data) : '',
                            ];
                        else
                            $added[] = [
                                'tld'       => $tld,
                                'type'      => $type,
                                'options'   => sizeof($options_values) > 0 ? Utility::jencode($options_values) : '',
                                'sortnum'   => $sort_num,
                                'languages' => sizeof($lang_data) > 0 ? Utility::jencode($lang_data) : '',
                            ];
                    } elseif ($check_added) $removed[] = $id;
                }
            }


            if (!$added && !$updated) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/domain-docs-tx18"),
                ]);
                return false;
            }


            foreach ($added as $a) $this->model->db->insert("tldlist_docs", $a);

            foreach ($updated as $id => $vals) $this->model->db->update("tldlist_docs", $vals)->where("id", "=", $id)->save();
            foreach ($removed as $id) $this->model->db->delete("tldlist_docs")->where("id", "=", $id)->run();


            if ($description_values) {
                $this->model->db
                    ->update("tldlist", ['required_docs_info' => Utility::jencode($description_values)])
                    ->where("name", "=", $tld)
                    ->save();
            }


            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/products/domain-docs-tx13"),
                'redirect' => $this->AdminCRLink("products-2", ["domain", "docs"]),
            ]);

            $adata = UserManager::LoginData("admin");

            User::addAction($adata["id"], 'alteration', "domain-doc-updated");


        }

        private function delete_tld_doc()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $id = Filter::init("POST/id", "domain");


            if (!$id) return false;

            $id = Filter::domain($id);
            $check = $this->model->db->select("id")->from("tldlist_docs");
            $check->where("tld", "=", $id);
            $check = $check->build();

            if (!$check) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Invalid tld document id",
                ]);
                return false;
            }

            $this->model->db->delete("tldlist_docs")->where("tld", "=", $id)->run();
            $this->model->db->update("tldlist", ["required_docs_info" => ''])->where("name", "=", $id)->save();


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/products/domain-docs-tx12"),
            ]);

            $adata = UserManager::LoginData("admin");

            User::addAction($adata["id"], 'added', "domain-doc-deleted", [
                'tld' => $id,
            ]);


        }

        private function deleteSelected_tld_docs()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $ids = Filter::init("POST/ids");


            if (!$ids) return false;

            foreach ($ids as $id) {
                $id = Filter::domain($id);
                $check = $this->model->db->select("id")->from("tldlist_docs");
                $check->where("tld", "=", $id);
                $check = $check->build();

                if (!$check) continue;

                $this->model->db->delete("tldlist_docs")->where("tld", "=", $id)->run();
                $this->model->db->update("tldlist", ["required_docs_info" => ''])->where("name", "=", $id)->save();

            }


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/products/domain-docs-tx12"),
            ]);

            $adata = UserManager::LoginData("admin");

            User::addAction($adata["id"], 'removed', "domain-docs-deleted", ['tlds' => $ids]);


        }


        private function operationMain($type, $operation)
        {
            if ($operation == "add_domain_doc" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->add_domain_doc();
            if ($operation == "delete_tld_doc" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->delete_tld_doc();
            if ($operation == "deleteSelected_tld_docs" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->deleteSelected_tld_docs();
            if ($operation == "edit_domain_doc" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->edit_domain_doc();
            if ($operation == "apply_products" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->apply_products($type);
            if ($operation == "delete_product" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->delete_product($type);
            if ($operation == "copy_product" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->copy_product($type);
            if ($operation == "delete_category" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->delete_category($type);
            if ($operation == "delete_category_hbackground" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->delete_category_hbackground();
            if ($operation == "delete_category_icon_image" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->delete_category_icon_image();
            if ($operation == "add_new_hosting_category" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->add_new_hosting_category();
            if ($operation == "edit_hosting_category" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->edit_hosting_category();
            if ($operation == "add_new_server_category" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->add_new_server_category();
            if ($operation == "edit_server_category" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->edit_server_category();
            if ($operation == "get_shared_server_mdata" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->get_shared_server_mdata();
            if ($operation == "get_module_product_detail" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->get_module_product_detail();
            if ($operation == "add_new_addon" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->add_new_addon();
            if ($operation == "edit_addon" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->edit_addon();
            if ($operation == "delete_addon" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->delete_addon();
            if ($operation == "add_new_addon_category" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->add_new_addon_category();
            if ($operation == "delete_addon_category" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->delete_addon_category();
            if ($operation == "get_addon_category" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->get_addon_category();
            if ($operation == "edit_addon_category" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->edit_addon_category();
            if ($operation == "add_new_requirement" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->add_new_requirement();
            if ($operation == "edit_requirement" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->edit_requirement();
            if ($operation == "delete_requirement" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->delete_requirement();
            if ($operation == "add_new_requirement_category" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->add_new_requirement_category();
            if ($operation == "delete_requirement_category" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->delete_requirement_category();
            if ($operation == "get_requirement_category" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->get_requirement_category();
            if ($operation == "edit_requirement_category" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->edit_requirement_category();
            if ($operation == "add_new_hosting" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->add_new_hosting();
            if ($operation == "edit_hosting" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->edit_hosting();
            if ($operation == "add_new_server" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->add_new_server();
            if ($operation == "edit_server" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->edit_server();
            if ($operation == "test_shared_server_connect" && Admin::isPrivilege("MODULES_SERVERS_SETTINGS"))
                return $this->test_shared_server_connect();

            if ($operation == "add_new_hosting_shared_server_group" && Admin::isPrivilege("MODULES_SERVERS_SETTINGS"))
                return $this->add_new_hosting_shared_server_group();

            if ($operation == "edit_hosting_shared_server_group" && Admin::isPrivilege("MODULES_SERVERS_SETTINGS"))
                return $this->edit_hosting_shared_server_group();

            if ($operation == "add_new_hosting_shared_server" && Admin::isPrivilege("MODULES_SERVERS_SETTINGS"))
                return $this->add_new_hosting_shared_server();
            if ($operation == "delete_shared_server" && Admin::isPrivilege("MODULES_SERVERS_SETTINGS"))
                return $this->delete_shared_server();
            if ($operation == "delete_shared_server_group" && Admin::isPrivilege("MODULES_SERVERS_SETTINGS"))
                return $this->delete_shared_server_group();
            if ($operation == "edit_hosting_shared_server" && Admin::isPrivilege("MODULES_SERVERS_SETTINGS"))
                return $this->edit_hosting_shared_server();

            if ($operation == "hosting_shared_server_import" && Admin::isPrivilege("MODULES_SERVERS_SETTINGS"))
                return $this->hosting_shared_server_import();

            if ($operation == "update_hosting_settings" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->update_hosting_settings();
            if ($operation == "update_server_settings" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->update_server_settings();
            if ($operation == "update_special_settings" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->update_special_settings();
            if ($operation == "update_sms_settings" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->update_sms_settings();
            if ($operation == "add_new_group" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->add_new_group();
            if ($operation == "add_new_special_category" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->add_new_special_category();
            if ($operation == "edit_special_category" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->edit_special_category();
            if ($operation == "add_new_special" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->add_new_special();
            if ($operation == "edit_special" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->edit_special();
            if ($operation == "delete_group" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->delete_group();
            if ($operation == "add_new_sms" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->add_new_sms();
            if ($operation == "edit_sms" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->edit_sms();
            if ($operation == "update_tld_list" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->update_tld_list();
            if ($operation == "update_tld_adjustments" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->update_tld_adjustments();
            if ($operation == "deleteSelected_tlds" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->deleteSelected_tlds();
            if ($operation == "add_new_tld" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->add_new_tld();
            if ($operation == "update_domain_settings" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->update_domain_settings();
            if ($operation == "delete_product_download_file" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->delete_product_download_file();
            if ($operation == "update_international_sms_automation_settings" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->update_international_sms_automation_settings();
            if ($operation == "update_international_sms" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->update_international_sms();
            if ($operation == "update_international_sms_costs" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->update_international_sms_costs();
            if ($operation == "update_international_sms_settings" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->update_international_sms_settings();
            if ($operation == "add_new_software_category" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->add_new_software_category();
            if ($operation == "edit_software_category" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->edit_software_category();
            if ($operation == "add_new_software" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->add_new_software();
            if ($operation == "edit_software" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->edit_software();
            if ($operation == "delete_product_hbackground" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->delete_product_hbackground($type);
            if ($operation == "delete_product_cover" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->delete_product_cover($type);
            if ($operation == "delete_product_order_image" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->delete_product_order_image($type);
            if ($operation == "delete_product_mockup" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->delete_product_mockup($type);
            if ($operation == "update_software_settings" && Admin::isPrivilege("PRODUCTS_GROUP_OPERATION"))
                return $this->update_software_settings();
            if ($operation == "update_intl_sms_origin" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->update_intl_sms_origin();

            if ($operation == "delete_intl_sms_origin" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->delete_intl_sms_origin();

            if ($operation == "delete_software_license_error" && Admin::isPrivilege("PRODUCTS_OPERATION"))
                return $this->delete_software_license_error();

            if ($operation == "get_intl_sms_report") return $this->get_intl_sms_report();

            if ($operation == "module_controller" && Admin::isPrivilege("PRODUCTS_API")) return $this->module_controller();
            if ($operation == "whois_query")
                return $this->domain_whois_query();

            if ($operation == "ajax-domain-docs")
                return $this->ajax_domain_docs();


            echo "Not found operation: " . $operation;
        }


        private function pageMain($type, $page)
        {
            if (!$type) die();

            if ($type == "api" && Admin::isPrivilege(["PRODUCTS_API"]))
                return $this->api($page);

            if ($type == "hosting" && Admin::isPrivilege(["PRODUCTS_GROUP_LOOK", "PRODUCTS_LOOK"]))
                return $this->hosting($page);
            if ($type == "server" && Admin::isPrivilege(["PRODUCTS_GROUP_LOOK", "PRODUCTS_LOOK"]))
                return $this->server($page);
            if ($type == "domain" && Admin::isPrivilege(["PRODUCTS_GROUP_LOOK", "PRODUCTS_LOOK"]))
                return $this->domain($page);
            if ($type == "sms" && Admin::isPrivilege(["PRODUCTS_GROUP_LOOK", "PRODUCTS_LOOK"]))
                return $this->sms($page);
            if ($type == "addons" && Admin::isPrivilege(["PRODUCTS_LOOK", "PRODUCTS_OPERATION"]))
                return $this->addons($page);
            if ($type == "requirements" && Admin::isPrivilege(["PRODUCTS_LOOK", "PRODUCTS_OPERATION"]))
                return $this->requirements($page);
            if ($type == "international-sms" && Admin::isPrivilege(["PRODUCTS_LOOK", "PRODUCTS_OPERATION"]))
                return $this->international_sms($page);
            if (strstr($type, "special") && Admin::isPrivilege(["PRODUCTS_GROUP_LOOK", "PRODUCTS_LOOK"])) {
                $exp = explode("-", $type);
                $id = (int)$exp[1];
                $group = $this->model->get_category($id);
                if (!$group) die("Not found group id");
                return $this->special($group, $page);
            }
            if ($type == "add-new-group" && Admin::isPrivilege(["PRODUCTS_GROUP_OPERATION"]))
                return $this->add_special_group();
            if ($type == "software" && Admin::isPrivilege(["PRODUCTS_GROUP_LOOK", "PRODUCTS_LOOK"]))
                return $this->software($page);
            echo "Not found Page";
        }


        private function software($pname = false)
        {
            $links = [
                'settings'                => $this->AdminCRLink("products-2", ["software", "settings"]),
                'add-new-product'         => $this->AdminCRLink("products-2", ['software', 'add']),
                'add-new-category'        => $this->AdminCRLink("products-2", ['software', 'add-category']),
                'edit-product'            => $this->AdminCRLink("products-2", ['software', 'edit']),
                'edit-category'           => $this->AdminCRLink("products-2", ['software', 'edit-category']),
                'software-group-redirect' => $this->CRLink("softwares"),
                'product-categories'      => $this->AdminCRLink("products-2", ['software', 'categories']),
                'ajax-product-list'       => $this->AdminCRLink("products-2", ['software', 'product-list.json']),
                'ajax-category-list'      => $this->AdminCRLink("products-2", ['software', 'category-list.json']),
                'add-new-addon'           => $this->AdminCRLink("products-2", ['addons', 'add']),
                'add-new-requirement'     => $this->AdminCRLink("products-2", ['requirements', 'add']),
                'license-errors'          => $this->AdminCRLink("products-2", ["software", "license-errors"]),
            ];

            if ($pname == "settings") {
                $links["controller"] = $links["settings"];
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-software-settings"));
                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["software"]),
                        'title' => __("admin/products/breadcrumb-software-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-software-settings"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);


                $this->addData("functions", []);

                $icon_folder = Config::get("pictures/category-icon/folder");


                $icon_picture = $this->model->get_picture("category", 4, "icon");
                if ($icon_picture)
                    $icon_picture = Utility::image_link_determiner($icon_picture, $icon_folder);
                $icon_pictureDeft = Utility::image_link_determiner("default.jpg", $icon_folder);
                $this->addData("getIconImage", $icon_picture);
                $this->addData("getIconImageDeft", $icon_pictureDeft);

                $this->view->chose("admin")->render("software-settings", $this->data);

                die();
            } elseif ($pname == "edit") {

                $id = (int)Filter::init("GET/id", "numbers");
                if (!$id) die();

                $product = $this->model->get_product_software($id);
                if (!$product) die();

                $this->statistics_extract('software', $product);

                $GLOBALS["product"] = $product;

                $this->addData("product", $product);

                $links["controller"] = $links["edit-product"] . "?id=" . $id;

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-edit-software"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["software"]),
                        'title' => __("admin/products/breadcrumb-software-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-edit-software", ['{name}' => $product["title"]]),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", [
                    'get_addons_with_category'       => function ($category = 0) {
                        return $this->model->get_addons_with_category("software", $category);
                    },
                    'get_requirements_with_category' => function ($category = 0) {
                        return $this->model->get_requirements_with_category("software", $category);
                    },
                    'get_product_with_lang'          => function ($lang) {
                        return $this->model->get_product_software_wlang($GLOBALS["product"]["id"], $lang);
                    },
                ]);

                $this->addData("categories", $this->model->get_select_categories("software"));

                $this->addData("addon_categories", $this->model->get_addon_categories([], [], 0, 1000));
                $this->addData("requirement_categories", $this->model->get_requirement_categories([], [], 0, 1000));

                $this->addData("prices", $this->model->get_prices("periodicals", "softwares", $product["id"]));

                $header_folder = Config::get("pictures/header-background/folder");
                $software_folder = Config::get("pictures/software/folder");

                $header_picture = $this->model->get_picture("page_software", $product["id"], "header-background");
                if ($header_picture)
                    $header_picture = Utility::image_link_determiner($header_picture, $header_folder . "thumb" . DS);
                $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
                $this->addData("getHeaderBackground", $header_picture);
                $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);

                $listimg_picture = $this->model->get_picture("page_software", $product["id"], "cover");
                if ($listimg_picture)
                    $listimg_picture = Utility::image_link_determiner($listimg_picture, $software_folder);
                $listimgDeft = Utility::image_link_determiner("list-image-default.jpg", $software_folder);
                $this->addData("getListImageDeft", $listimgDeft);
                $this->addData("getListImage", $listimg_picture);

                $mockup_picture = $this->model->get_picture("page_software", $product["id"], "mockup");
                if ($mockup_picture)
                    $mockup_picture = Utility::image_link_determiner($mockup_picture, $software_folder);
                $mockupDeft = Utility::image_link_determiner("mockup-default.jpg", $software_folder);
                $this->addData("getMockupImageDeft", $mockupDeft);
                $this->addData("getMockupImage", $mockup_picture);


                $product_folder = Config::get("pictures/products/folder");

                $orderimg_picture = $this->model->get_picture("software", $product["id"], "order");
                if ($orderimg_picture)
                    $orderimg_picture = Utility::image_link_determiner($orderimg_picture, $product_folder);
                $orderimgDeft = Utility::image_link_determiner("order-image-default.jpg", $product_folder);
                $this->addData("getOrderImageDeft", $orderimgDeft);
                $this->addData("getOrderImage", $orderimg_picture);


                Helper::Load("Money");
                $pattern = Config::get("crypt/license-pattern");
                $pattern = Utility::text_replace($pattern, ['{pid}' => $product["id"]]);
                $token = md5(Crypt::encode($pattern, Config::get("crypt/system")));
                $this->addData("token", $token);

                $this->view->chose("admin")->render("edit-software", $this->data);

                die();
            } elseif ($pname == "add") {
                $links["controller"] = $links["add-new-product"];

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-add-software"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["software"]),
                        'title' => __("admin/products/breadcrumb-software-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-add-software"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", [
                    'get_addons_with_category'       => function ($category = 0) {
                        return $this->model->get_addons_with_category("software", $category);
                    },
                    'get_requirements_with_category' => function ($category = 0) {
                        return $this->model->get_requirements_with_category("software", $category);
                    },
                ]);

                $this->addData("categories", $this->model->get_select_categories("software"));

                $this->addData("addon_categories", $this->model->get_addon_categories([], [], 0, 1000));
                $this->addData("requirement_categories", $this->model->get_requirement_categories([], [], 0, 1000));

                $header_folder = Config::get("pictures/header-background/folder");
                $software_folder = Config::get("pictures/software/folder");

                $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
                $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);

                $listimgDeft = Utility::image_link_determiner("list-image-default.jpg", $software_folder);
                $this->addData("getListImageDeft", $listimgDeft);

                $mockupDeft = Utility::image_link_determiner("mockup-default.jpg", $software_folder);
                $this->addData("getMockupImageDeft", $mockupDeft);

                $product_folder = Config::get("pictures/products/folder");

                $orderimgDeft = Utility::image_link_determiner("order-image-default.jpg", $product_folder);
                $this->addData("getOrderImageDeft", $orderimgDeft);

                Helper::Load("Money");


                $this->view->chose("admin")->render("add-software", $this->data);

                die();
            } elseif ($pname == "edit-category") {

                $id = (int)Filter::init("GET/id", "numbers");
                if (!$id) die();

                $category = $this->model->get_category($id);
                if (!$category) die();

                $GLOBALS["category"] = $category;

                $links["controller"] = $links["edit-category"] . "?id=" . $id;

                $this->addData("cat", $category);

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-edit-software-category"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["software"]),
                        'title' => __("admin/products/breadcrumb-software-list"),
                    ],
                    [
                        'link'  => $links["product-categories"],
                        'title' => __("admin/products/breadcrumb-software-categories"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-edit-software-category", ['{name}' => $category["title"]]),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", [
                    'get_category_with_lang' => function ($lang) {
                        $data = $this->model->get_category_wlang($GLOBALS["category"]["id"], $lang);
                        return $data;
                    },
                ]);

                $header_folder = Config::get("pictures/header-background/folder");
                $icon_folder = Config::get("pictures/category-icon/folder");

                $header_picture = $this->model->get_picture("category", $category["id"], "header-background");
                if ($header_picture)
                    $header_picture = Utility::image_link_determiner($header_picture, $header_folder . "thumb" . DS);
                $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
                $this->addData("getHeaderBackground", $header_picture);
                $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);


                $icon_picture = $this->model->get_picture("category", $category["id"], "icon");
                if ($icon_picture)
                    $icon_picture = Utility::image_link_determiner($icon_picture, $icon_folder);
                $icon_pictureDeft = Utility::image_link_determiner("default.jpg", $icon_folder);
                $this->addData("getIconImage", $icon_picture);
                $this->addData("getIconImageDeft", $icon_pictureDeft);


                $this->addData("categories", $this->model->get_select_categories("software"));

                $this->view->chose("admin")->render("edit-software-category", $this->data);

                die();

            } elseif ($pname == "add-category") {

                $links["controller"] = $links["add-new-category"];

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-add-software-category"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["software"]),
                        'title' => __("admin/products/breadcrumb-software-list"),
                    ],
                    [
                        'link'  => $links["product-categories"],
                        'title' => __("admin/products/breadcrumb-software-categories"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-add-software-category"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", []);

                $header_folder = Config::get("pictures/header-background/folder");
                $icon_folder = Config::get("pictures/category-icon/folder");

                $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
                $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);

                $icon_pictureDeft = Utility::image_link_determiner("default.jpg", $icon_folder);
                $this->addData("getIconImageDeft", $icon_pictureDeft);

                $this->addData("categories", $this->model->get_select_categories("software"));

                $this->view->chose("admin")->render("add-software-category", $this->data);

                die();
            } elseif ($pname == "categories") {

                $links["controller"] = $links["product-categories"];
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-software-categories"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["software"]),
                        'title' => __("admin/products/breadcrumb-software-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-software-categories"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("settings", [

                ]);

                $this->addData("functions", []);

                $this->view->chose("admin")->render("software-categories", $this->data);

                die();
            } elseif ($pname == "product-list.json") {

                $limit = 10;
                $output = [];
                $aColumns = array('', 'name', '', '', 'rank', '');

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

                $filteredList = $this->model->get_products("software", $searches, $orders, $start, $end);
                $filterTotal = $this->model->get_products_total("software", $searches);
                $listTotal = $this->model->get_products_total("software");

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {
                    Helper::Load("Money");

                    $privOperation = Admin::isPrivilege("PRODUCTS_OPERATION");
                    $privGroupLook = Admin::isPrivilege("PRODUCTS_GROUP_LOOK");

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $situations = $situations["products"];

                    $i = 0;
                    if ($filteredList) {
                        foreach ($filteredList as $row) {
                            $i++;
                            $item = [];
                            $show_category = ___("needs/none");
                            if ($row["category"]) {
                                $show_category = $row["category"] . ' <a href="' . $this->CRLink("softwares_cat", [$row["category_route"]]) . '" target="_blank" class="targeturl sbtn"><i class="fa fa-external-link" aria-hidden="true"></i></a>';
                            }

                            array_push($item, $i);

                            $id = $row["id"];

                            array_push($item, '<input type="checkbox" onchange="if($(\'.selected-item:not(:checked)\').length==0) $(\'#allSelect\').prop(\'checked\',true); else $(\'#allSelect\').prop(\'checked\',false);" class="checkbox-custom selected-item" id="product-' . $id . '-select" value="' . $id . '"><label for="product-' . $id . '-select" class="checkbox-custom-label"></label>');

                            array_push($item, $row["name"]);
                            array_push($item, $show_category);
                            array_push($item, Money::formatter_symbol($row["amount"], $row["cid"]));
                            array_push($item, $situations[$row["status"]]);
                            $opeations = '<a href="' . $links["edit-product"] . '?id=' . $row["id"] . '" class="sbtn" data-tooltip="' . ___("needs/button-edit") . '"><i class="fa fa-edit"></i></a>';
                            if ($privOperation) {
                                $opeations .= ' <a href="javascript:copyProduct(' . $row["id"] . ');" data-tooltip="' . ___("needs/copy") . '" class="blue sbtn" id="copy_' . $row["id"] . '"><i class="fa fa-copy" aria-hidden="true"></i></a> ';
                                $opeations .= ' <a href="javascript:deleteProduct(' . $row["id"] . ',\'' . str_replace("'", "\'", $row["name"]) . '\');" data-tooltip="' . __("admin/products/delete") . '" class="red sbtn" id="delete_' . $row["id"] . '"><i class="fa fa-trash-o" aria-hidden="true"></i></a>';
                            }
                            array_push($item, $opeations);
                            $output["aaData"][] = $item;
                        }
                    }
                }

                die(Utility::jencode($output));
            } elseif ($pname == "category-list.json") {
                $limit = 10;
                $output = [];
                $aColumns = array('', '', 'rank', '');

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

                $filteredList = $this->model->get_categories("software", $searches, $orders, $start, $end);
                $filterTotal = $this->model->get_categories_total("software", $searches);
                $listTotal = $this->model->get_categories_total("software");

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {
                    Helper::Load("Money");

                    $privGroupOperation = Admin::isPrivilege("PRODUCTS_GROUP_OPERATION");
                    $privGroupLook = Admin::isPrivilege("PRODUCTS_GROUP_LOOK");

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $situations = $situations["categories"];

                    $i = 0;
                    if ($filteredList) {
                        foreach ($filteredList as $row) {
                            $i++;
                            $item = [];
                            $catLink = $this->CRLink("softwares_cat", [$row["route"]]);
                            $catLink = ' <a href="' . $catLink . '" target="_blank" class="targeturl sbtn"><i class="fa fa-external-link" aria-hidden="true"></i></a>';
                            if ($row["parent_route"] == '')
                                $parent_catLink = null;
                            else {
                                $parent_catLink = $this->CRLink("softwares_cat", [$row["parent_route"]]);
                                $parent_catLink = ' <a href="' . $parent_catLink . '" target="_blank" class="targeturl sbtn"><i class="fa fa-external-link" aria-hidden="true"></i></a>';
                            }

                            array_push($item, $i);
                            array_push($item, $row["name"] . $catLink);
                            #array_push($item,$row["parent_name"].$parent_catLink);
                            array_push($item, $situations[$row["status"]]);
                            $opeations = '<a href="' . $links["edit-category"] . '?id=' . $row["id"] . '" class="sbtn"><i class="fa fa-edit"></i></a>';
                            if ($privGroupOperation)
                                $opeations .= ' <a href="javascript:deleteCategory(' . $row["id"] . ',\'' . $row["name"] . '\');" title="' . __("admin/products/delete") . '" class="red sbtn" id="delete_' . $row["id"] . '"><i class="fa fa-trash-o" aria-hidden="true"></i></a>';
                            array_push($item, $opeations);
                            $output["aaData"][] = $item;
                        }
                    }
                }

                die(Utility::jencode($output));
            } elseif ($pname == "license-errors.json") {
                $limit = 10;
                $output = [];
                $aColumns = array('', '', 'rank', '');

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

                $params = [
                    'type'  => "error",
                    'owner' => "license",
                ];

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

                    Helper::Load(["Products"]);

                    $privOperation = Admin::isPrivilege("PRODUCTS_OPERATION");

                    if ($filteredList) {
                        $this->addData("privOperation", $privOperation);
                        $this->addData("list", $filteredList);
                        $output["aaData"] = $this->view->chose("admin")->render("ajax-software-license-errors", $this->data, false, true);
                    }
                }

                die(Utility::jencode($output));
            } elseif ($pname == "license-errors") {

                $links["controller"] = $this->AdminCRLink("products", ["software"]);
                $links["ajax-list"] = $this->AdminCRLink("products-2", ["software", "license-errors.json"]);
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-software-license-errors"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["software"]),
                        'title' => __("admin/products/breadcrumb-software-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-software-license-errors"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("settings", [

                ]);

                $this->addData("functions", []);

                $this->view->chose("admin")->render("software-license-errors", $this->data);

                die();
            }

            $links["controller"] = $this->AdminCRLink("products", ["software"]);
            $this->addData("links", $links);

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

            $this->addData("meta", __("admin/products/meta-software-list"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/products/breadcrumb-software-list"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("settings", [

            ]);

            $this->addData("functions", []);

            $this->view->chose("admin")->render("software-list", $this->data);
        }


        private function international_sms($pname = false)
        {

            $links = [
                'page-redirect'    => $this->CRLink("international-sms"),
                'modules-redirect' => $this->AdminCRLink("modules", ["sms"]),
                'settings'         => $this->AdminCRLink("products-2", ["international-sms", "settings"]),
                'origins'          => $this->AdminCRLink("products-2", ["international-sms", "origins"]),
                'reports'          => $this->AdminCRLink("products-2", ["international-sms", "reports"]),
            ];

            if ($pname == "settings") {
                $links["controller"] = $links["settings"];
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-intl-sms-settings"));
                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["international-sms"]),
                        'title' => __("admin/products/breadcrumb-intl-sms"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-intl-sms-settings"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);


                $this->addData("functions", []);

                $icon_folder = Config::get("pictures/category-icon/folder");


                $icon_picture = $this->model->get_picture("category", 7, "icon");
                if ($icon_picture)
                    $icon_picture = Utility::image_link_determiner($icon_picture, $icon_folder);
                $icon_pictureDeft = Utility::image_link_determiner("default.jpg", $icon_folder);
                $this->addData("getIconImage", $icon_picture);
                $this->addData("getIconImageDeft", $icon_pictureDeft);

                $this->view->chose("admin")->render("international-sms-settings", $this->data);

                die();
            }

            if ($pname == "origins") {
                $links["controller"] = $links["origins"];
                $links["ajax-origins"] = $this->AdminCRLink("products-2", ["international-sms", "origins.json"]);
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-intl-sms-origins"));
                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["international-sms"]),
                        'title' => __("admin/products/breadcrumb-intl-sms"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-intl-sms-origins"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);


                $this->addData("functions", []);

                $this->view->chose("admin")->render("international-sms-origins", $this->data);

                die();
            }

            if ($pname == "origins.json") {
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


                $filteredList = $this->model->get_origins_intl($searches, $orders, $start, $end);
                $filterTotal = $this->model->get_origins_intl_total($searches);
                $listTotal = $this->model->get_origins_intl_total();

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {
                    Helper::Load(["Orders", "Products", "Money"]);

                    $privOperation = Admin::isPrivilege("PRODUCTS_OPERATION");

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $situations = $situations["origins"];

                    if ($filteredList) {
                        $this->addData("privOperation", $privOperation);
                        $this->addData("situations", $situations);
                        $this->addData("list", $filteredList);
                        $output["aaData"] = $this->view->chose("admin")->render("ajax-international-sms-origins", $this->data, false, true);
                    }
                }

                die(Utility::jencode($output));
            }

            if ($pname == "reports") {
                $links["controller"] = $links["reports"];
                $links["ajax-reports"] = $this->AdminCRLink("products-2", ["international-sms", "reports.json"]);
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-intl-sms-reports"));
                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["international-sms"]),
                        'title' => __("admin/products/breadcrumb-intl-sms"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-intl-sms-reports"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);


                $this->addData("functions", []);

                $this->view->chose("admin")->render("international-sms-reports", $this->data);

                die();
            }

            if ($pname == "reports.json") {
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


                $filteredList = $this->model->get_reports_intl($searches, $orders, $start, $end);
                $filterTotal = $this->model->get_reports_intl_total($searches);
                $listTotal = $this->model->get_reports_intl_total();

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {
                    Helper::Load(["Orders", "Products", "Money"]);

                    if ($filteredList) {
                        foreach ($filteredList as $k => $v)
                            if ($e_c = Crypt::decode($v["content"], "*_LOG_*" . Config::get("crypt/system")))
                                $filteredList[$k]['content'] = $e_c;

                        $this->addData("list", $filteredList);
                        $output["aaData"] = $this->view->chose("admin")->render("ajax-international-sms-reports", $this->data, false, true);
                    }
                }

                die(Utility::jencode($output));
            }

            $links["controller"] = $this->AdminCRLink("products", ["international-sms"]);
            $this->addData("links", $links);

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

            $this->addData("meta", __("admin/products/meta-intl-sms"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/products/breadcrumb-intl-sms"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $locall = Config::get("general/local");

            $mname = Config::get("modules/sms-intl");
            $module = Modules::Load("SMS", $mname, true);
            $mname = isset($module["lang"]["name"]) ? $module["lang"]["name"] : null;

            $this->addData("settings", [
                'module'           => $mname,
                'primary-currency' => Config::get("sms/primary-currency"),
                'profit-rate'      => Config::get("sms/profit-rate"),
                'cron'             => Config::get("cronjobs/tasks/auto-intl-sms-prices"),
            ]);

            $this->addData("functions", []);

            Helper::Load("Money");

            Money::$digit = 4;

            $this->addData("list", Config::get("sms/country-prices"));

            $this->view->chose("admin")->render("international-sms", $this->data);

        }


        private function domain($pname = false)
        {

            Helper::Load(["Products"]);

            $links = [
                'domain-redirect'  => $this->CRLink("domain"),
                'modules-redirect' => $this->AdminCRLink("modules", ["registrars"]),
                'settings'         => $this->AdminCRLink("products-2", ["domain", "settings"]),
                'whois'            => $this->AdminCRLink("products-2", ["domain", "whois"]),
                'ajax'             => $this->AdminCRLink("products-2", ["domain", "list.json"]),
            ];

            if ($pname == "settings") {
                $links["controller"] = $links["settings"];
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-domain-settings"));
                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["domain"]),
                        'title' => __("admin/products/breadcrumb-domain-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-domain-settings"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $locall = Config::get("general/local");

                $this->addData("functions", []);
                $this->addData("settings", [
                    'options'                   => [
                        'ns1' => Config::get("options/ns-addresses/ns1"),
                        'ns2' => Config::get("options/ns-addresses/ns2"),
                        'ns3' => Config::get("options/ns-addresses/ns3"),
                        'ns4' => Config::get("options/ns-addresses/ns4"),
                    ],
                    'override_usrcurrency'      => Config::get("options/domain-override-user-currency"),
                    'domain-check-default-tlds' => Config::get("options/limits/domain-check-default-tlds"),
                    'whois-privacy'             => Config::get("options/domain-whois-privacy"),
                ]);

                Helper::Load("Money");

                $this->addData("tlds", $this->model->get_tlds(false, false, 0, 10000));


                $icon_folder = Config::get("pictures/category-icon/folder");


                $icon_picture = $this->model->get_picture("category", 3, "icon");
                if ($icon_picture)
                    $icon_picture = Utility::image_link_determiner($icon_picture, $icon_folder);
                $icon_pictureDeft = Utility::image_link_determiner("default.jpg", $icon_folder);
                $this->addData("getIconImage", $icon_picture);
                $this->addData("getIconImageDeft", $icon_pictureDeft);


                $this->view->chose("admin")->render("domain-settings", $this->data);

                die();
            }
            if ($pname == "whois") {
                $links["controller"] = $links["whois"];
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-domain-whois"));
                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["domain"]),
                        'title' => __("admin/products/breadcrumb-domain-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-domain-whois"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->view->chose("admin")->render("domain-whois", $this->data);

                die();
            } elseif ($pname == "list.json") {
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

                $filteredList = $this->model->get_tlds($searches, $orders, $start, $end);
                $filterTotal = $this->model->get_tlds_total($searches);
                $listTotal = $this->model->get_tlds_total();

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {
                    Helper::Load("Money");

                    if ($filteredList) {
                        $registrars = Modules::Load("Registrars", "All", true);
                        if ($registrars) $registrars = array_keys($registrars);

                        $this->addData("registrars", $registrars);

                        $this->addData("list", $filteredList);

                        $output["aaData"] = $this->view->chose("admin")->render("ajax-tld-list", $this->data, false, true);
                    }
                }

                die(Utility::jencode($output));
            } elseif ($pname == "docs") {
                return $this->domain_docs();
            } elseif ($pname == "add-doc") {
                return $this->domain_add_doc();
            } elseif ($pname == "edit-doc") {
                return $this->domain_edit_doc();
            } elseif (Filter::GET("bring") == "adjustments")
                $this->addData("list", $this->model->get_tlds(false, false, 0, 10000));


            $links["controller"] = $this->AdminCRLink("products", ["domain"]);
            $this->addData("links", $links);

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

            $this->addData("meta", __("admin/products/meta-domain-list"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/products/breadcrumb-domain-list"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $registrars = Modules::Load("Registrars", "All", true);
            if ($registrars) $registrars = array_keys($registrars);

            $this->addData("registrars", $registrars);

            $this->addData("settings", []);

            $this->addData("functions", []);

            Helper::Load("Money");

            $this->view->chose("admin")->render("tld-list", $this->data);
        }


        private function sms($pname = false)
        {
            $links = [
                'settings'            => $this->AdminCRLink("products-2", ['sms', "settings"]),
                'add-new-product'     => $this->AdminCRLink("products-2", ['sms', 'add']),
                'edit-product'        => $this->AdminCRLink("products-2", ['sms', 'edit']),
                'sms-group-redirect'  => $this->CRLink("products", ['sms']),
                'ajax-product-list'   => $this->AdminCRLink("products-2", ['sms', 'product-list.json']),
                'add-new-requirement' => $this->AdminCRLink("products-2", ['requirements', 'add']),
                'reports'             => $this->AdminCRLink("products-2", ['sms', 'reports']),
                'origins'             => $this->AdminCRLink("products-2", ['sms', 'origins']),
                'orders'              => $this->AdminCRLink("orders"),
            ];

            if ($pname == "settings") {
                $links["controller"] = $links["settings"];
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-sms-settings"));
                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["sms"]),
                        'title' => __("admin/products/breadcrumb-sms-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-sms-settings"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);


                $this->addData("functions", []);

                $icon_folder = Config::get("pictures/category-icon/folder");


                $icon_picture = $this->model->get_picture("category", 6, "icon");
                if ($icon_picture)
                    $icon_picture = Utility::image_link_determiner($icon_picture, $icon_folder);
                $icon_pictureDeft = Utility::image_link_determiner("default.jpg", $icon_folder);
                $this->addData("getIconImage", $icon_picture);
                $this->addData("getIconImageDeft", $icon_pictureDeft);

                $this->view->chose("admin")->render("sms-settings", $this->data);

                die();
            }

            if ($pname == "origins") {
                $links["controller"] = $links["origins"];
                $links["ajax-origins"] = $this->AdminCRLink("products-2", ['sms', 'origins.json']);
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-sms-origins"));
                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["sms"]),
                        'title' => __("admin/products/breadcrumb-sms-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-sms-origins"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);


                $this->addData("functions", []);

                $this->view->chose("admin")->render("sms-origins", $this->data);

                die();
            }

            if ($pname == "reports") {
                $links["controller"] = $links["reports"];
                $links["ajax-reports"] = $this->AdminCRLink("products-2", ['sms', 'reports.json']);
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-sms-reports"));
                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["sms"]),
                        'title' => __("admin/products/breadcrumb-sms-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-sms-reports"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);


                $this->addData("functions", []);

                $this->view->chose("admin")->render("sms-reports", $this->data);

                die();
            }

            if ($pname == "origins.json") {
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


                $filteredList = $this->model->get_origins($searches, $orders, $start, $end);
                $filterTotal = $this->model->get_origins_total($searches);
                $listTotal = $this->model->get_origins_total();

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
                    $situations = $situations["origins"];

                    if ($filteredList) {
                        $this->addData("privOperation", $privOperation);
                        $this->addData("privDelete", $privDelete);
                        $this->addData("situations", $situations);
                        $this->addData("list", $filteredList);
                        $output["aaData"] = $this->view->chose("admin")->render("ajax-sms-origins", $this->data, false, true);
                    }
                }

                die(Utility::jencode($output));
            }

            if ($pname == "reports.json") {
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


                $filteredList = $this->model->get_reports($searches, $orders, $start, $end);
                $filterTotal = $this->model->get_reports_total($searches);
                $listTotal = $this->model->get_reports_total();

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {
                    Helper::Load(["Orders", "Products", "Money"]);

                    if ($filteredList) {
                        foreach ($filteredList as $k => $v)
                            if ($e_c = Crypt::decode($v["content"], "*_LOG_*" . Config::get("crypt/system")))
                                $filteredList[$k]['content'] = $e_c;

                        $this->addData("list", $filteredList);
                        $output["aaData"] = $this->view->chose("admin")->render("ajax-sms-reports", $this->data, false, true);
                    }
                }

                die(Utility::jencode($output));
            }

            if ($pname == "edit") {

                $id = (int)Filter::init("GET/id", "numbers");
                if (!$id) die();

                $product = $this->model->get_product($id);
                if (!$product) die();

                $this->statistics_extract('sms', $product);

                $GLOBALS["product"] = $product;

                $this->addData("product", $product);

                $links["controller"] = $links["edit-product"] . "?id=" . $id;

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-edit-sms"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["sms"]),
                        'title' => __("admin/products/breadcrumb-sms-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-edit-sms", ['{name}' => $product["title"]]),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);


                $this->addData("functions", [
                    'get_product_with_lang' => function ($lang) {
                        return $this->model->get_product_wlang($GLOBALS["product"]["id"], $lang);
                    },
                ]);


                $this->addData("price", $this->model->get_price("sale", "products", $product["id"]));

                Helper::Load("Money");

                $modules = Modules::Load("SMS", "All", true);
                $this->addData("modules", $modules);

                $this->view->chose("admin")->render("edit-sms", $this->data);

                die();
            }

            if ($pname == "add") {
                $links["controller"] = $links["add-new-product"];

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-add-sms"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["sms"]),
                        'title' => __("admin/products/breadcrumb-sms-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-add-sms"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                Helper::Load("Money");

                $modules = Modules::Load("SMS", "All", true);
                $this->addData("modules", $modules);


                $this->view->chose("admin")->render("add-sms", $this->data);

                die();
            }

            if ($pname == "product-list.json") {

                $limit = 10;
                $output = [];
                $aColumns = array('', 'name', '', 'rank', '');

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

                $filteredList = $this->model->get_products("sms", $searches, $orders, $start, $end);
                $filterTotal = $this->model->get_products_total("sms", $searches);
                $listTotal = $this->model->get_products_total("sms");

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {
                    Helper::Load("Money");

                    $privOperation = Admin::isPrivilege("PRODUCTS_OPERATION");
                    $privGroupLook = Admin::isPrivilege("PRODUCTS_GROUP_LOOK");

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $situations = $situations["products"];

                    $i = 0;
                    if ($filteredList) {
                        foreach ($filteredList as $row) {
                            $i++;
                            $item = [];
                            array_push($item, $i);

                            $id = $row["id"];

                            array_push($item, '<input type="checkbox" onchange="if($(\'.selected-item:not(:checked)\').length==0) $(\'#allSelect\').prop(\'checked\',true); else $(\'#allSelect\').prop(\'checked\',false);" class="checkbox-custom selected-item" id="product-' . $id . '-select" value="' . $id . '"><label for="product-' . $id . '-select" class="checkbox-custom-label"></label>');

                            array_push($item, $row["name"]);
                            array_push($item, Money::formatter_symbol($row["amount"], $row["cid"]));
                            array_push($item, $situations[$row["status"]]);
                            $opeations = '<a href="' . $links["edit-product"] . '?id=' . $row["id"] . '" class="sbtn"><i class="fa fa-edit"></i></a>';
                            if ($privOperation)
                                $opeations .= ' <a href="javascript:deleteProduct(' . $row["id"] . ',\'' . $row["name"] . '\');" title="' . __("admin/products/delete") . '" class="red sbtn" id="delete_' . $row["id"] . '"><i class="fa fa-trash-o" aria-hidden="true"></i></a>';
                            array_push($item, $opeations);
                            $output["aaData"][] = $item;
                        }
                    }
                }

                die(Utility::jencode($output));
            }

            $links["controller"] = $this->AdminCRLink("products", ["sms"]);
            $this->addData("links", $links);

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

            $this->addData("meta", __("admin/products/meta-sms-list"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/products/breadcrumb-sms-list"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("settings", [

            ]);

            $this->addData("functions", []);

            $this->view->chose("admin")->render("sms-list", $this->data);
        }


        private function add_special_group()
        {

            $links = [
                'controller' => $this->AdminCRLink("products", ["add-new-group"]),
            ];

            $this->addData("links", $links);

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

            $this->addData("meta", __("admin/products/meta-add-new-group"));
            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/products/breadcrumb-add-new-group"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $header_folder = Config::get("pictures/header-background/folder");

            $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
            $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);

            $this->addData("functions", []);

            $this->view->chose("admin")->render("add-product-group", $this->data);
        }


        private function special($group = [], $pname = false)
        {
            $links = [
                'settings'               => $this->AdminCRLink("products-2", ['special-' . $group["id"], "settings"]),
                'add-new-product'        => $this->AdminCRLink("products-2", ['special-' . $group["id"], 'add']),
                'add-new-category'       => $this->AdminCRLink("products-2", ['special-' . $group["id"], 'add-category']),
                'edit-product'           => $this->AdminCRLink("products-2", ['special-' . $group["id"], 'edit']),
                'edit-category'          => $this->AdminCRLink("products-2", ['special-' . $group["id"], 'edit-category']),
                'special-group-redirect' => $this->CRLink("products", [$group["route"]]),
                'product-categories'     => $this->AdminCRLink("products-2", ['special-' . $group["id"], 'categories']),
                'ajax-product-list'      => $this->AdminCRLink("products-2", ['special-' . $group["id"], 'product-list.json']),
                'ajax-category-list'     => $this->AdminCRLink("products-2", ['special-' . $group["id"], 'category-list.json']),
                'add-new-addon'          => $this->AdminCRLink("products-2", ['addons', 'add']),
                'add-new-requirement'    => $this->AdminCRLink("products-2", ['requirements', 'add']),
            ];

            if ($pname == "settings") {
                $links["controller"] = $links["settings"];
                $this->addData("links", $links);

                $group["options"] = Utility::jdecode($group["options"], true);

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

                $this->addData("meta", [
                    'title' => Utility::text_replace(__("admin/products/meta-special-settings/title"), [
                        '{group-name}' => $group["title"],
                    ]),
                ]);
                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["special-" . $group["id"]]),
                        'title' => __("admin/products/breadcrumb-special-list", ['{group-name}' => $group["title"]]),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-special-settings", ['{group-name}' => $group["title"]]),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);


                $this->addData("functions", []);

                $this->addData("group", $group);

                $GLOBALS["group"] = $group;

                $this->addData("functions", [
                    'get_lang' => function ($lang) {
                        $data = $this->model->get_category_wlang($GLOBALS["group"]["id"], $lang);
                        return $data;
                    },
                ]);

                $header_folder = Config::get("pictures/header-background/folder");

                $header_picture = $this->model->get_picture("category", $group["id"], "header-background");
                if ($header_picture)
                    $header_picture = Utility::image_link_determiner($header_picture, $header_folder . "thumb" . DS);
                $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
                $this->addData("getHeaderBackground", $header_picture);
                $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);

                $icon_folder = Config::get("pictures/category-icon/folder");


                $icon_picture = $this->model->get_picture("category", $group["id"], "icon");
                if ($icon_picture)
                    $icon_picture = Utility::image_link_determiner($icon_picture, $icon_folder);
                $icon_pictureDeft = Utility::image_link_determiner("default.jpg", $icon_folder);
                $this->addData("getIconImage", $icon_picture);
                $this->addData("getIconImageDeft", $icon_pictureDeft);

                $this->view->chose("admin")->render("special-settings", $this->data);
                die();
            } elseif ($pname == "edit") {

                $id = (int)Filter::init("GET/id", "numbers");
                if (!$id) die();

                $product = $this->model->get_product($id);
                if (!$product) die();

                $this->statistics_extract('special', $product);


                $GLOBALS["product"] = $product;

                $this->addData("product", $product);

                $links["controller"] = $links["edit-product"] . "?id=" . $id;

                $this->addData("links", $links);

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

                $this->addData("meta", [
                    'title' => Utility::text_replace(__("admin/products/meta-edit-special/title"), [
                        '{group-name}' => $group["title"],
                    ]),
                ]);

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["special-" . $group["id"]]),
                        'title' => __("admin/products/breadcrumb-special-list", [
                            '{group-name}' => $group["title"],
                        ]),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-edit-special", [
                            '{group-name}' => $group["title"],
                            '{name}'       => $product["title"]]),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("group", $group);
                $GLOBALS["group"] = $group;

                $this->addData("functions", [
                    'get_addons_with_category'       => function ($category = 0) {
                        return $this->model->get_addons_with_category("special_" . $GLOBALS["group"]["id"], $category);
                    },
                    'get_product_with_lang'          => function ($lang) {
                        return $this->model->get_product_wlang($GLOBALS["product"]["id"], $lang);
                    },
                    'get_requirements_with_category' => function ($category = 0) {
                        return $this->model->get_requirements_with_category("special_" . $GLOBALS["group"]["id"], $category);
                    },
                    'get_category'                   => function ($id) {
                        $data = $this->model->get_category($id);
                        return $data;
                    },
                    'get_category_lang'              => function ($id, $lang) {
                        $data = $this->model->get_category_wlang($id, $lang);
                        return $data;
                    },
                ]);


                $this->addData("categories", $this->model->get_select_categories("special", $group["id"], false, $group["id"]));

                $this->addData("addon_categories", $this->model->get_addon_categories([], [], 0, 1000));
                $this->addData("requirement_categories", $this->model->get_requirement_categories([], [], 0, 1000));

                $this->addData("prices", $this->model->get_prices("periodicals", "products", $product["id"]));

                Helper::Load("Money");

                $product_folder = Config::get("pictures/products/folder");

                $orderimg_picture = $this->model->get_picture("product", $product["id"], "order");
                if ($orderimg_picture)
                    $orderimg_picture = Utility::image_link_determiner($orderimg_picture, $product_folder);
                $orderimgDeft = Utility::image_link_determiner("order-image-default.jpg", $product_folder);
                $this->addData("getOrderImageDeft", $orderimgDeft);
                $this->addData("getOrderImage", $orderimg_picture);

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

                $upgradeable_products = [];

                $ps = $this->model->upgradeable_products('special-' . $group['id'], $group['id'], $product['id']);
                if ($ps)
                    $upgradeable_products[] = [
                        'id'       => 0,
                        'title'    => ___("needs/uncategorized"),
                        'products' => $ps,
                    ];

                if ($this->getData("categories")) {
                    foreach ($this->getData("categories") as $c) {
                        $ps = $this->model->upgradeable_products('special-' . $group['id'], $c['id'], $product['id']);
                        if ($ps) {
                            $c['products'] = $ps;
                            $upgradeable_products[] = $c;
                        }
                    }
                }

                $this->addData("upgradeable_products", $upgradeable_products);


                $this->view->chose("admin")->render("edit-special", $this->data);

                die();
            } elseif ($pname == "add") {
                $links["controller"] = $links["add-new-product"];

                $this->addData("links", $links);

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

                $this->addData("meta", [
                    'title' => Utility::text_replace(__("admin/products/meta-add-special/title"), [
                        '{group-name}' => $group["title"],
                    ]),
                ]);

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["special-" . $group["id"]]),
                        'title' => __("admin/products/breadcrumb-special-list", [
                            '{group-name}' => $group["title"],
                        ]),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-add-special", [
                            '{group-name}' => $group["title"],
                        ]),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("group", $group);

                $GLOBALS["group"] = $group;

                $this->addData("functions", [
                    'get_addons_with_category'       => function ($category = 0) {
                        return $this->model->get_addons_with_category("special_" . $GLOBALS["group"]["id"], $category);
                    },
                    'get_requirements_with_category' => function ($category = 0) {
                        return $this->model->get_requirements_with_category("special_" . $GLOBALS["group"]["id"], $category);
                    },
                    'get_category_lang'              => function ($id, $lang) {
                        $data = $this->model->get_category_wlang($id, $lang);
                        return $data;
                    },
                ]);

                $this->addData("categories", $this->model->get_select_categories("special", $group["id"], false, $group["id"]));

                $this->addData("addon_categories", $this->model->get_addon_categories([], [], 0, 1000));
                $this->addData("requirement_categories", $this->model->get_requirement_categories([], [], 0, 1000));

                Helper::Load("Money");

                $product_folder = Config::get("pictures/products/folder");

                $orderimgDeft = Utility::image_link_determiner("order-image-default.jpg", $product_folder);
                $this->addData("getOrderImageDeft", $orderimgDeft);

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

                $upgradeable_products = [];

                $ps = $this->model->upgradeable_products('special-' . $group['id'], $group['id']);
                if ($ps)
                    $upgradeable_products[] = [
                        'id'       => 0,
                        'title'    => ___("needs/uncategorized"),
                        'products' => $ps,
                    ];

                if ($this->getData("categories")) {
                    foreach ($this->getData("categories") as $c) {
                        $ps = $this->model->upgradeable_products('special-' . $group['id'], $c['id']);
                        if ($ps) {
                            $c['products'] = $ps;
                            $upgradeable_products[] = $c;
                        }
                    }
                }

                $this->addData("upgradeable_products", $upgradeable_products);


                $this->view->chose("admin")->render("add-special", $this->data);

                die();
            } elseif ($pname == "edit-category") {

                $id = (int)Filter::init("GET/id", "numbers");
                if (!$id) die();

                $category = $this->model->get_category($id);
                if (!$category) die();

                $GLOBALS["category"] = $category;


                $this->addData("cat", $category);

                $links["controller"] = $links["edit-category"] . "?id=" . $id;

                $this->addData("links", $links);

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

                $this->addData("meta", [
                    'title' => Utility::text_replace(__("admin/products/meta-edit-special-category/title"), [
                        '{group-name}' => $group["title"],
                    ]),
                ]);

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["special-" . $group["id"]]),
                        'title' => __("admin/products/breadcrumb-special-list", [
                            '{group-name}' => $group["title"],
                        ]),
                    ],
                    [
                        'link'  => $links["product-categories"],
                        'title' => __("admin/products/breadcrumb-special-categories", [
                            '{group-name}' => $group["title"],
                        ]),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-edit-server-category", [
                            '{name}'       => $category["title"],
                            '{group-name}' => $group["title"],
                        ]),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);
                $this->addData("group", $group);

                $this->addData("functions", [
                    'get_category_with_lang' => function ($lang) {
                        $data = $this->model->get_category_wlang($GLOBALS["category"]["id"], $lang);
                        return $data;
                    },
                ]);

                $header_folder = Config::get("pictures/header-background/folder");
                $icon_folder = Config::get("pictures/category-icon/folder");

                $header_picture = $this->model->get_picture("category", $category["id"], "header-background");
                if ($header_picture)
                    $header_picture = Utility::image_link_determiner($header_picture, $header_folder . "thumb" . DS);
                $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
                $this->addData("getHeaderBackground", $header_picture);
                $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);


                $icon_picture = $this->model->get_picture("category", $category["id"], "icon");
                if ($icon_picture)
                    $icon_picture = Utility::image_link_determiner($icon_picture, $icon_folder);
                $icon_pictureDeft = Utility::image_link_determiner("default.jpg", $icon_folder);
                $this->addData("getIconImage", $icon_picture);
                $this->addData("getIconImageDeft", $icon_pictureDeft);


                $this->addData("categories", $this->model->get_select_categories("special", $group["id"], false, $group["id"]));

                $this->view->chose("admin")->render("edit-special-category", $this->data);

                die();

            } elseif ($pname == "add-category") {

                $links["controller"] = $links["add-new-category"];

                $this->addData("links", $links);

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

                $this->addData("meta", [
                    'title' => Utility::text_replace(__("admin/products/meta-add-special-category/title"), [
                        '{group-name}' => $group["title"],
                    ]),
                ]);

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["special-" . $group["id"]]),
                        'title' => __("admin/products/breadcrumb-special-list", [
                            '{group-name}' => $group["title"],
                        ]),
                    ],
                    [
                        'link'  => $links["product-categories"],
                        'title' => __("admin/products/breadcrumb-special-categories", [
                            '{group-name}' => $group["title"],
                        ]),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-add-special-category", [
                            '{group-name}' => $group["title"],
                        ]),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("group", $group);

                $this->addData("functions", []);

                $header_folder = Config::get("pictures/header-background/folder");
                $icon_folder = Config::get("pictures/category-icon/folder");

                $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
                $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);

                $icon_pictureDeft = Utility::image_link_determiner("default.jpg", $icon_folder);
                $this->addData("getIconImageDeft", $icon_pictureDeft);

                $this->addData("categories", $this->model->get_select_categories("special", $group["id"], false, $group["id"]));
                $this->view->chose("admin")->render("add-special-category", $this->data);

                die();
            } elseif ($pname == "categories") {

                $links["controller"] = $links["product-categories"];
                $this->addData("links", $links);

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

                $this->addData("meta", [
                    'title' => Utility::text_replace(__("admin/products/meta-special-categories/title"), [
                        '{group-name}' => $group["title"],
                    ]),
                ]);

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["special-" . $group["id"]]),
                        'title' => __("admin/products/breadcrumb-special-list", [
                            '{group-name}' => $group["title"],
                        ]),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-special-categories", [
                            '{group-name}' => $group["title"],
                        ]),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("settings", [

                ]);

                $this->addData("functions", []);

                $this->addData("group", $group);

                $this->view->chose("admin")->render("special-categories", $this->data);

                die();
            } elseif ($pname == "product-list.json") {

                $limit = 10;
                $output = [];
                $aColumns = array('', 'name', '', '', 'rank', '');

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

                $filteredList = $this->model->get_products("special", $searches, $orders, $start, $end, $group["id"]);
                $filterTotal = $this->model->get_products_total("special", $searches, $group["id"]);
                $listTotal = $this->model->get_products_total("special", false, $group["id"]);

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {
                    Helper::Load("Money");

                    $privOperation = Admin::isPrivilege("PRODUCTS_OPERATION");
                    $privGroupLook = Admin::isPrivilege("PRODUCTS_GROUP_LOOK");

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $situations = $situations["products"];

                    $i = 0;
                    if ($filteredList) {
                        foreach ($filteredList as $row) {
                            $i++;
                            $item = [];
                            $show_category = ___("needs/none");
                            if ($row["category"] && $row["category_id"] != $group["id"]) {
                                $show_category = $row["category"] . ' <a href="' . $this->CRLink("products", [$row["category_route"]]) . '" target="_blank" class="targeturl sbtn"><i class="fa fa-external-link" aria-hidden="true"></i></a>';
                            }

                            array_push($item, $i);

                            $id = $row["id"];

                            array_push($item, '<input type="checkbox" onchange="if($(\'.selected-item:not(:checked)\').length==0) $(\'#allSelect\').prop(\'checked\',true); else $(\'#allSelect\').prop(\'checked\',false);" class="checkbox-custom selected-item" id="product-' . $id . '-select" value="' . $id . '"><label for="product-' . $id . '-select" class="checkbox-custom-label"></label>');

                            array_push($item, $row["name"]);
                            array_push($item, $show_category);
                            array_push($item, Money::formatter_symbol($row["amount"], $row["cid"]));
                            array_push($item, $situations[$row["status"]]);
                            $opeations = '<a href="' . $links["edit-product"] . '?id=' . $row["id"] . '" class="sbtn" data-tooltip="' . ___("needs/button-edit") . '"><i class="fa fa-edit"></i></a>';
                            if ($privOperation) {
                                $opeations .= ' <a href="javascript:copyProduct(' . $row["id"] . ');" data-tooltip="' . ___("needs/copy") . '" class="blue sbtn" id="copy_' . $row["id"] . '"><i class="fa fa-copy" aria-hidden="true"></i></a> ';
                                $opeations .= ' <a href="javascript:deleteProduct(' . $row["id"] . ',\'' . $row["name"] . '\');" data-tooltip="' . __("admin/products/delete") . '" class="red sbtn" id="delete_' . $row["id"] . '"><i class="fa fa-trash-o" aria-hidden="true"></i></a>';
                            }
                            array_push($item, $opeations);
                            $output["aaData"][] = $item;
                        }
                    }
                }

                die(Utility::jencode($output));
            } elseif ($pname == "category-list.json") {

                $limit = 10;
                $output = [];
                $aColumns = array('', '', '', 'rank', '');

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

                $filteredList = $this->model->get_categories("special", $searches, $orders, $start, $end, $group["id"]);
                $filterTotal = $this->model->get_categories_total("special", $searches, $group["id"]);
                $listTotal = $this->model->get_categories_total("special", false, $group["id"]);

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {
                    Helper::Load("Money");

                    $privGroupOperation = Admin::isPrivilege("PRODUCTS_GROUP_OPERATION");
                    $privGroupLook = Admin::isPrivilege("PRODUCTS_GROUP_LOOK");

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $situations = $situations["categories"];

                    $i = 0;
                    if ($filteredList) {
                        foreach ($filteredList as $row) {
                            $i++;
                            $item = [];
                            $catLink = $this->CRLink("products", [$row["route"]]);
                            $catLink = ' <a href="' . $catLink . '" target="_blank" class="targeturl sbtn"><i class="fa fa-external-link" aria-hidden="true"></i></a>';
                            if ($row["parent"] != $group["id"] && $row["parent_route"]) {
                                $parent_catLink = $this->CRLink("products", [$row["parent_route"]]);
                                $parent_catLink = $row["parent_name"] . ' <a href="' . $parent_catLink . '" target="_blank" class="targeturl sbtn"><i class="fa fa-external-link" aria-hidden="true"></i></a>';
                            } else {
                                $parent_catLink = ___("needs/none");
                            }

                            array_push($item, $i);
                            array_push($item, $row["name"] . $catLink);
                            array_push($item, $parent_catLink);
                            array_push($item, $situations[$row["status"]]);
                            $opeations = '<a href="' . $links["edit-category"] . '?id=' . $row["id"] . '" class="sbtn"><i class="fa fa-edit"></i></a>';
                            if ($privGroupOperation)
                                $opeations .= ' <a href="javascript:deleteCategory(' . $row["id"] . ',\'' . $row["name"] . '\');" title="' . __("admin/products/delete") . '" class="red sbtn" id="delete_' . $row["id"] . '"><i class="fa fa-trash-o" aria-hidden="true"></i></a>';
                            array_push($item, $opeations);
                            $output["aaData"][] = $item;
                        }
                    }
                }

                die(Utility::jencode($output));
            }

            $this->addData("group", $group);

            $links["controller"] = $this->AdminCRLink("products", ["special-" . $group["id"]]);
            $this->addData("links", $links);

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

            $this->addData("meta", [
                'title' => Utility::text_replace(__("admin/products/meta-special-list/title"), [
                    '{group-name}' => $group["title"],
                ]),
            ]);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/products/breadcrumb-special-list", ['{group-name}' => $group["title"]]),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $this->view->chose("admin")->render("special-list", $this->data);
        }


        private function select_server_modules()
        {
            return Modules::Load("Servers", "All");
        }


        private function hosting($pname = false)
        {
            $links = [
                'settings'                  => $this->AdminCRLink("products-2", ["hosting", "settings"]),
                'add-new-product'           => $this->AdminCRLink("products-2", ['hosting', 'add']),
                'add-new-category'          => $this->AdminCRLink("products-2", ['hosting', 'add-category']),
                'edit-product'              => $this->AdminCRLink("products-2", ['hosting', 'edit']),
                'edit-category'             => $this->AdminCRLink("products-2", ['hosting', 'edit-category']),
                'hosting-group-redirect'    => $this->CRLink("products", ['hosting']),
                'product-categories'        => $this->AdminCRLink("products-2", ['hosting', 'categories']),
                'shared-servers'            => $this->AdminCRLink("products-2", ['hosting', 'shared-servers']),
                'shared-server-root-login'  => $this->AdminCRLink("products-2", ['hosting', 'shared-server-root-login']),
                'add-shared-server-group'   => $this->AdminCRLink("products-2", ['hosting', 'add-shared-server-group']),
                'shared-server-groups'      => $this->AdminCRLink("products-2", ['hosting', 'shared-server-groups']),
                'edit-shared-server-group'  => $this->AdminCRLink("products-2", ['hosting', 'edit-shared-server-group']),
                'add-shared-server'         => $this->AdminCRLink("products-2", ['hosting', 'add-shared-server']),
                'edit-shared-server'        => $this->AdminCRLink("products-2", ['hosting', 'edit-shared-server']),
                'ajax-shared-server-list'   => $this->AdminCRLink("products-2", ['hosting', 'shared-servers.json']),
                'ajax-shared-server-groups' => $this->AdminCRLink("products-2", ['hosting', 'shared-server-groups.json']),
                'ajax-product-list'         => $this->AdminCRLink("products-2", ['hosting', 'product-list.json']),
                'ajax-category-list'        => $this->AdminCRLink("products-2", ['hosting', 'category-list.json']),
                'add-new-addon'             => $this->AdminCRLink("products-2", ['addons', 'add']),
                'add-new-requirement'       => $this->AdminCRLink("products-2", ['requirements', 'add']),
            ];

            Helper::Load("Money");

            if ($pname == "settings") {
                $links["controller"] = $links["settings"];
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-hosting-settings"));
                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["hosting"]),
                        'title' => __("admin/products/breadcrumb-hosting-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-hosting-settings"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);


                $this->addData("functions", []);

                $icon_folder = Config::get("pictures/category-icon/folder");

                $icon_picture = $this->model->get_picture("category", 1, "icon");
                if ($icon_picture)
                    $icon_picture = Utility::image_link_determiner($icon_picture, $icon_folder);
                $icon_pictureDeft = Utility::image_link_determiner("default.jpg", $icon_folder);
                $this->addData("getIconImage", $icon_picture);
                $this->addData("getIconImageDeft", $icon_pictureDeft);


                $this->view->chose("admin")->render("hosting-settings", $this->data);

                die();
            } elseif ($pname == "shared-servers" && Admin::isPrivilege("MODULES_SERVERS_SETTINGS")) {

                $links["controller"] = $links["shared-servers"];
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-hosting-shared-servers"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-hosting-shared-servers"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);


                $this->addData("functions", []);

                $this->view->chose("admin")->render("hosting-shared-servers", $this->data);

                die();
            } elseif ($pname == "add-shared-server" && Admin::isPrivilege("MODULES_SERVERS_SETTINGS")) {

                $links["controller"] = $links["add-shared-server"];

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-add-hosting-shared-server"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products-2", ["hosting", "shared-servers"]),
                        'title' => __("admin/products/breadcrumb-hosting-shared-servers"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-add-hosting-shared-server"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("server_modules", $this->select_server_modules());


                $this->view->chose("admin")->render("add-hosting-shared-server", $this->data);

                die();
            } elseif ($pname == "edit-shared-server" && Admin::isPrivilege("MODULES_SERVERS_SETTINGS")) {

                $id = (int)Filter::init("GET/id", "numbers");
                if (!$id) die();

                $server = $this->model->get_shared_server($id);
                if (!$server) die();


                $links["controller"] = $links["edit-shared-server"] . "?id=" . $id;
                $links["select-users.json"] = $this->AdminCRLink("orders") . "?operation=user-list.json";

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-edit-hosting-shared-server"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products-2", ["hosting", "shared-servers"]),
                        'title' => __("admin/products/breadcrumb-hosting-shared-servers"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-edit-hosting-shared-server", [
                            '{name}' => $server["name"],
                        ]),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("server_modules", $this->select_server_modules());

                $this->addData("server", $server);

                Helper::Load(["Products", "Money", "Orders"]);

                $module_name = $server["type"];
                $selectorModule = $module_name . "_Module";
                if (class_exists($selectorModule)) {
                    $server_pw = Crypt::decode($server["password"], Config::get("crypt/user"));
                    $serverInfo = $server;
                    $serverInfo["password"] = $server_pw;
                    $module = new $selectorModule($serverInfo);

                    $this->addData("module", $module);
                }

                $import = isset($module) && (method_exists($module, "listAccounts") || method_exists($module, "list_vps"));


                $this->addData("import_support", $import);

                if (Filter::GET("list") == "true" && $import) {

                    if ($module->config["type"] == "virtualization") {

                        $list_vps = $module->list_vps();
                        if ($list_vps) {
                            $keys = array_keys($list_vps);
                            $size = sizeof($keys) - 1;
                            for ($i = 0; $i <= $size; $i++) {
                                $row = $list_vps[$keys[$i]];
                                if ($sync = $this->model->sync_server($server, $row["sync_terms"])) {
                                    $user_data = User::getData($sync["user_id"], "id,full_name", "array");
                                    $user_data = array_merge($user_data, User::getInfo($sync["user_id"], ["company_name"]));
                                    $list_vps[$keys[$i]]["user_data"] = $user_data;
                                    $list_vps[$keys[$i]]["order_data"] = $sync;
                                }
                            }
                        }

                        if (!$list_vps) $this->addData("module_error", $module->error);

                        $this->addData("list_vps", $list_vps);

                        $products = $this->model->get_server_categories($server);
                        if (!$products) $products = [];

                        array_unshift($products, [
                            'title' => ___("needs/uncategorized"),
                            'items' => $this->model->get_category_server_products(0, $server),
                        ]);

                        $this->addData("products", $products);
                    } else {
                        $listAccounts = $module->listAccounts();
                        if ($listAccounts) {
                            $keys = array_keys($listAccounts);
                            $size = sizeof($keys) - 1;
                            for ($i = 0; $i <= $size; $i++) {
                                $row = $listAccounts[$keys[$i]];
                                if ($sync = $this->model->sync_hosting($row["domain"], $row["username"], $server)) {
                                    $user_data = User::getData($sync["user_id"], "id,full_name", "array");
                                    $user_data = array_merge($user_data, User::getInfo($sync["user_id"], ["company_name"]));
                                    $listAccounts[$keys[$i]]["user_data"] = $user_data;
                                    $listAccounts[$keys[$i]]["order_data"] = $sync;
                                }
                            }
                        }

                        if (!$listAccounts) $this->addData("module_error", $module->error);

                        $this->addData("listAccounts", $listAccounts);

                        $products = $this->model->get_hosting_categories($server);
                        if (!$products) $products = [];

                        array_unshift($products, [
                            'title' => ___("needs/uncategorized"),
                            'items' => $this->model->get_category_hosting_products(0, $server),
                        ]);

                        $this->addData("products", $products);
                    }
                }

                $case = "CASE ";
                $case .= "WHEN usp.status = 'waiting' THEN 0 ";
                $case .= "WHEN usp.status_msg != '' THEN 0 ";
                $case .= "WHEN usp.status = 'inprocess' THEN 1 ";
                $case .= "WHEN usp.status = 'active' THEN 2 ";
                $case .= "WHEN usp.status = 'suspended' THEN 3 ";
                $case .= "WHEN usp.status = 'cancelled' THEN 4 ";
                $case .= "ELSE 5 ";
                $case .= "END AS rank";

                $services = $this->model->db->select("usp.id,usp.name,usp.type,usp.product_id,usp.owner_id,usr.full_name AS user_full_name,JSON_UNQUOTE(JSON_EXTRACT(usp.options,'$.domain')) AS domain,JSON_UNQUOTE(JSON_EXTRACT(usp.options,'$.hostname')) AS hostname,JSON_UNQUOTE(JSON_EXTRACT(usp.options,'$.ip')) AS ip," . $case)->from("users_products AS usp");
                $services->join("LEFT", "users AS usr", "usr.id=usp.owner_id");
                $services->where("JSON_UNQUOTE(JSON_EXTRACT(usp.options,'$.server_id'))", "=", $server["id"], "&&");
                $services->where("usp.status", "!=", "cancelled");
                $services->order_by("rank ASC,usp.renewaldate DESC");
                $services = $services->build() ? $services->fetch_assoc() : [];

                $this->addData("services", $services);


                $used = Orders::linked_server_count($id);

                $g_cid = Config::get("general/currency");

                if ($server["cost_price"] > 0) {
                    $cost_amount = $server["cost_price"];
                    $cost_cid = $server["cost_currency"];
                    $cost_price = Money::exChange($cost_amount, $cost_cid, $g_cid);
                    $sales_total = 0;

                    $service_amounts = $this->model->linked_service_price_for_server($id);

                    if ($service_amounts) {
                        foreach ($service_amounts as $sa) $sales_total += Money::exChange($sa["amount"], $sa["currency"], $g_cid);
                    }

                    $sales_total = $sales_total / 12;

                    $this->addData("monthly_total", Money::formatter_symbol($sales_total, $g_cid));
                    $this->addData("net_total", Money::formatter_symbol($sales_total - $cost_price, $g_cid));
                }

                $this->addData("zero_amount", Money::formatter_symbol(0, $g_cid));

                $this->addData("used", $used);


                if (isset($server["maxaccounts"]) && $server["maxaccounts"] > 0)
                    $this->addData("remaining", $server["maxaccounts"] - $used);


                $this->view->chose("admin")->render("edit-hosting-shared-server", $this->data);

                die();
            } elseif ($pname == "shared-server-groups" && Admin::isPrivilege("MODULES_SERVERS_SETTINGS")) {

                $links["controller"] = $links["shared-server-groups"];
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-hosting-shared-server-groups"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $links["shared-servers"],
                        'title' => __("admin/products/breadcrumb-hosting-shared-servers"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-hosting-shared-server-groups"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);


                $this->addData("functions", []);

                $this->view->chose("admin")->render("hosting-shared-server-groups", $this->data);

                die();
            } elseif ($pname == "add-shared-server-group" && Admin::isPrivilege("MODULES_SERVERS_SETTINGS")) {

                $links["controller"] = $links["add-shared-server-group"];

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-add-hosting-shared-server-group"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products-2", ["hosting", "shared-servers"]),
                        'title' => __("admin/products/breadcrumb-hosting-shared-servers"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products-2", ["hosting", "shared-server-groups"]),
                        'title' => __("admin/products/breadcrumb-hosting-shared-server-groups"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-add-hosting-shared-server-group"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("servers", $this->model->get_shared_server_list(false, false, 0, 1000));


                $this->view->chose("admin")->render("add-hosting-shared-server-group", $this->data);

                die();
            } elseif ($pname == "edit-shared-server-group" && Admin::isPrivilege("MODULES_SERVERS_SETTINGS")) {

                $id = (int)Filter::init("GET/id", "numbers");
                if (!$id) die();

                $server = $this->model->get_shared_server_group($id);
                if (!$server) die();


                $links["controller"] = $links["edit-shared-server-group"] . "?id=" . $id;
                $links["select-users.json"] = $this->AdminCRLink("orders") . "?operation=user-list.json";

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-edit-hosting-shared-server-group"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products-2", ["hosting", "shared-servers"]),
                        'title' => __("admin/products/breadcrumb-hosting-shared-servers"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products-2", ["hosting", "shared-server-groups"]),
                        'title' => __("admin/products/breadcrumb-hosting-shared-server-groups"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-edit-hosting-shared-server-group", [
                            '{name}' => $server["name"],
                        ]),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("group", $server);

                Helper::Load(["Products", "Money"]);

                $this->addData("servers", $this->model->get_shared_server_list(false, false, 0, 1000));

                $this->view->chose("admin")->render("edit-hosting-shared-server-group", $this->data);

                die();
            } elseif ($pname == "shared-server-root-login" && Admin::isPrivilege("MODULES_SERVERS_SETTINGS")) {

                $rlb = Admin::isPrivilege(["MODULES_ROOT_LOGIN_BUTTON"]);

                if (!$rlb) die("You do not have permission for this action");

                $id = (int)Filter::init("GET/id", "numbers");
                if (!$id) die();

                $server = Products::get_server($id);
                if (!$server) die();

                Helper::Load(["Products"]);

                $module_name = $server["type"];

                Modules::Load("Servers", $module_name);

                $selectorModule = $module_name . "_Module";
                if (!class_exists($selectorModule)) die($selectorModule);
                $module = new $selectorModule($server);

                if (!method_exists($module, 'use_adminArea_root_SingleSignOn')) return false;

                $adata = UserManager::LoginData("admin");
                User::addAction($adata['id'], 'accessed', 'root-panel-accessed', [
                    'id'   => $server['id'],
                    'ip'   => $server["ip"],
                    'type' => $server["type"],
                    'name' => $server["name"],
                ]);

                $module->use_method("root_SingleSignOn");

                die();
            } elseif ($pname == "shared-servers.json") {

                $limit = 10;
                $output = [];
                $aColumns = array('', 'name', '', '', 'rank', '');

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

                $filteredList = $this->model->get_shared_server_list($searches, $orders, $start, $end);
                $filterTotal = $this->model->get_shared_server_list_total($searches);
                $listTotal = $this->model->get_shared_server_list_total();

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {
                    Helper::Load(["Money", "Orders"]);

                    $privOperation = Admin::isPrivilege("PRODUCTS_OPERATION");
                    $privGroupLook = Admin::isPrivilege("PRODUCTS_GROUP_LOOK");

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $situations = $situations["shared-servers"];

                    $i = 0;
                    if ($filteredList) {
                        foreach ($filteredList as $row) {
                            $i++;
                            $item = [];

                            $stats = "&#8734;";

                            $used = Orders::linked_server_count($row["id"]);

                            if ($row["maxaccounts"] > 0) {
                                $cal = round(($used / $row["maxaccounts"]) * 100);
                                if ($cal > 100) $cal = 100;
                                $percentage = $used > 0 ? $cal : 0;
                                $stats = $used . "/" . $row["maxaccounts"] . " (" . $percentage . "%)";
                            }


                            $perms = '';

                            $perms .= '<a href="' . $links["edit-shared-server"] . '?id=' . $row["id"] . '" class="sbtn"><i class="fa fa-edit"></i></a>';
                            if ($privOperation)
                                $perms .= ' <a href="javascript:deleteServer(' . $row["id"] . ',\'' . $row["name"] . '\');" title="' . __("admin/products/delete") . '" class="red sbtn" id="delete_' . $row["id"] . '"><i class="fa fa-trash-o" aria-hidden="true"></i></a>';


                            array_push($item, $i);
                            array_push($item, $row["name"]);
                            array_push($item, $row["ip"]);
                            array_push($item, $stats);
                            array_push($item, $row["type"]);
                            array_push($item, $situations[$row["status"]]);
                            array_push($item, $perms);
                            $output["aaData"][] = $item;
                        }
                    }
                }

                die(Utility::jencode($output));
            } elseif ($pname == "shared-server-groups.json") {

                $limit = 10;
                $output = [];
                $aColumns = array('', 'name', '', '', 'rank', '');

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

                $filteredList = $this->model->get_shared_server_groups($searches, $orders, $start, $end);
                $filterTotal = $this->model->get_shared_server_groups_total($searches);
                $listTotal = $this->model->get_shared_server_groups_total();

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {
                    Helper::Load("Money");

                    $privOperation = Admin::isPrivilege("PRODUCTS_OPERATION");
                    $privGroupLook = Admin::isPrivilege("PRODUCTS_GROUP_LOOK");

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $situations = $situations["shared-servers"];

                    $i = 0;
                    if ($filteredList) {
                        foreach ($filteredList as $row) {
                            $i++;
                            $item = [];

                            $perms = '';

                            $perms .= '<a href="' . $links["edit-shared-server-group"] . '?id=' . $row["id"] . '" class="sbtn"><i class="fa fa-edit"></i></a>';
                            if ($privOperation)
                                $perms .= ' <a href="javascript:deleteGroup(' . $row["id"] . ');" title="' . __("admin/products/delete") . '" class="red sbtn" id="delete_' . $row["id"] . '"><i class="fa fa-trash-o" aria-hidden="true"></i></a>';

                            $servers = $row["servers"];

                            if ($servers) {
                                $servers = explode(",", $row["servers"]);
                                $servers = sizeof($servers);
                            } else
                                $servers = 0;

                            array_push($item, $i);
                            array_push($item, $row["name"]);
                            array_push($item, $servers);
                            array_push($item, __("admin/products/shared-server-tx5-" . $row["fill_type"]));
                            array_push($item, $perms);
                            $output["aaData"][] = $item;
                        }
                    }
                }

                die(Utility::jencode($output));
            } elseif ($pname == "edit") {

                $id = (int)Filter::init("GET/id", "numbers");
                if (!$id) die();

                $product = $this->model->get_product($id);
                if (!$product) die();

                $this->statistics_extract('hosting', $product);

                $GLOBALS["product"] = $product;

                $this->addData("product", $product);

                $links["controller"] = $links["edit-product"] . "?id=" . $id;

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-edit-hosting"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["hosting"]),
                        'title' => __("admin/products/breadcrumb-hosting-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-edit-hosting", ['{name}' => $product["title"]]),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", [
                    'get_addons_with_category'       => function ($category = 0) {
                        return $this->model->get_addons_with_category("hosting", $category);
                    },
                    'get_product_with_lang'          => function ($lang) {
                        return $this->model->get_product_wlang($GLOBALS["product"]["id"], $lang);
                    },
                    'get_requirements_with_category' => function ($category = 0) {
                        return $this->model->get_requirements_with_category("hosting", $category);
                    },

                ]);

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

                $this->addData("categories", $this->model->get_select_categories("hosting"));

                $this->addData("addon_categories", $this->model->get_addon_categories([], [], 0, 1000));
                $this->addData("requirement_categories", $this->model->get_requirement_categories([], [], 0, 1000));

                $this->addData("prices", $this->model->get_prices("periodicals", "products", $product["id"]));

                Helper::Load("Money");

                $upgradeable_products = [];

                $ps = $this->model->upgradeable_products('hosting', 0, $product['id']);
                if ($ps)
                    $upgradeable_products[] = [
                        'id'       => 0,
                        'title'    => ___("needs/uncategorized"),
                        'products' => $ps,
                    ];

                if ($this->getData("categories")) {
                    foreach ($this->getData("categories") as $c) {
                        $ps = $this->model->upgradeable_products('hosting', $c['id'], $product['id']);
                        if ($ps) {
                            $c['products'] = $ps;
                            $upgradeable_products[] = $c;
                        }
                    }
                }

                $this->addData("upgradeable_products", $upgradeable_products);

                $this->addData("shared_server_groups", $this->model->select_shared_server_groups('hosting'));


                $this->view->chose("admin")->render("edit-hosting", $this->data);

                die();
            } elseif ($pname == "add") {
                $links["controller"] = $links["add-new-product"];

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-add-hosting"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["hosting"]),
                        'title' => __("admin/products/breadcrumb-hosting-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-add-hosting"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", [
                    'get_addons_with_category'       => function ($category = 0) {
                        return $this->model->get_addons_with_category("hosting", $category);
                    },
                    'get_requirements_with_category' => function ($category = 0) {
                        return $this->model->get_requirements_with_category("hosting", $category);
                    },
                ]);

                $shared_servers = $this->model->get_shared_servers();
                if ($shared_servers) {
                    $modules = Modules::Load("Servers");
                    $hosting = [];
                    if ($modules)
                        foreach ($modules as $module_name => $module)
                            if ($module["config"]["type"] == "hosting")
                                $hosting[] = $module_name;
                    $servers = [];
                    foreach ($shared_servers as $server) if (in_array($server["type"], $hosting)) $servers[$server["id"]] = $server;
                    $this->addData("shared_servers", $servers);

                    $this->addData("shared_server_groups", $this->model->select_shared_server_groups('hosting'));
                }

                $this->addData("categories", $this->model->get_select_categories("hosting"));

                $this->addData("addon_categories", $this->model->get_addon_categories([], [], 0, 1000));
                $this->addData("requirement_categories", $this->model->get_requirement_categories([], [], 0, 1000));

                Helper::Load("Money");

                $upgradeable_products = [];

                $ps = $this->model->upgradeable_products('hosting', 0);
                if ($ps)
                    $upgradeable_products[] = [
                        'id'       => 0,
                        'title'    => ___("needs/uncategorized"),
                        'products' => $ps,
                    ];

                if ($this->getData("categories")) {
                    foreach ($this->getData("categories") as $c) {
                        $ps = $this->model->upgradeable_products('hosting', $c['id']);
                        if ($ps) {
                            $c['products'] = $ps;
                            $upgradeable_products[] = $c;
                        }
                    }
                }

                $this->addData("upgradeable_products", $upgradeable_products);

                $this->view->chose("admin")->render("add-hosting", $this->data);

                die();
            } elseif ($pname == "edit-category") {

                $id = (int)Filter::init("GET/id", "numbers");
                if (!$id) die();

                $category = $this->model->get_category($id);
                if (!$category) die();

                $GLOBALS["category"] = $category;

                $links["controller"] = $links["edit-category"] . "?id=" . $id;

                $this->addData("cat", $category);

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-edit-hosting-category"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["hosting"]),
                        'title' => __("admin/products/breadcrumb-hosting-list"),
                    ],
                    [
                        'link'  => $links["product-categories"],
                        'title' => __("admin/products/breadcrumb-hosting-categories"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-edit-hosting-category", ['{name}' => $category["title"]]),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", [
                    'get_category_with_lang' => function ($lang) {
                        $data = $this->model->get_category_wlang($GLOBALS["category"]["id"], $lang);
                        return $data;
                    },
                ]);

                $header_folder = Config::get("pictures/header-background/folder");
                $icon_folder = Config::get("pictures/category-icon/folder");

                $header_picture = $this->model->get_picture("category", $category["id"], "header-background");
                if ($header_picture)
                    $header_picture = Utility::image_link_determiner($header_picture, $header_folder . "thumb" . DS);
                $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
                $this->addData("getHeaderBackground", $header_picture);
                $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);


                $icon_picture = $this->model->get_picture("category", $category["id"], "icon");
                if ($icon_picture)
                    $icon_picture = Utility::image_link_determiner($icon_picture, $icon_folder);
                $icon_pictureDeft = Utility::image_link_determiner("default.jpg", $icon_folder);
                $this->addData("getIconImage", $icon_picture);
                $this->addData("getIconImageDeft", $icon_pictureDeft);


                $this->addData("categories", $this->model->get_select_categories("hosting"));

                $this->view->chose("admin")->render("edit-hosting-category", $this->data);

                die();

            } elseif ($pname == "add-category") {

                $links["controller"] = $links["add-new-category"];

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-add-hosting-category"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["hosting"]),
                        'title' => __("admin/products/breadcrumb-hosting-list"),
                    ],
                    [
                        'link'  => $links["product-categories"],
                        'title' => __("admin/products/breadcrumb-hosting-categories"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-add-hosting-category"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", []);

                $header_folder = Config::get("pictures/header-background/folder");
                $icon_folder = Config::get("pictures/category-icon/folder");

                $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
                $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);

                $icon_pictureDeft = Utility::image_link_determiner("default.jpg", $icon_folder);
                $this->addData("getIconImageDeft", $icon_pictureDeft);

                $this->addData("categories", $this->model->get_select_categories("hosting"));

                $this->view->chose("admin")->render("add-hosting-category", $this->data);

                die();
            } elseif ($pname == "categories") {

                $links["controller"] = $links["product-categories"];
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-hosting-categories"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["hosting"]),
                        'title' => __("admin/products/breadcrumb-hosting-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-hosting-categories"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("settings", [

                ]);

                $this->addData("functions", []);

                $this->view->chose("admin")->render("hosting-categories", $this->data);

                die();
            } elseif ($pname == "product-list.json") {

                $limit = 10;
                $output = [];
                $aColumns = array('', 'name', '', '', 'rank', '');

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

                $filteredList = $this->model->get_products("hosting", $searches, $orders, $start, $end);
                $filterTotal = $this->model->get_products_total("hosting", $searches);
                $listTotal = $this->model->get_products_total("hosting");

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {
                    Helper::Load("Money");

                    $privOperation = Admin::isPrivilege("PRODUCTS_OPERATION");
                    $privGroupLook = Admin::isPrivilege("PRODUCTS_GROUP_LOOK");

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $situations = $situations["products"];

                    $i = 0;
                    if ($filteredList) {
                        foreach ($filteredList as $row) {
                            $i++;
                            $item = [];
                            $show_category = ___("needs/none");
                            if ($row["category"]) {
                                $show_category = $row["category"] . ' <a href="' . $this->CRLink("products", [$row["category_route"]]) . '" target="_blank" class="targeturl sbtn"><i class="fa fa-external-link" aria-hidden="true"></i></a>';
                            }

                            array_push($item, $i);

                            $id = $row["id"];

                            array_push($item, '<input type="checkbox" onchange="if($(\'.selected-item:not(:checked)\').length==0) $(\'#allSelect\').prop(\'checked\',true); else $(\'#allSelect\').prop(\'checked\',false);" class="checkbox-custom selected-item" id="product-' . $id . '-select" value="' . $id . '"><label for="product-' . $id . '-select" class="checkbox-custom-label"></label>');

                            array_push($item, $row["name"]);
                            array_push($item, $show_category);
                            array_push($item, Money::formatter_symbol($row["amount"], $row["cid"]));
                            array_push($item, $situations[$row["status"]]);
                            $opeations = '<a href="' . $links["edit-product"] . '?id=' . $row["id"] . '" class="sbtn" data-tooltip="' . ___("needs/button-edit") . '"><i class="fa fa-edit"></i></a>';
                            if ($privOperation) {
                                $opeations .= ' <a href="javascript:copyProduct(' . $row["id"] . ');" data-tooltip="' . ___("needs/copy") . '" class="blue sbtn" id="copy_' . $row["id"] . '"><i class="fa fa-copy" aria-hidden="true"></i></a> ';
                                $opeations .= ' <a href="javascript:deleteProduct(' . $row["id"] . ',\'' . $row["name"] . '\');" data-tooltip="' . __("admin/products/delete") . '" class="red sbtn" id="delete_' . $row["id"] . '"><i class="fa fa-trash-o" aria-hidden="true"></i></a>';
                            }
                            array_push($item, $opeations);
                            $output["aaData"][] = $item;
                        }
                    }
                }

                die(Utility::jencode($output));
            } elseif ($pname == "category-list.json") {
                $limit = 10;
                $output = [];
                $aColumns = array('', '', '', 'rank', '');

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

                $filteredList = $this->model->get_categories("hosting", $searches, $orders, $start, $end);
                $filterTotal = $this->model->get_categories_total("hosting", $searches);
                $listTotal = $this->model->get_categories_total("hosting");

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {
                    Helper::Load("Money");

                    $privGroupOperation = Admin::isPrivilege("PRODUCTS_GROUP_OPERATION");
                    $privGroupLook = Admin::isPrivilege("PRODUCTS_GROUP_LOOK");

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $situations = $situations["categories"];

                    $i = 0;
                    if ($filteredList) {
                        foreach ($filteredList as $row) {
                            $i++;
                            $item = [];
                            $catLink = $this->CRLink("products", [$row["route"]]);
                            $catLink = ' <a href="' . $catLink . '" target="_blank" class="targeturl sbtn"><i class="fa fa-external-link" aria-hidden="true"></i></a>';
                            if ($row["parent_route"] == '')
                                $parent_catLink = null;
                            else {
                                $parent_catLink = $this->CRLink("products", [$row["parent_route"]]);
                                $parent_catLink = ' <a href="' . $parent_catLink . '" target="_blank" class="targeturl sbtn"><i class="fa fa-external-link" aria-hidden="true"></i></a>';
                            }

                            array_push($item, $i);
                            array_push($item, $row["name"] . $catLink);
                            array_push($item, $row["parent_name"] . $parent_catLink);
                            array_push($item, $situations[$row["status"]]);
                            $opeations = '<a href="' . $links["edit-category"] . '?id=' . $row["id"] . '" class="sbtn"><i class="fa fa-edit"></i></a>';
                            if ($privGroupOperation)
                                $opeations .= ' <a href="javascript:deleteCategory(' . $row["id"] . ',\'' . $row["name"] . '\');" title="' . __("admin/products/delete") . '" class="red sbtn" id="delete_' . $row["id"] . '"><i class="fa fa-trash-o" aria-hidden="true"></i></a>';
                            array_push($item, $opeations);
                            $output["aaData"][] = $item;
                        }
                    }
                }

                die(Utility::jencode($output));
            }

            $links["controller"] = $this->AdminCRLink("products", ["hosting"]);
            $this->addData("links", $links);

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

            $this->addData("meta", __("admin/products/meta-hosting-list"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/products/breadcrumb-hosting-list"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("settings", [

            ]);

            $this->addData("functions", []);

            $this->view->chose("admin")->render("hosting-list", $this->data);
        }


        private function server($pname = false)
        {

            $links = [
                'settings'              => $this->AdminCRLink("products-2", ["server", "settings"]),
                'add-new-product'       => $this->AdminCRLink("products-2", ['server', 'add']),
                'add-new-category'      => $this->AdminCRLink("products-2", ['server', 'add-category']),
                'edit-product'          => $this->AdminCRLink("products-2", ['server', 'edit']),
                'edit-category'         => $this->AdminCRLink("products-2", ['server', 'edit-category']),
                'shared-servers'        => $this->AdminCRLink("products-2", ['hosting', 'shared-servers']),
                'server-group-redirect' => $this->CRLink("products", ['server']),
                'product-categories'    => $this->AdminCRLink("products-2", ['server', 'categories']),
                'ajax-product-list'     => $this->AdminCRLink("products-2", ['server', 'product-list.json']),
                'ajax-category-list'    => $this->AdminCRLink("products-2", ['server', 'category-list.json']),
                'add-new-addon'         => $this->AdminCRLink("products-2", ['addons', 'add']),
                'add-new-requirement'   => $this->AdminCRLink("products-2", ['requirements', 'add']),
            ];

            if ($pname == "settings") {
                $links["controller"] = $links["settings"];
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-server-settings"));
                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["server"]),
                        'title' => __("admin/products/breadcrumb-server-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-server-settings"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);


                $this->addData("functions", []);

                $icon_folder = Config::get("pictures/category-icon/folder");

                $icon_picture = $this->model->get_picture("category", 2, "icon");
                if ($icon_picture)
                    $icon_picture = Utility::image_link_determiner($icon_picture, $icon_folder);
                $icon_pictureDeft = Utility::image_link_determiner("default.jpg", $icon_folder);
                $this->addData("getIconImage", $icon_picture);
                $this->addData("getIconImageDeft", $icon_pictureDeft);


                $this->view->chose("admin")->render("server-settings", $this->data);

                die();
            } elseif ($pname == "edit") {

                $id = (int)Filter::init("GET/id", "numbers");
                if (!$id) die();

                $product = $this->model->get_product($id);
                if (!$product) die();


                $this->statistics_extract('server', $product);

                $GLOBALS["product"] = $product;

                $this->addData("product", $product);

                $links["controller"] = $links["edit-product"] . "?id=" . $id;

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-edit-server"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["server"]),
                        'title' => __("admin/products/breadcrumb-server-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-edit-server", ['{name}' => $product["title"]]),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", [
                    'get_addons_with_category'       => function ($category = 0) {
                        return $this->model->get_addons_with_category("server", $category);
                    },
                    'get_product_with_lang'          => function ($lang) {
                        return $this->model->get_product_wlang($GLOBALS["product"]["id"], $lang);
                    },
                    'get_requirements_with_category' => function ($category = 0) {
                        return $this->model->get_requirements_with_category("server", $category);
                    },
                ]);

                $this->addData("categories", $this->model->get_select_categories("server"));

                $this->addData("addon_categories", $this->model->get_addon_categories([], [], 0, 1000));
                $this->addData("requirement_categories", $this->model->get_requirement_categories([], [], 0, 1000));

                $this->addData("prices", $this->model->get_prices("periodicals", "products", $product["id"]));

                Helper::Load("Money");

                $product_folder = Config::get("pictures/products/folder");

                $orderimg_picture = $this->model->get_picture("product", $product["id"], "order");
                if ($orderimg_picture)
                    $orderimg_picture = Utility::image_link_determiner($orderimg_picture, $product_folder);
                $orderimgDeft = Utility::image_link_determiner("order-image-default.jpg", $product_folder);
                $this->addData("getOrderImageDeft", $orderimgDeft);
                $this->addData("getOrderImage", $orderimg_picture);

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


                $upgradeable_products = [];

                $ps = $this->model->upgradeable_products('server', 0, $product['id']);
                if ($ps)
                    $upgradeable_products[] = [
                        'id'       => 0,
                        'title'    => ___("needs/uncategorized"),
                        'products' => $ps,
                    ];

                if ($this->getData("categories")) {
                    foreach ($this->getData("categories") as $c) {
                        $ps = $this->model->upgradeable_products('server', $c['id'], $product['id']);
                        if ($ps) {
                            $c['products'] = $ps;
                            $upgradeable_products[] = $c;
                        }
                    }
                }

                $this->addData("upgradeable_products", $upgradeable_products);

                $this->addData("shared_server_groups", $this->model->select_shared_server_groups('server'));


                $this->view->chose("admin")->render("edit-server", $this->data);

                die();
            } elseif ($pname == "add") {
                $links["controller"] = $links["add-new-product"];

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-add-server"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["server"]),
                        'title' => __("admin/products/breadcrumb-server-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-add-server"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", [
                    'get_addons_with_category'       => function ($category = 0) {
                        return $this->model->get_addons_with_category("server", $category);
                    },
                    'get_requirements_with_category' => function ($category = 0) {
                        return $this->model->get_requirements_with_category("server", $category);
                    },
                ]);

                $this->addData("categories", $this->model->get_select_categories("server"));

                $this->addData("addon_categories", $this->model->get_addon_categories([], [], 0, 1000));
                $this->addData("requirement_categories", $this->model->get_requirement_categories([], [], 0, 1000));

                Helper::Load("Money");

                $product_folder = Config::get("pictures/products/folder");

                $orderimgDeft = Utility::image_link_determiner("order-image-default.jpg", $product_folder);
                $this->addData("getOrderImageDeft", $orderimgDeft);

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

                $upgradeable_products = [];

                $ps = $this->model->upgradeable_products('server', 0);
                if ($ps)
                    $upgradeable_products[] = [
                        'id'       => 0,
                        'title'    => ___("needs/uncategorized"),
                        'products' => $ps,
                    ];

                if ($this->getData("categories")) {
                    foreach ($this->getData("categories") as $c) {
                        $ps = $this->model->upgradeable_products('server', $c['id']);
                        if ($ps) {
                            $c['products'] = $ps;
                            $upgradeable_products[] = $c;
                        }
                    }
                }

                $this->addData("upgradeable_products", $upgradeable_products);

                $this->addData("shared_server_groups", $this->model->select_shared_server_groups('server'));

                $this->view->chose("admin")->render("add-server", $this->data);

                die();
            } elseif ($pname == "edit-category") {

                $id = (int)Filter::init("GET/id", "numbers");
                if (!$id) die();

                $category = $this->model->get_category($id);
                if (!$category) die();

                $GLOBALS["category"] = $category;


                $this->addData("cat", $category);

                $links["controller"] = $links["edit-category"] . "?id=" . $id;

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-edit-server-category"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["server"]),
                        'title' => __("admin/products/breadcrumb-server-list"),
                    ],
                    [
                        'link'  => $links["product-categories"],
                        'title' => __("admin/products/breadcrumb-server-categories"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-edit-server-category", ['{name}' => $category["title"]]),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", [
                    'get_category_with_lang' => function ($lang) {
                        $data = $this->model->get_category_wlang($GLOBALS["category"]["id"], $lang);
                        return $data;
                    },
                ]);

                $header_folder = Config::get("pictures/header-background/folder");
                $icon_folder = Config::get("pictures/category-icon/folder");

                $header_picture = $this->model->get_picture("category", $category["id"], "header-background");
                if ($header_picture)
                    $header_picture = Utility::image_link_determiner($header_picture, $header_folder . "thumb" . DS);
                $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
                $this->addData("getHeaderBackground", $header_picture);
                $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);


                $icon_picture = $this->model->get_picture("category", $category["id"], "icon");
                if ($icon_picture)
                    $icon_picture = Utility::image_link_determiner($icon_picture, $icon_folder);
                $icon_pictureDeft = Utility::image_link_determiner("default.jpg", $icon_folder);
                $this->addData("getIconImage", $icon_picture);
                $this->addData("getIconImageDeft", $icon_pictureDeft);


                $this->addData("categories", $this->model->get_select_categories("server"));

                $this->view->chose("admin")->render("edit-server-category", $this->data);

                die();

            } elseif ($pname == "add-category") {

                $links["controller"] = $links["add-new-category"];

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-add-server-category"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["server"]),
                        'title' => __("admin/products/breadcrumb-server-list"),
                    ],
                    [
                        'link'  => $links["product-categories"],
                        'title' => __("admin/products/breadcrumb-server-categories"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-add-server-category"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", []);

                $header_folder = Config::get("pictures/header-background/folder");
                $icon_folder = Config::get("pictures/category-icon/folder");

                $header_pictureDeft = Utility::image_link_determiner("default.jpg", $header_folder);
                $this->addData("getHeaderBackgroundDeft", $header_pictureDeft);

                $icon_pictureDeft = Utility::image_link_determiner("default.jpg", $icon_folder);
                $this->addData("getIconImageDeft", $icon_pictureDeft);

                $this->addData("categories", $this->model->get_select_categories("server"));

                $this->view->chose("admin")->render("add-server-category", $this->data);

                die();
            } elseif ($pname == "categories") {

                $links["controller"] = $links["product-categories"];
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-server-categories"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["server"]),
                        'title' => __("admin/products/breadcrumb-server-list"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-server-categories"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("settings", [

                ]);

                $this->addData("functions", []);

                $this->view->chose("admin")->render("server-categories", $this->data);

                die();
            } elseif ($pname == "product-list.json") {

                $limit = 10;
                $output = [];
                $aColumns = array('', 'name', '', '', 'rank', '');

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

                $filteredList = $this->model->get_products("server", $searches, $orders, $start, $end);
                $filterTotal = $this->model->get_products_total("server", $searches);
                $listTotal = $this->model->get_products_total("server");

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {
                    Helper::Load("Money");

                    $privOperation = Admin::isPrivilege("PRODUCTS_OPERATION");
                    $privGroupLook = Admin::isPrivilege("PRODUCTS_GROUP_LOOK");

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $situations = $situations["products"];

                    $i = 0;
                    if ($filteredList) {
                        foreach ($filteredList as $row) {
                            $i++;
                            $item = [];
                            $show_category = ___("needs/none");
                            if ($row["category"]) {
                                $show_category = $row["category"] . ' <a href="' . $this->CRLink("products", [$row["category_route"]]) . '" target="_blank" class="targeturl sbtn"><i class="fa fa-external-link" aria-hidden="true"></i></a>';
                            }

                            array_push($item, $i);

                            $id = $row["id"];

                            array_push($item, '<input type="checkbox" onchange="if($(\'.selected-item:not(:checked)\').length==0) $(\'#allSelect\').prop(\'checked\',true); else $(\'#allSelect\').prop(\'checked\',false);" class="checkbox-custom selected-item" id="product-' . $id . '-select" value="' . $id . '"><label for="product-' . $id . '-select" class="checkbox-custom-label"></label>');

                            array_push($item, $row["name"]);
                            array_push($item, $show_category);
                            array_push($item, Money::formatter_symbol($row["amount"], $row["cid"]));
                            array_push($item, $situations[$row["status"]]);
                            $opeations = '<a href="' . $links["edit-product"] . '?id=' . $row["id"] . '" class="sbtn" data-tooltip="' . ___("needs/button-edit") . '"><i class="fa fa-edit"></i></a>';
                            if ($privOperation) {
                                $opeations .= ' <a href="javascript:copyProduct(' . $row["id"] . ');" data-tooltip="' . ___("needs/copy") . '" class="blue sbtn" id="copy_' . $row["id"] . '"><i class="fa fa-copy" aria-hidden="true"></i></a> ';
                                $opeations .= ' <a href="javascript:deleteProduct(' . $row["id"] . ',\'' . $row["name"] . '\');" data-tooltip="' . __("admin/products/delete") . '" class="red sbtn" id="delete_' . $row["id"] . '"><i class="fa fa-trash-o" aria-hidden="true"></i></a>';
                            }
                            array_push($item, $opeations);
                            $output["aaData"][] = $item;
                        }
                    }
                }

                die(Utility::jencode($output));
            } elseif ($pname == "category-list.json") {

                $limit = 10;
                $output = [];
                $aColumns = array('', '', '', 'rank', '');

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

                $filteredList = $this->model->get_categories("server", $searches, $orders, $start, $end);
                $filterTotal = $this->model->get_categories_total("server", $searches);
                $listTotal = $this->model->get_categories_total("server");

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {
                    Helper::Load("Money");

                    $privGroupOperation = Admin::isPrivilege("PRODUCTS_GROUP_OPERATION");
                    $privGroupLook = Admin::isPrivilege("PRODUCTS_GROUP_LOOK");

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $situations = $situations["categories"];

                    $i = 0;
                    if ($filteredList) {
                        foreach ($filteredList as $row) {
                            $i++;
                            $item = [];
                            $catLink = $this->CRLink("products", [$row["route"]]);
                            $catLink = ' <a href="' . $catLink . '" target="_blank" class="targeturl sbtn"><i class="fa fa-external-link" aria-hidden="true"></i></a>';
                            if ($row["parent_route"] == '')
                                $parent_catLink = null;
                            else {
                                $parent_catLink = $this->CRLink("products", [$row["parent_route"]]);
                                $parent_catLink = ' <a href="' . $parent_catLink . '" target="_blank" class="targeturl sbtn"><i class="fa fa-external-link" aria-hidden="true"></i></a>';
                            }

                            array_push($item, $i);
                            array_push($item, $row["name"] . $catLink);
                            array_push($item, $row["parent_name"] . $parent_catLink);
                            array_push($item, $situations[$row["status"]]);
                            $opeations = '<a href="' . $links["edit-category"] . '?id=' . $row["id"] . '" class="sbtn"><i class="fa fa-edit"></i></a>';
                            if ($privGroupOperation)
                                $opeations .= ' <a href="javascript:deleteCategory(' . $row["id"] . ',\'' . $row["name"] . '\');" title="' . __("admin/products/delete") . '" class="red sbtn" id="delete_' . $row["id"] . '"><i class="fa fa-trash-o" aria-hidden="true"></i></a>';
                            array_push($item, $opeations);
                            $output["aaData"][] = $item;
                        }
                    }
                }

                die(Utility::jencode($output));
            }

            $links["controller"] = $this->AdminCRLink("products", ["server"]);
            $this->addData("links", $links);

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

            $this->addData("meta", __("admin/products/meta-server-list"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/products/breadcrumb-server-list"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("settings", [

            ]);

            $this->addData("functions", []);

            $this->view->chose("admin")->render("server-list", $this->data);
        }


        private function addons($pname = false)
        {

            $links = [
                'add'                => $this->AdminCRLink("products-2", ["addons", "add"]),
                'edit'               => $this->AdminCRLink("products-2", ["addons", "edit"]),
                'edit-category'      => $this->AdminCRLink("products-2", ["addons", "edit-category"]),
                'ajax-category-list' => $this->AdminCRLink("products-2", ["addons", "category-list.json"]),
            ];

            if ($pname == "add") {

                $category = Filter::init("GET/category", "numbers");
                if ($category) {
                    $category = $this->model->get_category($category);
                    $links["add"] = $links["add"] . "?category=" . $category["id"];
                }

                $links["controller"] = $links["add"];

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-add-addon"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    $category ? [
                        'link'  => $this->AdminCRLink("products-2", ["addons", "edit-category"]) . "?id=" . $category["id"],
                        'title' => __("admin/products/breadcrumb-addons-list") . " (" . $category["title"] . ")",
                    ] : [
                        'link'  => $this->AdminCRLink("products", ["addons"]),
                        'title' => __("admin/products/breadcrumb-addon-categories"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-add-addon"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", [
                    'get_requirements_with_category' => function ($mcategory = '', $category = 0) {
                        return $this->model->get_requirements_with_category($mcategory, $category);
                    },
                    'get_special_pgroups'            => function () {
                        $data = $this->model->get_product_special_groups();
                        return $data;
                    },
                    'get_product_categories'         => function ($type = '', $kind = '', $parent = 0) {
                        if ($type == "softwares") {
                            return $this->model->get_software_categories();
                        } elseif ($type == "products") {
                            return $this->model->get_product_group_categories($kind, $parent);
                        }
                    },
                    'get_category_products'          => function ($type = '', $category = 0) {
                        return $this->model->get_category_products($type, $category);
                    },
                    'get_tlds'                       => function () {
                        return $this->model->get_tlds2();
                    },
                ]);

                Helper::Load("Money");

                $this->addData("categories", $this->model->get_select_categories("addon"));
                $this->addData("categories_rqs", $this->model->get_select_categories("requirement"));
                $main_categories = __("admin/products/main-category-names2");
                if (!Config::get("options/pg-activation/hosting")) unset($main_categories["hosting"]);
                if (!Config::get("options/pg-activation/server")) unset($main_categories["server"]);
                if (!Config::get("options/pg-activation/software")) unset($main_categories["software"]);

                $specials = $this->model->get_main_special_categories();
                if ($specials) {
                    foreach ($specials as $special) {
                        $main_categories["special_" . $special["id"]] = $special["title"];
                    }
                }

                $this->addData("select_category", $category);

                $this->addData("main_categories", $main_categories);

                $server_modules = Modules::Load("Servers", "All", true);
                $this->addData("module_servers", $server_modules);

                $product_modules = Modules::Load("Product", "All", true);
                $this->addData("module_products", $product_modules);

                $this->view->chose("admin")->render("add-product-addon", $this->data);

                die();
            } elseif ($pname == "edit") {

                $id = (int)Filter::init("GET/id", "numbers");
                if (!$id) die();

                $addon = $this->model->get_addon($id);
                if (!$addon) die();

                $category = $this->model->get_category($addon["category"]);

                $GLOBALS["addon"] = $addon;

                $links["controller"] = $links["edit"] . "?id=" . $id;

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-edit-addon"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $links["edit-category"] . "?id=" . $category["id"],
                        'title' => __("admin/products/breadcrumb-addons-list") . " (" . $category["title"] . ")",
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-edit-addon", ['{name}' => $addon["name"]]),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", [
                    'get_addon_wlang'                => function ($lang) {
                        return $this->model->get_addon_wlang($GLOBALS["addon"]["id"], $lang);
                    },
                    'get_requirements_with_category' => function ($mcategory = '', $category = 0) {
                        return $this->model->get_requirements_with_category($mcategory, $category);
                    },
                    'get_special_pgroups'            => function () {
                        $data = $this->model->get_product_special_groups();
                        return $data;
                    },
                    'get_product_categories'         => function ($type = '', $kind = '', $parent = 0) {
                        if ($type == "softwares") {
                            return $this->model->get_software_categories();
                        } elseif ($type == "products") {
                            return $this->model->get_product_group_categories($kind, $parent);
                        }
                    },
                    'get_category_products'          => function ($type = '', $category = 0) {
                        return $this->model->get_category_products($type, $category);
                    },
                    'get_tlds'                       => function () {
                        return $this->model->get_tlds2();
                    },
                ]);

                Helper::Load("Money");

                $this->addData("categories", $this->model->get_select_categories("addon"));
                $this->addData("categories_rqs", $this->model->get_select_categories("requirement"));
                $main_categories = __("admin/products/main-category-names2");
                if (!Config::get("options/pg-activation/hosting")) unset($main_categories["hosting"]);
                if (!Config::get("options/pg-activation/server")) unset($main_categories["server"]);
                if (!Config::get("options/pg-activation/software")) unset($main_categories["software"]);

                $specials = $this->model->get_main_special_categories();
                if ($specials) {
                    foreach ($specials as $special) {
                        $main_categories["special_" . $special["id"]] = $special["title"];
                    }
                }

                $this->addData("main_categories", $main_categories);

                $this->addData("addon", $addon);

                $server_modules = Modules::Load("Servers", "All", true);
                $this->addData("module_servers", $server_modules);

                $product_modules = Modules::Load("Product", "All", true);
                $this->addData("module_products", $product_modules);

                $this->view->chose("admin")->render("edit-product-addon", $this->data);

                die();
            } elseif ($pname == "edit-category") {

                $id = Filter::init("GET/id", "numbers");
                $category = $this->model->get_category($id);

                if (!$category) die();

                $links["add"] = $links["add"] . "?category=" . $category["id"];

                $links["controller"] = $links["edit-category"] . "?id=" . $id;
                $links["ajax-list"] = $this->AdminCRLink("products-2", ["addons", "list.json"]) . "?category=" . $id;
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-addons-list"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["addons"]),
                        'title' => __("admin/products/breadcrumb-addon-categories"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-addons-list") . " (" . $category["title"] . ") ",
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("settings", [

                ]);

                $this->addData("category", $category);

                $this->addData("functions", []);

                $this->view->chose("admin")->render("product-addons-list", $this->data);
                die();
            } elseif ($pname == "list.json") {

                $category = Filter::init("GET/category", "numbers");

                $limit = 10;
                $output = [];
                $aColumns = array('', 'name', 'description', 'category_name', 'rank', '');

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

                $filteredList = $this->model->get_addons($searches, $orders, $start, $end, $category);
                $filterTotal = $this->model->get_addons_total($searches, $category);
                $listTotal = $this->model->get_addons_total(false, $category);

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {

                    $privOperation = Admin::isPrivilege("PRODUCTS_OPERATION");

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $situations = $situations["product-addons"];

                    $main_categories = __("admin/products/main-category-names");
                    if (!Config::get("options/pg-activation/hosting")) unset($main_categories["hosting"]);
                    if (!Config::get("options/pg-activation/server")) unset($main_categories["server"]);
                    if (!Config::get("options/pg-activation/software")) unset($main_categories["software"]);

                    $specials = $this->model->get_main_special_categories();
                    if ($specials) {
                        foreach ($specials as $special) {
                            $main_categories["special_" . $special["id"]] = $special["title"];
                        }
                    }

                    $i = 0;
                    if ($filteredList) {
                        foreach ($filteredList as $row) {
                            $i++;
                            $item = [];
                            $mcategory = isset($main_categories[$row["mcategory"]]) ? $main_categories[$row["mcategory"]] : false;
                            array_push($item, $i);
                            array_push($item, $row["name"]);
                            array_push($item, $row["category_name"] ? $row["category_name"] : ___("needs/none"));
                            array_push($item, $mcategory ? $mcategory : ___("needs/none"));
                            array_push($item, $situations[$row["status"]]);
                            $opeations = '<a href="' . $links["edit"] . '?id=' . $row["id"] . '" class="sbtn"><i class="fa fa-edit"></i></a>';
                            if ($privOperation)
                                $opeations .= ' <a href="javascript:deleteAddon(' . $row["id"] . ',\'' . $row["name"] . '\');" title="' . __("admin/products/delete") . '" class="red sbtn" id="delete_' . $row["id"] . '"><i class="fa fa-trash-o" aria-hidden="true"></i></a>';
                            array_push($item, $opeations);
                            $output["aaData"][] = $item;
                        }
                    }
                }

                die(Utility::jencode($output));
            } elseif ($pname == "category-list.json") {

                $limit = 10;
                $output = [];
                $aColumns = array('', '', '');

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

                $filteredList = $this->model->get_addon_categories($searches, $orders, $start, $end);
                $filterTotal = $this->model->get_addon_categories_total($searches);
                $listTotal = $this->model->get_addon_categories_total();

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {
                    Helper::Load("Money");

                    $privOperation = Admin::isPrivilege("PRODUCTS_OPERATION");

                    $i = 0;
                    if ($filteredList) {
                        foreach ($filteredList as $row) {
                            $i++;
                            $item = [];
                            array_push($item, $i);
                            array_push($item, $row["name"]);
                            $opeations = '<a href="' . $links["edit-category"] . '?id=' . $row["id"] . '" class="sbtn"><i class="fa fa-edit"></i></a>';
                            if ($privOperation)
                                $opeations .= ' <a href="javascript:deleteCategory(' . $row["id"] . ',\'' . $row["name"] . '\');" title="' . __("admin/products/delete") . '" class="red sbtn" id="delete_' . $row["id"] . '"><i class="fa fa-trash-o" aria-hidden="true"></i></a>';
                            array_push($item, $opeations);
                            $output["aaData"][] = $item;
                        }
                    }
                }

                die(Utility::jencode($output));
            }

            $links["controller"] = $this->AdminCRLink("products", ["addons"]);

            $this->addData("links", $links);

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

            $this->addData("meta", __("admin/products/meta-addon-categories"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/products/breadcrumb-addon-categories"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("settings", [

            ]);

            $this->addData("functions", []);

            $this->view->chose("admin")->render("product-addon-categories", $this->data);
        }


        private function requirements($pname = false)
        {

            $links = [
                'add'                => $this->AdminCRLink("products-2", ["requirements", "add"]),
                'edit'               => $this->AdminCRLink("products-2", ["requirements", "edit"]),
                'edit-category'      => $this->AdminCRLink("products-2", ["requirements", "edit-category"]),
                'ajax-category-list' => $this->AdminCRLink("products-2", ["requirements", "category-list.json"]),
            ];

            if ($pname == "add") {

                $category = Filter::init("GET/category", "numbers");
                if ($category) {
                    $category = $this->model->get_category($category);
                    $links["add"] = $links["add"] . "?category=" . $category["id"];
                }

                $links["controller"] = $links["add"];

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-add-requirement"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    $category ? [
                        'link'  => $this->AdminCRLink("products-2", ["requirements", "edit-category"]) . "?id=" . $category["id"],
                        'title' => __("admin/products/breadcrumb-requirements-list") . " (" . $category["title"] . ")",
                    ] : [
                        'link'  => $this->AdminCRLink("products", ["requirements"]),
                        'title' => __("admin/products/breadcrumb-requirement-categories"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-add-requirement"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", []);

                Helper::Load("Money");

                $this->addData("categories", $this->model->get_select_categories("requirement"));
                $main_categories = __("admin/products/main-category-names");
                if (!Config::get("options/pg-activation/hosting")) unset($main_categories["hosting"]);
                if (!Config::get("options/pg-activation/server")) unset($main_categories["server"]);
                if (!Config::get("options/pg-activation/software")) unset($main_categories["software"]);

                $specials = $this->model->get_main_special_categories();
                if ($specials) {
                    foreach ($specials as $special) {
                        $main_categories["special_" . $special["id"]] = $special["title"];
                    }
                }

                $this->addData("select_category", $category);

                $this->addData("main_categories", $main_categories);

                $server_modules = Modules::Load("Servers", "All", true);
                $this->addData("module_servers", $server_modules);

                $product_modules = Modules::Load("Product", "All", true);
                $this->addData("module_products", $product_modules);

                $this->view->chose("admin")->render("add-product-requirement", $this->data);

                die();
            } elseif ($pname == "edit") {

                $id = (int)Filter::init("GET/id", "numbers");
                if (!$id) die();

                $requirement = $this->model->get_requirement($id);
                if (!$requirement) die();

                $category = $this->model->get_category($requirement["category"]);

                $GLOBALS["requirement"] = $requirement;

                $links["controller"] = $links["edit"] . "?id=" . $id;

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-edit-requirement"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $links["edit-category"] . "?id=" . $category["id"],
                        'title' => __("admin/products/breadcrumb-requirements-list") . " (" . $category["title"] . ")",
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-edit-requirement", ['{name}' => $requirement["name"]]),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", [
                    'get_requirement_wlang' => function ($lang) {
                        return $this->model->get_requirement_wlang($GLOBALS["requirement"]["id"], $lang);
                    },
                ]);

                Helper::Load("Money");

                $this->addData("categories", $this->model->get_select_categories("requirement"));

                $main_categories = __("admin/products/main-category-names");
                if (!Config::get("options/pg-activation/hosting")) unset($main_categories["hosting"]);
                if (!Config::get("options/pg-activation/server")) unset($main_categories["server"]);
                if (!Config::get("options/pg-activation/software")) unset($main_categories["software"]);

                $specials = $this->model->get_main_special_categories();
                if ($specials) {
                    foreach ($specials as $special) {
                        $main_categories["special_" . $special["id"]] = $special["title"];
                    }
                }

                $this->addData("main_categories", $main_categories);

                $this->addData("requirement", $requirement);

                $server_modules = Modules::Load("Servers", "All", true);
                $this->addData("module_servers", $server_modules);

                $product_modules = Modules::Load("Product", "All", true);
                $this->addData("module_products", $product_modules);

                $this->view->chose("admin")->render("edit-product-requirement", $this->data);

                die();
            } elseif ($pname == "edit-category") {

                $id = Filter::init("GET/id", "numbers");
                $category = $this->model->get_category($id);

                if (!$category) die();

                $links["add"] = $links["add"] . "?category=" . $category["id"];

                $links["controller"] = $links["edit-category"] . "?id=" . $id;
                $links["ajax-list"] = $this->AdminCRLink("products-2", ["requirements", "list.json"]) . "?category=" . $id;
                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-requirements-list"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["requirements"]),
                        'title' => __("admin/products/breadcrumb-requirement-categories"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/breadcrumb-requirements-list") . " (" . $category["title"] . ") ",
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("settings", [

                ]);

                $this->addData("category", $category);

                $this->addData("functions", []);

                $this->view->chose("admin")->render("product-requirements-list", $this->data);
                die();
            } elseif ($pname == "list.json") {

                $category = Filter::init("GET/category", "numbers");

                $limit = 10;
                $output = [];
                $aColumns = array('', 'name', 'description', 'category_name', 'rank', '');

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

                $filteredList = $this->model->get_requirements($searches, $orders, $start, $end, $category);
                $filterTotal = $this->model->get_requirements_total($searches, $category);
                $listTotal = $this->model->get_requirements_total(false, $category);

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {

                    $privOperation = Admin::isPrivilege("PRODUCTS_OPERATION");

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $situations = $situations["product-requirements"];

                    $main_categories = __("admin/products/main-category-names");
                    if (!Config::get("options/pg-activation/hosting")) unset($main_categories["hosting"]);
                    if (!Config::get("options/pg-activation/server")) unset($main_categories["server"]);
                    if (!Config::get("options/pg-activation/software")) unset($main_categories["software"]);

                    $specials = $this->model->get_main_special_categories();
                    if ($specials) {
                        foreach ($specials as $special) {
                            $main_categories["special_" . $special["id"]] = $special["title"];
                        }
                    }

                    $i = 0;
                    if ($filteredList) {
                        foreach ($filteredList as $row) {
                            $i++;
                            $item = [];
                            $mcategory = isset($main_categories[$row["mcategory"]]) ? $main_categories[$row["mcategory"]] : false;
                            array_push($item, $i);
                            array_push($item, $row["name"]);
                            array_push($item, $row["category_name"] ? $row["category_name"] : ___("needs/none"));
                            array_push($item, $mcategory ? $mcategory : ___("needs/none"));
                            array_push($item, $situations[$row["status"]]);
                            $opeations = '<a href="' . $links["edit"] . '?id=' . $row["id"] . '" class="sbtn"><i class="fa fa-edit"></i></a>';
                            if ($privOperation)
                                $opeations .= ' <a href="javascript:deleteAddon(' . $row["id"] . ',\'' . $row["name"] . '\');" title="' . __("admin/products/delete") . '" class="red sbtn" id="delete_' . $row["id"] . '"><i class="fa fa-trash-o" aria-hidden="true"></i></a>';
                            array_push($item, $opeations);
                            $output["aaData"][] = $item;
                        }
                    }
                }

                die(Utility::jencode($output));
            } elseif ($pname == "category-list.json") {

                $limit = 10;
                $output = [];
                $aColumns = array('', '', '');

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

                $filteredList = $this->model->get_requirement_categories($searches, $orders, $start, $end);
                $filterTotal = $this->model->get_requirement_categories_total($searches);
                $listTotal = $this->model->get_requirement_categories_total();

                $this->takeDatas("language");

                $output = array_merge($output, [
                    "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                    "iTotalRecords"        => $listTotal,
                    "iTotalDisplayRecords" => $filterTotal,
                    "aaData"               => [],
                ]);

                if ($listTotal) {
                    Helper::Load("Money");

                    $privOperation = Admin::isPrivilege("PRODUCTS_OPERATION");

                    $i = 0;
                    if ($filteredList) {
                        foreach ($filteredList as $row) {
                            $i++;
                            $item = [];
                            array_push($item, $i);
                            array_push($item, $row["name"]);
                            $opeations = '<a href="' . $links["edit-category"] . '?id=' . $row["id"] . '" class="sbtn"><i class="fa fa-edit"></i></a>';
                            if ($privOperation)
                                $opeations .= ' <a href="javascript:deleteCategory(' . $row["id"] . ',\'' . $row["name"] . '\');" title="' . __("admin/products/delete") . '" class="red sbtn" id="delete_' . $row["id"] . '"><i class="fa fa-trash-o" aria-hidden="true"></i></a>';
                            array_push($item, $opeations);
                            $output["aaData"][] = $item;
                        }
                    }
                }

                die(Utility::jencode($output));
            }

            $links["controller"] = $this->AdminCRLink("products", ["requirements"]);

            $this->addData("links", $links);

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

            $this->addData("meta", __("admin/products/meta-requirement-categories"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/products/breadcrumb-requirement-categories"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("settings", [

            ]);

            $this->addData("functions", []);

            $this->view->chose("admin")->render("product-requirement-categories", $this->data);
        }


        private function api($pname = false)
        {

            $links = [
                'group-ssl'     => $this->AdminCRLink("products-2", ["api", "ssl"]),
                'group-license' => $this->AdminCRLink("products-2", ["api", "license"]),
                'group-other'   => $this->AdminCRLink("products-2", ["api", "other"]),
            ];

            if ($pname != '') {

                $links["controller"] = $this->AdminCRLink("products-2", ["api", $pname]);

                $this->addData("links", $links);

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

                $this->addData("meta", __("admin/products/meta-api"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("products", ["api"]),
                        'title' => __("admin/products/breadcrumb-api"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/products/api-manage-group-" . $pname),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                Helper::Load(["Money", "Products"]);

                $this->addData("page_name", __("admin/products/api-manage-group-" . $pname));
                $this->addData("module_group", $pname);

                $modules = Modules::Load("Product", "All");
                foreach ($modules as $k => $v) {
                    if ($v["config"]["group"] != $pname) {
                        unset($modules[$k]);
                        continue;
                    }
                    $v["created_at"] = $v["config"]["created_at"];
                    $modules[$k] = $v;
                }
                Utility::sksort($modules, "created_at");
                $this->addData("modules", $modules);
                $this->addData("module_url", CORE_FOLDER . DS . MODULES_FOLDER . DS . "Product" . DS);
                $m_name = Filter::init("REQUEST/module", "folder");
                $m_name = str_replace(['-', '.'], ['_', ''], $m_name);
                $m_content = false;
                $module = false;

                #if(!$m_name && $modules) $m_name = array_keys($modules)[0];


                $m_data = $m_name ? $modules[$m_name] : false;

                if ($m_data) {
                    $module = new $m_name;
                    $module->area_link = $links["controller"];
                    if (method_exists($module, 'configuration'))
                        $m_content = $module->configuration();
                    else
                        $m_content = Modules::getPage("Product", $m_name, 'settings', [
                            'module' => $module,
                        ]);
                }

                $this->addData("m_name", $m_name);
                $this->addData("module", $module);
                $this->addData("m_data", $m_data);
                $this->addData("m_content", $m_content);


                $this->view->chose("admin")->render("product-module", $this->data);

                die();
            }

            $links["controller"] = $this->AdminCRLink("products", ["api"]);

            $this->addData("links", $links);

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

            $this->addData("meta", __("admin/products/meta-api"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/products/breadcrumb-api"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $this->view->chose("admin")->render("product-api", $this->data);
        }


        public function main()
        {

            $type = isset($this->params[0]) ? $this->params[0] : false;
            $page = isset($this->params[1]) ? $this->params[1] : false;

            if (Filter::POST("operation")) return $this->operationMain($type, Filter::init("POST/operation", "route"));
            if (Filter::GET("operation")) return $this->operationMain($type, Filter::init("GET/operation", "route"));

            return $this->pageMain($type, $page);
        }


        private function statistics_extract($type = '', $product = [])
        {
            Helper::Load("Money");

            $result = [];
            $local_c = Config::get("general/currency");
            $this_month = $this->model->sales_statistics_orders('active', $type, $product["id"], 'this-month');
            $this_year = $this->model->sales_statistics_orders('active', $type, $product["id"], 'this-year');
            $active_orders = $this->model->get_order_count($type, $product["id"], "active");
            $all_orders = $this->model->get_order_count($type, $product["id"]);

            if ($this_month) {
                $this_month_amount = 0;
                $this_month_count = 0;
                foreach ($this_month as $t) {
                    $this_month_count += $t["total_count"];
                    $this_month_amount += Money::exChange($t["total_fee"], $t["currency"], $local_c);
                }
                $result["this_month"] = $this_month_amount;
            }
            if ($this_year) {
                $this_year_amount = 0;
                $this_year_count = 0;
                foreach ($this_year as $t) {
                    $this_year_count += $t["total_count"];
                    $this_year_amount += Money::exChange($t["total_fee"], $t["currency"], $local_c);
                }
                $result["this_year"] = $this_year_amount;
            }


            $this->addData("statistics", $result);

            $this->addData("active_order_count", $active_orders);
            $this->addData("total_order_count", $all_orders);


            return true;
        }


        private function domain_docs()
        {
            Helper::Load(["Products"]);

            $links = [
                'controller' => $this->AdminCRLink("products-2", ["domain", "docs"]),
                'new'        => $this->AdminCRLink("products-2", ["domain", "add-doc"]),
            ];


            $this->addData("links", $links);

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

            $this->addData("meta", __("admin/products/meta-domain-docs"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/products/breadcrumb-domain-docs"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $registrars = Modules::Load("Registrars", "All", true);
            if ($registrars) $registrars = array_keys($registrars);

            $this->addData("registrars", $registrars);

            $this->addData("settings", []);

            $this->addData("functions", []);

            Helper::Load("Money");

            $this->view->chose("admin")->render("tld-docs", $this->data);

        }

        private function domain_add_doc()
        {
            Helper::Load(["Products"]);

            $links = [
                'list'       => $this->AdminCRLink("products-2", ["domain", "docs"]),
                'controller' => $this->AdminCRLink("products-2", ["domain", "add-doc"]),
            ];


            $this->addData("links", $links);

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

            $this->addData("meta", __("admin/products/meta-domain-add-doc"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => $links["list"],
                    'title' => __("admin/products/breadcrumb-domain-docs"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/products/breadcrumb-domain-add-doc"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $registrars = Modules::Load("Registrars", "All", true);
            if ($registrars) {
                $new_registrars = [];
                foreach ($registrars as $k => $v) {
                    if (isset($v["config"]["settings"]["doc-fields"]) && $v["config"]["settings"]["doc-fields"]) {
                        $new_registrars[$k] = $v;
                    }
                    $registrars = $new_registrars;
                }
            }

            $this->addData("registrars", $registrars);


            $this->addData("doc_last_id", $this->tldlist_docs_id() - 1);


            $this->view->chose("admin")->render("tld-add-doc", $this->data);

        }

        private function domain_edit_doc()
        {
            Helper::Load(["Products"]);

            $links = [
                'list'       => $this->AdminCRLink("products-2", ["domain", "docs"]),
                'controller' => $this->AdminCRLink("products-2", ["domain", "edit-doc"]),
            ];

            $id = Filter::init("GET/id", "domain");

            if ($id) {
                $docs = $this->model->db->select()->from("tldlist_docs");
                $docs->where("tld", "=", $id);
                $docs->order_by("sortnum ASC");
                $docs = $docs->build() ? $docs->fetch_assoc() : [];
            }

            if (!$id || !$docs) {
                Utility::redirect($links["list"]);
                return false;
            }

            foreach ($docs as $k => $v) {
                $docs[$k]["options"] = Utility::jdecode($v["options"], true);
                $docs[$k]["languages"] = Utility::jdecode($v["languages"], true);
            }


            $this->addData("docs", $docs);

            $this->addData("links", $links);

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

            $this->addData("meta", __("admin/products/meta-domain-edit-doc"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => $links["list"],
                    'title' => __("admin/products/breadcrumb-domain-docs"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/products/breadcrumb-domain-edit-doc"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("doc_last_id", $this->tldlist_docs_id() - 1);

            $this->addData("id", $id);

            $descriptions = $this->model->db->select("required_docs_info")->from("tldlist")->where("name", "=", $id);
            if ($descriptions->build())
                $descriptions = Utility::jdecode($descriptions->getObject()->required_docs_info, true);
            else
                $descriptions = [];

            $this->addData("descriptions", $descriptions);


            $this->view->chose("admin")->render("tld-edit-doc", $this->data);

        }

    }