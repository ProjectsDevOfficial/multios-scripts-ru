<?php
    defined('CORE_FOLDER') or exit('You can not get in here!');

    class Controller extends Controllers
    {
        public $params, $data = [];
        public $time_to_process = true;
        public $process_limit = 15;


        public function __construct($arg = [])
        {
            parent::__construct();
            $this->params = $arg['params'];

            @set_time_limit(0);

            if (!defined("CRON")) exit;

            Helper::Load(["User"]);
        }


        private function calculate_time($now = '', $start = '', $end = '')
        {
            $now = (float)str_replace(":", ".", $now);
            $start = (float)str_replace(":", ".", $start);
            $end = (float)str_replace(":", ".", $end);

            if ($end > $start) {
                if ($now >= $end) $time_has_come = false;
                elseif ($now >= $start) $time_has_come = true;
                else $time_has_come = false;
            } else {
                if ($now <= $end) $time_has_come = true;
                elseif ($now >= $start) $time_has_come = true;
                else $time_has_come = false;
            }
            return $time_has_come;
        }


        public function main()
        {
            if (DEMO_MODE) return false;
            Helper::Load("Events");

            header("Content-Type:text/plain");


            Helper::Load(["Invoices", "Products", "Money", "User"]);

            $installer = Filter::GET("install") && Config::get("cronjobs/last-run-time") == DateManager::zero();

            $backup = Events::isCreated('system', 'backup', 0, 'cronjobs', false, 0, true);
            if (!$backup) {
                $backup_id = Events::create([
                    'type'  => 'system',
                    'owner' => 'backup',
                    'name'  => 'cronjobs',
                    'data'  => '',
                ]);
                $backup = Events::get($backup_id);
            }
            if ($backup && $backup["data"])
                $backup["data"] = base64_decode($backup["data"]);

            $cronjob_file = FileManager::file_read(CONFIG_DIR . "cronjobs.php");
            if (!stristr($cronjob_file, '];') && $backup && $backup["data"]) {
                FileManager::file_write(CONFIG_DIR . "cronjobs.php", $backup["data"]);
                return false;
            }

            if (!Config::get("cronjobs") && $backup && $backup["data"]) {
                FileManager::file_write(CONFIG_DIR . "cronjobs.php", $backup["data"]);
                return false;
            }


            $time_to_process = Config::get("cronjobs/time-to-process");

            $tasks = Config::get("cronjobs/tasks");
            $sets = [];
            $now = DateManager::Now("H:i");


            $lockFileName   = ROOT_DIR.'temp'.DS.'cronjobs.lock';
            $maxRunTime     = 3600;
            $getPid         = function_exists('getmypid') ? getmypid() : 0;

            $saveLog        = fn($msg) => FileManager::file_write(ROOT_DIR.'temp'.DS.'cronjobs.log','['.DateManager::Now().']:' . $msg."\n","a");

            if (file_exists($lockFileName)) {
                $lockInfo   = json_decode(file_get_contents($lockFileName), true);
                $lockTime   = $lockInfo['time'] ?? 0;
                $lockPid    = $lockInfo['pid'] ?? 0;

                $currentTime = time();
                $timeDifference = $currentTime - $lockTime;
                $isTimeout  = ($lockTime > 0) && ($timeDifference > 1800);

                /*
                $isProcessDead = false;
                if (PHP_OS !== 'WINNT' && $lockPid > 0) {
                    $isProcessDead = !file_exists("/proc/".$lockPid);
                    if (!$isProcessDead && function_exists('posix_kill')) {
                        $isProcessDead = !posix_kill($lockPid, 0);
                    }
                }
                */

                if ($isTimeout || !$lockInfo) {
                    $error = "Cronjob lock file is ";
                    if($isTimeout)
                        $error .= "timeout ($timeDifference seconds old, limit: 1800).";
                    elseif(!$lockInfo)
                        $error .= "corrupted.";
                    /*elseif($isProcessDead)
                        $error .= "dead (PID: $lockPid).";*/

                    if(LOG_SAVE) $saveLog($error);
                    if(ERROR_DEBUG) echo $error . "\n";
                    if(file_exists($lockFileName)) @unlink($lockFileName);
                }
            }

            $lockFile = fopen($lockFileName, 'w');

            if (!$lockFile) {
                $error = "Can not create lock file: ".$lockFileName;
                if(LOG_SAVE) $saveLog($error);
                if(ERROR_DEBUG) echo $error;
                return false;
            }

            register_shutdown_function(function() use ($lockFile,$lockFileName,$saveLog) {
                if (is_resource($lockFile)) {
                    $err    = error_get_last();
                    $type   = $err['type'] ?? null;
                    if ($type !== null && ($type == E_ERROR || $type == E_CORE_ERROR || $type == E_COMPILE_ERROR))
                    {
                        $error = "Cronjob error: {$err['message']} in {$err['file']} on line {$err['line']} - cleaning up lock";
                        if(LOG_SAVE) $saveLog($error);
                        if(ERROR_DEBUG) echo "\n".$error;
                        flock($lockFile, LOCK_UN);
                        fclose($lockFile);
                        if(file_exists($lockFileName)) @unlink($lockFileName);
                    }
                }
            });

            if(is_resource($lockFile)) {
                if(!flock($lockFile, LOCK_EX | LOCK_NB)) {
                    $lockContent = @file_get_contents($lockFileName);
                    $lockData = json_decode($lockContent, true);
                    $lockTime = is_array($lockData) ? ($lockData['time'] ?? 0) : (int) $lockContent;

                    if ($lockTime && (time() - $lockTime > $maxRunTime)) {
                        if(file_exists($lockFileName)) @unlink($lockFileName);
                        $lockFile = fopen($lockFileName, 'w');
                        if (!$lockFile || !flock($lockFile, LOCK_EX | LOCK_NB)) {
                            $error = "Failed to acquire lock even after removing old lock file.";
                            if(LOG_SAVE) $saveLog($error);
                            if(ERROR_DEBUG) echo $error;
                            return false;
                        }
                        $lockData = json_encode([
                            'pid' => $getPid,
                            'time' => time()
                        ]);
                        file_put_contents($lockFileName, $lockData);
                    }
                    else {
                        if(LOG_SAVE) $saveLog("Cronjob is running.");
                        if(ERROR_DEBUG) echo "Cronjob is running.";
                        $lockFile = null;
                        return false;
                    }
                }
                else {
                    $lockData = json_encode([
                        'pid' => $getPid,
                        'time' => time()
                    ]);
                    file_put_contents($lockFileName, $lockData);
                }
            }

            if ($time_to_process["start"] && $time_to_process["end"])
                $time_has_come = $this->calculate_time($now, $time_to_process["start"], $time_to_process["end"]);
            else
                $time_has_come = true;

            $this->time_to_process = $time_has_come;


            if (!$time_has_come) foreach ($time_to_process["tasks"] as $task) unset($tasks[$task]);

            $l_i_c = Events::isCreated('system', 'cronjob', 0, 'license-ip-check', false, 0, true);

            $l_i_c_n = $l_i_c ? $l_i_c["data"]["next-run-time"] : false;
            $l_i_c_i = $l_i_c ? $l_i_c["data"]["ip"] : false;
            $l_i_c_i_n = $l_i_c ? $l_i_c["data"]["ip_ne"] : false;
            $l_i_c_m = $l_i_c ? $l_i_c["data"]["mismatch"] : false;

            if (!$l_i_c_n || DateManager::strtotime($l_i_c_n) < DateManager::strtotime()) {
                $license_data = License::info();
                $e_ip = isset($license_data["ip"]) ? $license_data["ip"] : '';
                $e_ip_ne = isset($license_data["ip_ne"]) ? $license_data["ip_ne"] : '';
                $e_mismatch = isset($license_data["ip_mismatch"]) ? $license_data["ip_mismatch"] : false;
                $l_i_c_i = $e_ip;
                $l_i_c_i_n = $e_ip_ne;
                $l_i_c_m = $e_mismatch;
                $l_i_c_n = DateManager::next_date(['day' => 1]);

                if ($l_i_c)
                    Events::set($l_i_c["id"], [
                        'cdate' => DateManager::Now(),
                        'data'  => [
                            'next-run-time' => $l_i_c_n,
                            'ip'            => $l_i_c_i,
                            'ip_ne'         => $l_i_c_i_n,
                            'mismatch'      => $l_i_c_m,
                        ],
                    ]);
                else
                    Events::create([
                        'type'  => "system",
                        'owner' => "cronjob",
                        'name'  => "license-ip-check",
                        'data'  => [
                            'next-run-time' => $l_i_c_n,
                            'ip'            => $l_i_c_i,
                            'ip_ne'         => $l_i_c_i_n,
                            'mismatch'      => $l_i_c_m,
                        ],
                    ]);
            }

            if ($l_i_c_m) {
                echo 'This IP address of "' . $l_i_c_i_n . '" does not match the registered IP address of "' . $l_i_c_i . '".';
                $tasks = 'run-block';
            }

            if (!$tasks && $sets) $tasks = 'run-block';

            Hook::run("PreCronJob");

            if ($tasks) {
                if(is_array($tasks))
                {
                    foreach($tasks AS $key => $task)
                    {
                        $status = $task["status"];
                        if(!$status) continue;

                        if(isset($task["period"]) && isset($task["time"])){
                            $now           = DateManager::strtotime();
                            $next_run_time = DateManager::strtotime($task["next-run-time"]);
                            if($now < $next_run_time) continue;
                            $new_next_run_time = DateManager::next_date([$task["period"] => $task["time"]]);
                        }


                        $delay_period           = $task["delay"] ?? 60;
                        $running_log_f          = ROOT_DIR."temp".DS."cronjobs.json";
                        $running_log            = FileManager::file_read($running_log_f);
                        $running_log            = $running_log ? Utility::jdecode($running_log,true) : [];

                        if(isset($running_log[$key]) && $running_log[$key])
                        {
                            $expiry_date            = $running_log[$key];
                            $expiry_date            = DateManager::strtotime($expiry_date);
                            if($expiry_date > DateManager::strtotime()) {
                                if(ERROR_DEBUG) echo "Task: $key, Status: Skipped\n";
                                continue;
                            }
                        }

                        $running_log[$key] = DateManager::next_date(['minute' => $delay_period]);
                        FileManager::file_write($running_log_f,Utility::jencode($running_log));

                        $active_orders         = $this->model->db->select("COUNT(id) AS total")->from("users_products");
                        $active_orders->where("status","=","active");

                        $active_orders         = $active_orders->build() ? $active_orders->getObject()->total : 0;
                        $this->process_limit   = $task["process_limit"] ?? ($active_orders >= 1000 ? 9999 : 15);

                        $run            = $this->run_task($key,$task);
                        if(!$installer){
                            if(isset($task["period"]) && isset($task["time"])){
                                $sets["tasks"][$key]["last-run-time"] = DateManager::Now();
                                if($run) $sets["tasks"][$key]["next-run-time"] = $new_next_run_time;
                            }
                        }
                        unset($running_log[$key]);
                        if(ERROR_DEBUG) echo "Task: $key, Status: Completed\n";
                        FileManager::file_write($running_log_f,Utility::jencode($running_log));
                    }
                }


                if (!$installer) {
                    $sets["last-run-time"] = DateManager::Now();
                    $sets["php-version"] = PHP_VERSION;
                }

                if ($sets) {

                    $result = Config::set("cronjobs", $sets);
                    $var_export = Utility::array_export($result, ['pwith' => true]);
                    $c_file = CONFIG_DIR . "cronjobs.php";
                    $e_nm = 'cronjob-time-file-cannot-be-saved';

                    if (!stristr($var_export, '];') || strlen($var_export) <= 50 && isset($backup["data"]) && $backup["data"])
                        $var_export = $backup["data"];

                    $writable = is_writable($c_file);

                    if (!$writable) {
                        $isCreated = Events::isCreated("info", "system", 0, $e_nm, 'pending');
                        if (!$isCreated)
                            Events::create([
                                'type'  => "info",
                                'owner' => "system",
                                'name'  => $e_nm,
                            ]);
                        $var_export = false;
                    }

                    if ($writable && $var_export) {
                        if ($backup)
                            Events::set($backup["id"], [
                                'cdate' => DateManager::Now(),
                                'data'  => base64_encode($var_export),
                            ]);

                        $write = FileManager::file_write($c_file, $var_export);
                        if (!$write) {
                            $isCreated = Events::isCreated("info", "system", 0, $e_nm, 'pending');
                            if (!$isCreated)
                                Events::create([
                                    'type'  => "info",
                                    'owner' => "system",
                                    'name'  => $e_nm,
                                ]);
                            return true;
                        }

                        $isCreated = Events::isCreated("info", "system", 0, $e_nm, 'pending');
                        if ($isCreated)
                            Events::delete($isCreated);

                    }
                }
            }

            Hook::run("AfterCronJob");
            Hook::run("CronTasks");

            if(is_resource($lockFile)) {
                flock($lockFile, LOCK_UN);
                fclose($lockFile);
                if(file_exists($lockFileName)) @unlink($lockFileName);
            }

        }

        private function run_task($key = '', $data = [])
        {
            $method_name = str_replace("-", "_", $key);
            if (method_exists($this, $method_name)) return $data ? $this->{$method_name}($data) : $this->{$method_name}();
            return false;
        }

        private function auto_currency_rates($data = [])
        {
            Helper::Load(["Money", "User"]);

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
                }
            }

            if ($changes) {
                User::addAction(0, "cronjobs", "changed-currency-rates");
                self::$cache->clear("currencies");
                Hook::run("ExchangeRatesUpdated");
            }
            return true;
        }

        private function auto_intl_sms_prices($data = [])
        {
            Helper::Load(["Money", "User"]);

            $mname = Config::get("modules/sms-intl");
            if ($mname == '' || $mname == 'none') return false;

            $module = Modules::Load("SMS", $mname);
            if (!class_exists($mname)) return false;
            if (!isset($module["config"]["supported-currencies"])) return false;

            $sms = Config::get("sms");
            $sms_sets = [];
            $currency = 0;

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
            if (!$currency) return false;

            $getCurr = Money::Currency($currency);

            $module = new $mname();
            if (!method_exists($module, "get_prices")) return false;

            $prices = $module->get_prices();
            if (!$prices) return false;

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

            User::addAction(0, "cronjobs", "changed-intl-sms-country-prices");

            return true;
        }

        private function auto_define_domain_prices($data = [])
        {
            Helper::Load(["Events", "Products"]);
            $changes = Products::auto_define_domain_prices();
            if (!$changes && Products::$error) {
                Events::create([
                    'type'  => "info",
                    "owner" => "system",
                    'name'  => "auto-define-domain-prices-error",
                    'data'  => [
                        'message' => Products::$error,
                    ],
                ]);
            }
            if ($changes) self::$cache->clear();
            if ($changes) User::addAction(0, "cronjobs", "auto-domain-pricing-has-been-run");
            return true;
        }

        private function auto_backup_db($data = [])
        {
            $settings = $data["settings"];
            $ftp_upload = $settings["ftp-host"] && $settings["ftp-port"] && $settings["ftp-username"] && $settings["ftp-password"];
            $notification = Config::get("notifications/admin-messages/created-backup-db/status");
            if (!($notification || $ftp_upload)) return false;

            Helper::Load(["ExportDB", "Events", "Notification"]);


            $processing = Events::isCreated('processing', 'cronjob', 0, 'auto-backup-db', false, 0, true);
            if ($processing) {
                $p_data = Utility::jdecode($processing["data"], true);
                if ($p_data && $processing["status"] == "pending") {
                    $wait_time = DateManager::strtotime(DateManager::next_date([
                        $processing["cdate"],
                        'minute' => 5,
                    ]));
                    if (DateManager::strtotime() > $wait_time) Events::set($processing["id"], ['status' => "approved"]);
                    else
                        return true;
                }
            }


            $ev_data = [
                'type'   => "processing",
                'owner'  => "cronjob",
                'name'   => "auto-backup-db",
                'status' => "pending",
                'cdate'  => DateManager::Now(),
                'data'   => [],
            ];


            if ($processing) Events::set($processing["id"], $ev_data);
            else {
                $processing_id = Events::create($ev_data);
                $processing = Events::get($processing_id);
            }

            MioException::$error_hide = true;
            try {
                $DB_KEY = "WISECP_DB_" . Config::get("crypt/system") . "_WISECP_DB";
                $DB_USER = Crypt::decode(Config::get("database/username"), $DB_KEY);
                $DB_PASSWORD = Crypt::decode(Config::get("database/password"), $DB_KEY);

                try {
                    $data_dumper = Shuttle_Dumper::create(array(
                        'host'     => Config::get("database/host"),
                        'port'     => Config::get("database/port"),
                        'username' => $DB_USER,
                        'password' => $DB_PASSWORD,
                        'db_name'  => Config::get("database/name"),
                    ));

                    $rand_name = "backup-" . DateManager::Now("Y-m-d-H-i") . '-' . rand(100000, 999999);
                    $backup_name_sql = $rand_name . '.sql';
                    $backup_name_zip = $rand_name . ".zip";
                    $folder = ROOT_DIR . "temp" . DS;
                    $backup_file_sql = $folder . $backup_name_sql;
                    $backup_file_zip = $folder . $backup_name_zip;

                    $data_dumper->dump($backup_file_sql);
                } catch (Shuttle_Exception $e) {
                    Events::create([
                        'type'  => "info",
                        "owner" => "system",
                        'name'  => "auto-backup-db-error",
                        'data'  => [
                            'message' => "Can not create SQL File: " . $e->getMessage(),
                        ],
                    ]);
                    if ($processing) Events::set($processing["id"], ['status' => "approved"]);
                    return true;
                }

                if (!file_exists($backup_file_sql)) {
                    Events::create([
                        'type'  => "info",
                        "owner" => "system",
                        'name'  => "auto-backup-db-error",
                        'data'  => [
                            'message' => "Can not create SQL File.",
                        ],
                    ]);
                    if ($processing) Events::set($processing["id"], ['status' => "approved"]);
                    return true;
                }

                if (!class_exists("ZipArchive")) {
                    FileManager::file_delete($backup_file_sql);
                    Events::create([
                        'type'  => "info",
                        "owner" => "system",
                        'name'  => "auto-backup-db-error",
                        'data'  => [
                            'message' => "Can not create zip archive.",
                        ],
                    ]);
                    if ($processing) Events::set($processing["id"], ['status' => "approved"]);
                    return true;
                }

                $zip = new ZipArchive();

                if ($zip->open($backup_file_zip, ZipArchive::CREATE) !== true) {
                    FileManager::file_delete($backup_file_sql);
                    Events::create([
                        'type'  => "info",
                        "owner" => "system",
                        'name'  => "auto-backup-db-error",
                        'data'  => [
                            'message' => "Can not create zip archive.",
                        ],
                    ]);
                    if ($processing) Events::set($processing["id"], ['status' => "approved"]);
                    return true;
                }
                $zip->addFile($backup_file_sql, $backup_name_sql);
                $zip->close();

                FileManager::file_delete($backup_file_sql);

                if (!file_exists($backup_file_zip)) {
                    Events::create([
                        'type'  => "info",
                        "owner" => "system",
                        'name'  => "auto-backup-db-error",
                        'data'  => [
                            'message' => "Can not create zip archive.",
                        ],
                    ]);
                    if ($processing) Events::set($processing["id"], ['status' => "approved"]);
                    return true;
                }
                $backup_file = $backup_file_zip;
            } catch (Shuttle_Exception $e) {
                Events::create([
                    'type'  => "info",
                    "owner" => "system",
                    'name'  => "auto-backup-db-error",
                    'data'  => [
                        'message' => $e->getMessage(),
                    ],
                ]);
                if ($processing) Events::set($processing["id"], ['status' => "approved"]);
                return true;
            }
            MioException::$error_hide = false;

            if ($backup_file) {
                $backup_complete = false;

                if ($notification) {
                    $submit = Notification::created_backup_db($backup_file);
                    if ($submit == "OK") $backup_complete = true;
                }

                if ($ftp_upload) {

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

                    $host = $settings["ftp-host"];
                    $port = $settings["ftp-port"];
                    $username = $settings["ftp-username"];
                    $password = Crypt::decode($settings["ftp-password"], Config::get("crypt/user"));
                    $target = $settings["ftp-target"];
                    $ssl = isset($settings["ftp-ssl"]) && $settings["ftp-ssl"];

                    MioException::$error_hide = true;
                    $backup_ftp = $write($host, $port, $username, $password, $target, $ssl, false, $backup_file, $backup_name_zip);
                    if ($backup_ftp && !is_bool($backup_ftp))
                        $backup_ftp = $write($host, $port, $username, $password, $target, $ssl, true, $backup_file, $backup_name_zip);
                    MioException::$error_hide = false;

                    if ($backup_ftp && !is_bool($backup_ftp)) {
                        LogManager::core_error_log(500, 'Auto Backup FTP:' . $backup_ftp, __FILE__, __LINE__);
                        Events::create([
                            'type'  => "info",
                            "owner" => "system",
                            'name'  => "auto-backup-db-error",
                            'data'  => [
                                'message' => $backup_ftp,
                            ],
                        ]);
                    }
                    $backup_complete = true;
                }

                FileManager::file_delete($backup_file);

                if ($backup_complete)
                    User::addAction(0, "cronjobs", "created-backup-db");
                else {
                    Events::create([
                        'type'  => "info",
                        "owner" => "system",
                        'name'  => "auto-backup-db-error",
                        'data'  => [
                            'message' => "The backup file was created but could not be uploaded to FTP or sent as a notification.",
                        ],
                    ]);
                }
            }
            if ($processing) Events::set($processing["id"], ['status' => "approved"]);
            return true;
        }

        private function cancellation_requests($data = [])
        {
            Helper::Load(["User", "Orders", "Events", "Money", "Notification"]);

            $changes = 0;

            $orders = $this->model->cancellation_request_orders();
            if ($orders) {
                foreach ($orders as $row) {
                    if ($row["module"] != "none" && ($row["type"] == "server" || $row["type"] == "hosting")) {
                        $result = Orders::ModuleHandler(Orders::get($row["id"]), false, "terminate");
                        if (!$result || $result == "failed") {
                            Events::create([
                                'type'     => "operation",
                                'owner'    => "order",
                                'owner_id' => $row["id"],
                                'name'     => "order-terminate-error",
                                'data'     => [
                                    'message' => Orders::$message,
                                ],
                            ]);
                            continue;
                        }
                        Orders::set($row["id"], ['server_terminated' => 1, 'terminated_date' => DateManager::Now()]);
                    }
                    $apply = Orders::MakeOperation("cancelled", $row["id"], false, true);
                    if ($apply) {
                        $changes++;
                        User::addAction(0, "alteration", "cancelled-order", [
                            'id'   => $row["id"],
                            'name' => $row["name"],
                        ]);
                        User::addAction(0, "alteration", "approved-order-cancellation-request", [
                            'order_name' => $row["name"],
                            'order_id'   => $row["id"],
                            'id'         => 0,
                        ]);
                    } else
                        Events::set($row["event_id"], ['status_msg' => Orders::$message, 'unread' => 0]);

                }
            }
            if ($changes) User::addAction(0, "cronjobs", "order-cancellation-request-processing-received", ['count' => $changes]);
            return true;
        }

        private function invoice_deletion($data = [])
        {
            Helper::Load(["User", "Invoices", "Money", "Notification"]);
            $settings = $data["settings"];
            $day = $settings["day"];

            $invoices = $this->model->cancelled_invoices($day);

            $changes = 0;
            if ($invoices) foreach ($invoices as $invoice) if (Invoices::MakeOperation("delete", $invoice["id"])) $changes++;
            if ($changes) User::addAction(0, "cronjobs", "cancelled-invoices-has-been-deleted", ['count' => $changes]);
            return true;
        }

        private function invoice_create($data = [])
        {
            Helper::Load(["User", "Invoices", "Orders", "Money", "Products", "Notification", "Events"]);
            $settings = $data["settings"];
            $month = $settings["month"];
            $thn_month = $settings["than-one-month"];
            $orders = $this->model->remaining_orders($month, $thn_month);
            $addons = $this->model->remaining_addons($month, $thn_month);
            $udatas = [];
            $changes = 0;
            $notification_count = 0;
            $rows = array_merge($orders, $addons);

            $notifications = [];

            if ($rows) {
                foreach ($rows as $row) {
                    if ($changes >= $this->process_limit) break;
                    $order_type = isset($row["addon_id"]) ? "addon" : "order";

                    if ($order_type == "order" && $this->model->check_cancelled_request($row["id"], $row["renewaldate"])) continue;
                    if ($order_type == "addon" && $this->model->check_cancelled_request($row["owner_id"], $row["renewaldate"])) continue;
                    if ($order_type == "addon") {
                        $order = Orders::get($row["owner_id"], 'owner_id');
                        if (!$order) continue;
                        $row["user_id"] = $order["owner_id"];
                    }

                    $period_begin = new DateTime($row["duedate"]);
                    $period_end = new DateTime(DateManager::Now("Y-m-d H:i"));
                    if ($row["period_time"] == 0) continue;


                    $duedates = [];
                    if (!($period_begin > $period_end)) {
                        $interval = DateInterval::createFromDateString($row["period_time"] . ' ' . $row["period"]);
                        $loop = new DatePeriod($period_begin, $interval, $period_end);
                        if ($loop) foreach ($loop as $d) $duedates[] = (string)$d->format("Y-m-d H:i");
                    }

                    if (sizeof($duedates) > 1) {
                        $new_date = DateManager::next_date([end($duedates), $row["period"] => $row["period_time"]]);
                        if (DateManager::strtotime($new_date) < DateManager::strtotime())
                            $duedates[] = $new_date;

                        foreach ($duedates as $duedate) {
                            $row["duedate"] = $duedate;
                            $previouslyCreated = Invoices::previously_created_check($order_type, $row, true);
                            if (!$previouslyCreated) {
                                break;
                            }
                        }
                    } else
                        $previouslyCreated = Invoices::previously_created_check($order_type, $row);

                    if (!$previouslyCreated) {
                        if (!isset($udatas[$row["user_id"]])) {
                            $udata = User::getData($row["user_id"], "id,lang,balance_currency,balance_min,full_name", "array");
                            $udata = array_merge($udata, User::getInfo($udata["id"], ["auto_payment_by_credit", "dealership"]));
                            $udatas[$row["user_id"]] = $udata;
                        }

                        $udata = $udatas[$row["user_id"]];
                        $udata = array_merge($udata, User::getData($udata["id"], "balance", "array"));


                        $invoice = Invoices::generate_renewal_bill($order_type, $udata, $row);
                        if (!is_array($invoice) && $invoice == 'continue') continue;
                        if ($invoice) {
                            $balanceModule = Modules::Load("Payment", "Balance", true);
                            $balanceConfig = $balanceModule["config"] ?? [];
                            if (!($balanceConfig["settings"]["auto-payment"] ?? false)) continue;

                            $changes++;
                            if ($order_type == "addon") Orders::set_addon($row["id"], ['invoice_id' => $invoice["id"]]);
                            if ($order_type == "order") Orders::set($row["id"], ['invoice_id' => $invoice["id"]]);

                            $c_invoice = $invoice;
                            $c_invoice['pmethod'] = 'Balance';
                            $items = Invoices::get_items($invoice['id']);
                            $calculate = Invoices::calculate_invoice($c_invoice, $items);
                            $currency = $invoice["currency"];

                            $u_amount = Money::exChange($calculate["total"], $currency, $udata["balance_currency"]);
                            $paymentByBalance = $udata["auto_payment_by_credit"] && $u_amount < $udata["balance"];

                            if ($paymentByBalance) {
                                $new_credit = $udata["balance"] - $u_amount;
                                User::setData($udata["id"], ['balance' => $new_credit]);
                                User::insert_credit_log([
                                    'user_id'     => $udata["id"],
                                    'description' => $invoice["number"],
                                    'type'        => "down",
                                    'amount'      => $u_amount,
                                    'cid'         => $udata["balance_currency"],
                                    'cdate'       => DateManager::Now(),
                                ]);
                                User::addAction($udata["id"], "alteration", "paid-bill-by-credit", [
                                    'invoice_id'  => $invoice["id"],
                                    'old_credit'  => Money::formatter_symbol($udata["balance"], $udata["balance_currency"]),
                                    'new_credit'  => Money::formatter_symbol($new_credit, $udata["balance_currency"]),
                                    'amount_paid' => Money::formatter_symbol($u_amount, $udata["balance_currency"]),
                                ]);

                                $legal_zero = false;
                                $btxn = Config::get("options/balance-taxation");
                                if (!$btxn) $btxn = "y";

                                if ($btxn == "y") $legal_zero = true;
                                if ($btxn == "n") $legal_zero = false;

                                Invoices::set($invoice["id"], [
                                    'legal'          => $legal_zero ? 0 : 1,
                                    'subtotal'       => $calculate["subtotal"],
                                    'tax'            => $calculate["tax"],
                                    'total'          => $calculate["total"],
                                    'pmethod'        => "Balance",
                                    'pmethod_status' => "SUCCESS",
                                    'pmethod_msg'    => Utility::jencode([
                                        'format-amount-paid'   => Money::formatter_symbol($u_amount, $udata["balance_currency"]),
                                        'amount-paid'          => $u_amount,
                                        'amount-paid-currency' => $udata["balance_currency"],
                                    ]),
                                    'datepaid'       => DateManager::Now(),
                                ]);
                                Invoices::MakeOperation("paid", $invoice["id"]);
                                $invoice["status"] = "paid";
                            }

                            if ($invoice["status"] == "paid") $notifications[$invoice["id"]] = "paid";
                            if ($invoice["status"] == "unpaid") $notifications[$invoice["id"]] = "unpaid";

                            $this->save_log("#" . $invoice["id"] . " invoice created");
                        } elseif (Invoices::$message && Invoices::$message == "no-user-address") {
                            if (!Events::isCreated('info', 'system', 0, 'invoice-address-issue', false, $udata["id"]))
                                Events::create([
                                    'type'    => "info",
                                    'owner'   => "system",
                                    'name'    => "invoice-address-issue",
                                    'user_id' => $udata["id"],
                                    'data'    => [
                                        'order_type' => $order_type,
                                        'order_id'   => $row["id"],
                                        'user_id'    => $udata["id"],
                                        'user_name'  => $udata["full_name"],
                                        'order_name' => isset($row["name"]) ? $row["name"] : 'Addon',
                                    ],
                                ]);
                        }
                    }
                    ## Previously END ##
                }
            }

            if ($notifications) {
                foreach ($notifications as $invoice_id => $stat)
                    if ($stat == "paid")
                        Notification::invoice_has_been_approved($invoice_id);
            }

            if ($changes < 1) {
                $stmt = $this->model->db->select("id")->from("invoices")->where("status", "=", "unpaid");
                if ($stmt->build()) {
                    foreach ($stmt->fetch_object() as $in) {
                        if ($notification_count >= $this->process_limit) break;
                        if (!Events::isCreated('notification', 'invoice', $in->id, 'invoice-created')) {
                            $notification_count++;
                            Notification::invoice_created($in->id);
                        }
                    }
                }
            }

            if ($changes) User::addAction(0, "cronjobs", "created-invoice", ['count' => $changes]);
            return $changes < 1 && $notification_count < 1;
        }

        private function invoice_cancellation($data = [])
        {
            Helper::Load(["User", "Invoices", "Money", "Notification"]);
            $settings = $data["settings"];
            $day = $settings["day"];

            $invoices = $this->model->delayed_invoices($day);

            $changes = 0;
            if ($invoices) {
                foreach ($invoices as $invoice) {
                    if ($changes >= $this->process_limit) break;
                    $user_info = User::getInfo($invoice["user_id"], ['never_cancel']);
                    if (isset($user_info['never_cancel']) && $user_info['never_cancel']) continue;

                    if (Invoices::MakeOperation("cancelled", $invoice["id"], true)) $changes++;
                }
            }
            if ($changes) User::addAction(0, "cronjobs", "delayed-invoices-has-been-cancelled", ['count' => $changes]);
            return $changes < 1;
        }

        private function invoice_reminder($data = [])
        {
            Helper::Load(["User", "Invoices", "Money", "Notification", "Events"]);
            $settings = $data["settings"];
            $first = $settings["first"];
            $second = $settings["second"];
            $third = $settings["third"];

            $invoices = $this->model->remaining_invoices($first, $second, $third);
            $changes = 0;
            if ($invoices) {
                foreach ($invoices as $invoice) {
                    if ($changes >= $this->process_limit) break;
                    $isReminded = Events::isCreated("notification", "invoice", $invoice["id"], 'invoice-reminder', 0, 0, true);
                    if (!$isReminded || DateManager::format("Y-m-d", $isReminded["cdate"]) != DateManager::Now("Y-m-d"))
                        if (Notification::invoice_reminder($invoice["id"], $invoice["remaining_day"]))
                            $changes++;
                }
            }
            if ($changes) User::addAction(0, "cronjobs", "reminded-invoice", ['count' => $changes]);
            return $changes < 1;
        }

        private function invoice_overdue($data = [])
        {
            Helper::Load(["User", "Invoices", "Money", "Notification", "Events"]);
            $settings = $data["settings"];
            $first = $settings["first"];
            $second = $settings["second"];
            $third = $settings["third"];

            $invoices = $this->model->overdue_invoices($first, $second, $third);
            $changes = 0;
            if ($invoices) {
                foreach ($invoices as $invoice) {
                    if ($changes >= $this->process_limit) break;
                    $isReminded = Events::isCreated("notification", "invoice", $invoice["id"], 'invoice-overdue', 0, 0, true);
                    if (!$isReminded || DateManager::format("Y-m-d", $isReminded["cdate"]) != DateManager::Now("Y-m-d"))
                        if (Notification::invoice_overdue($invoice["id"]))
                            $changes++;
                }
            }
            if ($changes) User::addAction(0, "cronjobs", "overdue-invoice-has-been-reported", ['count' => $changes]);
            return $changes < 1;
        }

        private function invoice_auto_payment($data = [])
        {
            Helper::Load(["Basket", "User", "Invoices", "Money", "Notification", "Events"]);
            $s_pmethod = Config::get("modules/card-storage-module");

            if (!$s_pmethod || $s_pmethod == "none") return false;
            if (!Modules::Load("Payment", $s_pmethod)) return false;
            if (!class_exists($s_pmethod)) return false;

            $module = new $s_pmethod();


            $invoices = $this->model->auto_payment_orders();
            $changes = 0;

            if ($invoices) {
                foreach ($invoices as $invoice) {
                    if ($changes > 0) continue;
                    $user_id = $invoice["user_id"];
                    $invoice_id = $invoice["id"];
                    $stored_card = $this->model->get_default_stored_card($user_id, $s_pmethod);
                    $u_lang = 'en';
                    $user_data = User::getData($user_id, "lang", "assoc");
                    if ($user_data) $u_lang = $user_data["lang"];

                    $istdycc = $this->model->istdycc($invoice_id, DateManager::Now("Y-m-d"));
                    if ($istdycc) continue;


                    if (!$stored_card) {
                        Notification::invoice_auto_payment_failed($invoice_id, Bootstrap::$lang->get("errors/stored-card-1", $u_lang), '****');
                        Modules::save_log("Payment", $s_pmethod, "capture", "Credit card capture for invoice: #" . $invoice_id, Bootstrap::$lang->get("errors/stored-card-1", $u_lang));
                        continue;
                    }
                    $stored_card_id = $stored_card["id"];

                    $invoice = Invoices::get($invoice_id);
                    $items = Invoices::get_items($invoice_id);

                    $currency = $invoice["currency"];

                    $calculate = Invoices::calculate_invoice($invoice, $items, ['included_d_subtotal' => true]);
                    $total = $calculate['subtotal'];
                    $camount = 0;
                    if (method_exists($module, "commission_fee_calculator") && $module->commission)
                        $camount = $module->commission_fee_calculator($total);
                    if ($camount) {
                        $pcommission_rate = $module->get_commission_rate();
                        $pcommission = $camount;
                        $invoice['pmethod_commission'] = $pcommission;
                    }

                    $calculate = Invoices::calculate_invoice($invoice, $items, [
                        'discount_total' => true,
                    ]);

                    $d_total = $calculate['discount_total'];
                    $invoice['subtotal'] = $calculate['subtotal'];
                    $invoice['tax'] = $calculate['tax'];
                    $invoice['total'] = $calculate['total'];

                    $new_items = [];
                    foreach ($items as $item) {
                        $nitem = $item;
                        $nitem["name"] = $item["description"];
                        $new_items[] = $nitem;
                    }

                    if ($d_total > 0.0) {
                        $new_items[] = [
                            'options'      => [],
                            'name'         => "DISCOUNT",
                            'quantity'     => 1,
                            'amount'       => -$d_total,
                            'total_amount' => -$d_total,
                        ];
                    }

                    if (isset($pcommission) && $pcommission) {
                        $new_items[] = [
                            'options'      => [],
                            'name'         => Bootstrap::$lang->get_cm("website/account_invoices/pmethod_commission", ['{method}' => $s_pmethod], $u_lang) . " (%" . $pcommission_rate . ")",
                            'quantity'     => 1,
                            'amount'       => $pcommission,
                            'total_amount' => $pcommission,
                        ];
                    }

                    $data = [
                        'type'                    => "bill",
                        'user_data'               => $invoice["user_data"],
                        'user_id'                 => $user_id,
                        'invoice_id'              => $invoice["id"],
                        'local'                   => $invoice["local"],
                        'legal'                   => $invoice["legal"],
                        'currency'                => $invoice["currency"],
                        'subtotal'                => $invoice['subtotal'],
                        'taxrate'                 => $invoice["taxrate"],
                        'tax'                     => $invoice["tax"],
                        'total'                   => $invoice["total"],
                        'sendbta'                 => 0,
                        'pmethod'                 => $s_pmethod,
                        'pmethod_commission'      => isset($pcommission) ? $pcommission : 0,
                        'pmethod_commission_rate' => isset($pcommission_rate) ? $pcommission_rate : 0,
                        'pmethod_stored_card'     => $stored_card_id,
                        'pmethod_by_auto_pay'     => 1,
                    ];

                    $checkout = Basket::add_checkout([
                        'user_id' => $user_id,
                        'type'    => "bill",
                        'items'   => Utility::jencode($new_items),
                        'data'    => Utility::jencode($data),
                        'cdate'   => DateManager::Now(),
                        'mdfdate' => DateManager::Now(),
                    ]);

                    if (!$checkout) {
                        Notification::invoice_auto_payment_failed($invoice_id, Bootstrap::$lang->get("errors/stored-card-2", $u_lang), $stored_card["ln4"]);
                        Modules::save_log("Payment", $s_pmethod, "capture", "Credit card capture for invoice: #" . $invoice_id, Bootstrap::$lang->get("errors/stored-card-2", $u_lang));
                        continue;
                    }


                    Events::create([
                        'type'     => "log",
                        'user_id'  => $user_id,
                        'owner'    => "invoice",
                        'owner_id' => $invoice_id,
                        'name'     => "credit-card-captured",
                        'data'     => [
                            'stored_card_id' => $stored_card_id,
                            'ln4'            => $stored_card["ln4"],
                        ],
                    ]);

                    $checkout = Basket::get_checkout($checkout);

                    $module->set_checkout($checkout);

                    if ($s_pmethod == "PayTR")
                        $capture = $module->capture($checkout);
                    else {
                        $payment_amount = $checkout["data"]["total"];
                        $payment_currency = $checkout["data"]["currency"];
                        $force_curr = $module->config["settings"]["force_convert_to"] ?? 0;

                        if ($force_curr > 0) {
                            $payment_amount = Money::exChange($payment_amount, $payment_currency, $force_curr);
                            $payment_currency = $force_curr;
                        }

                        $stored_card = $module->get_stored_card($stored_card_id);
                        $capture = $module->capture([
                            'auto_payment' => true,
                            'card_storage' => $stored_card,
                            'amount'       => $payment_amount,
                            'currency'     => $payment_currency,
                        ]);
                    }

                    if ($capture && is_array($capture)) {
                        if (in_array($capture['status'], ['successful', 'success']))
                            $module->callback_processed($capture);
                        elseif (in_array($capture['status'], ['error', 'declined'])) {
                            $capture = false;
                            $module->error = $capture['message'] ?? 'Unknown';
                        } else {
                            $capture = false;
                            $module->error = Bootstrap::$lang->get_cm("website/payment/auto-payment-error1", false, $u_lang);
                        }
                    }

                    if (!$capture) {
                        if (!$module->error) $module->error = "Unknown";
                        Notification::invoice_auto_payment_failed($invoice_id, $module->error, $stored_card["ln4"]);
                        Modules::save_log("Payment", $s_pmethod, "capture", "Credit card capture for invoice: #" . $invoice_id, $module->error);
                        continue;
                    }


                    $changes++;
                }
            }

            return $changes < 1;
        }

        private function order_suspend($data = [])
        {
            Helper::Load(["User", "Orders", "Events", "Money", "Notification"]);
            $settings = $data["settings"];
            $day = $settings["day"];
            $hour = isset($settings["hour"]) ? $settings["hour"] : 0;
            $changes = 0;


            $processing = Events::isCreated('processing', 'cronjob', 0, 'order-suspend', false, 0, true);
            if ($processing) {
                $p_data = Utility::jdecode($processing["data"], true);
                if ($p_data && $processing["status"] == "pending") {
                    if ($p_data["type"] == "order")
                        $order = Orders::get($p_data["id"], 'status');
                    elseif ($p_data["type"] == "addon")
                        $order = Orders::get_addon($p_data["id"], 'status');
                    else
                        $order = false;

                    $wait_time = DateManager::strtotime(DateManager::next_date([
                        $processing["cdate"],
                        'minute' => 5,
                    ]));
                    if (DateManager::strtotime() > $wait_time) {
                        if ($p_data["type"] == "order") {
                            if ($order && !in_array($order["status"], ['suspended', 'cancelled'])) {
                                Orders::set($p_data["id"], ['status_msg' => 'Suspension or cancellation failed. If the operation has already been performed, mark this warning as read.']);
                            }
                        } elseif ($p_data["type"] == "addon") {
                            if ($order && !in_array($order["status"], ['suspended', 'cancelled']))
                                Orders::set_addon($p_data["id"], ['status_msg' => 'Suspension or cancellation failed. If the operation has already been performed, mark this warning as read.']);
                        }
                        Events::set($processing["id"], ['status' => "approved"]);
                    } else {
                        if ($order["status"] == "suspended")
                            Events::set($processing["id"], ['status' => "approved"]);
                        else
                            return false;
                    }
                }
            }

            $orders = $this->model->delayed_orders("order", $day, $hour, "active");
            $addons = $this->model->delayed_orders("addon", $day, $hour, "active");
            $rows = array_merge($orders, $addons);
            $rows_x = $this->model->delayed_orders_sc('suspend');

            if ($rows) {
                foreach ($rows as $row) {
                    if ($changes >= $this->process_limit) break;
                    if (strlen($row["status_msg"]) > 0) continue;
                    $user_info = User::getInfo($row["user_id"], ['never_suspend']);
                    if (isset($user_info['never_suspend']) && $user_info['never_suspend']) continue;
                    $order_type = isset($row["addon_id"]) ? "addon" : "order";

                    $ev_data = [
                        'type'   => "processing",
                        'owner'  => "cronjob",
                        'name'   => "order-suspend",
                        'status' => "pending",
                        'cdate'  => DateManager::Now(),
                        'data'   => [
                            'type' => $order_type,
                            'id'   => $row["id"],
                        ],
                    ];

                    if ($order_type == "addon") {
                        if ($row["addon_key"] == "whois-privacy") continue;

                        if ($processing) Events::set($processing["id"], $ev_data);
                        else {
                            $processing_id = Events::create($ev_data);
                            $processing = Events::get($processing_id);
                        }

                        $ulang = User::getData($row["user_id"], 'lang')->lang;
                        if (!Bootstrap::$lang->LangExists($ulang)) $ulang = Config::get("general/local");
                        Orders::$suspended_reason = Bootstrap::$lang->get_cm("admin/orders/expired-suspend-reason", false, $ulang);

                        $handle = Orders::MakeOperationAddon('suspended', $row["owner_id"], $row["id"]);

                        if ($handle && $handle !== 'realized-on-module') {
                            Orders::set_addon($row["id"], ['unread' => 0]);
                            if (!Events::isCreated('operation', 'order-addon', $row["id"], "order-addon-has-been-suspended", 'pending'))
                                Events::create([
                                    'type'     => "operation",
                                    'owner'    => "order-addon",
                                    'owner_id' => $row["id"],
                                    'name'     => "order-addon-has-been-suspended",
                                ]);
                        }

                        Events::set($processing["id"], ['status' => "approved"]);

                        $changes++;
                    } elseif ($order_type == "order") {
                        $sdt = $row["suspend_date"] ?? '';
                        if ($sdt) $sdt = substr($row["suspend_date"], 0, 4);

                        if ($sdt && $sdt != '1881' && $sdt != '0000' && $sdt != '1971' && $sdt != '1970') continue;

                        if ($processing) Events::set($processing["id"], $ev_data);
                        else {
                            $processing_id = Events::create($ev_data);
                            $processing = Events::get($processing_id);
                        }

                        $ulang = User::getData($row["user_id"], 'lang')->lang;
                        if (!Bootstrap::$lang->LangExists($ulang)) $ulang = Config::get("general/local");
                        Orders::$suspended_reason = Bootstrap::$lang->get_cm("admin/orders/expired-suspend-reason", false, $ulang);

                        $result = Orders::MakeOperation("suspended", $row["id"]);
                        if ($result) {
                            User::addAction(0, "alteration", "suspended-order", [
                                'id'   => $row["id"],
                                'name' => $row["name"],
                            ]);

                            $if_ex = true;
                            if (!defined("SOFTWARE_PRODUCT_NOTIFICATION"))
                                $if_ex = $row["type"] !== "software";

                            if ($row["module"] == "none" && $if_ex) {
                                if (!Events::isCreated('operation', 'order', $row["id"], "order-has-been-suspended", 'pending'))
                                    Events::create([
                                        'type'     => "operation",
                                        'owner'    => "order",
                                        'owner_id' => $row["id"],
                                        'name'     => "order-has-been-suspended",
                                    ]);
                            }
                            $changes++;
                        } elseif (Orders::$message)
                            Orders::set($row["id"], ['status_msg' => 'Reason for Suspension: ' . Orders::$message]);

                        Events::set($processing["id"], ['status' => "approved"]);
                    }
                }
            }
            if ($rows_x) {
                foreach ($rows_x as $row) {
                    if ($changes > 0) break;
                    if (strlen($row["status_msg"]) > 0) continue;
                    $user_info = User::getInfo($row["user_id"], ['never_suspend']);
                    if (isset($user_info['never_suspend']) && $user_info['never_suspend']) continue;


                    $ev_data = [
                        'type'   => "processing",
                        'owner'  => "cronjob",
                        'name'   => "order-suspend",
                        'status' => "pending",
                        'cdate'  => DateManager::Now(),
                        'data'   => [
                            'type' => "order",
                            'id'   => $row["id"],
                        ],
                    ];

                    if ($processing) Events::set($processing["id"], $ev_data);
                    else {
                        $processing_id = Events::create($ev_data);
                        $processing = Events::get($processing_id);
                    }

                    $ulang = User::getData($row["user_id"], 'lang')->lang;
                    if (!Bootstrap::$lang->LangExists($ulang)) $ulang = Config::get("general/local");
                    Orders::$suspended_reason = Bootstrap::$lang->get_cm("admin/orders/expired-suspend-reason", false, $ulang);

                    $result = Orders::MakeOperation("suspended", $row["id"]);
                    if ($result) {
                        User::addAction(0, "alteration", "suspended-order", [
                            'id'   => $row["id"],
                            'name' => $row["name"],
                        ]);

                        $if_ex = true;
                        if (!defined("SOFTWARE_PRODUCT_NOTIFICATION"))
                            $if_ex = $row["type"] !== "software";

                        if ($row["module"] == "none" && $if_ex) {
                            if (!Events::isCreated('operation', 'order', $row["id"], "order-has-been-suspended", 'pending'))
                                Events::create([
                                    'type'     => "operation",
                                    'owner'    => "order",
                                    'owner_id' => $row["id"],
                                    'name'     => "order-has-been-suspended",
                                ]);
                        }
                        $changes++;
                    } elseif (Orders::$message)
                        Orders::set($row["id"], ['status_msg' => 'Could not be suspended due to ' . Orders::$message]);

                    Events::set($processing["id"], ['status' => "approved"]);
                }
            }

            if ($changes) User::addAction(0, "cronjobs", "active-orders-has-been-suspended", ['count' => $changes]);
            return !$changes > 0;
        }

        private function order_cancel($data = [])
        {
            Helper::Load(["User", "Orders", "Events", "Money", "Notification"]);
            $settings = $data["settings"];
            $day = $settings["day"];
            $hour = isset($settings["hour"]) ? $settings["hour"] : 6;
            $changes = 0;

            $processing = Events::isCreated('processing', 'cronjob', 0, 'order-cancel', false, 0, true);
            if ($processing) {
                $p_data = Utility::jdecode($processing["data"], true);
                if ($p_data && $processing["status"] == "pending") {
                    if ($p_data["type"] == "order")
                        $order = Orders::get($p_data["id"], 'status');
                    elseif ($p_data["type"] == "addon")
                        $order = Orders::get_addon($p_data["id"], 'status');
                    else
                        $order = false;

                    $wait_time = DateManager::strtotime(DateManager::next_date([
                        $processing["cdate"],
                        'minute' => 5,
                    ]));
                    if (DateManager::strtotime() > $wait_time) {
                        if ($p_data["type"] == "order") {
                            if ($order && !in_array($order["status"], ['cancelled']))
                                Orders::set($p_data["id"], ['status_msg' => 'Suspension or cancellation failed. If the operation has already been performed, mark this warning as read.']);
                        } elseif ($p_data["type"] == "addon") {
                            if ($order && !in_array($order["status"], ['cancelled']))
                                Orders::set_addon($p_data["id"], ['status_msg' => 'Suspension or cancellation failed. If the operation has already been performed, mark this warning as read.']);
                        }
                        Events::set($processing["id"], ['status' => "approved"]);
                    } else {
                        if ($order["status"] == "cancelled")
                            Events::set($processing["id"], ['status' => "approved"]);
                        else
                            return false;
                    }
                }
            }

            $orders1 = $this->model->delayed_orders("order", $day, $hour, 'active');
            $orders2 = $this->model->delayed_orders("order", $day, $hour, 'suspended');
            $addons1 = $this->model->delayed_orders("addon", $day, $hour, 'active');
            $addons2 = $this->model->delayed_orders("addon", $day, $hour, 'suspended');
            $rows = array_merge($orders1, $orders2, $addons1, $addons2);
            $rows_x = $this->model->delayed_orders_sc('cancel');

            if ($rows) {
                foreach ($rows as $row) {
                    if ($changes >= $this->process_limit) break;
                    if (strlen($row["status_msg"]) > 0) continue;
                    $user_info = User::getInfo($row["user_id"], ['never_cancel']);
                    if (isset($user_info['never_cancel']) && $user_info['never_cancel']) continue;
                    if ($row["status"] == "cancelled") continue;
                    $order_type = isset($row["addon_id"]) ? "addon" : "order";

                    $ev_data = [
                        'type'   => "processing",
                        'owner'  => "cronjob",
                        'name'   => "order-cancel",
                        'status' => "pending",
                        'cdate'  => DateManager::Now(),
                        'data'   => [
                            'type' => $order_type,
                            'id'   => $row["id"],
                        ],
                    ];

                    if ($order_type == "addon") {

                        if ($row["addon_key"] == "whois-privacy") {
                            $order = Orders::get($row["owner_id"], "id,options");
                            $options = $order["options"];
                            if (isset($options["whois_privacy"])) unset($options["whois_privacy"]);
                            if (isset($options["whois_privacy_endtime"])) unset($options["whois_privacy_endtime"]);
                            Orders::set($order["id"], ['options' => Utility::jencode($options)]);
                        }

                        if ($processing) Events::set($processing["id"], $ev_data);
                        else {
                            $processing_id = Events::create($ev_data);
                            $processing = Events::get($processing_id);
                        }

                        $handle = Orders::MakeOperationAddon('cancelled', $row["owner_id"], $row["id"]);
                        if ($handle && $handle !== "realized-on-module") {
                            Orders::set_addon($row["id"], ['unread' => 0]);
                            if (!Events::isCreated('operation', 'order-addon', $row["id"], "order-addon-has-been-cancelled", 'pending'))
                                Events::create([
                                    'type'     => "operation",
                                    'owner'    => "order-addon",
                                    'owner_id' => $row["id"],
                                    'name'     => "order-addon-has-been-cancelled",
                                ]);
                        }
                        $changes++;
                        Events::set($processing["id"], ['status' => "approved"]);
                    } elseif ($order_type == "order") {

                        if ($processing) Events::set($processing["id"], $ev_data);
                        else {
                            $processing_id = Events::create($ev_data);
                            $processing = Events::get($processing_id);
                        }

                        $result = Orders::MakeOperation("cancelled", $row["id"]);
                        if ($result) {
                            User::addAction(0, "alteration", "cancelled-order", [
                                'id'   => $row["id"],
                                'name' => $row["name"],
                            ]);
                            $changes++;
                        } elseif (Orders::$message)
                            Orders::set($row["id"], ['status_msg' => 'Reason for Cancellation: ' . Orders::$message]);

                        Events::set($processing["id"], ['status' => "approved"]);
                    }
                }
            }
            if ($rows_x) {
                foreach ($rows_x as $row) {
                    if ($changes > 0) break;
                    if (strlen($row["status_msg"]) > 0) continue;
                    $user_info = User::getInfo($row["user_id"], ['never_cancel']);
                    if (isset($user_info['never_cancel']) && $user_info['never_cancel']) continue;
                    if ($row["status"] == "cancelled") continue;

                    $ev_data = [
                        'type'   => "processing",
                        'owner'  => "cronjob",
                        'name'   => "order-cancel",
                        'status' => "pending",
                        'cdate'  => DateManager::Now(),
                        'data'   => [
                            'type' => "order",
                            'id'   => $row["id"],
                        ],
                    ];

                    if ($processing) Events::set($processing["id"], $ev_data);
                    else {
                        $processing_id = Events::create($ev_data);
                        $processing = Events::get($processing_id);
                    }

                    $result = Orders::MakeOperation("cancelled", $row["id"]);

                    if ($result) {
                        User::addAction(0, "alteration", "cancelled-order", [
                            'id'   => $row["id"],
                            'name' => $row["name"],
                        ]);
                        $changes++;
                    } elseif (Orders::$message)
                        Orders::set($row["id"], ['status_msg' => 'Reason for Cancellation: ' . Orders::$message]);

                    Events::set($processing["id"], ['status' => "approved"]);
                }
            }
            if ($changes) User::addAction(0, "cronjobs", "orders-has-been-cancelled", ['count' => $changes]);
            return !$changes > 0;
        }

        private function order_terminate($data = [])
        {
            Helper::Load(["User", "Orders", "Events", "Money", "Notification"]);
            $settings = $data["settings"];
            $day = $settings["day"];
            $hour = isset($settings["hour"]) ? $settings["hour"] : 6;
            $changes = 0;


            $processing = Events::isCreated('processing', 'cronjob', 0, 'order-terminate', false, 0, true);
            if ($processing) {
                $p_data = Utility::jdecode($processing["data"], true);
                if ($p_data && $processing["status"] == "pending") {
                    if ($p_data["type"] == "order")
                        $order = Orders::get($p_data["id"], 'status,options,server_terminated');
                    elseif ($p_data["type"] == "addon")
                        $order = Orders::get_addon($p_data["id"], 'status');
                    else
                        $order = false;

                    $wait_time = DateManager::strtotime(DateManager::next_date([
                        $processing["cdate"],
                        'minute' => 5,
                    ]));
                    if (DateManager::strtotime() > $wait_time) {
                        if ($p_data["type"] == "order") {

                            if ($order && $order["server_terminated"] == 0 && isset($order["options"]["config"]) && $order["options"]["config"])
                                Orders::set($p_data["id"], ['status_msg' => 'Suspension or cancellation failed. If the operation has already been performed, mark this warning as read.']);
                        }
                        Events::set($processing["id"], ['status' => "approved"]);
                    } else {
                        if ($order && ($order["server_terminated"] == 1 || !isset($order["options"]["config"]) || !$order["options"]["config"]))
                            Events::set($processing["id"], ['status' => "approved"]);
                        else
                            return false;
                    }
                }
            }

            $orders = $this->model->delayed_orders("order", $day, $hour, "cancelled", "terminate");

            if ($orders) {
                foreach ($orders as $order) {
                    if ($changes >= $this->process_limit) break;
                    $isErrorMsg = Events::isCreated("operation", "order", $order["id"], "order-terminate-error", "pending");

                    if ($order["server_terminated"] == 1 || strlen($order["status_msg"]) > 0 || $isErrorMsg) continue;
                    if ($order["module"] != "none") {

                        $ev_data = [
                            'type'   => "processing",
                            'owner'  => "cronjob",
                            'name'   => "order-terminate",
                            'status' => "pending",
                            'cdate'  => DateManager::Now(),
                            'data'   => [
                                'type' => "order",
                                'id'   => $order["id"],
                            ],
                        ];

                        if ($processing) Events::set($processing["id"], $ev_data);
                        else {
                            $processing_id = Events::create($ev_data);
                            $processing = Events::get($processing_id);
                        }

                        $result = Orders::ModuleHandler(Orders::get($order["id"]), false, "terminate");
                        if (!$result || $result == "failed")
                            Events::create([
                                'type'     => "operation",
                                'owner'    => "order",
                                'owner_id' => $order["id"],
                                'name'     => "order-terminate-error",
                                'data'     => [
                                    'message' => Orders::$message,
                                ],
                            ]);
                        $changes++;
                        Events::set($processing["id"], ['status' => "approved"]);
                        Orders::set($order["id"], ['server_terminated' => 1, 'terminated_date' => DateManager::Now()]);
                    }
                }
            }
            if ($changes) User::addAction(0, "cronjobs", "orders-has-been-terminated", ['count' => $changes]);
            return !$changes > 0;
        }

        private function pending_order_deletion($data = [])
        {
            Helper::Load(["User", "Orders", "Events", "Money", "Notification"]);
            $settings = $data["settings"];
            $day = $settings["day"];
            $changes = 0;

            $orders = $this->model->pending_orders("order", $day);
            $addons = $this->model->pending_orders("addon", $day);
            $rows = array_merge($orders, $addons);

            if ($rows) {
                foreach ($rows as $row) {
                    $order_type = isset($row["addon_id"]) ? "addon" : "order";
                    if ($order_type == "addon") {
                        Orders::MakeOperationAddon('delete', $row["owner_id"], $row["id"]);
                        $changes++;
                    } elseif ($order_type == "order") {
                        Orders::MakeOperation("delete", $row["id"]);
                        $changes++;
                    }
                }
            }
            if ($changes) User::addAction(0, "cronjobs", "pending-orders-has-been-deleted", ['count' => $changes]);
            return true;
        }

        private function pending_order_cancellation($data = [])
        {
            Helper::Load(["User", "Orders", "Events", "Money", "Notification"]);
            $settings = $data["settings"];
            $day = $settings["day"];
            $changes = 0;

            $orders = $this->model->pending_orders("order", $day);
            $addons = $this->model->pending_orders("addon", $day);
            $rows = array_merge($orders, $addons);

            if ($rows) {
                foreach ($rows as $row) {
                    $order_type = isset($row["addon_id"]) ? "addon" : "order";
                    if ($order_type == "addon") {
                        Orders::set_addon($row["id"], ['status' => "cancelled", 'unread' => 1]);
                        $changes++;
                    } elseif ($order_type == "order") {
                        Orders::MakeOperation("cancelled", $row["id"]);
                        $changes++;
                    }
                }
            }
            if ($changes) User::addAction(0, "cronjobs", "Pending orders have been canceled", ['count' => $changes]);
            return true;
        }

        private function domain_pending_transfer($data = [])
        {
            Helper::Load(["User", "Orders", "Events", "Money", "Notification"]);
            $changes = 0;

            $rows = $this->model->pending_domain_transfer_orders();
            if ($rows) {
                foreach ($rows as $row) {
                    $options = Utility::jdecode($row["options"], true);
                    $module_name = $row["module"];
                    Modules::Load("Registrars", $module_name);
                    $module = new $module_name();
                    if (method_exists($module, 'set_order')) $module->set_order(Orders::get($row["id"]));
                    $result = $module->transfer_sync($options);
                    if ($result) {
                        $status = isset($result["status"]) ? $result["status"] : '';
                        $ctime = isset($result["creationtime"]) ? $result["creationtime"] : '';
                        $endtime = isset($result["endtime"]) ? $result["endtime"] : '';

                        if ($status == "active") {
                            $set_params = [
                                'duedate' => $endtime,
                            ];

                            if ($ctime) $set_params["cdate"] = $ctime;

                            Orders::set($row["id"], $set_params);
                            $set_active = Orders::MakeOperation("active", $row["id"], false, false, false);
                            if (!$set_active)
                                Orders::set($row["id"], ['status_msg' => strlen(Orders::$message) > 1 ? Orders::$message : 'The transferred domain name is not activated.']);

                            Notification::domain_transferred($row["id"]);
                            $changes++;
                            $event = Events::isCreated("operation", "order", $row["id"], "transfer-request-to-us-with-api", "pending");
                            if ($event) Events::approved($event);
                            Hook::run("DomainTransferCompleted", Orders::get($row["id"]));
                        }
                    } else {
                        Orders::set($row["id"], ['status_msg' => $module->error]);
                        Hook::run("DomainTransferFailed", Orders::get($row["id"]));
                    }
                }
            }
            if ($changes) User::addAction(0, "cronjobs", "pending-domain-transfer-orders-has-been-activated", ['count' => $changes]);
            return true;
        }

        private function domain_transfer_unlocked_check($data = [])
        {
            Helper::Load(["User", "Orders", "Events", "Money", "Notification"]);
            $changes = 0;

            $rows = $this->model->domain_transfer_unlocked_orders();

            if ($rows) {
                foreach ($rows as $row) {
                    $options = Utility::jdecode($row["options"], true);
                    $module_name = $row["module"];
                    Modules::Load("Registrars", $module_name);
                    $module = class_exists($module_name) ? new $module_name() : false;

                    $latest_update = $options["transferlock_latest_update"] ?? false;
                    if ($latest_update)
                        $delayed_day = DateManager::remaining_day(DateManager::Now("Y-m-d"), DateManager::format("Y-m-d", $latest_update));
                    else
                        $delayed_day = 1;

                    $result = $module && method_exists($module, 'sync') ? $module->sync($options) : false;

                    $status = (string) ($result["status"] ?? '');
                    if ($status == "transferred" || (!$result && $module->error)) {
                        Orders::set($row["id"], [
                            'status'  => "cancelled",
                            'unread'  => 0,
                            'status_msg' => "The domain name has been transferred elsewhere.",
                        ]);
                        $changes++;
                        continue;
                    }

                    if ($delayed_day >= 14) {
                        if ($module && method_exists($module, 'ModifyTransferLock')) $module->ModifyTransferLock($options, "enable");
                        $options["transferlock"] = true;
                        $options["transferlock_latest_update"] = DateManager::Now();
                        Orders::set($row["id"], [
                            'options' => Utility::jencode($options),
                        ]);
                    }

                    $changes++;
                }
            }

            if ($changes) User::addAction(0, "cronjobs", "domain-transfer-unlocked-orders-has-been-transfered", ['count' => $changes]);
            return true;
        }

        private function checking_order($data=[]){
            Helper::Load(["User","Orders","Events","Money","Notification"]);

            $rows   = Events::getList("checking","order",0,false,'pending');
            if($rows){
                foreach($rows AS $row){
                    $order          = Orders::get($row["owner_id"]);
                    $options        = $order["options"];

                    ## Checking Domain Activation Status START ##
                    if($row["name"] == "domain-activation-status"){
                        $module_name    = $order["module"];
                        if($module_name == "none") $module_name = false;

                        if(!$order || $order["status"] == "active" || $order["status"] == "cancelled" || !$module_name){
                            Events::approved($row["id"]);
                            continue;
                        }


                        Modules::Load("Registrars",$module_name);
                        $module         = new $module_name();

                        if(method_exists($module,"set_order")) $module->set_order($order);

                        $result         = $module->sync($options);
                        if($result){
                            $status     = isset($result["status"]) ? $result["status"] : '';
                            $endtime    = isset($result["endtime"]) ? $result["endtime"] : '';
                            if($status == "active"){
                                Orders::set($order["id"],['duedate' => $endtime]);
                                Orders::MakeOperation("active",$order["id"]);
                                Events::approved($row["id"]);
                            }
                        }else Orders::set($row["id"],['status_msg' => $module->error]);
                    }
                    ## Checking Domain Activation Status END ##

                }
            }
            return true;
        }

        private function ticket_close($data = [])
        {
            Helper::Load(["User", "Tickets", "Notification"]);
            $settings = $data["settings"];
            $day = $settings["day"];
            $changes = 0;
            $tickets = $this->model->replied_tickets($day);

            if ($tickets) {
                foreach ($tickets as $ticket) {
                    Tickets::set_request($ticket["id"], ['status' => "solved"]);
                    Notification::ticket_resolved_automatic($ticket["id"]);
                    $changes++;
                }
            }
            if ($changes) User::addAction(0, "cronjobs", "replied-tickets-has-been-resolved", ['count' => $changes]);
            return true;
        }

        private function ticket_lock($data = [])
        {
            Helper::Load(["User", "Tickets", "Notification"]);
            $settings = $data["settings"];
            $day = $settings["day"];
            $changes = 0;
            $tickets = $this->model->replied_tickets($day, true);

            if ($tickets) {
                foreach ($tickets as $ticket) {
                    Tickets::set_request($ticket["id"], ['locked' => "1"]);
                    $changes++;
                }
            }
            if ($changes) User::addAction(0, "cronjobs", "replied-tickets-has-been-locked", ['count' => $changes]);
            return true;
        }

        private function ticket_pipe()
        {
            if(!Config::get("options/ticket-pipe/status")) return true;

            Helper::Load(["Tickets","User","Notification","Events","Html2Text"]);

            $ticket_file_upload_dir         = ROOT_DIR.Config::get("pictures/attachment/folder");
            $ticket_allowed_ext             = explode(",",Config::get("options/attachment-extensions"));
            $ticket_file_max_size           = Config::get("options/attachment-max-file-size");
            $spam_control                   = Config::get("options/ticket-pipe/spam-control");
            $existing_client                = (int) Config::get("options/ticket-pipe/existing-client");

            $configDepartments              = Config::get("options/ticket-pipe/mail");
            $departments                    = Tickets::get_departments(false,'t1.id,t2.name');
            $first_dep_id                   = $departments ? $departments[0]["id"] : 0;
            $deps                           = [];
            $inbox                          = [];

            if($configDepartments) foreach($configDepartments AS $did => $d) if($d && trim($d["from"] ?? '')) $deps[trim($d["from"] ?? '')] = $did;

            $deps_addresses     = array_keys($deps);

            if(!$deps) return true;

            $dir_path                   = ROOT_DIR."temp".DS."pipe".DS;
            $server_inbox               = FileManager::glob($dir_path."*.txt");

            ## Receive mail from server ##
            if($server_inbox)
            {
                foreach($server_inbox AS $f)
                {
                    $read       = $this->mail_parse($this->msg_decrypt(FileManager::file_read($f)));

                    if(!$read)
                    {
                        FileManager::file_delete($f);
                        continue;
                    }

                    $date               = ($read["Headers"]["date"] ?? ($read["Headers"]["date:"] ?? ''));
                    $subject            = $read["Headers"]["subject:"] ?? "Unknown";
                    if(preg_match('/\w{3}, \d{1,2} \w{3} \d{4} \d{2}:\d{2}:\d{2} \+\d{4}/', $date, $date_matches))
                        $date = $date_matches[0];
                    $date = DateManager::format("Y-m-d H:i:s", $date);


                    if(isset($read["DecodedHeaders"]["subject:"][0][0]["Value"]))
                        $subject        = $read["DecodedHeaders"]["subject:"][0][0]["Value"];

                    if(!$subject) $subject = "Unknown";

                    $from_name          = $read["ExtractedAddresses"]["from:"][0]["name"] ?? 'Unknown';
                    $from_address       = $read["ExtractedAddresses"]["from:"][0]["address"] ?? '';

                    $to_name            = $read["ExtractedAddresses"]["to:"][0]["name"] ?? 'Unknown';
                    $to_address         = $read["ExtractedAddresses"]["to:"][0]["address"] ?? '';

                    $message            = NULL;
                    $attachments        = [];

                    if(!$from_name) $from_name = "Unknown";

                    if(!$from_address)
                    {
                        FileManager::file_delete($f);
                        continue;
                    }

                    $parts          = $read["Parts"] ?? [];

                    if(!$parts)
                    {
                        FileManager::file_delete($f);
                        continue;
                    }

                    foreach($parts AS $part)
                    {
                        if(stristr(($part["Headers"]["content-type:"] ?? ''),"text/plain")) $message = $part["Body"] ?? "";
                        if(stristr(($part["Headers"]["content-type:"] ?? ''),"multipart/alternative"))
                        {
                            if(isset($part["Parts"]))
                            {
                                foreach($part["Parts"] AS $p)
                                {
                                    if(stristr(($p["Headers"]["content-type:"] ?? ''),"text/plain")) $message = $p["Body"] ?? "";
                                }
                            }
                        }
                        elseif(stristr(($part["Headers"]["content-disposition:"] ?? ''),"attachment; filename"))
                        {
                            $file_name      = $part["FileName"] ?? '';
                            $rand_filename  = strtolower(substr(md5(uniqid(rand())), 0,23));
                            $ext_arr        = explode(".", $file_name);
                            $extension      = strtolower(array_pop($ext_arr));
                            $filename       = $rand_filename.".".$extension;
                            $file_size      = $part["BodyLength"];

                            $attachments[] = [
                                'file_name' => $file_name,
                                'name'      => $filename,
                                'file_ext'  => $extension,
                                'size'      => $file_size,
                                'content'   => base64_encode($part["Body"]),
                            ];
                        }
                    }

                    $ip_info            = ($read["Headers"]["received:"] ?? '');

                    if(is_array($ip_info)) $ip_info = current($ip_info);

                    preg_match('/\(\[(.*?)\]\:(.*?)\)/s',$ip_info, $ip_match);

                    $ip                 = $ip_match[1] ?? '';

                    FileManager::file_delete($f);

                    $data = [
                        'provider'      => "server",
                        'ip'            => $ip,
                        'date'          => $date,
                        'subject'       => $subject,
                        'spam'          => stristr(($read["Headers"]["x-spam-status:"] ?? 'No'),'Yes'),
                        'from'          => [
                            'name'      => $from_name,
                            'address'   => $from_address,
                        ],
                        'to'            => [
                            'name'      => $to_name,
                            'address'   => $to_address,
                        ],
                        'message'       => $message,
                        'attachments'   => $attachments,
                    ];

                    if($spam_control && $this->spam_check($data))
                    {
                        FileManager::file_delete($f);
                        continue;
                    }

                    $inbox[] = $data;
                }
            }
            ## Receive mail from server ##

            ## Receive mail from other providers
            if($configDepartments)
            {
                $available_providers = [];
                foreach($configDepartments AS $did => $d)
                {
                    $provider   = $d["provider"] ?? "server";
                    $from       = $d["from"] ?? '';
                    $fname      = $d["fname"] ?? '';
                    if($provider == "server" || Validation::isEmpty($from) || Validation::isEmpty($fname)) continue;
                    if(!in_array($provider,$available_providers)) $available_providers[] = $provider;
                }
                if($available_providers)
                {
                    foreach($available_providers AS $mk)
                    {
                        $module = Modules::Load("Pipe",$mk);
                        if(!$module) continue;

                        $act_time       = ($module["config"]["activation_date"] ?? false) ? DateManager::strtotime($module["config"]["activation_date"]) : 0;
                        $class_name         = "WISECP\\Modules\\Pipe\\".$mk;
                        if(!class_exists($class_name)) continue;
                        $init = new $class_name();
                        if(!method_exists($init,'inbox')) continue;
                        $messages   = $init->inbox();
                        if(!$messages) continue;
                        if(($messages["status"] ?? '') == 'successful' && $messages["data"] ?? [])
                        {
                            foreach($messages["data"] AS $row)
                            {
                                $mail_time      = DateManager::strtotime($row["date"] ?? '');
                                if($act_time > 0 && $mail_time < $act_time) continue;
                                $row["provider"] = $mk;
                                if($spam_control && $this->spam_check($row)) continue;
                                $inbox[] = $row;
                            }
                        }
                    }
                }
            }

            if($inbox)
            {
                $prefix     = Config::get("options/ticket-pipe/prefix");
                if(!$prefix) $prefix = "REF";

                foreach($inbox AS $row)
                {
                    $date           = $row["date"] ?: DateManager::Now();
                    $ip             = $row["ip"] ?: UserManager::GetIP();
                    $subject        = $row["subject"];
                    $message        = $row["message"];
                    $from_address   = $row["from"]["address"] ?? '';
                    $from_name      = $row["from"]["name"] ?? '';
                    $to_address     = $row["to"]["address"] ?? '';
                    $to_name        = $row["to"]["name"] ?? '';
                    $attachments    = $row["attachments"] ?? [];

                    if(stristr($date,'0000') || Validation::isEmpty($date)) $date = DateManager::Now();

                    preg_match('/\['.preg_quote($prefix.":").'(.*?)\]/s', $subject, $matches);
                    $refnum          = Filter::letters_numbers($matches[1] ?? 0,'-');

                    $check_id           = WDB::select("id")->from("tickets")->where("refnum","=",$refnum);
                    if($check_id->build())
                        $ticket_id = $check_id->getObject()->id;
                    else
                        $ticket_id = 0;

                    if($ticket_id && !Tickets::get_request($ticket_id,'id'))
                    {
                        $ticket_id      = 0;
                        $subject_parse  =  explode("[".$prefix,$subject);
                        $subject        = $subject_parse[0] ?? 'Untitled';
                    }

                    $client_email       = NULL;
                    $client_name        = NULL;


                    if(preg_match('/<[^>]+>/', $message))
                    {
                        try {
                            $message = new Html2Text($message,['width' => 0]);
                            $message = trim(htmlentities($message->getText()));
                        }
                        catch (Exception $e)
                        {
                            $message = preg_replace('/<head>(.*?)<\/head>/si', '', $message);
                            $message = preg_replace('/<\s*(p|div|span)[^>]*>\s*<\/\1>/', '', $message);
                            $message = preg_replace('/<div[^>]*>\s*/i', '<br>', $message);
                            $message = preg_replace('/\s*<\/div>/i', '', $message);
                            $message = preg_replace('/(<[^>]+) (on\w+)=([^>]*>)/i', '$1$3', $message);
                            $message = preg_replace('/(<[^>]+) style=([^>]*>)/i', '$1$2', $message);
                            $message = preg_replace('/(<[^>]+) style="[^"]*background-image\s*:\s*url\([^\)]*\)[^"]*"/i', '$1', $message);
                            $message = strip_tags($message, '<p><br><b><i><u><strong><em><s><ol><ul><li>');
                            $message = preg_replace('/(<br\s*\/?>\s*){2,}/', '<br>', $message);
                            $message = preg_replace('/\s+/', ' ', $message);
                            $message = trim($message);
                            $message = preg_replace('/^(<br[^>]*>)+|(<br[^>]*>)+$/i', '', $message);
                            $message = preg_replace('/<br\s*clear="all"\s*\/?>/i', '', $message);

                        }
                    }

                    if(in_array($from_address,$deps_addresses))
                    {
                        $department_id      = $deps[$from_address] ?? $first_dep_id;
                        $department_eml     = $from_address;
                        $client_email       = $to_address;
                        $client_name        = $to_name;
                    }
                    elseif(in_array($to_address,$deps_addresses))
                    {
                        $department_id      = $deps[$to_address] ?? $first_dep_id;
                        $client_email       = $from_address;
                        $client_name        = $from_name;
                    }
                    else
                    {
                        $department_id      = $first_dep_id;
                        $client_email       = $from_address;
                        $client_name        = $from_name;
                    }

                    $message = implode("\n", array_filter(explode("\n", $message), function($line){
                        return trim($line) === '' || !(str_starts_with($line, '&gt;') || str_starts_with($line, '>'));
                    }));

                    $message = implode("\n", array_filter(explode("\n", $message), function($line) use ($from_name, $from_address)
                    {
                        $email_html_encoded = '&lt;' . $from_address . '&gt;';
                        $email_normal = '<' . $from_address . '>';
                        return !(stripos($line, $from_name) !== false &&
                            (stripos($line, $email_html_encoded) !== false || stripos($line, $email_normal) !== false));
                    }));

                    $message = implode("\n", array_filter(explode("\n", $message), function($line) use ($to_name, $to_address)
                    {
                        $email_html_encoded = '&lt;' . $to_address . '&gt;';
                        $email_normal = '<' . $to_address . '>';
                        return !(stripos($line, $to_name) !== false &&
                            (stripos($line, $email_html_encoded) !== false || stripos($line, $email_normal) !== false));
                    }));

                    $message_parse = explode("-------------------------",$message);
                    $message        = $message_parse[0];


                    $message = trim($message);


                    $owner_domain       = strtolower(str_replace("www.","",Utility::getDomain()));
                    $owner_email        = "pipe@".$owner_domain;

                    $pipe_client_id       = User::email_check($owner_email);

                    if(!$pipe_client_id)
                        $pipe_client_id    = User::create([
                            'type'              => "member",
                            'name'              => "Piping",
                            'surname'           => "System",
                            'full_name'         => "Piping System",
                            'email'             => $owner_email,
                            'password'          => "NA",
                            'lang'              => Config::get("general/local"),
                            'country'           => AddressManager::get_id_with_cc(Config::get("general/country")),
                            'currency'          => Config::get("general/currency"),
                            'balance_currency'  => Config::get("general/currency"),
                            'creation_time'     => DateManager::Now(),
                            'last_login_time'   => DateManager::Now(),
                        ]);

                    $client_id      = User::email_check($client_email);

                    if($existing_client == 0 || ($existing_client == 1 && !$client_id))
                        $client_id = $pipe_client_id;
                    elseif($existing_client == 2 && !$client_id)
                    {
                        User::addAction(0,"failed","There was a problem with the email pipe, the ticket could not be created because the Client ID could not be determined.",[
                            'subject' => $subject,
                            'client_email' => $client_email,
                        ]);
                        continue;
                    }

                    $created        = false;

                    if($ticket_id)
                    {
                        $ticket         = Tickets::get_request($ticket_id);

                        if($client_id != $ticket["user_id"])
                        {
                            User::addAction(0,"failed","A problem occurred on the email pipe, the specified Client ID and the client id of the support ticket do not match.",[
                                'subject' => $subject,
                                'client_email' => $client_email,
                                'client_id'    => $client_id,
                                'ticket_client_id' => $ticket["user_id"],
                            ]);
                            continue;
                        }

                        $client_id      = $ticket["user_id"];

                        if($ticket["assigned"])
                            Events::create([
                                'user_id' => $ticket["assigned"],
                                'type'    => "info",
                                'owner'   => "tickets",
                                'owner_id' => $ticket_id,
                                'name'     => "ticket-replied-by-user",
                                'data'     => [
                                    'subject' => $ticket["title"],
                                ],
                            ]);


                        $set_request    = [
                            'status' => "waiting",
                            'lastreply' => DateManager::Now(),
                            'userunread' => 1,
                            'adminunread' => 0,
                        ];

                        if(!$ticket["pipe"])
                        {
                            $set_request["pipe"]    = 1;
                            $set_request["name"]    = $client_name;
                            $set_request["email"]   = $client_email;
                        }

                        Tickets::set_request($ticket_id,$set_request);
                    }
                    else
                    {
                        $created            = true;
                        $ticket_id          = Tickets::insert_request([
                            'did'           => $department_id,
                            'user_id'       => $client_id,
                            'status'        => "waiting",
                            'priority'      => 2,
                            'title'         => $subject,
                            'ctime'         => $date,
                            'lastreply'     => $date,
                            'userunread'    => 1,
                            'pipe'          => 1,
                            'name'          => $client_name,
                            'email'         => $client_email,
                        ]);
                        $ticket             = Tickets::get_request($ticket_id);
                    }

                    $reply_id         = Tickets::insert_reply([
                        'user_id'       => $client_id,
                        'owner_id'      => $ticket_id,
                        'name'          => $client_name,
                        'message'       => $message,
                        'pipe'          => 1,
                        'ctime'         => $date,
                        'ip'            => $ip,
                    ]);

                    if($attachments && is_array($attachments))
                    {
                        foreach($attachments AS $ope)
                        {
                            $ope['file_path'] = DateManager::Now("Y-m-d")."/".$ope["name"];

                            if(!in_array(".".($ope["file_ext"] ?? 'zip'),$ticket_allowed_ext)) continue;
                            if(($ope["size"] ?? 0) > $ticket_file_max_size) continue;

                            if(!file_exists($ticket_file_upload_dir.DateManager::Now("Y-m-d")))
                            {
                                mkdir($ticket_file_upload_dir.DateManager::Now("Y-m-d"),0777,true);
                                touch($ticket_file_upload_dir.DateManager::Now("Y-m-d").DS."index.html");
                            }

                            FileManager::file_write($ticket_file_upload_dir.$ope["file_path"],base64_decode($ope["content"]));

                            Tickets::addAttachment([
                                'ticket_id' => $ticket_id,
                                'reply_id'  => $reply_id,
                                'user_id'   => $client_id,
                                'name'      => $ope["name"],
                                'file_path' => $ope["file_path"],
                                'file_name' => $ope["file_name"],
                                'file_size' => $ope["size"],
                                'ctime'     => $date,
                                'ip'        => $ip,
                            ]);
                        }
                    }

                    if($created)
                        Notification::ticket_has_been_created_by_user($ticket_id);
                    else
                        Notification::ticket_replied_by_user($ticket_id);

                }
            }

            return false;
        }

        private function ticket_tasks()
        {
            Helper::Load(["Tickets", "Notification"]);

            $tasks = $this->model->db->select()->from("tickets_tasks");
            $tasks = $tasks->build() ? $tasks->fetch_assoc() : [];
            $now = new DateTime();

            $admin = $this->model->db->select("id,full_name")->from("users");
            $admin->where("type", "=", "admin", "&&");
            $admin->where("status", "=", "active");
            $admin->order_by("id ASC");
            $admin->limit(1);
            $admin = $admin->build() ? $admin->getObject() : false;

            $notification_functions = [
                'ticket-resolved-automatic'        => 'ticket_resolved_automatic',
                'ticket-replied-by-admin'          => 'ticket_replied_by_admin',
                'ticket-replied-by-admin-pipe'     => 'ticket_replied_by_admin_pipe',
                'ticket-has-been-created-by-admin' => 'ticket_has_been_created_by_admin',
                'ticket-your-has-been-created'     => 'ticket_your_has_been_created',
                'ticket-your-has-been-processed'   => 'ticket_your_has_been_processed',
                'ticket-resolved-by-admin'         => 'ticket_resolved_by_admin',
                'ticket-assigned-to-you'           => 'ticket_assigned_to_you',
                'ticket-resolved-by-user'          => 'ticket_resolved_by_user',
                'ticket-replied-by-user'           => 'ticket_replied_by_user',
                'ticket-has-been-created-by-user'  => 'ticket_has_been_created_by_user',
            ];


            if ($tasks) {
                foreach ($tasks as $task) {
                    $departments = $task["departments"] ?? '';
                    $statuses = $task["statuses"] ? explode(",", $task["statuses"] ?? '') : [];
                    $priorities = $task["priorities"] ?? '';
                    $delay_time = (int)Filter::numbers($task["delay_time"]);
                    if ($delay_time < 1) $delay_time = 1;

                    $tickets = $this->model->db->select()->from("tickets");

                    if ($departments) $tickets->where(sprintf("FIND_IN_SET(did,'%s')", $departments), "", "", "&&");

                    if ($priorities) $tickets->where(sprintf("FIND_IN_SET(priority,'%s')", $priorities), "", "", "&&");

                    if ($statuses) {
                        $statuses_length = sizeof($statuses) - 1;
                        $tickets->where("(");
                        foreach ($statuses as $k => $s) {
                            $endor = $k == $statuses_length ? '' : '||';
                            $s_split = explode("-", $s);
                            $s = $s_split[0] ?? $s;
                            $s_id = $s_split[1] ?? 0;

                            if ($s_id) {
                                $tickets->where("(");
                                $tickets->where("status", "=", $s, "&&");
                                $tickets->where("cstatus", "=", $s_id);
                                $tickets->where(")", "", "", $endor);
                            } else
                                $tickets->where("status", "=", $s, $endor);

                        }
                        $tickets->where(")", "", "", "&&");
                    }

                    if ($task["department"])
                        $tickets->where("did", "!=", $task["department"], "&&");

                    if ($task["status"]) {
                        $status_split = explode("-", $task["status"]);
                        $status = $status_split[0] ?? $task["status"];
                        $status_id = $status_split[1] ?? 0;

                        if ($status_id)
                            $tickets->where("cstatus", "!=", $status_id, "&&");
                        else
                            $tickets->where("status", "!=", $status, "&&");
                    }

                    if ($task["priority"])
                        $tickets->where("priority", "!=", $task["priority"], "&&");


                    if ($task["assign_to"])
                        $tickets->where("assigned", "!=", $task["assign_to"], "&&");

                    if ($task["mark_locked"])
                        $tickets->where("locked", "!=", "1", "&&");

                    $tickets->where("id", "!=", "0");
                    $tickets->order_by("id DESC");
                    $tickets->limit(1);
                    $tickets = $tickets->build() ? $tickets->fetch_assoc() : [];

                    if ($tickets) {
                        foreach ($tickets as $ticket) {
                            $lastReplyDate = new DateTime($ticket["lastreply"]);
                            $diff = $now->diff($lastReplyDate);
                            $minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;

                            if ($minutes >= $delay_time) {
                                $check = $this->model->db->select("id")->from("tickets_tasks_logs");
                                $check->where("owner_id", "=", $task["id"], "&&");
                                $check->where("ticket_id", "=", $ticket["id"]);
                                $check = $check->build() ? $check->getObject()->id : 0;

                                if (!$check || $task["repeat_action"]) {
                                    $update_ticket = [];

                                    if ($task["department"])
                                        $update_ticket["did"] = $task["department"];

                                    if ($task["status"]) {
                                        $update_ticket["status"] = $status ?? $ticket["status"];
                                        $update_ticket["cstatus"] = $status_id ?? 0;
                                    }

                                    if ($task["priority"])
                                        $update_ticket["priority"] = $task["priority"];

                                    if ($task["assign_to"])
                                        $update_ticket["assign"] = $task["assign_to"];


                                    // add reply
                                    if ($task["reply"]) {
                                        $u = User::getData($ticket["user_id"], 'id,name,surname,full_name,email,phone,lang', 'assoc');
                                        $ulang = $u["lang"] ?? Config::get("general/local");
                                        $lreply = Utility::jdecode($task["reply"], true);
                                        $dreply = current($lreply);
                                        $reply = $lreply[$ulang] ?? $dreply;

                                        $service = $ticket["service"] > 0 ? Orders::get($ticket["service"]) : [];
                                        $domain = $service ? ($service["options"]["domain"] ?? $service["name"]) : '';


                                        $reply = Utility::text_replace($reply, [
                                            '{FULL_NAME}' => $u["full_name"] ?? '',
                                            '{NAME}'      => $u["name"] ?? '',
                                            '{SURNAME}'   => $u["surname"] ?? '',
                                            '{EMAIL}'     => $u["email"] ?? '',
                                            '{PHONE}'     => ($u["phone"] ?? '') ? "+" . $u["phone"] : '',
                                            '{SERVICE}'   => $service["name"] ?? '',
                                            '{DOMAIN}'    => $domain,
                                        ]);

                                        $set_reply = [
                                            'user_id'  => $admin->id,
                                            'owner_id' => $ticket["id"],
                                            'name'     => Bootstrap::$lang->get_cm("website/cronjobs/auto-message", false, $ulang),
                                            'message'  => $reply,
                                            'admin'    => 1,
                                            'ctime'    => DateManager::Now(),
                                            'ip'       => UserManager::GetIP(),
                                        ];

                                        $send_reply = Tickets::insert_reply($set_reply);

                                        User::addAction($admin->id, "alteration", "reply-ticket-request", ['id' => $ticket["id"]]);

                                        $update_ticket["lastreply"] = DateManager::Now();
                                        $update_ticket["userunread"] = 0;
                                        $update_ticket["adminunread"] = 0;
                                    }

                                    // update ticket
                                    if ($update_ticket)
                                        Tickets::set_request($ticket["id"], $update_ticket);

                                    // add notification
                                    if ($task["template"]) {
                                        $template_split = explode("/", $task["template"]);
                                        $template_group = $template_split[0];
                                        $template_name = $template_split[1];
                                        $notification = Config::get("notifications/" . $task["template"]);

                                        if ($notification) {
                                            if ($notification['custom'])
                                                Notification::send([
                                                    'template'  => $template_name,
                                                    'user_id'   => $u["id"],
                                                    'variables' => Tickets::get_request($ticket["id"]),
                                                ]);
                                            else
                                                Notification::{$notification_functions[$template_name]}($ticket);
                                        }
                                    }

                                    // add Hook
                                    if (isset($update_ticket["lastreply"]) && isset($send_reply))
                                        Hook::run("TicketAdminReplied", [
                                            'source'  => "cron",
                                            'request' => $ticket,
                                            'reply'   => Tickets::get_reply($send_reply),
                                        ]);

                                    // add tickets_tasks_logs
                                    $this->model->db->insert("tickets_tasks_logs", [
                                        'owner_id'   => $task["id"],
                                        'ticket_id'  => $ticket["id"],
                                        'created_at' => DateManager::Now(),
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
            return true;
        }

        private function clear_logs($data = [])
        {
            $settings = $data["settings"];
            $day = $settings["day"];

            $deleted_actions = $this->model->delete_actions($day);
            $this->model->delete_login_log($day);
            $this->model->delete_checkouts(10);

            if (file_exists(ROOT_DIR . "backup")) {
                $files = [];
                $files = array_merge($files, FileManager::glob(ROOT_DIR . "backup" . DS . "*.zip"));
                $files = array_merge($files, FileManager::glob(ROOT_DIR . "backup" . DS . "*.sql"));
                if ($files) {
                    foreach ($files as $file) {
                        $f_date = DateManager::timetostr("Y-m-d H:i:s", filemtime($file));
                        $days = DateManager::diff_day($f_date, DateManager::Now());
                        if ($days >= 10) FileManager::file_delete($file);
                    }
                }
            }


            if ($deleted_actions) User::addAction(0, "cronjobs", "actions-has-been-deleted");
            return true;
        }

        private function clear_notifications($data = [])
        {
            $settings = $data["settings"];
            $day = $settings["day"];

            $deleted_notifications = $this->model->delete_notifications($day);
            $this->model->delete_spam_notifications();


            if ($deleted_notifications) User::addAction(0, "cronjobs", "notifications-has-been-deleted");

            return true;
        }

        private function unverified_accounts_deletion($data = [])
        {
            Helper::Load(["User"]);
            $settings = $data["settings"];
            $day = $settings["day"];
            $everify = Config::get("options/sign/up/email/verify");
            $gverify = Config::get("options/sign/up/gsm/verify");
            $changes = 0;

            $users = $this->model->unverified_accounts($day, $everify, $gverify);

            if ($users) {
                foreach ($users as $user) {
                    User::delete($user["id"]);
                    $changes++;
                }
            }

            if ($changes) User::addAction(0, "cronjobs", "unverified-accounts-has-been-deleted", [
                'count' => $changes,
            ]);
            return true;
        }

        private function non_order_accounts_deletion($data = [])
        {
            Helper::Load(["User"]);
            $settings = $data["settings"];
            $day = $settings["day"];
            $changes = 0;

            $users = $this->model->non_order_accounts($day);

            if ($users) {
                foreach ($users as $user) {
                    User::delete($user["id"]);
                    $changes++;
                }
            }
            if ($changes) User::addAction(0, "cronjobs", "non-order-accounts-has-been-deleted", [
                'count' => $changes,
            ]);
            return true;
        }

        private function low_balance_remind($data = [])
        {
            Helper::Load(["User", "Notification"]);
            $changes = 0;
            $users = $this->model->low_balance_accounts();
            $day = $data["settings"]["day"];
            if ($users) {
                foreach ($users as $user) {
                    $u_day = $user["delayed_day"];
                    if ($u_day === null || $u_day >= $day) {
                        if (Notification::credit_fell_below_a_minimum($user["id"])) $changes++;
                    }
                }
            }
            if ($changes) User::addAction(0, "cronjobs", "low-balance-accounts-has-been-reminded");
            return true;
        }

        private function clear_storage_logs($data = [])
        {

            $files = [];
            $files = array_merge($files, FileManager::glob(ROOT_DIR . "temp" . DS . "*"));
            $files = array_merge($files, FileManager::glob(STORAGE_DIR . "logs" . DS . "database" . DS . "*"));
            $files = array_merge($files, FileManager::glob(STORAGE_DIR . "logs" . DS . "system" . DS . "*"));

            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $name = basename($file);
                        if ($name == "index.html") continue;
                        FileManager::file_delete($file);
                    }
                }
            }

            $statistics = FileManager::glob(STORAGE_DIR . "statistics" . DS . "*.txt");
            if ($statistics) {
                $now = DateManager::Now("Y-m-d");
                foreach ($statistics as $file) {
                    if (!stristr($file, $now) || basename($file) != "index.html")
                        FileManager::file_delete($file);
                }
            }
            $this->model->delete_events();

            $this->model->clear_module_logs();


            self::$cache->clear();

            return true;
        }

        private function reminding($data = [])
        {

            $now = DateManager::Now("Y-m-d H:i");
            $parse = explode(" ", $now);
            $parse_date = explode("-", $parse[0]);
            $parse_time = explode(":", $parse[1]);
            $date = $parse_date[0] . "-" . $parse_date[1] . "-" . $parse_date[2];
            $year = (int)$parse_date[0];
            $month = (int)$parse_date[1];
            $day = (int)$parse_date[2];
            $time = $parse_time[0] . ":" . $parse_time[1];
            $hour = (int)$parse_time[0];
            $minute = (int)$parse_time[1];
            $affected = 0;

            Helper::Load(["Notification", "User"]);

            $waiting_reminders = $this->model->get_reminders("waiting");
            $past_reminders = $this->model->get_reminders("past");

            $waiting_reminders2 = $this->model->get_notification_reminders("waiting");
            $past_reminders2 = $this->model->get_notification_reminders("past");


            if ($past_reminders) {
                foreach ($past_reminders as $row) {
                    Notification::remind($row);
                    $affected++;
                    $this->model->db->update("users_reminders", [
                        'status' => "inactive",
                    ])->where("id", "=", $row["id"])->save();
                }
            }
            if ($waiting_reminders) {
                foreach ($waiting_reminders as $row) {
                    $period = $row["period"];

                    if ($row["period_hour"] == $hour && $minute == $row["period_minute"]) {
                        $period_date = $date;
                        $period_time = $row["period_hour"] > 9 ? $row["period_hour"] : "0" . $row["period_hour"];
                        $period_time .= ":";
                        $period_time .= $row["period_minute"] > 9 ? $row["period_minute"] : "0" . $row["period_minute"];
                        $period_time .= ":00";


                        $check_log = $this->model->db->select()->from("users_reminders_logs");
                        $check_log->where("owner_id", "=", $row["id"], "&&");
                        $check_log->where("reminding_date", "=", $period_date, "&&");
                        $check_log->where("reminding_time", "LIKE", "%" . substr($period_time, 0, -3) . "%");
                        $check_log = $check_log->build();
                        if (!$check_log) {
                            Notification::remind($row);
                            $affected++;

                            if ($period == "onetime")
                                $this->model->db->update("users_reminders", [
                                    'status' => "inactive",
                                ])->where("id", "=", $row["id"])->save();


                            $this->model->db->insert("users_reminders_logs", [
                                'owner_id'       => $row["id"],
                                'reminding_date' => $period_date,
                                'reminding_time' => $period_time,
                            ]);
                        }
                    }

                }
            }

            if ($past_reminders2) {
                foreach ($past_reminders2 as $row) {
                    Notification::remind_template($row);
                    $affected++;

                    $period_datetime = $row["period_datetime"];
                    $period_datetime = explode(" ", $period_datetime);
                    $period_date = $period_datetime[0];
                    $period_time = $period_datetime[1];

                    $this->model->db->insert("notification_templates_logs", [
                        'owner_id'       => $row["id"],
                        'reminding_date' => $period_date,
                        'reminding_time' => $period_time,
                    ]);


                }
            }
            if ($waiting_reminders2) {
                foreach ($waiting_reminders2 as $row) {
                    $period = $row["period"];

                    if ($row["period_hour"] == $hour && $minute == $row["period_minute"]) {
                        $period_date = $date;
                        $period_time = $row["period_hour"] > 9 ? $row["period_hour"] : "0" . $row["period_hour"];
                        $period_time .= ":";
                        $period_time .= $row["period_minute"] > 9 ? $row["period_minute"] : "0" . $row["period_minute"];
                        $period_time .= ":00";


                        $check_log = $this->model->db->select()->from("notification_templates_logs");
                        $check_log->where("owner_id", "=", $row["id"], "&&");
                        $check_log->where("reminding_date", "=", $period_date, "&&");
                        $check_log->where("reminding_time", "LIKE", "%" . substr($period_time, 0, -3) . "%");
                        $check_log = $check_log->build();
                        if (!$check_log) {
                            Notification::remind_template($row);
                            $affected++;

                            $this->model->db->insert("notification_templates_logs", [
                                'owner_id'       => $row["id"],
                                'reminding_date' => $period_date,
                                'reminding_time' => $period_time,
                            ]);
                        }
                    }

                }
            }

            if ($affected) User::addAction(0, "cron", "reminders-has-been-reminded", ['count' => $affected]);
            return true;
        }

        private function periodic_outgoings($data = [])
        {

            $now = DateManager::Now("Y-m-d H:i");
            $parse = explode(" ", $now);
            $parse_date = explode("-", $parse[0]);
            $parse_time = explode(":", $parse[1]);
            $date = $parse_date[0] . "-" . $parse_date[1] . "-" . $parse_date[2];
            $year = (int)$parse_date[0];
            $month = (int)$parse_date[1];
            $day = (int)$parse_date[2];
            $time = $parse_time[0] . ":" . $parse_time[1];
            $hour = (int)$parse_time[0];
            $minute = (int)$parse_time[1];

            Helper::Load(["Invoices", "User"]);

            $rows = $this->model->get_periodic_outgoings();
            $added = 0;

            if ($rows) {
                foreach ($rows as $row) {
                    $period_minute = (int)$row["period_minute"];
                    $period_minute_max = $period_minute + 5;

                    if ($row["period_hour"] == $hour && ($minute >= $period_minute && $minute <= $period_minute_max)) {

                        $cdate = $year . "-";
                        $cdate .= $month > 9 ? $month : "0" . $month;
                        $cdate .= "-";
                        $cdate .= $day > 9 ? $day : "0" . $day;
                        $cdate .= " ";
                        $cdate .= $hour > 9 ? $hour : "0" . $hour;
                        $cdate .= ":";
                        $cdate .= $period_minute > 9 ? $period_minute : "0" . $period_minute;

                        $check_insert = $this->model->db->select("id")->from("income_expense");
                        $check_insert->where("type", "=", "expense", "&&");
                        $check_insert->where("description", "=", $row["description"], "&&");
                        $check_insert->where("cdate", "LIKE", "%" . $cdate . "%", "&&");
                        $check_insert->where("amount", "=", $row["amount"], "&&");
                        $check_insert->where("currency", "=", $row["currency"]);
                        $check_insert = $check_insert->build();
                        if (!$check_insert) {
                            Invoices::insert_inex([
                                'type'        => "expense",
                                'amount'      => $row["amount"],
                                'currency'    => $row["currency"],
                                'cdate'       => $cdate,
                                'description' => $row["description"],
                            ]);
                            $added++;
                        }
                    }
                }
            }

            if ($added)
                User::addAction(0, "added", "auto-periodic-outgoing-in-cash-added", [
                    'count' => $added,
                ]);


            return true;
        }

        private function scheduled_operations($data = [])
        {
            Helper::Load(["User", "Orders", "Products", "Events", "Money", "Notification"]);

            $rows = Events::getList("scheduled-operations", false, 0, false, 'pending');
            $rows = array_merge($rows, Events::getList("scheduled-operations", false, 0, false, 'error'));

            if ($rows) foreach ($rows as $row) Events::run_scheduled_operation($row);

            return true;
        }

        private function monthly()
        {
            Hook::run("MonthlyCronJob");
            return true;
        }

        private function daily()
        {
            if (DEVELOPMENT) {

                $error_log = true;
                $error_debug = false;
                $development = false;


                $str = '<?php';
                $str .= ' 
    defined(\'CORE_FOLDER\') OR exit(\'You can not get in here!\');
    define("DEMO_MODE",' . (DEMO_MODE ? "true" : "false") . ');
    define("DEVELOPMENT",' . ($development ? "true" : "false") . ');
    define("ERROR_DEBUG",' . ($error_debug ? "true" : "false") . ');
    define("LOG_SAVE",' . ($error_log ? "true" : "false") . ');';
                FileManager::file_write(CONFIG_DIR . "constants.php", $str);
            }

            // Cleaning API logs exceeding 7 day.
            $this->model->db->delete("api_logs")->where("created_at", "< NOW() - INTERVAL 7 DAY")->run();

            Hook::run("DailyCronJob");
            return true;
        }

        private function hourly()
        {

            // Domain name order product link check
            $this->model->db->query('UPDATE ' . $this->model->pfx . 'users_products t1
SET t1.product_id = (
    SELECT t2.id
    FROM ' . $this->model->pfx . 'tldlist t2
    WHERE JSON_UNQUOTE(JSON_EXTRACT(t1.options, "$.tld")) = t2.name
)
WHERE 
t1.type = "domain" AND
t1.product_id != 0 AND
t1.product_id NOT IN (SELECT t2.id FROM ' . $this->model->pfx . 'tldlist t2) AND
JSON_UNQUOTE(JSON_EXTRACT(t1.options, "$.tld")) IS NOT NULL AND
JSON_UNQUOTE(JSON_EXTRACT(t1.options, "$.tld")) IN (SELECT t2.name FROM ' . $this->model->pfx . 'tldlist t2);');

            Helper::Load(["Invoices", "Events", "Orders", "Products", "Money", "Notification"]);
            $linked_orders = [];
            $upcoming_subs = $this->model->upcoming_subscriptions();
            $get_modules = $this->model->subscription_modules();
            $changes = 0;

            $modules = [];

            if ($get_modules) {
                foreach ($get_modules as $m) {
                    $mod_name = $m->module;
                    $mod = Modules::Load("Payment", $mod_name);
                    if ($mod && class_exists($mod_name)) {
                        $class = new $mod_name();
                        $modules[$mod_name] = $class;
                    }
                }
            }

            if ($upcoming_subs) {
                foreach ($upcoming_subs as $s) {
                    if ($changes >= 2) continue;

                    $s["items"] = Utility::jdecode($s, true);

                    $invoices = [];
                    $last_duedate = false;

                    $orders = $this->model->db->select("id,duedate")->from("users_products");
                    $orders->where("subscription_id", "=", $s["id"], "&&");
                    $orders->where("status", "!=", "cancelled");
                    if ($orders->build()) {
                        foreach ($orders->fetch_object() as $o) {
                            $linked_orders[$s["id"]] = $o->id;
                            $l_invoice = Invoices::get_last_invoice($o->id, 'unpaid');
                            if ($l_invoice) $invoices[] = $l_invoice["id"];
                            if (!$last_duedate) $last_duedate = $o->duedate;
                        }
                    }

                    $addons = $this->model->db->select("id,duedate")->from("users_products_addons");
                    $addons->where("subscription_id", "=", $s["id"], "&&");
                    $addons->where("status", "!=", "cancelled");
                    if ($addons->build()) {
                        foreach ($addons->fetch_object() as $o) {
                            $l_invoice = Invoices::get_last_invoice_addon($o->id, 'unpaid');
                            if ($l_invoice) $invoices[] = $l_invoice["id"];
                            if (!$last_duedate) $last_duedate = $o->duedate;

                        }
                    }

                    if ($invoices) {
                        $processing = Events::isCreated('processing', 'cronjob', $s["id"], 'subscription-payment-check', false, 0, true);
                        if ($processing) {
                            $wait_time = DateManager::strtotime(DateManager::next_date([
                                $processing["cdate"],
                                'minute' => 10,
                            ]));
                            if ($wait_time > DateManager::strtotime()) continue;
                        }
                        if ($processing) Events::delete($processing["id"]);

                        $processing_id = Events::create(
                            [
                                'type'     => 'processing',
                                'owner'    => 'cronjob',
                                'owner_id' => $s["id"],
                                'name'     => 'subscription-payment-check',
                                'cdate'    => DateManager::Now(),
                            ]
                        );

                        $processing = Events::get($processing_id);


                        $mod_name = $s["module"];
                        $class = $modules[$mod_name] ?? false;

                        if ($class && method_exists($class, 'get_subscription')) {
                            $get = $class->get_subscription($s);
                            if ($get) {
                                if (isset($get["last_paid"]["time"]) && $get["last_paid"]["time"]) {
                                    $now = DateManager::Now();
                                    $last_paid = DateManager::strtotime($get["last_paid"]["time"]);
                                    $c_time = DateManager::strtotime($last_duedate);
                                    $b_c_time = DateManager::strtotime(DateManager::old_date([
                                        $last_duedate,
                                        'day' => 5,
                                    ]));

                                    if ($last_paid >= $c_time || $last_paid >= $b_c_time) {
                                        Orders::set_subscription($s["id"], [
                                            'status'         => $get["status"],
                                            'last_paid_fee'  => Money::exChange($get["last_paid"]["fee"]["amount"], $get["last_paid"]["fee"]["currency"]),
                                            'last_paid_date' => $get["last_paid"]["time"],

                                            'next_payable_fee'  => Money::exChange($get["next_payable"]["fee"]["amount"], $get["next_payable"]["fee"]["currency"]),
                                            'next_payable_date' => $get["next_payable"]["time"],
                                        ]);
                                        $s = Orders::get_subscription($s["id"]);
                                        $s["items"] = Utility::jdecode($s["items"], true);
                                        $commission_rate = 0;
                                        if (isset($mod["config"]["settings"]["commission_rate"]))
                                            $commission_rate = $mod["config"]["settings"]["commission_rate"];

                                        if ($invoices)
                                            foreach ($invoices as $i) Invoices::paid_subscription($s, $i, $commission_rate, $mod_name);
                                        if (isset($linked_orders[$s["id"]]) && $linked_orders[$s["id"]]) {
                                            foreach ($linked_orders[$s["id"]] as $lo_id)
                                                Orders::add_history(0, $lo_id, "Subscription Charged");
                                        }
                                    } else {
                                        if (method_exists($class, 'capture_subscription')) {
                                            $isCaptured = Events::isCreated("capture", "subscription", $s["id"], 'capture-subscription-payment', 0, 0, true);
                                            if (!$isCaptured || DateManager::format("Y-m-d", $isCaptured["cdate"]) != DateManager::Now("Y-m-d")) {
                                                Events::create([
                                                    'type'     => 'capture',
                                                    'owner'    => 'subscription',
                                                    'owner_id' => $s["id"],
                                                    'name'     => 'capture-subscription-payment',
                                                ]);
                                                $s["invoices"] = $invoices;
                                                $capture = $class->capture_subscription($s);
                                                if ($capture) {
                                                    $days = DateManager::diff_day($s["last_paid_date"], $s["next_payable_date"]);
                                                    Orders::set_subscription($s["id"], [
                                                        'status'            => 'active',
                                                        'last_paid_date'    => $s["next_payable_date"],
                                                        'last_paid_fee'     => $s["next_payable_fee"],
                                                        'next_payable_date' => DateManager::next_date([$s["next_payable_date"], 'day' => $days]),
                                                    ]);

                                                    $s["items"] = Utility::jdecode($s["items"], true);
                                                    $commission_rate = 0;
                                                    if (isset($mod["config"]["settings"]["commission_rate"]))
                                                        $commission_rate = $mod["config"]["settings"]["commission_rate"];

                                                    if ($invoices)
                                                        foreach ($invoices as $i)
                                                            Invoices::paid_subscription($s, $i, $commission_rate, $mod_name);

                                                    if (isset($linked_orders[$s["id"]]) && $linked_orders[$s["id"]]) {
                                                        foreach ($linked_orders[$s["id"]] as $lo_id)
                                                            Orders::add_history(0, $lo_id, "Subscription Charged");
                                                    }
                                                    continue;
                                                }
                                            }
                                        }

                                        if ($c_time <= $now) {
                                            $isReminded = Events::isCreated("notification", "subscription", $s["id"], 'invoice-subscription-payment-failed', 0, 0, true);
                                            if (!$isReminded || DateManager::format("Y-m-d", $isReminded["cdate"]) != DateManager::Now("Y-m-d")) Notification::invoice_subscription_payment_failed($s);
                                        }

                                    }
                                }
                            }
                        }

                        if ($processing) Events::set($processing["id"], ['status' => "approved"]);
                        $changes++;
                    } else
                        continue;

                }
            }


            ## CHECK SERVER FULL ALERT #
            Helper::Load(["Orders", "Products"]);

            $stmt = Models::$init->db->select()->from("servers");
            $stmt->where("full_alert", "=", 1, "&&");
            $stmt->where("status", "=", "active", "&&");
            $stmt->where("maxaccounts", ">", "0");
            if ($stmt->build()) {
                foreach ($stmt->fetch_assoc() as $s) {
                    $used = Orders::linked_server_count($s["id"]);
                    $maxacc = $s["maxaccounts"] ?? 0;

                    if ($maxacc <= $used) {
                        $isCreated = Events::isCreated("info", "system", $s["id"], "server-is-full", false, false, true);
                        if (!$isCreated)
                            Events::create([
                                'type'     => "info",
                                'owner'    => "system",
                                'owner_id' => $s["id"],
                                'name'     => "server-is-full",
                                'data'     => [],
                            ]);
                    } else {
                        $isCreated = Events::isCreated("info", "system", $s["id"], "server-is-full", false, false, true);
                        if ($isCreated) Events::approved($isCreated["id"]);
                    }
                }
            }
            ## CHECK SERVER FULL ALERT ##

            $version_dirs = FileManager::glob(STORAGE_DIR . "updates/*", GLOB_ONLYDIR);
            if ($version_dirs) {
                foreach ($version_dirs as $vd) {
                    $time = filemtime($vd);
                    $time_str = DateManager::timetostr("Y-m-d", $time);
                    $diff = DateManager::diff_day($time_str, DateManager::Now("Y-m-d"));
                    if ($diff >= 10) FileManager::remove_glob_directory($vd);
                }
            }


            Hook::run("HourlyCronJob");
            return !($changes > 0);
        }

        private function per_minute(){
            if(!stristr($_SERVER["PHP_SELF"],'coremio/cronjobs'))
            {
                Helper::Load("Events");
                $event = Events::isCreated("info","system",0,"cronjobs-change-file",false,false,true);
                if($event)
                {
                    if(file_exists(STORAGE_DIR."CHANGE_CRONJOB_FILE"))
                        FileManager::file_delete(STORAGE_DIR."CHANGE_CRONJOB_FILE");
                    Events::delete($event["id"]);
                }
            }

            $changes            = 0;
            $subscriptions      = $this->model->subscriptions();
            $get_modules        = $this->model->subscription_modules();

            $modules            = [];

            if($get_modules)
            {
                foreach($get_modules AS $m)
                {
                    $mod_name       = $m->module;
                    $mod            = Modules::Load("Payment",$mod_name);
                    if($mod && class_exists($mod_name))
                    {
                        $class      = new $mod_name();
                        $modules[$mod_name] = $class;
                    }
                }
            }

            if($subscriptions)
            {
                Helper::Load(["Invoices","Money","Coupon","Products","Orders","User","Events"]);
                foreach($subscriptions AS $sb)
                {
                    if($changes >= 3) break;

                    $ulang                  = User::getData($sb["user_id"],"lang")->lang;
                    $items                  = Utility::jdecode($sb["items"],true);
                    $new_items              = $items;
                    $next_payable_fee       = round($sb["next_payable_fee"],2);
                    $new_next_payable_fee   = 0;
                    $currency               = $sb["currency"];

                    $mod_name               = $sb["module"];
                    $com_rate               = 0;

                    if(isset($modules[$mod_name]))
                    {
                        $class          = $modules[$mod_name];
                    }
                    else
                        continue;

                    if(!($class->config["settings"]["change_subscription_fee"] ?? false)) continue;


                    if($class && method_exists($class,'get_commission_rate'))
                        $com_rate = $class->get_commission_rate();


                    if($items)
                    {
                        foreach($items AS $k => $i)
                        {
                            if($i["product_type"] == "addon")
                                $product    = Products::addon($i["product_id"],$ulang);
                            else
                                $product    = Products::get($i["product_type"],$i["product_id"]);
                            if($product)
                            {
                                $product_t  = $i["product_type"];
                                $period     = $i["period"] ?? "month";
                                $period_t   = $i["period_time"] ?? 1;
                                $amount     = $i["amount"];
                                $tax_rate   = $i["tax_rate"];
                                $tax_exempt = $i["tax_exempt"];
                                $curr       = $i["currency"];

                                if($com_rate > 0.00)
                                    $amount -= Money::get_inclusive_tax_amount($amount,$com_rate);

                                $org_amount     = $amount;
                                $org_curr       = $curr;

                                if($product_t == "domain")
                                {
                                    $prices         = $product["price"];
                                    $amount         = $prices["renewal"]["amount"] * $period_t;
                                    $curr           = $prices["renewal"]["cid"];
                                }
                                elseif($product_t == "addon")
                                {
                                    $opts   = $product["options"];
                                    if($opts)
                                    {
                                        foreach($opts AS $op)
                                        {
                                            if($op["id"] == $i["option_id"])
                                            {
                                                $amount     = $op["amount"];
                                                $curr       = $op["cid"];
                                            }
                                        }
                                    }
                                }
                                else
                                {
                                    $prices         = $product["price"];
                                    foreach($prices AS $p)
                                    {
                                        if($p["period"] == $period && $p["time"] == $period_t)
                                        {
                                            $amount     = $p["amount"];
                                            $curr       = $p["cid"];
                                        }
                                    }
                                }

                                if($product_t != "addon") {
                                    $findOrder  = WDB::select("id")->from("users_products");
                                    $findOrder->where("type","=",$product["type"],"&&");
                                    $findOrder->where("product_id","=",$product["id"],"&&");
                                    $findOrder->where("subscription_id","=",$sb["id"]);
                                    if($findOrder->build()) {
                                        foreach($findOrder->fetch_object() AS $f) {
                                            $order_id = $f->id;
                                            $rc = Coupon::select_renewal_coupon_for_order($order_id);
                                            if($rc) {
                                                if($rc["type"] == "percentage")
                                                    $dValue = Money::get_discount_amount($amount, $rc["rate"]);
                                                else
                                                    $dValue = Money::exChange($rc["amount"],$rc["currency"],$curr);
                                                if($dValue > $amount) $amount = 0;
                                                else $amount -= $dValue;
                                                break;
                                            }
                                        }
                                    }
                                }

                                if($com_rate > 0.00) $amount += Money::get_exclusive_tax_amount($amount,$com_rate);

                                $tax_included   = $amount;

                                if(!$tax_exempt && $tax_rate > 0.00)
                                    $tax_included       += Money::get_exclusive_tax_amount($tax_included,$tax_rate);

                                $new_items[$k]["currency"]          = $curr;
                                $new_items[$k]["amount"]            = $amount;
                                $new_items[$k]["commission_rate"]   = $com_rate;
                                $new_items[$k]["tax_included"]      = $tax_included;

                                $new_next_payable_fee               += Money::exChange($tax_included,$curr,$currency);
                            }
                        }
                    }

                    if($class && round($next_payable_fee,2) != round($new_next_payable_fee,2))
                    {
                        if(!method_exists($class,'change_subscription_fee')) continue;
                        $sb["items"] = $new_items;
                        $apply  = $class->change_subscription_fee($sb,$new_next_payable_fee,$currency);

                        if($apply)
                            $set_data = [
                                'items'                 => Utility::jencode($new_items),
                                'next_payable_fee'      => $new_next_payable_fee,
                                'status_msg'            => '',
                            ];
                        else
                            $set_data = [
                                'status_msg' => $class->error
                            ];

                        Orders::set_subscription($sb["id"],$set_data);
                        $changes++;
                    }
                }
            }

            Hook::run("PerMinuteCronJob");
            return !($changes > 0);
        }

        private function msg_decrypt($source = '')
        {
            $key = Config::get("crypt/user");
            $encrypt_method = "AES-256-CBC";
            if ($key === null)
                $secret_key = "NULL";
            else
                $secret_key = $key;
            $secret_iv = 'pUPoFn89JNh2eSKM';
            $key = hash('sha256', $secret_key);
            $iv = substr(hash('sha256', $secret_iv), 0, 16);
            $output = base64_decode($source);
            return openssl_decrypt($output, $encrypt_method, $key, 0, $iv);
        }

        private function mail_parse($data = '')
        {
            if (!class_exists("rfc822_addresses_class")) include CLASS_DIR . "rfc822_addresses.php";
            if (!class_exists("mime_parser_class")) include CLASS_DIR . "mime_parser.php";

            $mime = new mime_parser_class;
            $mime->ignore_syntax_errors = 1;
            $parameters = array('Data' => $data);

            $mime->Decode($parameters, $decoded);

            return $decoded[0] ?? [];
        }


        private function save_log($message)
        {
            $date = new DateTimeImmutable();
            $log_file = ROOT_DIR . "temp" . DS . "cronjobs.log";

            if (!file_exists($log_file)) touch($log_file);
            $file = fopen($log_file, "a+");
            fwrite($file, '[' . $date->format("Y-m-d H:i") . '] ' . $message . "\n");
            fclose($file);

            return true;
        }

        private function spam_check($row = [])
        {
            $spammed = false;
            $ip = $row["ip"] ?? "N/A";

            if (!$spammed && $row["spam"] ?? false)
                $spammed = "spam status: " . $row["spam"];

            if (!$spammed && Config::get("options/spam-control/block-temporary"))
                if (UserManager::is_temporary_mail($row["form"]["address"], $ip) || UserManager::is_temporary_mail($row["to"]["address"], $ip))
                    $spammed = "Temporary email detected";

            $banned_words = Config::get("options/spam-control/word-list");
            $banned_words = $banned_words ? explode("\n", $banned_words) : false;

            if (!$spammed && $banned_words) {
                foreach ($banned_words as $b) {
                    $hashing = $row["subject"] ?? '';
                    $hashing .= '   ' . ($row["from"]["name"] ?? '');
                    $hashing .= '   ' . ($row["from"]["address"]);
                    $hashing .= '   ' . ($row["to"]["name"] ?? '');
                    $hashing .= '   ' . ($row["to"]["address"]);
                    $hashing .= '   ' . ($row["message"] ?? '');
                    if (stristr($hashing, $b)) $spammed = "Blocked Keyword : " . $b;
                }
            }

            if ($spammed) {
                if (file_exists(STORAGE_DIR . "SPAM_COUNTER"))
                    $total_spam_count = FileManager::file_read(STORAGE_DIR . "SPAM_COUNTER");
                else
                    $total_spam_count = 0;

                $spam_list = $this->model->db->select("id")->from("last_spam_records");
                $spam_list->order_by("id ASC");
                if ($spam_list->build()) {
                    $spam_list_count = $spam_list->rowCounter();
                    if ($spam_list_count >= 50)
                        $this->model->db->delete("last_spam_records")->where("id", "=", $spam_list->getObject()->id)->run();
                }
                $this->model->db->insert("last_spam_records", [
                    'subject'      => $row["subject"] ?? '',
                    'from_address' => $row["from"]["address"] ?? '',
                    'from_name'    => $row["from"]["name"] ?? '',
                    'to_address'   => $row["to"]["address"] ?? '',
                    'to_name'      => $row["to"]["name"] ?? '',
                    'message'      => $row["message"] ?? '',
                    'reason'       => $spammed,
                    'ip'           => $ip,
                    'created_at'   => DateManager::Now(),
                ]);
                $total_spam_count++;
                FileManager::file_write(STORAGE_DIR . "SPAM_COUNTER", $total_spam_count);
            }
            return $spammed;
        }

        private function domain_upcoming_renewal_notice($data = [])
        {
            Helper::Load(["User", "Orders", "Events", "Money", "Notification"]);
            $changes = 0;
            $settings = $data["settings"];

            $notice = false;
            $first = (int)$settings["first"] ?? 0;
            $second = (int)$settings["second"] ?? 0;
            $third = (int)$settings["third"] ?? 0;
            $fourth = (int)$settings["fourth"] ?? 0;
            $fifth = (int)$settings["fifth"] ?? 0;

            if ($first > 0 || $second > 0 || $third > 0 || $fourth > 0 || $fifth > 0) $notice = true;

            if ($notice) {
                $select = [
                    'id',
                    'duedate',
                    'name',
                    'DATEDIFF(DATE(duedate), CURDATE()) AS upcoming_days',
                ];
                $domains = $this->model->db->select(implode(",", $select))->from("users_products");
                $domains->where("type", "=", "domain", "&&");
                $days = [$first, $second, $third, $fourth, $fifth];
                $days = array_filter($days, function ($value) {
                    return $value !== 0 && $value !== '0';
                });
                $daysS = sizeof($days) - 1;
                $now = DateManager::Now("Y-m-d");

                $domains->where("(");
                for ($i = 0; $i <= $daysS; $i++) {
                    $day = $days[$i];
                    $end = $i == $daysS;
                    $domains->where("DATEDIFF(DATE(duedate),'" . $now . "')", "=", $day, $end ? "" : "||");
                }
                $domains->where(")", "", "", "&&");
                $domains->where("status", "!=", "cancelled");
                $domains->order_by("upcoming_days ASC");
                $domains->limit(50);
                $domains = $domains->build() ? $domains->fetch_assoc() : [];
                if ($domains) {
                    foreach ($domains as $row) {
                        $already_check = $this->model->db->select("id")->from("events");
                        $already_check->where("type", "=", "notification", "&&");
                        $already_check->where("owner", "=", "order", "&&");
                        $already_check->where("owner_id", "=", $row["id"], "&&");
                        $already_check->where("cdate", "LIKE", "%" . $now . "%", "&&");
                        $already_check->where("name", "LIKE", "domain-upcoming-renewal-notice");
                        $already_check = $already_check->build();
                        if (!$already_check) {
                            Notification::domain_upcoming_renewal_notice($row["id"]);
                            $changes++;
                        }
                    }
                }
            }
            if ($changes) User::addAction(0, "cronjobs", "Upcoming Domain Renewal Notifications sent", ['count' => $changes]);

            return true;
        }

        private function domain_expired_notice($data = [])
        {
            Helper::Load(["User", "Orders", "Events", "Money", "Notification"]);
            $changes = 0;
            $settings = $data["settings"] ?? [];
            $now = DateManager::Now("Y-m-d");
            $day = $settings["day"] ?? 3;

            $select = [
                'id',
                'duedate',
                'name',
                'DATEDIFF(CURDATE(),duedate) AS delayed_day',
            ];
            $domains = $this->model->db->select(implode(",", $select))->from("users_products");
            $domains->where("type", "=", "domain", "&&");
            $domains->where("status", "!=", "cancelled", "&&");
            $domains->where("DATEDIFF('" . $now . "',duedate)", "=", $day, "&&");
            $domains->where("duedate", "<", $now);
            $domains->order_by("delayed_day ASC");
            $domains->limit(50);
            $domains = $domains->build() ? $domains->fetch_assoc() : [];

            if ($domains) {
                foreach ($domains as $row) {
                    $already_check = $this->model->db->select("id")->from("events");
                    $already_check->where("type", "=", "notification", "&&");
                    $already_check->where("owner", "=", "order", "&&");
                    $already_check->where("owner_id", "=", $row["id"], "&&");
                    $already_check->where("cdate", "LIKE", "%" . $now . "%", "&&");
                    $already_check->where("name", "LIKE", "domain-expired-notice");
                    $already_check = $already_check->build();
                    if (!$already_check) {
                        Notification::domain_expired_notice($row["id"]);
                        $changes++;
                    }
                }
            }

            if ($changes) User::addAction(0, "cronjobs", "Domain Expired Notifications sent", ['count' => $changes]);

            return false;
        }

    }