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
            if (!Admin::isPrivilege(Config::get("privileges/TICKETS"))) die();
        }


        private function ajax_requests()
        {
            $limit = 10;
            $output = [];
            $aColumns = array();
            $config_limit = Config::get("options/ticket-list-limit");
            if ($config_limit > 0) $limit = $config_limit;


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

            if ($from = Filter::init("GET/from", "letters") == "user" && $user_id = (int)Filter::init("GET/id", "numbers")) {
                $searches["user_id"] = $user_id;
            }

            if (!Validation::isEmpty(Filter::GET("sSearch"))) $searches['word'] = Filter::init("GET/sSearch", "hclear");

            $client = Filter::init("GET/client", "numbers");
            $department = Filter::init("GET/department", "numbers");
            $status = Filter::init("GET/status");
            $priority = Filter::init("GET/priority", "numbers");
            $ticket_id = Filter::init("GET/ticket_id", "numbers");
            $assigned_to = Filter::init("GET/assigned_to", "numbers");
            $is_search = $client || $department || $status || $priority || $ticket_id || $assigned_to;


            if ($status && is_array($status)) {
                $cstatus_n = [];
                $status_n = [];
                foreach ($status as $s) {
                    $s = Filter::route($s);
                    if ($s) {
                        if (stristr($s, '-'))
                            $cstatus_n[] = $s;
                        else
                            $status_n[] = $s;
                    }
                }
                $status = $status_n;
                $cstatus = $cstatus_n;
                $status = $status ? implode(",", $status) : '';
                $cstatus = $cstatus ? implode(",", $cstatus) : '';
            } else {
                $status = false;
                $cstatus = false;
            }

            if ($client) $searches["client"] = (int)$client;
            if ($department) $searches["department"] = (int)$department;
            if ($status) $searches["status"] = $status;
            if ($cstatus) $searches["cstatus"] = $cstatus;
            if ($priority) $searches["priority"] = $priority;
            if ($ticket_id) $searches["ticket_id"] = $ticket_id;
            if ($assigned_to) $searches["assigned_to"] = $assigned_to;

            $filteredList = $this->model->get_requests($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_requests_total($searches);
            if ($from == "user" && isset($searches["word"])) unset($searches["word"]);
            $listTotal = $this->model->get_requests_total($searches);

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                Helper::Load(["Money", "Orders", "Tickets"]);

                $privOperation = Admin::isPrivilege("TICKETS_OPERATION");
                $privDelete = Admin::isPrivilege("TICKETS_DELETE");
                $privOrder = Admin::isPrivilege("ORDERS_LOOK");
                $privUser = Admin::isPrivilege("USERS_LOOK");

                $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                $situations = $situations["ticket_requests"];

                if ($filteredList) {
                    $this->addData("from", $from);
                    $this->addData("privOperation", $privOperation);
                    $this->addData("privDelete", $privDelete);
                    $this->addData("privOrder", $privOrder);
                    $this->addData("privUser", $privUser);
                    $this->addData("situations", $situations);
                    $this->addData("statuses", $this->model->statuses());
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-ticket-requests", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function ajax_auto_tasks()
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


            $filteredList = $this->model->get_tasks($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_tasks_total($searches);
            if (isset($searches["word"])) unset($searches["word"]);
            $listTotal = $this->model->get_tasks_total($searches);

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                Helper::Load(["Tickets"]);
                if ($filteredList) {
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-tickets-tasks", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function ajax_ticket_logs()
        {


            $ticket_id = $this->params[1] ?? 0;

            $admins = true;

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

            $filteredList = $this->model->log_list($ticket_id, $searches, $orders, $start, $end);
            $filterTotal = $this->model->log_list_total($ticket_id, $searches);
            $listTotal = $this->model->log_list_total($ticket_id);

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
                    $this->addData("admins", $admins);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-user-actions", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function delete_request()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) return false;

            Helper::Load(["Tickets"]);

            $ticket = Tickets::get_request($id);

            $del = Tickets::delete_request($id);
            if (!$del)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tickets/error1"),
                ]));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "deleted", "deleted-ticket-request", [
                'id' => $id,
            ]);

            Hook::run("TicketDeleted", $ticket);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/tickets/success1"),
            ]);
        }


        private function cud_auto_task()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $return = ['status' => "successful", 'message' => ''];
            try {

                $type = Filter::init("POST/type", "letters");

                if (!in_array($type, ['create', 'update', 'delete'])) throw new Exception("Invalid type name");

                $admin = UserManager::LoginData("admin");

                $id = (int)Filter::init("POST/id", "numbers");

                if ($type == 'create' || $type == 'update') {
                    $name = Filter::init("POST/name", "hlcear");
                    $departments = Filter::init("POST/departments");
                    $statuses = Filter::init("POST/statuses");
                    $priorities = Filter::init("POST/priorities");
                    $delay_time = Filter::init("POST/delay_time", "numbers");

                    $department = Filter::init("POST/department", "numbers");
                    $status = Filter::init("POST/status", "route");
                    $priority = Filter::init("POST/priority", "numbers");
                    $assign_to = Filter::init("POST/assign_to", "numbers");
                    $mark_locked = Filter::init("POST/mark_locked", "numbers");
                    $template = Filter::init("POST/template", "hclear");
                    $repeat_action = Filter::init("POST/repeat_action", "numbers");
                    $reply = Filter::init("POST/reply");


                    if ($reply && is_array($reply)) {
                        foreach ($reply as $l => $lc) {
                            if (Validation::isEmpty(Filter::html_clear($lc))) unset($reply[$l]);
                        }
                    }

                    if (!$name) throw new Exception(__("admin/tickets/error11"));

                    if (!($departments || $statuses || $priorities))
                        throw new Exception(__("admin/tickets/error12"));

                    if (!($department || $status || $priority || $assign_to || $mark_locked || $template || $reply))
                        throw new Exception(__("admin/tickets/error13"));


                    $set = [
                        'name'          => $name,
                        'departments'   => is_array($departments) ? implode(",", $departments) : '',
                        'statuses'      => is_array($statuses) ? implode(",", $statuses) : '',
                        'priorities'    => is_array($priorities) ? implode(",", $priorities) : '',
                        'delay_time'    => (int)$delay_time,
                        'department'    => (int)$department,
                        'status'        => $status,
                        'template'      => $template,
                        'priority'      => (int)$priority,
                        'assign_to'     => (int)$assign_to,
                        'mark_locked'   => (int)$mark_locked,
                        'repeat_action' => (int)$repeat_action,
                        'reply'         => $reply ? Utility::jencode($reply) : '',
                    ];


                    if ($type == "create") {
                        $this->model->db->insert("tickets_tasks", $set);
                        $return['message'] = __("admin/products/domain-docs-tx11");
                    }

                    if ($type == "update") {
                        $this->model->db->update("tickets_tasks", $set)->where("id", "=", $id)->save();
                        $return['message'] = __("admin/financial/success5");
                    }


                    if ($id) {
                        $get = $this->model->db->select()->from("tickets_tasks")->where("id", "=", $id);
                        $get = $get->build() ? $get->getObject() : false;

                        User::addAction($admin["id"], 'alteration', 'Ticket auto task modified. "' . $get->name . '"');
                    } else
                        User::addAction($admin["id"], 'added', 'Ticket auto task created. "' . $name . '"');

                }

                if ($type == 'delete') {
                    $get = $this->model->db->select()->from("tickets_tasks")->where("id", "=", $id);
                    $get = $get->build() ? $get->getObject() : false;

                    if ($get) {
                        $this->model->db->delete("tickets_tasks")->where("id", "=", $id)->run();

                        User::addAction($admin["id"], 'deleted', 'Ticket auto task deleted. "' . $get->name . '"');

                        $return['message'] = __("admin/financial/success7");
                    } else {
                        $return['status'] = 'error';
                        $return['message'] = 'Incorrect task id';
                    }
                }


            } catch (Exception $e) {
                $return = [
                    'status'  => "error",
                    'message' => $e->getMessage(),
                ];
            }

            echo Utility::jencode($return);

        }

        private function save_settings()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $show_first = (int)Filter::init("POST/show_first", "numbers");
            $member_group = (int)Filter::init("POST/member_group", "numbers");
            $block_blacklisted = (int)Filter::init("POST/block_blacklisted", "numbers");
            $block_those_without_service = (int)Filter::init("POST/block_those_without_service", "numbers");
            $assigned_tickets_only = (int)Filter::init("POST/assigned_tickets_only", "numbers");
            $list_limit = (int)Filter::init("POST/list_limit", "numbers");
            $refresh_time = (int)Filter::init("POST/refresh_time", "numbers");
            $nsricwv = (int)Filter::init("POST/nsricwv", "numbers");
            $ticket_assignable = (bool)(int)Filter::init("POST/ticket-assignable", "numbers");

            if (!$show_first) $show_first = 2;


            $config_sets = [];
            $config_sets2 = [];

            if ($show_first != Config::get("options/ticket-show-first"))
                $config_sets["options"]["ticket-show-first"] = $show_first;

            if ($member_group != Config::get("options/ticket-member-group"))
                $config_sets["options"]["ticket-member-group"] = $member_group;

            if ($block_blacklisted != Config::get("options/ticket-block-blacklisted"))
                $config_sets["options"]["ticket-block-blacklisted"] = $block_blacklisted;

            if ($block_those_without_service != Config::get("options/ticket-block-those-without-service"))
                $config_sets["options"]["ticket-block-those-without-service"] = $block_those_without_service;

            if ($assigned_tickets_only != Config::get("options/ticket-assigned-tickets-only"))
                $config_sets["options"]["ticket-assigned-tickets-only"] = $assigned_tickets_only;

            if ($list_limit != Config::get("options/ticket-list-limit"))
                $config_sets["options"]["ticket-list-limit"] = $list_limit;

            if ($refresh_time != Config::get("options/ticket-refresh-time"))
                $config_sets["options"]["ticket-refresh-time"] = $refresh_time;

            if ($nsricwv != Config::get("options/nsricwv")) {
                $config_sets["options"]["nsricwv"] = $nsricwv;
            }

            if ($ticket_assignable != Config::get("options/ticket-assignable")) {
                $config_sets["options"]["ticket-assignable"] = $ticket_assignable;
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
                    User::addAction($adata["id"], "alteration", "changed-ticket-settings");
                }
            }


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/financial/success1"),
            ]);

        }

        private function manage_pipe()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $operation_button = Filter::init("POST/operation_button", "route");
            $operation_button_did = Filter::init("POST/operation_button_did", "numbers");


            $status = (boolean)(int)Filter::init("POST/status", "numbers");
            $existing_client = (int)Filter::init("POST/existing_client", "numbers");
            $spam_control = (boolean)(int)Filter::init("POST/spam_control", "numbers");
            $prefix = Filter::init("POST/prefix", "hclear");


            $departments = Filter::POST("department");


            $config_sets = [];
            $config_sets2 = [];

            if ($status != Config::get("options/ticket-pipe/status"))
                $config_sets["options"]["ticket-pipe"]["status"] = $status;


            if ($existing_client != (int)Config::get("options/ticket-pipe/existing-client"))
                $config_sets["options"]["ticket-pipe"]["existing-client"] = $existing_client;

            if ($spam_control != Config::get("options/ticket-pipe/spam-control"))
                $config_sets["options"]["ticket-pipe"]["spam-control"] = $spam_control;

            if ($prefix != Config::get("options/ticket-pipe/prefix"))
                $config_sets["options"]["ticket-pipe"]["prefix"] = $prefix;

            if ($departments)
                foreach ($departments as $did => $d)
                    $config_sets["options"]["ticket-pipe"]["mail"][$did] = $d;

            $modules = Modules::Load("Pipe", "All");

            if ($modules) {
                foreach ($modules as $mk => $mv) {
                    $class_name = "WISECP\\Modules\\Pipe\\" . $mk;
                    $mv["init"] = class_exists($class_name) ? new $class_name() : null;
                    $modules[$mk] = $mv;
                    if (!$operation_button && $mv["init"] && method_exists($mv["init"], 'save_config')) {
                        $m_config = Filter::init("POST/module/" . $mk);
                        $mv["init"]->save_config($m_config);
                    }
                }
            }

            $operation_result = [];

            if ($operation_button && $operation_button_did) {
                $settings = $config_sets["options"]["ticket-pipe"]["mail"][$operation_button_did] ?? [];
                $mk = $settings["provider"] ?? 'Pop3';
                $mv = $modules[$mk] ?? [];
                if ($mv) {
                    $init = $modules[$mk]["init"] ?? null;
                    if (!$init)
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => "Not found " . $mk,
                        ]));

                    if (!method_exists($init, $operation_button))
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => "Not found " . $mk . " > " . $operation_button,
                        ]));

                    $operation_result = $init->$operation_button($operation_button_did);
                    if ($operation_result && $operation_result["status"] == "error")
                        die(Utility::jencode($operation_result));
                    if ($operation_result["saved"] ?? false) {
                        $m_config = Filter::init("POST/module/" . $mk);
                        $init->save_config($m_config);
                    }
                }
            }

            $changes = 0;

            if ($config_sets && (!$operation_result || $operation_result["saved"] ?? false)) {
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
                    User::addAction($adata["id"], "alteration", "changed-ticket-settings-pipe");
                }
            }

            if ($operation_result)
                echo Utility::jencode($operation_result);

            if (!$operation_button) {
                echo Utility::jencode([
                    'status'  => "successful",
                    'message' => __("admin/financial/success1"),
                ]);
            }
        }

        private function save_statuses()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $statuses_data = Filter::init("POST/statuses");

            $id_s = $statuses_data["id"] ?? [];
            $type_s = $statuses_data["type"] ?? [];
            $name_s = $statuses_data["name"] ?? [];
            $color_s = $statuses_data["color"] ?? [];
            $delete_statuses = Filter::init("POST/delete_statuses", "numbers", ",");

            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");


            if ($type_s) {
                foreach ($type_s as $sort_num => $v) {
                    $s_id = $id_s[$sort_num] ?? 0;
                    $s_type = $type_s[$sort_num] ?? 'process';
                    $s_color = $color_s[$sort_num] ?? '';

                    if ($s_id) {
                        if ($s_color && $s_type) {
                            foreach ($lang_list as $l) {
                                $lk = $l["key"];
                                $s_name = $name_s[$lk][$sort_num] ?? '';

                                if (Utility::strlen($s_name) > 1) {
                                    $this->model->save_status_lang($s_id, $lk, $s_name);
                                } else
                                    die(Utility::jencode([
                                        'status'  => "error",
                                        'message' => __("website/account_products/domain-dns-records-12"),
                                    ]));
                            }

                            $this->model->save_status($s_id, [
                                'type'    => $s_type,
                                'color'   => $s_color,
                                'sortnum' => $sort_num,
                            ]);
                        }
                    } else {
                        if ($s_color && $s_type) {

                            foreach ($lang_list as $l) {
                                $lk = $l["key"];
                                $s_name = $name_s[$lk][$sort_num] ?? '';

                                if (Utility::strlen($s_name) < 1)
                                    die(Utility::jencode([
                                        'status'  => "error",
                                        'message' => __("website/account_products/domain-dns-records-12"),
                                    ]));
                            }


                            $create_status = $this->model->insert_status([
                                'type'    => $s_type,
                                'color'   => $s_color,
                                'sortnum' => $sort_num,
                            ]);

                            foreach ($lang_list as $l) {
                                $lk = $l["key"];
                                $s_name = $name_s[$lk][$sort_num] ?? '';

                                if (Utility::strlen($s_name) > 1)
                                    $this->model->save_status_lang($create_status, $lk, $s_name);
                            }

                        }
                    }
                }
            }

            if ($delete_statuses) {
                $delete_statuses = explode(",", $delete_statuses);
                if (sizeof($delete_statuses) > 0)
                    foreach ($delete_statuses as $s)
                        if ($s = Filter::numbers($s))
                            $this->model->remove_status($s);
            }

            $udata = UserManager::LoginData("admin");

            User::addAction($udata["id"], "modify", "Support ticket statuses have been updated");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/financial/success1"),
            ]);

        }


        private function add_request()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Tickets", "Orders", "Notification"]);

            $adata = UserManager::LoginData("admin");
            $adata = array_merge($adata, User::getData($adata["id"], "privilege,full_name", "array"));

            $user_id = (int)Filter::init("POST/user_id", "numbers");
            if (!$user_id)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tickets/error3"),
                ]));


            $udata = User::getData($user_id, "id,full_name,name,surname,email", "array");
            if (!$udata) return false;
            $udata = array_merge($udata, User::getInfo($udata["id"], "gsm,gsm_cc,phone,notes,company_name"));

            $locall = Config::get("general/local");

            $subject = Filter::init("POST/subject", "hclear");
            $service = (int)Filter::init("POST/service", "numbers");
            $status = Filter::init("POST/status", "letters");
            $department = (int)Filter::init("POST/department", "numbers");
            $priority = (int)Filter::init("POST/priority", "numbers");
            $assigned = (int)Filter::init("POST/assigned", "numbers");
            $locked = (int)Filter::init("POST/locked", "numbers");
            $encrypt_msg = (int)Filter::init("POST/encrypt_msg", "numbers");
            $notes = Filter::init("POST/notes");

            if ($department) $get_department = Tickets::get_department($department, $locall, "t1.id,t2.name");
            if ($assigned) {
                $get_assigned = User::getData($assigned, "id,full_name,name,surname,email", "array");
                if ($get_assigned) $get_assigned = array_merge($get_assigned, User::getInfo($assigned, "gsm_cc,gsm"));
            }

            if ($service) $get_service = Orders::get($service, "id,type,name,options");

            $message = Filter::init("POST/message");

            if ($adata["privilege"] != 1) $message = Filter::ticket_message($message);

            $domain = '';
            if (isset($get_service)) {
                if (isset($get_service["options"]["domain"])) $domain = $get_service["options"]["domain"];
                if ($get_service["type"] == "domain") $domain = $get_service["name"];
            }

            $message = Utility::text_replace($message, [
                '{FULL_NAME}' => $udata["full_name"],
                '{NAME}'      => $udata["name"],
                '{SURNAME}'   => $udata["surname"],
                '{EMAIL}'     => $udata["email"],
                '{PHONE}'     => "+" . $udata["phone"],
                '{SERVICE}'   => isset($get_service["name"]) ? $get_service["name"] : '',
                '{DOMAIN}'    => "www." . $domain,
            ]);

            if (Validation::isEmpty($subject))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='subject']",
                    'message' => __("admin/tickets/error4"),
                ]));

            if (Validation::isEmpty($message))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tickets/error5"),
                ]));

            $attachments = Filter::FILES("attachments");


            $uploaded_attachments = false;

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
                        'message' => __("admin/tickets/error2", ['{error}' => $upload->error]),
                    ]));
                if ($upload->operands) $uploaded_attachments = $upload->operands;
            }


            $set_request = [];

            $set_request["status"] = $status;

            if ($service && !$get_service) return false;
            if ($department && !$get_department) return false;
            if ($assigned && !$get_assigned) return false;

            if ($service) $set_request["service"] = $service;
            if ($department) $set_request["did"] = $department;
            if ($assigned) {
                $set_request["assigned"] = $assigned;
                $set_request["assignedBy"] = $adata["id"];
            }
            if ($priority) $set_request["priority"] = $priority;
            if ($locked) $set_request["locked"] = $locked;

            $set_request["userunread"] = 0;
            $set_request["adminunread"] = 1;
            $set_request["lastreply"] = DateManager::Now();

            $set_request["user_id"] = $udata["id"];
            $set_request["title"] = $subject;
            $set_request["ctime"] = DateManager::Now();
            $set_request["notes"] = $notes;


            $h_params = $set_request;
            $h_params['message'] = $message;

            if ($h_validations = Hook::run("TicketAdminCreateValidation", $h_params))
                foreach ($h_validations as $h_validation)
                    if ($h_validation && isset($h_validation["error"]) && $h_validation["error"])
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => $h_validation["error"],
                        ]));


            $ticket_id = Tickets::insert_request($set_request);

            $ticket = Tickets::get_request($ticket_id);

            $a_name = $adata["full_name"];

            $set_reply = [
                'user_id'  => $adata["id"],
                'owner_id' => $ticket_id,
                'name'     => $a_name,
                'message'  => $message,
                'admin'    => 1,
                'ctime'    => DateManager::Now(),
                'ip'       => UserManager::GetIP(),
            ];

            $send_reply = Tickets::insert_reply($set_reply, $encrypt_msg);


            if ($uploaded_attachments) {
                foreach ($uploaded_attachments as $ope) {
                    Tickets::addAttachment([
                        'ticket_id' => $ticket_id,
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
            }

            User::addAction($adata["id"], "added", "added-new-ticket-request", [
                'id'     => $ticket_id,
                'status' => $status,
            ]);

            Notification::ticket_has_been_created_by_admin($ticket);

            if ($assigned && $assigned != $adata["id"]) {
                $assigned_info = User::getData($assigned, "full_name", "array");
                User::addAction($adata["id"], "alteration", "assign-ticket-request", [
                    'id'         => $ticket["id"],
                    'assigned'   => $assigned_info["full_name"],
                    'assignedBy' => $adata["full_name"],
                ]);
                Notification::ticket_assigned_to_you($ticket);
                Helper::Load(["Events"]);
                Events::create([
                    'user_id'  => $assigned,
                    'type'     => "info",
                    'owner'    => "tickets",
                    'owner_id' => $ticket["id"],
                    'name'     => "ticket-assigned-to-you",
                    'data'     => [
                        'assigned-by-name' => $adata["full_name"],
                        'subject'          => $subject ? $subject : $ticket["title"],
                    ],
                ]);
            }

            $ticket['message'] = $message;
            Hook::run("TicketAdminCreated", $ticket);


            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/tickets/success6"),
                'redirect' => $this->AdminCRLink("tickets"),
            ]);

        }


        private function field_file_download()
        {
            $lang = Bootstrap::$lang->clang;

            Helper::Load(["Tickets"]);

            $id = isset($this->params[1]) && $this->params[1] != 0 && $this->params[1] != '' ? Filter::rnumbers($this->params[1]) : 0;
            if ($id != 0 && Validation::isInt($id)) {
                $ticket = Tickets::get_request($id);
                if (!$ticket) {
                    die();
                }
            }

            if (!isset($ticket) || !$ticket) die();

            if ($ticket["custom_fields"]) {
                $custom_fields_values = $ticket["custom_fields"];
            } else
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

            Helper::Load(["Tickets", "Events"]);

            $id = isset($this->params[1]) && $this->params[1] != 0 && $this->params[1] != '' ? Filter::rnumbers($this->params[1]) : 0;
            if ($id != 0 && Validation::isInt($id)) {
                $ticket = Tickets::get_request($id);
                if (!$ticket) {
                    die();
                }
            }
            if (!isset($ticket) || !$ticket) die();

            $last_reply_id = (int)Filter::init("POST/last_reply_id", "numbers");

            $this->takeDatas(["language"]);

            if (!$ticket["adminunread"]) Tickets::set_request($id, ['adminunread' => 1]);

            $adata = UserManager::LoginData("admin");

            if ($events = Events::isCreated("info", "tickets", $ticket["id"], false, "pending", $adata["id"]))
                Events::apply_approved("info", "tickets", $ticket["id"], false, "pending", $adata["id"]);

            $replies = Tickets::get_request_replies($ticket["id"], $last_reply_id);
            $last_admin_msg = Tickets::get_last_admin_reply($ticket["id"]);

            $user_is_typing = DateManager::strtotime($ticket["user_is_typing"]);
            $user_is_typing = $user_is_typing >= DateManager::strtotime() ? true : false;

            if ($replies) {
                $this->addData("get_last_reply_id", $last_reply_id);
                $this->addData("ticket", $ticket);
                $this->addData("replies", $replies);

                $content = $this->view->chose("admin")->render("ajax-ticket-replies", $this->data, true);

                $results = [
                    'user_is_typing'      => $user_is_typing,
                    'status'              => $ticket["cstatus"] > 0 ? $ticket["status"] . "-" . $ticket["cstatus"] : $ticket["status"],
                    'userunread'          => $ticket["userunread"] ? true : false,
                    'lastreply'           => DateManager::format(Config::get("options/date-format") . " - H:i", $ticket["lastreply"]),
                    'last_reply_id'       => $replies[0]['id'],
                    'last_admin_reply_id' => $last_admin_msg ? $last_admin_msg["id"] : 0,
                    'content'             => $content,
                ];
            } else
                $results = [
                    'userunread'          => $ticket["userunread"] ? true : false,
                    'last_admin_reply_id' => $last_admin_msg ? $last_admin_msg["id"] : 0,
                    'status'              => $ticket["cstatus"] > 0 ? $ticket["status"] . "-" . $ticket["cstatus"] : $ticket["status"],
                    'lastreply'           => DateManager::format(Config::get("options/date-format") . " - H:i", $ticket["lastreply"]),
                    'user_is_typing'      => $user_is_typing,
                ];

            $results["assigned"] = $ticket["assigned"];

            $assigned = $ticket["assigned"] > 0 ? User::getData($ticket["assigned"], "id,name,surname,full_name,lang", "array") : [];
            if ($assigned) {
                $assigned_link = $this->AdminCRLink("admins-p", [$assigned["id"]]);
                $results["assigned_link"] = $assigned_link;
                $results["assigned_name"] = $assigned["full_name"];
            }
            $results["is_assigned"] = $ticket["assigned"] == $adata["id"];

            echo Utility::jencode($results);
        }


        private function applyRequest($type = '')
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Tickets", "Orders", "Notification"]);

            $id = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$id) return false;

            $ticket = Tickets::get_request($id);

            $privOperation = Admin::isPrivilege(["TICKETS_OPERATION"]);
            $adata = UserManager::LoginData("admin");
            $adata = array_merge($adata, User::getData($adata["id"], "privilege,full_name", "array"));

            $udata = User::getData($ticket["user_id"], "id,full_name,name,surname,email", "array");
            $udata = array_merge($udata, User::getInfo($udata["id"], "gsm,gsm_cc,phone,notes,company_name"));

            $locall = Config::get("general/local");

            if ($privOperation) {
                $service = (int)Filter::init("POST/service", "numbers");
                $status = Filter::init("POST/status", "route");
                $department = (int)Filter::init("POST/department", "numbers");
                $priority = (int)Filter::init("POST/priority", "numbers");
                $assigned = (int)Filter::init("POST/assigned", "numbers");
                $locked = (int)Filter::init("POST/locked", "numbers");
                $notes = Filter::init("POST/notes");
                $cstatus = 0;

                if (stristr($status, "-")) {
                    $split_status = explode("-", $status);
                    $cstatus = (int)Filter::numbers($split_status[1]);
                    $status = Filter::letters($split_status[0]);
                }


                $before_assigned = false;
                if ($ticket["assigned"])
                    $before_assigned = User::getData($ticket["assigned"], "id,full_name,name,surname,email", "array");

                if ($department) $get_department = Tickets::get_department($department, $locall, "t1.id,t2.name");
                if ($assigned) {
                    $get_assigned = User::getData($assigned, "id,full_name,name,surname,email", "array");
                    if ($get_assigned) $get_assigned = array_merge($get_assigned, User::getInfo($assigned, "gsm_cc,gsm"));
                }
                if ($type == "reply" && ($ticket["status"] != "process" && $status == "process"))
                    $status = "process";
                elseif ($type == "reply" && !$status)
                    $status = "replied";
            }

            if (!$status) $status = $ticket["status"];

            if (isset($service) || $ticket["service"]) {
                $get_service = Orders::get(isset($service) ? $service : $ticket["service"], "id,type,name,options");
            }

            $message = Filter::init("POST/message");

            if ($adata["privilege"] != 1) $message = Filter::ticket_message($message);

            $domain = '';
            if (isset($get_service)) {
                if (isset($get_service["options"]["domain"])) $domain = $get_service["options"]["domain"];
                if ($get_service["type"] == "domain") $domain = $get_service["name"];
            }

            $message = Utility::text_replace($message, [
                '{FULL_NAME}' => $udata["full_name"],
                '{NAME}'      => $udata["name"],
                '{SURNAME}'   => $udata["surname"],
                '{EMAIL}'     => $udata["email"],
                '{PHONE}'     => $udata["phone"] ? "+" . $udata["phone"] : '',
                '{SERVICE}'   => isset($get_service["name"]) ? $get_service["name"] : '',
                '{DOMAIN}'    => $domain,
            ]);

            if ($type == "reply" && Validation::isEmpty($message))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/tickets/error5"),
                ]));

            $attachments = Filter::FILES("attachments");

            $set_request = [];
            $hooks_trigger = [];

            if ($privOperation) {
                if ($service && !$get_service) return false;
                if ($department && !$get_department) return false;
                if ($assigned && !$get_assigned) return false;
                if ($service != $ticket["service"]) {

                    $set_request["service"] = $service;
                    $hooks_trigger[] = 'TicketServiceChange';
                }
                if ($status != $ticket["status"]) {
                    $set_request["status"] = $status;
                    $hooks_trigger[] = 'TicketStatusChange';
                }
                if ($cstatus != $ticket["cstatus"]) $set_request["cstatus"] = $cstatus;
                if ($department != $ticket["did"]) {
                    $set_request["did"] = $department;
                    $hooks_trigger[] = 'TicketDepartmentChange';
                }
                if ($assigned != $ticket["assigned"]) {
                    $set_request["assigned"] = $assigned;
                    $set_request["assignedBy"] = $adata["id"];
                    $hooks_trigger[] = 'TicketAssignedChange';
                }
                if ($priority != $ticket["priority"]) {
                    $set_request["priority"] = $priority;
                    $hooks_trigger[] = 'TicketPriorityChange';
                }
                if ($locked != $ticket["locked"]) {
                    $set_request["locked"] = $locked;
                    $hooks_trigger[] = 'TicketLockChange';
                }
                if ($set_request || $type == "reply") {
                    $set_request["userunread"] = 0;
                    $set_request["adminunread"] = 1;
                    $set_request["lastreply"] = DateManager::Now();
                }
                if (Admin::isPrivilege(["USERS_OPERATION"]) && $notes != $ticket["notes"]) $set_request["notes"] = $notes;
            }

            if ($type == "reply") {

                if (Config::get("options/ticket-assignable") && $ticket["assigned"] && $ticket["assigned"] != $adata["id"])
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => strip_tags(__("admin/tickets/assign-text1", ['{staff}' => $before_assigned["full_name"]])),
                    ]));

                $set_request["userunread"] = 0;
                $set_request["adminunread"] = 1;

                $a_name = $adata["full_name"];

                $set_reply = [
                    'user_id'  => $adata["id"],
                    'owner_id' => $ticket["id"],
                    'name'     => $a_name,
                    'message'  => $message,
                    'admin'    => 1,
                    'ctime'    => DateManager::Now(),
                    'ip'       => UserManager::GetIP(),
                ];

                $h_params = ['request' => $ticket, 'reply' => $set_reply];

                if ($h_validations = Hook::run("TicketAdminReplyValidation", $h_params))
                    foreach ($h_validations as $h_validation)
                        if ($h_validation && isset($h_validation["error"]) && $h_validation["error"])
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => $h_validation["error"],
                            ]));

                $uploaded_attachments = false;
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
                            'message' => __("admin/tickets/error2", ['{error}' => $upload->error]),
                        ]));
                    if ($upload->operands) $uploaded_attachments = $upload->operands;
                }

                $send_reply = Tickets::insert_reply($set_reply);

                if ($uploaded_attachments) {
                    foreach ($uploaded_attachments as $ope) {
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
                    }
                }
            }

            $field_key = Config::get("crypt/system") . "_CUSTOM_FIELDS";

            $custom_fields = $ticket["custom_fields"];
            if (!$custom_fields) $custom_fields = [];


            $get_custom_fields = Tickets::custom_fields($locall, $department, 'active');
            $fields = Filter::POST("fields");

            if ($get_custom_fields) {
                foreach ($get_custom_fields as $field) {
                    $value = '';
                    if (isset($fields[$field["id"]])) $value = $fields[$field["id"]];
                    if (is_array($value)) $value = implode(",", $value);
                    if ($field["type"] == "file") continue;
                    $custom_fields[$field["id"]] = [
                        'type'  => $field["type"],
                        'value' => $value,
                    ];
                }
            }

            $private_data = $fields["private_data"] ?? null;
            if ($private_data) $custom_fields["private_data"] = $private_data;
            elseif (isset($custom_fields["private_data"])) unset($custom_fields["private_data"]);

            $set_request["custom_fields"] = Crypt::encode(Utility::jencode($custom_fields), $field_key);


            if ($set_request) Tickets::set_request($ticket["id"], $set_request);

            if (isset($set_request["assigned"]) && $set_request["assigned"] && $assigned != $adata["id"]) {
                $assigned_info = User::getData($assigned, "full_name", "array");
                User::addAction($adata["id"], "alteration", "assign-ticket-request", [
                    'id'         => $ticket["id"],
                    'assigned'   => $assigned_info["full_name"],
                    'assignedBy' => $adata["full_name"],
                ]);
                Notification::ticket_assigned_to_you($ticket["id"]);
                Helper::Load(["Events"]);
                Events::create([
                    'user_id'  => $assigned,
                    'type'     => "info",
                    'owner'    => "tickets",
                    'owner_id' => $ticket["id"],
                    'name'     => "ticket-assigned-to-you",
                    'data'     => [
                        'assigned-by-name' => $adata["full_name"],
                        'subject'          => $ticket["title"],
                    ],
                ]);
            }

            $ticket = Tickets::get_request($ticket["id"]);

            if ($type == "reply") {

                User::addAction($adata["id"], "alteration", "reply-ticket-request", [
                    'id' => $ticket["id"],
                ]);

                echo Utility::jencode([
                    'status'  => "successful",
                    'message' => __("admin/tickets/success3"),
                ]);

                Notification::ticket_replied_by_admin($ticket);

                Hook::run("TicketAdminReplied", [
                    'request' => $ticket,
                    'reply'   => Tickets::get_reply($send_reply),
                ]);


            } else {
                User::addAction($adata["id"], "alteration", "changed-ticket-request", [
                    'id' => $ticket["id"],
                ]);

                Hook::run("TicketAdminUpdated", $ticket);

                echo Utility::jencode([
                    'status'  => "successful",
                    'message' => __("admin/tickets/success2"),
                ]);

                if ($hooks_trigger) foreach ($hooks_trigger as $h) Hook::run($h, $ticket);

                if (!isset($set_request["cstatus"]) && isset($set_request["status"])) {
                    if ($status == "process") Notification::ticket_your_has_been_processed($ticket);
                    if ($status == "solved") Notification::ticket_resolved_by_admin($ticket);
                }

            }

        }


        private function update_staff_info()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Tickets", "Orders", "Notification"]);

            $id = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$id) return false;

            $ticket = Tickets::get_request($id);

            $adata = UserManager::LoginData("admin");
            $adata = array_merge($adata, User::getData($adata["id"], "privilege,full_name", "array"));

            $dsa = (int)Filter::init("POST/dont-show-it-again", "numbers");
            $assigned = (int)Filter::init("POST/assigned", "numbers");
            $notes = Filter::init("POST/notes");

            $set_request = [];

            if ($assigned != $ticket["assigned"]) {
                if ($assigned > 0) {
                    $set_request["assigned"] = $assigned;
                    $set_request["assignedBy"] = $adata["id"];
                } else {
                    $set_request["assigned"] = 0;
                    $set_request["assignedBy"] = 0;
                }
            }

            if ($dsa) {
                $set_request["assigned"] = 0;
                $set_request["assignedBy"] = 0;
            }

            if ($notes != $ticket["notes"]) $set_request["notes"] = $notes;


            if ($set_request) Tickets::set_request($ticket["id"], $set_request);

            if ($assigned > 0 && $assigned != $ticket["assigned"] && $assigned != $adata["id"]) {
                $assigned_info = User::getData($assigned, "full_name", "array");
                User::addAction($adata["id"], "alteration", "assign-ticket-request", [
                    'id'         => $ticket["id"],
                    'assigned'   => $assigned_info["full_name"],
                    'assignedBy' => $adata["full_name"],
                ]);
                Notification::ticket_assigned_to_you($ticket["id"]);
                Helper::Load(["Events"]);
                Events::create([
                    'user_id'  => $assigned,
                    'type'     => "info",
                    'owner'    => "tickets",
                    'owner_id' => $ticket["id"],
                    'name'     => "ticket-assigned-to-you",
                    'data'     => [
                        'assigned-by-name' => $adata["full_name"],
                        'subject'          => $ticket["title"],
                    ],
                ]);
            }

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/tickets/success15"),
            ]);

        }


        private function add_new_custom_field()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $status = Filter::init("POST/status", "letters");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $did = (int)Filter::init("POST/did", "numbers");
            $names = Filter::POST("name");
            $descriptions = Filter::POST("description");
            $types = Filter::POST("type");
            $compulsoryy = Filter::POST("compulsory");
            $optionss = Filter::POST("options");

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
                        'message' => __("admin/tickets/error9", ['{lang}' => strtoupper($lkey)]),
                    ]));

                $properties["compulsory"] = $compulsory;

                $opts = [];
                $size = sizeof($options["name"]) - 1;

                for ($i = 0; $i <= $size; $i++) {
                    $opt_name = Filter::html_clear($options["name"][$i]);

                    if (!Validation::isEmpty($opt_name)) {
                        $opts[] = [
                            'id'   => $i,
                            'name' => $opt_name,
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

            $insert = $this->model->insert_custom_field([
                'did'    => $did,
                'status' => $status,
                'rank'   => $rank,
            ]);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/products/error10"),
                ]));

            foreach ($lang_data as $data) {
                $data["owner_id"] = $insert;
                $this->model->insert_custom_field_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-ticket-custom-field", [
                'id'   => $insert,
                'name' => $lang_data[$locall]["name"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/tickets/success13"),
                'redirect' => $this->AdminCRLink("tickets-1", ["custom-fields"]),
            ]);
        }


        private function edit_custom_field()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) die();

            $cfield = $this->model->get_c_field($id);
            if (!$cfield) die();


            $status = Filter::init("POST/status", "letters");
            $rank = (int)Filter::init("POST/rank", "numbers");
            $did = (int)Filter::init("POST/did", "numbers");
            $names = Filter::POST("name");
            $descriptions = Filter::POST("description");
            $types = Filter::POST("type");
            $compulsoryy = Filter::POST("compulsory");
            $optionss = Filter::POST("options");

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
                $cfieldl = $this->model->get_c_field_wlang($id, $lkey);
                $lid = $cfieldl ? $cfieldl["lid"] : -1;

                if (Validation::isEmpty($name))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "#field-name-" . $lkey,
                        'message' => __("admin/tickets/error9", ['{lang}' => strtoupper($lkey)]),
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

                        $opts[] = [
                            'id'   => $opt_id,
                            'name' => $opt_name,
                        ];
                    }
                }

                $lang_data[$lkey] = [
                    'id'          => $cfieldl ? $cfieldl["id"] : 0,
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

            $this->model->set_custom_field($id, [
                'did'    => $did,
                'status' => $status,
                'rank'   => $rank,
            ]);

            foreach ($lang_data as $data) {
                $data_id = $data["id"];
                unset($data["id"]);
                if ($data_id)
                    $this->model->set_custom_field_lang($data_id, $data);
                else
                    $this->model->insert_custom_field_lang($data);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "changed-ticket-custom-field", [
                'id'   => $id,
                'name' => $cfield["name"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/tickets/success14"),
                'redirect' => $this->AdminCRLink("tickets-2", ["custom-fields", "edit"]) . "?id=" . $cfield["id"],
            ]);
        }


        private function edit_reply()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Tickets", "Orders", "Notification"]);

            $tid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$tid) return false;

            $ticket = Tickets::get_request($tid);

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) return false;

            $reply = Tickets::get_reply($id, $tid);

            if (!$reply) return false;


            $adata = UserManager::LoginData("admin");
            $adata = array_merge($adata, User::getData($adata["id"], "privilege", "array"));

            $mobile = Filter::init("POST/mobile");
            $message = Filter::init("POST/message");
            if ($mobile) $message = htmlentities($message, ENT_QUOTES);

            if ($adata["privilege"] != 1) $message = Filter::ticket_message($message);

            Tickets::set_reply($id, ['message' => $message], $reply["encrypted"]);

            User::addAction($adata["id"], "alteration", "changed-ticket-request-reply", [
                'ticket_id' => $tid,
                'reply_id'  => $id,
            ]);

            Hook::run("TicketReplyModified", ['request' => $ticket, 'reply' => $reply]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/tickets/success4"),
            ]);

        }


        private function add_reply_to_custom_data()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load("Tickets");

            $ticket_id = (int)Filter::init("POST/ticket_id", "numbers");
            $reply_id = (int)Filter::init("POST/reply_id", "numbers");

            $ticket = Tickets::get_request($ticket_id);

            if (!$ticket)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Not found ticket",
                ]));

            $custom_fields = $ticket["custom_fields"];
            $private_data = $custom_fields["private_data"] ?? null;
            $private_data_x = $private_data;
            if (!$custom_fields) $custom_fields = [];


            $reply = Tickets::get_reply($reply_id, $ticket_id);

            if ($reply) {
                $msg = $reply["message"];

                if (preg_match('/<[^<]+>/', $msg)) {
                    $msg = str_replace('<p>', '', $msg);
                    $msg = str_replace('</p>', "\n", $msg);
                    $msg = str_replace('<br>', "\n", $msg);
                    $msg = str_replace('<br />', "\n", $msg);
                    $msg = strip_tags($msg);
                }

                $private_data = $msg . ($private_data ? "\n---\n" . $private_data : "");

                if (!$reply["encrypted"]) Tickets::set_reply($reply_id, ['message' => $reply["message"]], true);

                if (!$private_data_x) {
                    if ($custom_fields) {
                        $private_data .= "\n---";
                        foreach ($custom_fields as $cf) if ($cf["value"]) $private_data .= "\n" . $cf["value"];
                    }

                    $departments = Tickets::get_departments(Bootstrap::$lang->clang);
                    if ($departments) {
                        foreach ($departments as $dep) {
                            if (stristr($dep["name"], 'Teknik') || stristr($dep["name"], 'Tech')) {
                                if ($dep && $dep["id"] != $ticket["did"])
                                    Tickets::set_request($ticket_id, ['did' => $dep["id"]]);
                            }
                        }
                    }
                }
            }


            if ($private_data) $custom_fields["private_data"] = $private_data;
            elseif (isset($custom_fields["private_data"]))
                unset($custom_fields["private_data"]);


            Tickets::set_request($ticket_id, [
                'custom_fields' => Crypt::encode(Utility::jencode($custom_fields), Config::get("crypt/system") . "_CUSTOM_FIELDS"),
            ]);

            $admin = UserManager::LoginData("admin");

            User::addAction($admin["id"], "added", "Added reply to custom data", [
                'ticket_id' => $ticket_id,
                'reply_id'  => $reply_id,
            ]);


            echo Utility::jencode([
                'status'  => "successful",
                'message' => $private_data,
            ]);
        }


        private function delete_reply()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Tickets", "Orders", "Notification"]);

            $tid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$tid) return false;

            $ticket = Tickets::get_request($tid);

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) return false;

            $reply = Tickets::get_reply($id, $tid);

            if (!$reply) return false;

            $replies = Tickets::get_request_replies($tid);

            if (sizeof($replies) == 1) {
                $del = Tickets::delete_request($tid);
                if (!$del)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/tickets/error1"),
                    ]));

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "deleted", "deleted-ticket-request", [
                    'id' => $tid,
                ]);

                Hook::run("TicketDeleted", $ticket);

                echo Utility::jencode([
                    'status'  => "successful",
                    'message' => __("admin/tickets/success1"),
                ]);

                return false;
            }


            $adata = UserManager::LoginData("admin");
            $adata = array_merge($adata, User::getData($adata["id"], "privilege", "array"));

            Tickets::delete_reply($id);

            $replies = Tickets::get_request_replies($tid);

            if ($replies && !$replies[0]["admin"])
                Tickets::set_request($tid, ['status' => "waiting", 'lastreply' => $replies[0]['ctime']]);

            User::addAction($adata["id"], "deleted", "deleted-ticket-request-reply", [
                'ticket_id' => $tid,
                'reply_id'  => $id,
            ]);

            Hook::run("TicketReplyDeleted", ['request' => $ticket, 'reply' => $reply]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/tickets/success5"),
            ]);
        }


        private function add_predefined_reply_category()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Tickets"]);

            $parent = (int)Filter::init("POST/parent", "numbers");
            $titles = Filter::POST("title");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/tickets/error6", ['{lang}' => strtoupper($lkey)]),
                    ]));

                $lang_data[] = [
                    'owner_id' => 0,
                    'lang'     => $lkey,
                    'title'    => $title,
                ];

            }

            $insert = $this->model->insert_category([
                'type'   => "predefined_replies",
                'parent' => $parent,
            ]);

            foreach ($lang_data as $datum) {
                $datum["owner_id"] = $insert;
                $this->model->insert_category_lang($datum);
            }

            $adta = UserManager::LoginData("admin");
            User::addAction($adta["id"], "added", "added-new-predefined-category", [
                'id'   => $insert,
                'name' => $titles[$locall],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/tickets/success7"),
                'redirect' => $this->AdminCRLink("tickets-2", ["predefined-replies", "categories"]),
            ]);

        }


        private function edit_predefined_reply_category()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Tickets", "Products"]);

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) return false;

            $category = Products::getCategory($id, Config::get("general/local"));
            if (!$category) return false;

            $parent = (int)Filter::init("POST/parent", "numbers");
            $titles = Filter::POST("title");


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $title = isset($titles[$lkey]) ? Filter::html_clear($titles[$lkey]) : false;

                if (Validation::isEmpty($title))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='title[" . $lkey . "]']",
                        'message' => __("admin/tickets/error6", ['{lang}' => strtoupper($lkey)]),
                    ]));

                $get_lcategory = Products::getCategory($category["id"], $lkey);

                $lang_data[] = [
                    'id'       => $get_lcategory ? $get_lcategory["lid"] : 0,
                    'owner_id' => $category["id"],
                    'lang'     => $lkey,
                    'title'    => $title,
                ];

            }

            $this->model->set_category($category["id"], [
                'parent' => $parent,
            ]);

            foreach ($lang_data as $datum) {
                $dat_id = $datum["id"];
                unset($datum["id"]);

                if ($dat_id) $this->model->set_category_lang($dat_id, $datum);
                else $this->model->insert_category_lang($datum);
            }

            $adta = UserManager::LoginData("admin");
            User::addAction($adta["id"], "alteration", "changed-predefined-category", [
                'id'   => $category["id"],
                'name' => $category["id"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/tickets/success9"),
                'redirect' => $this->AdminCRLink("tickets-2", ["predefined-replies", "categories"]),
            ]);

        }


        private function add_new_predefined_reply()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $names = Filter::POST("name");
            $messages = Filter::POST("message");

            Helper::Load(["Tickets", "Products"]);

            $cid = (int)Filter::init("POST/cid", "numbers");
            if (!$cid)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='cid']",
                    'message' => __("admin/tickets/error8"),
                ]));

            $category = Products::getCategory($cid, Config::get("general/local"));
            if (!$category) return false;


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $name = isset($names[$lkey]) ? $names[$lkey] : false;
                $message = isset($messages[$lkey]) ? $messages[$lkey] : false;

                if (Validation::isEmpty($name))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='name[" . $lkey . "]']",
                        'message' => __("admin/tickets/error7", ['{lang}' => strtoupper($lkey)]),
                    ]));

                $lang_data[] = [
                    'owner_id' => 0,
                    'lang'     => $lkey,
                    'name'     => $name,
                    'message'  => $message,
                ];

            }

            $insert = $this->model->insert_predefined_reply([
                'category' => $category["id"],
            ]);

            foreach ($lang_data as $datum) {
                $datum["owner_id"] = $insert;
                $this->model->insert_predefined_reply_lang($datum);
            }

            $adta = UserManager::LoginData("admin");
            User::addAction($adta["id"], "added", "added-predefined-reply", [
                'id'   => $insert,
                'name' => $names[$locall],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/tickets/success10"),
                'redirect' => $this->AdminCRLink("tickets-1", ["predefined-replies"]),
            ]);

        }


        private function edit_predefined_reply()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Tickets", "Products"]);


            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) return false;

            $reply = $this->model->get_reply($id, Config::get("general/local"));
            if (!$reply) return false;


            $names = Filter::POST("name");
            $messages = Filter::POST("message");
            $cid = (int)Filter::init("POST/cid", "numbers");


            if (!$cid)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='cid']",
                    'message' => __("admin/tickets/error8"),
                ]));

            $category = Products::getCategory($cid, Config::get("general/local"));
            if (!$category) return false;


            $locall = Config::get("general/local");
            $lang_list = Bootstrap::$lang->rank_list();
            Utility::sksort($lang_list, "local");

            $lang_data = [];

            foreach ($lang_list as $lang) {
                $lkey = $lang["key"];
                $name = isset($names[$lkey]) ? $names[$lkey] : false;
                $message = isset($messages[$lkey]) ? $messages[$lkey] : false;

                if (Validation::isEmpty($name))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='name[" . $lkey . "]']",
                        'message' => __("admin/tickets/error7", ['{lang}' => strtoupper($lkey)]),
                    ]));

                $lget = $this->model->get_reply($reply["id"], $lkey);

                $lang_data[] = [
                    'id'       => $lget ? $lget["lid"] : 0,
                    'owner_id' => $reply["id"],
                    'lang'     => $lkey,
                    'name'     => $name,
                    'message'  => $message,
                ];

            }

            foreach ($lang_data as $datum) {
                $dat_id = $datum["id"];
                unset($datum["id"]);

                if ($dat_id) $this->model->set_predefined_reply_lang($dat_id, $datum);
                else $this->model->insert_predefined_reply_lang($datum);
            }

            $this->model->set_predefined_reply($id, ['category' => $cid]);

            $adta = UserManager::LoginData("admin");
            User::addAction($adta["id"], "alteration", "changed-predefined-reply", [
                'id'   => $reply["id"],
                'name' => $reply["name"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/tickets/success12"),
                'redirect' => $this->AdminCRLink("tickets-1", ["predefined-replies"]),
            ]);

        }


        private function operationMain($operation)
        {
            if ($operation == "ajax-auto-tasks" && Admin::isPrivilege(["ADMIN_PRIVILEGES"])) return $this->ajax_auto_tasks();
            if ($operation == "requests.json") return $this->ajax_requests();
            if ($operation == "ajax-ticket-logs") return $this->ajax_ticket_logs();

            if ($operation == "cud_auto_task" && Admin::isPrivilege(["ADMIN_PRIVILEGES"]))
                return $this->cud_auto_task();

            if ($operation == "save_settings" && Admin::isPrivilege(["ADMIN_PRIVILEGES"]))
                return $this->save_settings();

            if ($operation == "save_statuses" && Admin::isPrivilege(["ADMIN_PRIVILEGES"]))
                return $this->save_statuses();

            if ($operation == "manage_pipe" && Admin::isPrivilege(["ADMIN_PRIVILEGES"]))
                return $this->manage_pipe();

            if ($operation == "add_new_custom_field" && Admin::isPrivilege(["TICKETS_CUSTOM_FIELDS"]))
                return $this->add_new_custom_field();

            if ($operation == "edit_custom_field" && Admin::isPrivilege(["TICKETS_CUSTOM_FIELDS"]))
                return $this->edit_custom_field();

            if ($operation == "add_predefined_reply_category" && Admin::isPrivilege(["TICKETS_PREDEFINED_REPLIES"]))
                return $this->add_predefined_reply_category();

            if ($operation == "edit_predefined_reply_category" && Admin::isPrivilege(["TICKETS_PREDEFINED_REPLIES"]))
                return $this->edit_predefined_reply_category();

            if ($operation == "add_new_predefined_reply" && Admin::isPrivilege(["TICKETS_PREDEFINED_REPLIES"]))
                return $this->add_new_predefined_reply();

            if ($operation == "edit_predefined_reply" && Admin::isPrivilege(["TICKETS_PREDEFINED_REPLIES"]))
                return $this->edit_predefined_reply();

            if ($operation == "add_request" && Admin::isPrivilege(["TICKETS_OPERATION"]))
                return $this->add_request();

            if ($operation == "edit_reply" && Admin::isPrivilege(["TICKETS_OPERATION"]))
                return $this->edit_reply();

            if ($operation == "add_reply_to_custom_data" && Admin::isPrivilege(["TICKETS_OPERATION"]))
                return $this->add_reply_to_custom_data();

            if ($operation == "delete_reply" && Admin::isPrivilege(["TICKETS_OPERATION"]))
                return $this->delete_reply();

            if ($operation == "add_request" && Admin::isPrivilege(["TICKETS_OPERATION"]))
                return $this->add_request();

            if ($operation == "edit_request" && Admin::isPrivilege(["TICKETS_OPERATION"]))
                return $this->applyRequest("edit");

            if ($operation == "reply_request" && Admin::isPrivilege(["TICKETS_OPERATION"]))
                return $this->applyRequest("reply");


            if ($operation == "update_staff_info" && Admin::isPrivilege(["TICKETS_OPERATION"]))
                return $this->update_staff_info("reply");

            if ($operation == "field-file-download" && Admin::isPrivilege(["TICKETS_OPERATION"]))
                return $this->field_file_download();

            if ($operation == "get-replies" && Admin::isPrivilege(["TICKETS_OPERATION"]))
                return $this->get_replies();

            if ($operation == "delete_request" && Admin::isPrivilege(["TICKETS_OPERATION"]))
                return $this->delete_request();

            echo "Not found operation: " . $operation;
        }


        private function pageMain($name = '')
        {
            if ($name == "create") return $this->create_detail();
            elseif (!$name)
                return $this->requests($name);
            elseif ($name == "settings" && Admin::isPrivilege(["ADMIN_PRIVILEGES"]))
                return $this->settings();
            elseif ($name == "pipe-callback" && Admin::isPrivilege(["ADMIN_PRIVILEGES"]))
                return $this->pipe_callback();
            elseif ($name == "predefined-replies" && Admin::isPrivilege(["TICKETS_PREDEFINED_REPLIES"]))
                return $this->predefined_replies();
            elseif ($name == "custom-fields" && Admin::isPrivilege(["TICKETS_CUSTOM_FIELDS"]))
                return $this->custom_fields();
            elseif ($name == "detail" && $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0)
                return $this->detail($id);
            echo "Not found main: " . $name;
        }


        private function create_detail()
        {
            Helper::Load(["Tickets", "Orders", "Products"]);

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
                'controller'        => $this->AdminCRLink("tickets-1", ["create"]),
                'select-users.json' => $this->AdminCRLink("orders") . "?operation=user-list.json",
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/tickets/meta-create"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("tickets"),
                'title' => __("admin/tickets/breadcrumb-requests"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/tickets/breadcrumb-create"),
            ]);


            $this->addData("breadcrumb", $breadcrumbs);

            $user_id = (int)Filter::init("GET/user_id", "numbers");
            if ($user_id) {
                $user = User::getData($user_id, "id,full_name,lang", "array");
                if ($user) {
                    $user = array_merge($user, User::getInfo($user["id"], "company_name,notes"));
                    $this->addData("user", $user);
                    $this->addData("services", Tickets::get_services($user["id"]));
                    $this->addData("predefined_replies", Tickets::get_predefined_replies($user["lang"]));
                }
            } else
                $this->addData("predefined_replies", Tickets::get_predefined_replies(Bootstrap::$lang->clang));

            $adata = UserManager::LoginData("admin");
            $adata = array_merge($adata, User::getInfo($adata["id"], "signature"));

            $local_l = Config::get("general/local");
            if ($sarr = Utility::jdecode($adata["signature"], true)) {
                if ($user ?? false)
                    $adata["signature"] = isset($sarr[$user["lang"]]) && $sarr[$user["lang"]] ? $sarr[$user["lang"]] : $sarr[$local_l];
                else
                    $adata["signature"] = $sarr[$local_l];
            }

            $signature = $adata["signature"];

            $this->addData("admin_signature", $signature);

            $this->addData("departments", Tickets::get_departments(Bootstrap::$lang->clang, "t1.id,t2.name"));

            $this->addData("assignable_users", Tickets::assignable_users());

            $this->addData("allowed_attachment_extensions", str_replace(",", ", ", Config::get("options/attachment-extensions")));

            $this->view->chose("admin")->render("add-ticket-request", $this->data);

        }


        private function requests()
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
                'controller' => $this->AdminCRLink("tickets"),
                'create'     => $this->AdminCRLink("tickets-1", ["create"]),
                'settings'   => $this->AdminCRLink("tickets-1", ["settings"]),
            ];
            $links["ajax"] = $links["controller"] . "?operation=requests.json";

            $this->addData("links", $links);

            $meta = __("admin/tickets/meta-requests");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/tickets/breadcrumb-requests"),
            ]);


            $this->addData("breadcrumb", $breadcrumbs);

            Helper::Load("Tickets");

            $this->addData("departments", Tickets::get_departments(false, "t1.id,t2.name"));
            $this->addData("situations", array_merge(__("admin/tickets/situations"), $this->model->statuses()));
            $this->addData("priorities", __("admin/tickets/priorities"));
            $this->addData("assignable_users", Tickets::assignable_users());
            $this->addData("user_groups", $this->model->user_groups());


            $this->view->chose("admin")->render("ticket-requests", $this->data);
        }

        private function settings()
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
                'controller' => $this->AdminCRLink("tickets-1", ["settings"]),
                'create'     => $this->AdminCRLink("tickets-1", ["create"]),
                'requests'   => $this->AdminCRLink("tickets"),
            ];

            $this->addData("links", $links);

            $meta = __("admin/tickets/meta-settings");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $links["requests"],
                'title' => __("admin/tickets/breadcrumb-requests"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/tickets/breadcrumb-settings"),
            ]);


            $this->addData("breadcrumb", $breadcrumbs);

            Helper::Load("Tickets");

            $this->addData("departments", Tickets::get_departments(false, "t1.id,t2.name"));
            $this->addData("situations", __("admin/tickets/situations"));
            $this->addData("priorities", __("admin/tickets/priorities"));
            $this->addData("assignable_users", Tickets::assignable_users());
            $this->addData("user_groups", $this->model->user_groups());

            $this->addData("statuses", $this->model->statuses());

            $templates = [];
            $rank = 0;
            foreach (Config::get("notifications") as $key => $row) {
                $rank++;
                $name = __("admin/notifications/groups/" . $key);
                if (!$name) $name = $key;
                $templates[$key] = [
                    'name'  => $name,
                    'key'   => $key,
                    'rank'  => $rank,
                    'items' => [],
                ];

                if ($row) {
                    foreach ($row as $key2 => $row2) {
                        $name2 = __("admin/notifications/templates/" . $key2);
                        if (!$name2) $name2 = $key2;
                        $row2["name"] = $name2;
                        $templates[$key]["items"][$key2] = $row2;
                    }
                }
            }

            $this->addData("templates", $templates);

            $pipeModules = Modules::Load("Pipe", "All");
            $pipe_modules = [];
            if ($pipeModules) {
                foreach ($pipeModules as $mk => $mv) {
                    $className = 'WISECP\Modules\Pipe\\' . $mk;
                    if (!class_exists($className)) continue;
                    $mv["init"] = new $className();
                    $pipe_modules[$mk] = $mv;
                }
            }
            $this->addData("pipe_modules", $pipe_modules);


            $this->view->chose("admin")->render("ticket-settings", $this->data);
        }


        private function predefined_replies()
        {

            Helper::Load(["Tickets", "Products"]);

            $param2 = isset($this->params[1]) ? Filter::route($this->params[1]) : false;

            if ($param2 == "add-reply") {

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
                    'controller' => $this->AdminCRLink("tickets-2", ["predefined-replies", "add-reply"]),
                ];

                $this->addData("links", $links);

                $this->addData("meta", __("admin/tickets/meta-predefined-replies-add-reply"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                ];

                array_push($breadcrumbs, [
                    'link'  => $this->AdminCRLink("tickets"),
                    'title' => __("admin/tickets/breadcrumb-requests"),
                ]);

                array_push($breadcrumbs, [
                    'link'  => $this->AdminCRLink("tickets-1", ["predefined-replies"]),
                    'title' => __("admin/tickets/breadcrumb-predefined-replies"),
                ]);

                array_push($breadcrumbs, [
                    'link'  => false,
                    'title' => __("admin/tickets/breadcrumb-predefined-replies-add-reply"),
                ]);

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("categories", Tickets::select_predefined_reply_categories(Config::get("general/local")));

                $this->view->chose("admin")->render("ticket-predefined-replies-add-reply", $this->data);
                die();
            }

            if ($param2 == "edit-reply") {

                $id = (int)Filter::init("GET/id", "numbers");
                if (!$id) return false;

                $reply = $this->model->get_reply($id, Config::get("general/local"));
                if (!$reply) return false;

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
                    'controller' => $this->AdminCRLink("tickets-2", ["predefined-replies", "edit-reply"]),
                ];

                $this->addData("links", $links);

                $this->addData("meta", [
                    'title' => __("admin/tickets/meta-predefined-replies-edit-reply-title", ['{name}' => $reply["name"]]),
                ]);

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                ];

                array_push($breadcrumbs, [
                    'link'  => $this->AdminCRLink("tickets"),
                    'title' => __("admin/tickets/breadcrumb-requests"),
                ]);

                array_push($breadcrumbs, [
                    'link'  => $this->AdminCRLink("tickets-1", ["predefined-replies"]),
                    'title' => __("admin/tickets/breadcrumb-predefined-replies"),
                ]);

                array_push($breadcrumbs, [
                    'link'  => false,
                    'title' => __("admin/tickets/breadcrumb-predefined-replies-edit-reply", ['{name}' => $reply["name"]]),
                ]);

                $this->addData("reply", $reply);

                $GLOBALS["reply"] = $reply;

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", [
                    'get_reply_lang' => function ($lang = '') {
                        return $this->model->get_reply($GLOBALS["reply"]["id"], $lang);
                    },
                ]);

                $this->addData("categories", Tickets::select_predefined_reply_categories(Config::get("general/local")));

                $this->view->chose("admin")->render("ticket-predefined-replies-edit-reply", $this->data);
                die();
            }

            if ($param2 == "categories") {

                if (Filter::GET("delete")) {
                    $id = (int)Filter::init("GET/delete", "numbers");
                    if ($id) {
                        $this->model->delete_category("predefined_replies", $id);

                        $adata = UserManager::LoginData("admin");
                        User::addAction($adata["id"], "deleted", "deleted-ticket-predefined-replies-category", [
                            'id' => $id,
                        ]);
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
                    'controller' => $this->AdminCRLink("tickets-2", ["predefined-replies", "categories"]),
                ];

                $this->addData("links", $links);

                $this->addData("meta", __("admin/tickets/meta-predefined-replies-categories"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                ];

                array_push($breadcrumbs, [
                    'link'  => $this->AdminCRLink("tickets"),
                    'title' => __("admin/tickets/breadcrumb-requests"),
                ]);

                array_push($breadcrumbs, [
                    'link'  => $this->AdminCRLink("tickets-1", ["predefined-replies"]),
                    'title' => __("admin/tickets/breadcrumb-predefined-replies"),
                ]);

                array_push($breadcrumbs, [
                    'link'  => false,
                    'title' => __("admin/tickets/breadcrumb-predefined-replies-categories"),
                ]);

                $this->addData("breadcrumb", $breadcrumbs);

                Helper::Load(["Tickets", "Products"]);

                $this->addData("get_cat_lang_data", function ($id = 0, $lang = '') {
                    return Products::getCategory($id, $lang);
                });

                $this->addData("list", $this->model->get_predefined_reply_categories());
                $this->addData("parent_categories", Tickets::select_predefined_reply_categories(Config::get("general/local")));

                $this->view->chose("admin")->render("ticket-predefined-replies-categories", $this->data);
                die();
            }

            if (!$param2) {
                if (Filter::GET("delete") && !DEMO_MODE) {
                    $del = (int)Filter::init("GET/delete", "numbers");
                    if ($del) {
                        $this->model->delete_predefined_reply($del);

                        $adata = UserManager::LoginData("admin");
                        User::addAction($adata["id"], "deleted", "deleted-ticket-predefined-reply", [
                            'id' => $del,
                        ]);
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
                    'controller' => $this->AdminCRLink("tickets-1", ["predefined-replies"]),
                    'add-reply'  => $this->AdminCRLink("tickets-2", ["predefined-replies", "add-reply"]),
                    'edit-reply' => $this->AdminCRLink("tickets-2", ["predefined-replies", "edit-reply"]) . "?id=",
                    'categories' => $this->AdminCRLink("tickets-2", ["predefined-replies", "categories"]),
                ];

                $this->addData("links", $links);

                $this->addData("meta", __("admin/tickets/meta-predefined-replies"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                ];

                array_push($breadcrumbs, [
                    'link'  => $this->AdminCRLink("tickets"),
                    'title' => __("admin/tickets/breadcrumb-requests"),
                ]);

                array_push($breadcrumbs, [
                    'link'  => null,
                    'title' => __("admin/tickets/breadcrumb-predefined-replies"),
                ]);

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("list", Tickets::predefined_replies(0, Config::get("general/local")));

                $this->view->chose("admin")->render("ticket-predefined-replies", $this->data);
                die();
            }

        }

        private function custom_fields()
        {

            Helper::Load(["Tickets"]);

            $param2 = isset($this->params[1]) ? Filter::route($this->params[1]) : false;

            if ($param2 == "add") {

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
                    'controller' => $this->AdminCRLink("tickets-2", ["custom-fields", "add"]),
                ];

                $this->addData("links", $links);

                $this->addData("meta", __("admin/tickets/meta-custom-fields-add"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                ];

                array_push($breadcrumbs, [
                    'link'  => $this->AdminCRLink("tickets"),
                    'title' => __("admin/tickets/breadcrumb-requests"),
                ]);

                array_push($breadcrumbs, [
                    'link'  => $this->AdminCRLink("tickets-1", ["custom-fields"]),
                    'title' => __("admin/tickets/breadcrumb-custom-fields"),
                ]);

                array_push($breadcrumbs, [
                    'link'  => false,
                    'title' => __("admin/tickets/breadcrumb-custom-fields-add"),
                ]);

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("departments", Tickets::get_departments(Config::get("general/local")));

                $this->view->chose("admin")->render("ticket-custom-fields-add", $this->data);
                die();
            }

            if ($param2 == "edit") {

                $id = (int)Filter::init("GET/id", "numbers");
                if (!$id) return false;

                $field = $this->model->get_c_field($id);
                if (!$field) return false;

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
                    'controller' => $this->AdminCRLink("tickets-2", ["custom-fields", "edit"]) . "?id=" . $id,
                ];

                $this->addData("links", $links);

                $this->addData("meta", [
                    'title' => __("admin/tickets/meta-custom-fields-edit", ['{name}' => $field["name"]]),
                ]);

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                ];

                array_push($breadcrumbs, [
                    'link'  => $this->AdminCRLink("tickets"),
                    'title' => __("admin/tickets/breadcrumb-requests"),
                ]);

                array_push($breadcrumbs, [
                    'link'  => $this->AdminCRLink("tickets-1", ["custom-fields"]),
                    'title' => __("admin/tickets/breadcrumb-custom-fields"),
                ]);

                array_push($breadcrumbs, [
                    'link'  => false,
                    'title' => __("admin/tickets/breadcrumb-custom-fields-edit", ['{name}' => $field["name"]]),
                ]);

                $this->addData("field", $field);

                $GLOBALS["field"] = $field;

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("functions", [
                    'get_field_wlang' => function ($lang = '') {
                        return $this->model->get_c_field_wlang($GLOBALS["field"]["id"], $lang);
                    },
                ]);

                $this->addData("departments", Tickets::get_departments(Config::get("general/local")));

                $this->view->chose("admin")->render("ticket-custom-fields-edit", $this->data);
                die();
            }

            if (Filter::GET("delete") && !DEMO_MODE) {
                $del = (int)Filter::init("GET/delete", "numbers");
                if ($del) {
                    $this->model->delete_custom_field($del);

                    $adata = UserManager::LoginData("admin");
                    User::addAction($adata["id"], "deleted", "deleted-ticket-custom-field", [
                        'id' => $del,
                    ]);
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
                'controller' => $this->AdminCRLink("tickets-1", ["custom-fields"]),
                'add'        => $this->AdminCRLink("tickets-2", ["custom-fields", "add"]),
                'edit'       => $this->AdminCRLink("tickets-2", ["custom-fields", "edit"]) . "?id=",
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/tickets/meta-custom-fields"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("tickets"),
                'title' => __("admin/tickets/breadcrumb-requests"),
            ]);

            array_push($breadcrumbs, [
                'link'  => null,
                'title' => __("admin/tickets/breadcrumb-custom-fields"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("list", Tickets::custom_fields(Config::get("general/local")));

            $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
            $situations = $situations["custom-fields"];
            $this->addData("situations", $situations);

            $this->view->chose("admin")->render("ticket-custom-fields", $this->data);
            die();

        }


        private function detail($id = 0)
        {
            Helper::Load(["Tickets", "Orders", "Products", "Events"]);
            $ticket = Tickets::get_request($id);
            if (!$ticket) return false;

            if (!$ticket["adminunread"]) Tickets::set_request($id, ['adminunread' => 1]);

            $adata = UserManager::LoginData("admin");

            $assigned_tickets_only = Config::get("options/ticket-assigned-tickets-only");

            $root_admin = Admin::isPrivilege(["ADMIN_PRIVILEGES"]);

            $admin_id = $adata["id"];

            if ($assigned_tickets_only && !$root_admin && $ticket["assigned"] != $admin_id) {
                Utility::redirect($this->AdminCRLink("tickets"));
                return false;
            }

            if ($events = Events::isCreated("info", "tickets", $ticket["id"], false, "pending", $adata["id"]))
                Events::apply_approved("info", "tickets", $ticket["id"], false, "pending", $adata["id"]);

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
                'controller'       => $this->AdminCRLink("tickets-2", ["detail", $id]),
                'ajax-ticket-logs' => $this->AdminCRLink("tickets-2", ["detail", $id]) . "?operation=ajax-ticket-logs",
            ];

            $this->addData("meta", ['title' => __("admin/tickets/meta-request-detail-title", [
                "{id}"      => $id,
                "{subject}" => $ticket["title"],
            ])]);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("tickets"),
                'title' => __("admin/tickets/breadcrumb-requests"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/tickets/breadcrumb-request-detail", [
                    "{id}"      => $id,
                    "{subject}" => $ticket["title"],
                ]),
            ]);


            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("ticket", $ticket);

            $user = User::getData($ticket["user_id"], "id,name,surname,full_name,lang,blacklist", "array");
            if ($user) {
                $user = array_merge($user, User::getInfo($user["id"], "company_name,notes"));

                if ($ticket["lang"] != "none") $user["lang"] = $ticket["lang"];
                $user_link = $this->AdminCRLink("users-2", ["detail", $user["id"]]);
                $this->addData("user_link", $user_link);
                $this->addData("user", $user);
                $this->addData("services", Tickets::get_services($user["id"]));
                $this->addData("predefined_replies", Tickets::get_predefined_replies($user["lang"]));
            }

            $assigned = $ticket["assigned"] > 0 ? User::getData($ticket["assigned"], "id,name,surname,full_name,lang", "array") : [];
            if ($assigned) {
                $assigned_link = $this->AdminCRLink("admins-p", [$assigned["id"]]);
                $this->addData("assigned_link", $assigned_link);
                $this->addData("assigned", $assigned);
            }

            $links['add-bill'] = $this->AdminCRLink("invoices-1", ["create"]) . "?user_id=" . $user["id"];

            if ($ticket["service"]) {
                $service = Orders::get($ticket["service"], "id,name,options");
                if ($service) $this->addData("service", $service);
            }

            $adata = array_merge($adata, User::getInfo($adata["id"], "signature"));

            $local_l = Config::get("general/local");
            if ($sarr = Utility::jdecode($adata["signature"], true)) {
                if ($user)
                    $adata["signature"] = isset($sarr[$user["lang"]]) && $sarr[$user["lang"]] ? $sarr[$user["lang"]] : $sarr[$local_l];
                else
                    $adata["signature"] = $sarr[$local_l];
            }


            $signature = $adata["signature"];

            $this->addData("admin_signature", $signature);

            $this->addData("departments", Tickets::get_departments(Bootstrap::$lang->clang, "t1.id,t2.name"));

            $this->addData("assignable_users", Tickets::assignable_users());

            $this->addData("allowed_attachment_extensions", str_replace(",", ", ", Config::get("options/attachment-extensions")));

            if ($ticket["custom_fields"])
                $custom_fields_values = $ticket["custom_fields"];
            else
                $custom_fields_values = [];

            $this->addData("custom_fields", Tickets::custom_fields(Bootstrap::$lang->clang, 0, 'active'));
            $this->addData("custom_fields_values", $custom_fields_values);

            $this->addData("is_assigned", $adata["id"] == $ticket["assigned"]);

            $this->addData("udata", $adata);

            $this->addData("links", $links);

            $this->addData("user_blacklist", $user["blacklist"]);

            $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
            $situations = $situations["orders"];
            $this->addData("situations_orders", $situations);

            $this->addData("custom_statuses", $this->model->statuses());


            $this->view->chose("admin")->render("detail-ticket-request", $this->data);
        }


        public function main()
        {

            if (Filter::POST("operation")) return $this->operationMain(Filter::init("POST/operation", "route"));
            if (Filter::GET("operation")) return $this->operationMain(Filter::init("GET/operation", "route"));

            $page = isset($this->params[0]) ? $this->params[0] : false;
            return $this->pageMain($page);
        }

        private function pipe_callback()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $this->params = explode("/", Bootstrap::$init->target);

            $provider = $this->params[3] ?? 'na';

            $mv = Modules::Load("Pipe", $provider);
            $className = "WISECP\\Modules\\Pipe\\" . $provider;

            if (!class_exists($className)) {
                echo 'Class ' . $className . ' not found';
                return false;
            }

            $init = new $className;

            if (!method_exists($init, 'callback')) {
                echo 'Callback function not found';
                return false;
            }

            $init->callback();

            return true;
        }
    }