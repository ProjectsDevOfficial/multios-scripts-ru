<?php
    defined('CORE_FOLDER') or exit('You can not get in here!');

    class Controller extends Controllers
    {
        protected $params, $data = [], $pagination = [];


        public function __construct($arg = [])
        {
            parent::__construct();
            $this->params = $arg['params'];

            if (!UserManager::LoginCheck("member")) {
                Utility::redirect($this->CRLink("sign-in"));
                die();
            }

            if (!Config::get("options/ticket-system")) {
                $this->main_404();
                die();
            }

            $udata = UserManager::LoginData("member");
            $redirect_link = User::full_access_control_account($udata);
            if (Config::get("options/nsricwv") && $redirect_link) {
                Utility::redirect($redirect_link);
                die();
            }


            Helper::Load(["Tickets"]);
        }


        public function main()
        {
            if (Filter::GET("operation") == "foundKnowledgeBase")
                return $this->foundKnowledgeBase();
            elseif (Filter::GET("operation") == "field-file-download")
                return $this->field_file_download();
            elseif (Filter::POST("operation") == "get-replies")
                return $this->get_replies();

            if (isset($this->params[0]) && $this->params[0] == "create-request")
                return $this->create_request();
            elseif (isset($this->params[0]) && $this->params[0] == "detail") {
                unset(Bootstrap::$init->route[0]);
                return $this->detail_request();
            } else
                return $this->my_tickets();
        }


        private function field_file_download()
        {
            $lang = Bootstrap::$lang->clang;
            $udata = UserManager::LoginData("member");
            $id = isset($this->params[1]) && $this->params[1] != 0 && $this->params[1] != '' ? Filter::rnumbers($this->params[1]) : 0;
            if ($id != 0 && Validation::isInt($id)) {
                $ticket = $this->model->getTicket($id, $udata["id"]);
                if (!$ticket) {
                    Utility::redirect($this->CRLink("ac-ps-tickets"));
                    die();
                }
            }

            if (!isset($ticket) || !$ticket) {
                Utility::redirect($this->CRLink("ac-ps-tickets"));
                die();
            }

            if ($ticket["custom_fields"])
                $custom_fields_values = $ticket["custom_fields"];
            else
                $custom_fields_values = [];

            if (!$custom_fields_values) return false;


            $fid = (int)Filter::init("GET/fid", "numbers");
            $key = (int)Filter::init("GET/key", "numbers");
            if (!$fid) die();

            $field = isset($custom_fields_values[$fid]) ? $custom_fields_values[$fid] : false;
            if (!$field) return false;

            $response = $field["value"];
            if (!isset($response[$key])) die();

            $re = $response[$key];
            $file = RESOURCE_DIR . "uploads" . DS . "attachments" . DS . $re["file_path"];

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

        private function get_replies()
        {
            $lang = Bootstrap::$lang->clang;
            $udata = UserManager::LoginData("member");
            $id = isset($this->params[1]) && $this->params[1] != 0 && $this->params[1] != '' ? Filter::rnumbers($this->params[1]) : 0;
            if ($id != 0 && Validation::isInt($id)) {
                $ticket = $this->model->getTicket($id, $udata["id"]);
                if (!$ticket) die();
            }

            if (!isset($ticket) || !$ticket) die();

            if (!$ticket["userunread"]) $this->model->userRead($ticket["id"]);

            $last_reply_id = (int)Filter::init("POST/last_reply_id", "numbers");
            $message = Filter::POST("message");
            $message = Filter::ticket_message($message);

            $time = $message ? DateManager::next_date(['second' => 5]) : $ticket["user_is_typing"];

            if ($time !== $ticket["user_is_typing"]) Tickets::set_request($ticket["id"], ['user_is_typing' => $time]);


            $this->takeDatas(["language"]);

            $zone = User::getLastLoginZone();
            $situations = $this->view->chose("website")->render("common-needs", false, false, true);
            $situations = $situations["ticket"];

            $status = $situations[$ticket["status"]] ?? '';
            $statuses = Tickets::custom_statuses();

            $custom = $ticket["cstatus"] > 0 ? ($statuses[$ticket["cstatus"]] ?? []) : [];

            $ui_lang = Bootstrap::$lang->clang;

            if ($custom)
                $status = str_replace([
                    '{color}',
                    '{name}',
                ], [
                    $custom["color"],
                    $custom["languages"][$ui_lang]["name"],
                ], $situations["custom"]);


            $lastreply = UserManager::formatTimeZone($ticket["lastreply"], $zone, Config::get("options/date-format") . " - H:i");

            $replies = Tickets::get_request_replies($ticket["id"], $last_reply_id);

            if ($replies) {
                $this->addData("get_last_reply_id", $last_reply_id);
                $this->addData("ticket", $ticket);
                $this->addData("replies", $replies);
                $this->addData("zone", $zone);

                $content = $this->view->chose("website")->render("ajax-ticket-replies", $this->data, true);

                echo Utility::jencode([
                    'status'        => $status,
                    'lastreply'     => $lastreply,
                    'last_reply_id' => $replies[0]['id'],
                    'content'       => $content,
                ]);
            } else {
                $return = [];
                echo Utility::jencode($return);

            }

        }


        private function foundKnowledgeBase()
        {
            $word = Filter::init("POST/word", "hclear");
            $word = Filter::quotes($word);
            if (Utility::strlen($word) >= 3) {
                $lang = Bootstrap::$lang->clang;
                $data = $this->model->foundKnowledgeBase($word, $lang);
                if ($data) {
                    $keys = array_keys($data);
                    $size = sizeof($keys) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $var = $data[$keys[$i]];
                        $data[$keys[$i]]["route"] = $this->CRLink("kbase_detail", [$var["route"]]);
                    }
                    echo Utility::jencode([
                        'status' => "found",
                        'data'   => $data,
                    ]);
                } else {
                    echo Utility::jencode(['status' => "not found"]);
                }
            }
        }


        private function getDepartments()
        {
            $cache = self::$cache;
            $lang = Bootstrap::$lang->clang;
            $cname = "ticket-departments";
            $cache->setCache("account-" . $lang);
            $cache->eraseExpired();
            if (!$cache->isCached($cname) || Config::get("general/cache")) {
                $data = $this->model->getDepartments($lang);
                if (Config::get("general/cache")) $cache->store($cname, $data);
            } else
                $data = $cache->retrieve($cname);
            return $data;
        }


        private function getServices($id = 0)
        {
            Helper::Load(["Products"]);
            return Tickets::get_services($id);
        }


        private function getAttachments($attachments = [])
        {
            $new_attachments = [];
            if ($attachments) {
                foreach ($attachments as $attachment) {
                    $attachment["link"] = $this->CRLink("download-id", ["ticket-reply-attachment", $attachment["id"]]);
                    $new_attachments[] = $attachment;
                }
            }
            return $new_attachments ? $new_attachments : false;
        }


        private function getReplies($id = 0)
        {
            $data = $this->model->getReplies($id);
            if (!$data) return false;
            $keys = array_keys($data);
            $size = sizeof($keys) - 1;
            for ($i = 0; $i <= $size; $i++) {
                $var = $data[$keys[$i]];
                $data[$keys[$i]]["attachments"] = $this->getAttachments($this->model->getAttachments($var["id"]));
            }
            return $data;
        }


        private function detail_request_submit($ticket = [])
        {
            $stage = Filter::init("GET/stage", "letters_numbers");
            if ($stage && $stage == "reply")
                return $this->reply_submit($ticket);
            elseif ($stage && $stage == "solved")
                return $this->solved_submit($ticket);
        }


        private function solved_submit($ticket = [])
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            if ($ticket["status"] == "solved") return false;


            $status = $this->model->update_request([
                'status'      => "solved",
                'cstatus'     => 0,
                'adminunread' => 1,
            ], $ticket["id"]);
            if (!$status)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_tickets/solved-failed"),
                ]));

            $udata = UserManager::LoginData("member");
            User::addAction($udata["id"], "alteration", "ticket-has-been-resolved", [
                'id' => $ticket["id"],
            ]);

            Helper::Load(["Notification"]);

            Notification::ticket_resolved_by_user($ticket["id"]);

            $h_params = $ticket;
            $h_params["source"] = "client";

            Hook::run("TicketSolved", $h_params);

            echo Utility::jencode(['status' => "successful"]);
        }


        private function reply_submit($ticket = [])
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $lang = Bootstrap::$lang->clang;
            $message = Filter::POST("message");
            $filter_msg = Filter::text($message);
            $attachments = Filter::FILES("attachments");
            $encrypt_msg = (int)Filter::init("POST/encrypt_message", "numbers");

            if ($ticket["locked"] == 1) return false;

            if (Validation::isEmpty($filter_msg) || Utility::strlen($filter_msg) < 5)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "textarea[name='message']",
                    'message' => __("website/account_tickets/creq-message-empty"),
                ]));


            $blocking_time = Config::get("options/blocking-times/reply-ticket");

            Helper::Load("User");
            $data1 = UserManager::LoginData("member");
            $data2 = User::getData($data1["id"], "name,surname,full_name,email", "array");
            $data3 = User::getInfo($data1["id"], ["kind", "company_name", "gsm", "gsm_cc"]);
            $udata = array_merge($data1, $data2, $data3);

            if (User::CheckBlocked("replied-to-ticket", $udata["id"]))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_tickets/reply-blocking", ['{blocking-time}' => DateManager::str_expression($blocking_time)]),
                ]));

            $uploaded_attachments = false;
            if ($attachments && is_array($attachments) && Validation::isEmpty($attachments["name"][0])) $attachments = [];
            if ($attachments && is_array($attachments)) {
                Helper::Load("Uploads");
                $upload = Helper::get("Uploads");
                $upload->init($attachments, [
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
                        'message' => __("website/account_tickets/failed-attachment-upload", ['{error}' => $upload->error]),
                    ]));
                if ($upload->operands) $uploaded_attachments = $upload->operands;
            }

            $u_name = $udata["full_name"];
            if ($udata["company_name"]) $u_name .= " (" . $udata["company_name"] . ")";

            $message = htmlspecialchars($message, ENT_QUOTES);


            $reply_data = [
                'user_id'  => $udata["id"],
                'owner_id' => $ticket["id"],
                'name'     => $u_name,
                'message'  => $message,
                'ctime'    => DateManager::Now(),
                'ip'       => UserManager::GetIP(),
            ];


            $h_params = [
                'request' => $ticket,
                'reply'   => $reply_data,
            ];

            if ($h_validations = Hook::run("TicketClientReplyValidation", $h_params))
                foreach ($h_validations as $h_validation)
                    if ($h_validation && isset($h_validation["error"]) && $h_validation["error"])
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => $h_validation["error"],
                        ]));


            $send_reply = Tickets::insert_reply($reply_data, $encrypt_msg);

            if ($ticket["assigned"]) {
                Helper::Load(["Events"]);
                Events::create([
                    'user_id'  => $ticket["assigned"],
                    'type'     => "info",
                    'owner'    => "tickets",
                    'owner_id' => $ticket["id"],
                    'name'     => "ticket-replied-by-user",
                    'data'     => [
                        'subject' => $ticket["title"],
                    ],
                ]);
            }


            if ($uploaded_attachments)
                foreach ($uploaded_attachments as $ope)
                    Tickets::addAttachment([
                        'ticket_id' => $ticket["id"],
                        'reply_id'  => $send_reply,
                        'user_id'   => $udata["id"],
                        'name'      => $ope["name"],
                        'file_path' => $ope["file_path"],
                        'file_name' => $ope["file_name"],
                        'file_size' => $ope["size"],
                        'ctime'     => DateManager::Now(),
                        'ip'        => UserManager::GetIP(),
                    ]);

            if (!$send_reply)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_tickets/reply-not-sent"),
                ]));

            $this->model->update_request([
                'status'      => "waiting",
                'cstatus'     => 0,
                'lastreply'   => DateManager::Now(),
                'userunread'  => 1,
                'adminunread' => 0,
            ], $ticket["id"]);

            echo Utility::jencode(['status' => "successful"]);

            if (sizeof($blocking_time)) User::addBlocked("replied-to-ticket", $udata["id"], [], DateManager::next_date($blocking_time));

            Helper::Load(["Notification"]);

            Notification::ticket_replied_by_user($ticket["id"]);

            User::addAction($udata["id"], "added", "replied-to-ticket", [
                'ticket_id' => $ticket["id"],
            ]);

            $h_params["reply"]["id"] = $send_reply;

            Hook::run("TicketClientReplied", $h_params);

        }


        private function detail_request()
        {
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


            $id = isset($this->params[1]) && $this->params[1] != 0 && $this->params[1] != '' ? Filter::rnumbers($this->params[1]) : 0;
            if ($id != 0 && Validation::isInt($id)) {
                $ticket = $this->model->getTicket($id, $udata["id"]);
                if (!$ticket) {
                    Utility::redirect($this->CRLink("ac-ps-tickets"));
                    die();
                }
            } else {
                Utility::redirect($this->CRLink("ac-ps-tickets"));
                die();
            }

            if (Filter::isPOST()) return $this->detail_request_submit($ticket);

            if (!$ticket["userunread"]) $this->model->userRead($ticket["id"]);

            $this->addData("pname", "account_tickets");
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
            $this->addData("meta", __("website/account_tickets/detail-request-meta"));
            $this->addData("header_title", $ticket["title"]);

            $breadcrumb = [
                [
                    'link'  => $this->CRLink("my-account"),
                    'title' => __("website/account/breadcrumb-dashboard"),
                ],
                [
                    'link'  => $this->CRLink("ac-ps-tickets"),
                    'title' => __("website/account_tickets/breadcrumb-tickets"),
                ],
                [
                    'link'  => null,
                    'title' => $ticket["title"],
                ],
            ];
            $this->addData("panel_breadcrumb", $breadcrumb);

            $this->addData("links", [
                'controller' => $this->CRLink("ac-ps-detail-ticket", [$ticket["id"]]),
                'my-tickets' => $this->CRLink("ac-ps-tickets"),
            ]);

            if ($ticket["did"] != 0) {
                $this->addData("department", $this->model->getDepartment($ticket["did"], $lang));
            }

            if ($ticket["custom_fields"])
                $custom_fields_values = $ticket["custom_fields"];
            else
                $custom_fields_values = [];

            $this->addData("udata", $udata);
            $this->addData("ticket", $ticket);

            $this->addData("atachment_extensions", Config::get("options/attachment-extensions"));

            $this->addData("custom_fields", Tickets::custom_fields($lang, $ticket["did"], 'active'));
            $this->addData("custom_fields_values", $custom_fields_values);

            $situations = $this->view->chose("website")->render("common-needs", false, false, true);
            $situations = $situations["ticket"];

            $this->addData("situations", $situations);

            if (!file_exists($this->view->get_template_dir() . "ajax-ticket-replies.php"))
                $this->addData("replies", $this->getReplies($ticket["id"]));


            $this->view->chose("website")->render("ac-detail-ticket-request", $this->data);
        }


        private function create_request_submit()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }


            $udata = UserManager::LoginData();
            $lang = Bootstrap::$lang->clang;
            $department = Filter::init("POST/department", "numbers");
            $service = Filter::init("POST/service", "rnumbers");
            $priority = Filter::init("POST/priority", "numbers");
            $title = Filter::quotes(Filter::init("POST/title", "hclear"));
            $message = Filter::init("POST/message", "text");
            $encrypt_msg = (int)Filter::init("POST/encrypt_message", "numbers");
            $attachments = Filter::FILES("attachments");
            $fields = Filter::POST("fields");
            $rs_selected = [];

            if (Validation::isEmpty($title))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='title']",
                    'message' => __("website/account_tickets/creq-title-empty"),
                ]));

            if ($department == 0 || Validation::isEmpty($department))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='department']",
                    'message' => __("website/account_tickets/creq-department-empty"),
                ]));

            if (Validation::isEmpty($priority) || !Validation::isInt($priority) || $priority > 3 || $priority < 1)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='priority']",
                    'message' => __("website/account_tickets/creq-priority-empty"),
                ]));

            if (Validation::isEmpty($message) || Utility::strlen($message) < 5)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "textarea[name='message']",
                    'message' => __("website/account_tickets/creq-message-empty"),
                ]));


            if ($department != '' && $department != 0) {
                $cdepartment = $this->model->getDepartment($department, $lang);
                if (!$cdepartment)
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "select[name='department']",
                        'message' => __("website/account_tickets/creq-department-empty"),
                    ]));
            }


            $btws = false;

            if (Config::get("options/ticket-block-those-without-service")) {
                $block_those_without_service = Models::$init->db->select("id")->from("users_products")
                    ->where("owner_id", "=", $udata["id"], "&&")->where("status", "=", "active");
                $block_those_without_service = $block_those_without_service->build() ? $block_those_without_service->rowCounter() : 0;
                if ($block_those_without_service < 1) $btws = true;
            }


            if (!$service && $btws) {

                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='service']",
                    'message' => __("website/account_tickets/creq-service-empty"),
                ]));
            }

            if ($service != '' && $service != 0) {
                $cservice = $this->model->getService($service);
                if (!$cservice)
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "select[name='service']",
                        'message' => __("website/account_tickets/creq-service-empty"),
                    ]));

                if ($cservice['status'] != 'active' && $btws)
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "select[name='service']",
                        'message' => __("website/account_tickets/creq-service-not-active"),
                    ]));
            }

            $limit = Config::get("options/limits/create-ticket-sending");
            $glimit = LogManager::getLogCount("create_ticket_attempt");
            $blocking_time = Config::get("options/blocking-times/create-ticket");

            Helper::Load("User");
            $data1 = UserManager::LoginData("member");
            $data2 = User::getData($data1["id"], "name,surname,full_name,email", "array");
            $data3 = User::getInfo($data1["id"], ["kind", "ticket_blocked", "ticket_restricted"]);
            $udata = array_merge($data1, $data2, $data3);

            if (User::CheckBlocked("create-ticket", $udata["id"]))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_tickets/creq-blocking", ['{blocking-time}' => DateManager::str_expression($blocking_time)]),
                ]));

            if ($udata["ticket_blocked"])
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_tickets/blocked-opening-new-ticket"),
                ]));


            if ($udata["ticket_restricted"]) {
                $check = $this->model->ntobCheck($udata["id"]);
                if ($check)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_tickets/blocked-restricted-new-ticket"),
                    ]));
            }


            $getFields = Tickets::custom_fields(Bootstrap::$lang->clang, $cdepartment["id"], 'active');
            if ($getFields) {
                foreach ($getFields as $field) {
                    $values = null;
                    $options = $field["options"];
                    if ($options) $options = Utility::jdecode($options, true);
                    $properties = $field["properties"];
                    if ($properties) $properties = Utility::jdecode($properties, true);

                    $is_input = in_array($field["type"], ["input", "textarea"]);
                    $is_opt = in_array($field["type"], ["select", "radio", "checkbox"]);
                    $is_plural = $field["type"] == "checkbox";
                    $is_key = !Validation::isInt($field["id"]) ? true : false;
                    $value = null;
                    if (isset($fields[$field["id"]])) $value = $fields[$field["id"]];
                    if ($is_opt && !is_array($value)) $value = Utility::substr($value, 0, 200);

                    $opt_ids = [];
                    if ($options) foreach ($options as $option) $opt_ids[] = $option["id"];

                    if ($field["type"] == "file") {
                        $files = Filter::FILES("field-" . $field["id"]);
                        if (isset($properties["compulsory"]) && $properties["compulsory"])
                            if (!$files)
                                die(Utility::jencode([
                                    'status'  => "error",
                                    'for'     => "input[name='field-" . $field["id"] . "[]']",
                                    'message' => __("website/osteps/field-required", ['{name}' => $field["name"]]),
                                ]));
                        if ($files && !DEMO_MODE) {
                            $extensions = isset($options["allowed-extensions"]) ? $options["allowed-extensions"] : Config::get("options/attachment-extensions");
                            $max_filesize = isset($options["max-file-size"]) ? $options["max-file-size"] : Config::get("options/attachment-max-file-size");
                            Helper::Load("Uploads");
                            $upload = Helper::get("Uploads");
                            $upload->init($files, [
                                'date'          => false,
                                'multiple'      => true,
                                'max-file-size' => $max_filesize,
                                'folder'        => ROOT_DIR . RESOURCE_DIR . "uploads" . DS . "attachments" . DS,
                                'allowed-ext'   => $extensions,
                                'file-name'     => "random",
                                'width'         => Config::get("pictures/attachment/sizing/width"),
                                'height'        => Config::get("pictures/attachment/sizing/height"),
                            ]);
                            if (!$upload->processed())
                                die(Utility::jencode([
                                    'status'  => "error",
                                    'for'     => "input[name='field-" . $field["id"] . "[]']",
                                    'message' => __("website/osteps/failed-field-upload", ['{error}' => $upload->error]),
                                ]));
                            if ($upload->operands) $values = $upload->operands;
                        }
                    } elseif (isset($properties["compulsory"]) && $properties["compulsory"]) {
                        $compulsory = false;

                        if (!isset($fields[$field["id"]])) $compulsory = true;
                        elseif (Validation::isEmpty($value)) $compulsory = true;
                        elseif ($is_input && Utility::strlen($value) == 0) $compulsory = true;

                        elseif ($is_opt && $is_plural && !is_array($value)) $compulsory = true;

                        elseif ($is_opt && $is_plural && $value)
                            foreach ($value as $val) if (!in_array($val, $opt_ids)) $compulsory = true;

                            elseif ($is_opt && !in_array($value, $opt_ids)) $compulsory = true;


                        if ($compulsory)
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "*[name='fields[" . $field["id"] . "]']",
                                'message' => __("website/osteps/field-required", ['{name}' => $field["name"]]),
                            ]));
                    }


                    if ($field["type"] == "input") $values = Utility::short_text(Filter::html_clear($value), 0, 500);
                    elseif ($field["type"] == "textarea") $values = Utility::short_text($value, 0, 5000);
                    elseif ($is_opt) {
                        $values = [];
                        if (isset($fields[$field["id"]])) {
                            if (!is_array($value)) $value = [$value];
                            foreach ($options as $opt) {
                                if (in_array($opt["id"], $value)) {
                                    $values[] = $opt["id"];
                                }
                            }
                        }
                    }

                    if ($values) {
                        if ($field["type"] != "file" && is_array($values)) $values = implode(",", $values);
                        $rs_selected[$field["id"]] = [
                            'name'  => $field["name"],
                            'type'  => $field["type"],
                            'value' => $values,
                        ];
                    }
                }
            }

            $date_now = DateManager::Now();

            $rs_selected_e = $rs_selected ? Utility::jencode($rs_selected) : '';


            if ($attachments && is_array($attachments) && Validation::isEmpty($attachments["name"][0])) $attachments = [];
            if ($attachments && is_array($attachments)) {
                Helper::Load("Uploads");
                $upload = Helper::get("Uploads");
                $upload->init($attachments, [
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
                        'message' => __("website/account_tickets/failed-attachment-upload", ['{error}' => $upload->error]),
                    ]));
                if ($upload->operands) $upload_operands = $upload->operands;
            }

            $crequest_data = [
                'did'           => $department,
                'user_id'       => $udata["id"],
                'status'        => "waiting",
                'priority'      => $priority,
                'title'         => $title,
                'ctime'         => $date_now,
                'lastreply'     => $date_now,
                'userunread'    => 1,
                'service'       => $service,
                'custom_fields' => $rs_selected_e ? Crypt::encode($rs_selected_e, Config::get("crypt/system") . "_CUSTOM_FIELDS") : '',
            ];

            $h_params = $crequest_data;
            $h_params['custom_fields'] = $rs_selected ? $rs_selected : [];
            $h_params['message'] = $message;

            if ($h_validations = Hook::run("TicketClientCreateValidation", $h_params))
                foreach ($h_validations as $h_validation)
                    if ($h_validation && isset($h_validation["error"]) && $h_validation["error"])
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => $h_validation["error"],
                        ]));

            $crequest = Tickets::insert_request($crequest_data);

            if (!$crequest) die(Utility::jencode(['status' => "error", 'message' => __("website/account_tickets/creq-failed")]));

            $send_reply = Tickets::insert_reply([
                'user_id'  => $udata["id"],
                'owner_id' => $crequest,
                'name'     => $udata["full_name"],
                'message'  => $message,
                'ctime'    => $date_now,
                'ip'       => UserManager::GetIP(),
            ], $encrypt_msg);


            if (isset($upload_operands) && $upload_operands) {
                $attachments = '';
                foreach ($upload_operands as $ope)
                    Tickets::addAttachment([
                        'ticket_id' => $crequest,
                        'reply_id'  => $send_reply,
                        'user_id'   => $udata["id"],
                        'name'      => $ope["name"],
                        'file_path' => $ope["file_path"],
                        'file_name' => $ope["file_name"],
                        'file_size' => $ope["size"],
                        'ctime'     => DateManager::Now(),
                        'ip'        => UserManager::GetIP(),
                    ]);
            }

            if ($limit != 0 && current($blocking_time)) {
                $glimit++;
                LogManager::setLogCount("create_ticket_attempt", $glimit);

                if ($limit == $glimit) {
                    User::addBlocked("create-ticket", $udata["id"], [], DateManager::next_date($blocking_time));
                    LogManager::deleteLogCount("create_ticket_attempt");
                }
            }

            echo Utility::jencode(['status' => "successful"]);

            Helper::Load(["Notification"]);

            $request = Tickets::get_request($crequest);

            Notification::ticket_your_has_been_created($request);
            Notification::ticket_has_been_created_by_user($request);

            User::addAction($udata["id"], "added", "has-been-created-ticket", [
                'id' => $crequest,
            ]);

            $h_params = $request;
            $h_params['message'] = $message;

            Hook::run("TicketClientCreated", $h_params);

        }


        private function create_request()
        {

            if (Filter::isPOST()) return $this->create_request_submit();

            $this->addData("pname", "account_tickets");
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
            $this->addData("meta", __("website/account_tickets/create-request-meta"));
            $this->addData("header_title", __("website/account_tickets/page-creq-title"));

            $breadcrumb = [
                [
                    'link'  => $this->CRLink("my-account"),
                    'title' => __("website/account/breadcrumb-dashboard"),
                ],
                [
                    'link'  => $this->CRLink("ac-ps-tickets"),
                    'title' => __("website/account_tickets/breadcrumb-tickets"),
                ],
                [
                    'link'  => null,
                    'title' => __("website/account_tickets/breadcrumb-create-request"),
                ],
            ];
            $this->addData("panel_breadcrumb", $breadcrumb);

            $links = [
                'controller' => $this->CRLink("ac-ps-creq-ticket"),
                'my-tickets' => $this->CRLink("ac-ps-tickets"),
            ];


            $this->addData("links", $links);

            $udata = UserManager::LoginData("member");

            $infos = User::getInfo($udata["id"], "gsm_cc,gsm,identity");
            $datas = isset($udata["id"]) ? User::getData($udata["id"], "country,email,name,surname,full_name,blacklist,phone", "array") : [];
            $udata = is_array($udata) ? array_merge($udata, $infos, $datas) : [];


            if (Config::get("options/ticket-block-blacklisted")) {
                if (User::checkBlackList($udata)) {
                    Utility::redirect($links["my-tickets"]);
                    exit();
                }
            }


            $address = AddressManager::getAddress(0, $udata["id"]);
            $udata = array_merge($udata, User::getData($udata["id"], "name,surname,full_name,company_name,email", "array"));

            $udata["address"] = $address;

            $visibility_balance = false;

            $balanceModule = Modules::Load("Payment", "Balance", true);
            if ($balanceModule) $visibility_balance = $balanceModule["config"]["settings"]["status"];

            $this->addData("visibility_balance", $visibility_balance);
            $this->addData("udata", $udata);

            $this->addData("departments", $this->getDepartments());
            $this->addData("services", $this->getServices($udata["id"]));
            $this->addData("atachment_extensions", Config::get("options/attachment-extensions"));

            $this->addData("custom_fields", Tickets::custom_fields(Bootstrap::$lang->clang, 0, 'active'));

            $this->view->chose("website")->render("ac-create-ticket-request", $this->data);
        }


        private function get_tickets($user_id = 0, $searches = [], $orders = [], $start = 0, $end = 10)
        {
            Helper::Load(["Orders"]);
            $data = $this->model->get_tickets($user_id, $searches, $orders, $start, $end);
            if ($data) {
                $keys = array_keys($data);
                $size = sizeof($keys);
                $size -= 1;
                for ($i = 0; $i <= $size; $i++) {
                    $var = $data[$keys[$i]];
                    $var["detail_link"] = $this->CRLink("ac-ps-detail-ticket", [$var["id"]]);
                    if ($var["service"]) $var["service"] = Orders::detail_name(Orders::get($var["service"]));
                    $data[$keys[$i]] = $var;
                }
            }
            return $data;
        }


        private function my_tickets()
        {
            $this->addData("pname", "account_tickets");
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
            $this->addData("meta", __("website/account_tickets/list-meta"));
            $this->addData("header_title", __("website/account_tickets/page-title"));

            $breadcrumb = [
                [
                    'link'  => $this->CRLink("my-account"),
                    'title' => __("website/account/breadcrumb-dashboard"),
                ],
                [
                    'link'  => null,
                    'title' => __("website/account_tickets/breadcrumb-tickets"),
                ],
            ];

            $links = [
                'create-request' => $this->CRLink("ac-ps-creq-ticket"),
                'ajax'           => $this->CRLink("ac-ps-tickets") . "?operation=ajaxList",
            ];

            $udata = UserManager::LoginData("member");

            $infos = User::getInfo($udata["id"], "gsm_cc,gsm,identity");
            $datas = isset($udata["id"]) ? User::getData($udata["id"], "country,email,name,surname,full_name,blacklist,phone", "array") : [];
            $udata = is_array($udata) ? array_merge($udata, $infos, $datas) : [];


            if (Config::get("options/ticket-block-blacklisted")) {
                if (User::checkBlackList($udata)) unset($links["create-request"]);
            }

            if (Config::get("options/ticket-block-those-without-service")) {
                $block_those_without_service = Models::$init->db->select("id")->from("users_products")->where("owner_id", "=", $udata["id"]);
                $block_those_without_service = $block_those_without_service->build() ? $block_those_without_service->rowCounter() : 0;
                if ($block_those_without_service < 1 && isset($links["create-request"]))
                    unset($links["create-request"]);
            }


            $this->addData("panel_breadcrumb", $breadcrumb);
            $this->addData("links", $links);


            $situations = $this->view->chose("website")->render("common-needs", false, true, true);
            $situations = $situations["ticket"];

            $this->addData("situations", $situations);

            $this->addData("list", $this->get_tickets($udata["id"], false, false, 0, 500));

            $address = AddressManager::getAddress(0, $udata["id"]);
            $udata = array_merge($udata, User::getData($udata["id"], "name,surname,full_name,company_name,email", "array"));

            $udata["address"] = $address;

            $visibility_balance = false;

            $balanceModule = Modules::Load("Payment", "Balance", true);
            if ($balanceModule) $visibility_balance = $balanceModule["config"]["settings"]["status"];

            $this->addData("visibility_balance", $visibility_balance);
            $this->addData("udata", $udata);


            $this->view->chose("website")->render("ac-tickets", $this->data);
        }

    }