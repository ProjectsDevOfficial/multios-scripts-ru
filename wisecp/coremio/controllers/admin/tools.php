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
        }


        private function get_contact_list()
        {
            $user_type = Filter::init("POST/user_type", "letters");
            $type = Filter::init("POST/type", "letters");
            $result = [];
            $user_groups = Filter::POST("user_groups");
            $departments = Filter::POST("departments");
            $countries = Filter::POST("countries");
            $languages = Filter::POST("languages");
            $services = Filter::POST("services");
            $servers = Filter::POST("servers");
            $addons = Filter::POST("addons");
            $client_ss = Filter::POST("client_status");
            $services_ss = Filter::POST("services_status");
            $without_products = (int)Filter::POST("without_products");
            $birthday_marketing = (int)Filter::POST("birthday_marketing");

            $results = $this->model->get_contact_list($user_type, $type, $user_groups, $departments, $countries, $languages, $services, $servers, $addons, $services_ss, $without_products, $client_ss, $birthday_marketing);
            if ($results) {
                foreach ($results as $row) {
                    $option_value = false;
                    if ($type == "email") {
                        $option_value = $row["id"];
                        $option_text = $row["email"];
                    } elseif ($type == "gsm" && $row["phone"]) {
                        $option_value = $row["id"];
                        $option_text = "+" . $row["phone"];
                    }

                    if ($option_value) {
                        $option_text .= " - " . $row["full_name"];
                        if ($row["company_name"]) $option_text .= " (" . $row["company_name"] . ")";
                        $result[$option_value] = $option_text;
                    }
                }
            }

            echo Utility::jencode(['result' => $result]);
        }


        private function change_newsletter()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $type = Filter::init("POST/type", "letters");
            $lang = Filter::init("POST/lang", "route");
            if (!($type == "email" || $type == "sms")) return false;
            $data = Filter::POST("data/" . $lang);
            $data = $data ? explode("\n", $data) : [];
            if ($data)
                $data = array_map(function ($v) {
                    return trim($v);
                }, $data);

            $new_added = [];
            $deleted = [];

            $fetch_current_data = $this->model->newsletters($type, $lang);
            $current_data = [];
            if ($fetch_current_data) {
                foreach ($fetch_current_data as $row) {
                    $content = $row["content"];
                    if (in_array($content, $current_data)) $deleted[] = $row["id"];
                    $current_data[] = $content;
                    if (!in_array($content, $data)) $deleted[] = $row["id"];
                }
            }

            if ($data) {
                foreach ($data as $datum) {
                    if ($type == "email") {
                        $datum = Filter::email($datum);
                        if (!$datum || !Validation::isEmail($datum)) continue;
                    }
                    if (!in_array($datum, $current_data)) $new_added[] = $datum;
                }
            }

            $changes = false;


            if ($new_added)
                foreach ($new_added as $item)
                    if ($this->model->db->insert("newsletters", [
                        'lang'    => $lang,
                        'type'    => $type,
                        'content' => $item,
                        'ctime'   => DateManager::Now(),
                        'ip'      => UserManager::GetIP(),
                    ])) $changes = true;

            if ($deleted)
                foreach ($deleted as $item)
                    if ($this->model->db->delete("newsletters")->where("id", "=", $item)->run()) $changes = true;

            if ($changes) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-" . $type . "-newsletter");
            }

            $new_data = [];
            $fetch_new_data = $this->model->newsletters($type, $lang);
            if ($fetch_new_data) foreach ($fetch_new_data as $datum) $new_data[] = $datum["content"];

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/tools/success4"),
                'data'    => $new_data,
            ]);

        }


        private function save_notification_template()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $just_submission = (int)Filter::init("POST/just_submission", "numbers");
            $template_id = (int)Filter::init("POST/template_id", "numbers");
            $template_name = (string)Filter::init("POST/template_name", "hclear");
            $template_type = (string)Filter::init("POST/template_type", "letters");
            $type = (string)Filter::init("POST/type", "letters");
            $criteria = (string)Filter::init("POST/criteria", "letters");
            $newsletter = (string)Filter::init("POST/newsletter", "route");
            $without_products = (int)Filter::init("POST/without_products", "numbers");
            $birthday_marketing = (int)Filter::init("POST/birthday_marketing", "numbers");
            $cc = (string)Filter::init("POST/cc");
            $subject = (string)Filter::init("POST/subject");
            $message = (string)Filter::init("POST/message");
            $submission_type = (string)Filter::init("POST/submission_type", "letters");
            $auto_submission = (int)Filter::init("POST/auto_submission", "numbers");

            $user_groups = Filter::init("POST/user_groups");
            $departments = Filter::init("POST/departments");
            $countries = Filter::init("POST/countries");
            $languages = Filter::init("POST/languages");
            $services = Filter::init("POST/services");
            $servers = Filter::init("POST/servers");
            $addons = Filter::init("POST/addons");
            $services_status = Filter::init("POST/services_status");
            $client_status = Filter::init("POST/client_status");


            if ($just_submission && !$template_id)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tools/bulk-please-select-template"),
                ]));


            $period = Filter::init("POST/period", "route");
            $period_datetime = Filter::init("POST/period_datetime", "hclear");
            $period_month = -1;
            $period_day = -1;
            $period_hour = -1;
            $period_minute = -1;
            $period_hour_minute = Filter::POST("period_hour_minute");
            if ($period_datetime) $period_datetime = str_replace("T", " ", $period_datetime);


            if (!$just_submission && Validation::isEmpty($template_name))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tools/bulk-template-name-prompt"),
                ]));

            if ((int)Filter::init("POST/period_month", "amount") && $period == "recurring")
                $period_month = (int)Filter::init("POST/period_month", "numbers");

            if ((int)Filter::init("POST/period_day", "amount") && $period == "recurring")
                $period_day = (int)Filter::init("POST/period_day", "numbers");

            if ($period_hour_minute && $period == "recurring") {
                $split_hour_minute = explode(":", $period_hour_minute);
                if (isset($split_hour_minute[0])) $period_hour = (int)$split_hour_minute[0];
                if (isset($split_hour_minute[1])) $period_minute = (int)$split_hour_minute[1];
            }


            if ($just_submission) {
                if (!$period) $period = "onetime";

                if (($period == "onetime" && !$period_datetime) || ($period == "recurring" && !($period_hour > -1 && $period_minute > -1)))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/tools/bulk-error1"),
                    ]));

                if ($period == "onetime") {
                    $parse = explode(" ", $period_datetime);
                    $parse_date = explode("-", $parse[0]);
                    $parse_time = explode(":", $parse[1]);
                    $date = $parse_date[0] . "-" . $parse_date[1] . "-" . $parse_date[2];
                    $year = (int)$parse_date[0];
                    $month = (int)$parse_date[1];
                    $day = (int)$parse_date[2];
                    $time = $parse_time[0] . ":" . $parse_time[1];
                    $hour = (int)$parse_time[0];
                    $minute = (int)$parse_time[1];
                    $period_month = $month;
                    $period_day = $day;
                    $period_hour = $hour;
                    $period_minute = $minute;
                }
            }

            if ($just_submission && !$template_id) exit("Template not found");

            if ($just_submission)
                $data = [
                    'auto_submission' => $auto_submission,
                    'period'          => $period,
                    'period_datetime' => $period_datetime,
                    'period_month'    => $period_month,
                    'period_day'      => $period_day,
                    'period_hour'     => $period_hour,
                    'period_minute'   => $period_minute,
                ];

            else {
                if ($template_type == "mail" && Validation::isEmpty($subject))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='subject']",
                        'message' => __("admin/tools/error3"),
                    ]));

                if ($template_type == "mail" && Utility::strlen($message) < 3)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/tools/error4"),
                    ]));


                $data = [
                    'template_name'      => $template_name,
                    'template_type'      => $template_type,
                    'type'               => $type,
                    'criteria'           => $criteria,
                    'newsletter'         => $newsletter,
                    'without_products'   => $without_products,
                    'birthday_marketing' => $birthday_marketing,
                    'cc'                 => $cc,
                    'subject'            => $subject,
                    'message'            => $message,
                    'submission_type'    => $submission_type,
                    'user_groups'        => $user_groups ? Utility::jencode($user_groups) : '',
                    'departments'        => $departments ? Utility::jencode($departments) : '',
                    'countries'          => $countries ? Utility::jencode($countries) : '',
                    'languages'          => $languages ? Utility::jencode($languages) : '',
                    'services'           => $services ? Utility::jencode($services) : '',
                    'servers'            => $servers ? Utility::jencode($servers) : '',
                    'addons'             => $addons ? Utility::jencode($addons) : '',
                    'services_status'    => $services_status ? Utility::jencode($services_status) : '',
                    'client_status'      => $client_status ? Utility::jencode($client_status) : '',
                ];
            }


            if ($template_id) {
                $data["updated_at"] = DateManager::Now();
                $ok_msg = __("admin/tools/bulk-successful3");
                if ($just_submission) $ok_msg = __("admin/tools/bulk-successful4");
                $this->model->db->update("notification_templates")->set($data)->where("id", "=", $template_id)->save();
            } else {
                $data["created_at"] = DateManager::Now();
                $data["updated_at"] = DateManager::Now();
                $ok_msg = __("admin/tools/bulk-successful1");
                $this->model->db->insert("notification_templates", $data);
            }
            echo Utility::jencode(['status' => "successful", 'message' => $ok_msg]);
        }

        private function remove_notification_template()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $template_id = (int)Filter::init("POST/template_id", "numbers");

            if ($template_id) $this->model->db->delete("notification_templates")->where("id", "=", $template_id)->run();


            echo Utility::jencode(['status' => "successful", 'message' => __("admin/tools/bulk-successful2")]);
        }


        private function submit_bulk_mail()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $users = Filter::POST("users");
            $users = is_array($users) ? $users : explode(",", $users);
            $cc = Filter::init("POST/cc", "hclear");
            $newsletter = Filter::init("POST/newsletter", "hclear");
            $subject = Filter::init("POST/subject", "hclear");
            $message = Filter::POST("message");
            $submission_type = Filter::POST("submission-type");

            if ((int)Filter::init("POST/test_mode", "numbers")) {
                $departments = Filter::init("POST/departments");
                if ($departments && is_array($departments)) {
                    if (!$users) $users = [];
                    Helper::Load("Tickets");
                    foreach ($departments as $d_id) {
                        $d = Tickets::get_department($d_id, false, 't1.appointees');
                        if (!$d) continue;
                        $d = isset($d["appointees"]) ? $d["appointees"] : '';
                        if (!$d) continue;
                        $d = explode(",", $d);
                        foreach ($d as $uid) if (!in_array($uid, $users)) $users[] = $uid;
                    }
                }
            }


            if (!Validation::isEmpty($cc)) {
                $cc_parse = explode("\n", $cc);
                if ($cc_parse) {
                    $cc = [];
                    foreach ($cc_parse as $c) {
                        $c = Filter::email($c);
                        if (Validation::isEmail($c)) $cc[] = $c;
                    }
                }
            }

            if (!Validation::isEmpty($newsletter)) {
                $nr_parse = explode("\n", $newsletter);
                if ($nr_parse) {
                    $newsletter = [];
                    foreach ($nr_parse as $nr) {
                        $nr = Filter::email($nr);
                        if (Validation::isEmail($nr)) $newsletter[] = $nr;
                    }
                }
            }

            if (!$users && !$cc && !$newsletter)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tools/error1"),
                ]));

            if (Validation::isEmpty($subject))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='subject']",
                    'message' => __("admin/tools/error3"),
                ]));

            if (Validation::isEmpty($message))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tools/error4"),
                ]));

            $emails = [];

            if ($users && is_array($users)) {
                foreach ($users as $uid) {
                    $uid = (int)$uid;
                    if (!$uid) continue;
                    $gData = User::getData($uid, "id,full_name,email,lang", "array");
                    if ($gData) {
                        $emails[$gData["email"]] = $gData["full_name"] . "|" . $gData["id"] . "|" . $gData["lang"];
                    }
                }
            }
            if ($cc) foreach ($cc as $c) if (!isset($emails[$c])) $emails[$c] = null;
            if ($newsletter) foreach ($newsletter as $n) if (!isset($emails[$n])) $emails[$n] = null;


            if (!$emails)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tools/error1"),
                ]));

            $submissions = [];

            Modules::Load("Mail");
            $mail_module = Config::get("modules/mail");

            if (!$mail_module || $mail_module == "none" || !class_exists($mail_module))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Please configure your smtp settings.",
                ]));

            $mail = new $mail_module();

            $locall = Config::get("general/local");

            if ($submission_type == "single") {
                foreach ($emails as $address => $name) {
                    $parse = explode("|", $name);
                    $name = $parse[0];
                    $uid = isset($parse[1]) ? $parse[1] : 0;
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $msg = $message;
                    View::variables_handler("mail", $uid, [
                        'newsletter_unsubscribe_link' => $this->CRLink("newsletter/unsubscribe", false, "none") . "?lang=auto&email=" . $address,
                    ], $msg, $lang);
                    $send = $mail->body($msg, false, false, $lang, $uid)->subject($subject);
                    $send = $send->addAddress($address, $name)->submit();
                    if ($send) {
                        $submissions[] = $address;
                        LogManager::Mail_Log(0, "bulk-mail", $mail->getSubject(), $mail->getBody(), implode(",", $mail->getAddresses()));
                    }
                }
            } elseif ($submission_type == "multiple") {
                $concats = [];
                foreach ($emails as $address => $name) {
                    $parse = explode("|", $name);
                    $name = $parse[0];
                    $concats[$address] = $name;
                    $submissions[] = $address;
                }
                $msg = $message;
                View::variables_handler("mail", 0, [
                    'newsletter_unsubscribe_link' => $this->CRLink("newsletter/unsubscribe", false, "none") . "?lang=auto",
                ], $msg);
                $mail->body($msg)->subject($subject)->addAddress($concats);
                if ($mail->submit()) {
                    LogManager::Mail_Log(0, "bulk-mail", $mail->getSubject(), $mail->getBody(), implode(",", $mail->getAddresses()));
                } else
                    $submissions = [];
            }

            if (!$submissions)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tools/error5") . " -> " . $mail->error,
                ]));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "sent", "bulk-mail-sent");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/tools/success1"),
            ]);


        }


        private function submit_bulk_sms()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $users = Filter::POST("users");
            $users = is_array($users) ? $users : explode(",", $users);
            $cc = Filter::init("POST/cc", "hclear");
            $newsletter = Filter::init("POST/newsletter", "hclear");
            $message = Filter::POST("message");
            $submission_type = Filter::POST("submission-type");

            if ((int)Filter::init("POST/test_mode", "numbers")) {
                $departments = Filter::init("POST/departments");
                if ($departments && is_array($departments)) {
                    if (!$users) $users = [];
                    Helper::Load("Tickets");
                    foreach ($departments as $d_id) {
                        $d = Tickets::get_department($d_id, false, 't1.appointees');
                        if (!$d) continue;
                        $d = isset($d["appointees"]) ? $d["appointees"] : '';
                        if (!$d) continue;
                        $d = explode(",", $d);
                        foreach ($d as $uid) if (!in_array($uid, $users)) $users[] = $uid;
                    }
                }
            }


            if (!Validation::isEmpty($cc)) {
                $cc_parse = explode("\n", $cc);
                if ($cc_parse) {
                    $cc = [];
                    foreach ($cc_parse as $c) {
                        $c = Filter::numbers($c);
                        if (Validation::isPhone($c)) $cc[] = $c;
                    }
                }
            }

            if (!Validation::isEmpty($newsletter)) {
                $nr_parse = explode("\n", $newsletter);
                if ($nr_parse) {
                    $newsletter = [];
                    foreach ($nr_parse as $nr) {
                        $nr = Filter::numbers($nr);
                        if (Validation::isPhone($nr)) $newsletter[] = $nr;
                    }
                }
            }


            if (!$users && !$cc && !$newsletter)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tools/error2"),
                ]));

            if (Validation::isEmpty($message))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tools/error4"),
                ]));

            $phones = [];

            if ($users && is_array($users)) {
                foreach ($users as $uid) {
                    $uid = (int)$uid;
                    if (!$uid) continue;
                    $gData = User::getData($uid, "id,full_name,email,lang", "array");
                    if ($gData) $gData = array_merge($gData, User::getInfo($uid, ["gsm_cc", "gsm"]));
                    if ($gData) {
                        $phones[] = $gData["gsm_cc"] . "|" . $gData["gsm"] . "|" . $gData["id"] . "|" . $gData["lang"];
                    }
                }
            }
            if ($cc) {
                foreach ($cc as $c) {
                    $parse_gsm = Filter::phone_smash($c);
                    $gsm_cc = $parse_gsm["cc"];
                    $gsm_num = $parse_gsm["number"];
                    if (!array_search($gsm_cc . "|" . $gsm_num, $phones)) $phones[] = $gsm_cc . "|" . $gsm_num;
                }
            }

            if ($newsletter) {
                foreach ($newsletter as $nr) {
                    $parse_gsm = Filter::phone_smash($nr);
                    $gsm_cc = $parse_gsm["cc"];
                    $gsm_num = $parse_gsm["number"];
                    if (!array_search($gsm_cc . "|" . $gsm_num, $phones)) $phones[] = $gsm_cc . "|" . $gsm_num;
                }
            }

            if (!$phones)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tools/error2"),
                ]));


            $submissions = [];

            Modules::Load("SMS");
            $sms_module = Config::get("modules/sms");

            if (!$sms_module || $sms_module == "none" || !class_exists($sms_module))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Please configure your sms settings.",
                ]));


            $sms = new $sms_module();

            $locall = Config::get("general/local");

            if ($submission_type == "single") {
                foreach ($phones as $phone) {
                    $parse = explode("|", $phone);
                    $cc = $parse[0];
                    $number = $parse[1];
                    $uid = isset($parse[2]) ? $parse[2] : 0;
                    $lang = isset($parse[3]) ? $parse[3] : $locall;
                    $msg = $message;
                    View::variables_handler("sms", $uid, [], $msg, $lang);
                    $send = $sms->body($msg, false, false, $lang, $uid);
                    $send = $send->addNumber($number, $cc)->submit();
                    if ($send) {
                        $submissions[] = "+" . $cc . $number;
                        LogManager::Sms_Log(0, "bulk-sms", $sms->getTitle(), $sms->getBody(), implode(",", $sms->getNumbers()));
                    }
                }
            } elseif ($submission_type == "multiple") {
                $submissions = $phones;
                $msg = $message;
                View::variables_handler("sms", 0, [], $msg);
                $send = $sms->body($msg, false, false);
                $send = $send->addNumber($phones)->submit();
                if ($send) {
                    LogManager::Sms_Log(0, "bulk-sms", $sms->getTitle(), $sms->getBody(), implode(",", $sms->getNumbers()));
                } else
                    $submissions = [];
            }

            if (!$submissions)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tools/error5") . " -> " . $sms->getError(),
                ]));


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "sent", "bulk-sms-sent");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/tools/success2"),
            ]);


        }


        public function backup_database($from = 'direct')
        {
            $backup_dir = ROOT_DIR . "backup";
            if (!file_exists($backup_dir))
                if (!mkdir($backup_dir, 0777)) {
                    throw new Exception("'" . $backup_dir . "' can not create folder, please check your directory permissions.");
                }

            if (!file_exists($backup_dir . DS . "index.html")) touch($backup_dir . DS . "index.html");

            $_date = DateManager::Now("Y-m-d_H-i");
            $_token = md5('BacKuP-^_' . $_date . '_^-BacKuP');
            $b_name = $_date . "-" . $_token;
            $s_name = $b_name . ".sql";
            $s_z_name = $b_name . "-sql.zip";
            $s_z_file = $backup_dir . DS . $s_z_name;
            $error = false;

            Helper::Load("ExportDB");

            if (!file_exists($s_z_file)) {
                $backup_file_sql = $backup_dir . DS . $s_name;
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
                        $data_dumper->dump($backup_file_sql);
                    } catch (Shuttle_Exception $e) {
                        throw new Exception("Can not create SQL File: " . $e->getMessage());
                    }

                    if (!file_exists($backup_file_sql)) {
                        throw new Exception("Can not create SQL File.");
                    }


                    $zip = new ZipArchive();
                    if ($zip->open($s_z_file, ZipArchive::CREATE) !== true) {
                        FileManager::file_delete($backup_file_sql);
                        echo Utility::jencode([
                            'status'  => "error",
                            'message' => "Can not create zip archive.",
                        ]);
                        return false;
                    }
                    $zip->addFile($backup_file_sql, $s_name);
                    $zip->close();

                    FileManager::file_delete($backup_file_sql);

                    if (!file_exists($s_z_file)) {
                        throw new Exception("Can not create zip archive.");
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                MioException::$error_hide = false;
            }

            if ($from == "direct") {
                if ($error)
                    echo $error;
                else {
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . $s_z_name . '"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($s_z_file));

                    echo FileManager::file_read($s_z_file);
                }
            } else {
                if ($error)
                    return ['status' => "error", 'message' => $error];
                else
                    return ['status' => "completed", 'file' => $s_z_file];

            }
        }


        private function clear_notifications()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $password = Filter::init("POST/password", "password");
            $apassword = UserManager::LoginData("admin");
            $apassword = User::getData($apassword["id"], "password", "array");
            $apassword = $apassword["password"];
            $date = Filter::init("POST/date", "hclear");

            if (!$date || !Validation::isDate($date))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='date']",
                    'message' => __("admin/tools/error10"),
                ]));

            if (!$password)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "#password",
                    'message' => ___("needs/permission-delete-item-empty-password"),
                ]));

            if (!User::_password_verify("admin", $password, $apassword))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => ___("needs/permission-delete-item-invalid-password"),
                ]));

            $type = Filter::init("POST/type");


            if ($type == "mail")
                $this->model->db->delete("mail_logs")->where("ctime", "<=", $date)->run();
            elseif ($type == "sms")
                $this->model->db->delete("sms_logs")->where("ctime", "<=", $date)->run();


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/tools/success3"),
            ]);

        }


        private function clear_actions()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $password = Filter::init("POST/password", "password");
            $apassword = UserManager::LoginData("admin");
            $apassword = User::getData($apassword["id"], "password", "array");
            $apassword = $apassword["password"];
            $date = Filter::init("POST/date", "hclear");

            if (!$date || !Validation::isDate($date))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='date']",
                    'message' => __("admin/tools/error10"),
                ]));

            if (!$password)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "#password",
                    'message' => ___("needs/permission-delete-item-empty-password"),
                ]));

            if (!User::_password_verify("admin", $password, $apassword))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => ___("needs/permission-delete-item-invalid-password"),
                ]));

            $type = Filter::init("POST/type");

            if ($type == "module-log") {
                $this->model->db->delete("users_actions")
                    ->where("ctime", "<=", $date, "&&")
                    ->where("reason", "=", "module-log")
                    ->run();
            } else {
                $this->model->db->delete("users_actions")
                    ->where("ctime", "<=", $date, "&&")
                    ->where("reason", "!=", "module-log")
                    ->run();
            }

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/tools/success3"),
            ]);

        }


        private function get_addon_content()
        {
            $this->takeDatas(["language"]);
            $key = Filter::init("GET/module", "route");
            if (!$key) die("Not Found Addon");
            $module = Modules::Load("Addons", $key);
            if (!$module) die("Not Found Addon");
            $obj = new $key;
            if (property_exists($obj, 'area_link')) $obj->area_link = $this->AdminCRLink("tools-1", ["addons"]);
            if (Filter::POST("module_operation") == 'save_config' && method_exists($obj, 'save_settings'))
                return $obj->save_settings();
            if (method_exists($obj, 'settings')) $obj->settings();
            else $obj->main();
        }


        private function set_addon_status()
        {
            $this->takeDatas(["language"]);

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $key = Filter::init("POST/module", "route");
            $status = (int)Filter::init("POST/status", "rnumbers");
            if (!$key) die("Not Found Addon");
            $module = Modules::Load("Addons", $key);
            if (!$module) die("Not Found Addon");

            if (!class_exists($key))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "class " . $key . " not found.",
                ]));

            $obj = new $key;

            if (!method_exists($obj, "change_addon_status"))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "change_addon_status() method not exists",
                ]));

            $status = $status ? "enable" : "disable";

            $change = $obj->change_addon_status($status);
            if (!$change)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => $obj->error,
                ]));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "change-addon-status-" . $status, [
                'module' => $key,
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/tools/success5-" . $status),
            ]);

        }

        private function delete_addon()
        {
            $this->takeDatas(["language"]);

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $key = Filter::init("POST/module", "route");
            if (!$key) die("Not Found Addon");
            $module = Modules::Load("Addons", $key);
            if (!$module) die("Not Found Addon");


            if (!class_exists($key))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "class " . $key . " not found.",
                ]));

            $obj = new $key;

            if (method_exists($obj, "change_addon_status")) {
                $change = $obj->change_addon_status("disable");
                if (!$change)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => $obj->error,
                    ]));
            }

            FileManager::remove_glob_directory(MODULE_DIR . "Addons" . DS . $key);


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "Deleted Addon: " . $key);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/tools/success13"),
            ]);

        }


        private function add_new_task()
        {
            $this->takeDatas(["language"]);

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $title = Filter::init("POST/title", "hclear");
            $description = Filter::POST("description");
            //$assignment         = Filter::POST("assignment");
            $admin_id = Filter::init("POST/admin", "rnumbers");
            $department = Filter::POST("department");
            $user_id = Filter::init("POST/user", "rnumbers");
            $c_date = Filter::init("POST/c_date", "route");
            $due_date = Filter::init("POST/due_date", "route");
            $status = Filter::init("POST/status", "route");
            $status_note = Filter::POST("status_note");
            $notification = Filter::POST("notification");

            $is_full_admin = Admin::isPrivilege(["ADMIN_CONFIGURE"]);

            if (Validation::isEmpty($title))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name=title]",
                    'message' => __("admin/tools/error12"),
                ]));

            if (Validation::isEmpty($c_date))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name=title]",
                    'message' => __("admin/tools/error13"),
                ]));

            $udata = UserManager::LoginData("admin");
            $owner_id = $udata["id"];
            if (!$is_full_admin && !$admin_id) $admin_id = $owner_id;
            $departments = $department && is_array($department) ? implode(",", $department) : '';

            if (!$due_date) $due_date = DateManager::ata();

            $create = $this->model->db->insert("users_tasks", [
                'owner_id'    => $owner_id,
                'admin_id'    => $admin_id,
                'user_id'     => $user_id,
                'departments' => $departments,
                'title'       => $title,
                'description' => $description,
                'c_date'      => $c_date,
                'due_date'    => $due_date,
                'status'      => $status,
                'status_note' => $status_note,
            ]);

            if (!$create)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tools/error14"),
                ]));

            $insert_id = $this->model->db->lastID();


            if ($notification) {
                Helper::Load(["Notification"]);
                Notification::task_plan_created($insert_id);
            }

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/tools/success6"),
                'redirect' => $this->AdminCRLink("tools-1", ["tasks"]),
            ]);

            User::addAction($udata["id"], "added", "a-new-task-plan-added", [
                'id'    => $insert_id,
                'title' => $title,
            ]);


        }

        private function edit_task()
        {
            $this->takeDatas(["language"]);

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "rnumbers");
            if (!$id) return false;

            $is_full_admin = Admin::isPrivilege(["ADMIN_CONFIGURE"]);

            $udata = UserManager::LoginData("admin");
            $local_l = Config::get("general/local");
            $my_dids = [];
            $departments = [];

            Helper::Load(["Tickets"]);
            $get_departments = Tickets::get_departments($local_l, "t1.id,t1.appointees,t2.name");
            if ($get_departments) {
                foreach ($get_departments as $department) {
                    $appointess = $department["appointees"] ? explode(",", $department["appointees"]) : [];
                    $departments[$department["id"]] = $department;
                    if (in_array($udata["id"], $appointess)) $my_dids[] = $department["id"];
                }
            }


            $task = $this->model->db->select()->from("users_tasks AS t1")->where("t1.id", "=", $id, "&&");
            if ($is_full_admin) {
                $task->where("t1.owner_id", "!=", "0");
            } else {
                $task->where("(");
                if ($my_dids) {
                    foreach ($my_dids as $my_did) {
                        $task->where("FIND_IN_SET('" . $my_did . "',t1.departments)", "", "", "||");
                    }
                }
                $task->where("t1.owner_id", "=", $udata["id"], "||");
                $task->where("t1.admin_id", "=", $udata["id"], "");
                $task->where(")");
            }

            $task = $task->build() ? $task->getAssoc() : false;
            if (!$task) return false;


            $title = Filter::init("POST/title", "hclear");
            $description = Filter::POST("description");
            //$assignment         = Filter::POST("assignment");
            $admin_id = Filter::init("POST/admin", "rnumbers");
            $department = Filter::POST("department");
            $user_id = Filter::init("POST/user", "rnumbers");
            $c_date = Filter::init("POST/c_date", "route");
            $due_date = Filter::init("POST/due_date", "route");
            $status = Filter::init("POST/status", "route");
            $status_note = Filter::POST("status_note");
            $notification = Filter::POST("notification");

            $is_full_admin = Admin::isPrivilege(["ADMIN_CONFIGURE"]);


            if ($is_full_admin || $task["owner_id"] == $udata["id"]) {
                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name=title]",
                        'message' => __("admin/tools/error12"),
                    ]));

                if (Validation::isEmpty($c_date))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name=title]",
                        'message' => __("admin/tools/error13"),
                    ]));
            }


            $udata = UserManager::LoginData("admin");
            $owner_id = $udata["id"];
            if (!$is_full_admin && !$admin_id) $admin_id = $owner_id;
            $departments = $department && is_array($department) ? implode(",", $department) : '';

            if (!$due_date) $due_date = DateManager::ata();

            if ($is_full_admin || $task["owner_id"] == $udata["id"])
                $set_data = [
                    'admin_id'    => $admin_id,
                    'user_id'     => $user_id,
                    'departments' => $departments,
                    'title'       => $title,
                    'description' => $description,
                    'c_date'      => $c_date,
                    'due_date'    => $due_date,
                    'status'      => $status,
                    'status_note' => $status_note,
                ];
            else
                $set_data = [
                    'status'      => $status,
                    'status_note' => $status_note,
                ];


            $edit = $this->model->db->update("users_tasks", $set_data)->where("id", "=", $id)->save();

            if (!$edit)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tools/error14"),
                ]));

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/tools/success7"),
                'redirect' => $this->AdminCRLink("tools-1", ["tasks"]),
            ]);

            User::addAction($udata["id"], "alteration", "task-plan-changed", [
                'id'    => $id,
                'title' => $task["title"],
            ]);


        }

        private function task_apply_operation()
        {

            $this->takeDatas(["language"]);

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $type = Filter::POST("type");
            $from = Filter::POST("from");
            $id = (int)Filter::init("POST/id", "rnumbers");

            if (!$id || !$type) return false;

            $is_full_admin = Admin::isPrivilege(["ADMIN_CONFIGURE"]);

            $udata = UserManager::LoginData("admin");
            $local_l = Config::get("general/local");
            $my_dids = [];
            $departments = [];

            Helper::Load(["Tickets"]);
            $get_departments = Tickets::get_departments($local_l, "t1.id,t1.appointees,t2.name");
            if ($get_departments) {
                foreach ($get_departments as $department) {
                    $appointess = $department["appointees"] ? explode(",", $department["appointees"]) : [];
                    $departments[$department["id"]] = $department;
                    if (in_array($udata["id"], $appointess)) $my_dids[] = $department["id"];
                }
            }


            $task = $this->model->db->select()->from("users_tasks AS t1")->where("t1.id", "=", $id, "&&");
            if ($is_full_admin) {
                $task->where("t1.owner_id", "!=", "0");
            } else {
                $task->where("(");
                if ($my_dids) {
                    foreach ($my_dids as $my_did) {
                        $task->where("FIND_IN_SET('" . $my_did . "',t1.departments)", "", "", "||");
                    }
                }
                $task->where("t1.owner_id", "=", $udata["id"], "||");
                $task->where("t1.admin_id", "=", $udata["id"], "");
                $task->where(")");
            }

            $task = $task->build() ? $task->getAssoc() : false;
            if (!$task) return false;

            if ($type == "delete" && ($is_full_admin || $task["owner_id"] == $udata["id"])) {

                $delete = $this->model->db->delete("users_tasks")->where("id", "=", $id)->run();
                if (!$delete)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => "Cannot delete task.",
                    ]));

                User::addAction($udata["id"], "delete", "task-deleted", [
                    'id'    => $id,
                    'title' => $task["title"],
                ]);

                echo Utility::jencode([
                    'status'  => "successful",
                    'message' => __("admin/tools/success8"),
                ]);
            } elseif ($type == "completed") {

                $apply = $this->model->db->update("users_tasks", ['status' => "completed"])->where("id", "=", $id)->save();
                if (!$apply)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => "Cannot apply task.",
                    ]));

                User::addAction($udata["id"], "alteration", "task-plan-changed", [
                    'id'    => $id,
                    'title' => $task["title"],
                ]);

                echo Utility::jencode([
                    'status'  => "successful",
                    'message' => __("admin/tools/success7"),
                ]);
            }


        }


        private function add_new_reminder()
        {
            $this->takeDatas(["language"]);

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $note = Filter::POST("note");
            $status = Filter::init("POST/status", "route");
            $period = Filter::init("POST/period", "route");
            $period_datetime = Filter::init("POST/period_datetime", "hclear");
            $period_month = -1;
            $period_day = -1;
            $period_hour = -1;
            $period_minute = -1;
            $period_hour_minute = Filter::POST("period_hour_minute");
            if ($period_datetime) $period_datetime = str_replace("T", " ", $period_datetime);


            if ((int)Filter::init("POST/period_month", "amount") && $period == "recurring")
                $period_month = (int)Filter::init("POST/period_month", "numbers");

            if ((int)Filter::init("POST/period_day", "amount") && $period == "recurring")
                $period_day = (int)Filter::init("POST/period_day", "numbers");

            if ($period_hour_minute && $period == "recurring") {
                $split_hour_minute = explode(":", $period_hour_minute);
                if (isset($split_hour_minute[0])) $period_hour = (int)$split_hour_minute[0];
                if (isset($split_hour_minute[1])) $period_minute = (int)$split_hour_minute[1];
            }


            if (($period == "onetime" && !$period_datetime) || ($period == "recurring" && !($period_hour > -1 && $period_minute > -1)))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tools/error15"),
                ]));

            if ($period == "onetime") {
                $parse = explode(" ", $period_datetime);
                $parse_date = explode("-", $parse[0]);
                $parse_time = explode(":", $parse[1]);
                $date = $parse_date[0] . "-" . $parse_date[1] . "-" . $parse_date[2];
                $year = (int)$parse_date[0];
                $month = (int)$parse_date[1];
                $day = (int)$parse_date[2];
                $time = $parse_time[0] . ":" . $parse_time[1];
                $hour = (int)$parse_time[0];
                $minute = (int)$parse_time[1];
                $period_month = $month;
                $period_day = $day;
                $period_hour = $hour;
                $period_minute = $minute;
            }


            $udata = UserManager::LoginData("admin");

            $insert = $this->model->db->insert("users_reminders", [
                'owner_id'        => $udata["id"],
                'note'            => $note,
                'creation_time'   => DateManager::Now(),
                'status'          => $status,
                'period'          => $period,
                'period_datetime' => $period_datetime,
                'period_month'    => $period_month,
                'period_day'      => $period_day,
                'period_hour'     => $period_hour,
                'period_minute'   => $period_minute,
            ]);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tools/error14"),
                ]));

            $insert_id = $this->model->db->lastID();


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/tools/success9"),
            ]);

            User::addAction($udata["id"], "added", "a-new-reminder-added", [
                'id' => $insert_id,
            ]);


        }

        private function edit_reminder()
        {
            $this->takeDatas(["language"]);

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "rnumbers");

            if (!$id) return false;

            $udata = UserManager::LoginData("admin");

            $reminder = $this->model->db->select()->from("users_reminders")->where("id", "=", $id, "&&");
            $reminder->where("owner_id", "=", $udata["id"]);
            $reminder = $reminder->build() ? $reminder->getAssoc() : false;
            if (!$reminder) return false;

            $note = Filter::POST("note");
            $status = Filter::init("POST/status", "route");
            $period = Filter::init("POST/period", "route");
            $period_datetime = Filter::init("POST/period_datetime", "hclear");
            $period_month = -1;
            $period_day = -1;
            $period_hour = -1;
            $period_minute = -1;
            $period_hour_minute = Filter::POST("period_hour_minute");
            if ($period_datetime) $period_datetime = str_replace("T", " ", $period_datetime);

            if ((int)Filter::init("POST/period_month", "amount") && $period == "recurring")
                $period_month = (int)Filter::init("POST/period_month", "numbers");

            if ((int)Filter::init("POST/period_day", "amount") && $period == "recurring")
                $period_day = (int)Filter::init("POST/period_day", "numbers");

            if ($period_hour_minute && $period == "recurring") {
                $split_hour_minute = explode(":", $period_hour_minute);
                if (isset($split_hour_minute[0])) $period_hour = (int)$split_hour_minute[0];
                if (isset($split_hour_minute[1])) $period_minute = (int)$split_hour_minute[1];
            }

            if ($period != "onetime" && $period_datetime) $period_datetime = DateManager::ata();


            if (($period == "onetime" && !$period_datetime) || ($period == "recurring" && !($period_hour > -1 && $period_minute > -1)))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tools/error15"),
                ]));

            if ($period == "onetime") {
                $parse = explode(" ", $period_datetime);
                $parse_date = explode("-", $parse[0]);
                $parse_time = explode(":", $parse[1]);
                $date = $parse_date[0] . "-" . $parse_date[1] . "-" . $parse_date[2];
                $year = (int)$parse_date[0];
                $month = (int)$parse_date[1];
                $day = (int)$parse_date[2];
                $time = $parse_time[0] . ":" . $parse_time[1];
                $hour = (int)$parse_time[0];
                $minute = (int)$parse_time[1];
                $period_month = $month;
                $period_day = $day;
                $period_hour = $hour;
                $period_minute = $minute;
            }


            $update = $this->model->db->update("users_reminders", [
                'note'            => $note,
                'status'          => $status,
                'period'          => $period,
                'period_datetime' => $period_datetime,
                'period_month'    => $period_month,
                'period_day'      => $period_day,
                'period_hour'     => $period_hour,
                'period_minute'   => $period_minute,
            ])->where("id", "=", $id)->save();

            if (!$update)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tools/error14"),
                ]));


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/tools/success11"),
            ]);

            User::addAction($udata["id"], "alteration", "reminder-successfully-updated", [
                'id' => $id,
            ]);


        }


        private function delete_reminder()
        {

            $this->takeDatas(["language"]);

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "rnumbers");

            if (!$id) return false;

            $udata = UserManager::LoginData("admin");

            $reminder = $this->model->db->select()->from("users_reminders")->where("id", "=", $id, "&&");
            $reminder->where("owner_id", "=", $udata["id"]);
            $reminder = $reminder->build() ? $reminder->getAssoc() : false;
            if (!$reminder) return false;

            $delete = $this->model->db->delete("users_reminders")->where("id", "=", $id)->run();
            if (!$delete)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Cannot delete reminder.",
                ]));

            User::addAction($udata["id"], "delete", "reminder-deleted", [
                'id' => $id,
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/tools/success10"),
            ]);


        }

        private function change_module_log_status()
        {

            $this->takeDatas(["language"]);

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $status = Filter::init("POST/status");

            $config_sets = [];

            $config_sets["options"]["save-module-log"] = $status == 1;

            $changes = 0;

            if ($config_sets) {
                if (isset($config_sets["options"])) {
                    $options_result = Config::set("options", $config_sets["options"]);

                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                    if ($write) $changes++;
                }

                if ($changes) {
                    $adata = UserManager::LoginData("admin");
                    User::addAction($adata["id"], "alteration", "Changed save module log option");
                }
            }


        }

        private function change_error_log_situations()
        {

            $this->takeDatas(["language"]);

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $error_log = (bool)(int)Filter::init("POST/error_log");
            $error_debug = (bool)(int)Filter::init("POST/error_debug");
            $development = (bool)(int)Filter::init("POST/development");

            $str = '<?php
    defined(\'CORE_FOLDER\') OR exit(\'You can not get in here!\');
    define("DEMO_MODE",' . (DEMO_MODE ? "true" : "false") . ');
    define("DEVELOPMENT",' . ($development ? "true" : "false") . ');
    define("ERROR_DEBUG",' . ($error_debug ? "true" : "false") . ');
    define("LOG_SAVE",' . ($error_log ? "true" : "false") . ');';

            FileManager::file_write(CONFIG_DIR . "constants.php", $str);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "Changed system error log settings");

        }


        private function ajax_sms_logs()
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
            $udata = UserManager::LoginData("admin");
            $local_l = Config::get("general/local");

            if (!Validation::isEmpty(Filter::GET("sSearch"))) $searches['word'] = Filter::init("GET/sSearch", "hclear");

            $filteredList = $this->model->get_sms_logs($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_sms_logs_total($searches);
            $listTotal = $this->model->get_sms_logs_total($searches, true);

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                if ($filteredList) {
                    if ($filteredList)
                        foreach ($filteredList as $k => $v)
                            if ($e_c = Crypt::decode($v["content"], "*_LOG_*" . Config::get("crypt/system")))
                                $filteredList[$k]['content'] = $e_c;

                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-sms-logs", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }

        private function ajax_mail_logs()
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
            $udata = UserManager::LoginData("admin");
            $local_l = Config::get("general/local");

            if (!Validation::isEmpty(Filter::GET("sSearch"))) $searches['word'] = Filter::init("GET/sSearch", "hclear");

            $filteredList = $this->model->get_mail_logs($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_mail_logs_total($searches);
            $listTotal = $this->model->get_mail_logs_total($searches, true);

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                if ($filteredList) {

                    if ($filteredList)
                        foreach ($filteredList as $k => $v)
                            if ($e_c = Crypt::decode($v["content"], "*_LOG_*" . Config::get("crypt/system")))
                                $filteredList[$k]['content'] = $e_c;

                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-mail-logs", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function ajax_actions_module_log()
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

            $filteredList = $this->model->get_actions("module-log", $searches, $orders, $start, $end);
            $filterTotal = $this->model->get_actions_total("module-log", $searches);
            $listTotal = $this->model->get_actions_total("module-log");

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
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-module-logs", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }

        private function ajax_actions_error_log()
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

            $filteredList = $this->model->get_actions("error-log", $searches, $orders, $start, $end);
            $filterTotal = $this->model->get_actions_total("error-log", $searches);
            $listTotal = $this->model->get_actions_total("error-log");

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
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-error-logs", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }

        private function ajax_actions_login_log()
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
            $searches["type"] = Filter::init("GET/type", "letters");

            $filteredList = $this->model->get_actions("login-log", $searches, $orders, $start, $end);
            $filterTotal = $this->model->get_actions_total("login-log", $searches);
            if (isset($searches["word"])) unset($searches["word"]);
            $listTotal = $this->model->get_actions_total("login-log", $searches);

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            $this->addData("l_type", $searches["type"]);

            Helper::Load("Browser");

            if ($listTotal) {
                if ($filteredList) {
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-login-logs", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function operationMain($operation)
        {
            if ($operation == "clear_notifications" && Admin::isPrivilege("TOOLS_ACTIONS")) return $this->clear_notifications();
            if ($operation == "clear_actions" && Admin::isPrivilege("TOOLS_ACTIONS")) return $this->clear_actions();
            if ($operation == "get_contact_list") return $this->get_contact_list();
            if ($operation == "change_newsletter" && Admin::isPrivilege(["TOOLS_BULK_MAIL", "TOOLS_BULK_SMS"])) return $this->change_newsletter();
            if ($operation == "submit_bulk_mail" && Admin::isPrivilege(["TOOLS_BULK_MAIL"])) return $this->submit_bulk_mail();
            if ($operation == "submit_bulk_mail_test" && Admin::isPrivilege(["TOOLS_BULK_MAIL"])) return $this->submit_bulk_mail_test();
            if ($operation == "save_notification_template" && Admin::isPrivilege(["TOOLS_BULK_MAIL", "TOOLS_BULK_SMS"])) return $this->save_notification_template();
            if ($operation == "remove_notification_template" && Admin::isPrivilege(["TOOLS_BULK_MAIL", "TOOLS_BULK_SMS"])) return $this->remove_notification_template();

            elseif ($operation == "backup-database" && Admin::isPrivilege(["TOOLS_IMPORTS"]))
                return $this->backup_database();

            if ($operation == "submit_bulk_sms" && Admin::isPrivilege(["TOOLS_BULK_SMS"])) return $this->submit_bulk_sms();
            if ($operation == "submit_bulk_sms_test" && Admin::isPrivilege(["TOOLS_BULK_SMS"])) return $this->submit_bulk_sms_test();
            if ($operation == "get_addon_content" && Admin::isPrivilege(["TOOLS_ADDONS"])) return $this->get_addon_content();
            if ($operation == "set_addon_status" && Admin::isPrivilege(["TOOLS_ADDONS"])) return $this->set_addon_status();
            if ($operation == "delete_addon" && Admin::isPrivilege(["TOOLS_ADDONS"])) return $this->delete_addon();
            if ($operation == "add_new_task" && Admin::isPrivilege(["TOOLS_TASKS"])) return $this->add_new_task();
            if ($operation == "edit_task" && Admin::isPrivilege(["TOOLS_TASKS"])) return $this->edit_task();
            if ($operation == "task_apply_operation" && Admin::isPrivilege(["TOOLS_TASKS"])) return $this->task_apply_operation();
            if ($operation == "add_new_reminder" && Admin::isPrivilege(["TOOLS_REMINDERS"])) return $this->add_new_reminder();
            if ($operation == "edit_reminder" && Admin::isPrivilege(["TOOLS_REMINDERS"])) return $this->edit_reminder();
            if ($operation == "delete_reminder" && Admin::isPrivilege(["TOOLS_REMINDERS"])) return $this->delete_reminder();
            if ($operation == "ajax-sms-logs" && Admin::isPrivilege(["TOOLS_SMS_LOGS"])) return $this->ajax_sms_logs();
            if ($operation == "ajax-mail-logs" && Admin::isPrivilege(["TOOLS_MAIL_LOGS"])) return $this->ajax_mail_logs();
            if ($operation == "actions-module-log.json" && Admin::isPrivilege(["TOOLS_ACTIONS"]))
                return $this->ajax_actions_module_log();
            if ($operation == "actions-error-log.json" && Admin::isPrivilege(["TOOLS_ACTIONS"]))
                return $this->ajax_actions_error_log();
            if ($operation == "change_module_log_status" && Admin::isPrivilege(["TOOLS_ACTIONS"]))
                return $this->change_module_log_status();
            if ($operation == "change_error_log_situations" && Admin::isPrivilege(["TOOLS_ACTIONS"]))
                return $this->change_error_log_situations();
            if ($operation == "actions-login-log.json" && Admin::isPrivilege(["TOOLS_ACTIONS"]))
                return $this->ajax_actions_login_log();

            echo "Not found operation: " . $operation;
        }


        private function pageMain($name = '')
        {
            if ($name == "addons") return $this->addons();
            if ($name == "bulk-mail" && Admin::isPrivilege(["TOOLS_BULK_MAIL"])) return $this->bulk_mail();
            if ($name == "bulk-sms" && Admin::isPrivilege(["TOOLS_BULK_SMS"])) return $this->bulk_sms();
            if ($name == "import-via-module" && Admin::isPrivilege(["TOOLS_IMPORTS"])) return $this->import_via_module();
            if ($name == "tasks" && Admin::isPrivilege(["TOOLS_TASKS"])) return $this->tasks();
            if ($name == "reminders" && Admin::isPrivilege(["TOOLS_REMINDERS"])) return $this->reminders();
            if ($name == "btk-reports" && Admin::isPrivilege(["TOOLS_BTK_REPORTS"])) return $this->btk_reports();
            if ($name == "actions" && Admin::isPrivilege(["TOOLS_ACTIONS"])) return $this->actions();
            if ($name == "sms-logs" && Admin::isPrivilege(["TOOLS_SMS_LOGS"])) return $this->sms_logs();
            if ($name == "mail-logs" && Admin::isPrivilege(["TOOLS_MAIL_LOGS"])) return $this->mail_logs();
            echo "Not found main: " . $name;
        }


        private function addons()
        {

            $udata = UserManager::LoginData("admin");
            $udata = array_merge($udata, User::getData($udata["id"], 'privilege', 'array'));

            $module = Filter::route(isset($this->params[1]) ? $this->params[1] : false);

            if ($module) {
                $module_data = Modules::Load("Addons", $module);

                if ($module_data && !isset($module_data["config"]["status"]) || !$module_data["config"]["status"]) $module = false;
                if ($module && !in_array($udata["privilege"], $module_data["config"]["access_ps"])) $module = false;
                if ($module) {
                    $m_name = $module;
                    $module = new $m_name;
                    $module->area_link = $this->AdminCRLink("tools-2", ["addons", $m_name]);
                }
            }

            if (!$module && !Admin::isPrivilege("TOOLS_ADDONS")) return false;

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

            $page_title = '';
            $module_content = '';
            $module_breadcrumbs = [];

            if ($module) {
                $page_title = $module->lang['meta']['name'];
                if (method_exists($module, 'adminArea'))
                    $adminArea = $module->adminArea();
                else
                    $adminArea = $module->main();

                if (is_array($adminArea)) {
                    if (isset($adminArea["page_title"])) $page_title = $adminArea["page_title"];
                    if (isset($adminArea["content"])) $module_content = $adminArea["content"];
                    if (isset($adminArea["breadcrumbs"])) $module_breadcrumbs = $adminArea["breadcrumbs"];
                } else
                    $module_content = $adminArea;

            }


            $links = [
                'controller' => $this->AdminCRLink("tools-1", ["addons"]),
            ];

            if ($module) $links['controller'] = $this->AdminCRLink("tools-2", ["addons", $m_name]);

            $this->addData("links", $links);

            if ($module)
                $this->addData("meta", __("admin/tools/meta-detail-addon", ['{module}' => $module->lang["meta"]["name"]]));
            else
                $this->addData("meta", __("admin/tools/meta-addons"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            if ($module) {
                array_push($breadcrumbs, [
                    'link'  => $this->AdminCRLink("tools-1", ["addons"]),
                    'title' => __("admin/tools/breadcrumb-addons"),
                ]);

                if ($module_breadcrumbs)
                    $breadcrumbs = array_merge($breadcrumbs, $module_breadcrumbs);
                else
                    array_push($breadcrumbs, [
                        'link'  => false,
                        'title' => $module->lang['meta']['name'],
                    ]);
            } else
                array_push($breadcrumbs, [
                    'link'  => false,
                    'title' => __("admin/tools/breadcrumb-addons"),
                ]);


            $this->addData("breadcrumb", $breadcrumbs);

            if (!$module) {
                $get_modules = Modules::Load("Addons", 'All', true);
                $modules = [];

                if ($get_modules) {
                    foreach ($get_modules as $key => $row) {
                        if (isset($row["config"]) && isset($row["lang"])) {
                            $row["rank"] = $row["config"]["created_at"];
                            if (!isset($row["config"]["meta"]["logo"])) $row["config"]["meta"]["logo"] = 'logo.png';
                            $modules[$key] = $row;
                        }
                    }
                }
                if ($modules) Utility::sksort($modules, "rank");


                $this->addData("modules", $modules);
            }

            $this->addData("module", $module);

            $this->addData("module_content", $module_content);
            $this->addData("page_title", $page_title);

            $this->view->chose("admin")->render("addons", $this->data);
        }


        private function bulk_mail()
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
                'controller' => $this->AdminCRLink("tools-1", ["bulk-mail"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/tools/meta-bulk-mail"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/tools/breadcrumb-bulk-mail"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("user_groups", $this->model->user_groups());
            $this->addData("departments", $this->model->departments());

            $lang = Bootstrap::$lang->clang;
            $countries = $this->model->db->select("t1.id,t1.a2_iso AS code,t2.name")->from("countries AS t1")->join("LEFT", "countries_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
            $countries->where("t2.id", "IS NOT NULL", "", "&&");
            $countries->where("(SELECT COUNT(id) FROM users WHERE country=t1.id)", ">", "0");
            $countries->order_by("t1.id ASC");
            $countries = $countries->build() ? $countries->fetch_assoc() : false;


            $this->addData("countries", $countries);

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

            $this->addData("total_user_count", $this->model->get_total_user_count("email"));

            $newsletter = [];

            foreach (Bootstrap::$lang->rank_list() as $item) {
                $key = $item["key"];
                $newsletter_data = $this->model->newsletters("email", $key);
                $newsletter_data_count = sizeof($newsletter_data);
                if ($newsletter_data_count) foreach ($newsletter_data as $datum) $newsletter[$key]["data"][] = $datum["content"];
                $newsletter[$key]["count"] = $newsletter_data_count;
            }

            $this->addData("newsletter", $newsletter);

            $servers = $this->model->db->select()->from("servers");
            $servers = $servers->build() ? $servers->fetch_assoc() : [];

            $this->addData("servers", $servers);

            $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
            $order_situations = $situations["orders"];
            $user_situations = $situations["users"];
            $reminder_situations = $situations["reminders"];

            $this->addData("order_situations", $order_situations);
            $this->addData("user_situations", $user_situations);
            $this->addData("reminder_situations", $reminder_situations);

            $addon_products = [];

            $groups = $this->model->db->select("c.id,cl.title")->from("categories AS c");
            $groups->join("LEFT", "categories_lang AS cl", "cl.owner_id=c.id AND cl.lang='" . $lang . "'");
            $groups->where("cl.id", "IS NOT NULL", "", "&&");
            $groups->where("c.type", "=", "addon", "&&");
            $groups->where("c.status", "=", "active");
            $groups->order_by("c.rank ASC");
            if ($groups->build()) {
                foreach ($groups->fetch_assoc() as $g) {
                    $a_ps = $this->model->db->select("a.id,al.name")->from("products_addons AS a");
                    $a_ps->join("LEFT", "products_addons_lang AS al", "al.owner_id=a.id AND al.lang='" . $lang . "'");
                    $a_ps->where("al.id", "IS NOT NULL", "", "&&");
                    $a_ps->where("a.category", "=", $g["id"]);
                    $a_ps->order_by("a.rank ASC");
                    $a_ps = $a_ps->build() ? $a_ps->fetch_assoc() : [];
                    if ($a_ps) {
                        $addon_products[$g["id"]] = [
                            'id'    => $g["id"],
                            'title' => $g["title"],
                            'data'  => $a_ps,
                        ];
                    }
                }
            }

            $this->addData("addon_products", $addon_products);

            $bring = Filter::init("REQUEST/bring", "route");
            $show = Filter::init("REQUEST/show", "route");

            if ($show) {
                $without_products_users = $this->model->db->select("COUNT(usi.id) AS total")->from("users_informations AS usi");
                $without_products_users->where("(SELECT COUNT(id) FROM " . $this->model->pfx . "users_products WHERE owner_id=usi.owner_id)", "<", "1", "&&");
                $without_products_users->where("usi.name", "=", "email_notifications", "&&");
                $without_products_users->where("usi.content", ">", "0");
                $without_products_users = $without_products_users->build() ? $without_products_users->getObject()->total : 0;

                $birthday_marketing_users = $this->model->db->select("COUNT(usi.id) AS total")->from("users_informations AS usi");
                $birthday_marketing_users->join("INNER", "users_informations AS usi2", "usi2.owner_id = usi.owner_id AND usi2.name='birthday' AND MONTH(usi2.content) = '" . DateManager::Now("m") . "' AND DAY(usi2.content) = '" . DateManager::Now("d") . "'");
                $birthday_marketing_users->where("usi2.id", "IS NOT NULL", "", "&&");
                $birthday_marketing_users->where("usi.name", "=", "email_notifications", "&&");
                $birthday_marketing_users->where("usi.content", "=", "1");
                $birthday_marketing_users = $birthday_marketing_users->build() ? $birthday_marketing_users->getObject()->total : 0;


                $this->addData("without_products_users", $without_products_users);
                $this->addData("birthday_marketing_users", $birthday_marketing_users);
            }

            $rows = $this->model->db->select()->from("notification_templates");
            $rows->where("template_type", "=", "mail");
            $rows->order_by("id DESC");
            $rows = $rows->build() ? $rows->fetch_assoc() : [];

            if ($rows) {
                $rows_x = [];
                foreach ($rows as $row) {
                    $row['user_groups'] = Utility::jdecode($row['user_groups'], true);
                    $row['departments'] = Utility::jdecode($row['departments'], true);
                    $row['countries'] = Utility::jdecode($row['countries'], true);
                    $row['languages'] = Utility::jdecode($row['languages'], true);
                    $row['services'] = Utility::jdecode($row['services'], true);
                    $row['servers'] = Utility::jdecode($row['servers'], true);
                    $row['addons'] = Utility::jdecode($row['addons'], true);
                    $row['services_status'] = Utility::jdecode($row['services_status'], true);
                    $row['client_status'] = Utility::jdecode($row['client_status'], true);

                    $rows_x[$row["id"]] = $row;
                }
                $rows = $rows_x;
            }

            $this->addData("templates", $rows);

            $this->addData("bring", $bring);
            $this->addData("show", $show);

            $header_title = __("admin/tools/page-bulk-mail");

            if ($show == "send") {
                $header_title = __("admin/tools/bulk-send");
                $end_k = sizeof($breadcrumbs) - 1;
                $breadcrumbs[$end_k]['link'] = $links["controller"];
                $breadcrumbs[] = [
                    'link'  => '',
                    'title' => __("admin/tools/bulk-send"),
                ];
                $this->addData("breadcrumb", $breadcrumbs);
            } elseif ($show == "create_campaign") {
                $header_title = __("admin/tools/bulk-create-template");
                $end_k = sizeof($breadcrumbs) - 1;
                $breadcrumbs[$end_k]['link'] = $links["controller"];
                $breadcrumbs[] = [
                    'link'  => '',
                    'title' => __("admin/tools/bulk-create-template"),
                ];
                $this->addData("breadcrumb", $breadcrumbs);
            } elseif ($show == "edit_campaign") {
                $id = (int)Filter::init("GET/id", "numbers");

                $header_title = $rows[$id]["template_name"];
                $end_k = sizeof($breadcrumbs) - 1;
                $breadcrumbs[$end_k]['link'] = $links["controller"];
                $breadcrumbs[] = [
                    'link'  => '',
                    'title' => $rows[$id]["template_name"],
                ];
                $this->addData("breadcrumb", $breadcrumbs);
            }


            $this->addData("header_title", $header_title);

            $this->view->chose("admin")->render("bulk-mail", $this->data);
        }


        private function bulk_sms()
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
                'controller' => $this->AdminCRLink("tools-1", ["bulk-sms"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/tools/meta-bulk-sms"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/tools/breadcrumb-bulk-sms"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("user_groups", $this->model->user_groups());
            $this->addData("departments", $this->model->departments());

            $lang = Bootstrap::$lang->clang;
            $countries = $this->model->db->select("t1.id,t1.a2_iso AS code,t2.name")->from("countries AS t1")->join("LEFT", "countries_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
            $countries->where("t2.id", "IS NOT NULL", "", "&&");
            $countries->where("(SELECT COUNT(id) FROM users WHERE country=t1.id)", ">", "0");
            $countries->order_by("t1.id ASC");
            $countries = $countries->build() ? $countries->fetch_assoc() : false;


            $this->addData("countries", $countries);

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

            $this->addData("total_user_count", $this->model->get_total_user_count("gsm"));

            $servers = $this->model->db->select()->from("servers");
            $servers = $servers->build() ? $servers->fetch_assoc() : [];

            $this->addData("servers", $servers);

            $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
            $order_situations = $situations["orders"];
            $user_situations = $situations["users"];
            $reminder_situations = $situations["reminders"];

            $this->addData("order_situations", $order_situations);
            $this->addData("user_situations", $user_situations);
            $this->addData("reminder_situations", $reminder_situations);

            $addon_products = [];

            $groups = $this->model->db->select("c.id,cl.title")->from("categories AS c");
            $groups->join("LEFT", "categories_lang AS cl", "cl.owner_id=c.id AND cl.lang='" . $lang . "'");
            $groups->where("cl.id", "IS NOT NULL", "", "&&");
            $groups->where("c.type", "=", "addon", "&&");
            $groups->where("c.status", "=", "active");
            $groups->order_by("c.rank ASC");
            if ($groups->build()) {
                foreach ($groups->fetch_assoc() as $g) {
                    $a_ps = $this->model->db->select("a.id,al.name")->from("products_addons AS a");
                    $a_ps->join("LEFT", "products_addons_lang AS al", "al.owner_id=a.id AND al.lang='" . $lang . "'");
                    $a_ps->where("al.id", "IS NOT NULL", "", "&&");
                    $a_ps->where("a.category", "=", $g["id"]);
                    $a_ps->order_by("a.rank ASC");
                    $a_ps = $a_ps->build() ? $a_ps->fetch_assoc() : [];
                    if ($a_ps) {
                        $addon_products[$g["id"]] = [
                            'id'    => $g["id"],
                            'title' => $g["title"],
                            'data'  => $a_ps,
                        ];
                    }
                }
            }

            $this->addData("addon_products", $addon_products);

            $bring = Filter::init("REQUEST/bring", "route");
            $show = Filter::init("REQUEST/show", "route");


            if ($show) {
                $without_products_users = $this->model->db->select("COUNT(usi.id) AS total")->from("users_informations AS usi");
                $without_products_users->where("(SELECT COUNT(id) FROM " . $this->model->pfx . "users_products WHERE owner_id=usi.owner_id)", "<", "1", "&&");
                $without_products_users->where("usi.name", "=", "sms_notifications", "&&");
                $without_products_users->where("usi.content", ">", "0");
                $without_products_users = $without_products_users->build() ? $without_products_users->getObject()->total : 0;

                $birthday_marketing_users = $this->model->db->select("COUNT(usi.id) AS total")->from("users_informations AS usi");
                $birthday_marketing_users->join("INNER", "users_informations AS usi2", "usi2.owner_id = usi.owner_id AND usi2.name='birthday' AND MONTH(usi2.content) = '" . DateManager::Now("m") . "' AND DAY(usi2.content) = '" . DateManager::Now("d") . "'");
                $birthday_marketing_users->where("usi2.id", "IS NOT NULL", "", "&&");
                $birthday_marketing_users->where("usi.name", "=", "sms_notifications", "&&");
                $birthday_marketing_users->where("usi.content", "=", "1");
                $birthday_marketing_users = $birthday_marketing_users->build() ? $birthday_marketing_users->getObject()->total : 0;

                $this->addData("without_products_users", $without_products_users);
                $this->addData("birthday_marketing_users", $birthday_marketing_users);
            }

            $rows = $this->model->db->select()->from("notification_templates");
            $rows->where("template_type", "=", "sms");
            $rows->order_by("id DESC");
            $rows = $rows->build() ? $rows->fetch_assoc() : [];

            if ($rows) {
                $rows_x = [];
                foreach ($rows as $row) {
                    $row['user_groups'] = Utility::jdecode($row['user_groups'], true);
                    $row['departments'] = Utility::jdecode($row['departments'], true);
                    $row['countries'] = Utility::jdecode($row['countries'], true);
                    $row['languages'] = Utility::jdecode($row['languages'], true);
                    $row['services'] = Utility::jdecode($row['services'], true);
                    $row['servers'] = Utility::jdecode($row['servers'], true);
                    $row['addons'] = Utility::jdecode($row['addons'], true);
                    $row['services_status'] = Utility::jdecode($row['services_status'], true);
                    $row['client_status'] = Utility::jdecode($row['client_status'], true);

                    $rows_x[$row["id"]] = $row;
                }
                $rows = $rows_x;
            }

            $this->addData("templates", $rows);


            $this->addData("bring", $bring);
            $this->addData("show", $show);

            $header_title = __("admin/tools/page-bulk-sms");

            if ($show == "send") {
                $header_title = __("admin/tools/bulk-send");
                $end_k = sizeof($breadcrumbs) - 1;
                $breadcrumbs[$end_k]['link'] = $links["controller"];
                $breadcrumbs[] = [
                    'link'  => '',
                    'title' => __("admin/tools/bulk-send"),
                ];
                $this->addData("breadcrumb", $breadcrumbs);
            } elseif ($show == "create_campaign") {
                $header_title = __("admin/tools/bulk-create-template");
                $end_k = sizeof($breadcrumbs) - 1;
                $breadcrumbs[$end_k]['link'] = $links["controller"];
                $breadcrumbs[] = [
                    'link'  => '',
                    'title' => __("admin/tools/bulk-create-template"),
                ];
                $this->addData("breadcrumb", $breadcrumbs);
            } elseif ($show == "edit_campaign") {
                $id = (int)Filter::init("GET/id", "numbers");

                $header_title = $rows[$id]["template_name"];
                $end_k = sizeof($breadcrumbs) - 1;
                $breadcrumbs[$end_k]['link'] = $links["controller"];
                $breadcrumbs[] = [
                    'link'  => '',
                    'title' => $rows[$id]["template_name"],
                ];
                $this->addData("breadcrumb", $breadcrumbs);
            }


            $this->addData("header_title", $header_title);

            $this->view->chose("admin")->render("bulk-sms", $this->data);
        }


        private function actions()
        {

            $type = isset($this->params[1]) ? $this->params[1] : false;

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

            if ($type == "module-log" || $type == "error-log" || $type == "login-log") {
                $c_link = $this->AdminCRLink("tools-2", ["actions", $type]);
                $c_ajax_link = $c_link . "?operation=actions-" . $type . ".json";
            } else {
                $c_link = $this->AdminCRLink("tools-1", ["actions"]);
                $c_ajax_link = $this->AdminCRLink("users") . "?operation=actions.json&admins=true";
            }

            $links = [
                'controller'   => $c_link,
                'ajax-actions' => $c_ajax_link,
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/tools/meta-actions" . ($type ? '-' . $type : '')));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/tools/breadcrumb-actions" . ($type ? '-' . $type : '')),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            if ($type == "module-log" || $type == "error-log" || $type == "login-log")
                $this->view->chose("admin")->render($type . "s", $this->data);
            else
                $this->view->chose("admin")->render("transaction-logs", $this->data);
        }

        private function sms_logs()
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
                'controller' => $this->AdminCRLink("tools-1", ["sms-logs"]),
                'ajax'       => $this->AdminCRLink("tools-1", ["sms-logs"]) . "?operation=ajax-sms-logs",
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/tools/meta-sms-logs"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/tools/breadcrumb-sms-logs"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->view->chose("admin")->render("sms-logs", $this->data);
        }

        private function mail_logs()
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
                'controller' => $this->AdminCRLink("tools-1", ["mail-logs"]),
                'ajax'       => $this->AdminCRLink("tools-1", ["mail-logs"]) . "?operation=ajax-mail-logs",
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/tools/meta-mail-logs"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/tools/breadcrumb-mail-logs"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->view->chose("admin")->render("mail-logs", $this->data);
        }


        private function btk_reports_json()
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

            $type = Filter::init("GET/type", "numbers");
            if (!$type) $type = 1;

            $startx = Filter::init("GET/start", "numbers", "\-");
            $endx = Filter::init("GET/end", "numbers", "\-");
            if (!Validation::isDate($startx)) $startx = false;
            if (!Validation::isDate($endx)) $endx = false;
            if ($type) $searches["type"] = $type;
            if (!($startx && $endx)) {
                $startx = DateManager::old_date(["month" => 1], "Y-m") . "-01";
                $endx = DateManager::format("Y-m-t", $startx);
            }
            if ($startx && $endx) {
                if ($startx) $searches["start"] = $startx;
                if ($endx) $searches["end"] = $endx;
            }

            $filteredList = $this->model->get_btk_reports_list($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_btk_reports_list_total($searches);
            $listTotal = $filterTotal;

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
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-btk-reports", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function btk_reports_csv()
        {
            $searches = [];

            $type = Filter::init("GET/type", "numbers");
            if (!$type) $type = 1;

            if ($type == 1)
                $name = "alan-adlari-ve-hostingler";
            elseif ($type == 2)
                $name = "sadece-alan-adlari";

            $startx = Filter::init("GET/start", "numbers", "\-");
            $endx = Filter::init("GET/end", "numbers", "\-");
            if (!Validation::isDate($startx)) $startx = false;
            if (!Validation::isDate($endx)) $endx = false;
            if ($type) $searches["type"] = $type;
            if (!($startx && $endx)) {
                $startx = DateManager::old_date(["month" => 1], "Y-m") . "-01";
                $endx = DateManager::format("Y-m-t", $startx);
            }
            if ($startx && $endx) {
                if ($startx) $searches["start"] = $startx;
                if ($endx) $searches["end"] = $endx;
            }

            $name .= "-" . DateManager::format("d-m-Y", $startx) . "_" . DateManager::format("d-m-Y", $endx);

            $list = $this->model->get_btk_reports_list($searches, false, 0, 2000);

            //$items  = [];

            if (!$list) return false;

            foreach ($list as $i => $row) {
                $options = Utility::jdecode($row["options"], true);
                //$item   = [];

                $cdate = DateManager::format(Config::get("options/date-format"), $row["cdate"]);
                $duedate = DateManager::format(Config::get("options/date-format"), $row["duedate"]);
                $user_name = $row["user_name"];
                $user_data = User::getData($row["user_id"], "id,email", "array");
                $user_data = array_merge($user_data, User::getInfo($row["user_id"], ["phone", "landline_phone"]));

                $phone = null;
                if ($user_data["phone"]) $phone = "+" . $user_data["phone"];
                if (!$phone && $user_data["landline_phone"]) $phone = "+" . $user_data["landline_phone"];
                if (!$phone) $phone = "*";

                $user_detail = $user_name;
                if ($row["user_company_name"]) $user_detail .= " (" . $row["user_company_name"] . ")";

                $user_detail = str_replace(",", " ", $user_detail);


                if ($row["type"] == "domain") $domain = $row["name"];
                elseif ($row["type"] == "hosting") $domain = $options["domain"];

                echo $domain . ",";
                echo Filter::transliterate($user_detail) . ",";
                echo $phone . ",";
                echo $user_data["email"] . ",";
                echo $cdate . ",";
                echo $duedate . EOL;
            }


            header('Content-type: text/csv;charset=utf8');
            header('Content-Disposition: attachment;filename="' . $name . '.csv"');
            header('Cache-Control: max-age=0');

// If you're serving to IE over SSL, then the following may be needed
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
            header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
            header('Pragma: public'); // HTTP/1.0

            exit;

        }


        private function btk_reports()
        {

            $param = isset($this->params[1]) ? $this->params[1] : false;

            if ($param == "listing.json") return $this->btk_reports_json();
            if ($param == "export.csv") return $this->btk_reports_csv();

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
                'controller' => $this->AdminCRLink("tools-1", ["btk-reports"]),
                'ajax'       => $this->AdminCRLink("tools-2", ["btk-reports", "listing.json"]),
                'csv-export' => $this->AdminCRLink("tools-2", ["btk-reports", "export.csv"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", ["title" => "BTK Rapor Yönetimi"]);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => "BTK Rapor Yönetimi",
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $type = Filter::init("GET/type", "numbers");
            if (!$type) $type = 1;

            $start = Filter::init("GET/start", "numbers", "\-");
            $end = Filter::init("GET/end", "numbers", "\-");
            if (!Validation::isDate($start)) $start = false;
            if (!Validation::isDate($end)) $end = false;

            if (!($start && $end)) {
                $start = DateManager::old_date(["month" => 1], "Y-m") . "-01";
                $end = DateManager::format("Y-m-t", $start);
            }

            if (isset($type)) $this->addData("type", $type);
            if (isset($start) && $start) $this->addData("start", $start);
            if (isset($end) && $end) $this->addData("end", $end);

            $filteredTotal = $this->model->get_btk_reports_list_total([
                'type'  => $type,
                'start' => $start,
                'end'   => $end,
            ]);

            $this->addData("filteredTotal", $filteredTotal);


            $this->view->chose("admin")->render("btk-reports", $this->data);
        }

        private function tasks()
        {
            $param = isset($this->params[1]) ? $this->params[1] : false;

            $links = [
                'controller' => $this->AdminCRLink("tools-1", ["tasks"]),
                'ajax-tasks' => $this->AdminCRLink("tools-2", ["tasks", "listing.json"]),
                'create'     => $this->AdminCRLink("tools-2", ["tasks", "create"]),
            ];


            $is_full_admin = Admin::isPrivilege(["ADMIN_CONFIGURE"]);

            if ($param) {

                if ($param == "listing.json") {
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
                    $udata = UserManager::LoginData("admin");
                    $local_l = Config::get("general/local");
                    $my_dids = [];
                    $departments = [];

                    Helper::Load(["Tickets"]);
                    $get_departments = Tickets::get_departments($local_l, "t1.id,t1.appointees,t2.name");
                    if ($get_departments) {
                        foreach ($get_departments as $department) {
                            $appointess = $department["appointees"] ? explode(",", $department["appointees"]) : [];
                            $departments[$department["id"]] = $department;
                            if (in_array($udata["id"], $appointess)) $my_dids[] = $department["id"];
                        }
                    }

                    $searches["owner_id"] = $udata["id"];
                    $searches["my_departments"] = $my_dids;
                    if ($is_full_admin) $searches["is_full_admin"] = true;

                    if (!Validation::isEmpty(Filter::GET("sSearch"))) $searches['word'] = Filter::init("GET/sSearch", "hclear");

                    $filteredList = $this->model->get_tasks($searches, $orders, $start, $end);
                    $filterTotal = $this->model->get_tasks_total($searches);
                    $listTotal = $this->model->get_tasks_total($searches, true);

                    $this->takeDatas("language");

                    $output = array_merge($output, [
                        "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                        "iTotalRecords"        => $listTotal,
                        "iTotalDisplayRecords" => $filterTotal,
                        "aaData"               => [],
                    ]);

                    if ($listTotal) {

                        $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                        $situations = $situations["tasks"];

                        if ($filteredList) {
                            $this->addData("udata", $udata);
                            $this->addData("departments", $departments);
                            $this->addData("is_full_admin", $is_full_admin);
                            $this->addData("situations", $situations);
                            $this->addData("list", $filteredList);
                            $output["aaData"] = $this->view->chose("admin")->render("ajax-tasks", $this->data, false, true);
                        }
                    }

                    echo Utility::jencode($output);
                } elseif ($param == "create") {

                    $this->addData("is_full_admin", $is_full_admin);

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

                    $links["select-users.json"] = $this->AdminCRLink("orders") . "?operation=user-list.json";

                    $this->addData("links", $links);

                    $this->addData("meta", [
                        'title' => __("admin/tools/tasks-create"),
                    ]);

                    $breadcrumbs = [
                        [
                            'link'  => $this->AdminCRLink("dashboard"),
                            'title' => __("admin/index/breadcrumb-name"),
                        ],
                    ];

                    array_push($breadcrumbs, [
                        'link'  => $this->AdminCRLink("tools-1", ["tasks"]),
                        'title' => __("admin/tools/breadcrumb-tasks"),
                    ]);

                    array_push($breadcrumbs, [
                        'link'  => false,
                        'title' => __("admin/tools/tasks-create"),
                    ]);

                    $this->addData("breadcrumb", $breadcrumbs);

                    $local_l = Config::get("general/local");

                    $admins = Models::$init->db->select("id,full_name")->from("users")->where("type", "=", "admin", "&&");
                    $admins->where("status", "=", "active");
                    $admins->order_by("id ASC");
                    $admins = $admins->build() ? $admins->fetch_assoc() : false;
                    $this->addData("admins", $admins);

                    Helper::Load(["Tickets"]);

                    $departments = Tickets::get_departments($local_l, "t1.id,t2.name");
                    $this->addData("departments", $departments);

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $situations = $situations["tasks"];

                    $this->addData("situations", $situations);


                    $this->view->chose("admin")->render("add-task", $this->data);

                } elseif ($param == "detail") {

                    $id = (int)Filter::init("GET/id", "rnumbers");
                    if (!$id) return false;

                    $udata = UserManager::LoginData("admin");
                    $local_l = Config::get("general/local");
                    $my_dids = [];
                    $departments = [];

                    Helper::Load(["Tickets"]);
                    $get_departments = Tickets::get_departments($local_l, "t1.id,t1.appointees,t2.name");
                    if ($get_departments) {
                        foreach ($get_departments as $department) {
                            $appointess = $department["appointees"] ? explode(",", $department["appointees"]) : [];
                            $departments[$department["id"]] = $department;
                            if (in_array($udata["id"], $appointess)) $my_dids[] = $department["id"];
                        }
                    }


                    $task = $this->model->db->select()->from("users_tasks AS t1")->where("t1.id", "=", $id, "&&");
                    if ($is_full_admin) {
                        $task->where("t1.owner_id", "!=", "0");
                    } else {
                        $task->where("(");
                        if ($my_dids) {
                            foreach ($my_dids as $my_did) {
                                $task->where("FIND_IN_SET('" . $my_did . "',t1.departments)", "", "", "||");
                            }
                        }
                        $task->where("t1.owner_id", "=", $udata["id"], "||");
                        $task->where("t1.admin_id", "=", $udata["id"], "");
                        $task->where(")");
                    }

                    $task = $task->build() ? $task->getAssoc() : false;
                    if (!$task) return false;

                    $this->addData("task", $task);

                    $this->addData("is_full_admin", $is_full_admin);

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

                    $links["select-users.json"] = $this->AdminCRLink("orders") . "?operation=user-list.json";

                    $this->addData("links", $links);

                    $this->addData("meta", [
                        'title' => $task["title"],
                    ]);

                    $breadcrumbs = [
                        [
                            'link'  => $this->AdminCRLink("dashboard"),
                            'title' => __("admin/index/breadcrumb-name"),
                        ],
                    ];

                    array_push($breadcrumbs, [
                        'link'  => $this->AdminCRLink("tools-1", ["tasks"]),
                        'title' => __("admin/tools/breadcrumb-tasks"),
                    ]);

                    array_push($breadcrumbs, [
                        'link'  => false,
                        'title' => $task["title"],
                    ]);

                    $this->addData("breadcrumb", $breadcrumbs);

                    $local_l = Config::get("general/local");

                    $admins = Models::$init->db->select("id,full_name")->from("users")->where("type", "=", "admin", "&&");
                    $admins->where("status", "=", "active");
                    $admins->order_by("id ASC");
                    $admins = $admins->build() ? $admins->fetch_assoc() : false;
                    $this->addData("admins", $admins);

                    Helper::Load(["Tickets"]);

                    $departments = Tickets::get_departments($local_l, "t1.id,t2.name");
                    $this->addData("departments", $departments);

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $situations = $situations["tasks"];

                    $this->addData("situations", $situations);

                    $user = [];

                    if ($task["user_id"]) {
                        $user = User::getData($task["user_id"], "id,full_name,company_name", "array");
                    }
                    $this->addData("user", $user);


                    if ($is_full_admin || $task["owner_id"] == $udata["id"])
                        $this->view->chose("admin")->render("edit-task", $this->data);
                    else
                        $this->view->chose("admin")->render("detail-task", $this->data);

                }
                exit;
            }

            $this->addData("is_full_admin", $is_full_admin);

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

            $this->addData("links", $links);

            $this->addData("meta", __("admin/tools/meta-tasks"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/tools/breadcrumb-tasks"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->view->chose("admin")->render("tasks", $this->data);
        }

        private function reminders()
        {
            $param = isset($this->params[1]) ? $this->params[1] : false;

            $links = [
                'controller'     => $this->AdminCRLink("tools-1", ["reminders"]),
                'ajax-reminders' => $this->AdminCRLink("tools-2", ["reminders", "listing.json"]),
            ];

            if ($param) {
                if ($param == "listing.json") {
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
                    $udata = UserManager::LoginData("admin");
                    $local_l = Config::get("general/local");

                    $searches["owner_id"] = $udata["id"];

                    if (!Validation::isEmpty(Filter::GET("sSearch"))) $searches['word'] = Filter::init("GET/sSearch", "hclear");

                    $filteredList = $this->model->get_reminders($searches, $orders, $start, $end);
                    $filterTotal = $this->model->get_reminders_total($searches);
                    $listTotal = $this->model->get_reminders_total($searches, true);

                    $this->takeDatas("language");

                    $output = array_merge($output, [
                        "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                        "iTotalRecords"        => $listTotal,
                        "iTotalDisplayRecords" => $filterTotal,
                        "aaData"               => [],
                    ]);

                    if ($listTotal) {

                        $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                        $situations = $situations["reminders"];

                        if ($filteredList) {
                            $this->addData("udata", $udata);
                            $this->addData("situations", $situations);
                            $this->addData("list", $filteredList);
                            $output["aaData"] = $this->view->chose("admin")->render("ajax-reminders", $this->data, false, true);
                        }
                    }

                    echo Utility::jencode($output);
                }
                exit;
            }

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

            $this->addData("links", $links);

            $this->addData("meta", __("admin/tools/meta-reminders"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/tools/breadcrumb-reminders"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
            $situations = $situations["reminders"];

            $this->addData("situations", $situations);

            $this->view->chose("admin")->render("reminders", $this->data);
        }

        private function import_via_module()
        {
            $mName = $this->params[1] ?? '';

            $area_link = $this->AdminCRLink("tools-2", ["import-via-module", $mName]);

            if ($mName) {
                $module_data = Modules::Load("Imports", $mName);
                if (!$module_data) return $this->main_404();

                $className = "\\WISECP\\Modules\\Imports\\" . $mName;

                if (!class_exists($className)) return $this->main_404();
                $module = new $className();
            } else
                $module = null;

            if (!$mName || !$module) return $this->main_404();

            if (property_exists($module, 'area_link'))
                $module->area_link = $area_link;

            if (property_exists($module, 'lang'))
                $module->lang = Modules::Lang("Imports", $mName);

            if (property_exists($module, 'name')) $module->name = $mName;

            if (property_exists($module, 'controller'))
                $module->controller = $this;


            if (Filter::POST("call")) {
                $call = Filter::init("REQUEST/call", "route");
                $call = str_replace(["-", "."], ["_", ""], $call);
                if (method_exists($module, "call_" . $call)) {
                    $this->takeDatas([
                        "language",
                        "lang_list",
                    ]);

                    $module->{"call_" . $call}();

                    return true;
                } else {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => "Undefined method : call_" . $call,
                    ]);
                    return true;
                }
            }


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
                'controller' => $area_link,
            ];

            $this->addData("links", $links);

            $this->addData("meta", ["title" => $module_data["lang"]["name"]]);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => $module_data["lang"]["name"],
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("module_data", $module_data);
            $this->addData("module", $module);

            $this->view->chose("admin")->render("import-via-module", $this->data);
        }


        public function main()
        {

            if (Filter::POST("operation")) return $this->operationMain(Filter::init("POST/operation", "route"));
            if (Filter::GET("operation")) return $this->operationMain(Filter::init("GET/operation", "route"));

            $page = isset($this->params[0]) ? $this->params[0] : false;
            return $this->pageMain($page);
        }
    }