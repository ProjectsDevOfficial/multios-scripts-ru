<?php
    defined('CORE_FOLDER') or exit('You can not get in here!');

    class Controller extends Controllers
    {
        protected $params, $data = [], $error;


        public function __construct($arg = [])
        {
            parent::__construct();
            $this->params = $arg['params'];


            if (!UserManager::LoginCheck("admin")) {
                Utility::redirect($this->AdminCRLink("sign-in"));
                die();
            }
            Helper::Load("Admin");
            if (!Admin::isPrivilege(Config::get("privileges/SETTINGS"))) die();

        }

        static function timezones()
        {
            $list = DateTimeZone::listIdentifiers();
            $timezone_list = [];

            foreach ($list as $timezone) {
                try {
                    $tz = new DateTimeZone($timezone);
                    $offset = $tz->getOffset(new DateTime("now", $tz));

                    $offset_prefix = $offset < 0 ? '-' : '+';
                    $offset_formatted = gmdate('H:i', abs($offset));
                    $pretty_offset = "UTC" . $offset_prefix . $offset_formatted;

                    $timezone_list[$timezone] = "(" . $pretty_offset . ") " . $timezone;
                } catch (Exception $e) {
                    continue;
                }
            }
            asort($timezone_list);
            return $timezone_list;
        }


        private function check_server_requirement($upload_check = false)
        {
            if (!class_exists("ZipArchive")) {
                $this->error = __("admin/help/error6");
                return false;
            }

            ob_start();
            phpinfo();
            $php_info = ob_get_clean();


            if ($upload_check) {
                $search = '<tr><td(.*?)>upload_max_filesize<\/td><td(.*?)>(.*?)<\/td><td(.*?)>(.*?)<\/td><\/tr>';
                preg_match('/' . $search . '/', $php_info, $matches);
                $upload_max_filesize_1_raw = trim($matches[3]);
                $upload_max_filesize_2_raw = trim($matches[5]);

                $upload_max_filesize_1 = 0;
                if (preg_match('/^(\d+)(.)$/', $upload_max_filesize_1_raw, $matches)) {
                    if ($matches[2] == 'G') {
                        $upload_max_filesize_1 = $matches[1] * 1024 * 1024 * 1024; // nnnG -> nnn GB
                    } elseif ($matches[2] == 'M') {
                        $upload_max_filesize_1 = $matches[1] * 1024 * 1024; // nnnM -> nnn MB
                    } elseif ($matches[2] == 'K') {
                        $upload_max_filesize_1 = $matches[1] * 1024; // nnnK -> nnn KB
                    } elseif ($matches[2] == 'B') {
                        $upload_max_filesize_1 = $matches[1];
                    }
                }

                $suggested_limit = 20;

                if (substr($upload_max_filesize_1_raw, 0, 2) !== '-1' && $upload_max_filesize_1 > 0 && $upload_max_filesize_1 < ($suggested_limit * 1024 * 1024)) {
                    $this->error = Bootstrap::$lang->get_cm("admin/settings/error11", ['{value}' => $upload_max_filesize_1_raw]);
                    return false;
                }

                if ($upload_max_filesize_1_raw == '') {
                    $this->error = Bootstrap::$lang->get_cm("admin/settings/error11", ['{value}' => $upload_max_filesize_1_raw]);
                    return false;
                }
            }


            $search = '<tr><td(.*?)>memory_limit<\/td><td(.*?)>(.*?)<\/td><td(.*?)>(.*?)<\/td><\/tr>';
            preg_match('/' . $search . '/', $php_info, $matches);
            $memory_limit_1_raw = $matches[3];
            $memory_limit_2_raw = $matches[5];

            $search = '<tr><td(.*?)>max_execution_time<\/td><td(.*?)>(.*?)<\/td><td(.*?)>(.*?)<\/td><\/tr>';
            preg_match('/' . $search . '/', $php_info, $matches);
            $max_execution_time_1 = $matches[3];
            $max_execution_time_2 = $matches[5];


            $memory_limit_1 = 0;
            if (preg_match('/^(\d+)(.)$/', $memory_limit_1_raw, $matches)) {
                if ($matches[2] == 'G') {
                    $memory_limit_1 = $matches[1] * 1024 * 1024 * 1024; // nnnG -> nnn GB
                } elseif ($matches[2] == 'M') {
                    $memory_limit_1 = $matches[1] * 1024 * 1024; // nnnM -> nnn MB
                } elseif ($matches[2] == 'K') {
                    $memory_limit_1 = $matches[1] * 1024; // nnnK -> nnn KB
                } elseif ($matches[2] == 'B') {
                    $memory_limit_1 = $matches[1];
                }
            }

            $memory_limit_2 = 0;
            if (preg_match('/^(\d+)(.)$/', $memory_limit_2_raw, $matches)) {
                if ($matches[2] == 'G') {
                    $memory_limit_2 = $matches[1] * 1024 * 1024 * 1024; // nnnG -> nnn GB
                } elseif ($matches[2] == 'M') {
                    $memory_limit_2 = $matches[1] * 1024 * 1024; // nnnM -> nnn MB
                } elseif ($matches[2] == 'K') {
                    $memory_limit_2 = $matches[1] * 1024; // nnnK -> nnn KB
                } elseif ($matches[2] == 'B') {
                    $memory_limit_2 = $matches[1];
                }
            }

            $suggested_memory = 128;

            $error_msg = Bootstrap::$lang->get_cm("admin/help/error7", ['{suggested_memory}' => $suggested_memory, '{current_memory}' => $memory_limit_1_raw]) . ' ' . Bootstrap::$lang->get_cm("admin/help/error8");
            $error_msg = str_replace("<br>", "", $error_msg);

            if (substr($memory_limit_1_raw, 0, 2) !== '-1' && $memory_limit_1 > 0 && $memory_limit_1 < ($suggested_memory * 1024 * 1024)) {
                $this->error = $error_msg;
                return false;
            }

            if ($memory_limit_1_raw == '') {
                $this->error = $error_msg;
                return false;
            }


            $error_msg = Bootstrap::$lang->get_cm("admin/help/error9", ['{suggested}' => 120, '{current}' => $max_execution_time_1]) . ' ' . Bootstrap::$lang->get_cm("admin/help/error8");
            $error_msg = str_replace("<br>", "", $error_msg);

            if ($max_execution_time_1 != '-1' && !($max_execution_time_1 >= 120)) {
                $this->error = $error_msg;
                return false;
            }

            if ($max_execution_time_1 == '') {
                $this->error = $error_msg;
                return false;
            }


            return true;
        }


        private function get_block_picture($id = 0)
        {
            $data = $this->model->get_picture($id);
            if (!$data) $data = "default.jpg";

            $data = Utility::image_link_determiner($data, Config::get("pictures/blocks/folder"));

            return $data;
        }


        private function get_product_groups()
        {
            $data = $this->model->get_product_groups();
            if ($data) {
                $nwdata = [];
                foreach ($data as $d) $nwdata[$d["lang"]][$d["id"]] = $d;
                $data = $nwdata;
            }
            return $data;
        }


        private function update_theme_other_settings()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $onlyPanel = (int)Filter::init("POST/only-panel", "numbers");
            $ctixswps = (int)Filter::init("POST/ctixswps", "numbers");
            $maintenance = (int)Filter::init("POST/maintenance-mode", "numbers");
            $padeno = (int)Filter::init("POST/padeno", "numbers");
            $padenews = (int)Filter::init("POST/padenews", "numbers");
            $padeart = (int)Filter::init("POST/padeart", "numbers");
            $padekbs = (int)Filter::init("POST/padekbs", "numbers");

            $config_sets = [];


            if ($onlyPanel != Config::get("theme/only-panel")) {
                $config_sets["theme"]["only-panel"] = $onlyPanel;
            }

            if ($ctixswps != Config::get("options/client-index-show-products")) {
                $config_sets["options"]["client-index-show-products"] = $ctixswps;
            }

            if ($maintenance != Config::get("theme/maintenance-mode")) {
                $config_sets["theme"]["maintenance-mode"] = $maintenance;
            }

            if ($padeno != Config::get("options/sidebars/page-detail-normal")) {
                $config_sets["options"]["sidebars"]["page-detail-normal"] = $padeno;
            }

            if ($padenews != Config::get("options/sidebars/page-detail-news")) {
                $config_sets["options"]["sidebars"]["page-detail-news"] = $padenews;
            }

            if ($padeart != Config::get("options/sidebars/page-detail-articles")) {
                $config_sets["options"]["sidebars"]["page-detail-articles"] = $padeart;
            }

            if ($padekbs != Config::get("options/sidebars/page-detail-kbase")) {
                $config_sets["options"]["sidebars"]["page-detail-kbase"] = $padekbs;
            }

            $changes = 0;

            if (isset($config_sets["theme"]) && $config_sets["theme"]) {
                $theme_result = Config::set("theme", $config_sets["theme"]);
                $var_export = Utility::array_export($theme_result, ['pwith' => true]);
                $write = FileManager::file_write(CONFIG_DIR . "theme.php", $var_export);
                if ($write) $changes + 1;
            }

            if (isset($config_sets["options"]) && $config_sets["options"]) {
                $options_result = Config::set("options", $config_sets["options"]);
                $var_export = Utility::array_export($options_result, ['pwith' => true]);
                $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                if ($write) $changes + 1;
            }

            if ($changes) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-theme-other-settings");
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);

        }

        private function update_logo()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $header_logo = Filter::FILES("header_logo");
            $footer_logo = Filter::FILES("footer_logo");
            $clientArea_logo = Filter::FILES("clientArea_logo");
            $favicon_logo = Filter::FILES("favicon_logo");
            $invoice_detail_logo = Filter::FILES("invoice_detail_logo");
            $color1 = ltrim(Filter::init("POST/new_color1"), "#");
            $color2 = ltrim(Filter::init("POST/new_color2"), "#");


            $config_sets = [];

            Helper::Load("Uploads");

            $logo_folder = Config::get("pictures/logo/folder");

            if ($header_logo) {
                $file_name = Filter::permalink(__("website/index/meta/title"));
                $upload = Helper::get("Uploads");
                $upload->init($header_logo, [
                    'image-upload' => true,
                    'folder'       => $logo_folder,
                    'width'        => Config::get("pictures/logo/header/sizing/width"),
                    'height'       => Config::get("pictures/logo/header/sizing/height"),
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => $file_name,
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='header_logo']",
                        'message' => __("admin/settings/error1", ['{error}' => $upload->error]),
                    ]));
                $picture = current($upload->operands);
                $picture = $picture["file_path"];
                $config_sets["theme"]["header-logo"] = $logo_folder . $picture;
            }

            if ($footer_logo) {
                $file_name = Filter::permalink(__("website/index/meta/title")) . "-2";
                $upload = Helper::get("Uploads");
                $upload->init($footer_logo, [
                    'image-upload' => true,
                    'folder'       => $logo_folder,
                    'width'        => Config::get("pictures/logo/footer/sizing/width"),
                    'height'       => Config::get("pictures/logo/footer/sizing/height"),
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => $file_name,
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='footer_logo']",
                        'message' => __("admin/settings/error1", ['{error}' => $upload->error]),
                    ]));
                $picture = current($upload->operands);
                $picture = $picture["file_path"];
                $way = $logo_folder . $picture;
                $config_sets["theme"]["footer-logo"] = $way;
            }

            if ($clientArea_logo) {
                $upload = Helper::get("Uploads");
                $upload->init($clientArea_logo, [
                    'image-upload' => true,
                    'folder'       => $logo_folder,
                    'width'        => Config::get("pictures/logo/clientArea/sizing/width"),
                    'height'       => Config::get("pictures/logo/clientArea/sizing/height"),
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='clientArea_logo']",
                        'message' => __("admin/settings/error1", ['{error}' => $upload->error]),
                    ]));
                $picture = current($upload->operands);
                $picture = $picture["file_path"];
                $way = $logo_folder . $picture;
                $config_sets["theme"]["clientArea-logo"] = $way;
                $config_sets["theme"]["sign-logo"] = $way;
            }

            if ($invoice_detail_logo) {
                $upload = Helper::get("Uploads");
                $upload->init($invoice_detail_logo, [
                    'image-upload' => true,
                    'folder'       => $logo_folder,
                    'width'        => Config::get("pictures/logo/clientArea/sizing/width"),
                    'height'       => Config::get("pictures/logo/clientArea/sizing/height"),
                    'allowed-ext'  => "image/*",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='invoice_detail_logo']",
                        'message' => __("admin/settings/error1", ['{error}' => $upload->error]),
                    ]));
                $picture = current($upload->operands);
                $picture = $picture["file_path"];
                $way = $logo_folder . $picture;
                $config_sets["theme"]["invoice-detail-logo"] = $way;
            }

            if ($favicon_logo) {
                $file_name = "favicon";
                $upload = Helper::get("Uploads");
                $upload->init($favicon_logo, [
                    'date'         => false,
                    'image-upload' => true,
                    'folder'       => $logo_folder,
                    'width'        => Config::get("pictures/logo/favicon/sizing/width"),
                    'height'       => Config::get("pictures/logo/favicon/sizing/height"),
                    'allowed-ext'  => "image/*,svg,ico",
                    'file-name'    => $file_name,
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='favicon_logo']",
                        'message' => __("admin/settings/error1", ['{error}' => $upload->error]),
                    ]));
                $picture = current($upload->operands);
                $picture = $picture["file_path"];
                $config_sets["theme"]["favicon"] = $logo_folder . $picture;
            }

            $changes = 0;

            if (isset($config_sets["theme"]) && $config_sets["theme"]) {
                $theme_result = Config::set("theme", $config_sets["theme"]);
                $var_export = Utility::array_export($theme_result, ['pwith' => true]);
                $write = FileManager::file_write(CONFIG_DIR . "theme.php", $var_export);
                if ($write) $changes + 1;
            }

            if (isset($config_sets["options"]) && $config_sets["options"]) {
                $options_result = Config::set("options", $config_sets["options"]);
                $var_export = Utility::array_export($options_result, ['pwith' => true]);
                $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                if ($write) $changes + 1;
            }

            if ($changes) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-theme-logo");
            }

            if ($color1 && $color2) {
                $templates = FileManager::glob(TEMPLATE_DIR . "website" . DS . "*", GLOB_ONLYDIR);
                foreach ($templates as $t) {
                    $config_p = $t . DS . "theme-config.php";
                    if (file_exists($config_p)) {
                        $config = include $config_p;

                        $config["settings"]["color1"] = $color1;
                        $config["settings"]["meta-color"] = $color1;
                        $config["settings"]["color2"] = $color2;
                        $config_data = Utility::array_export($config, ['pwith' => true]);
                        FileManager::file_write($config_p, $config_data);
                    }
                }
            }


            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);


        }

        private function update_theme_settings()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $key = Filter::POST("key", "route");
            $theme = $this->get_themes($key, ["init"]);
            if (!$theme) return false;

            $config = $theme["config"];
            $init = $theme["init"];

            if (!$init)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Not found theme class",
                ]));

            if (!method_exists($init, "change_settings"))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Not found \"change_settings\" method in theme class",
                ]));

            $apply_change = $init->change_settings();

            if (!$apply_change && $init->error)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => $init->error,
                ]));

            $config["settings"] = $apply_change;

            $main_config = Config::get("theme");

            if ($apply_change) {
                foreach ($apply_change as $k => $v) {
                    if (isset($main_config[$k])) {
                        $main_config[$k] = $v;
                    }
                }
            }


            $var_export = Utility::array_export($config, ['pwith' => true]);
            FileManager::file_write(TEMPLATE_DIR . "website" . DS . $key . DS . "theme-config.php", $var_export);

            $var_export = Utility::array_export(['theme' => $main_config], ['pwith' => true]);
            FileManager::file_write(CORE_DIR . "configuration" . DS . "theme.php", $var_export);


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-theme-settings", [
                'name' => $theme["config"]["meta"]["name"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/settings/success1"),
                'redirect' => $this->AdminCRLink("settings-p", ["theme"]) . "?group=theme",
            ]);


        }

        private function upload_theme()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            if (!$this->check_server_requirement(true))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => $this->error,
                ]));


            $file = Filter::FILES("theme");
            Helper::Load(["Uploads"]);
            $upload = Helper::get("Uploads");

            $tmp_folder = ROOT_DIR . "temp" . DS;

            $upload->init($file, [
                'folder'      => $tmp_folder,
                'file-name'   => "random",
                'date'        => false,
                'allowed-ext' => ".zip",
            ]);

            if (!$upload->processed())
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/settings/error1", ['{error}' => $upload->error]),
                ]));

            $file = current($upload->operands);
            $file = $tmp_folder . $file["file_path"];

            if (!class_exists("ZipArchive")) {
                FileManager::file_delete($file);
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/help/error6"),
                ]));
            }

            MioException::$error_hide = true;
            $zip = zip_open($file);
            if (!is_resource($zip)) {
                FileManager::file_delete($file);
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/help/error2"),
                ]));
            }

            $theme_config = [];

            while ($zip_entry = zip_read($zip)) {
                if (zip_entry_name($zip_entry) == "theme-config.php") {
                    if (zip_entry_open($zip, $zip_entry)) {
                        $contents = zip_entry_read($zip_entry, 3000000);
                        if ($contents && stristr($contents, "<?php")) {
                            $contents = str_replace("<?php", "", $contents);
                            $theme_config = eval($contents);
                        }
                        zip_entry_close($zip_entry);
                    }
                }
            }
            zip_close($zip);
            MioException::$error_hide = false;

            if (!$theme_config || !is_array($theme_config) || !isset($theme_config["meta"]["name"])) {
                FileManager::file_delete($file);
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/settings/error9"),
                ]));
            }

            $name = $theme_config["meta"]["name"];
            $name = Filter::transliterate($name);
            $name = str_replace(" ", "", $name);
            $key = Filter::folder($name);


            $new_folder = TEMPLATE_DIR . "website" . DS . $key . DS;

            if (!mkdir(rtrim($new_folder, DS), 0755))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Failed to create directory. " . $new_folder,
                ]));


            MioException::$error_hide = true;
            $zip = new ZipArchive();
            if ($zip->open($file) !== true)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/help/error2"),
                ]));
            $zip->extractTo($new_folder);
            $zip->close();
            MioException::$error_hide = false;

            FileManager::file_delete($file);


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "uploaded-theme", [
                'name' => $theme_config["meta"]["name"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'redirect' => $this->AdminCRLink("settings-p", ["theme"]) . "?group=theme",
            ]);


        }

        private function download_theme()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $key = Filter::init("GET/key", "route");

            if (!$key) return false;

            $folder = TEMPLATE_DIR . "website" . DS . $key;

            $theme = $this->get_themes($key);
            if (!$theme) return false;


            if (!$this->check_server_requirement())
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => $this->error,
                ]));


            $files = FileManager::glob($folder . DS . "*", 0, true);

            if (!$files) die("Not found theme files");

            $zip_file = ROOT_DIR . "temp" . DS . md5(time()) . ".zip";
            if (file_exists($zip_file)) FileManager::file_delete($zip_file);

            $zip = new ZipArchive();
            if ($zip->open($zip_file, ZIPARCHIVE::CREATE) !== true) die("Can not create zip archive.");

            foreach ($files as $file) {
                $local_file = str_replace($folder . DS, "", $file);
                if (is_dir($file)) $zip->addEmptyDir($local_file);
                else $zip->addFile($file, $local_file);
            }
            $zip->close();

            if (!file_exists($zip_file)) die("Not found theme archive files");

            $quoted = $key . ".zip";
            $size = filesize($zip_file);

            echo FileManager::file_read($zip_file, $size);

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $quoted);
            header('Content-Transfer-Encoding: binary');
            header('Connection: Keep-Alive');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . $size);

            FileManager::file_delete($zip_file);
        }

        private function remove_theme()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $key = Filter::init("POST/key", "route");

            if (!$key) return false;

            $folder = TEMPLATE_DIR . "website" . DS . $key;

            $theme = $this->get_themes($key);
            if (!$theme) die("Not found Theme");

            if ($key == "Default")
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/settings/error10"),
                ]));

            FileManager::remove_glob_directory($folder);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "removed-theme", [
                'name' => $theme["config"]["meta"]["name"],
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/settings/success6"),
            ]);

        }

        private function apply_theme()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $key = Filter::init("POST/key", "route");

            if (!$key) return false;

            $folder = TEMPLATE_DIR . "website" . DS . $key;

            $theme = $this->get_themes($key);
            if (!$theme) die("Not found Theme");

            if (!$theme["has_files"]) {
                $version = License::get_version();
                $file = ROOT_DIR . "temp" . DS . md5(time() . rand(1000, 9999)) . ".zip";
                $download_url = "https://my.wisecp.com/download/theme-file/92/" . $version . "/" . $key;

                $download_file = Updates::download_remote_file($download_url, $file);

                if (!$download_file || !file_exists($file))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/help/error1"),
                    ]));


                MioException::$error_hide = true;
                $zip = new ZipArchive();
                if ($zip->open($file) !== true)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/help/error2"),
                    ]));
                $zip->extractTo(ROOT_DIR);
                $zip->close();
                MioException::$error_hide = false;
                FileManager::file_delete($file);
            }


            $theme_result = Config::set("theme", ['name' => $key]);
            $var_export = Utility::array_export($theme_result, ['pwith' => true]);
            FileManager::file_write(CONFIG_DIR . "theme.php", $var_export);


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "theme-applied", [
                'name' => $theme["config"]["meta"]["name"],
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/settings/success7"),
            ]);

        }

        private function upgrade_theme_version()
        {
            $this->takeDatas("language");

            Helper::Load(["Events"]);

            $key = Filter::init("POST/key", "route");

            if (!$key) return false;

            $folder = TEMPLATE_DIR . "website" . DS . $key . DS;

            $theme = $this->get_themes($key);
            if (!$theme) die("Not found Theme");

            if (!isset($theme["new-update"]) || !$theme["new-update"]) return false;

            $new_update = $theme["new-update"];

            $download_url = null;

            if (isset($new_update["file_url"]) && $new_update["file_url"]) $download_url = $new_update["file_url"];

            if (!filter_var($download_url, FILTER_VALIDATE_URL)) $download_url = null;

            if (!$download_url)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "The 'file_url' parameter was sent missing by the provider.",
                ]));


            if (!$this->check_server_requirement())
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => $this->error,
                ]));


            $file = ROOT_DIR . "temp" . DS . md5(time() . rand(1000, 9999)) . ".zip";

            $download_file = Updates::download_remote_file($download_url, $file);

            if (!$download_file || !file_exists($file))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/help/error1"),
                ]));


            MioException::$error_hide = true;
            $zip = new ZipArchive();
            if ($zip->open($file) !== true)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/help/error2"),
                ]));
            $zip->extractTo($folder);
            $zip->close();
            MioException::$error_hide = false;

            FileManager::file_delete($file);


            if (file_exists($folder . "theme-config.php")) {
                $config = include $folder . "theme-config.php";
                if ($config["meta"]["version"] != $new_update["version"]) {
                    $config["meta"]["version"] = $new_update["version"];
                    $var_export = Utility::array_export($config, ['pwith' => true]);
                    FileManager::file_write($folder . "theme-config.php", $var_export);
                }
            }
            if (file_exists($folder . "UPDATES")) FileManager::file_delete($folder . "UPDATES");

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "theme-has-been-upgraded-version", [
                'name'    => $theme["config"]["meta"]["name"],
                'version' => "v" . $new_update["version"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/settings/success8"),
                'redirect' => $this->AdminCRLink("settings-p", ["theme"]) . "?group=theme",
            ]);

        }


        private function update_blocks_status()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $lang = Filter::init("POST/lang", "route");
            $situations = Filter::POST("situations");
            $new_blocks = [];

            if (!Bootstrap::$lang->LangExists($lang))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/settings/error2"),
                ]));

            $blocks = ___("blocks", false, $lang);
            foreach ($situations as $status) {
                $status = Filter::route($status);
                if (!isset($blocks[$status]) && strstr($status, "__")) {
                    $exp = explode("__", $status);
                    $owner = $exp[0];
                    $id = $exp[1];
                    $blocks[$status] = [
                        'status' => 1,
                        'owner'  => $owner,
                        'id'     => $id,
                    ];

                    if ($owner == "product-group")
                        $blocks[$status] = array_merge($blocks[$status], [
                            'title'         => null,
                            'description'   => null,
                            'items'         => [],
                            'pic_id'        => 0,
                            'listing_limit' => null,
                        ]);
                }
            }

            foreach ($blocks as $key => $val) $blocks[$key]["status"] = is_array($situations) && in_array($key, $situations) ? 1 : 0;

            $encode = Utility::array_export($blocks, ['pwith' => true]);

            FileManager::file_write(LANG_DIR . $lang . DS . "blocks.php", $encode);

            self::$cache->clear("index-" . $lang);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-blocks-status", ['lang' => $lang]);


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/settings/success1"),
            ]);

        }


        private function update_blocks_ranking()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $lang = Filter::init("POST/lang", "route");
            $ranking = Filter::POST("ranking");

            if (!Bootstrap::$lang->LangExists($lang))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/settings/error2"),
                ]));

            if ($ranking && is_array($ranking)) {
                $blocks = ___("blocks", false, $lang);
                $new_blocks = [];
                foreach ($ranking as $key) {
                    $key = Filter::route($key);
                    if (isset($blocks[$key])) {
                        $new_blocks[$key] = $blocks[$key];
                    } else {
                        if (strstr($key, "__")) {
                            $exp = explode("__", $key);
                            $owner = $exp[0];
                            $id = $exp[1];
                            $new_blocks[$key] = [
                                'status' => 0,
                                'owner'  => $owner,
                                'id'     => $id,
                            ];

                            if ($owner == "product-group")
                                $new_blocks[$key] = array_merge($new_blocks[$key], [
                                    'title'         => null,
                                    'description'   => null,
                                    'items'         => [],
                                    'pic_id'        => 0,
                                    'listing_limit' => null,
                                ]);
                        }
                    }
                }
                foreach ($blocks as $key => $item) {
                    if (!isset($new_blocks[$key])) {
                        $new_blocks[$key] = $item;
                    }
                }
            }

            if ($new_blocks) {

                $encode = Utility::array_export($new_blocks, ['pwith' => true]);

                FileManager::file_write(LANG_DIR . $lang . DS . "blocks.php", $encode);

                self::$cache->clear("index-" . $lang);

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-blocks-ranking", ['lang' => $lang]);
            }


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/settings/success1"),
            ]);

        }


        private function update_block_options()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $lang = Filter::init("POST/lang", "route");
            $key = Filter::init("POST/key", "route");

            if (!Bootstrap::$lang->LangExists($lang))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/settings/error2"),
                ]));

            $blocks = ___("blocks", false, $lang);
            $changed = false;


            if (!isset($blocks[$key]) && strstr($key, "__")) {
                $exp = explode("__", $key);
                $blocks[$key] = [
                    'owner'         => $exp[0],
                    'id'            => $exp[1],
                    'title'         => null,
                    'description'   => null,
                    'items'         => [],
                    'pic_id'        => 0,
                    'listing_limit' => null,
                ];
            }


            if (!isset($blocks[$key]))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/settings/error2"),
                ]));

            $block = $blocks[$key];
            if (!isset($block["owner"])) $block["owner"] = false;
            $title = Filter::init("POST/title", "hclear");
            $description = Filter::init("POST/description", "dtext");
            $button_name = Filter::init("POST/button_name", "hclear");
            $button_link = Filter::init("POST/button_link", "hclear");
            $listing_limit = Filter::init("POST/listing_limit", "numbers");
            $picture_hide = Filter::init("POST/picture_hide", "numbers");
            $picture = Filter::FILES("picture");
            $video = Filter::FILES("video");
            $items = Filter::POST("items");


            if (($key == "about-us" || $key == "features" || $key == "statistics-by-numbers" || $key == "customer-feedback" || $key == "home-softwares" || $block["owner"] == "product-group" || $block["owner"] == "hosting" || $block["owner"] == "server" || $block["owner"] == "sms") && $title != $blocks[$key]["title"]) {
                $changed += 1;
                $blocks[$key]["title"] = $title;
            }

            if (($key == "about-us" || $key == "statistics-by-numbers" || $key == "customer-feedback" || $key == "home-softwares" || $block["owner"] == "product-group" || $block["owner"] == "hosting" || $block["owner"] == "server" || $block["owner"] == "sms") && $description != $blocks[$key]["description"]) {
                $changed += 1;
                $blocks[$key]["description"] = $description;
            }

            if (($key == "about-us") && $button_name != $blocks[$key]["button_name"]) {
                $changed += 1;
                $blocks[$key]["button_name"] = $button_name;
            }

            if (($key == "about-us") && $button_link != $blocks[$key]["button_link"]) {
                $changed += 1;
                $blocks[$key]["button_link"] = $button_link;
            }

            if (($key == "about-us" || $key == "news-articles" || $key == "features" || $key == "statistics-by-numbers" || $key == "home-softwares" || $block["owner"] == "product-group" || $block["owner"] == "hosting" || $block["owner"] == "server" || $block["owner"] == "sms") && $picture) {
                $pic_key = $block["owner"] ? $block["owner"] : $key;
                Helper::Load("Uploads");
                $upload = Helper::get("Uploads");
                $upload->init($picture, [
                    'image-upload' => true,
                    'folder'       => Config::get("pictures/blocks/folder"),
                    'width'        => Config::get("pictures/blocks/" . $pic_key . "/sizing/width"),
                    'height'       => Config::get("pictures/blocks/" . $pic_key . "/sizing/height"),
                    'allowed-ext'  => "image/*,svg",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='picture']",
                        'message' => __("admin/settings/error1", ['{error}' => $upload->error]),
                    ]));
                $picture = current($upload->operands);
                $picture = $picture["file_path"];
                $pic_id = $this->model->add_block_picture($picture, $key);

                if ($blocks[$key]["pic_id"] != 0) {
                    $get_picture = $this->model->get_picture($blocks[$key]["pic_id"]);
                    if ($get_picture) {
                        $filepath = Config::get("pictures/blocks/folder") . $get_picture;
                        if (file_exists($filepath)) unlink($filepath);
                    }
                }

                $changed += 1;
                $blocks[$key]["pic_id"] = (int)$pic_id;
            }

            if ($picture_hide) {
                $blocks[$key]["pic_id"] = 0;
                $changed += 1;
            }

            if ($key == "about-us" && $video) {
                Helper::Load("Uploads");
                $upload = Helper::get("Uploads");
                $upload->init($video, [
                    'image-upload' => false,
                    'folder'       => Config::get("pictures/blocks/folder"),
                    'allowed-ext'  => "mp4",
                    'file-name'    => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='video']",
                        'message' => __("admin/settings/error1", ['{error}' => $upload->error]),
                    ]));
                $video = current($upload->operands);
                $video = $video["file_path"];

                if ($blocks[$key]["video"]) {
                    $video_file = Config::get("pictures/blocks/folder") . $blocks[$key]["video"];
                    if (file_exists($video_file)) @unlink($video_file);
                }

                $changed += 1;
                $blocks[$key]["video"] = $video;
            }

            if (($key == "features")) {
                $nitems = [];

                if ($items && is_array($items)) {
                    $titles = $items["title"];
                    $descriptions = $items["description"];
                    $icons = $items["icon"];
                    $size = sizeof($titles) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        if (isset($titles[$i])) {
                            $nitems[] = [
                                'title'       => isset($titles[$i]) ? Filter::html_clear($titles[$i]) : null,
                                'description' => isset($descriptions[$i]) ? Filter::dtext($descriptions[$i]) : null,
                                'icon'        => isset($icons[$i]) ? Filter::letters_numbers($icons[$i], " \-_") : null,
                                'pic_id'      => 0,
                                'link'        => null,
                                'target'      => null,
                            ];
                        }
                    }
                }
                $changed += 1;
                $blocks[$key]["items"] = $nitems;
            }

            if (($key == "statistics-by-numbers")) {
                $nitems = [];

                if ($items && is_array($items)) {
                    $titles = $items["title"];
                    $numbers = $items["number"];
                    $icons = $items["icon"];
                    $size = sizeof($titles) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        if (isset($titles[$i])) {
                            $nitems[] = [
                                'title'  => isset($titles[$i]) ? Filter::html_clear($titles[$i]) : null,
                                'number' => isset($numbers[$i]) ? (int)Filter::numbers($numbers[$i]) : 0,
                                'icon'   => isset($icons[$i]) ? Filter::letters_numbers($icons[$i], " \-_") : null,
                            ];
                        }
                    }
                }
                $changed += 1;
                $blocks[$key]["items"] = $nitems;
            }

            if (($key == "home-softwares")) {
                $nitems = [];
                if ($items && is_array($items)) {
                    foreach ($items as $item) {
                        if (Validation::isInt($item)) {
                            if ($this->model->get_software($item, $lang)) {
                                array_push($nitems, (int)$item);
                            }
                        }
                    }
                }
                $changed += 1;
                $blocks[$key]["items"] = $nitems;
            }


            if ($block["owner"] == "hosting" || $block["owner"] == "server" || $block["owner"] == "product-group") {
                $nitems = [];
                if ($items && is_array($items)) {
                    foreach ($items as $item) {
                        if (Validation::isInt($item)) {
                            if ($this->model->get_product_category($item, $lang)) {
                                array_push($nitems, (int)$item);
                            }
                        }
                    }
                }

                $changed += 1;
                $blocks[$key]["items"] = $nitems;
            }

            if (($key == "home-domain-check")) {
                $nitems = [];
                if ($items && is_array($items)) {
                    foreach ($items as $item) {
                        if ($this->model->check_tld($item)) {
                            array_push($nitems, $item);
                        }
                    }
                }

                $changed += 1;
                $blocks[$key]["items"] = $nitems;
            }

            if ($key == "news-articles") {
                $news = (bool)Filter::init("POST/news", "numbers");
                $articles = (bool)Filter::init("POST/articles", "numbers");

                if ($news != $blocks[$key]["news"]) {
                    $changed += 1;
                    $blocks[$key]["news"] = $news;
                }

                if ($articles != $blocks[$key]["articles"]) {
                    $changed += 1;
                    $blocks[$key]["articles"] = $articles;
                }
            }

            if ($key == "customer-feedback") {
                $send_opinion = (bool)Filter::init("POST/send-opinion", "numbers");
                if ($send_opinion != $blocks[$key]["send-opinion"]) {
                    $changed += 1;
                    $blocks[$key]["send-opinion"] = $send_opinion;
                }
            }

            if ($block["owner"] == "product-group" || $block["owner"] == "hosting" || $block["owner"] == "server" || $block["owner"] == "sms") {
                $changed += 1;
                $blocks[$key]["listing_limit"] = $listing_limit;
            }


            if ($changed) {

                $encode = Utility::array_export($blocks, ['pwith' => true]);
                FileManager::file_write(LANG_DIR . $lang . DS . "blocks.php", $encode);

                self::$cache->clear("index-" . $lang);

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-block-options", ['lang' => $lang, 'key' => $key]);
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);

        }

        private function delete_block_background_video()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $lang = Filter::init("POST/lang", "route");
            $key = Filter::init("POST/key", "route");

            if (!Bootstrap::$lang->LangExists($lang))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/settings/error2"),
                ]));

            $blocks = ___("blocks", false, $lang);
            $changed = false;

            if (!isset($blocks[$key]))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/settings/error2"),
                ]));

            $block = $blocks[$key];

            if ($key == "about-us") {
                if ($blocks[$key]["video"]) {
                    $video_file = Config::get("pictures/blocks/folder") . $blocks[$key]["video"];
                    if (file_exists($video_file)) @unlink($video_file);
                    $changed += 1;
                    unset($blocks[$key]["video"]);
                }
            }

            if ($changed) {

                $encode = Utility::array_export($blocks, ['pwith' => true]);
                FileManager::file_write(LANG_DIR . $lang . DS . "blocks.php", $encode);

                self::$cache->clear("index-" . $lang);

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-block-options", ['lang' => $lang, 'key' => $key]);
            }
            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);
        }


        private function add_user_custom_field()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $lang = Filter::init("POST/lang", "route");

            if (!Bootstrap::$lang->LangExists($lang))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/settings/error2"),
                ]));

            $added = $this->model->add_user_custom_field($lang);
            if ($added) {
                echo Utility::jencode(['status' => "successful", 'id' => $added]);

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "added", "added-new-user-custom-field", ['lang' => $lang]);
            } else {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Can not add",
                ]);
            }

        }


        private function update_users_custom_fields_ranking()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $lang = Filter::init("POST/lang", "route");
            $ranking = Filter::POST("ranking");

            if (!Bootstrap::$lang->LangExists($lang))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/settings/error2"),
                ]));

            if ($ranking && is_array($ranking)) {
                $changes = 0;
                foreach ($ranking as $rank => $id) {
                    $id = (int)Filter::numbers($id);
                    if ($this->model->check_custom_field($id)) {
                        $update = $this->model->set_custom_field($id, ['rank' => $rank]);
                        if ($update) {
                            $changes++;
                        }
                    }
                }

                if ($changes) {

                    echo Utility::jencode(['status' => "successful"]);

                    $adata = UserManager::LoginData("admin");
                    User::addAction($adata["id"], "alteration", "changed-user-custom-field", ['lang' => $lang]);
                }
            }

        }


        private function delete_user_custom_field()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            $ids = Filter::POST("ids");
            $deleted = 0;

            if ($id) $ids = [$id];

            if ($ids && is_array($ids)) {
                foreach ($ids as $i) {
                    $i = (int)Filter::numbers($i);
                    if ($i) {
                        if ($this->model->check_custom_field($i)) {
                            $delete = $this->model->delete_custom_field($i);
                            if ($delete) $deleted++;
                        }
                    }
                }
            }

            if ($deleted) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "delete", "deleted-user-custom-field", ['id' => $id]);
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success2")]);

        }


        private function save_custom_fields()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $fields = Filter::POST("fields");

            if ($fields && !is_array($fields)) die();

            $changes = 0;

            foreach ($fields as $id => $item) {
                $id = (int)$id;
                if ($this->model->check_custom_field($id)) {
                    $update = $this->model->set_custom_field($id, [
                        'type'       => Filter::letters_numbers($item["type"]),
                        'name'       => Filter::html_clear($item["name"]),
                        'status'     => isset($item["status"]) ? "active" : "inactive",
                        'required'   => isset($item["required"]) ? 1 : 0,
                        'uneditable' => isset($item["uneditable"]) ? 1 : 0,
                        'invoice'    => isset($item["invoice"]) ? 1 : 0,
                        'signForm'   => isset($item["signForm"]) ? 1 : 0,
                        'options'    => Filter::html_clear($item["options"]),
                    ]);
                    if ($update) $changes++;
                }
            }

            if ($changes) {
                echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-user-custom-fields");
            }


        }


        private function save_membership_settings()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $smart_naming = (int)Filter::init("POST/smart-naming", "numbers");
            $crtacwshop = (int)Filter::init("POST/crtacwshop", "numbers");
            $sign_editable_full_name = (bool)Filter::init("POST/sign_editable_full_name", "numbers");
            $sign_up_email_verify_status = (int)Filter::init("POST/sign_up_email_verify_status", "numbers");
            $sign_editable_email = (bool)Filter::init("POST/sign_editable_email", "numbers");
            $sign_editable_gsm = (bool)Filter::init("POST/sign_editable_gsm", "numbers");
            $sign_up_gsm_status = (int)Filter::init("POST/sign_up_gsm_status", "numbers");
            $sign_up_gsm_required = (int)Filter::init("POST/sign_up_gsm_required", "numbers");
            $sign_up_gsm_checker = (int)Filter::init("POST/sign_up_gsm_checker", "numbers");
            $sign_up_gsm_verify = (int)Filter::init("POST/sign_up_gsm_verify", "numbers");
            $sign_up_landline_phone_status = (int)Filter::init("POST/sign_up_landline_phone_status", "numbers");
            $sign_up_landline_phone_required = (int)Filter::init("POST/sign_up_landline_phone_required", "numbers");
            $sign_up_landline_phone_checker = (int)Filter::init("POST/sign_up_landline_phone_checker", "numbers");
            $sign_editable_landline_phone = (bool)Filter::init("POST/sign_editable_landline_phone", "numbers");
            $sign_up_identity_status = (int)Filter::init("POST/sign_up_identity_status", "numbers");
            $sign_up_identity_required = (int)Filter::init("POST/sign_up_identity_required", "numbers");
            $sign_up_identity_checker = (int)Filter::init("POST/sign_up_identity_checker", "numbers");
            $sign_editable_identity = (bool)Filter::init("POST/sign_editable_identity", "numbers");
            $sign_in_status = (int)Filter::init("POST/sign_in_status", "numbers");
            $sign_up_status = (int)Filter::init("POST/sign_up_status", "numbers");
            $sign_up_kind_status = (int)Filter::init("POST/sign_up_kind_status", "numbers");
            $sign_editable_kind = (bool)Filter::init("POST/sign_editable_kind", "numbers");
            $sign_birthday_status = (int)Filter::init("POST/sign_birthday_status", "numbers");
            $sign_birthday_required = (int)Filter::init("POST/sign_birthday_required", "numbers");
            $sign_birthday_adult_verify = (int)Filter::init("POST/sign_birthday_adult_verify", "numbers");
            $sign_editable_birthday = (bool)Filter::init("POST/sign_editable_birthday", "numbers");
            $security_question_status = (int)Filter::init("POST/security_question_status", "numbers");
            $security_question_required = (int)Filter::init("POST/security_question_required", "numbers");

            $config_sets = [];
            $config_sets2 = [];


            if ($smart_naming != Config::get("options/smart-naming"))
                $config_sets["options"]["smart-naming"] = $smart_naming;

            if ($crtacwshop != Config::get("options/crtacwshop"))
                $config_sets["options"]["crtacwshop"] = $crtacwshop;


            if ($sign_editable_full_name != Config::get("options/sign/editable/full_name")) {
                $config_sets["options"]["sign"]["editable"]["full_name"] = $sign_editable_full_name;
            }

            if ($sign_up_email_verify_status != Config::get("options/sign/up/email/verify")) {
                $config_sets["options"]["sign"]["up"]["email"]["verify"] = $sign_up_email_verify_status;
            }

            if ($sign_editable_email != Config::get("options/sign/editable/email")) {
                $config_sets["options"]["sign"]["editable"]["email"] = $sign_editable_email;
            }

            if ($sign_editable_gsm != Config::get("options/sign/editable/gsm")) {
                $config_sets["options"]["sign"]["editable"]["gsm"] = $sign_editable_gsm;
            }

            if ($sign_up_gsm_status != Config::get("options/sign/up/gsm/status")) {
                $config_sets["options"]["sign"]["up"]["gsm"]["status"] = $sign_up_gsm_status;
            }

            if ($sign_up_gsm_required != Config::get("options/sign/up/gsm/required")) {
                $config_sets["options"]["sign"]["up"]["gsm"]["required"] = $sign_up_gsm_required;
            }

            if ($sign_up_gsm_checker != Config::get("options/sign/up/gsm/checker")) {
                $config_sets["options"]["sign"]["up"]["gsm"]["checker"] = $sign_up_gsm_checker;
            }

            if ($sign_up_gsm_verify != Config::get("options/sign/up/gsm/verify")) {
                $config_sets["options"]["sign"]["up"]["gsm"]["verify"] = $sign_up_gsm_verify;
            }


            if ($sign_up_landline_phone_status != Config::get("options/sign/up/landline-phone/status")) {
                $config_sets["options"]["sign"]["up"]["landline-phone"]["status"] = $sign_up_landline_phone_status;
            }

            if ($sign_up_landline_phone_required != Config::get("options/sign/up/landline-phone/required")) {
                $config_sets["options"]["sign"]["up"]["landline-phone"]["required"] = $sign_up_landline_phone_required;
            }

            if ($sign_up_landline_phone_checker != Config::get("options/sign/up/landline-phone/checker")) {
                $config_sets["options"]["sign"]["up"]["landline-phone"]["checker"] = $sign_up_landline_phone_checker;
            }

            if ($sign_editable_landline_phone != Config::get("options/sign/editable/landline_phone")) {
                $config_sets["options"]["sign"]["editable"]["landline_phone"] = $sign_editable_landline_phone;
            }

            if ($sign_up_identity_status != Config::get("options/sign/up/kind/individual/identity/status")) {
                $config_sets["options"]["sign"]["up"]["kind"]["individual"]["identity"]["status"] = $sign_up_identity_status;
            }

            if ($sign_up_identity_required != Config::get("options/sign/up/kind/individual/identity/required")) {
                $config_sets["options"]["sign"]["up"]["kind"]["individual"]["identity"]["required"] = $sign_up_identity_required;
            }

            if ($sign_up_identity_checker != Config::get("options/sign/up/kind/individual/identity/checker")) {
                $config_sets["options"]["sign"]["up"]["kind"]["individual"]["identity"]["checker"] = $sign_up_identity_checker;
            }

            if ($sign_editable_identity != Config::get("options/sign/editable/identity")) {
                $config_sets["options"]["sign"]["editable"]["identity"] = $sign_editable_identity;
            }


            if ($sign_in_status != Config::get("options/sign/in/status")) {
                $config_sets["options"]["sign"]["in"]["status"] = $sign_in_status;
            }

            if ($sign_up_status != Config::get("options/sign/up/status")) {
                $config_sets["options"]["sign"]["up"]["status"] = $sign_up_status;
            }


            if ($sign_up_kind_status != Config::get("options/sign/up/kind/status")) {
                $config_sets["options"]["sign"]["up"]["kind"]["status"] = $sign_up_kind_status;
            }

            if ($sign_editable_kind != Config::get("options/sign/editable/kind")) {
                $config_sets["options"]["sign"]["editable"]["kind"] = $sign_editable_kind;
            }

            if ($sign_birthday_status != Config::get("options/sign/birthday/status")) {
                $config_sets["options"]["sign"]["birthday"]["status"] = $sign_birthday_status;
            }

            if ($sign_birthday_required != Config::get("options/sign/birthday/required")) {
                $config_sets["options"]["sign"]["birthday"]["required"] = $sign_birthday_required;
            }

            if ($sign_birthday_adult_verify != Config::get("options/sign/birthday/adult_verify")) {
                $config_sets["options"]["sign"]["birthday"]["adult_verify"] = $sign_birthday_adult_verify;
            }

            if ($sign_editable_birthday != Config::get("options/sign/editable/birthday")) {
                $config_sets["options"]["sign"]["editable"]["birthday"] = $sign_editable_birthday;
            }

            if ($security_question_status != Config::get("options/sign/security-question/status"))
                $config_sets["options"]["sign"]["security-question"]["status"] = $security_question_status;

            if ($security_question_required != Config::get("options/sign/security-question/required"))
                $config_sets["options"]["sign"]["security-question"]["required"] = $security_question_required;


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
                    User::addAction($adata["id"], "alteration", "changed-membership-settings");
                }
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);
        }


        private function set_currencies_rate()
        {
            Helper::Load("Money");

            $currencies = $this->model->get_currencies();
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
                } else
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => $rates,
                    ]));
            }
            if ($changes) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-currency-rates");
                self::$cache->clear("currencies");
            }
        }


        private function update_localization_settings()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $language = (string)Filter::init("POST/default_language", "route");
            $local = (string)Filter::init("POST/local", "route");
            $currency = (int)Filter::init("POST/currency", "numbers");
            $country = (string)Filter::init("POST/country", "route");
            $date_f = (string)Filter::init("POST/date-format", "hclear");
            $timezone = (string)Filter::init("POST/timezone", "route", "\/");
            $ip_api = (string)Filter::init("POST/ip_api", "route");
            $ip_api_config = Filter::POST("ip_api_config");
            if (!$ip_api_config) $ip_api_config = [];

            $configs = [];

            if (!Validation::isEmpty($language) && $language != Config::get("general/language"))
                $configs["general"]["language"] = $language;

            if (!Validation::isEmpty($local) && $local != Config::get("general/local"))
                $configs["general"]["local"] = $local;

            if (!Validation::isEmpty($currency) && $currency != Config::get("general/currency"))
                $configs["general"]["currency"] = $currency;

            if (!Validation::isEmpty($country) && $country != Config::get("general/country"))
                $configs["general"]["country"] = $country;

            if (!Validation::isEmpty($timezone) && $timezone != Config::get("general/timezone"))
                $configs["general"]["timezone"] = $timezone;

            if ($date_f != Config::get("options/date-format")) $configs["options"]["date-format"] = $date_f;

            if ($ip_module = Modules::Load("IP", $ip_api, true)) {
                if (Config::get("modules/ip") !== $ip_api) $configs["modules"]["ip"] = $ip_api;
                $get_config = isset($ip_module["config"]) ? $ip_module["config"] : [];
                $get_config = array_replace_recursive($get_config, $ip_api_config);

                $var_export = Utility::array_export($get_config, ['pwith' => true]);
                FileManager::file_write(MODULE_DIR . "IP" . DS . $ip_api . DS . "config.php", $var_export);
            }


            if ($configs) {

                if ($local != Config::get("general/local")) {
                    $old_local_file = LANG_DIR . Config::get("general/local") . DS . "package.php";
                    $new_local_file = LANG_DIR . $local . DS . "package.php";

                    $old_local = include $old_local_file;
                    $new_local = include $new_local_file;

                    $old_local["local"] = false;
                    $old_local["prefix"] = "enabled";

                    $new_local["local"] = true;
                    $new_local["prefix"] = "disabled";

                    $old_local_code = Utility::array_export($old_local, ['pwith' => true]);
                    $new_local_code = Utility::array_export($new_local, ['pwith' => true]);

                    FileManager::file_write($old_local_file, $old_local_code);
                    FileManager::file_write($new_local_file, $new_local_code);
                }

                if ($country !== 'tr') {
                    $configs["options"]["sign"]["up"]["kind"]["individual"]["identity"]["status"] = 0;
                    $configs["options"]["sign"]["up"]["kind"]["individual"]["identity"]["required"] = 0;
                    $configs["options"]["sign"]["up"]["kind"]["individual"]["identity"]["checker"] = 0;
                }


                $changes = 0;

                if (isset($configs["general"])) {
                    $general_result = Config::set("general", $configs["general"]);
                    $var_export = Utility::array_export($general_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "general.php", $var_export);
                    if ($write) $changes++;

                    if (isset($configs["general"]["currency"])) {
                        $this->model->db->update("currencies", ['local' => 0])->save();
                        $this->model->db->update("currencies", ['local' => 1, 'rate' => 1])->where("id", "=", $configs["general"]["currency"])->save();
                        $this->set_currencies_rate();
                    }

                }

                if (isset($configs["options"])) {
                    $options_result = Config::set("options", $configs["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                    if ($write) $changes++;
                }
                if (isset($configs["modules"])) {
                    $modules_result = Config::set("modules", $configs["modules"]);
                    $var_export = Utility::array_export($modules_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "modules.php", $var_export);
                    if ($write) $changes++;
                }

                if ($changes) {
                    $adata = UserManager::LoginData("admin");
                    User::addAction($adata["id"], "alteration", "changed-localization-settings");
                }
            }


            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);

        }


        private function update_informations_settings()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $informations = Filter::POST("informations");
            $address = Filter::init("POST/address", "hclear");
            $email_addresses = Filter::init("POST/email_addresses", "hclear");
            $phone_numbers = Filter::init("POST/phone_numbers", "hclear");
            $map_latitude = Filter::init("POST/map_latitude", "numbers", ".");
            $map_longitude = Filter::init("POST/map_longitude", "numbers", ".");
            $google_api_key = Filter::init("POST/google_api_key", "hclear");
            $social_links = Filter::POST("social_links");
            $analytics_code = Filter::POST("analytics-code");
            $support_code = Filter::POST("support-code");
            $webmaster_ts_code = Filter::POST("webmaster-tools-code");
            $external_embed_code = Filter::POST("external-embed-code");
            $contact_form_status = (int)Filter::init("POST/contact_form_status", "numbers");
            $contact_form_my_phn = (int)Filter::init("POST/contact_form_mandatory_phone", "numbers");
            $contact_map_status = (int)Filter::init("POST/contact_map_status", "numbers");


            if ($informations) {
                foreach ($informations as $lkey => $info) {
                    $ldata = Bootstrap::$lang->get("constants", $lkey);
                    if ($ldata) {
                        $ldata["informations"] = $info;
                        $export = Utility::array_export($ldata, ['pwith' => true]);
                        FileManager::file_write(LANG_DIR . $lkey . DS . "constants.php", $export);
                    }
                }
            }


            $configs = [];
            $configs2 = [];


            if ($address != Config::get("contact/address"))
                $configs["contact"]["address"] = $address;

            $email_addresses = $email_addresses ? explode(",", $email_addresses) : [];
            if ($email_addresses !== Config::get("contact/email-addresses")) {
                $configs["contact"]["email-addresses"] = null;
                $configs2["contact"]["email-addresses"] = $email_addresses;
            }

            $phone_numbers = $phone_numbers ? explode(",", $phone_numbers) : [];
            if ($phone_numbers !== Config::get("contact/phone-numbers")) {
                $configs["contact"]["phone-numbers"] = null;
                $configs2["contact"]["phone-numbers"] = $phone_numbers;
            }

            if ($map_latitude != Config::get("contact/maps/latitude"))
                $configs["contact"]["maps"]["latitude"] = $map_latitude;

            if ($map_longitude != Config::get("contact/maps/longitude"))
                $configs["contact"]["maps"]["longitude"] = $map_longitude;

            if ($google_api_key != Config::get("contact/google-api-key"))
                $configs["contact"]["google-api-key"] = $google_api_key;

            if ($contact_form_status != Config::get("options/contact-form"))
                $configs["options"]["contact-form"] = $contact_form_status;

            if ($contact_form_my_phn != Config::get("options/contact-form-mandatory-phone"))
                $configs["options"]["contact-form-mandatory-phone"] = $contact_form_my_phn;

            if ($contact_map_status != Config::get("options/contact-map"))
                $configs["options"]["contact-map"] = $contact_map_status;

            $soccials = [];

            if ($social_links && is_array($social_links)) {
                for ($i = 0; $i <= sizeof($social_links["name"]) - 1; $i++) {
                    $icon = $social_links["icon"][$i];
                    $name = $social_links["name"][$i];
                    $url = $social_links["url"][$i];
                    $soccials[] = [
                        'icon' => $icon,
                        'name' => $name,
                        'url'  => $url,
                    ];
                }
            }

            if ($soccials !== Config::get("contact/social-links")) {
                $configs["contact"]["social-links"] = null;
                $configs2["contact"]["social-links"] = $soccials;
            }

            if ($analytics_code != Config::get("info/analytics-code"))
                $configs["info"]["analytics-code"] = $analytics_code;

            if ($support_code != Config::get("info/support-code"))
                $configs["info"]["support-code"] = $support_code;

            if ($webmaster_ts_code != Config::get("info/webmaster-tools-code"))
                $configs["info"]["webmaster-tools-code"] = $webmaster_ts_code;

            if ($external_embed_code != Config::get("info/external-embed-code"))
                $configs["info"]["external-embed-code"] = $external_embed_code;


            if ($configs) {

                $changes = 0;

                if (isset($configs["contact"])) {
                    $contact_result = Config::set("contact", $configs["contact"]);
                    if (isset($configs2["contact"])) $contact_result = Config::set("contact", $configs2["contact"]);
                    $var_export = Utility::array_export($contact_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "contact.php", $var_export);
                    if ($write) $changes++;
                }

                if (isset($configs["info"])) {
                    $info_result = Config::set("info", $configs["info"]);
                    if (isset($configs2["info"])) $info_result = Config::set("info", $configs2["info"]);
                    $var_export = Utility::array_export($info_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "info.php", $var_export);
                    if ($write) $changes++;
                }

                if (isset($configs["options"])) {
                    $options_result = Config::set("options", $configs["options"]);
                    if (isset($configs2["options"]) && $configs2["options"]) $options_result = Config::set("options", $configs2["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                    if ($write) $changes++;
                }

                if ($changes) {
                    $adata = UserManager::LoginData("admin");
                    User::addAction($adata["id"], "alteration", "changed-informations-settings");
                }
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);

        }


        private function update_seo_settings()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $lang = Filter::init("POST/lang", "route");
            $title = Filter::init("POST/title", "hclear");
            $title_suffix = Filter::init("POST/title-suffix", "hclear");
            $keywords = Filter::init("POST/keywords", "hclear");
            $description = Filter::init("POST/description", "hclear");
            $rich_url = (bool)Filter::init("POST/rich-url", "numbers");
            if ($rich_url)
                $rich_url = "on";
            else
                $rich_url = "off";


            if (!Bootstrap::$lang->LangExists($lang)) die();

            $config_sets = [];
            $lsets = [];
            $lfsets = [];


            if ($rich_url != Config::get("general/rich-url")) {
                if ($rich_url == "on") {
                    $request = Utility::HttpRequest(APP_URI . "/mod-rewrite-test", [
                        'timeout' => 10,
                    ]);
                    if ($request != "OK") {
                        echo Utility::jencode([
                            'status'  => "error",
                            'message' => strlen(Utility::$error) > 0 ? Utility::$error : __("admin/settings/seo-permalink-error"),
                        ]);
                        return false;
                    }
                }

                $config_sets["general"]["rich-url"] = $rich_url;
            }


            if ($title != __("website/index/meta/title", false, $lang))
                $lfsets["website"]["index"]["meta"]["title"] = $title;

            if ($title_suffix != __("website/index/meta/title-suffix", false, $lang))
                $lfsets["website"]["index"]["meta"]["title-suffix"] = $title_suffix;

            if ($keywords != __("website/index/meta/keywords", false, $lang))
                $lfsets["website"]["index"]["meta"]["keywords"] = $keywords;

            if ($description != __("website/index/meta/description", false, $lang))
                $lfsets["website"]["index"]["meta"]["description"] = $description;

            if ($lfsets || $lsets || $config_sets) {
                $changes = 0;

                if (isset($lfsets["website"]["index"])) {
                    $arr_result = array_replace_recursive(__("website/index", false, $lang), $lfsets["website"]["index"]);
                    $var_export = Utility::array_export($arr_result, ['pwith' => true]);
                    $write = FileManager::file_write(LANG_DIR . $lang . DS . "cm" . DS . "website/index.php", $var_export);
                    if ($write) $changes++;
                }

                if (isset($lsets["package"])) {
                    $arr_result = array_replace_recursive(___("package", false, $lang), $lsets["package"]);
                    $var_export = Utility::array_export($arr_result, ['pwith' => true]);
                    $write = FileManager::file_write(LANG_DIR . $lang . DS . "package.php", $var_export);
                    if ($write) $changes++;
                }

                if (isset($config_sets["general"])) {
                    $general_result = Config::set("general", $config_sets["general"]);
                    $var_export = Utility::array_export($general_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "general.php", $var_export);
                    if ($write) $changes++;
                }

                if ($changes) {
                    $adata = UserManager::LoginData("admin");
                    User::addAction($adata["id"], "alteration", "changed-seo-settings");
                    self::$cache->clear();
                }
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);

        }


        private function update_seo_routes_settings()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $lang = Filter::init("POST/lang", "route");
            $routes = Filter::POST("routes");

            if (!Bootstrap::$lang->LangExists($lang)) die();


            if (!is_array($routes)) die();

            $oroutes = ___("website-routes/website-routes", false, $lang);

            foreach ($routes as $key => $route) $oroutes[$key][0] = $route;

            $array = ['website-routes' => $oroutes];
            $var_export = Utility::array_export($array, ['pwith' => true]);
            $write = FileManager::file_write(LANG_DIR . $lang . DS . "website-routes.php", $var_export);

            if ($write) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-seo-routes-settings");
                self::$cache->clear();
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);

        }


        private function restore_default_seo_routes_settings()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $lang = Filter::init("POST/lang", "route");

            if (!Bootstrap::$lang->LangExists($lang)) die();

            FileManager::file_delete(LANG_DIR . $lang . DS . "website-routes.php");
            FileManager::file_copy(LANG_DIR . $lang . DS . "website-routes-default.php", LANG_DIR . $lang . DS . "website-routes.php");

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "restore-seo-routes-settings");

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success3")]);
        }


        private function update_backgrounds_settings()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $files = [
                'header-backgrounds' => [
                    "page-normal-header-background"      => "page_normal",
                    "page-articles-header-background"    => "page_articles",
                    "page-news-header-background"        => "page_news",
                    "page-software-header-background"    => "page_software",
                    "articles-header-background"         => "articles",
                    "references-header-background"       => "references",
                    "news-header-background"             => "news",
                    "softwares-header-background"        => "softwares",
                    "knowledgebase-header-background"    => "knowledgebase",
                    "contact-header-background"          => "contact",
                    "domain-header-background"           => "domain",
                    "basket-header-background"           => "basket",
                    "order-steps-header-background"      => "order-steps",
                    "account-header-background"          => "account",
                    "account-sms-header-background"      => "account_sms",
                    "hosting-header-background"          => "hosting",
                    "server-header-background"           => "server",
                    "special-products-header-background" => "special-products",
                    "sms-header-background"              => "sms",
                    "license-header-background"          => "license",
                    "404-header-background"              => "404",
                ],
            ];


            Helper::Load(["Uploads", "Image"]);

            $changes = 0;
            $hbackground = Config::get("pictures/header-background");
            $hfolder = $hbackground["folder"];
            $hsizing = $hbackground["sizing"];
            foreach ($files["header-backgrounds"] as $k => $v) {
                $file = Filter::FILES($k);
                if ($file) {
                    $file_name = $k;
                    $upload = Helper::get("Uploads");
                    $upload->init($file, [
                        'image-upload' => true,
                        'date'         => false,
                        'folder'       => $hfolder,
                        'width'        => $hsizing["width"],
                        'height'       => $hsizing["height"],
                        'allowed-ext'  => "image/*,svg",
                        'file-name'    => $file_name,
                    ]);
                    if (!$upload->processed())
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "input[name='" . $file_name . "']",
                            'message' => __("admin/settings/error1", ['{error}' => $upload->error]),
                        ]));
                    $picture = current($upload->operands);
                    $picture = $picture["file_path"];
                    $this->model->set_picture_data($v, "header-background", $picture);
                    Image::set($hfolder . $picture, $hfolder . "thumb" . DS, false, 331, 100);


                    $changes++;
                }
            }

            if ($changes) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-backgrounds-settings");
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);

        }


        private function update_other_settings()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $easy_order = (int)Filter::init("POST/easy-order", "numbers");
            $order_renewal_type = Filter::init("POST/order-renewal-type", "letters");
            $viwseebasket = (int)Filter::init("POST/viwseebasket", "numbers");
            $cletwzmoy = (int)Filter::init("POST/cletwzmoy", "numbers");
            $use_coupon = (int)Filter::init("POST/use_coupon", "numbers");
            $pagination_ranks = (int)Filter::init("POST/pagination_ranks", "numbers");
            $limit_news = (int)Filter::init("POST/limit_news", "numbers");
            $limit_articles = (int)Filter::init("POST/limit_articles", "numbers");
            $limit_sidebar_categories = (int)Filter::init("POST/limit_sidebar-categories", "numbers");
            $limit_sidebar_articles_mrd = (int)Filter::init("POST/limit_sidebar-articles-most-read", "numbers");
            $limit_sidebar_news_mostre = (int)Filter::init("POST/limit_sidebar-news-most-read", "numbers");
            $limit_sidebar_normal_mread = (int)Filter::init("POST/limit_sidebar-normal-most-read", "numbers");
            $limit_knowledgebase = (int)Filter::init("POST/limit_knowledgebase", "numbers");
            $limit_softwares = (int)Filter::init("POST/limit_softwares", "numbers");
            $limit_most_popular_softws = (int)Filter::init("POST/limit_most-popular-softwares", "numbers");
            $limit_account_dashboardnws = (int)Filter::init("POST/limit_account-dashboard-news", "numbers");
            $limit_account_dashboardacy = (int)Filter::init("POST/limit_account-dashboard-activity", "numbers");
            $pg_activation = Filter::POST("pg-activation");
            $cookie_policy_s = (int)Filter::init("POST/cookie-policy-status", "numbers");
            $cookie_policy_p = (int)Filter::init("POST/cookie-policy-page", "numbers");
            $redirect_https = (int)Filter::init("POST/redirect-https", "numbers");
            $redirect_www = (int)Filter::init("POST/redirect-www", "numbers");
            $ticket_system = (int)Filter::init("POST/ticket-system", "numbers");
            $kbase_system = (int)Filter::init("POST/kbase-system", "numbers");
            $invoice_system = (int)Filter::init("POST/invoice-system", "numbers");
            $basket_system = (int)Filter::init("POST/basket-system", "numbers");
            $ctoc_service_transfer = (bool)(int)Filter::init("POST/ctoc-service-transfer", "numbers");
            $voice_notification = (bool)(int)Filter::init("POST/voice-notification", "numbers");
            $voice_notification_mp3 = Filter::init("FILES/voice-notification-mp3");
            $voice_notification_ogg = Filter::init("FILES/voice-notification-ogg");
            $g_users_auto_increment = (int)Filter::init("POST/users_auto_increment", "numbers");
            $g_orders_auto_increment = (int)Filter::init("POST/orders_auto_increment", "numbers");
            $g_tickets_auto_increment = (int)Filter::init("POST/tickets_auto_increment", "numbers");
            $accessibility = (int)Filter::init("POST/accessibility", "numbers");


            $db_name = Config::get("database/name");

            $last_id = $this->model->db->query('SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = "' . $db_name . '" AND TABLE_NAME = "users"');
            $last_id = $this->model->db->getAssoc($last_id);
            $users_auto_increment = $last_id["AUTO_INCREMENT"];

            $last_id = $this->model->db->query('SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = "' . $db_name . '" AND TABLE_NAME = "users_products"');
            $last_id = $this->model->db->getAssoc($last_id);
            $orders_auto_increment = $last_id["AUTO_INCREMENT"];


            $last_id = $this->model->db->query('SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = "' . $db_name . '" AND TABLE_NAME = "tickets"');
            $last_id = $this->model->db->getAssoc($last_id);
            $tickets_auto_increment = $last_id["AUTO_INCREMENT"];


            $config_sets = [];
            $config_sets2 = [];

            if ($ctoc_service_transfer !== Config::get("options/ctoc-service-transfer/status"))
                $config_sets["options"]["ctoc-service-transfer"]["status"] = $ctoc_service_transfer;

            if ($voice_notification !== Config::get("options/voice-notification"))
                $config_sets["options"]["voice-notification"] = $voice_notification;

            Helper::Load("Uploads");


            $sound_folder = RESOURCE_DIR . "assets" . DS . "sounds" . DS;
            $file_name = "bubble";

            if ($voice_notification_mp3) {
                $upload = Helper::get("Uploads");
                $upload->init($voice_notification_mp3, [
                    'date'         => false,
                    'image-upload' => false,
                    'folder'       => $sound_folder,
                    'allowed-ext'  => "mp3",
                    'file-name'    => $file_name,
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='voice-notification-mp3']",
                        'message' => __("admin/settings/error1", ['{error}' => $upload->error]),
                    ]));
            }
            if ($voice_notification_ogg) {
                $upload = Helper::get("Uploads");
                $upload->init($voice_notification_ogg, [
                    'image-upload' => false,
                    'folder'       => $sound_folder,
                    'allowed-ext'  => "ogg",
                    'file-name'    => $file_name,
                    'date'         => false,
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='voice-notification-ogg']",
                        'message' => __("admin/settings/error1", ['{error}' => $upload->error]),
                    ]));
            }

            foreach (Config::get("options/pg-activation") as $k => $v) {
                $stat = isset($pg_activation[$k]) ? 1 : 0;
                if ($stat != $v) $config_sets["options"]["pg-activation"][$k] = $stat;
            }

            if ($easy_order != Config::get("options/easy-order")) {
                $config_sets["options"]["easy-order"] = $easy_order;
            }

            if ($order_renewal_type != Config::get("options/order-renewal-type")) {
                $config_sets["options"]["order-renewal-type"] = $order_renewal_type;
            }

            if ($viwseebasket != Config::get("options/visitors-will-see-basket")) {
                $config_sets["options"]["visitors-will-see-basket"] = $viwseebasket;
            }

            if ($cletwzmoy != Config::get("options/clear-end-two-zero-money")) {
                $config_sets["options"]["clear-end-two-zero-money"] = $cletwzmoy;
            }

            if ($use_coupon != Config::get("options/use-coupon")) {
                $config_sets["options"]["use-coupon"] = $use_coupon;
            }

            if ($pagination_ranks != Config::get("options/pagination-ranks")) {
                $config_sets["options"]["pagination-ranks"] = $pagination_ranks;
            }

            if ($limit_news != Config::get("options/limits/news")) {
                $config_sets["options"]["limits"]["news"] = $limit_news;
            }

            if ($limit_articles != Config::get("options/limits/articles")) {
                $config_sets["options"]["limits"]["articles"] = $limit_articles;
            }

            if ($limit_sidebar_categories != Config::get("options/limits/sidebar-categories")) {
                $config_sets["options"]["limits"]["sidebar-categories"] = $limit_sidebar_categories;
            }

            if ($limit_sidebar_articles_mrd != Config::get("options/limits/sidebar-articles-most-read")) {
                $config_sets["options"]["limits"]["sidebar-articles-most-read"] = $limit_sidebar_articles_mrd;
            }

            if ($limit_sidebar_news_mostre != Config::get("options/limits/sidebar-news-most-read")) {
                $config_sets["options"]["limits"]["sidebar-news-most-read"] = $limit_sidebar_news_mostre;
            }

            if ($limit_sidebar_normal_mread != Config::get("options/limits/sidebar-normal-most-read")) {
                $config_sets["options"]["limits"]["sidebar-normal-most-read"] = $limit_sidebar_normal_mread;
            }

            if ($limit_knowledgebase != Config::get("options/limits/knowledgebase")) {
                $config_sets["options"]["limits"]["knowledgebase"] = $limit_knowledgebase;
            }

            if ($limit_softwares != Config::get("options/limits/softwares")) {
                $config_sets["options"]["limits"]["softwares"] = $limit_softwares;
            }

            if ($limit_most_popular_softws != Config::get("options/limits/most-popular-softwares")) {
                $config_sets["options"]["limits"]["most-popular-softwares"] = $limit_most_popular_softws;
            }

            if ($limit_account_dashboardnws != Config::get("options/limits/account-dashboard-news")) {
                $config_sets["options"]["limits"]["account-dashboard-news"] = $limit_account_dashboardnws;
            }

            if ($limit_account_dashboardacy != Config::get("options/limits/account-dashboard-activity")) {
                $config_sets["options"]["limits"]["account-dashboard-activity"] = $limit_account_dashboardacy;
            }

            if ($cookie_policy_s != Config::get("options/cookie-policy/status"))
                $config_sets["options"]["cookie-policy"]["status"] = $cookie_policy_s;

            if ($cookie_policy_p != Config::get("options/cookie-policy/page"))
                $config_sets["options"]["cookie-policy"]["page"] = $cookie_policy_p;

            if ($redirect_https != Config::get("options/redirect-https")) {
                $config_sets["options"]["redirect-https"] = $redirect_https;
            }

            if ($redirect_www != Config::get("options/redirect-www")) {
                $config_sets["options"]["redirect-www"] = $redirect_www;
            }

            if ($ticket_system != Config::get("options/ticket-system")) {
                $config_sets["options"]["ticket-system"] = $ticket_system;
            }


            if ($kbase_system != Config::get("options/kbase-system")) {
                $config_sets["options"]["kbase-system"] = $kbase_system;
            }

            if ($invoice_system != Config::get("options/invoice-system")) {
                $config_sets["options"]["invoice-system"] = $invoice_system;
            }

            if ($basket_system != Config::get("options/basket-system")) {
                $config_sets["options"]["basket-system"] = $basket_system;
            }

            if ($accessibility != Config::get("options/accessibility"))
                $config_sets["options"]["accessibility"] = $accessibility;


            $changes = 0;

            $users_last_id = $this->model->db->select("id")->from("users")->order_by("id DESC")->limit(1);
            $users_last_id = $users_last_id->build() ? $users_last_id->getObject()->id : 0;

            $orders_last_id = $this->model->db->select("id")->from("users_products")->order_by("id DESC")->limit(1);
            $orders_last_id = $orders_last_id->build() ? $orders_last_id->getObject()->id : 0;


            $tickets_last_id = $this->model->db->select("id")->from("tickets")->order_by("id DESC")->limit(1);
            $tickets_last_id = $tickets_last_id->build() ? $tickets_last_id->getObject()->id : 0;

            foreach (['users', 'orders', 'tickets'] as $v) {
                if (${"g_" . $v . "_auto_increment"} <= ${$v . "_last_id"}) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/settings/starting-id-error", [
                            '{id}'      => __("admin/settings/starting-id-number-" . $v),
                            '{last_id}' => ${$v . "_last_id"},
                        ]),
                    ]);
                    return false;
                }
                $t_n = $v;
                if ($t_n == "orders") $t_n = "users_products";
                if (!$this->model->db->query('ALTER TABLE ' . $t_n . ' AUTO_INCREMENT=' . ${"g_" . $v . "_auto_increment"} . ';')) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => $this->model->db->_error,
                    ]);
                    return false;
                }
            }


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
                    User::addAction($adata["id"], "alteration", "changed-other-settings");
                }
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);


        }


        private function update_WFraud()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $proxy_block = (bool)Filter::init("POST/proxy-block", "numbers");
            $proxy_block_host = Filter::init("POST/proxy-block-host", "hclear");
            $proxy_block_whitelist = Filter::init("POST/proxy-block-whitelist", "hclear");
            $status = (bool)(int)Filter::init("POST/status", "numbers");
            $use_producer_as_source = (bool)(int)Filter::init("POST/use-producer-as-source", "numbers");
            $order_blocking = (bool)(int)Filter::init("POST/order-blocking", "numbers");
            $risk_score = (int)Filter::init("POST/risk-score", "numbers");
            $ip_country_mismatch = (bool)(int)Filter::init("POST/ip-country-mismatch", "numbers");

            $config_sets = [];
            $config_sets2 = [];

            if ($status != Config::get("options/blacklist/status"))
                $config_sets["options"]['blacklist']['status'] = $status;

            if ($use_producer_as_source != Config::get("options/blacklist/use-producer-as-source"))
                $config_sets["options"]['blacklist']['use-producer-as-source'] = $use_producer_as_source;

            if ($order_blocking != Config::get("options/blacklist/order-blocking"))
                $config_sets["options"]['blacklist']['order-blocking'] = $order_blocking;

            if ($ip_country_mismatch != Config::get("options/blacklist/ip-country-mismatch"))
                $config_sets["options"]['blacklist']['ip-country-mismatch'] = $ip_country_mismatch;

            if ($proxy_block != Config::get("options/proxy-block")) {
                $config_sets["options"]["proxy-block"] = $proxy_block;
            }

            if ($proxy_block_host != Config::get("options/proxy-block-host")) {
                $config_sets["options"]["proxy-block-host"] = $proxy_block_host;
            }

            if ($proxy_block_whitelist != Config::get("options/proxy-block-whitelist")) {
                $config_sets["options"]["proxy-block-whitelist"] = $proxy_block_whitelist;
            }

            if ($risk_score != Config::get("options/blacklist/risk-score"))
                $config_sets["options"]['blacklist']['risk-score'] = $risk_score;


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
                    self::$cache->clear();
                    $adata = UserManager::LoginData("admin");
                    User::addAction($adata["id"], "alteration", "Changed WFraud settings");
                }
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);

        }

        private function update_fraud_protection()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $status = (boolean)(int)Filter::init("POST/fields/status", "numbers");
            $fields = Filter::init("POST/fields");
            $key = Filter::init("POST/module", "route");

            if (isset($fields["status"])) unset($fields["status"]);

            if (!$key) die("Not Found Module");

            $module = Modules::Load("Fraud", $key);

            if (!$module) die("Not Found Module");

            $key_x = "Fraud_" . $key;

            if (!class_exists($key_x)) die("Not Found Module");

            $obj = new $key_x;

            if (method_exists($obj, 'save_fields')) {
                $fields = $obj->save_fields($fields);

                if (!$fields && $obj->error)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => $obj->error,
                    ]));
            }

            $config = $obj->config;
            $config["settings"] = $fields;

            if ($status != $config["status"] && $status && method_exists($obj, 'activate')) {
                $activate = $obj->activate();
                if (!$activate)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => $obj->error,
                    ]));
            } elseif ($status != $config["status"] && !$status && method_exists($obj, 'deactivate')) {
                $deactivate = $obj->deactivate();
                if (!$deactivate)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => $obj->error,
                    ]));
            }

            $config['status'] = $status;

            if (method_exists($obj, 'save_config')) $obj->save_config($config);

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);

        }

        private function update_security_settings()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $admin_folder = (string)Filter::init("POST/admin_folder", "route");
            $two_factor_verification = (bool)Filter::init("POST/two-factor-verification", "numbers");
            $two_factor_verification_admin = (bool)Filter::init("POST/two-factor-verification-admin", "numbers");
            $location_verification = (bool)Filter::init("POST/location-verification", "numbers");
            $location_verification_type = Filter::init("POST/location-verification-type", "letters");
            $cache = (bool)Filter::init("POST/cache", "numbers");

            $prfiext = (string)Filter::init("POST/prfiext", "hclear");
            $attext = (string)Filter::init("POST/attext", "hclear");
            $prfisize = (int)Filter::init("POST/prfisize", "numbers");
            $attsize = (int)Filter::init("POST/attsize", "numbers");
            $password_length = (int)Filter::init("POST/password-length", "numbers");

            $config_sets = [];
            $config_sets2 = [];
            $admin_folder_c = false;
            $admin_folder = Crypt::encode($admin_folder, "*WCP-ADMIN*" . Config::get("crypt/system"));


            if ($admin_folder != Config::get("general/admin-folder")) {
                $admin_folder_c = true;
                $config_sets["general"]["admin-folder"] = $admin_folder;
            }

            if ($two_factor_verification != Config::get("options/two-factor-verification")) {
                $config_sets["options"]["two-factor-verification"] = $two_factor_verification;
            }

            if ($two_factor_verification_admin != Config::get("options/two-factor-verification-admin")) {
                $config_sets["options"]["two-factor-verification-admin"] = $two_factor_verification_admin;
            }

            if ($location_verification != Config::get("options/location-verification")) {
                $config_sets["options"]["location-verification"] = $location_verification;
            }

            if ($location_verification_type != Config::get("options/location-verification-type")) {
                $config_sets["options"]["location-verification-type"] = $location_verification_type;
            }

            if ($cache != Config::get("general/cache")) {
                $config_sets["general"]["cache"] = $cache;
            }

            if ($prfiext != Config::get("options/product-fields-extensions")) {
                $config_sets["options"]["product-fields-extensions"] = $prfiext;
            }

            if ($attext != Config::get("options/attachment-extensions")) {
                $config_sets["options"]["attachment-extensions"] = $attext;
            }

            $prfisize_bayt = FileManager::converByte($prfisize . "MB");
            $attsize_bayt = FileManager::converByte($attsize . "MB");

            if ($prfisize_bayt != Config::get("options/product-fields-max-file-size")) {
                $config_sets["options"]["product-fields-max-file-size"] = $prfisize_bayt;
            }

            if ($attsize_bayt != Config::get("options/attachment-max-file-size")) {
                $config_sets["options"]["attachment-max-file-size"] = $attsize_bayt;
            }

            if ($password_length != Config::get("options/password-length")) {
                $config_sets["options"]["password-length"] = $password_length;
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

                if (isset($config_sets["general"])) {
                    $general_result = Config::set("general", $config_sets["general"]);
                    $var_export = Utility::array_export($general_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "general.php", $var_export);
                    if ($write) $changes++;
                }


                if ($changes) {
                    self::$cache->clear();
                    $adata = UserManager::LoginData("admin");
                    User::addAction($adata["id"], "alteration", "changed-security-settings");
                    if ($admin_folder_c) UserManager::Logout('admin');
                }
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);
        }


        private function update_security_captcha()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $captcha_status = (int)Filter::init("POST/captcha_status", "numbers");
            $captcha_type = (string)Filter::init("POST/captcha_type", "route");
            $captcha_type_data = Filter::POST("captcha_data");
            $captcha_contact_form = (int)Filter::init("POST/captcha_contact_form", "numbers");
            $captcha_software_license = (int)Filter::init("POST/captcha_software_license", "numbers");
            $captcha_sign_up = (int)Filter::init("POST/captcha_sign_up", "numbers");
            $captcha_sign_in = (int)Filter::init("POST/captcha_sign_in", "numbers");
            $captcha_sign_forget = (int)Filter::init("POST/captcha_sign_forget", "numbers");
            $captcha_cfeedback = (int)Filter::init("POST/captcha_customer_feedback", "numbers");
            $captcha_newsletter = (int)Filter::init("POST/captcha_newsletter", "numbers");
            $captcha_domain_check = (int)Filter::init("POST/captcha_domain_check", "numbers");


            $config_sets = [];
            $config_sets2 = [];

            if ($captcha_type == "") $captcha_type = "DefaultCaptcha";


            if ($captcha_status != Config::get("options/captcha/status")) {
                $config_sets["options"]["captcha"]["status"] = $captcha_status;
            }

            if ($captcha_type != Config::get("options/captcha/type")) {
                $config_sets["options"]["captcha"]["type"] = $captcha_type;
            }

            $captcha_module_data = Modules::Load("Captcha", $captcha_type);
            if ($captcha_module_data) {
                if (class_exists($captcha_type)) {
                    $init = new $captcha_type;
                    if (method_exists($init, 'save_fields')) {
                        $save = $init->save_fields($captcha_type_data);
                        if (!$save && $init->error) {
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => $init->error,
                            ]));
                        }
                        $array_export = Utility::array_export($save, ['pwith' => true]);
                        FileManager::file_write(MODULE_DIR . "Captcha" . DS . $captcha_type . DS . "config.php", $array_export);
                    }
                }
            }

            if ($captcha_contact_form != Config::get("options/captcha/contact-form")) {
                $config_sets["options"]["captcha"]["contact-form"] = $captcha_contact_form;
            }

            if ($captcha_software_license != Config::get("options/captcha/software-license")) {
                $config_sets["options"]["captcha"]["software-license"] = $captcha_software_license;
            }

            if ($captcha_sign_up != Config::get("options/captcha/sign-up")) {
                $config_sets["options"]["captcha"]["sign-up"] = $captcha_sign_up;
            }

            if ($captcha_sign_in != Config::get("options/captcha/sign-in")) {
                $config_sets["options"]["captcha"]["sign-in"] = $captcha_sign_in;
            }

            if ($captcha_sign_forget != Config::get("options/captcha/sign-forget")) {
                $config_sets["options"]["captcha"]["sign-forget"] = $captcha_sign_forget;
            }

            if ($captcha_cfeedback != Config::get("options/captcha/customer-feedback")) {
                $config_sets["options"]["captcha"]["customer-feedback"] = $captcha_cfeedback;
            }

            if ($captcha_newsletter != Config::get("options/captcha/newsletter")) {
                $config_sets["options"]["captcha"]["newsletter"] = $captcha_newsletter;
            }

            if ($captcha_domain_check != Config::get("options/captcha/domain-check")) {
                $config_sets["options"]["captcha"]["domain-check"] = $captcha_domain_check;
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
                    User::addAction($adata["id"], "alteration", "changed-security-captcha");
                }
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);

        }


        private function update_security_transaction_blocking()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $sign_up_email_verify_attempt = (int)Filter::init("POST/sign_up_email_verify_attempt", "numbers");
            $blgte_email_verify_time = (int)Filter::init("POST/blgte_email_verify_time", "numbers");
            $blgte_email_verify_period = (string)Filter::init("POST/blgte_email_verify_period", "letters");
            $sign_up_gsm_verify_attempt = (int)Filter::init("POST/sign_up_gsm_verify_attempt", "numbers");
            $blgte_gsm_verify_time = (int)Filter::init("POST/blgte_gsm_verify_time", "numbers");
            $blgte_gsm_verify_period = (string)Filter::init("POST/blgte_gsm_verify_period", "letters");
            $sign_in_attempt = (int)Filter::init("POST/sign_in_attempt", "numbers");
            $blgte_sign_in_attempt_time = (int)Filter::init("POST/blgte_sign_in_attempt_time", "numbers");
            $blgte_sign_in_attempt_period = (string)Filter::init("POST/blgte_sign_in_attempt_period", "letters");
            $sign_fpassword_attempt = (int)Filter::init("POST/sign_fpassword_attempt", "numbers");
            $blgte_fpassword_time = (int)Filter::init("POST/blgte_fpassword_time", "numbers");
            $blgte_fpassword_period = (string)Filter::init("POST/blgte_fpassword_period", "letters");
            $limit_contact_form_sending = (int)Filter::init("POST/limit_contact_form_sending", "numbers");
            $blgte_contact_form_time = (int)Filter::init("POST/blgte_contact_form_time", "numbers");
            $blgte_contact_form_period = (string)Filter::init("POST/blgte_contact_form_period", "letters");
            $limit_create_ticket_sending = (int)Filter::init("POST/limit_create_ticket_sending", "numbers");
            $blgte_create_ticket_time = (int)Filter::init("POST/blgte_create_ticket_time", "numbers");
            $blgte_create_ticket_period = (string)Filter::init("POST/blgte_create_ticket_period", "letters");
            $limit_customer_feedback_sending = (int)Filter::init("POST/limit_customer_feedback_sending", "numbers");
            $blgte_customer_feedback_time = (int)Filter::init("POST/blgte_customer_feedback_time", "numbers");
            $blgte_customer_feedback_period = (string)Filter::init("POST/blgte_customer_feedback_period", "letters");
            $limit_newsletter_sending = (int)Filter::init("POST/limit_newsletter_sending", "numbers");
            $blgte_newsletter_time = (int)Filter::init("POST/blgte_newsletter_time", "numbers");
            $blgte_newsletter_period = (string)Filter::init("POST/blgte_newsletter_period", "letters");

            $limit_domain_check = (int)Filter::init("POST/limit_domain_check", "numbers");
            $blgte_domain_check_time = (int)Filter::init("POST/blgte_domain_check_time", "numbers");
            $blgte_domain_check_period = (string)Filter::init("POST/blgte_domain_check_period", "letters");


            $config_sets = [];
            $config_sets2 = [];

            if ($sign_up_email_verify_attempt != Config::get("options/sign/up/email/verify_checking_limit")) {
                $config_sets["options"]["sign"]["up"]["email"]["verify_checking_limit"] = $sign_up_email_verify_attempt;
            }

            $blgte_email_verify = Config::get("options/blocking-times/email-verify");
            if (isset($blgte_email_verify[$blgte_email_verify_period])) {
                if ($blgte_email_verify_time != $blgte_email_verify[$blgte_email_verify_period]) {
                    $config_sets["options"]["blocking-times"]["email-verify"][$blgte_email_verify_period] = $blgte_email_verify_time;
                }
            } else {
                $config_sets["options"]["blocking-times"]["email-verify"] = null;
                $config_sets2["options"]["blocking-times"]["email-verify"] = [$blgte_email_verify_period => $blgte_email_verify_time];
            }


            if ($sign_up_gsm_verify_attempt != Config::get("options/sign/up/gsm/verify_checking_limit")) {
                $config_sets["options"]["sign"]["up"]["gsm"]["verify_checking_limit"] = $sign_up_gsm_verify_attempt;
            }

            $blgte_gsm_verify = Config::get("options/blocking-times/gsm-verify");
            if (isset($blgte_gsm_verify[$blgte_gsm_verify_period])) {
                if ($blgte_gsm_verify_time != $blgte_gsm_verify[$blgte_gsm_verify_period]) {
                    $config_sets["options"]["blocking-times"]["gsm-verify"][$blgte_gsm_verify_period] = $blgte_gsm_verify_time;
                }
            } else {
                $config_sets["options"]["blocking-times"]["gsm-verify"] = null;
                $config_sets2["options"]["blocking-times"]["gsm-verify"] = [$blgte_gsm_verify_period => $blgte_gsm_verify_time];
            }


            if ($sign_in_attempt != Config::get("options/sign/in/attempt_limit")) {
                $config_sets["options"]["sign"]["in"]["attempt_limit"] = $sign_in_attempt;
            }

            $blgte_sign_in_attempt = Config::get("options/blocking-times/sign-in-attempt");
            if (isset($blgte_sign_in_attempt[$blgte_sign_in_attempt_period])) {
                if ($blgte_sign_in_attempt_time != $blgte_sign_in_attempt[$blgte_sign_in_attempt_period]) {
                    $config_sets["options"]["blocking-times"]["sign-in-attempt"][$blgte_sign_in_attempt_period] = $blgte_sign_in_attempt_time;
                }
            } else {
                $config_sets["options"]["blocking-times"]["sign-in-attempt"] = null;
                $config_sets2["options"]["blocking-times"]["sign-in-attempt"] = [$blgte_sign_in_attempt_period => $blgte_sign_in_attempt_time];
            }

            if ($sign_fpassword_attempt != Config::get("options/sign/forget/attempt_limit")) {
                $config_sets["options"]["sign"]["forget"]["attempt_limit"] = $sign_fpassword_attempt;
            }

            $blgte_fpassword = Config::get("options/blocking-times/forget-password");
            if (isset($blgte_fpassword[$blgte_fpassword_period])) {
                if ($blgte_fpassword_time != $blgte_fpassword[$blgte_fpassword_period]) {
                    $config_sets["options"]["blocking-times"]["forget-password"][$blgte_fpassword_period] = $blgte_fpassword_time;
                }
            } else {
                $config_sets["options"]["blocking-times"]["forget-password"] = null;
                $config_sets2["options"]["blocking-times"]["forget-password"] = [$blgte_fpassword_period => $blgte_fpassword_time];
            }

            if ($limit_contact_form_sending != Config::get("options/limits/contact-form-sending")) {
                $config_sets["options"]["limits"]["contact-form-sending"] = $limit_contact_form_sending;
            }

            $blgte_contact_form = Config::get("options/blocking-times/contact-form");
            if (isset($blgte_contact_form[$blgte_contact_form_period])) {
                if ($blgte_contact_form_time != $blgte_contact_form[$blgte_contact_form_period]) {
                    $config_sets["options"]["blocking-times"]["contact-form"][$blgte_contact_form_period] = $blgte_contact_form_time;
                }
            } else {
                $config_sets["options"]["blocking-times"]["contact-form"] = null;
                $config_sets2["options"]["blocking-times"]["contact-form"] = [$blgte_contact_form_period => $blgte_contact_form_time];
            }


            if ($limit_create_ticket_sending != Config::get("options/limits/create-ticket-sending")) {
                $config_sets["options"]["limits"]["create-ticket-sending"] = $limit_create_ticket_sending;
            }

            $blgte_create_ticket = Config::get("options/blocking-times/create-ticket");
            if (isset($blgte_create_ticket[$blgte_create_ticket_period])) {
                if ($blgte_create_ticket_time != $blgte_create_ticket[$blgte_create_ticket_period]) {
                    $config_sets["options"]["blocking-times"]["create-ticket"][$blgte_create_ticket_period] = $blgte_create_ticket_time;
                }
            } else {
                $config_sets["options"]["blocking-times"]["create-ticket"] = null;
                $config_sets2["options"]["blocking-times"]["create-ticket"] = [$blgte_create_ticket_period => $blgte_create_ticket_time];
            }


            if ($limit_customer_feedback_sending != Config::get("options/limits/customer-feedback-sending")) {
                $config_sets["options"]["limits"]["customer-feedback-sending"] = $limit_customer_feedback_sending;
            }

            $blgte_customer_feedback = Config::get("options/blocking-times/customer-feedback");
            if (isset($blgte_customer_feedback[$blgte_customer_feedback_period])) {
                if ($blgte_customer_feedback_time != $blgte_customer_feedback[$blgte_customer_feedback_period]) {
                    $config_sets["options"]["blocking-times"]["customer-feedback"][$blgte_customer_feedback_period] = $blgte_customer_feedback_time;
                }
            } else {
                $config_sets["options"]["blocking-times"]["customer-feedback"] = null;
                $config_sets2["options"]["blocking-times"]["customer-feedback"] = [$blgte_customer_feedback_period => $blgte_customer_feedback_time];
            }

            if ($limit_newsletter_sending != Config::get("options/limits/newsletter-sending")) {
                $config_sets["options"]["limits"]["newsletter-sending"] = $limit_newsletter_sending;
            }

            $blgte_newsletter = Config::get("options/blocking-times/newsletter");
            if (isset($blgte_newsletter[$blgte_newsletter_period])) {
                if ($blgte_newsletter_time != $blgte_newsletter[$blgte_newsletter_period]) {
                    $config_sets["options"]["blocking-times"]["newsletter"][$blgte_newsletter_period] = $blgte_newsletter_time;
                }
            } else {
                $config_sets["options"]["blocking-times"]["newsletter"] = null;
                $config_sets2["options"]["blocking-times"]["newsletter"] = [$blgte_newsletter_period => $blgte_newsletter_time];
            }


            if ($limit_domain_check != Config::get("options/limits/domain-check")) {
                $config_sets["options"]["limits"]["domain-check"] = $limit_domain_check;
            }

            $blgte_domain_check = Config::get("options/blocking-times/domain-check");
            if (isset($blgte_domain_check[$blgte_domain_check_period])) {
                if ($blgte_domain_check_time != $blgte_domain_check[$blgte_domain_check_period]) {
                    $config_sets["options"]["blocking-times"]["domain-check"][$blgte_domain_check_period] = $blgte_domain_check_time;
                }
            } else {
                $config_sets["options"]["blocking-times"]["domain-check"] = null;
                $config_sets2["options"]["blocking-times"]["domain-check"] = [$blgte_domain_check_period => $blgte_domain_check_time];
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
                    User::addAction($adata["id"], "alteration", "changed-security-transaction-blocking");
                }
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);

        }


        private function update_security_botshield()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $status = (int)Filter::init("POST/bot-shield", "numbers");
            $within_time_duration = (int)Filter::init("POST/within-time-duration", "numbers");
            $within_time_period = Filter::init("POST/within-time-period", "letters");
            $attempts = Filter::POST("attempt");

            $config_sets = [];
            $config_sets2 = [];

            if ($status != Config::get("options/BotShield/status")) {
                $config_sets["options"]["BotShield"]["status"] = $status;
            }

            $within_time = Config::get("options/BotShield/within-time");
            if (isset($within_time[$within_time_period])) {
                if ($within_time_duration != $within_time[$within_time_period]) {
                    $config_sets["options"]["BotShield"]["within-time"][$within_time_period] = $within_time_duration;
                }
            } else {
                $config_sets["options"]["BotShield"]["within-time"] = null;
                $config_sets2["options"]["BotShield"]["within-time"] = [$within_time_period => $within_time_duration];
            }

            if ($attempts) {
                foreach ($attempts as $k => $v) {
                    if ($v != Config::get("options/BotShield/attempts/" . $k))
                        $config_sets["options"]["BotShield"]["attempts"][$k] = $v;
                }
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
                    User::addAction($adata["id"], "alteration", "changed-security-bot-shield");
                }
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);

        }


        private function clear_blocking_data()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $this->model->clear_blocking_data();

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success4")]);

        }


        private function test_ftp_connect()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $host = (string)Filter::init("POST/backup_db_ftp_host", "domain");
            $port = (int)Filter::init("POST/backup_db_ftp_port", "numbers");
            $username = (string)Filter::init("POST/backup_db_ftp_username", "hclear");
            $password = (string)Filter::init("POST/backup_db_ftp_password", "password");
            $target = (string)Filter::init("POST/backup_db_ftp_target", "route", "\/");
            $ssl = (int)Filter::init("POST/backup_db_ftp_ssl", "numbers");

            if ($password == "*****")
                $password = Crypt::decode(Config::get("cronjobs/tasks/auto-backup-db/settings/ftp-password"), Config::get("crypt/user"));


            if (Validation::isEmpty($host) || Validation::isEmpty($port) || Validation::isEmpty($username) || Validation::isEmpty($password) || Validation::isEmpty($target))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/settings/error8"),
                ]));

            $write = function ($host, $port, $username, $password, $target, $ssl, $passive, $file, $target_file) {
                if (!function_exists("ftp_connect") || !function_exists("ftp_ssl_connect"))
                    return 'Server Error: Fatal error server does not have ftp_connect method.';

                if ($ssl)
                    $con = @ftp_ssl_connect($host, $port, 5);
                else
                    $con = @ftp_connect($host, $port, 5);

                if (false === $con) return "FTP connection failed";

                $loggedIn = @ftp_login($con, $username, $password);
                if (false === $loggedIn) {
                    ftp_close($con);
                    return "FTP login failed.";
                }

                if ($passive) {
                    $change_pasv_mode = @ftp_pasv($con, true);
                    if (!$change_pasv_mode) {
                        @ftp_close($con);
                        return "Cannot set passive mode";
                    }
                }

                if (@ftp_chdir($con, $target)) @ftp_chdir($con, "/");
                else {
                    @ftp_close($con);
                    return "Cannot change directory: ".$target;
                }

                $remote_file = rtrim(str_replace("\\", "/", $target), "/") . "/";
                $remote_file .= $target_file;


                if (!file_exists($file)) {
                    @ftp_close($con);
                    return "Local file does not exist: " . $file;
                }


                if (!is_readable($file)) {
                    @ftp_close($con);
                    return "Cannot read local file: " . $file;
                }



                $file_size = filesize($file);
                if ($file_size === false) {
                    @ftp_close($con);
                    return "Cannot determine file size: " . $file;
                }

                if (function_exists('ftp_raw') && $con) {
                    $quota_response = @ftp_raw($con, 'SITE QUOTA');
                    if ($quota_response && is_array($quota_response)) {
                        foreach ($quota_response as $line) {
                            if (preg_match('/(\d+)\s+KB\s+available/i', $line, $matches)) {
                                $available_space_kb = (int)$matches[1];
                                if ($available_space_kb * 1024 < $file_size) {
                                    @ftp_close($con);
                                    return "Insufficient disk space on the FTP server.";
                                }
                            }
                        }
                    }

                }

                $upload_result = @ftp_put($con, $remote_file, $file, FTP_BINARY);
                if (!$upload_result) {
                    $error_code = error_get_last();
                    $error_detail = $error_code['message'] ?? "Unknown error";
                    @ftp_close($con);
                    return "Failed to upload file to destination directory. Error: " . $error_detail;
                }


                @ftp_close($con);
                return true;
            };

            MioException::$error_hide = true;
            $test = $write($host, $port, $username, $password, $target, $ssl, false, CORE_DIR . "VERSION", "TEST");
            if ($test && !is_bool($test))
                $test = $write($host, $port, $username, $password, $target, $ssl, true, CORE_DIR . "VERSION", "TEST");
            MioException::$error_hide = false;

            if (!is_bool($test))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => $test,
                ]));

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success5")]);

        }


        private function update_backup_settings()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $status = (bool)Filter::init("POST/backup_db_status", "numbers");
            $period_time = (int)Filter::init("POST/backup_db_period_time", "numbers");
            $period_type = (string)Filter::init("POST/backup_db_period_type", "letters");
            $notification = (int)Filter::init("POST/backup_db_notification", "rnumbers");
            $host = (string)Filter::init("POST/backup_db_ftp_host", "domain");
            $port = (int)Filter::init("POST/backup_db_ftp_port", "numbers");
            $username = (string)Filter::init("POST/backup_db_ftp_username", "hclear");
            $password = (string)Filter::init("POST/backup_db_ftp_password", "password");
            $target = (string)Filter::init("POST/backup_db_ftp_target", "route", "\/");
            $ssl = (int)Filter::init("POST/backup_db_ftp_ssl", "numbers");

            $configs = [];


            if ($status != Config::get("cronjobs/tasks/auto-backup-db/status"))
                $configs["cronjobs"]["tasks"]["auto-backup-db"]["status"] = $status;

            if ($period_time != Config::get("cronjobs/tasks/auto-backup-db/time"))
                $configs["cronjobs"]["tasks"]["auto-backup-db"]["time"] = $period_time;

            if ($period_type != Config::get("cronjobs/tasks/auto-backup-db/period"))
                $configs["cronjobs"]["tasks"]["auto-backup-db"]["period"] = $period_type;

            if ($notification != Config::get("notifications/admin-messages/created-backup-db/status"))
                $configs["notifications"]["admin-messages"]["created-backup-db"]["status"] = $notification;

            if ($host != Config::get("cronjobs/tasks/auto-backup-db/settings/ftp-host"))
                $configs["cronjobs"]["tasks"]["auto-backup-db"]["settings"]["ftp-host"] = $host;

            if ($port != Config::get("cronjobs/tasks/auto-backup-db/settings/ftp-port"))
                $configs["cronjobs"]["tasks"]["auto-backup-db"]["settings"]["ftp-port"] = $port;

            if ($username != Config::get("cronjobs/tasks/auto-backup-db/settings/ftp-username"))
                $configs["cronjobs"]["tasks"]["auto-backup-db"]["settings"]["ftp-username"] = $username;

            if (!$password) {
                $configs["cronjobs"]["tasks"]["auto-backup-db"]["settings"]["ftp-password"] = "";
            } elseif ($password != "*****")
                $configs["cronjobs"]["tasks"]["auto-backup-db"]["settings"]["ftp-password"] = Crypt::encode($password, Config::get("crypt/user"));

            if ($target != Config::get("cronjobs/tasks/auto-backup-db/settings/ftp-target"))
                $configs["cronjobs"]["tasks"]["auto-backup-db"]["settings"]["ftp-target"] = $target;

            if ($ssl != Config::get("cronjobs/tasks/auto-backup-db/settings/ftp-ssl"))
                $configs["cronjobs"]["tasks"]["auto-backup-db"]["settings"]["ftp-ssl"] = $ssl;


            if ($status != Config::get("cronjobs/tasks/auto-backup-db/status") && $status) {
                if (!function_exists("ftp_connect") || !function_exists("ftp_ssl_connect"))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => "Server Error: Fatal error server does not have ftp_connect method.",
                    ]));
            }


            if ($configs) {

                $changes = 0;

                if (isset($configs["cronjobs"])) {
                    $cronjobs_result = Config::set("cronjobs", $configs["cronjobs"]);
                    $var_export = Utility::array_export($cronjobs_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "cronjobs.php", $var_export);
                    if ($write) $changes++;
                }

                if (isset($configs["notifications"])) {
                    $notifications_result = Config::set("notifications", $configs["notifications"]);
                    $var_export = Utility::array_export($notifications_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "notifications.php", $var_export);
                    if ($write) $changes++;
                }

                if ($changes) {
                    $adata = UserManager::LoginData("admin");
                    User::addAction($adata["id"], "alteration", "changed-security-backup-settings");
                }
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);

        }

        private function get_ip_api_configs()
        {
            $this->takeDatas("language");
            $module = Filter::init("GET/module", "route");

            if (!$module || $module == 'none') return false;

            $load = Modules::Load("IP", $module);

            if (!$load) return false;

            $class = new $module();

            $data = [
                'module' => $class,
            ];
            echo Modules::getPage("IP", $module, "settings", $data);
        }

        private function update_prohibited_settings()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $block_u_t_e = Filter::init("POST/block-user-temporary-email", "numbers");
            $domain_list = Filter::init("POST/domain-list", "hclear");
            $email_list = Filter::init("POST/email-list", "hclear");
            $gsm_list = Filter::init("POST/gsm-list", "hclear");
            $word_list = Filter::init("POST/word-list", "hclear");

            if (Validation::isEmpty($domain_list)) $domain_list = '';
            if (Validation::isEmpty($email_list)) $email_list = '';
            if (Validation::isEmpty($gsm_list)) $gsm_list = '';
            if (Validation::isEmpty($word_list)) $word_list = '';

            if ($domain_list) $domain_list = array_filter(array_map('trim', explode("\n", $domain_list)));
            if ($email_list) $email_list = array_filter(array_map('trim', explode("\n", $email_list)));
            if ($gsm_list) $gsm_list = array_filter(array_map('trim', explode("\n", $gsm_list)));
            if ($word_list) $word_list = array_filter(array_map('trim', explode("\n", $word_list)));

            $config_sets = [];
            $config_sets2 = [];


            if ($block_u_t_e != Config::get("options/block-user-temporary-email"))
                $config_sets["options"]["block-user-temporary-email"] = $block_u_t_e;

            if ($domain_list != Config::get("options/prohibited/domain-list")) {
                $config_sets["options"]["prohibited"]["domain-list"] = '';
                $config_sets2["options"]["prohibited"]["domain-list"] = $domain_list;
            }

            if ($email_list != Config::get("options/prohibited/email-list")) {
                $config_sets["options"]["prohibited"]["email-list"] = '';
                $config_sets2["options"]["prohibited"]["email-list"] = $email_list;
            }

            if ($gsm_list != Config::get("options/prohibited/gsm-list")) {
                $config_sets["options"]["prohibited"]["gsm-list"] = '';
                $config_sets2["options"]["prohibited"]["gsm-list"] = $gsm_list;
            }

            if ($word_list != Config::get("options/prohibited/word-list")) {
                $config_sets["options"]["prohibited"]["word-list"] = '';
                $config_sets2["options"]["prohibited"]["word-list"] = $word_list;
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
                User::addAction($adata["id"], "alteration", "Changed security prohibited list");
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);

        }

        private function delete_spam_records()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $this->model->db->delete("last_spam_records")->run();

            FileManager::file_delete(STORAGE_DIR . "SPAM_COUNTER");


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "Cleared top 50 spam records");


            echo Utility::jencode(['status' => "successful", 'message' => __("admin/financial/success7")]);

        }


        private function update_spam_settings()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $word_list = Filter::init("POST/word-list", "hclear");
            $spam_api_status = Filter::init("POST/spam_api_status", "numbers");
            $spam_api_key = Filter::init("POST/spam_api_key", "hclear");
            $spam_block_temp_ser = Filter::init("POST/spam_block_temporary_service", "numbers");
            $spam_api_risk_score = Filter::init("POST/spam_api_risk_score", "numbers");
            $spam_contact_check_proxy = Filter::init("POST/spam_contact_check_proxy", "numbers");


            $configs = [];


            if ($word_list != Config::get("options/spam-control/word-list"))
                $configs["options"]["spam-control"]["word-list"] = $word_list;

            if ($spam_api_status != Config::get("options/spam-control/api-status"))
                $configs["options"]["spam-control"]["api-status"] = $spam_api_status;

            if ($spam_api_key != Config::get("options/spam-control/api-key"))
                $configs["options"]["spam-control"]["api-key"] = $spam_api_key;

            if ($spam_block_temp_ser != Config::get("options/spam-control/block-temporary"))
                $configs["options"]["spam-control"]["block-temporary"] = $spam_block_temp_ser;

            if ($spam_block_temp_ser != Config::get("options/spam-control/api-risk-score"))
                $configs["options"]["spam-control"]["api-risk-score"] = $spam_api_risk_score;

            if ($spam_contact_check_proxy != Config::get("options/spam-control/contact-check-proxy"))
                $configs["options"]["spam-control"]["contact-check-proxy"] = $spam_contact_check_proxy;

            if ($spam_api_status) {
                try {
                    $php = new ProjectHoneyPot($spam_api_key);
                    $results = $php->query('127.0.0.1');
                } catch (Exception $e) {
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => $e->getMessage(),
                    ]));
                }
            }


            if ($configs) {

                if (isset($configs["options"])) {
                    $options_result = Config::set("options", $configs["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                }

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "Changed spam security");
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/settings/success1")]);

        }


        private function get_captcha_content()
        {
            $this->takeDatas("language");

            $module = Filter::init("POST/module", "route");

            if (!$module) {
                echo 'Not found module name';
                return false;
            }
            $module_data = Modules::Load("Captcha", $module);

            if (!$module_data) {
                echo 'No module data content';
                return false;
            }

            if (!class_exists($module)) {
                echo "No module class file";
                return false;
            }


            $init = new $module();

            $config_fields = method_exists($init, 'config_fields') ? $init->config_fields() : [];

            if (!$config_fields) {
                return false;
            }

            Modules::fields_output($module, $config_fields, "captcha_data");


        }


        private function ajax_api_credentials()
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

            $filteredList = $this->model->get_api_credentials($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_api_credentials_total($searches);
            $listTotal = $this->model->get_api_credentials_total();

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
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-api-credentials", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);

        }


        private function ajax_api_logs()
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

            $filteredList = $this->model->get_api_logs($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_api_logs_total($searches);
            $listTotal = $this->model->get_api_logs_total();

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
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-api-logs", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function creupd_api_credential()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = Filter::init("POST/id", "numbers");
            $name = Filter::init("POST/name", "hclear");
            $ips = Filter::init("POST/ips");
            $permissions = Filter::init("POST/permissions");
            if ($ips) $ips = explode("\n", $ips);


            if (Validation::isEmpty($name))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/settings/error13"),
                ]));

            if (!$permissions || !is_array($permissions))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/settings/error14"),
                ]));


            if ($id > 0) {

                $this->model->db->update("api_credentials", [
                    'name'        => $name,
                    'permissions' => Utility::jencode($permissions),
                    'ips'         => $ips ? implode(",", $ips) : '',
                    'updated_at'  => DateManager::Now(),
                ])->where("id", "=", $id)->save();

            } else {
                $api_key = $this->generate_api_key();

                if (!$api_key)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => "API Key cannot be generated",
                    ]));

                $this->model->db->insert("api_credentials", [
                    'name'        => $name,
                    'identifier'  => $api_key,
                    'permissions' => Utility::jencode($permissions),
                    'ips'         => $ips ? implode(",", $ips) : '',
                    'created_at'  => DateManager::Now(),
                    'updated_at'  => DateManager::Now(),
                ]);
            }

            $a_data = UserManager::LoginData("admin");

            User::addAction($a_data["id"], $id > 0 ? "alteration" : "added", $id > 0 ? "Updated API Credential #" . $id : "Created API Credential");


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/settings/" . ($id > 0 ? "success10" : "success9")),
                'api_key' => $api_key ?? '',
            ]);
        }

        private function delete_api_credential()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");


            $this->model->db->delete("api_credentials")->where("id", "=", $id)->run();


            $a_data = UserManager::LoginData("admin");

            User::addAction($a_data["id"], "deleted", "Deleted API Credential #" . $id);


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/settings/success11"),
            ]);
        }

        private function delete_all_api_logs()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $this->model->db->delete("api_logs")->run();


            $a_data = UserManager::LoginData("admin");

            User::addAction($a_data["id"], "deleted", "All API logs have been cleared");


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/settings/success12"),
            ]);
        }


        private function generate_api_key($length = 32)
        {
            try {
                return bin2hex(random_bytes($length));
            } catch (Exception $e) {
                return null;
            }
        }


        private function operationMain($operation)
        {
            if ($operation == "upload_theme" && Admin::isPrivilege(['SETTINGS_THEME_CONFIGURE']))
                return $this->upload_theme();
            if ($operation == "download_theme" && Admin::isPrivilege(['SETTINGS_THEME_CONFIGURE']))
                return $this->download_theme();
            if ($operation == "remove_theme" && Admin::isPrivilege(['SETTINGS_THEME_CONFIGURE']))
                return $this->remove_theme();
            if ($operation == "apply_theme" && Admin::isPrivilege(['SETTINGS_THEME_CONFIGURE']))
                return $this->apply_theme();
            if ($operation == "upgrade_theme_version" && Admin::isPrivilege(['SETTINGS_THEME_CONFIGURE']))
                return $this->upgrade_theme_version();
            if ($operation == "update_theme_settings" && Admin::isPrivilege(['SETTINGS_THEME_CONFIGURE']))
                return $this->update_theme_settings();
            if ($operation == "update_logo" && Admin::isPrivilege(['SETTINGS_THEME_CONFIGURE']))
                return $this->update_logo();
            if ($operation == "update_theme_other_settings" && Admin::isPrivilege(['SETTINGS_THEME_CONFIGURE']))
                return $this->update_theme_other_settings();
            if ($operation == "update_blocks_ranking" && Admin::isPrivilege(['SETTINGS_HOME_CONFIGURE']))
                return $this->update_blocks_ranking();
            if ($operation == "update_blocks_status" && Admin::isPrivilege(['SETTINGS_HOME_CONFIGURE']))
                return $this->update_blocks_status();
            if ($operation == "update_block_options" && Admin::isPrivilege(['SETTINGS_HOME_CONFIGURE']))
                return $this->update_block_options();
            if ($operation == "delete_block_background_video" && Admin::isPrivilege(['SETTINGS_HOME_CONFIGURE']))
                return $this->delete_block_background_video();
            if ($operation == "add_user_custom_field" && Admin::isPrivilege(['SETTINGS_MEMBERSHIP_CONFIGURE']))
                return $this->add_user_custom_field();
            if ($operation == "update_users_custom_fields_ranking" && Admin::isPrivilege(['SETTINGS_MEMBERSHIP_CONFIGURE']))
                return $this->update_users_custom_fields_ranking();
            if ($operation == "delete_user_custom_field" && Admin::isPrivilege(['SETTINGS_MEMBERSHIP_CONFIGURE']))
                return $this->delete_user_custom_field();
            if ($operation == "save_custom_fields" && Admin::isPrivilege(['SETTINGS_MEMBERSHIP_CONFIGURE']))
                return $this->save_custom_fields();
            if ($operation == "save_membership_settings" && Admin::isPrivilege(['SETTINGS_MEMBERSHIP_CONFIGURE']))
                return $this->save_membership_settings();
            if ($operation == "update_localization_settings" && Admin::isPrivilege(['SETTINGS_LOCALIZATION_CONFIGURE']))
                return $this->update_localization_settings();
            if ($operation == "update_informations_settings" && Admin::isPrivilege(['SETTINGS_INFORMATIONS_CONFIGURE']))
                return $this->update_informations_settings();
            if ($operation == "update_seo_settings" && Admin::isPrivilege(['SETTINGS_SEO_CONFIGURE']))
                return $this->update_seo_settings();
            if ($operation == "update_seo_routes_settings" && Admin::isPrivilege(['SETTINGS_SEO_CONFIGURE']))
                return $this->update_seo_routes_settings();
            if ($operation == "restore_default_seo_routes_settings" && Admin::isPrivilege(['SETTINGS_SEO_CONFIGURE']))
                return $this->restore_default_seo_routes_settings();
            if ($operation == "update_backgrounds_settings" && Admin::isPrivilege(['SETTINGS_BACKGROUNDS_CONFIGURE']))
                return $this->update_backgrounds_settings();
            if ($operation == "update_other_settings" && Admin::isPrivilege(['SETTINGS_OTHER_CONFIGURE']))
                return $this->update_other_settings();
            if ($operation == "update_WFraud" && Admin::isPrivilege(['SETTINGS_FRAUD_PROTECTION']))
                return $this->update_WFraud();
            if ($operation == "update_fraud_protection" && Admin::isPrivilege(['SETTINGS_FRAUD_PROTECTION']))
                return $this->update_fraud_protection();
            if ($operation == "update_security_settings" && Admin::isPrivilege(['SECURITY_SETTINGS']))
                return $this->update_security_settings();
            if ($operation == "update_security_botshield" && Admin::isPrivilege(['SECURITY_SETTINGS']))
                return $this->update_security_botshield();
            if ($operation == "update_security_captcha" && Admin::isPrivilege(['SECURITY_SETTINGS']))
                return $this->update_security_captcha();
            if ($operation == "update_security_transaction_blocking" && Admin::isPrivilege(['SECURITY_SETTINGS']))
                return $this->update_security_transaction_blocking();
            if ($operation == "clear_blocking_data" && Admin::isPrivilege(['SECURITY_SETTINGS']))
                return $this->clear_blocking_data();
            if ($operation == "test_ftp_connect" && Admin::isPrivilege(['SECURITY_BACKUP']))
                return $this->test_ftp_connect();
            if ($operation == "update_backup_settings" && Admin::isPrivilege(['SECURITY_BACKUP']))
                return $this->update_backup_settings();
            if ($operation == "get_ip_api_configs" && Admin::isPrivilege(['SETTINGS_LOCALIZATION_CONFIGURE']))
                return $this->get_ip_api_configs();

            if ($operation == "update_prohibited_settings")
                return $this->update_prohibited_settings();

            if ($operation == "delete_spam_records")
                return $this->delete_spam_records();

            if ($operation == "update_spam_settings") return $this->update_spam_settings();
            if ($operation == "get_captcha_content") return $this->get_captcha_content();
            if (Admin::isPrivilege(["SETTINGS_API_CREDENTIALS"])) {
                if ($operation == "ajax-api-credentials") return $this->ajax_api_credentials();
                if ($operation == "ajax-api-logs") return $this->ajax_api_logs();
                if ($operation == "create_api_credential" || $operation == "update_api_credential") return $this->creupd_api_credential();
                if ($operation == "delete_api_credential") return $this->delete_api_credential();
                if ($operation == "delete_all_api_logs") return $this->delete_all_api_logs();
            }

            echo "Operation not found : " . $operation;
        }


        public function pageMain($name = '')
        {
            if ($name == "fraud-protection" && Admin::isPrivilege(["SETTINGS_FRAUD_PROTECTION"])) return $this->fraud_protection();
            if ($name == "security" && Admin::isPrivilege(Config::get("privileges/SECURITY"))) return $this->security_main();
            if ($name == "theme" && Admin::isPrivilege(["SETTINGS_THEME_CONFIGURE"])) return $this->theme_main();
            if ($name == "get-softwares.json" && Admin::isPrivilege(['SETTINGS_HOME_CONFIGURE'])) return $this->get_softwares_json();
            if ($name == "get-tlds.json" && Admin::isPrivilege(['SETTINGS_HOME_CONFIGURE'])) return $this->get_tlds_json();
            if ($name == "get-group-categories.json" && Admin::isPrivilege(['SETTINGS_HOME_CONFIGURE'])) return $this->get_product_group_categories_json();
            echo "Not found main: " . $name;
        }


        private function get_product_group_categories_json()
        {
            header('Content-Type: application/json');
            $response = ['results' => []];
            $query = Filter::init("GET/search", "hclear");
            $id = Filter::init("GET/id", "letters_numbers");
            $lang = Filter::init("GET/lang", "route");

            if (!Validation::isInt($id) && ($id != "hosting" && $id != "server")) {
                die("Error ID");
            }

            if (!Bootstrap::$lang->LangExists($lang)) die("Error Language");

            $results = $this->model->get_product_group_categories($id, $lang);
            if ($results) $response["results"] = $results;

            echo Utility::jencode($response);
        }


        private function get_tlds_json()
        {
            header('Content-Type: application/json');
            $response = ['results' => []];
            $query = Filter::init("GET/search", "hclear");

            $results = $this->model->get_tlds($query);
            if ($results) $response["results"] = $results;

            echo Utility::jencode($response);
        }


        private function get_softwares_json()
        {
            header('Content-Type: application/json');
            $response = ['results' => []];
            $query = Filter::init("GET/search", "hclear");
            $lang = Filter::init("GET/lang", "route");

            if (!Bootstrap::$lang->LangExists($lang)) die("Error Language");

            $results = $this->model->get_softwares($lang, $query);
            if ($results) $response["results"] = $results;

            echo Utility::jencode($response);
        }


        public function main()
        {

            if (Filter::POST("operation")) return $this->operationMain(Filter::init("POST/operation", "route"));
            if (Filter::GET("operation")) return $this->operationMain(Filter::init("GET/operation", "route"));

            if (isset($this->params[0]) && $this->params[0] != '') return $this->pageMain($this->params[0]);

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

            $this->addData("clientArea_logo_link", APP_URI . "/" . Config::get("theme/clientArea-logo"));

            $this->addData("links", [
                'controller'                => $this->AdminCRLink("settings"),
                'get-softwares-json'        => $this->AdminCRLink("settings-p", ["get-softwares.json"]),
                'get-tlds-json'             => $this->AdminCRLink("settings-p", ["get-tlds.json"]),
                'get-group-categories-json' => $this->AdminCRLink("settings-p", ["get-group-categories.json"]),
            ]);

            $this->addData("meta", __("admin/settings/meta"));


            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/settings/breadcrumb-general-name"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            Helper::Load("Money");

            $this->addData("settings", [
                'theme'   => [
                    'name'             => Config::get("theme/name"),
                    'header_type'      => Config::get("theme/header-type"),
                    'clientArea_type'  => Config::get("theme/clientArea-type"),
                    'color1'           => Config::get("theme/color1"),
                    'color2'           => Config::get("theme/color2"),
                    'tcolor'           => Config::get("theme/text-color"),
                    'onlyPanel'        => Config::get("theme/only-panel"),
                    'maintenance_mode' => Config::get("theme/maintenance-mode"),
                    'padeno'           => Config::get("options/sidebars/page-detail-normal"),
                    'padenews'         => Config::get("options/sidebars/page-detail-news"),
                    'padeart'          => Config::get("options/sidebars/page-detail-articles"),
                    'padekbs'          => Config::get("options/sidebars/page-detail-kbase"),
                ],
                'block'   => [
                    'product_groups' => $this->get_product_groups(),
                ],
                'other'   => [
                    'currencies'       => Money::getCurrencies(),
                    'timezones'        => self::timezones(),
                    'default_language' => Config::get("general/language"),
                    'local'            => Config::get("general/local"),
                    'country'          => Config::get("general/country"),
                    'currency'         => Config::get("general/currency"),
                    'timezone'         => Config::get("general/timezone"),
                ],
                'options' => [
                    'cletwzmoy'                    => Config::get("options/clear-end-two-zero-money"),
                    'use_coupon'                   => Config::get("options/use-coupon"),
                    'duse_coupon'                  => Config::get("options/dealers-can-use-coupon"),
                    'viwseebasket'                 => Config::get("options/visitors-will-see-basket"),
                    'crtacwshop'                   => Config::get("options/crtacwshop"),
                    'ctixswps'                     => Config::get("options/client-index-show-products"),
                    'intsmsser'                    => Config::get("options/international-sms-service"),
                    'contact_form_status'          => Config::get("options/contact-form"),
                    'contact_form_mandatory_phone' => Config::get("options/contact-form-mandatory-phone"),
                    'contact_map_status'           => Config::get("options/contact-map"),
                    'pagination_ranks'             => Config::get("options/pagination-ranks"),
                    'limits'                       => Config::get("options/limits"),
                    'sign'                         => Config::get("options/sign"),
                    'pg-activation'                => Config::get("options/pg-activation"),
                ],
                'contact' => Config::get("contact"),
                'ip-api'  => Config::get("modules/ip"),
            ]);


            $this->addData("functions", [
                'get_block_picture'          => function ($id) {
                    return $this->get_block_picture($id);
                },
                'get_software'               => function ($id, $lang) {
                    return $this->model->get_software($id, $lang);
                },
                'users_custom_fields'        => function ($lang = '') {
                    $data = $this->model->users_custom_fields($lang);
                    return $data;
                },
                'get_countries'              => function () {
                    $lang = Bootstrap::$lang->clang;
                    $data = $this->model->get_countries($lang);
                    return $data;
                },
                'get_product_group_category' => function ($id = 0, $lang = '') {
                    return $this->model->get_product_category($id, $lang);
                },
                'get_picture'                => function ($owner = '', $reason = '') {
                    if ($reason == "header-background") {
                        $hbackground = Config::get("pictures/header-background");
                        $hfolder = $hbackground["folder"];
                        $hsize = $hbackground["sizing"];
                        $data = $this->model->get_picture_data($owner, $reason);
                        if ($data) {
                            return [
                                'sizing' => $hsize,
                                'link'   => Utility::image_link_determiner($data["name"], $hfolder . "thumb" . DS),
                            ];
                        }
                    }

                    return false;
                },
            ]);

            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");
            $this->addData("lang_list", $lang_list);

            $ip_modules = [];
            $get_ip_modules = Modules::Load("IP", "All", true);
            if ($get_ip_modules) foreach ($get_ip_modules as $k => $v) $ip_modules[$k] = $v["config"]["website"];
            $this->addData("ip_modules", $ip_modules);

            $theme_folder = TEMPLATE_DIR . "website" . DS;
            $getThemes = FileManager::glob($theme_folder . "*", GLOB_ONLYDIR);
            $themes = [];
            if ($getThemes) {
                foreach ($getThemes as $theme) {
                    $theme_name = basename($theme);
                    $config = [];

                    if (file_exists($theme . DS . "theme-config.php")) $config = include $theme . DS . "theme-config.php";

                    if (!isset($config["meta"]["name"])) $config["meta"]["name"] = $theme_name;
                    if (!isset($config["meta"]["version"])) $config["meta"]["version"] = '1.0';
                    if (!isset($config["meta"]["provider"])) $config["meta"]["provider"] = 'None';
                    if (!isset($config["meta"]["image"])) $config["meta"]["image"] = '';
                    $themes[$theme_name] = ['config' => $config];
                }
            }
            $this->addData("themes", $themes);

            $this->addData("page_list", $this->model->page_list());

            $db_name = Config::get("database/name");

            $last_id = $this->model->db->query('SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = "' . $db_name . '" AND TABLE_NAME = "users"');
            $last_id = $this->model->db->getAssoc($last_id);
            $users_auto_increment = $last_id["AUTO_INCREMENT"];

            $last_id = $this->model->db->query('SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = "' . $db_name . '" AND TABLE_NAME = "users_products"');
            $last_id = $this->model->db->getAssoc($last_id);
            $orders_auto_increment = $last_id["AUTO_INCREMENT"];


            $last_id = $this->model->db->query('SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = "' . $db_name . '" AND TABLE_NAME = "tickets"');
            $last_id = $this->model->db->getAssoc($last_id);
            $tickets_auto_increment = $last_id["AUTO_INCREMENT"];

            $this->addData("users_auto_increment", $users_auto_increment);
            $this->addData("orders_auto_increment", $orders_auto_increment);
            $this->addData("tickets_auto_increment", $tickets_auto_increment);

            $api_actions = [];
            $get_actions = Config::get("api-actions");
            if ($get_actions) {
                foreach ($get_actions as $group => $actions) {
                    $name = __("admin/settings/api-permission-" . $group);
                    if (!$name) $name = $group;

                    $api_actions[$group] = [
                        'name'  => $name,
                        'items' => $actions,
                    ];
                }
            }
            $this->addData("api_actions", $api_actions);


            $this->view->chose("admin")->render("settings", $this->data);

        }


        private function fraud_protection()
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
                'controller' => $this->AdminCRLink("settings", ["fraud-protection"]),
            ]);

            $this->addData("meta", __("admin/settings/meta-fraud-protection"));


            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/settings/breadcrumb-fraud-protection"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $get_modules = Modules::Load("Fraud", 'All');
            $modules = [];

            if ($get_modules) {
                foreach ($get_modules as $key => $row) {
                    if (isset($row["config"]) && isset($row["lang"])) {
                        $row["rank"] = isset($row["config"]["created_at"]) ? $row["config"]["created_at"] : 0;
                        if (!isset($row["config"]["meta"]["logo"])) $row["config"]["meta"]["logo"] = 'logo.png';
                        $module_k = "Fraud_" . $key;
                        $row["init"] = class_exists("Fraud_" . $key) ? new $module_k : new stdClass();
                        $modules[$key] = $row;
                    }
                }
            }
            if ($modules) Utility::sksort($modules, "rank");
            $this->addData("modules", $modules);

            $this->addData("module_url", CORE_FOLDER . DS . MODULES_FOLDER . DS . "Fraud" . DS);

            $this->addData("functions", [
                'get_module_page' => function ($name, $page) {
                    return Modules::getPage("Fraud", $name, $page);
                },
            ]);


            $this->view->chose("admin")->render("fraud-protection", $this->data);
        }

        private function security_main()
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
                'controller' => $this->AdminCRLink("settings", ["security"]),
            ]);

            $this->addData("meta", __("admin/settings/meta-security"));


            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/settings/breadcrumb-security-name"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("settings", [
                'password-length'               => Config::get("options/password-length"),
                'admfolder'                     => ADMINISTRATOR,
                'two-factor-verification'       => Config::get("options/two-factor-verification"),
                'two-factor-verification-admin' => Config::get("options/two-factor-verification-admin"),
                'location-verification'         => Config::get("options/location-verification"),
                'location-verification-type'    => Config::get("options/location-verification-type"),
                'proxy-block'                   => Config::get("options/proxy-block"),
                'proxy-block-host'              => Config::get("options/proxy-block-host"),
                'cache'                         => Config::get("general/cache"),
                'captcha'                       => Config::get("options/captcha"),
                'sign'                          => Config::get("options/sign"),
                'blgte'                         => Config::get("options/blocking-times"),
                'limits'                        => Config::get("options/limits"),
                'module'                        => Config::get("modules"),
                'prfiext'                       => Config::get("options/product-fields-extensions"),
                'attext'                        => Config::get("options/attachment-extensions"),
                'prfisize'                      => FileManager::showMB(Config::get("options/product-fields-max-file-size")),
                'attsize'                       => FileManager::showMB(Config::get("options/attachment-max-file-size")),
                'backup-db'                     => Config::get("cronjobs/tasks/auto-backup-db"),
                'BotShield'                     => Config::get("options/BotShield"),
            ]);


            $this->addData("functions", [
                'get_countries' => function () {
                    $lang = Bootstrap::$lang->clang;
                    $data = $this->model->get_countries($lang);
                    return $data;
                },
                'get_picture'   => function ($owner = '', $reason = '') {
                    if ($reason == "header-background") {
                        $hbackground = Config::get("pictures/header-background");
                        $hfolder = $hbackground["folder"];
                        $hsize = $hbackground["sizing"];
                        $data = $this->model->get_picture_data($owner, $reason);
                        if ($data) {
                            return [
                                'sizing' => $hsize,
                                'link'   => Utility::image_link_determiner($data["name"], $hfolder),
                            ];
                        }
                    }

                    return false;
                },
            ]);

            $captcha_modules = [];
            $get_captcha_modules = Modules::Load("Captcha", "All", true);
            if ($get_captcha_modules) foreach ($get_captcha_modules as $k => $v) $captcha_modules[$k] = $v;
            if (isset($captcha_modules["DefaultCaptcha"])) {
                $defCap = $captcha_modules["DefaultCaptcha"];
                unset($captcha_modules["DefaultCaptcha"]);
                $captcha_modules = array_merge(['DefaultCaptcha' => $defCap], $captcha_modules);
            }

            $this->addData("captcha_modules", $captcha_modules);


            $this->view->chose("admin")->render("security", $this->data);
        }

        private function theme_main()
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
                'controller'                => $this->AdminCRLink("settings-p", ["theme"]),
                'get-softwares-json'        => $this->AdminCRLink("settings-p", ["get-softwares.json"]),
                'get-tlds-json'             => $this->AdminCRLink("settings-p", ["get-tlds.json"]),
                'get-group-categories-json' => $this->AdminCRLink("settings-p", ["get-group-categories.json"]),
            ]);

            $this->addData("meta", __("admin/settings/meta-theme-manage"));


            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/settings/breadcrumb-theme-manage"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("settings", [
                'theme'   => [
                    'name'             => Config::get("theme/name"),
                    'header_type'      => Config::get("theme/header-type"),
                    'clientArea_type'  => Config::get("theme/clientArea-type"),
                    'color1'           => Config::get("theme/color1"),
                    'color2'           => Config::get("theme/color2"),
                    'tcolor'           => Config::get("theme/text-color"),
                    'onlyPanel'        => Config::get("theme/only-panel"),
                    'maintenance_mode' => Config::get("theme/maintenance-mode"),
                    'padeno'           => Config::get("options/sidebars/page-detail-normal"),
                    'padenews'         => Config::get("options/sidebars/page-detail-news"),
                    'padeart'          => Config::get("options/sidebars/page-detail-articles"),
                    'padekbs'          => Config::get("options/sidebars/page-detail-kbase"),
                ],
                'block'   => [
                    'product_groups' => $this->get_product_groups(),
                ],
                'options' => [
                    'cletwzmoy'                    => Config::get("options/clear-end-two-zero-money"),
                    'use_coupon'                   => Config::get("options/use-coupon"),
                    'duse_coupon'                  => Config::get("options/dealers-can-use-coupon"),
                    'viwseebasket'                 => Config::get("options/visitors-will-see-basket"),
                    'crtacwshop'                   => Config::get("options/crtacwshop"),
                    'ctixswps'                     => Config::get("options/client-index-show-products"),
                    'intsmsser'                    => Config::get("options/international-sms-service"),
                    'contact_form_status'          => Config::get("options/contact-form"),
                    'contact_form_mandatory_phone' => Config::get("options/contact-form-mandatory-phone"),
                    'contact_map_status'           => Config::get("options/contact-map"),
                    'pagination_ranks'             => Config::get("options/pagination-ranks"),
                    'limits'                       => Config::get("options/limits"),
                    'sign'                         => Config::get("options/sign"),
                    'pg-activation'                => Config::get("options/pg-activation"),
                ],
                'other'   => [
                    'currencies'       => Money::getCurrencies(),
                    'timezones'        => self::timezones(),
                    'default_language' => Config::get("general/language"),
                    'local'            => Config::get("general/local"),
                    'country'          => Config::get("general/country"),
                    'currency'         => Config::get("general/currency"),
                    'timezone'         => Config::get("general/timezone"),
                ],
            ]);

            $this->addData("functions", [
                'get_block_picture'          => function ($id) {
                    return $this->get_block_picture($id);
                },
                'get_software'               => function ($id, $lang) {
                    return $this->model->get_software($id, $lang);
                },
                'get_product_group_category' => function ($id = 0, $lang = '') {
                    return $this->model->get_product_category($id, $lang);
                },
                'get_picture'                => function ($owner = '', $reason = '') {
                    if ($reason == "header-background") {
                        $hbackground = Config::get("pictures/header-background");
                        $hfolder = $hbackground["folder"];
                        $hsize = $hbackground["sizing"];
                        $data = $this->model->get_picture_data($owner, $reason);
                        if ($data) {
                            return [
                                'sizing' => $hsize,
                                'link'   => Utility::image_link_determiner($data["name"], $hfolder . "thumb" . DS),
                            ];
                        }
                    }

                    return false;
                },
            ]);

            $this->addData("themes", $this->get_themes());

            $invoice_detail_logo = Config::get("theme/invoice-detail-logo");

            if (!$invoice_detail_logo) $invoice_detail_logo = Config::get("theme/footer-logo");


            $this->addData("clientArea_logo_link", Utility::image_link_determiner(Config::get("theme/clientArea-logo")));
            $this->addData("invoice_detail_logo_link", Utility::image_link_determiner($invoice_detail_logo));


            $this->view->chose("admin")->render("theme-manage", $this->data);
        }


        private function get_themes($get_key = '', $extra_data = [])
        {
            Helper::Load("Events");
            $themes = [];
            $active_theme = [];
            $template_dir = TEMPLATE_DIR . "website" . DS;
            $folders = FileManager::glob($template_dir . "*", GLOB_ONLYDIR);

            foreach ($folders as $folder) {
                $b_name = basename($folder);
                if ($get_key && $get_key != $b_name) continue;
                $theme_data = [
                    'has_files' => true,
                    'key'       => $b_name,
                    'time'      => filemtime($folder),
                    'config'    => [],
                    'locale'    => [],
                ];

                # Set Config START #
                if (file_exists($folder . DS . "theme-config.php"))
                    $theme_data["config"] = include $folder . DS . "theme-config.php";

                if (!isset($theme_data["config"]["meta"]["name"]))
                    $theme_data["config"]["meta"]["name"] = $b_name;

                if (!isset($theme_data["config"]["meta"]["version"]))
                    $theme_data["config"]["meta"]["version"] = '1.0';

                if (!isset($theme_data["config"]["meta"]["provider"]))
                    $theme_data["config"]["meta"]["provider"] = 'None';

                if (!isset($theme_data["config"]["meta"]["website"]))
                    $theme_data["config"]["meta"]["website"] = '';

                if (!isset($theme_data["config"]["meta"]["image"]))
                    $theme_data["config"]["meta"]["image"] =
                        $this->view->get_resources_url('assets/images/no-theme-image.png');

                if (!stristr($theme_data["config"]["meta"]["image"], '://')) {
                    $theme_data["config"]["meta"]["image"] =
                        Utility::image_link_determiner($theme_data["config"]["meta"]["image"],
                            "templates" . DS . "website" . DS . $b_name . DS);
                }

                # Set Config END #

                # Set Languages START #
                $languages = FileManager::glob($folder . DS . "locale" . DS . "*.php");
                if ($languages) {
                    foreach ($languages as $language) {
                        $l_name = basename($language);
                        $l_name = substr($l_name, 0, -4);
                        $theme_data["locale"][$l_name] = include $language;
                    }
                }
                # Set Languages END #

                if (in_array("init", $extra_data)) {
                    # Set Class #
                    if (file_exists($folder . DS . "theme.php")) {
                        $class_Name = $b_name . "_Theme";
                        if (!class_exists($class_Name)) include $folder . DS . "theme.php";
                        $theme_data["init"] = class_exists($class_Name) ? new $class_Name() : null;
                    } else $theme_data["init"] = null;
                }

                $theme_data["active"] = Config::get("theme/name") == $b_name;

                if (file_exists($folder . DS . "theme-settings.php")) {
                    $theme_data["view_settings_btn"] = true;
                } else
                    $theme_data["view_settings_btn"] = false;

                $theme_data["disable_apply_btn"] = false;
                $theme_data["disable_remove_btn"] = $b_name == "Default" ? true : false;

                if ($theme_data["view_settings_btn"]) {
                    $theme_data["page_settings"] =
                        $this->view->chose(false, true)->render($folder . DS . "theme-settings", ['theme' => $theme_data], true);
                }

                $check_update = Updates::check_new_version_theme($b_name);
                if ($check_update) {
                    $theme_data["new-update"] = $check_update;
                    if ($theme_data["active"]) Events::readAll("info", "system", 0, "theme-new-version");
                }


                if ($theme_data["active"] && !$get_key) {
                    $active_theme = $theme_data;
                    continue;
                }
                $themes[$b_name] = $theme_data;
            }

            if ($get_key) {
                return $themes[$get_key];
            } else {
                if ($themes) Utility::sksort($themes, 'time');
                array_unshift($themes, $active_theme);


                return $themes;
            }
        }

    }