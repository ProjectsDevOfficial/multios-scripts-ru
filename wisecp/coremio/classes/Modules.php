<?php

    class Modules
    {
        private static $temp_include = [];
        static $modules = [
            'Addons'     => [],
            'Currency'   => [],
            'Mail'       => [],
            'Payment'    => [],
            'Registrars' => [],
            'Servers'    => [],
            'SMS'        => [],
            'Widgets'    => [],
        ];
        static $lang;


        static function Lang($type, $module, $lang = '')
        {
            if ($lang && $lang != self::$lang) {
                self::$lang = $lang;

                $fpath = MODULE_DIR . $type . DS . $module . DS;
                $dlfile = $fpath . "lang" . DS . "en.php";
                $lfile = $fpath . "lang" . DS . $lang . ".php";

                if (file_exists($lfile))
                    self::$modules[$type][$module]["lang"] = include($lfile);
                elseif (file_exists($dlfile))
                    self::$modules[$type][$module]["lang"] = include($dlfile);
            }


            if (isset(self::$modules[$type][$module]["lang"]))
                return self::$modules[$type][$module]["lang"];
        }


        static function Config($type, $module)
        {
            if (isset(self::$modules[$type][$module]["config"]))
                return self::$modules[$type][$module]["config"];
        }


        static function add($file, $type, $nominc = false, $status = '')
        {
            $fpath = MODULE_DIR . $type . DS . $file . DS;
            $clang = self::$lang ? self::$lang : (Bootstrap::$lang ? Bootstrap::$lang->clang : 'en');
            $mfile = $fpath . $file . ".php";
            $cfile = $fpath . "config.php";
            $dlfile = $fpath . "lang" . DS . "en.php";
            $lfile = $fpath . "lang" . DS . $clang . ".php";

            $reload = !isset(self::$modules[$type][$file]);

            if (!$reload && !$nominc && !in_array($mfile, self::$temp_include)) $reload = true;

            if ($reload) {

                $conf = [];

                if (file_exists($cfile)) $conf = include($cfile);

                $save_controller = defined("ADMINISTRATOR") && Filter::POST("operation") == "module_controller";

                if (!$save_controller && $type == "Registrars") {
                    $doc_fields_file = STORAGE_DIR . "domain-doc-fields.php";
                    if (file_exists($doc_fields_file)) {
                        $fields = include($doc_fields_file);
                        $fieldsM = $conf["settings"]["doc-fields"] ?? [];
                        $conf["settings"]["doc-fields"] = $fields;

                        if ($fieldsM)
                            foreach ($fieldsM as $tld => $items)
                                $conf["settings"]["doc-fields"][$tld] = $items;
                    }
                }


                if (!(((gettype($status) == 'string' && $status == '') || (isset($conf["status"]) && $conf["status"] == $status))))
                    return false;


                self::$modules[$type][$file] = [];

                if (file_exists($lfile))
                    self::$modules[$type][$file]["lang"] = include($lfile);
                elseif (file_exists($dlfile))
                    self::$modules[$type][$file]["lang"] = include($dlfile);

                if ($conf) self::$modules[$type][$file]["config"] = $conf;


                if (!in_array($mfile, self::$temp_include) && !$nominc && file_exists($mfile)) {
                    include $mfile;
                    self::$temp_include[] = $mfile;
                }
            }
        }


        static function Load($type = '', $name = '', $nominc = false, $status = '')
        {
            if ($type != '') {
                if ($name != 'All' && $name == '' && ($type == "Mail" || $type == "SMS")) {
                    if ($type == "Mail")
                        $module = Config::get("modules/mail");
                    elseif ($type == "SMS")
                        $module = Config::get("modules/sms");
                    self::add($module, $type, $nominc, $status);
                } else {
                    if ($name == "All") $name = null;
                    if ($name == '') {
                        $path = MODULE_DIR . $type . DS;
                        if (is_dir($path)) {
                            if ($dh = opendir($path)) {
                                while (($file = readdir($dh)) !== false) {
                                    $pf = $path . $file;
                                    if ($file != '.' && $file != '..' && filetype($pf) == "dir") {
                                        self::add($file, $type, $nominc, $status);
                                    }
                                }
                                closedir($dh);
                            }
                        }
                    } else {
                        $path = MODULE_DIR . $type . DS . $name;
                        if (is_dir($path)) self::add($name, $type, $nominc, $status);
                        else return false;
                    }

                }
                return self::getModules($type, $name);
            }
        }


        static function getModules($type = '', $name = '')
        {

            $local_cc = Config::get("general/country");

            if (isset(self::$modules[$type])) {
                arsort(self::$modules[$type]);
                if ($name)
                    return isset(self::$modules[$type][$name]) ? self::$modules[$type][$name] : false;
                else {
                    $rank = [];
                    $data = self::$modules[$type];

                    $rank_x = [];
                    foreach ($data as $k => $v) {
                        $nm = $v["config"]["meta"]["name"] ?? 'na';
                        if (isset($v["lang"]["name"]) && $v["lang"]["name"])
                            $nm = $v["lang"]["name"];
                        $rank_x[] = $nm . "||" . $k;
                    }
                    natcasesort($rank_x);
                    foreach ($rank_x as $r) {
                        $sp = explode("||", $r);
                        $rank[] = $sp[1];
                    }


                    if ($rank) {
                        $new_data = [];
                        if ($rank) {
                            foreach ($rank as $item) {
                                if (isset($data[$item])) {

                                    $module = $data[$item];

                                    if ($type == 'SMS') {
                                        if ($local_cc !== 'tr' && !$module["config"]["meta"]["international"]) {
                                            if (isset($data[$item])) unset($data[$item]);
                                            continue;
                                        }
                                    }

                                    $new_data[$item] = $data[$item];
                                    unset($data[$item]);
                                }
                            }
                        }
                        if ($data) {
                            foreach ($data as $module_key => $module) {
                                if ($type == 'SMS') {
                                    if ($local_cc !== 'tr' && (!isset($module["config"]["meta"]["international"]) || !$module["config"]["meta"]["international"])) {
                                        if (isset($new_data[$module_key])) unset($new_data[$module_key]);
                                        continue;
                                    }
                                }
                                $new_data[$module_key] = $module;
                            }
                        }

                        if ($type == 'SMS' && $local_cc == 'tr' && isset($new_data["SitemioTurkey"]))
                            if (Config::get("modules/sms") != 'SitemioTurkey') unset($new_data['SitemioTurkey']);

                        $data = $new_data;
                    }

                    return $data;
                }
            }
        }


        static function getPage($type = '', $name = '', $page = '', $data = [])
        {
            $path = CORE_FOLDER . DS . MODULES_FOLDER . DS . $type . DS . $name;

            if (!isset($data["module"])) {
                $class_name = $name;
                if ($type == "Servers") $class_name .= "_Module";
                $module = class_exists($class_name) ? new $class_name() : false;

                $data['module'] = $module;

                if ($module && method_exists($module, 'config_fields') && $type == 'Payment')
                    return Controllers::$init->view->chose("system")->render("payment-gateway-settings-form", $data, true);

                if ($module && method_exists($module, 'config_fields') && $type == 'Registrars')
                    return Controllers::$init->view->chose("system")->render("registrar-settings-form", $data, true);
            }
            return Controllers::$init->view->chose($path . DS . "pages", true)->render($page, $data, true);
        }


        static function getController($type = '', $name = '', $cname = '')
        {

            $cname2 = str_replace("-", "_", $cname);
            $class_name = $name;
            if ($type == "Servers") $class_name .= "_Module";

            $module = class_exists($class_name) ? new $class_name() : false;

            $path = CORE_FOLDER . DS . MODULES_FOLDER . DS . $type . DS . $name . DS . "controllers" . DS;
            $file = $path . $cname . ".php";

            if (file_exists($file)) include $file;
            elseif (method_exists($module, 'controller_' . $cname2)) return $module->{"controller_" . $cname2}();
            else {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Controller not found",
                ]);
                return false;
            }
        }


        static function save_log($type = '', $module = '', $action = '', $request = '', $response = '', $processed = '')
        {
            if (!Config::get("options/save-module-log")) return false;

            $data = [];

            Helper::Load("User");

            $data['type'] = $type;
            $data['module'] = $module;
            $data['action'] = $action;
            $data['request'] = is_array($request) ? Utility::jencode($request) : $request;
            $data['response'] = is_array($response) ? Utility::jencode($response) : $response;
            $data['processed'] = is_array($processed) ? Utility::jencode($processed) : $processed;
            $data['REQUEST_URI'] = Utility::RequestURI();
            $data['_GET'] = Filter::GET() ? Utility::jencode(Filter::GET()) : '';
            $data['_POST'] = Filter::POST() ? Utility::jencode(Filter::POST()) : '';

            return User::addAction(0, 'module-log', $module, $data);
        }


        static function fields_output($mn, $data = [], $input_name = '')
        {
            if (!$input_name) $input_name = 'fields';
            foreach ($data as $f_k => $f_v) {
                $wrap_width = isset($f_v["wrap_width"]) ? Filter::numbers($f_v["wrap_width"]) : 50;
                $wrap_class = 'yuzde' . $wrap_width;
                $name = $f_v["name"];
                $is_tooltip = isset($f_v["is_tooltip"]) && $f_v["is_tooltip"] ? true : false;
                $dec_pos = isset($f_v["description_pos"]) ? $f_v["description_pos"] : 'R';
                $description = isset($f_v["description"]) ? $f_v["description"] : null;
                $type = isset($f_v["type"]) ? $f_v["type"] : "text";
                $value = isset($f_v["value"]) ? $f_v["value"] : null;
                $placeholder = isset($f_v["placeholder"]) ? $f_v["placeholder"] : null;
                $width = isset($f_v["width"]) ? $f_v["width"] : null;
                $checked = isset($f_v["checked"]) ? $f_v["checked"] : false;
                $options = isset($f_v["options"]) ? $f_v["options"] : [];
                $class = $width ? 'yuzde' . $width : '';
                $_rows = isset($f_v["rows"]) ? $f_v["rows"] : false;

                if ($options && !is_array($options)) {
                    $n_options = [];
                    foreach (explode(",", $options) as $opt) $n_options[$opt] = $opt;
                    $options = $n_options;
                }
                if (!in_array($type, [
                    'text', 'file', 'password', 'approval', 'radio', 'dropdown', 'textarea', 'output',
                ])) $type = "text";
                ?>
                <div class="<?php echo $wrap_class; ?>" id="wrap-el-<?php echo $f_k; ?>">
                    <div class="formcon">
                        <div class="yuzde30">
                            <?php echo $name; ?>
                            <?php if ($description && $dec_pos == 'L' && $is_tooltip): ?>
                                <span class="kinfo"
                                      data-tooltip="<?php echo str_replace('"', '\"', $description); ?>"><i
                                            class="fa fa-question-circle-o"></i></span>
                            <?php elseif ($description && $dec_pos == 'L'): ?>
                                <div class="clear"></div>
                                <span class="kinfo"><?php echo $description; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="yuzde70">

                            <?php
                                if ($type == 'text') {
                                    ?>
                                <input
                                        type="text"
                                        name="<?php echo $input_name; ?>[<?php echo $f_k; ?>]"
                                        class="<?php echo $class; ?>"
                                        placeholder="<?php echo $placeholder; ?>"
                                        value="<?php echo htmlentities($value); ?>"
                                        id="el-<?php echo $mn; ?>-item-<?php echo $f_k; ?>"
                                />
                                <?php
                                    }
                                    elseif ($type == 'password')
                                    {
                                ?>
                                <input
                                        type="password"
                                        name="<?php echo $input_name; ?>[<?php echo $f_k; ?>]"
                                        class="<?php echo $class; ?>"
                                        placeholder="<?php echo $placeholder; ?>"
                                        value="<?php echo htmlentities($value); ?>"
                                        id="el-<?php echo $mn; ?>-item-<?php echo $f_k; ?>"
                                />
                                <?php
                                    }
                                    elseif ($type == 'file')
                                    {
                                ?>
                                <input
                                        type="file"
                                        name="<?php echo $input_name; ?>[<?php echo $f_k; ?>]"
                                        class="<?php echo $class; ?>"
                                        id="el-<?php echo $mn; ?>-item-<?php echo $f_k; ?>"
                                />
                                <?php
                                    }
                                    elseif ($type == 'approval')
                                    {
                                ?>
                                <input
                                    <?php echo $checked ? 'checked' : ''; ?>
                                        type="checkbox"
                                        name="<?php echo $input_name; ?>[<?php echo $f_k; ?>]"
                                        class="checkbox-custom"
                                        value="<?php echo $value ? htmlentities($value) : 1; ?>"
                                        id="el-<?php echo $mn; ?>-item-<?php echo $f_k; ?>"
                                />
                                    <label class="checkbox-custom-label"
                                           for="el-<?php echo $mn; ?>-item-<?php echo $f_k; ?>"><span
                                                class="kinfo"><?php echo $dec_pos == 'R' && !$is_tooltip ? $description : ''; ?></span></label>
                                <?php
                                    }
                                    elseif ($type == 'textarea')
                                    {
                                ?>
                                    <textarea
                                            name="<?php echo $input_name; ?>[<?php echo $f_k; ?>]"
                                            rows="<?php echo $_rows; ?>"
                                            class="<?php echo $class; ?>"
                                            placeholder="<?php echo $placeholder; ?>"
                                            id="el-<?php echo $mn; ?>-item-<?php echo $f_k; ?>"
                                    ><?php echo $value; ?></textarea>
                                <?php
                                    }
                                    elseif ($type == 'dropdown')
                                    {
                                ?>
                                    <script type="text/javascript">
                                        $(document).ready(function () {
                                            $("#el-<?php echo $mn; ?>-item-<?php echo $f_k; ?>").select2({
                                                width: '<?php echo $width ? $width : '100'; ?>%',
                                                placeholder: "<?php echo ___("needs/select-your"); ?>",
                                            });
                                        });
                                    </script>
                                    <select
                                            name="<?php echo $input_name; ?>[<?php echo $f_k; ?>]"
                                            id="el-<?php echo $mn; ?>-item-<?php echo $f_k; ?>"
                                            class="select2<?php echo $class ? ' ' . $class : ''; ?>"
                                    >
                                        <?php
                                            if ($options) {
                                                foreach ($options as $k => $v) {
                                                    ?>
                                                    <option<?php echo $value == $k ? ' selected' : ''; ?>
                                                            value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                                    <?php
                                                }
                                            }
                                        ?>
                                    </select>
                                <?php
                                    }
                                    elseif ($type == 'radio')
                                    {
                                    if ($options)
                                    {
                                    if (!$class) $class = 'yuzde30';
                                    $i = 0;
                                    foreach ($options

                                    as $k => $v)
                                    {
                                    $i++;
                                ?>
                                    <div style="display: inline-block; " class="<?php echo $class; ?>">
                                        <input<?php echo $value == $k ? ' checked' : ''; ?> type="radio"
                                                                                            value="<?php echo $k; ?>"
                                                                                            id="el-<?php echo $mn; ?>-item-<?php echo $f_k; ?>-<?php echo $i; ?>"
                                                                                            class="radio-custom"
                                                                                            name="<?php echo $input_name; ?>[<?php echo $f_k; ?>]"/>
                                        <label class="radio-custom-label"
                                               for="el-<?php echo $mn; ?>-item-<?php echo $f_k; ?>-<?php echo $i; ?>"><?php echo $v; ?></label>
                                    </div>
                                    <?php
                                }
                                }

                                }
                                else echo $value;
                            ?>
                            <?php if ($description && $dec_pos == 'R' && $is_tooltip): ?>
                                <span class="kinfo"
                                      data-tooltip="<?php echo str_replace('"', '\"', $description); ?>"><i
                                            class="fa fa-question-circle-o"></i></span>
                            <?php elseif ($type != "approval"): ?>
                                <div class="clear"></div><?php endif; ?>
                            <?php if ($description && $dec_pos == 'R' && !$is_tooltip && $type != 'approval'): ?>
                                <span class="kinfo"><?php echo $description; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php
            }
        }

    }

    class ServerModule
    {
        protected $server;
        public $force_setup = true;
        public $entity_id_name = 'vm_id', $area_link, $page = false;
        public $_name, $order = [], $product = [], $user = [], $admin = [];
        public $config = [], $options = [], $val_of_conf_opt, $val_of_requirements, $requirements, $addons, $id_of_conf_opt = [];
        public $lang, $error;
        public $dir, $url;


        function __construct($server, $options = [])
        {
            $n_s = explode("_Module", $this->_name);
            $this->_name = $n_s[0];

            $this->dir = MODULE_DIR . "Servers" . DS . $this->_name . DS;
            $this->url .= CORE_FOLDER . DS . MODULES_FOLDER . DS . "Servers" . DS . $this->_name . DS;
            $this->url = Utility::link_determiner($this->url, false, false);

            $this->server = $server;
            $config = Modules::Config("Servers", $this->_name);
            if (isset($options["config"])) {
                $this->options = $options;
                $external_config = $options["config"];
            } else {
                $this->options = $options;
                $external_config = $options;
            }
            $this->config = array_merge($config, $external_config);
            $this->lang = Modules::Lang("Servers", $this->_name);

            Helper::Load(["User"]);
            $a_data = UserManager::LoginData("admin");
            if ($a_data) {
                $this->admin = User::getData($a_data["id"], [
                    "id",
                    "name",
                    "surname",
                    "full_name",
                    "email",
                    "phone",
                    "lang",
                    "country",
                ], "array");
                $this->admin = array_merge($this->admin, User::getInfo($this->admin["id"], [
                    "gsm_cc",
                    "gsm_number",
                ]));
            }

            if ($server) $this->define_server_info($server);

        }

        static function save_log($type = '', $module = '', $action = '', $request = '', $response = '', $processed = '')
        {
            return Modules::save_log($type, $module, $action, $request, $response, $processed);
        }

        static function sync_terms($ip = '', $hostname = '')
        {
            return [
                [
                    'column'  => "options",
                    'mark'    => "LIKE",
                    'value'   => '%"ip":"' . $ip . '"%',
                    'logical' => "&&",
                ],
                [
                    'column'  => "options",
                    'mark'    => "LIKE",
                    'value'   => '%"hostname":"' . $hostname . '"%',
                    'logical' => "",
                ],
            ];
        }

        public function set_order($order = [])
        {
            $this->order = $order;
            Helper::Load(["Products", "User", "Orders"]);
            $this->product = Products::get($order["type"], $order["product_id"]);
            $this->user = User::getData($order["owner_id"], "id,name,surname,company_name,full_name,email,phone,lang,country", "array");
            $this->user = array_merge($this->user, User::getInfo($order["owner_id"], ["gsm_cc", "gsm_number"]));
            $this->user["address"] = AddressManager::getAddress(false, $order["owner_id"]);

            $configurable_options = [];
            if ($addons = Orders::addons($this->order["id"])) {
                $lang = $this->user["lang"];
                foreach ($addons as $addon) {
                    if ($gAddon = Products::addon($addon["addon_id"], $lang)) {
                        $addon["attributes"] = $gAddon;
                        $this->addons[$addon["id"]] = $addon;
                        if ($gAddon["options"]) {
                            if ($gAddon["type"] == "quantity" || $addon["option_quantity"] > 0) {
                                if ($addon["option_quantity"] > 0)
                                    $addon_v = (int)$addon["option_quantity"];
                                else {
                                    $addon_v = $addon["option_name"];
                                    $addon_v = explode("x", $addon_v);
                                    $addon_v = (int)trim($addon_v[0]);
                                }
                            }
                            else
                                $addon_v = '';
                            foreach ($gAddon["options"] as $option) {
                                if ($option["id"] == $addon["option_id"]) {
                                    if (isset($option["module"]) && $option["module"]) {
                                        if (isset($option["module"][$this->_name])) {
                                            $c_options = $option["module"][$this->_name]["configurable"];
                                            foreach ($c_options as $k => $v) {
                                                $d_v = $v;
                                                if(strlen($addon_v) > 0) $d_v = ((int) $d_v) * ((int) $addon_v);

                                                if (!in_array($addon['status'], ["cancelled", "waiting"])) {
                                                    if (isset($configurable_options[$k]) && strlen($addon_v) > 0)
                                                        $configurable_options[$k] += $d_v;
                                                    else
                                                        $configurable_options[$k] = $d_v;
                                                }

                                                $this->id_of_conf_opt[$addon["id"]][$k] = $d_v;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $this->val_of_conf_opt = $configurable_options;

            $values_of_requirements = [];
            if ($requirements = Orders::requirements($this->order["id"])) {
                $this->requirements = $requirements;
                foreach ($requirements as $req) {
                    if ($req["module_co_names"]) {
                        $req["module_co_names"] = Utility::jdecode($req["module_co_names"], true);
                        if (isset($req["module_co_names"][$this->_name])) {
                            $c_o_name = $req["module_co_names"][$this->_name];
                            if (in_array($req["response_type"], ['input', 'password', 'textarea', 'file']))
                                $response = $req["response"];
                            else {
                                $mkey = $req["response_mkey"];
                                if ($dc = Utility::jdecode($mkey, true)) $mkey = $dc;
                                $response = is_array($mkey) && sizeof($mkey) < 2 ? current($mkey) : $mkey;
                            }
                            $values_of_requirements[$c_o_name] = $response;
                        }
                    }
                }
            }
            $this->val_of_requirements = $values_of_requirements;
            if (!$this->options) $this->options = $this->order["options"];
            $this->config = array_merge($this->config, isset($this->options["config"]) ? $this->options["config"] : []);
        }

        public function activation_infos($type = 'html', $order = [], $lang = '')
        {
            $this->lang = Modules::Lang("Servers", $this->_name, $lang);
            $options = $order["options"];

            if (isset($options["login"]["password"])) {
                $password = $options["login"]["password"];
                $password_d = $this->decode_str($password);
                if ($password_d) $options["login"]["password"] = $password_d;
            }
            if (isset($options["config"]["password"]) && $options["config"]["password"])
                $options["config"]["password"] = $this->decode_str($options["config"]["password"]);

            if (isset($options["ftp_info"]["password"]) && $options["ftp_info"]["password"])
                $options["ftp_info"]["password"] = $this->decode_str($options["ftp_info"]["password"]);

            $order["options"] = $options;

            $data = [
                'order'   => $order,
                'module'  => $this,
                'options' => $options,
                'server'  => $this->server,
            ];

            return Modules::getPage("Servers", $this->_name, "activation-" . $type, $data);
        }

        public function edit_order_params()
        {
            $options = $this->options;
            if (!isset($options["creation_info"])) $options["creation_info"] = [];
            if (!isset($options["config"])) $options["config"] = [];
            $creation_info = (array)Filter::POST("creation_info");
            $config = (array)Filter::POST("config");
            $login = isset($options["login"]) ? $options["login"] : [];

            if (method_exists($this, 'save_adminArea_service_fields')) {
                $moduleCall = $this->save_adminArea_service_fields([
                    'creation_info' => $creation_info,
                    'config'        => $config,
                ]);

                if (!$moduleCall && $this->error) return false;
                if (is_array($moduleCall) && isset($moduleCall['creation_info'])) $creation_info = $moduleCall['creation_info'];
                if (is_array($moduleCall) && isset($moduleCall['config'])) $config = $moduleCall['config'];
                if (is_array($moduleCall) && isset($moduleCall['login'])) $login = $moduleCall['login'];
                if (is_array($moduleCall) && isset($moduleCall['combine_options'])) $options = array_replace_recursive($options, $moduleCall["combine_options"]);
            }

            $options["config"] = $config;
            $options["creation_info"] = $creation_info;
            if ($login) $options["login"] = $login;

            if (!isset($options["config"][$this->entity_id_name]) || !$options["config"][$this->entity_id_name]) {
                unset($options["config"]);
                unset($options["established"]);
            } elseif (!isset($options["established"])) $options["established"] = true;
            return $options;
        }

        public function apply_options($old_options, $new_options = [])
        {
            $o_creation_info = isset($old_options["creation_info"]) ? $old_options["creation_info"] : [];
            $o_config = isset($old_options["config"]) ? $old_options["config"] : [];
            $o_ftp_info = isset($old_options["ftp_info"]) ? $old_options["ftp_info"] : [];
            $o_options = $old_options;
            $n_options = $new_options;

            if (isset($o_options['creation_info'])) unset($o_options['creation_info']);
            if (isset($o_options['config'])) unset($o_options['config']);
            if (isset($o_options['ftp_info'])) unset($o_options['ftp_info']);

            if (isset($n_options['creation_info'])) unset($n_options['creation_info']);
            if (isset($n_options['config'])) unset($n_options['config']);
            if (isset($n_options['ftp_info'])) unset($n_options['ftp_info']);

            $n_creation_info = isset($new_options["creation_info"]) ? $new_options["creation_info"] : [];
            $n_config = isset($new_options["config"]) ? $new_options["config"] : [];

            $o_password = isset($o_config["password"]) ? $this->decode_str($o_config["password"]) : '';
            $n_password = Filter::password($n_config["password"]);
            $n_ftp_info = [];

            if ($o_password) $o_config['password'] = $o_password;
            if (isset($o_ftp_info["password"]) && $o_ftp_info["password"])
                $o_ftp_info["password"] = $this->decode_str($o_ftp_info["password"]);

            if ($n_password && $n_password != $o_password && method_exists($this, 'change_password')) {
                if (Utility::strlen($n_password) < 5) {
                    $this->error = __("admin/orders/error8");
                    return false;
                }

                $changed = $this->change_password($n_password);
                if (!$changed) return false;
            }

            if (!$n_ftp_info && isset($n_config['user']) && $n_config['user']) {
                $host = $this->server["ip"];
                if (Validation::NSCheck($this->server["name"])) $host = $this->server["name"];

                $n_ftp_info["ip"] = $this->server["ip"];
                $n_ftp_info["host"] = $host;
                $n_ftp_info["port"] = 21;
                $n_ftp_info["username"] = $n_config['user'];
                $n_ftp_info["password"] = $n_config['password'];
            }


            if (method_exists($this, 'save_adminArea_service_fields')) {
                $moduleCall = $this->save_adminArea_service_fields([
                    'old' => [
                        'creation_info' => $o_creation_info,
                        'config'        => $o_config,
                        'ftp_info'      => $o_ftp_info,
                        'options'       => $o_options,
                    ],
                    'new' => [
                        'creation_info' => $n_creation_info,
                        'config'        => $n_config,
                        'ftp_info'      => $n_ftp_info,
                        'options'       => $n_options,
                    ],
                ]);

                if (!$moduleCall && $this->error) return false;
                if (is_array($moduleCall) && isset($moduleCall['creation_info'])) $n_creation_info = $moduleCall['creation_info'];
                if (is_array($moduleCall) && isset($moduleCall['config'])) $n_config = $moduleCall['config'];
                if (is_array($moduleCall) && isset($moduleCall['ftp_info'])) $n_ftp_info = $moduleCall['ftp_info'];
                if (is_array($moduleCall) && isset($moduleCall['options'])) $n_options = $moduleCall['options'];
            }


            if (isset($n_config["password"]) && $n_config["password"])
                $n_config["password"] = $this->encode_str($n_config["password"]);

            if (isset($n_ftp_info["password"]) && $n_ftp_info["password"])
                $n_ftp_info["password"] = $this->encode_str($n_ftp_info["password"]);

            $new_options["config"] = $n_config;
            $new_options["creation_info"] = $n_creation_info;
            $new_options["ftp_info"] = $n_ftp_info;

            $new_options = array_merge($new_options, $n_options);

            return $new_options;
        }

        public function config_options_output($data = [], $_type = '')
        {
            foreach ($data as $f_k => $f_v) {
                $wrap_width = isset($f_v["wrap_width"]) ? Filter::numbers($f_v["wrap_width"]) : 50;
                $wrap_class = 'yuzde' . $wrap_width;
                $name = $f_v["name"];
                $is_tooltip = isset($f_v["is_tooltip"]) && $f_v["is_tooltip"] ? true : false;
                $dec_pos = isset($f_v["description_pos"]) ? $f_v["description_pos"] : 'R';
                $description = isset($f_v["description"]) ? $f_v["description"] : null;
                $type = isset($f_v["type"]) ? $f_v["type"] : "text";
                $value = isset($f_v["value"]) ? $f_v["value"] : null;
                $placeholder = isset($f_v["placeholder"]) ? $f_v["placeholder"] : null;
                $width = isset($f_v["width"]) ? $f_v["width"] : null;
                $checked = isset($f_v["checked"]) ? $f_v["checked"] : false;
                $options = isset($f_v["options"]) ? $f_v["options"] : [];
                $class = $width ? 'yuzde' . $width : '';
                $_rows = isset($f_v["rows"]) ? $f_v["rows"] : false;

                if ($options && !is_array($options)) {
                    $n_options = [];
                    foreach (explode(",", $options) as $opt) $n_options[$opt] = $opt;
                    $options = $n_options;
                }
                if (!in_array($type, [
                    'text', 'password', 'approval', 'radio', 'dropdown', 'textarea', 'output',
                ])) $type = "text";
                ?>
                <div class="<?php echo $wrap_class; ?>" id="wrap-el-<?php echo $f_k; ?>">
                    <div class="formcon">
                        <div class="yuzde30">
                            <?php echo $name; ?>
                            <?php if ($description && $dec_pos == 'L' && $is_tooltip): ?>
                                <span class="kinfo"
                                      data-tooltip="<?php echo str_replace('"', '\"', $description); ?>"><i
                                            class="fa fa-question-circle-o"></i></span>
                            <?php elseif ($description && $dec_pos == 'L'): ?>
                                <div class="clear"></div>
                                <span class="kinfo"><?php echo $description; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="yuzde70">

                            <?php
                                if ($type == 'text') {
                                    ?>
                                <input
                                        type="text"
                                        name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                        class="<?php echo $class; ?>"
                                        placeholder="<?php echo $placeholder; ?>"
                                        value="<?php echo htmlentities($value); ?>"
                                        id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                />
                                <?php
                                    }
                                    elseif ($type == 'password')
                                    {
                                ?>
                                <input
                                        type="password"
                                        name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                        class="<?php echo $class; ?>"
                                        placeholder="<?php echo $placeholder; ?>"
                                        value="<?php echo htmlentities($value); ?>"
                                        id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                />
                                <?php
                                    }
                                    elseif ($type == 'approval')
                                    {
                                ?>
                                <input
                                    <?php echo $checked ? 'checked' : ''; ?>
                                        type="checkbox"
                                        name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                        class="checkbox-custom"
                                        value="<?php echo $value ? htmlentities($value) : 1; ?>"
                                        id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                />
                                    <label class="checkbox-custom-label"
                                           for="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"><span
                                                class="kinfo"><?php echo $dec_pos == 'R' && !$is_tooltip ? $description : ''; ?></span></label>
                                <?php
                                    }
                                    elseif ($type == 'textarea')
                                    {
                                ?>
                                    <textarea
                                            name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                            rows="<?php echo $_rows; ?>"
                                            class="<?php echo $class; ?>"
                                            placeholder="<?php echo $placeholder; ?>"
                                            id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                    ><?php echo $value; ?></textarea>
                                <?php
                                    }
                                    elseif ($type == 'dropdown')
                                    {
                                ?>
                                    <script type="text/javascript">
                                        $(document).ready(function () {
                                            $("#el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>").select2({
                                                width: '<?php echo $width ? $width : '100'; ?>%',
                                                placeholder: "<?php echo ___("needs/select-your"); ?>",
                                            });
                                        });
                                    </script>
                                    <select
                                            name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                            id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                            class="select2<?php echo $class ? ' ' . $class : ''; ?>"
                                    >
                                        <?php
                                            if ($options) {
                                                foreach ($options as $k => $v) {
                                                    ?>
                                                    <option<?php echo $value == $k ? ' selected' : ''; ?>
                                                            value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                                    <?php
                                                }
                                            }
                                        ?>
                                    </select>
                                <?php
                                    }
                                    elseif ($type == 'radio')
                                    {
                                    if ($options)
                                    {
                                    if (!$class) $class = 'yuzde30';
                                    $i = 0;
                                    foreach ($options

                                    as $k => $v)
                                    {
                                    $i++;
                                ?>
                                    <div style="display: inline-block; " class="<?php echo $class; ?>">
                                        <input<?php echo $value == $k ? ' checked' : ''; ?> type="radio"
                                                                                            value="<?php echo $k; ?>"
                                                                                            id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>-<?php echo $i; ?>"
                                                                                            class="radio-custom"
                                                                                            name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"/>
                                        <label class="radio-custom-label"
                                               for="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>-<?php echo $i; ?>"><?php echo $v; ?></label>
                                    </div>
                                    <?php
                                }
                                }

                                }
                                else echo $value;
                            ?>
                            <?php if ($description && $dec_pos == 'R' && $is_tooltip): ?>
                                <span class="kinfo"
                                      data-tooltip="<?php echo str_replace('"', '\"', $description); ?>"><i
                                            class="fa fa-question-circle-o"></i></span>
                            <?php elseif ($type != "approval"): ?>
                                <div class="clear"></div><?php endif; ?>
                            <?php if ($description && $dec_pos == 'R' && !$is_tooltip && $type != 'approval'): ?>
                                <span class="kinfo"><?php echo $description; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php
            }
        }

        public function use_method($param = '')
        {
            $method_name = '';
            $method_prefix_c = 'use_clientArea_';
            $method_prefix_a = 'use_adminArea_';
            $param = str_replace("-", "_", $param);

            if (defined("ADMINISTRATOR") && $param)
                $method_name = method_exists($this, $method_prefix_a . $param) ? $method_prefix_a . $param : '';
            elseif ($param)
                $method_name = method_exists($this, $method_prefix_c . $param) ? $method_prefix_c . $param : '';

            if ($method_name) return $this->{$method_name}();
        }

        public function UsernameGenerator($domain = '', $half_mixed = false)
        {
            $exp = explode(".", $domain);
            $domain = Filter::transliterate($exp[0]);
            $username = $domain;
            $fchar = substr($username, 0, 1);
            $size = strlen($username);
            if ($fchar == "0" || (int)$fchar)
                $username = Utility::generate_hash(1, false, "l") . substr($username, 1, $size - 1);

            if ($size >= 8) {
                $username = substr($username, 0, 5);
                $username .= Utility::generate_hash(3, false, "l");
            } elseif ($size > 4 && $size < 9) {
                $username = substr($username, 0, 5);
                $username .= Utility::generate_hash(3, false, "l");
            } elseif ($size >= 1 && $size < 5) {
                $how = (8 - $size);
                $username = substr($username, 0, $size);
                $username .= Utility::generate_hash($how, false, "l");
            }

            return $username;
        }

        protected function encode_str($str = '')
        {
            return Crypt::encode($str, Config::get("crypt/user"));
        }

        protected function decode_str($str = '')
        {
            return Crypt::decode($str, Config::get("crypt/user"));
        }

        public function get_page($page_file = '', $vars = [])
        {
            $vars['module'] = $this;
            return Modules::getPage('Servers', $this->_name, $page_file, $vars);
        }

        public function clientArea_buttons_output($external_buttons = [])
        {
            $text = '';
            $buttons = [];

            if (!$external_buttons) {
                if ($this->config["type"] != "hosting" && (!$this->page || $this->page == "home") && method_exists($this, 'use_clientArea_SingleSignOn')) {
                    $buttons['SingleSignOn'] = [
                        'type'         => 'function',
                        'text'         => Bootstrap::$lang->get_cm("website/account_products/login-panel"),
                        'icon'         => 'fa fa-sign-in',
                        'target_blank' => true,
                        'attributes'   => [
                            'id' => 'vpspanellogin',
                        ],
                    ];
                }
                if ($this->config["type"] == "hosting" && (!$this->page || $this->page == "home") && method_exists($this, 'use_clientArea_SingleSignOn2')) {
                    $buttons['SingleSignOn2'] = [
                        'type'         => 'function',
                        'text'         => Bootstrap::$lang->get_cm("website/account_products/login-panel"),
                        'icon'         => 'fa fa-sign-in',
                        'target_blank' => true,
                        'attributes'   => [
                            'id' => 'vpsstart',
                        ],
                    ];
                }
                if ($this->config["type"] == "hosting" && (!$this->page || $this->page == "home") && method_exists($this, 'use_clientArea_webMail2')) {
                    $buttons['webMail2'] = [
                        'type'         => 'function',
                        'text'         => Bootstrap::$lang->get_cm("website/account_products/login-webmail"),
                        'icon'         => 'fa fa-envelope-o',
                        'target_blank' => true,
                        'attributes'   => [
                            'id' => 'vpsgeneral',
                        ],
                    ];
                }

            }

            if ($external_buttons) $buttons = array_merge($buttons, $external_buttons);
            elseif (method_exists($this, 'clientArea_buttons')) $buttons = array_merge($buttons, $this->clientArea_buttons());

            if ($buttons) {
                foreach ($buttons as $b_k => $button) {
                    $type = isset($button['type']) ? $button['type'] : 'transaction';
                    if (!is_array($button)) $_text = $button;
                    else $_text = isset($button['text']) ? $button['text'] : 'Button';
                    $_blank = isset($button['target_blank']) ? $button['target_blank'] : false;
                    $icon = isset($button['icon']) ? $button['icon'] : null;
                    $link = null;
                    $attributes = isset($button['attributes']) ? $button["attributes"] : [];

                    if ($type == 'transaction') $link = 'javascript:void 0;';
                    elseif ($type == 'page-loader') $link = "javascript:reload_module_content('" . $b_k . "'); void 0;";
                    elseif ($type == 'page') $link = $this->area_link . "?m_page=" . $b_k;
                    elseif ($this->config["type"] == 'hosting' && $type == 'function')
                        $link = $this->area_link . "?inc=use_method&method=" . $b_k;
                    elseif ($type == 'function') $link = $this->area_link . "?inc=panel_operation_method&method=" . $b_k;
                    elseif ($type == 'link') $link = isset($button['url']) ? $button['url'] : $this->area_link;

                    $text .= '<a';
                    if (!isset($attributes['href'])) $text .= ' href="' . $link . '"';
                    if (!isset($attributes['onclick']) && $type == 'transaction') $text .= ' onclick="run_transaction(\'' . $b_k . '\',this);"';
                    if ($_blank) $text .= ' target="_blank"';
                    if (!isset($attributes['class'])) $text .= ' class="hostbtn"';
                    if ($attributes) foreach ($attributes as $k => $v) $text .= ' ' . $k . '="' . $v . '"';
                    $text .= '>';
                    if ($icon) $text .= '<i class="' . $icon . '"></i>';
                    $text .= $_text;
                    $text .= '';
                    $text .= '</a> ';
                }
            }

            return $text;
        }

        public function adminArea_buttons_output($external_buttons = [])
        {
            $text = '';
            $buttons = [];

            if (!$external_buttons) {
                $rlb = Admin::isPrivilege(["MODULES_ROOT_LOGIN_BUTTON"]);

                if ($rlb && $this->config["type"] != 'hosting' && method_exists($this, 'use_adminArea_root_SingleSignOn')) {
                    $buttons['root_SingleSignOn'] = [
                        'type'         => 'function',
                        'text'         => Bootstrap::$lang->get_cm("admin/products/login-root-panel"),
                        'color'        => 'blue',
                        'target_blank' => true,
                    ];
                }

                if ($this->config["type"] != 'hosting' && method_exists($this, 'use_adminArea_SingleSignOn')) {
                    $buttons['SingleSignOn'] = [
                        'type'         => 'function',
                        'icon'         => 'fa fa-sign-in',
                        'text'         => Bootstrap::$lang->get_cm("website/account_products/login-panel"),
                        'attributes'   => [
                            'id' => 'vpspanellogin',
                        ],
                        'target_blank' => true,
                    ];
                }
                if ($this->config["type"] == 'hosting' && method_exists($this, 'use_adminArea_SingleSignOn2')) {
                    $buttons['SingleSignOn2'] = [
                        'type'         => 'function',
                        'icon'         => 'fa fa-sign-in',
                        'text'         => Bootstrap::$lang->get_cm("website/account_products/login-panel"),
                        'attributes'   => [
                            'id' => 'vpspanellogin',
                        ],
                        'target_blank' => true,
                    ];
                }
            }

            if ($external_buttons) $buttons = array_merge($buttons, $external_buttons);
            elseif (method_exists($this, 'adminArea_buttons')) {
                $buttons2 = $this->adminArea_buttons();
                if ($buttons2 && is_array($buttons2)) $buttons = array_merge($buttons, $buttons2);
            }

            if ($buttons) {
                foreach ($buttons as $b_k => $button) {
                    $type = isset($button['type']) ? $button['type'] : 'transaction';
                    if (!is_array($button)) $_text = $button;
                    else $_text = isset($button['text']) ? $button['text'] : 'Button';
                    $_blank = isset($button['target_blank']) ? $button['target_blank'] : false;
                    $icon = isset($button['icon']) ? $button['icon'] : null;
                    $link = null;
                    $attributes = isset($button['attributes']) ? $button["attributes"] : [];

                    if ($type == 'transaction') $link = 'javascript:void 0;';
                    elseif ($type == 'page-loader') $link = "javascript:open_m_page('" . $b_k . "'); void 0;";
                    elseif ($type == 'page') $link = $this->area_link . "?content=automation&m_page=" . $b_k;
                    elseif ($this->config["type"] == "hosting" && $type == 'function')
                        $link = $this->area_link . "?operation=hosting_use_method&use_method=" . $b_k;
                    elseif ($type == 'function')
                        $link = $this->area_link . "?operation=operation_server_automation&use_method=" . $b_k;

                    elseif ($type == 'link') $link = isset($button['url']) ? $button['url'] : $this->area_link;

                    $text .= '<a';
                    if (!isset($attributes['href'])) $text .= ' href="' . $link . '"';
                    if (!isset($attributes['onclick']) && $type == 'transaction') $text .= ' onclick="run_transaction(\'' . $b_k . '\',this);"';
                    if ($_blank) $text .= ' target="_blank"';
                    if (!isset($attributes['class'])) $text .= ' class="hostbtn"';
                    if ($attributes) foreach ($attributes as $k => $v) $text .= ' ' . $k . '="' . $v . '"';
                    $text .= '>';
                    if ($icon) $text .= '<i class="' . $icon . '"></i> ';
                    $text .= $_text;
                    $text .= '';
                    $text .= '</a> ';
                }
            }

            return $text;
        }

        public function panel_links_for_client()
        {
            $buttons = [];

            if (method_exists($this, 'use_clientArea_SingleSignOn'))
                $buttons["panel"] = [
                    'url'   => $this->area_link . "?inc=use_method&method=SingleSignOn",
                    'color' => 'blue',
                    'icon'  => 'fa fa-sign-in',
                    'name'  => __("website/account_products/login-panel"),
                ];

            if (method_exists($this, 'use_clientArea_webMail'))
                $buttons["mail"] = [
                    'url'  => $this->area_link . "?inc=use_method&method=webMail",
                    'name' => __("website/account_products/login-webmail"),
                ];

            return $buttons;
        }

        public function panel_links_for_admin()
        {
            $buttons = [];

            $rlb = Admin::isPrivilege(["MODULES_ROOT_LOGIN_BUTTON"]);

            if ($rlb && method_exists($this, 'use_adminArea_root_SingleSignOn'))
                $buttons["root_panel"] = [
                    'url'  => $this->area_link . "?operation=hosting_use_method&use_method=root_SingleSignOn",
                    'name' => __("admin/products/login-root-panel"),
                ];

            if (method_exists($this, 'use_adminArea_SingleSignOn'))
                $buttons["panel"] = [
                    'url'  => $this->area_link . "?operation=hosting_use_method&use_method=SingleSignOn",
                    'name' => __("website/account_products/login-panel"),
                ];

            if (method_exists($this, 'use_adminArea_webMail'))
                $buttons["mail"] = [
                    'url'  => $this->area_link . "?operation=hosting_use_method&use_method=webMail",
                    'name' => __("website/account_products/login-webmail"),
                ];

            return $buttons;
        }

        protected function udgrade($params = [])
        {
            Helper::Load("Events");
            $this->config = isset($params["config"]) ? $params["config"] : [];
            $this->options = $params;

            $updowngrade_remove = $this->server["updowngrade_remove_server"];

            if ($updowngrade_remove == "now") {
                if ($this->terminate()) return $this->create($params);
                return false;
            } elseif (substr($updowngrade_remove, 0, 4) == "then")
                Events::add_scheduled_operation([
                    'owner'    => "order",
                    'owner_id' => $this->order["id"],
                    'name'     => "remove-server-for-updowngrade",
                    'period'   => "day",
                    'time'     => substr($updowngrade_remove, 5, 4),
                    'module'   => $this->_name,
                    'command'  => "terminate",
                    'needs'    => ['options' => $this->options],
                ]);
            return $this->create($params);
        }

        public function save_config($data = [])
        {
            return FileManager::file_write($this->dir . "config.php", Utility::array_export($data, ['pwith' => true]));
        }

    }

    class AddonModule
    {
        public $error, $config, $lang, $area_link, $_name, $user, $admin;
        public $url = CORE_FOLDER . DS . MODULES_FOLDER . DS . "Addons" . DS;
        protected $dir;


        function __construct()
        {
            $this->dir = MODULE_DIR . "Addons" . DS . $this->_name . DS;
            $this->url .= $this->_name . DS;
            $this->url = Utility::link_determiner($this->url, false, false);
            if (!$this->area_link && defined("ADMINISTRATOR"))
                $this->area_link = Controllers::$init->AdminCRLink("tools-2", ["addons", $this->_name]);
            elseif (!$this->area_link)
                $this->area_link = Controllers::$init->CRLink("addon") . "/" . $this->_name;

            $this->config = Modules::Config("Addons", $this->_name);
            $this->lang = Modules::Lang("Addons", $this->_name);

            Helper::Load(["User"]);
            $a_data = UserManager::LoginData("admin");
            $u_data = UserManager::LoginData("member");
            $user_id = isset($u_data["id"]) ? $u_data["id"] : (isset($this->user["id"]) ? $this->user["id"] : 0);

            if ($a_data) {
                $this->admin = User::getData($a_data["id"], [
                    "id",
                    "name",
                    "surname",
                    "full_name",
                    "email",
                    "phone",
                    "lang",
                    "country",
                ], "array");
                $this->admin = array_merge($this->admin, User::getInfo($this->admin["id"], [
                    "gsm_cc",
                    "gsm_number",
                ]));
            }

            if ($user_id) {
                $this->user = User::getData($user_id, [
                    "id",
                    "name",
                    "surname",
                    "full_name",
                    "email",
                    "phone",
                    "lang",
                    "country",
                ], "array");
                $this->user = array_merge($this->user, User::getInfo($user_id, [
                    "gsm_cc",
                    "gsm_number",
                ]));
                $this->user["address"] = AddressManager::getAddress(false, $user_id);
            }
        }


        protected function view($file = '', $variables = [])
        {
            ob_start();
            if ($variables) extract($variables);
            if (file_exists($this->dir . "views" . DS . $file)) include $this->dir . "views" . DS . $file;
            return ob_get_clean();
        }


        protected function privileges()
        {
            return Models::$init->db->select()->from("privileges")->order_by("id ASC")->build() ? Models::$init->db->fetch_assoc() : [];
        }


        public function settings()
        {
            if ($this->version != $this->config["meta"]["version"]) {
                if (method_exists($this, 'upgrade')) {
                    if ($this->upgrade()) {
                        $set_config = $this->config;
                        $set_config["meta"]["version"] = $this->version;
                        FileManager::file_write($this->dir . "config.php", Utility::array_export($set_config, ['pwith' => true]));
                    } else {
                        echo '<div class="red-info"><div class="padding10">' . Bootstrap::$lang->get_cm("admin/tools/error17") . ($this->error ? ': ' : '') . $this->error . '</div></div>';
                        return false;
                    }
                }
            }

            $variables = [
                'lang'        => $this->lang,
                'config'      => $this->config,
                'request_uri' => $this->area_link,
                'fields'      => $this->fields(),
                'privileges'  => $this->privileges(),
                'access_ps'   => isset($this->config["access_ps"]) ? $this->config["access_ps"] : [],
                'module'      => $this,
            ];

            if (file_exists($this->dir . "views" . DS . "settings.php"))
                $view = $this->view("settings.php", $variables);
            else
                $view = View::$init->chose("admin")->render("default-addon-settings", $variables, true);

            if ($view) echo $view;
            else echo 'Not found settings file';
        }


        public function save_settings()
        {
            $fields = $this->fields();
            $set_config = $this->config;
            $p_fields = Filter::isPOST() ? Filter::POST("fields") : [];
            $access_ps = Filter::init("POST/access_ps");

            if (!is_array($access_ps)) $access_ps = [];

            if (method_exists($this, 'save_fields')) {
                $p_fields = $this->save_fields($p_fields);
                if (!is_array($p_fields) && $this->error) {

                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => $this->error,
                    ]);
                    return false;
                }
            }

            if ($fields) foreach ($fields as $f_k => $field) if (($field["type"] ?? "output") != "output") $set_config['settings'][$f_k] = $p_fields[$f_k];
            if ($p_fields) foreach ($p_fields as $f_k => $f_v) $set_config['settings'][$f_k] = $f_v;

            $set_config['access_ps'] = $access_ps;

            $this->save_config($set_config);

            echo Utility::jencode(['status' => "successful", 'message' => Bootstrap::$lang->get_cm("admin/tools/success12")]);
            return true;
        }


        public function fields_output($data = [], $_type = '')
        {
            return Modules::fields_output($this->_name, $data, $_type);
        }


        public function change_addon_status($arg = '')
        {
            $status = $arg == "enable";
            $apply = true;

            if ($status && method_exists($this, 'activate')) $apply = $this->activate();
            if (!$status && method_exists($this, 'deactivate')) $apply = $this->deactivate();

            if ($apply) {
                $config = $this->config;
                $config["status"] = $status;
                $this->save_config($config);
            }
            return $apply;
        }


        public function save_config($data = [])
        {
            return FileManager::file_write($this->dir . "config.php", Utility::array_export($data, ['pwith' => true]));
        }

    }

    class ProductModule
    {
        public $_name = null;
        public $page = null;
        public $area_link = false;
        public $config = [];
        public $lang = [];
        public $error = null;
        public $url = null;
        public $dir = null;
        public $order = [];
        public $options = [];
        public $user = [];
        public $admin = [];
        public $id_of_conf_opt = [];
        public $val_of_conf_opt = [];
        public $val_of_requirements = [];
        public $requirements = [];
        public $addons = [];
        public $product = [];


        function __construct()
        {
            $this->dir = MODULE_DIR . "Product" . DS . $this->_name . DS;
            $this->url .= CORE_FOLDER . DS . MODULES_FOLDER . DS . "Product" . DS . $this->_name . DS;
            $this->url = Utility::link_determiner($this->url, false, false);

            $this->config = Modules::Config("Product", $this->_name);
            $this->lang = Modules::Lang("Product", $this->_name);

            Helper::Load(["User"]);
            $a_data = UserManager::LoginData("admin");
            if ($a_data) {
                $this->admin = User::getData($a_data["id"], [
                    "id",
                    "name",
                    "surname",
                    "full_name",
                    "email",
                    "phone",
                    "lang",
                    "country",
                ], "array");
                $this->admin = array_merge($this->admin, User::getInfo($this->admin["id"], [
                    "gsm_cc",
                    "gsm_number",
                ]));
            }
        }

        static function save_log($type = '', $module = '', $action = '', $request = '', $response = '', $processed = '')
        {
            return Modules::save_log($type, $module, $action, $request, $response, $processed);
        }

        public function set_order($order = [])
        {
            $this->order = $order;
            Helper::Load(["Products", "User", "Orders"]);
            $this->options = $order["options"];
            $this->product = Products::get($order["type"], $order["product_id"]);
            $this->user = User::getData($order["owner_id"], "id,name,surname,full_name,email,phone,lang,country", "array");
            $this->user = array_merge($this->user, User::getInfo($order["owner_id"], ["gsm_cc", "gsm_number"]));
            $this->user["address"] = AddressManager::getAddress(false, $order["owner_id"]);

            $configurable_options = [];
            if ($addons = Orders::addons($this->order["id"])) {
                $lang = $this->user["lang"];
                foreach ($addons as $addon) {
                    if ($gAddon = Products::addon($addon["addon_id"], $lang)) {
                        $addon["attributes"] = $gAddon;
                        $this->addons[$addon["id"]] = $addon;
                        if ($gAddon["options"]) {
                            if ($gAddon["type"] == "quantity" || $addon["option_quantity"] > 0) {
                                if ($addon["option_quantity"] > 0)
                                    $addon_v = (int)$addon["option_quantity"];
                                else {
                                    $addon_v = $addon["option_name"];
                                    $addon_v = explode("x", $addon_v);
                                    $addon_v = (int)trim($addon_v[0]);
                                }
                            } else
                                $addon_v = '';
                            foreach ($gAddon["options"] as $option) {
                                if ($option["id"] == $addon["option_id"]) {
                                    if (isset($option["module"]) && $option["module"]) {
                                        if (isset($option["module"][$this->_name]) && !in_array($addon['status'], ["cancelled", "waiting"])) {
                                            $c_options = $option["module"][$this->_name]["configurable"];
                                            foreach ($c_options as $k => $v) {
                                                $d_v = $v;
                                                if(strlen($addon_v) > 0) $d_v = ((int) $d_v) * ((int) $addon_v);

                                                if (isset($configurable_options[$k]) && strlen($addon_v) > 0)
                                                    $configurable_options[$k] += $d_v;
                                                else
                                                    $configurable_options[$k] = $d_v;

                                                $this->id_of_conf_opt[$addon["id"]][$k] = $d_v;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $this->val_of_conf_opt = $configurable_options;

            $values_of_requirements = [];
            if ($requirements = Orders::requirements($this->order["id"])) {
                $this->requirements = $requirements;
                foreach ($requirements as $req) {
                    if ($req["module_co_names"]) {
                        $req["module_co_names"] = Utility::jdecode($req["module_co_names"], true);
                        if (isset($req["module_co_names"][$this->_name])) {
                            $c_o_name = $req["module_co_names"][$this->_name];
                            if (in_array($req["response_type"], ['input', 'password', 'textarea', 'file']))
                                $response = $req["response"];
                            else {
                                $mkey = $req["response_mkey"];
                                if ($dc = Utility::jdecode($mkey, true)) $mkey = $dc;
                                $response = is_array($mkey) && sizeof($mkey) < 2 ? current($mkey) : $mkey;
                            }
                            $values_of_requirements[$c_o_name] = $response;
                        }
                    }
                }
            }
            $this->val_of_requirements = $values_of_requirements;
            if (!$this->options) $this->options = $this->order["options"];
        }

        public function extend($data = [])
        {
            return method_exists($this, 'renewal') ? $this->renewal($data) : false;
        }

        public function edit_order_params()
        {
            $options = $this->options;
            if (!isset($options["creation_info"])) $options["creation_info"] = [];
            if (!isset($options["config"])) $options["config"] = [];
            $n_creation_info = (array)Filter::POST("creation_info");
            $n_config = (array)Filter::POST("config");
            $o_options = $options;

            if (isset($o_options["config"])) unset($o_options["config"]);
            if (isset($o_options["creation_info"])) unset($o_options["creation_info"]);

            $n_options = $o_options;


            if (method_exists($this, 'save_adminArea_service_fields')) {
                $moduleCall = $this->save_adminArea_service_fields([
                    'old' => [
                        'creation_info' => $options["creation_info"],
                        'config'        => $options["config"],
                        'options'       => $o_options,
                    ],
                    'new' => [
                        'creation_info' => $n_creation_info,
                        'config'        => $n_config,
                        'options'       => $n_options,
                    ],
                ]);

                if (!$moduleCall && $this->error) return false;
                if (is_array($moduleCall) && isset($moduleCall['creation_info'])) $n_creation_info = $moduleCall['creation_info'];
                if (is_array($moduleCall) && isset($moduleCall['config'])) $n_config = $moduleCall['config'];
                if (is_array($moduleCall) && isset($moduleCall['options'])) $n_options = $moduleCall['options'];
            }

            $options["config"] = $n_config;
            $options["creation_info"] = $n_creation_info;
            $options = array_merge($options, $n_options);

            return $options;
        }

        protected function encode_str($str = '')
        {
            return Crypt::encode($str, Config::get("crypt/user"));
        }

        protected function decode_str($str = '')
        {
            return Crypt::decode($str, Config::get("crypt/user"));
        }

        public function get_page($page_file = '', $vars = [])
        {
            $vars['module'] = $this;
            return Modules::getPage('Product', $this->_name, $page_file, $vars);
        }

        public function use_controller($param = '')
        {
            $method_name = '';
            $method_prefix_a = 'controller_';
            $param = str_replace("-", "_", $param);

            $method_name = method_exists($this, $method_prefix_a . $param) ? $method_prefix_a . $param : '';

            if ($method_name) return $this->{$method_name}();
        }

        public function use_method($param = '')
        {
            $method_name = '';
            $method_prefix_c = 'use_clientArea_';
            $method_prefix_a = 'use_adminArea_';
            $param = str_replace("-", "_", $param);

            if (defined("ADMINISTRATOR") && $param)
                $method_name = method_exists($this, $method_prefix_a . $param) ? $method_prefix_a . $param : '';
            elseif ($param)
                $method_name = method_exists($this, $method_prefix_c . $param) ? $method_prefix_c . $param : '';

            if ($method_name) return $this->{$method_name}();
        }

        public function save_config($data = [], $auto_status = true)
        {
            if ($auto_status && isset($data['settings']) && $data['settings']) $data['status'] = true;

            return FileManager::file_write($this->dir . "config.php", Utility::array_export($data, ['pwith' => true]));
        }

        public function config_options_output($data = [], $_type = '')
        {
            foreach ($data as $f_k => $f_v) {
                $wrap_width = isset($f_v["wrap_width"]) ? Filter::numbers($f_v["wrap_width"]) : 50;
                $wrap_class = 'yuzde' . $wrap_width;
                $name = $f_v["name"];
                $is_tooltip = isset($f_v["is_tooltip"]) && $f_v["is_tooltip"] ? true : false;
                $dec_pos = isset($f_v["description_pos"]) ? $f_v["description_pos"] : 'R';
                $description = isset($f_v["description"]) ? $f_v["description"] : null;
                $type = isset($f_v["type"]) ? $f_v["type"] : "text";
                $value = isset($f_v["value"]) ? $f_v["value"] : null;
                $placeholder = isset($f_v["placeholder"]) ? $f_v["placeholder"] : null;
                $width = isset($f_v["width"]) ? $f_v["width"] : null;
                $checked = isset($f_v["checked"]) ? $f_v["checked"] : false;
                $options = isset($f_v["options"]) ? $f_v["options"] : [];
                $class = $width ? 'yuzde' . $width : '';
                $_rows = isset($f_v["rows"]) ? $f_v["rows"] : false;

                if ($options && !is_array($options)) {
                    $n_options = [];
                    foreach (explode(",", $options) as $opt) $n_options[$opt] = $opt;
                    $options = $n_options;
                }
                if (!in_array($type, [
                    'text', 'password', 'approval', 'radio', 'dropdown', 'textarea', 'output',
                ])) $type = "text";
                ?>
                <div class="<?php echo $wrap_class; ?>" id="wrap-el-<?php echo $f_k; ?>">
                    <div class="formcon">
                        <div class="yuzde30">
                            <?php echo $name; ?>
                            <?php if ($description && $dec_pos == 'L' && $is_tooltip): ?>
                                <span class="kinfo"
                                      data-tooltip="<?php echo str_replace('"', '\"', $description); ?>"><i
                                            class="fa fa-question-circle-o"></i></span>
                            <?php elseif ($description && $dec_pos == 'L'): ?>
                                <div class="clear"></div>
                                <span class="kinfo"><?php echo $description; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="yuzde70">

                            <?php
                                if ($type == 'text') {
                                    ?>
                                <input
                                        type="text"
                                        name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                        class="<?php echo $class; ?>"
                                        placeholder="<?php echo $placeholder; ?>"
                                        value="<?php echo htmlentities($value); ?>"
                                        id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                />
                                <?php
                                    }
                                    elseif ($type == 'password')
                                    {
                                ?>
                                <input
                                        type="password"
                                        name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                        class="<?php echo $class; ?>"
                                        placeholder="<?php echo $placeholder; ?>"
                                        value="<?php echo htmlentities($value); ?>"
                                        id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                />
                                <?php
                                    }
                                    elseif ($type == 'approval')
                                    {
                                ?>
                                <input
                                    <?php echo $checked ? 'checked' : ''; ?>
                                        type="checkbox"
                                        name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                        class="checkbox-custom"
                                        value="<?php echo $value ? htmlentities($value) : 1; ?>"
                                        id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                />
                                    <label class="checkbox-custom-label"
                                           for="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"><span
                                                class="kinfo"><?php echo $dec_pos == 'R' && !$is_tooltip ? $description : ''; ?></span></label>
                                <?php
                                    }
                                    elseif ($type == 'textarea')
                                    {
                                ?>
                                    <textarea
                                            name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                            rows="<?php echo $_rows; ?>"
                                            class="<?php echo $class; ?>"
                                            placeholder="<?php echo $placeholder; ?>"
                                            id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                    ><?php echo $value; ?></textarea>
                                <?php
                                    }
                                    elseif ($type == 'dropdown')
                                    {
                                ?>
                                    <script type="text/javascript">
                                        $(document).ready(function () {
                                            $("#el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>").select2({
                                                width: '<?php echo $width ? $width : '100'; ?>%',
                                                placeholder: "<?php echo ___("needs/select-your"); ?>",
                                            });
                                        });
                                    </script>
                                    <select
                                            name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                            id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                            class="select2<?php echo $class ? ' ' . $class : ''; ?>"
                                    >
                                        <?php
                                            if ($options) {
                                                foreach ($options as $k => $v) {
                                                    ?>
                                                    <option<?php echo $value == $k ? ' selected' : ''; ?>
                                                            value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                                    <?php
                                                }
                                            }
                                        ?>
                                    </select>
                                <?php
                                    }
                                    elseif ($type == 'radio')
                                    {
                                    if ($options)
                                    {
                                    if (!$class) $class = 'yuzde30';
                                    $i = 0;
                                    foreach ($options

                                    as $k => $v)
                                    {
                                    $i++;
                                ?>
                                    <div style="display: inline-block; " class="<?php echo $class; ?>">
                                        <input<?php echo $value == $k ? ' checked' : ''; ?> type="radio"
                                                                                            value="<?php echo $k; ?>"
                                                                                            id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>-<?php echo $i; ?>"
                                                                                            class="radio-custom"
                                                                                            name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"/>
                                        <label class="radio-custom-label"
                                               for="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>-<?php echo $i; ?>"><?php echo $v; ?></label>
                                    </div>
                                    <?php
                                }
                                }

                                }
                                else echo $value;
                            ?>
                            <?php if ($description && $dec_pos == 'R' && $is_tooltip): ?>
                                <span class="kinfo"
                                      data-tooltip="<?php echo str_replace('"', '\"', $description); ?>"><i
                                            class="fa fa-question-circle-o"></i></span>
                            <?php elseif ($type != "approval"): ?>
                                <div class="clear"></div><?php endif; ?>
                            <?php if ($description && $dec_pos == 'R' && !$is_tooltip && $type != 'approval'): ?>
                                <span class="kinfo"><?php echo $description; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php
            }
        }

        public function configuration()
        {
            return '<div class="red-info"><div class="padding10">' . __('admin/modules/settings-page-not-found') . '</div></div>';
        }

        public function clientArea_buttons_output($external_buttons = [])
        {
            $text = '';
            $buttons = [];

            if ($external_buttons) $buttons = array_merge($buttons, $external_buttons);
            elseif (method_exists($this, 'clientArea_buttons')) $buttons = array_merge($buttons, $this->clientArea_buttons());

            if ($buttons) {
                foreach ($buttons as $b_k => $button) {
                    $type = isset($button['type']) ? $button['type'] : 'transaction';
                    if (!is_array($button)) $_text = $button;
                    else $_text = isset($button['text']) ? $button['text'] : 'Button';
                    $_blank = isset($button['target_blank']) ? $button['target_blank'] : false;
                    $icon = isset($button['icon']) ? $button['icon'] : null;
                    $link = null;
                    $attributes = isset($button["attributes"]) ? $button["attributes"] : [];

                    if ($type == 'transaction') $link = 'javascript:void 0;';
                    elseif ($type == 'page-loader') $link = "javascript:reload_module_content('" . $b_k . "'); void 0;";
                    elseif ($type == 'page') $link = $this->area_link . "?m_page=" . $b_k;
                    elseif ($type == 'function') $link = $this->area_link . "?action=use_method&method=" . $b_k;
                    elseif ($type == 'link') $link = isset($button['url']) ? $button['url'] : $this->area_link;

                    $text .= '<a';
                    if (!isset($attributes['href'])) $text .= ' href="' . $link . '"';
                    if (!isset($attributes['onclick']) && $type == 'transaction') $text .= ' onclick="run_transaction(\'' . $b_k . '\',this);"';
                    if ($_blank) $text .= ' target="_blank"';
                    if (!isset($attributes['class'])) $text .= ' class="hostbtn"';
                    if ($attributes) foreach ($attributes as $k => $v) $text .= ' ' . $k . '="' . $v . '"';
                    $text .= '>';
                    if ($icon) $text .= '<i class="' . $icon . '"></i> ';
                    $text .= $_text;
                    $text .= '';
                    $text .= '</a> ';
                }
            }

            return $text;
        }

        public function adminArea_buttons_output($external_buttons = [])
        {
            $text = '';
            $buttons = [];

            if ($external_buttons) $buttons = array_merge($buttons, $external_buttons);
            elseif (method_exists($this, 'adminArea_buttons')) $buttons = array_merge($buttons, $this->adminArea_buttons());

            if ($buttons) {
                foreach ($buttons as $b_k => $button) {
                    $type = isset($button['type']) ? $button['type'] : 'transaction';
                    if (!is_array($button)) $_text = $button;
                    else $_text = isset($button['text']) ? $button['text'] : 'Button';
                    $_blank = isset($button['target_blank']) ? $button['target_blank'] : false;
                    $icon = isset($button['icon']) ? $button['icon'] : null;
                    $link = null;
                    $attributes = isset($button["attributes"]) ? $button["attributes"] : [];

                    if ($type == 'transaction') $link = 'javascript:void 0;';
                    elseif ($type == 'function')
                        $link = $this->area_link . "?operation=operation_special_automation&use_method=" . $b_k;

                    elseif ($type == 'link') $link = isset($button['url']) ? $button['url'] : $this->area_link;

                    $text .= '<a';
                    if (!isset($attributes['href'])) $text .= ' href="' . $link . '"';
                    if (!isset($attributes['onclick']) && $type == 'transaction') $text .= ' onclick="run_transaction(\'' . $b_k . '\',this);"';
                    if ($_blank) $text .= ' target="_blank"';
                    if (!isset($attributes['class'])) $text .= ' class="hostbtn"';
                    if ($attributes) foreach ($attributes as $k => $v) $text .= ' ' . $k . '="' . $v . '"';
                    $text .= '>';
                    if ($icon) $text .= '<i class="' . $icon . '"></i> ';
                    $text .= $_text;
                    $text .= '';
                    $text .= '</a> ';
                }
            }

            return $text;
        }

    }

    class FraudModule
    {
        public $error, $config, $lang, $_name, $user, $admin;
        public $url = CORE_FOLDER . DS . MODULES_FOLDER . DS . "Fraud" . DS;
        protected $dir;


        function __construct()
        {
            $this->_name = substr($this->_name, 6);

            $this->dir = MODULE_DIR . "Fraud" . DS . $this->_name . DS;
            $this->url .= $this->_name . DS;
            $this->url = Utility::link_determiner($this->url, false, false);
            $this->config = Modules::Config("Fraud", $this->_name);
            $this->lang = Modules::Lang("Fraud", $this->_name);

            Helper::Load(["User"]);
            $a_data = UserManager::LoginData("admin");
            $u_data = UserManager::LoginData("member");
            $user_id = isset($u_data["id"]) ? $u_data["id"] : (isset($this->user["id"]) ? $this->user["id"] : 0);

            if ($a_data) {
                $this->admin = User::getData($a_data["id"], [
                    "id",
                    "name",
                    "surname",
                    "full_name",
                    "email",
                    "phone",
                    "lang",
                    "country",
                ], "array");
                $this->admin = array_merge($this->admin, User::getInfo($this->admin["id"], [
                    "gsm_cc",
                    "gsm_number",
                ]));
            }

            if ($user_id) {
                $this->user = User::getData($user_id, [
                    "id",
                    "name",
                    "surname",
                    "full_name",
                    "email",
                    "phone",
                    "lang",
                    "country",
                ], "array");
                $this->user = array_merge($this->user, User::getInfo($user_id, [
                    "gsm_cc",
                    "gsm_number",
                ]));
                $this->user["address"] = AddressManager::getAddress(false, $user_id);
            }
        }


        public function fields_output($data = [], $_type = '')
        {
            if (!$_type) $_type = 'fields';
            foreach ($data as $f_k => $f_v) {
                $wrap_width = isset($f_v["wrap_width"]) ? Filter::numbers($f_v["wrap_width"]) : 50;
                $wrap_class = 'yuzde' . $wrap_width;
                $name = isset($f_v["name"]) ? $f_v["name"] : '';
                $is_tooltip = isset($f_v["is_tooltip"]) && $f_v["is_tooltip"] ? true : false;
                $dec_pos = isset($f_v["description_pos"]) ? $f_v["description_pos"] : 'R';
                $description = isset($f_v["description"]) ? $f_v["description"] : null;
                $type = isset($f_v["type"]) ? $f_v["type"] : "text";
                $value = isset($f_v["value"]) ? $f_v["value"] : null;
                $placeholder = isset($f_v["placeholder"]) ? $f_v["placeholder"] : null;
                $width = isset($f_v["width"]) ? $f_v["width"] : null;
                $checked = isset($f_v["checked"]) ? $f_v["checked"] : false;
                $options = isset($f_v["options"]) ? $f_v["options"] : [];
                $class = $width ? 'yuzde' . $width : '';
                $_rows = isset($f_v["rows"]) ? $f_v["rows"] : false;

                if ($options && !is_array($options)) {
                    $n_options = [];
                    foreach (explode(",", $options) as $opt) $n_options[$opt] = $opt;
                    $options = $n_options;
                }
                if (!in_array($type, [
                    'text', 'password', 'approval', 'radio', 'dropdown', 'textarea', 'output', 'html',
                ])) $type = "text";

                if ($type == 'html') {
                    echo $value;
                    continue;
                }
                ?>
                <div class="<?php echo $wrap_class; ?>" id="wrap-el-<?php echo $f_k; ?>">
                    <div class="formcon">
                        <div class="yuzde30">
                            <?php echo $name; ?>
                            <?php if ($description && $dec_pos == 'L' && $is_tooltip): ?>
                                <span class="kinfo"
                                      data-tooltip="<?php echo str_replace('"', '\"', $description); ?>"><i
                                            class="fa fa-question-circle-o"></i></span>
                            <?php elseif ($description && $dec_pos == 'L'): ?>
                                <div class="clear"></div>
                                <span class="kinfo"><?php echo $description; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="yuzde70">
                            <?php
                                if ($type == 'text') {
                                    ?>
                                <input
                                        type="text"
                                        name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                        class="<?php echo $class; ?>"
                                        style="vertical-align: middle;"
                                        placeholder="<?php echo $placeholder; ?>"
                                        value="<?php echo htmlentities($value); ?>"
                                        id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                />
                                <?php
                                    }
                                    elseif ($type == 'password')
                                    {
                                ?>
                                <input
                                        type="password"
                                        name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                        class="<?php echo $class; ?>"
                                        style="vertical-align: middle;"
                                        placeholder="<?php echo $placeholder; ?>"
                                        value="<?php echo htmlentities($value); ?>"
                                        id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                />
                                <?php
                                    }
                                    elseif ($type == 'file')
                                    {
                                ?>
                                <input
                                        type="file"
                                        name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                        class="<?php echo $class; ?>"
                                        style="vertical-align: middle;"
                                        id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                />
                                <?php
                                    }
                                    elseif ($type == 'approval')
                                    {
                                ?>
                                <input
                                    <?php echo $checked ? 'checked' : ''; ?>
                                        type="checkbox"
                                        name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                        class="checkbox-custom"
                                        value="<?php echo $value ? htmlentities($value) : 1; ?>"
                                        id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                />
                                    <label class="checkbox-custom-label"
                                           for="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"><span
                                                class="kinfo"><?php echo $dec_pos == 'R' && !$is_tooltip ? $description : ''; ?></span></label>
                                <?php
                                    }
                                    elseif ($type == 'textarea')
                                    {
                                ?>
                                    <textarea
                                            name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                            rows="<?php echo $_rows; ?>"
                                            class="<?php echo $class; ?>"
                                            style="vertical-align: middle;"
                                            placeholder="<?php echo $placeholder; ?>"
                                            id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                    ><?php echo $value; ?></textarea>
                                <?php
                                    }
                                    elseif ($type == 'dropdown')
                                    {
                                ?>
                                    <script type="text/javascript">
                                        $(document).ready(function () {
                                            $("#el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>").select2({
                                                width: '<?php echo $width ? $width : '100'; ?>%',
                                                placeholder: "<?php echo ___("needs/select-your"); ?>",
                                            });
                                        });
                                    </script>
                                    <select
                                            name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                            id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                            class="select2<?php echo $class ? ' ' . $class : ''; ?>"
                                            style="vertical-align: middle;"
                                    >
                                        <?php
                                            if ($options) {
                                                foreach ($options as $k => $v) {
                                                    ?>
                                                    <option<?php echo $value == $k ? ' selected' : ''; ?>
                                                            value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                                    <?php
                                                }
                                            }
                                        ?>
                                    </select>
                                <?php
                                    }
                                    elseif ($type == 'radio')
                                    {
                                    if ($options)
                                    {
                                    if (!$class) $class = 'yuzde30';
                                    $i = 0;
                                    foreach ($options

                                    as $k => $v)
                                    {
                                    $i++;
                                ?>
                                    <div style="display: inline-block; " class="<?php echo $class; ?>">
                                        <input<?php echo $value == $k ? ' checked' : ''; ?> type="radio"
                                                                                            value="<?php echo $k; ?>"
                                                                                            id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>-<?php echo $i; ?>"
                                                                                            class="radio-custom"
                                                                                            name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"/>
                                        <label class="radio-custom-label"
                                               for="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>-<?php echo $i; ?>"><?php echo $v; ?></label>
                                    </div>
                                    <?php
                                }
                                }

                                }
                                else echo $value;
                            ?>
                            <?php if ($description && $dec_pos == 'R' && $is_tooltip): ?>
                                <span class="kinfo"
                                      data-tooltip="<?php echo str_replace('"', '\"', $description); ?>"><i
                                            class="fa fa-question-circle-o"></i></span>
                            <?php elseif ($type != "approval" && !$width): ?>
                                <div class="clear"></div><?php endif; ?>
                            <?php if ($description && $dec_pos == 'R' && !$is_tooltip && $type != 'approval'): ?>
                                <span class="kinfo"><?php echo $description; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php
            }
        }


        public function save_config($data = [])
        {
            return FileManager::file_write($this->dir . "config.php", Utility::array_export($data, ['pwith' => true]));
        }


        public function insert_record($user_id, $message = '')
        {
            return Models::$init->db->insert("fraud_detected_records", [
                'module'     => $this->_name,
                'user_id'    => $user_id,
                'message'    => $message,
                'created_at' => DateManager::Now(),
            ]);
        }


        public function records()
        {
            $select = [
                '*',
                '(SELECT full_name FROM ' . Models::$init->pfx . 'users WHERE id=t1.user_id) AS user_full_name',
                '(SELECT company_name FROM ' . Models::$init->pfx . 'users WHERE id=t1.user_id) AS user_company_name',
            ];
            $stmt = Models::$init->db->select(implode(",", $select))->from("fraud_detected_records AS t1");
            $stmt->where("module", "=", $this->_name);
            $stmt->order_by("created_at DESC");
            $stmt->limit(500);
            return $stmt->build() ? $stmt->fetch_assoc() : [];
        }
    }

    class PaymentGatewayModule
    {
        public $name, $error, $config, $lang, $_name, $user, $admin, $payform, $checkout_id, $checkout;
        public $card_storage = false;
        public $standard_card = false;
        public $call_function = [], $links = [];
        public $commission = true, $page_type = "in-page", $callback_type = "server-sided";
        public $url = CORE_FOLDER . DS . MODULES_FOLDER . DS . "Payment" . DS;
        public $clientInfo;
        public $dir;
        public $l_payNow = '';


        function __construct()
        {
            Controllers::$init->takeDatas("language");
            $this->clientInfo = new stdClass();
            $this->_name = $this->name;
            $this->dir = MODULE_DIR . "Payment" . DS . $this->_name . DS;
            $this->url .= $this->_name . DS;
            $this->url = Utility::link_determiner($this->url, false, false);
            $this->config = Modules::Config("Payment", $this->_name);
            $this->lang = Modules::Lang("Payment", $this->_name);

            if (!isset($this->lang['pay-button']))
                $this->l_payNow = __("website/payment/pay-now");
            else
                $this->l_payNow = $this->lang['pay-button'];


            $card_storage = $this->config["meta"]["card-storage-supported"] ?? -1;
            $standard_card = $this->config["meta"]["standard-card-form"] ?? -1;


            if (!is_bool($card_storage) && $card_storage == -1) $card_storage = $this->card_storage;
            else $this->card_storage = $card_storage;


            if ($standard_card == -1)
                $standard_card = $this->standard_card;
            else
                $this->standard_card = $standard_card;

            if (method_exists($this, 'capture') && $this->name != "PayTR" && $card_storage && $standard_card)
                $this->payform = TEMPLATE_DIR . "system" . DS . "credit-card-storage-form";
            elseif (method_exists($this, 'capture') && $this->name != "PayTR" && $standard_card)
                $this->payform = TEMPLATE_DIR . "system" . DS . "credit-card-standard-form";
            elseif (method_exists($this, 'area'))
                $this->payform = TEMPLATE_DIR . "system" . DS . "payment-area";
            else
                $this->payform = $this->dir . "pages" . DS . "payform";


            $this->links['callback'] = Controllers::$init->CRLink("payment", [$this->name, $this->get_auth_token(), 'callback']);
            $this->links["successful"] = Controllers::$init->CRLink("pay-successful");
            $this->links["failed"] = Controllers::$init->CRLink("pay-failed");

            if (method_exists($this, 'capture') && $this->name != "PayTR") {
                $this->links['capture'] = Controllers::$init->CRLink("payment", [$this->name, 'function', 'capture']);
                $this->call_function['capture'] = 'pre_capture';
            }

            if (method_exists($this, 'capture')) {
                $this->links["getBin"] = Controllers::$init->CRLink("payment", [$this->name, 'function', 'getBin']);
                $this->call_function['getBin'] = 'get_card_bin';
            }


            Helper::Load(["User"]);
            $a_data = UserManager::LoginData("admin");
            $u_data = UserManager::LoginData("member");
            $user_id = isset($u_data["id"]) ? $u_data["id"] : (isset($this->user["id"]) ? $this->user["id"] : 0);

            if ($a_data) {
                $this->admin = User::getData($a_data["id"], [
                    "id",
                    "name",
                    "surname",
                    "full_name",
                    "email",
                    "phone",
                    "lang",
                    "country",
                ], "array");
                $this->admin = array_merge($this->admin, User::getInfo($this->admin["id"], [
                    "gsm_cc",
                    "gsm_number",
                ]));
            }

            if ($user_id) {
                $this->user = User::getData($user_id, [
                    "id",
                    "name",
                    "surname",
                    "full_name",
                    "email",
                    "phone",
                    "lang",
                    "country",
                ], "array");
                $this->user = array_merge($this->user, User::getInfo($user_id, [
                    "gsm_cc",
                    "gsm_number",
                ]));
                $this->user["address"] = AddressManager::getAddress(false, $user_id);
            }
        }


        public function get_stored_card($id = 0, $user_id = 0)
        {
            $g_stored = Models::$init->db->select()->from("users_stored_cards");
            if ($user_id > 0) $g_stored->where("user_id", "=", $user_id, "&&");
            $g_stored->where("id", "=", $id);
            $g_stored = $g_stored->build() ? $g_stored->getAssoc() : [];
            if ($g_stored) {
                $token = $g_stored["token"];
                $cvc = $g_stored["cvc"];
                if ($token) if ($dc = Crypt::decode($token, Config::get("crypt/user") . "**STORED_CARD**")) $token = $dc;
                if ($cvc) if ($dc = Crypt::decode($cvc, Config::get("crypt/user") . "**STORED_CARD**")) $cvc = $dc;
                if ($is_arr = Utility::jdecode($token, true)) $token = $is_arr;
                $g_stored["token"] = $token;
                $g_stored["cvc"] = $cvc;
            }
            return $g_stored;
        }

        static function find_bank_logo($bank_name = '', $card_brand = '', $card_type = '', $returnComparisons = false)
        {
            $comparisons = [];
            $bank_logo_i = APP_URI . "/resources/assets/images/creditcardlogos/";
            if ($card_type == "debit") {
                $comparisons['kredi'] = $bank_logo_i . 'yapikredi.png';
                $comparisons['akbank'] = $bank_logo_i . 'akbank.png';
                $comparisons['İş Bank'] = $bank_logo_i . 'isbankasi.png';
                $comparisons['Finansbank'] = $bank_logo_i . 'finansbank.png';
                $comparisons['Halkbank'] = $bank_logo_i . 'halkbank.png';
                $comparisons['HSBC'] = $bank_logo_i . 'HSBC.png';
                $comparisons['Ziraat'] = $bank_logo_i . 'ziraat-bankasi.png';
                $comparisons['Garanti'] = $bank_logo_i . 'garanti.png';
            } else {
                $comparisons['kredi'] = $bank_logo_i . 'world-yapikredi.png';
                $comparisons['akbank'] = $bank_logo_i . 'axess-akbank.png';
                $comparisons['İş Bank'] = $bank_logo_i . 'maximum-isbankasi.png';
                $comparisons['Finansbank'] = $bank_logo_i . 'cardfinans-finansbank.png';
                $comparisons['Halkbank'] = $bank_logo_i . 'paraf-halkbank.png';
                $comparisons['HSBC'] = $bank_logo_i . 'advantage-HSBC.png';
                $comparisons['Ziraat'] = $bank_logo_i . 'bankkart-ziraat.png';
                $comparisons['Garanti'] = $bank_logo_i . 'bonus-garanti.png';
            }

            $hook_data = Hook::run("PaymentGatewayBankLogoAdd", $bank_name, $card_brand, $card_type);
            if ($hook_data && is_array($hook_data)) {
                foreach ($hook_data as $hook_datum)
                    if ($hook_datum && is_array($hook_datum)) $comparisons = array_merge($comparisons, $hook_datum);
            }


            $result = false;

            if ($comparisons)
                foreach ($comparisons as $k => $v)
                    if (!$result && stristr($bank_name, $k))
                        $result = $v;


            return $returnComparisons ? $comparisons : $result;
        }

        public function generate_card_identification_checkout($pmethod = '')
        {
            Helper::Load(["Invoices", "Basket"]);
            $udata = UserManager::LoginData();
            if (!$udata) return false;
            $user_data = Invoices::generate_user_data($udata["id"]);
            if (!isset($user_data["address"])) {
                $this->error = "no-address";
                return false;
            }

            $currency = $user_data["currency"];
            $amount = 1;
            $local_c = Config::get("general/currency");
            $address_c = isset($user_data["address"]["country_code"]) ? $user_data["address"]["country_code"] : '';
            if ($currency != 147 && $address_c == "TR" && $local_c == 147) $currency = 147;

            $data = [
                'type'                    => "card-identification",
                'user_data'               => $user_data,
                'user_id'                 => $udata["id"],
                'currency'                => $currency,
                'subtotal'                => $amount,
                'total'                   => $amount,
                'pmethod'                 => $pmethod,
                'pmethod_commission'      => 0,
                'pmethod_commission_rate' => 0,
                'redirect'                => [
                    'success' => Controllers::$init->CRLink('ac-ps-info') . '?tab=csm&identification_status=successful',
                    'failed'  => Controllers::$init->CRLink('ac-ps-info') . '?tab=csm&identification_status=failed',
                ],
            ];

            $items = [
                [

                    'options'      => [],
                    'name'         => 'CARD IDENTIFICATION',
                    'quantity'     => 1,
                    'amount'       => 1,
                    'total_amount' => 1,

                ],
            ];

            $checkout_id = Basket::add_checkout([
                'user_id' => $udata["id"],
                'type'    => "card-identification",
                'items'   => Utility::jencode($items),
                'data'    => Utility::jencode($data),
                'cdate'   => DateManager::Now(),
                'mdfdate' => DateManager::Now(),
            ]);

            return $checkout_id;
        }


        public function get_auth_token()
        {
            $syskey = Config::get("crypt/system");
            $token = md5(Crypt::encode($this->name . "-Auth-Token=" . $syskey, $syskey));
            return $token;
        }


        public function set_checkout($checkout)
        {
            $this->checkout_id = $checkout["id"];
            $this->checkout = $checkout;

            if (isset($this->checkout["data"]["redirect"]["success"]))
                $this->links["successful"] = $this->checkout["data"]["redirect"]["success"];

            if (isset($this->checkout["data"]["redirect"]["failed"]))
                $this->links["failed"] = $this->checkout["data"]["redirect"]["failed"];

            if (isset($this->checkout["data"]["redirect"]["return"]))
                $this->links["return"] = $this->checkout["data"]["redirect"]["return"];

            $this->clientInfo = $this->generate_clientDetails();

        }


        public function commission_fee_calculator($amount)
        {
            $rate = $this->get_commission_rate();
            if (!$rate) return 0;
            $calculate = Money::get_discount_amount($amount, $rate);
            return $calculate;
        }


        public function get_commission_rate()
        {
            return $this->config["settings"]["commission_rate"];
        }


        public function cid_convert_code($id = 0)
        {
            Helper::Load("Money");
            $currency = Money::Currency($id);
            if ($currency) return $currency["code"];
            return false;
        }


        public function currency($id = 0)
        {
            return $this->cid_convert_code($id);
        }


        public function generate_clientDetails($data = [])
        {
            return Utility::jdecode(Utility::jencode($data ? $data : $this->checkout["data"]["user_data"]));
        }


        public function get_ip()
        {
            return UserManager::GetIP();
        }


        public function payment_result()
        {
            if (method_exists($this, 'callback')) return $this->payment_finish($this->callback());

            return ['status' => 'ERROR', 'message' => "Not found callback function"];
        }


        public function controller_settings()
        {
            Helper::Load(["Money"]);

            $fields = Filter::POST("fields");
            $commission_rate = Filter::init("POST/commission_rate", "amount");
            $commission_rate = str_replace(",", ".", $commission_rate);
            $convert_to = (int)Filter::init("POST/force_convert_to", "numbers");
            $accepted_cs = Filter::init("POST/accepted_countries");
            $unaccepted_cs = Filter::init("POST/unaccepted_countries");

            if (!$accepted_cs) $accepted_cs = [];
            if (!$unaccepted_cs) $unaccepted_cs = [];


            $sets = $this->config;
            $sets2 = [];

            if (method_exists($this, "config_fields_filter"))
                $fields = $this->config_fields_filter($fields);

            $config_fields = $this->config_fields();

            if ($config_fields) {
                foreach ($config_fields as $k => $v) {
                    if (isset($fields[$k]))
                        $sets['settings'][$k] = $fields[$k];
                    else
                        $sets['settings'][$k] = false;
                }
            }

            if ($commission_rate != $this->config["settings"]["commission_rate"])
                $sets["settings"]["commission_rate"] = $commission_rate;

            if (!isset($this->config["settings"]["force_convert_to"]) || $convert_to != $this->config["settings"]["force_convert_to"])
                $sets["settings"]["force_convert_to"] = $convert_to;

            if (!isset($this->config["settings"]["accepted_countries"]) || $accepted_cs != $this->config["settings"]["accepted_countries"]) {
                $sets["settings"]["accepted_countries"] = null;
                $sets2["settings"]["accepted_countries"] = $accepted_cs;

            }

            if (!isset($this->config["settings"]["unaccepted_countries"]) || $unaccepted_cs != $this->config["settings"]["unaccepted_countries"]) {
                $sets["settings"]["unaccepted_countries"] = null;
                $sets2["settings"]["unaccepted_countries"] = $unaccepted_cs;
            }


            if ($sets) {
                $config_result = array_replace_recursive($this->config, $sets);

                if ($sets2) $config_result = array_replace_recursive($config_result, $sets2);
                $array_export = Utility::array_export($config_result, ['pwith' => true]);

                $file = $this->dir . "config.php";

                FileManager::file_write($file, $array_export);

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-payment-module-settings", [
                    'module' => $this->config["meta"]["name"] ?? $this->name,
                    'name'   => $this->lang["name"] ?? $this->name,
                ]);
            }

            echo Utility::jencode([
                'status'  => "successful",
                'message' => Bootstrap::$lang->get_cm("admin/financial/success1"),
            ]);

            return true;
        }


        public function config_fields_output()
        {
            $data = $this->config_fields();

            $_type = 'fields';
            foreach ($data as $f_k => $f_v) {
                $wrap_width = isset($f_v["wrap_width"]) ? Filter::numbers($f_v["wrap_width"]) : 100;
                $wrap_class = 'yuzde' . $wrap_width;
                $name = $f_v["name"];
                $is_tooltip = isset($f_v["is_tooltip"]) && $f_v["is_tooltip"];
                $dec_pos = $f_v["description_pos"] ?? 'R';
                $description = $f_v["description"] ?? null;
                $type = $f_v["type"] ?? "text";
                $value = $f_v["value"] ?? null;
                $placeholder = $f_v["placeholder"] ?? null;
                $width = $f_v["width"] ?? null;
                $checked = $f_v["checked"] ?? false;
                $options = $f_v["options"] ?? [];
                $class = $width ? 'yuzde' . $width : '';
                $_rows = isset($f_v["rows"]) ? $f_v["rows"] : false;

                if ($options && !is_array($options)) {
                    $n_options = [];
                    foreach (explode(",", $options) as $opt) $n_options[$opt] = $opt;
                    $options = $n_options;
                }
                if (!in_array($type, [
                    'info', 'text', 'password', 'approval', 'radio', 'dropdown', 'textarea', 'output',
                ])) $type = "text";
                ?>
                <div class="<?php echo $wrap_class; ?>" id="wrap-el-<?php echo $f_k; ?>">
                    <div class="formcon">
                        <div class="yuzde30">
                            <?php echo $name; ?>
                            <?php if ($description && $dec_pos == 'L' && $is_tooltip): ?>
                                <span class="kinfo"
                                      data-tooltip="<?php echo str_replace('"', '\"', $description); ?>"><i
                                            class="fa fa-question-circle-o"></i></span>
                            <?php elseif ($description && $dec_pos == 'L'): ?>
                                <div class="clear"></div>
                                <span class="kinfo"><?php echo $description; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="yuzde70">

                            <?php
                                if ($type == 'info') {
                                    echo $value;
                                }
                                elseif ($type == 'text') {
                                    ?>
                                <input
                                        type="text"
                                        name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                        class="<?php echo $class; ?>"
                                        placeholder="<?php echo $placeholder; ?>"
                                        value="<?php echo htmlentities($value); ?>"
                                        id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                />
                                <?php
                                    }
                                    elseif ($type == 'password')
                                    {
                                ?>
                                <input
                                        type="password"
                                        name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                        class="<?php echo $class; ?>"
                                        placeholder="<?php echo $placeholder; ?>"
                                        value="<?php echo htmlentities($value); ?>"
                                        id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                />
                                <?php
                                    }
                                    elseif ($type == 'approval')
                                    {
                                ?>
                                <input
                                    <?php echo $checked ? 'checked' : ''; ?>
                                        type="checkbox"
                                        name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                        class="checkbox-custom"
                                        value="<?php echo $value ? htmlentities($value) : 1; ?>"
                                        id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                />
                                    <label class="checkbox-custom-label"
                                           for="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"><span
                                                class="kinfo"><?php echo $dec_pos == 'R' && !$is_tooltip ? $description : ''; ?></span></label>
                                <?php
                                    }
                                    elseif ($type == 'textarea')
                                    {
                                ?>
                                    <textarea
                                            name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                            rows="<?php echo $_rows; ?>"
                                            class="<?php echo $class; ?>"
                                            placeholder="<?php echo $placeholder; ?>"
                                            id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                    ><?php echo $value; ?></textarea>
                                <?php
                                    }
                                    elseif ($type == 'dropdown')
                                    {
                                ?>
                                    <script type="text/javascript">
                                        $(document).ready(function () {
                                            $("#el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>").select2({
                                                width: '<?php echo $width ? $width : '100'; ?>%',
                                                placeholder: "<?php echo ___("needs/select-your"); ?>",
                                            });
                                        });
                                    </script>
                                    <select
                                            name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"
                                            id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>"
                                            class="select2<?php echo $class ? ' ' . $class : ''; ?>"
                                    >
                                        <?php
                                            if ($options) {
                                                foreach ($options as $k => $v) {
                                                    ?>
                                                    <option<?php echo $value == $k ? ' selected' : ''; ?>
                                                            value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                                    <?php
                                                }
                                            }
                                        ?>
                                    </select>
                                <?php
                                    }
                                    elseif ($type == 'radio')
                                    {
                                    if ($options)
                                    {
                                    if (!$class) $class = 'yuzde30';
                                    $i = 0;
                                    foreach ($options

                                    as $k => $v)
                                    {
                                    $i++;
                                ?>
                                    <div style="display: inline-block; " class="<?php echo $class; ?>">
                                        <input<?php echo $value == $k ? ' checked' : ''; ?> type="radio"
                                                                                            value="<?php echo $k; ?>"
                                                                                            id="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>-<?php echo $i; ?>"
                                                                                            class="radio-custom"
                                                                                            name="<?php echo $_type; ?>[<?php echo $f_k; ?>]"/>
                                        <label class="radio-custom-label"
                                               for="el-<?php echo $this->_name; ?>-item-<?php echo $f_k; ?>-<?php echo $i; ?>"><?php echo $v; ?></label>
                                    </div>
                                    <?php
                                }
                                }

                                }
                                else echo $value;
                            ?>
                            <?php if ($description && $dec_pos == 'R' && $is_tooltip): ?>
                                <span class="kinfo"
                                      data-tooltip="<?php echo str_replace('"', '\"', $description); ?>"><i
                                            class="fa fa-question-circle-o"></i></span>
                            <?php elseif ($type != "approval"): ?>
                                <div class="clear"></div><?php endif; ?>
                            <?php if ($description && $dec_pos == 'R' && $type != "approval"): ?>
                                <span class="kinfo"><?php echo $description; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php
            }
        }


        public function bin_check_international($bin_number = "")
        {
            $bin_number = substr($bin_number, 0, 8);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://data.handyapi.com/bin/" . $bin_number);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $result = @curl_exec($ch);

            if (curl_errno($ch)) {
                $this->error = "Connection refused";
                curl_close($ch);
                return false;
            }
            curl_close($ch);
            $parse = json_decode($result, true);

            if (!$parse) {
                $this->error = $result;
                return false;
            }

            $result = $parse;

            //if(isset($result['Status']) && $result['Status'] != 'SUCCESS')
            //{
            //    //$this->error = Utility::jencode($result);
            //    //return false;
            //}

            if (!is_array($result)) $result = [];

            return [
                'country'   => isset($result['Country']['A2']) ? $result['Country']['A2'] : 'US',
                'card_type' => strtolower(isset($result['Type']) ? $result['Type'] : 'DEBIT'),
                'schema'    => isset($result['Scheme']) ? strtoupper($result['Scheme']) : '',
                'bank_name' => isset($result['Issuer']) ? $result['Issuer'] : 'UNKNOWN',
                'brand'     => isset($result['CardTier']) ? $result['CardTier'] : '',
            ];
        }


        public function get_card_bin()
        {
            $stored_card = Filter::init("POST/stored_card", "numbers");
            $number = Filter::init("POST/number", "numbers");
            $chid = Filter::init("POST/chid", "numbers");
            $identification = Filter::init("POST/identification", "numbers");
            $number = substr($number, 0, 6);

            Helper::Load(["Basket", "Money"]);

            $checkout = $identification ? [] : Basket::get_checkout($chid);

            if (!$checkout && !$identification) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Invalid Checkout ID",
                ]);
                return false;
            }

            if ($stored_card && $checkout) {
                $user_data = $checkout["data"]["user_data"];
                $g_stored = $this->get_stored_card($stored_card, $user_data["id"]);
                if (!$g_stored) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => "Invalid Stored Card",
                    ]);
                    return false;
                }
                $result = [
                    'country'   => "TR",
                    'card_type' => $g_stored['card_type'],
                    'schema'    => $g_stored['card_schema'],
                    'bank_name' => $g_stored['bank_name'],
                    'brand'     => $g_stored['card_brand'],
                ];
            } else {
                if (strlen($number) !== 6) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => "Must be 6 characters",
                    ]);
                    return false;
                }

                $result = false;

                if (method_exists($this, 'bin_check'))
                    $result = $this->bin_check($number);
                else
                    $this->error = "INTERNATIONAL";

                if (!$result && $this->error == "INTERNATIONAL")
                    $result = $this->bin_check_international($number);

                if (!$result) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => $this->error,
                    ]);
                    return false;
                }

            }
            $result['status'] = "successful";

            if ($result["bank_name"] == '') $result["bank_name"] = "Unknown Bank";

            $total_payable = $checkout["data"]["total"];
            $cid = $checkout["data"]["currency"];

            $force_curr = $this->config["settings"]["force_convert_to"] ?? 0;

            if ($force_curr > 0 && $cid != $force_curr) {
                $total_payable = Money::exChange($total_payable, $cid, $force_curr);
                $cid = $force_curr;
            }

            $result['total_payable'] = Money::formatter_symbol($total_payable, $cid);
            $result['total_payable_uf'] = Money::formatter($total_payable, $cid);


            if ($result['card_type'] != "" && $result['card_type'] != 'debit' && $checkout && method_exists($this, 'installment_rates')) {
                $rates = $this->installment_rates($result);
                if ($rates) {
                    $max_installment = $this->config["settings"]["max_installment"] ?? 0;
                    for ($i = 2; $i <= $max_installment; $i++) {
                        if (isset($rates[$i]) && strlen($rates[$i]) > 0) {
                            $t_rate = $rates[$i];
                            $total_payable_t = (($total_payable * $t_rate) / 100) + $total_payable;
                            $monthly_payable = ($total_payable_t / $i);

                            $result['installments'][] = [
                                'quantity'     => $i,
                                'fee'          => Money::formatter_symbol($monthly_payable, $cid),
                                'fee_uf'       => Money::formatter($monthly_payable, $cid),
                                'total_fee'    => Money::formatter_symbol($total_payable_t, $cid),
                                'total_fee_uf' => Money::formatter($total_payable_t, $cid),
                                'rate'         => round((float) $t_rate, 2),
                            ];

                        }
                    }
                }
            }


            if ($result["bank_name"] == '' || $result['card_type'] == '') {

                $result = [
                    'status'  => "error",
                    'message' => Bootstrap::$lang->get_cm("website/payment/card-tx24"),
                ];
            }
            echo Utility::jencode($result);
        }


        public function pre_capture()
        {
            $identification = (int)Filter::init("POST/identification", "numbers");
            $chid = (int)Filter::init("REQUEST/chid", "numbers");
            $number = Filter::init("REQUEST/card_num", "numbers");
            $hold_ne = Filter::init("REQUEST/card_name", "hclear");
            $hold_ne = Utility::substr($hold_ne, 0, 255);
            $expiry = Filter::init("REQUEST/card_expiry", "nunbers", "\/");
            $cvc = Filter::init("REQUEST/card_cvc", "numbers");
            $expiry = explode("/", $expiry);
            $cvc = substr($cvc, 0, 4);
            $checkout = $chid && !$identification ? Basket::get_checkout($chid) : false;
            $expiry_m = substr($expiry[0] ?? '99', 0, 2);
            $expiry_y = substr($expiry[1] ?? '99', 0, 2);
            $installment = (int)Filter::init("POST/installment", "numbers");
            $save_card = (int)Filter::init("POST/save_card", "numbers");
            $auto_card = (int)Filter::init("POST/auto_card", "numbers");
            $stored_card = (int)Filter::init("POST/stored_card", "numbers");


            if (method_exists($this, 'capture')) {

                if (!$stored_card) {
                    if (strlen($number) > 20 || strlen($number) < 16) {
                        echo Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/payment/card-tx24"),
                        ]);
                        return false;
                    }

                    if (strlen($number) < 5 || strlen($number) > 200) {
                        echo Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/payment/card-tx25"),
                        ]);
                        return false;
                    }

                    if (strlen($expiry_m) < 2 || strlen($expiry_y) < 2 || $expiry_m == 99 || $expiry_y == 99) {
                        echo Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/payment/card-tx26"),
                        ]);
                        return false;
                    }

                    if (strlen($cvc) < 3 || strlen($cvc) > 4) {
                        echo Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/payment/card-tx27"),
                        ]);
                        return false;
                    }

                    $bin = false;

                    if (method_exists($this, 'bin_check'))
                        $bin = $this->bin_check($number);
                    else
                        $this->error = "INTERNATIONAL";

                    if (!$bin && $this->error == "INTERNATIONAL")
                        $bin = $this->bin_check_international($number);

                    if (!$bin) {
                        echo Utility::jencode([
                            'status'  => "error",
                            'message' => $this->error,
                        ]);
                        return false;
                    }
                    $bin_result = $bin;
                }

                if ($identification) {
                    $installment = 0;
                    $save_card = 1;
                    $auto_card = 0;
                    $chid = $this->generate_card_identification_checkout($this->name);
                    if (!$chid && $this->error == "no-address") {
                        echo Utility::jencode([
                            'status'  => "error",
                            'message' => "Not found billing address",
                        ]);
                        return false;
                    }
                    $checkout = Basket::get_checkout($chid);
                }

                if (!$checkout) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => 'Checkout data not found',
                    ]);
                    return false;
                }


                $this->set_checkout($checkout);

                $checkout_data = $checkout["data"];
                $user_data = $checkout_data["user_data"];

                if ($stored_card) {
                    $bin_result = [];

                    // Get stored card data
                    $g_stored = $this->get_stored_card($stored_card, $user_data["id"]);
                    if (!$g_stored) {
                        echo Utility::jencode([
                            'status'  => "error",
                            'message' => "Invalid Stored Card",
                        ]);
                        return false;
                    }

                    $bin_result["country"] = $g_stored["card_country"];
                    $bin_result["type"] = $g_stored["card_type"];
                    $bin_result["schema"] = $g_stored["card_schema"];
                    $bin_result["brand"] = $g_stored["card_brand"];
                }

                $payment_amount = $checkout_data["total"];
                $payment_currency = $checkout_data["currency"];
                $force_curr = $this->config["settings"]["force_convert_to"] ?? 0;

                if ($force_curr > 0 && $payment_currency != $force_curr) {
                    $payment_amount = Money::exChange($payment_amount, $payment_currency, $force_curr);
                    $payment_currency = $force_curr;
                }

                $payment_currency = $this->currency($payment_currency);


                if (method_exists($this, 'installment_rates')) {
                    $installment_rates = $this->installment_rates($bin_result);
                    if ($installment_rates && $installment > 1) {
                        if (isset($installment_rates[$installment])) {
                            $t_rate = $installment_rates[$installment];
                            $payment_amount = round((($payment_amount * $t_rate) / 100) + $payment_amount, 2);
                        }
                    }
                }

                if ($stored_card) {
                    $checkout_data["pmethod_stored_card"] = $stored_card;
                } else {
                    $checkout_data["pmethod_card_country"] = $bin_result["country"];
                    $checkout_data["pmethod_card_type"] = $bin_result["card_type"];
                    $checkout_data["pmethod_card_schema"] = $bin_result["schema"];
                    $checkout_data["pmethod_bank_name"] = $bin_result["bank_name"];
                    $checkout_data["pmethod_card_brand"] = $bin_result["brand"];
                    $checkout_data["pmethod_card_ln4"] = substr($number, -4);
                    $checkout_data["pmethod_card_cvc"] = $cvc;
                    $checkout_data["pmethod_name"] = $hold_ne;
                    $checkout_data["pmethod_expiry_month"] = $expiry_m;
                    $checkout_data["pmethod_expiry_year"] = $expiry_y;

                    $checkout_data['pmethod_store_new_card'] = $save_card;
                }

                $checkout_data['pmethod_auto_pay'] = $auto_card;

                $checkout_data['pmethod_installment'] = $installment;

                Basket::set_checkout($chid, ['data' => Utility::jencode($checkout_data)]);

                $this->checkout["data"] = $checkout_data;

                if ($stored_card && isset($g_stored)) {
                    $capture_data = [
                        'card_storage' => $g_stored,
                        'amount'       => $payment_amount,
                        'currency'     => $payment_currency,
                        'checkout_id'  => $this->checkout_id,
                        'clientInfo'   => $this->clientInfo,
                    ];
                } else {
                    $capture_data = [
                        'installment'  => $installment,
                        'card_storage' => [],
                        'type'         => $bin_result['schema'] ? $bin_result['schema'] : strtoupper($bin_result['card_type']),
                        'holder_name'  => $hold_ne,
                        'num'          => $number,
                        'expiry_m'     => $expiry_m,
                        'expiry_y'     => $expiry_y,
                        'cvc'          => $cvc,
                        'amount'       => $payment_amount,
                        'currency'     => $payment_currency,
                        'save_card'    => (bool)$save_card,
                        'auto_pay'     => (bool)$auto_card,
                        'checkout_id'  => $this->checkout_id,
                        'clientInfo'   => $this->clientInfo,
                    ];
                }

                $capture = $this->capture($capture_data);

                if (!$capture) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => $this->error ? $this->error : 'Capture Failed',
                    ]);
                    return false;
                }

                $message = null;
                $output = null;
                $redirect = null;


                if (!isset($capture['status'])) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => 'Capture status not found',
                    ]);
                    return false;
                }

                if ($capture['status'] == 'successful')
                    $status = 'SUCCESS';
                elseif ($capture['status'] == 'output')
                    $status = 'OUTPUT';
                elseif ($capture['status'] == 'error') {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => $capture['message'] ?? 'Payment failed',
                    ]);
                    return false;
                } elseif ($capture['status'] == 'pending')
                    $status = 'PAPPROVAL';
                elseif (strtoupper($capture['status']) == '3D' || strtoupper($capture['status']) == 'REDIRECT')
                    $status = "REDIRECT";
                else {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => 'Capture status unknown : ' . $capture['status'],
                    ]);
                    return false;
                }

                if ($status != 'REDIRECT' && $status != 'OUTPUT') $capture = $this->payment_finish($capture);


                if (isset($capture['message']) && $capture['message']) $message = $capture['message'];
                if (isset($capture['output']) && $capture['output']) $output = $capture['output'];
                if (isset($capture['redirect']) && $capture['redirect']) $redirect = $capture['redirect'];

                if ($status == "REDIRECT") {
                    $process = [
                        'status'   => 'REDIRECT',
                        'output'   => $output,
                        'redirect' => $redirect,
                    ];
                } elseif ($status == "OUTPUT") {
                    $process = [
                        'status' => 'OUTPUT',
                        'output' => $output,
                    ];
                } else {
                    $process = self::processed($this, [
                        'status'     => $status,
                        'status_msg' => $message,
                        'checkout'   => $this->checkout,
                    ]);
                    $process['status'] = 'successful';
                    if ($redirect) $process['redirect'] = $redirect;
                    if ($output) $process['output'] = $output;
                }

                if (array_key_exists('redirect', $process) && !$process['redirect']) unset($process['redirect']);
                if (array_key_exists('output', $process) && !$process['output']) unset($process['output']);

                echo Utility::jencode($process);
            }

        }


        public function pre_area()
        {
            $checkout_data = $this->checkout["data"];

            $payment_amount = $checkout_data["total"];
            $payment_currency = $checkout_data["currency"];

            $force_curr = $this->config["settings"]["force_convert_to"] ?? 0;

            if ($force_curr > 0 && $payment_currency != $force_curr) {
                $payment_amount = Money::exChange($payment_amount, $payment_currency, $force_curr);
                $payment_currency = $force_curr;
            }


            $params = [
                'amount'   => $payment_amount,
                'currency' => $this->currency($payment_currency),
            ];
            return $this->area($params);
        }


        public static function processed($module, $result = [])
        {
            $return_data = [];

            $status = "none";
            $status_msg = "";
            $checkout = false;
            $method = $module ? $module->name : '';

            if (!$result) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "No response payment method",
                ]);
                return false;
            }


            $status = $result["status"] ?? $status;
            $status_msg = $result["status_msg"] ?? ($result["message"] ?? $status_msg);
            $checkout = $result["checkout"] ?? $checkout;
            $return_msg = $result["return_msg"] ?? ($result['callback_message'] ?? null);
            $subscribed = $result["subscribed"] ?? false;
            $paid = $result["paid"] ?? [];
            $change_total = false;
            $force_covert_currency = $module->config["settings"]["force_convert_to"] ?? 0;
            if ($force_covert_currency && ((int)$force_covert_currency) == ((int)$checkout["data"]["currency"] ?? 0))
                $force_covert_currency = 0;


            if ($paid && !$force_covert_currency) {
                $paid_amount = Money::exChange($paid["amount"], $paid["currency"], $checkout["data"]["currency"]);
                $def_amount = $checkout["data"]["total"];
                $diff_amount = round($paid_amount - $def_amount, 2);

                if ($diff_amount > 0.00 && isset($checkout["data"]["pmethod_commission"])) {
                    $tax_diff_amount = 0;

                    if ($checkout["data"]["tax"] > 0.00) {
                        $tax_diff_amount = Money::get_inclusive_tax_amount($diff_amount, $checkout["data"]["taxrate"]);
                        $checkout["data"]["tax"] += $tax_diff_amount;
                    }

                    $checkout["data"]["pmethod_commission"] += ($diff_amount - $tax_diff_amount);
                    $checkout["data"]["subtotal"] += ($diff_amount - $tax_diff_amount);

                    $checkout["data"]["total"] += $diff_amount;
                    $change_total = true;
                }
            }

            if (is_array($status_msg)) $status_msg = Utility::jencode($status_msg);

            if ($status == 'successful' || $status == 'success')
                $status = 'SUCCESS';
            elseif ($status == 'pending')
                $status = 'PAPPROVAL';


            $last_paid_page = Session::get("last_paid_page", true);
            $last_paid_page = $last_paid_page ? Utility::jdecode($last_paid_page, true) : [];

            if ($checkout) {
                if (isset($checkout["data"]["redirect"]) && $checkout["data"]["redirect"])
                    $last_paid_page = $checkout["data"]["redirect"];

                if ($status == "PAPPROVAL" || $status == "SUCCESS") {
                    if ($subscribed) {
                        $subscribed_n = [];
                        $subscribable = $checkout["data"]["subscribable"];
                        $subscribable_keys = [];
                        if ($subscribable)
                            foreach ($subscribable as $s)
                                $subscribable_keys[$s["identifier"]] = $s;

                        foreach ($subscribed as $identifier => $sub_identifier) {
                            $f_sub = Orders::get_subscription(0, $sub_identifier, $method);
                            if ($f_sub) {
                                $sb_items = Utility::jdecode($f_sub["items"], true);
                                $n_p_fee = $f_sub["next_payable_fee"];
                                $s_i_c = $subscribable_keys[$identifier] ?? [];

                                if ($s_i_c) {
                                    $sb_items[] = $s_i_c;
                                    $n_p_fee += Money::exChange($s_i_c["tax_included"], $s_i_c["currency"], $checkout["data"]["currency"]);
                                    $subscribed_n[$identifier] = $f_sub["id"];

                                    Orders::set_subscription($f_sub["id"], [
                                        'items'            => Utility::jencode($sb_items),
                                        'next_payable_fee' => $n_p_fee,
                                    ]);
                                }

                            } else {
                                $sb_items = [];
                                $s_i_c = $subscribable_keys[$identifier] ?? [];
                                $n_p_fee = Money::exChange($s_i_c["tax_included"], $s_i_c["currency"], $checkout["data"]["currency"]);
                                $sb_items[] = $s_i_c;

                                $create_subscription = Models::$init->db->insert("users_products_subscriptions", [
                                    'user_id'           => $checkout["user_id"],
                                    'module'            => $method,
                                    'items'             => Utility::jencode($sb_items),
                                    'status'            => 'active',
                                    'identifier'        => $sub_identifier,
                                    'currency'          => $checkout["data"]["currency"],
                                    'first_paid_fee'    => $checkout["data"]["total"],
                                    'last_paid_fee'     => $checkout["data"]["total"],
                                    'next_payable_fee'  => $n_p_fee,
                                    'last_paid_date'    => DateManager::Now(),
                                    'next_payable_date' => DateManager::next_date([$s_i_c["period"] => $s_i_c["period_time"]]),
                                    'created_at'        => DateManager::Now(),
                                    'updated_at'        => DateManager::Now(),
                                ]);
                                $sub_id = $create_subscription ? Models::$init->db->lastID() : 0;
                                $subscribed_n[$identifier] = $sub_id;
                            }
                        }
                        if ($subscribed_n) {
                            $checkout["data"]["subscribed"] = $subscribed_n;
                            Basket::set_checkout($checkout["id"], ['data' => Utility::jencode($checkout["data"])]);
                        }
                    }
                    if (isset($checkout["data"]["pmethod_store_new_card"]) && $checkout["data"]["pmethod_store_new_card"]) {


                        $card_country = '';
                        $card_type = '';
                        $card_schema = '';
                        $card_brand = '';
                        $bank_name = '';
                        $ln4 = '';
                        $name = '';
                        $expiry_m = '';
                        $expiry_y = '';
                        $m_token = '';
                        $cvc = '';

                        if (isset($checkout["data"]["pmethod_card_country"]))
                            $card_country = $checkout["data"]["pmethod_card_country"];

                        if (isset($checkout["data"]["pmethod_card_type"]))
                            $card_type = $checkout["data"]["pmethod_card_type"];

                        if (isset($checkout["data"]["pmethod_card_schema"]))
                            $card_schema = $checkout["data"]["pmethod_card_schema"];

                        if (isset($checkout["data"]["pmethod_card_brand"]))
                            $card_brand = $checkout["data"]["pmethod_card_brand"];

                        if (isset($checkout["data"]["pmethod_bank_name"]))
                            $bank_name = $checkout["data"]["pmethod_bank_name"];

                        if (isset($checkout["data"]["pmethod_card_ln4"]))
                            $ln4 = $checkout["data"]["pmethod_card_ln4"];

                        if (isset($checkout["data"]["pmethod_name"]))
                            $name = $checkout["data"]["pmethod_name"];

                        if (isset($checkout["data"]["pmethod_expiry_month"]))
                            $expiry_m = $checkout["data"]["pmethod_expiry_month"];

                        if (isset($checkout["data"]["pmethod_card_cvc"]))
                            $cvc = $checkout["data"]["pmethod_card_cvc"];

                        if (isset($checkout["data"]["pmethod_expiry_year"]))
                            $expiry_y = $checkout["data"]["pmethod_expiry_year"];

                        if (isset($checkout["data"]["pmethod_token"]))
                            $m_token = $checkout["data"]["pmethod_token"];

                        if (is_array($m_token) || is_object($m_token))
                            $m_token = Utility::jencode($m_token);

                        if ($m_token) $m_token = Crypt::encode($m_token, Config::get("crypt/user") . "**STORED_CARD**");
                        if ($cvc) $cvc = Crypt::encode($cvc, Config::get("crypt/user") . "**STORED_CARD**");


                        $as_default = Models::$init->db->update("users_stored_cards", ['as_default' => 0]);
                        $as_default->where("user_id", "=", $checkout["data"]["user_data"]["id"]);
                        $as_default = $as_default->save();

                        Models::$init->db->insert("users_stored_cards", [
                            'user_id'      => $checkout["data"]["user_data"]["id"],
                            'card_country' => $card_country,
                            'card_type'    => $card_type,
                            'card_schema'  => $card_schema,
                            'card_brand'   => $card_brand,
                            'bank_name'    => $bank_name,
                            'ln4'          => $ln4,
                            'cvc'          => $cvc,
                            'name'         => $name,
                            'expiry_month' => $expiry_m,
                            'expiry_year'  => $expiry_y,
                            'module'       => $method,
                            'token'        => $m_token,
                            'as_default'   => 1,
                        ]);
                        $stored_card = Models::$init->db->lastID();
                        $checkout["data"]["pmethod_stored_card"] = $stored_card;
                    }
                    if ($change_total) Basket::set_checkout($checkout["id"], ['data' => Utility::jencode($checkout["data"])]);

                    if ($checkout["type"] == "basket") Invoices::process($checkout, $status, $status_msg);
                    elseif ($checkout["type"] == "bill") Invoices::paid($checkout, $status, $status_msg, true);
                    elseif ($checkout["type"] == "invoice-bulk-payment") Invoices::bulk_paid($checkout, $status, $status_msg, true);
                } else {
                    if ($checkout["type"] == "bill") {
                        Events::create([
                            'user_id' => $checkout["user_id"],
                            'type'    => 'error',
                            'owner'   => "payment",
                            'name'    => "bill-payment-error",
                            'data'    => [
                                'get'        => Filter::GET(),
                                'post'       => Filter::POST(),
                                'payment'    => $method,
                                'checkout'   => $checkout,
                                'invoice_id' => $checkout["data"]["invoice_id"],
                                'message'    => $status_msg,
                                'user_name'  => $checkout["data"]["user_data"]["full_name"],
                            ],
                        ]);
                    } elseif ($checkout["type"] == "invoice-bulk-payment") {
                        Events::create([
                            'user_id' => $checkout["user_id"],
                            'type'    => 'error',
                            'owner'   => "payment",
                            'name'    => "bill-payment-error",
                            'data'    => [
                                'get'       => Filter::GET(),
                                'post'      => Filter::POST(),
                                'payment'   => $method,
                                'checkout'  => $checkout,
                                'invoices'  => $checkout["data"]["invoices"],
                                'message'   => $status_msg,
                                'user_name' => $checkout["data"]["user_data"]["full_name"],
                            ],
                        ]);
                    } elseif ($checkout["type"] == "basket") {
                        Events::create([
                            'user_id' => $checkout["user_id"],
                            'type'    => 'error',
                            'owner'   => "payment",
                            'name'    => "cart-payment-error",
                            'data'    => [
                                'get'       => Filter::GET(),
                                'post'      => Filter::POST(),
                                'payment'   => $method,
                                'checkout'  => $checkout,
                                'user_name' => $checkout["data"]["user_data"]["full_name"],
                                'message'   => $status_msg,
                            ],
                        ]);
                    }
                }
            }

            if ($return_msg != null) $return_data['message'] = $return_msg;
            else {
                if ($last_paid_page) {
                    if ($status == "PAPPROVAL" || $status == "SUCCESS")
                        $return_data['redirect'] = $last_paid_page["success"];
                    else
                        $return_data['redirect'] = $last_paid_page["failed"];
                }
            }

            return $return_data;
        }


        public function stored_cards($checkout = [], $capture = false)
        {
            $user_data = UserManager::LoginData();
            if (!$user_data && !$capture) return [];
            $user_id = $capture ? $checkout["data"]["user_data"]["id"] : $user_data["id"];

            if ($user_data && $user_id && $user_data["id"] != $user_id) return [];

            $stmt = Models::$init->db->select("id")->from("users_stored_cards");
            $stmt->where("module", "=", $this->name, "&&");
            $stmt->where("user_id", "=", $user_id);
            $stmt = $stmt->build() ? $stmt->fetch_assoc() : [];
            $returnData = [];
            if ($stmt) foreach ($stmt as $row) $returnData[] = $this->get_stored_card($row["id"], $user_id);
            return $returnData;
        }


        protected function payment_finish($result)
        {
            return self::payment_finish_alt($result, $this);
        }


        public static function payment_finish_alt($result, $class)
        {
            if ($class->checkout && !isset($result['checkout'])) $result['checkout'] = $class->checkout;
            if ($result && isset($result['checkout']) && $result['checkout']) {
                $checkout = $result['checkout'];
                $c_s_token = $result['card_storage_token'] ?? '';
                $subscribed = $checkout["data"]["subscribed"] ?? [];

                if ($subscribed && !isset($result['subscribed'])) $result['subscribed'] = $subscribed;

                if ($c_s_token) {
                    if (isset($checkout["data"]["pmethod_store_new_card"]) && $checkout["data"]["pmethod_store_new_card"]) {
                        $checkout["data"]["pmethod_token"] = $c_s_token;
                        $result["checkout"]["data"]["pmethod_token"] = $c_s_token;
                    }

                    if ($checkout["data"]["type"] == "card-identification") {
                        if (!class_exists("Events")) Helper::Load(["Events"]);
                        Events::add_scheduled_operation([
                            'owner'    => "Refund",
                            'owner_id' => 0,
                            'name'     => "refund-on-payment-module",
                            'period'   => 'minute',
                            'time'     => 3,
                            'module'   => __CLASS__,
                            'needs'    => ['checkout_id' => $checkout["id"]],
                        ]);
                    }
                }

                if ($checkout["status"] && isset($checkout["status"]) && $checkout["status"] == "paid")
                    return [
                        'status'     => "error",
                        'return_msg' => "Already paid",
                    ];

                if ($result['status'] == 'successful' || $result['status'] == 'pending') {
                    $class->checkout["data"] = $checkout["data"];
                    Basket::set_checkout($checkout["id"], ['status' => "paid", 'data' => Utility::jencode($checkout["data"])]);
                }

            }
            return $result;
        }


        public function get_checkout($id = 0, $status = '', $type = '', $uid = 0)
        {
            $checkout = Basket::get_checkout($id, $uid, $type, $status);
            if (!$this->checkout) $this->checkout = $checkout;
            return $checkout;
        }


        public function get_installment_count()
        {
            return $this->checkout["data"]["pmethod_installment"] ?? 0;
        }


        public function checkSaveCard()
        {
            return (bool)$this->checkout["data"]["pmethod_store_new_card"] ?? false;
        }


        public function checkAutoPay()
        {
            return (bool)$this->checkout["data"]["pmethod_auto_pay"] ?? false;
        }


        public function pre_remove_stored_card($id = 0)
        {
            return $this->remove_stored_card($this->get_stored_card($id));
        }


        public function subscribable_items()
        {
            return $this->checkout["data"]["subscribable"] ?? [];
        }


        public function set_subscribed_items($arg = [])
        {
            $this->checkout["data"]["subscribed"] = $arg;
            Basket::set_checkout($this->checkout_id, ['data' => Utility::jencode($this->checkout["data"])]);
        }


        public function setRoute($arg1 = '', $arg2 = '')
        {
            $this->links[$arg1] = Controllers::$init->CRLink("payment", [$this->name, "function", $arg1]);
            $this->call_function[$arg1] = $arg2;
        }


        public function define_function($name = '', $funcion_name = '')
        {
            if (!$funcion_name) $funcion_name = $name;
            $this->links[$name] = Controllers::$init->CRLink("payment", [$this->name, 'function', $name]);
            $this->call_function[$name] = $funcion_name;
        }


        public function save_checkout($id = 0, $fields = [])
        {
            if (isset($fields['items']) && is_array($fields['items']))
                $fields['items'] = Utility::jencode($fields['items']);

            if (isset($fields['data']) && is_array($fields['data']))
                $fields['data'] = Utility::jencode($fields['data']);

            return Basket::set_checkout($id, $fields);
        }


        public function save_custom_data($data, $checkout_id = 0)
        {
            if (!$this->checkout) $this->get_checkout($checkout_id);
            $this->checkout["data"]["pmethod_custom_data"] = $data;
            $this->save_checkout($this->checkout_id, ['data' => $this->checkout["data"]]);
        }


        public function get_custom_data($checkout_id = 0)
        {
            if (!$this->checkout) $this->get_checkout($checkout_id);
            return $this->checkout["data"]["pmethod_custom_data"] ?? false;
        }


        public function page_request_info()
        {
            $url = Controllers::$init->getData("links")["controller"];

            $data = [];

            if ($this->checkout['type'] == 'bill') {
                $data["operation"] = "payment-screen";
                if (strlen(Filter::REQUEST("sendbta")) > 0) $data["sendbta"] = Filter::REQUEST("sendbta");
                if (strlen(Filter::REQUEST("pmethod")) > 0) $data["pmethod"] = Filter::REQUEST("pmethod");
            } elseif ($this->checkout['type'] == 'invoice-bulk-payment') {
                $data["operation"] = "payment-screen";
                if (strlen(Filter::REQUEST("pmethod")) > 0) $data["pmethod"] = Filter::REQUEST("pmethod");
                if (strlen(Filter::REQUEST("invoices")) > 0) $data["invoices"] = Filter::REQUEST("invoices");
            }

            return [
                'address' => $url,
                'data'    => $data,
            ];
        }


        public function callback_processed($result = [])
        {
            $result = $this->payment_finish($result);

            return self::processed($this, $result);
        }


        public function getItems()
        {
            return $this->checkout["items"] ?? [];
        }


        static function processed_by_callback($result, $class)
        {
            $result = self::payment_finish_alt($result, $class);
            return self::processed($class, $result);
        }


    }

    class RegistrarModule
    {
        public $config = [];
        public $lang = [];
        public $whidden = [];
        public $docs = [];
        public $order = [];
        public $product = [];
        public $user = [];
        public $admin = [];
        public $dir = null;
        public $url = null;
        public $name = null;
        public $_name = null;
        public $error = null;
        public $api = null;
        public $val_of_conf_opt, $val_of_requirements, $requirements, $addons, $id_of_conf_opt = [];


        public function __construct($name)
        {
            $this->name = $name;
            $this->_name = $name;
            $this->config = Modules::Config("Registrars", $this->_name);
            $this->lang = Modules::Lang("Registrars", $this->_name);

            $this->dir = MODULE_DIR . "Registrars" . DS . $this->_name . DS;
            $this->url .= CORE_FOLDER . DS . MODULES_FOLDER . DS . "Registrars" . DS . $this->_name . DS;
            $this->url = Utility::link_determiner($this->url, false, false);


            if (isset($this->config["settings"]["whidden-amount"])) {
                $whidden_amount = $this->config["settings"]["whidden-amount"];
                $whidden_currency = $this->config["settings"]["whidden-currency"];
                $this->whidden["amount"] = $whidden_amount;
                $this->whidden["currency"] = $whidden_currency;
            }


        }


        static function get_doc_lang($param, $lang = '')
        {
            if (is_array($param) && $param) {
                $keys = array_keys($param);
                $default = $keys[0];
                if (!$lang)
                    $lang = Modules::$lang;
                if (!$lang) $lang = Bootstrap::$lang->clang;


                return $param[$lang] ?? $param[$default];
            } else
                return $param;
        }


        public function set_order($order = [])
        {
            $this->order = $order;
            Helper::Load(["Products", "User", "Orders"]);
            $this->product = Products::get($order["type"], $order["product_id"]);
            $this->user = User::getData($order["owner_id"], "id,name,surname,company_name,full_name,email,phone,lang,country", "array");
            $this->user = array_merge($this->user, User::getInfo($order["owner_id"], ["gsm_cc", "gsm_number"]));
            $this->user["address"] = AddressManager::getAddress(false, $order["owner_id"]);

            $configurable_options = [];
            if ($addons = Orders::addons($this->order["id"])) {
                $lang = $this->user["lang"];
                foreach ($addons as $addon) {
                    if ($gAddon = Products::addon($addon["addon_id"], $lang)) {
                        $addon["attributes"] = $gAddon;
                        $this->addons[$addon["id"]] = $addon;
                        if ($gAddon["options"]) {
                            if ($gAddon["type"] == "quantity" || $addon["option_quantity"] > 0) {
                                if ($addon["option_quantity"] > 0)
                                    $addon_v = (int)$addon["option_quantity"];
                                else {
                                    $addon_v = $addon["option_name"];
                                    $addon_v = explode("x", $addon_v);
                                    $addon_v = (int)trim($addon_v[0]);
                                }
                            } else
                                $addon_v = '';
                            foreach ($gAddon["options"] as $option) {
                                if ($option["id"] == $addon["option_id"]) {
                                    if (isset($option["module"]) && $option["module"]) {
                                        if (isset($option["module"][$this->_name])) {
                                            $c_options = $option["module"][$this->_name]["configurable"];
                                            foreach ($c_options as $k => $v) {
                                                $d_v = $v;
                                                if(strlen($addon_v) > 0) $d_v = ((int) $d_v) * ((int) $addon_v);

                                                if (!in_array($addon['status'], ["cancelled", "waiting"])) {
                                                    if (isset($configurable_options[$k]) && strlen($addon_v) > 0)
                                                        $configurable_options[$k] += $d_v;
                                                    else
                                                        $configurable_options[$k] = $d_v;
                                                }

                                                $this->id_of_conf_opt[$addon["id"]][$k] = $d_v;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $this->val_of_conf_opt = $configurable_options;

            $values_of_requirements = [];
            if ($requirements = Orders::requirements($this->order["id"])) {
                $this->requirements = $requirements;
                foreach ($requirements as $req) {
                    if ($req["module_co_names"]) {
                        $req["module_co_names"] = Utility::jdecode($req["module_co_names"], true);
                        if (isset($req["module_co_names"][$this->_name])) {
                            $c_o_name = $req["module_co_names"][$this->_name];
                            if (in_array($req["response_type"], ['input', 'password', 'textarea', 'file']))
                                $response = $req["response"];
                            else {
                                $mkey = $req["response_mkey"];
                                if ($dc = Utility::jdecode($mkey, true)) $mkey = $dc;
                                $response = is_array($mkey) && sizeof($mkey) < 2 ? current($mkey) : $mkey;
                            }
                            $values_of_requirements[$c_o_name] = $response;
                        }
                    }
                }
            }
            $this->val_of_requirements = $values_of_requirements;
        }

        public function define_docs($docs = [])
        {
            $this->docs = $docs;
        }

        protected function encode_str($str = '', $key = 'system')
        {
            return Crypt::encode($str, Config::get("crypt/" . $key));
        }

        protected function decode_str($str = '', $key = 'system')
        {
            return Crypt::decode($str, Config::get("crypt/" . $key));
        }

        public function questioning($sld = null, $tlds = [])
        {
            if ($sld == '' || empty($tlds)) {
                $this->error = $this->lang["error2"];
                return false;
            }
            $sld = idn_to_ascii($sld, 0, INTL_IDNA_VARIANT_UTS46);
            if (!is_array($tlds)) $tlds = [$tlds];

            $servers = Registrar::whois_server($tlds);

            $result = [];
            foreach ($tlds as $t) {
                if (isset($servers[$t]["host"]) && isset($servers[$t]["available_pattern"]))
                    $questioning = Registrar::questioning($sld, $t, $servers[$t]["host"], 43, $servers[$t]["available_pattern"]);
                else
                    $questioning = false;

                $result[$t] = ['status' => $questioning['status']];

            }
            return $result;
        }

        public function import_domain($data = [])
        {
            $config = $this->config;

            $imports = [];

            Helper::Load(["Orders", "Products", "Money"]);

            foreach ($data as $domain => $datum) {
                $domain_parse = Utility::domain_parser("http://" . $domain);
                $sld = $domain_parse["host"];
                $tld = $domain_parse["tld"];
                $user_id = (int)$datum["user_id"];
                if (!$user_id) continue;
                $info = $this->get_info([
                    'domain' => $domain,
                    'name'   => $sld,
                    'tld'    => $tld,
                ]);
                if (!$info) continue;

                $user_data = User::getData($user_id, "id,lang", "array");
                $ulang = $user_data["lang"];
                $locallang = Config::get("general/local");
                $productID = Models::$init->db->select("id")->from("tldlist")->where("name", "=", $tld);
                $productID = $productID->build() ? $productID->getObject()->id : false;
                if (!$productID) continue;
                $productPrice = Products::get_price("register", "tld", $productID);
                $productPrice_amt = $productPrice["amount"];
                $productPrice_cid = $productPrice["cid"];
                $start_date = $info["creation_time"];
                $end_date = $info["end_time"];
                $year = 1;

                $options = [
                    "established"      => true,
                    "group_name"       => Bootstrap::$lang->get_cm("website/account_products/product-type-names/domain", false, $ulang),
                    "local_group_name" => Bootstrap::$lang->get_cm("website/account_products/product-type-names/domain", false, $locallang),
                    "category_id"      => 0,
                    "domain"           => $domain,
                    "name"             => $sld,
                    "tld"              => $tld,
                    "dns_manage"       => true,
                    "whois_manage"     => true,
                    "transferlock"     => $info["transferlock"],
                    "cns_list"         => isset($info["cns"]) ? $info["cns"] : [],
                    "whois"            => isset($info["whois"]) ? $info["whois"] : [],
                ];

                if (isset($info["whois_privacy"]) && $info["whois_privacy"]) {
                    $options["whois_privacy"] = $info["whois_privacy"]["status"] == "enable";
                    $wprivacy_endtime = DateManager::ata();
                    if (isset($info["whois_privacy"]["end_time"]) && $info["whois_privacy"]["end_time"]) {
                        $wprivacy_endtime = $info["whois_privacy"]["end_time"];
                        $options["whois_privacy_endtime"] = $wprivacy_endtime;
                    }
                }

                if (isset($info["ns1"]) && $info["ns1"]) $options["ns1"] = $info["ns1"];
                if (isset($info["ns2"]) && $info["ns2"]) $options["ns2"] = $info["ns2"];
                if (isset($info["ns3"]) && $info["ns3"]) $options["ns3"] = $info["ns3"];
                if (isset($info["ns4"]) && $info["ns4"]) $options["ns4"] = $info["ns4"];


                $order_data = [
                    "owner_id"     => (int)$user_id,
                    "type"         => "domain",
                    "product_id"   => (int)$productID,
                    "name"         => $domain,
                    "period"       => "year",
                    "period_time"  => (int)$year,
                    "amount"       => (float)$productPrice_amt,
                    "total_amount" => (float)$productPrice_amt,
                    "amount_cid"   => (int)$productPrice_cid,
                    "status"       => "active",
                    "cdate"        => $start_date,
                    "duedate"      => $end_date,
                    "renewaldate"  => DateManager::Now(),
                    "module"       => $config["meta"]["name"],
                    "options"      => Utility::jencode($options),
                    "unread"       => 1,
                ];

                $insert = Orders::insert($order_data);
                if (!$insert) continue;

                if (isset($options["whois_privacy"])) {
                    $amount = Money::exChange($this->whidden["amount"], $this->whidden["currency"], $productPrice_cid);
                    $start = DateManager::Now();
                    $end = isset($wprivacy_endtime) ? $wprivacy_endtime : DateManager::ata();
                    Orders::insert_addon([
                        'invoice_id'  => 0,
                        'owner_id'    => $insert,
                        "addon_key"   => "whois-privacy",
                        'addon_id'    => 0,
                        'addon_name'  => Bootstrap::$lang->get_cm("website/account_products/whois-privacy", false, $ulang),
                        'option_id'   => 0,
                        "option_name" => Bootstrap::$lang->get("needs/iwwant", $ulang),
                        'period'      => 1,
                        'period_time' => "year",
                        'status'      => "active",
                        'cdate'       => $start,
                        'renewaldate' => $start,
                        'duedate'     => $end,
                        'amount'      => $amount,
                        'cid'         => $productPrice_cid,
                        'unread'      => 1,
                    ]);
                }
                $imports[] = $order_data["name"] . " (#" . $insert . ")";
            }

            if ($imports) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "domain-imported", [
                    'module'   => $config["meta"]["name"],
                    'imported' => implode(", ", $imports),
                ]);
            }

            return $imports;
        }

        public function apply_import_tlds()
        {

            $cost_cid = $this->config["settings"]["cost-currency"] ?? 4; // Currency ID

            if (method_exists($this, 'tlds'))
                $list = $this->tlds();
            else
                $list = $this->cost_prices();


            if (!$list) return false;

            Helper::Load(["Products", "Money"]);

            $profit_rate = Config::get("options/domain-profit-rate");

            foreach ($list as $name => $val) {

                if (isset($val["register"])) {
                    $api_cost_prices = [
                        'register' => $val["register"],
                        'transfer' => $val["transfer"],
                        'renewal'  => $val["renewal"],
                    ];
                } else {
                    $api_cost_prices = [
                        'register' => Money::exChange($val["price"]["register"]["amount"] ?? 0, $val["price"]["register"]["currency"] ?? '', $cost_cid),
                        'transfer' => Money::exChange($val["price"]["transfer"]["amount"] ?? 0, $val["price"]["transfer"]["currency"] ?? '', $cost_cid),
                        'renewal'  => Money::exChange($val["price"]["renewal"]["amount"] ?? 0, $val["price"]["renewal"]["currency"] ?? '', $cost_cid),
                    ];
                }

                $paperwork = 0;
                $epp_code = $val["epp_code"] ?? 1;
                $dns_manage = $val["dns_manage"] ?? 1;
                $whois_privacy = $val["whois_privacy"] ?? 1;
                $min_years = $val["min_years"] ?? 1;
                $max_years = $val["max_years"] ?? 10;
                $module = $this->name;

                $check = Models::$init->db->select()->from("tldlist")->where("name", "=", $name);

                if ($check->build()) {
                    $tld = $check->getAssoc();
                    $pid = $tld["id"];

                    $reg_price = Products::get_price("register", "tld", $pid);
                    $ren_price = Products::get_price("renewal", "tld", $pid);
                    $tra_price = Products::get_price("transfer", "tld", $pid);

                    $tld_cid = $reg_price["cid"];


                    $register_cost = Money::deformatter($api_cost_prices["register"]);
                    $renewal_cost = Money::deformatter($api_cost_prices["renewal"]);
                    $transfer_cost = Money::deformatter($api_cost_prices["transfer"]);

                    // ExChanges
                    $register_cost = Money::exChange($register_cost, $cost_cid, $tld_cid);
                    $renewal_cost = Money::exChange($renewal_cost, $cost_cid, $tld_cid);
                    $transfer_cost = Money::exChange($transfer_cost, $cost_cid, $tld_cid);


                    $reg_profit = Money::get_discount_amount($register_cost, $profit_rate);
                    $ren_profit = Money::get_discount_amount($renewal_cost, $profit_rate);
                    $tra_profit = Money::get_discount_amount($transfer_cost, $profit_rate);

                    $register_sale = $register_cost + $reg_profit;
                    $renewal_sale = $renewal_cost + $ren_profit;
                    $transfer_sale = $transfer_cost + $tra_profit;

                    Products::set("domain", $pid, [
                        'paperwork'     => $paperwork,
                        'epp_code'      => $epp_code,
                        'dns_manage'    => $dns_manage,
                        'whois_privacy' => $whois_privacy,
                        'register_cost' => $register_cost,
                        'renewal_cost'  => $renewal_cost,
                        'transfer_cost' => $transfer_cost,
                        'min_years'     => $min_years,
                        'max_years'     => $max_years,
                        'module'        => $module,
                    ]);

                    Models::$init->db->update("prices", [
                        'amount' => $register_sale,
                        'cid'    => $tld_cid,
                    ])->where("id", "=", $reg_price["id"])->save();


                    Models::$init->db->update("prices", [
                        'amount' => $renewal_sale,
                        'cid'    => $tld_cid,
                    ])->where("id", "=", $ren_price["id"])->save();


                    Models::$init->db->update("prices", [
                        'amount' => $transfer_sale,
                        'cid'    => $tld_cid,
                    ])->where("id", "=", $tra_price["id"])->save();

                } else {

                    $tld_cid = $cost_cid;

                    $register_cost = Money::deformatter($api_cost_prices["register"]);
                    $renewal_cost = Money::deformatter($api_cost_prices["renewal"]);
                    $transfer_cost = Money::deformatter($api_cost_prices["transfer"]);


                    $reg_profit = Money::get_discount_amount($register_cost, $profit_rate);
                    $ren_profit = Money::get_discount_amount($renewal_cost, $profit_rate);
                    $tra_profit = Money::get_discount_amount($transfer_cost, $profit_rate);

                    $register_sale = $register_cost + $reg_profit;
                    $renewal_sale = $renewal_cost + $ren_profit;
                    $transfer_sale = $transfer_cost + $tra_profit;

                    $insert = Models::$init->db->insert("tldlist", [
                        'status'        => "inactive",
                        'cdate'         => DateManager::Now(),
                        'name'          => $name,
                        'paperwork'     => $paperwork,
                        'epp_code'      => $epp_code,
                        'dns_manage'    => $dns_manage,
                        'whois_privacy' => $whois_privacy,
                        'currency'      => $tld_cid,
                        'register_cost' => $register_cost,
                        'renewal_cost'  => $renewal_cost,
                        'transfer_cost' => $transfer_cost,
                        'module'        => $module,
                    ]);

                    if ($insert) {
                        $tld_id = Models::$init->db->lastID();

                        Models::$init->db->insert("prices", [
                            'owner'    => "tld",
                            'owner_id' => $tld_id,
                            'type'     => 'register',
                            'amount'   => $register_sale,
                            'cid'      => $tld_cid,
                        ]);


                        Models::$init->db->insert("prices", [
                            'owner'    => "tld",
                            'owner_id' => $tld_id,
                            'type'     => 'renewal',
                            'amount'   => $renewal_sale,
                            'cid'      => $tld_cid,
                        ]);


                        Models::$init->db->insert("prices", [
                            'owner'    => "tld",
                            'owner_id' => $tld_id,
                            'type'     => 'transfer',
                            'amount'   => $transfer_sale,
                            'cid'      => $tld_cid,
                        ]);
                    }

                }
            }
            return true;
        }

        public function config_fields_output($data)
        {
            return Modules::fields_output($this->_name, $data, "fields");
        }

        public function controller_settings()
        {
            $fields = Filter::POST("fields");
            $sets = $this->config;
            $sets2 = [];

            if (method_exists($this, "config_fields_filter")) $fields = $this->config_fields_filter($fields);

            $config_fields = $this->config_fields($this->config["settings"] ?? []);

            if ($config_fields) {
                foreach ($config_fields as $k => $v) {
                    if (isset($fields[$k]))
                        $sets['settings'][$k] = $fields[$k];
                    else
                        $sets['settings'][$k] = false;
                }
            }

            Helper::Load("Money");

            $sets["settings"]["whidden-amount"] = Filter::amount($fields["whidden-amount"] ?? 0);
            $sets["settings"]["whidden-currency"] = Filter::numbers($fields["whidden-currency"] ?? 4);
            $sets["settings"]["whidden-amount"] = Money::deformatter($sets["settings"]["whidden-amount"], $sets["settings"]["whidden-currency"]);
            $sets["settings"]["adp"] = (bool)(Filter::rnumbers($fields["adp"] ?? 0));
            $sets["settings"]["cost-currency"] = (int)(Filter::rnumbers($fields["cost-currency"] ?? 4));


            if ($sets) {
                $config_result = array_replace_recursive($this->config, $sets);

                if ($sets2) $config_result = array_replace_recursive($config_result, $sets2);
                $array_export = Utility::array_export($config_result, ['pwith' => true]);

                $file = $this->dir . "config.php";

                FileManager::file_write($file, $array_export);

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-registrars-module-settings", [
                    'module' => $this->config["meta"]["name"] ?? $this->_name,
                    'name'   => $this->lang["name"] ?? $this->_name,
                ]);
            }

            echo Utility::jencode([
                'status'  => "successful",
                'message' => Bootstrap::$lang->get_cm("admin/financial/success1"),
            ]);

            return true;
        }

        public function controller_test_connection()
        {
            $fields = Filter::POST("fields");
            $sets = $this->config;
            $sets2 = [];

            if (method_exists($this, "config_fields_filter")) $fields = $this->config_fields_filter($fields);

            $config_fields = $this->config_fields($this->config["settings"] ?? []);

            if ($config_fields) {
                foreach ($config_fields as $k => $v) {
                    if (isset($fields[$k]))
                        $sets['settings'][$k] = $fields[$k];
                    else
                        $sets['settings'][$k] = false;
                }
            }

            $config_result = array_replace_recursive($this->config, $sets);
            if ($sets2) $config_result = array_replace_recursive($config_result, $sets2);

            if (!method_exists($this, 'testConnection')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => 'testConnection() method was not found in the class.',
                ]);
                return true;
            }

            if (!$this->testConnection($config_result)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $this->error,
                ]);
                return false;
            }

            echo Utility::jencode([
                'status'  => "successful",
                'message' => Bootstrap::$lang->get_cm("admin/products/success12"),
            ]);

            return true;
        }

        public function controller_import()
        {
            if (!method_exists($this, 'import_domain')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => 'import_domain() method was not found in the class.',
                ]);
                return true;
            }

            $data = Filter::POST("data");

            if (!$this->import_domain($data)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => Bootstrap::$lang->get_cm("admin/modules/failed-import"),
                ]);
                return true;
            }

            echo Utility::jencode([
                'status'  => "successful",
                'message' => Bootstrap::$lang->get_cm("admin/modules/successful-import"),
            ]);

            return true;
        }

        public function controller_import_tld()
        {
            if (!method_exists($this, 'apply_import_tlds')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => 'apply_import_tlds() method was not found in the class.',
                ]);
                return true;
            }

            $data = Filter::POST("data");

            if (!$this->apply_import_tlds()) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => Bootstrap::$lang->get_cm("admin/modules/failed-import") . ($this->error ?: ": " . $this->error),
                ]);
                return true;
            }

            echo Utility::jencode([
                'status'  => "successful",
                'message' => Bootstrap::$lang->get_cm("admin/modules/successful-import"),
            ]);

            return true;
        }


    }