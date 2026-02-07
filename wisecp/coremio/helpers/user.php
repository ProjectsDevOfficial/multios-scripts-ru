<?php
    defined('CORE_FOLDER') or exit('You can not get in here!');

    class User extends Helper
    {

        static function getLastLoginZone($user_type = 'member', $user_id = 0)
        {
            if (!$user_id && $user_type) {
                $user_data = UserManager::LoginData($user_type);
                if ($user_data) {
                    $user_id = $user_data["id"];
                }
            }

            if (!$user_id) return '';

            $stmt = Models::$init->db->select("timezone")->from("users_last_logins");
            $stmt->where("owner_id", "=", $user_id);
            $stmt->order_by("id DESC");
            $stmt->limit(1);
            $data = $stmt->build() ? $stmt->getAssoc() : false;

            if ($data) return isset($data["timezone"]) ? $data["timezone"] : '';
            return '';
        }


        static function get_credit_log($id = 0)
        {
            $stmt = Models::$init->db->select()->from("users_credit_logs");
            $stmt->where("id", "=", $id);
            return $stmt->build() ? $stmt->getAssoc() : false;
        }

        static function get_credit_logs($uid = 0)
        {
            $stmt = Models::$init->db->select()->from("users_credit_logs");
            $stmt->where("user_id", "=", $uid);
            $stmt->order_by("id DESC");
            return $stmt->build() ? $stmt->fetch_assoc() : false;
        }

        static function insert_credit_log($data = [])
        {
            return Models::$init->db->insert("users_credit_logs", $data) ? Models::$init->db->lastID() : false;
        }

        static function set_credit_log($id = 0, $data = [])
        {
            return Models::$init->db->update("users_credit_logs", $data)->where("id", "=", $id)->save();
        }

        static function delete_credit_log($id = 0)
        {
            return Models::$init->db->delete("users_credit_logs")->where("id", "=", $id)->run();
        }

        static function delete($id = 0)
        {
            Helper::Load(["Orders", "Invoices", "Tickets"]);

            $client_data = array_merge((array)self::getData($id,
                [
                    'id',
                    'name',
                    'surname',
                    'full_name',
                    'company_name',
                    'email',
                    'phone',
                    'currency',
                    'lang',
                    'country',
                ], "array"), self::getInfo($id,
                [
                    'company_tax_number',
                    'company_tax_office',
                    'gsm_cc',
                    'gsm',
                    'landline_cc',
                    'landline_phone',
                    'identity',
                    'kind',
                    'taxation',
                ]));
            $client_data["address"] = AddressManager::getAddress(0, $id);
            $client_data["source"] = Invoices::action_source();

            Hook::run("PreClientDeleted", $client_data);

            $db = Models::$init->db;
            $stmt = $db->select()->from("invoices")->where("user_id", "=", $id);
            $stmt = $stmt->build() ? $stmt->fetch_assoc() : false;
            if ($stmt) foreach ($stmt as $row) Invoices::MakeOperation("delete", $row);

            $stmt = Orders::get_orders($id);
            if ($stmt) foreach ($stmt as $row) if (!Orders::MakeOperation("delete", $row, false, false)) return false;


            $aff = self::get_affiliate($id);
            if ($aff) {
                $db->delete("users_affiliates")->where("id", "=", $aff["id"])->run();
                $db->delete("users_affiliate_referrers")->where("affiliate_id", "=", $aff["id"])->run();
                $db->delete("users_affiliate_hits")->where("affiliate_id", "=", $aff["id"])->run();
                $db->delete("users_affiliate_transactions")->where("affiliate_id", "=", $aff["id"])->run();
                $db->delete("users_affiliate_withdrawals")->where("affiliate_id", "=", $aff["id"])->run();
            }


            $stmt = $db->select()->from("tickets")->where("user_id", "=", $id);
            $stmt = $stmt->build() ? $stmt->fetch_assoc() : false;
            if ($stmt) foreach ($stmt as $row) Tickets::delete_request($row["id"]);

            $db->delete("blocked")->where("user_id", "=", $id)->run();
            $db->delete("checkouts")->where("user_id", "=", $id)->run();
            $db->delete("mail_logs")->where("user_id", "=", $id)->run();
            $db->delete("sms_logs")->where("user_id", "=", $id)->run();
            $db->delete("users_credit_logs")->where("user_id", "=", $id)->run();
            $db->delete("events")->where("user_id", "=", $id)->run();
            $db->delete("t1,t2", "users_sms_origins AS t1")->join("INNER", "users_sms_origin_prereg AS t2", "t2.origin_id=t1.id")->where("t1.user_id", "=", $id)->run();

            $db->delete("users_actions")->where("owner_id", "=", $id)->run();
            $db->delete("users_addresses")->where("owner_id", "=", $id)->run();
            $db->delete("users_informations")->where("owner_id", "=", $id)->run();
            $db->delete("users_last_logins")->where("owner_id", "=", $id)->run();


            $db->delete("users_gdpr_requests")->where("user_id", "=", $id)->run();

            $delete = $db->delete("users")->where("id", "=", $id)->run();

            if ($delete) Hook::run("ClientDeleted", $client_data);

            return $delete;
        }

        static function create($data = [])
        {
            return Models::$init->db->insert("users", $data) ? Models::$init->db->lastID() : false;
        }

        static function action_desc($key, $data = [], $lang = '')
        {
            $variables = false;
            if ($data) {
                if (is_array($data)) {
                    $variables = [];
                    foreach ($data as $k => $v) $variables["{" . $k . "}"] = $v;
                } else $variables = ['{data}' => $data];
            }
            $transliterate = Bootstrap::$lang ? Bootstrap::$lang->get("actions/" . $key, $lang, $variables) : false;
            return $transliterate ? $transliterate : $key;
        }

        static function isforeign($id = 0)
        {
            if (!class_exists("Invoices")) Helper::Load(["Invoices"]);

            $udata = self::getInfo($id, ["taxation"]);
            $getAddress = Models::$init->db->select("country_id")->from("users_addresses");
            $getAddress->where("status", "=", "active", "&&");
            $getAddress->where("owner_id", "=", $id, "&&");
            $getAddress->where("detouse", "=", 1);
            $country_id = $getAddress->build() ? $getAddress->getObject()->country_id : 0;

            $taxation = Invoices::getTaxation($country_id, $udata["taxation"]);
            $isLocal = Invoices::isLocal($country_id, $id);

            return !($taxation && $isLocal);
        }

        static function checking_filter_rule($rule = [], $udata = [])
        {
            $type = $rule["type"];

            if ($type == 'country-city') {
                if (isset($udata["address"]) && $udata["address"]) {
                    if ($rule["country"] == $udata["address"]["country_id"]) {
                        if ($rule["city"]) {
                            if ($rule["city"] == $udata["address"]["city_id"])
                                return true;
                        } else
                            return true;
                    }
                }
            } elseif ($type == 'age' && $udata["birthday"]) {
                $g_age = (int)$rule["age"];
                $condition = $rule["age_condition"];
                $date_of_birth = $udata["birthday"];
                $today = DateManager::Now("Y-m-d");
                $diff = date_diff(date_create($date_of_birth), date_create($today));
                $age = $diff->format('%y');

                if ($condition == 'up' && $age > $g_age) return true;
                elseif ($condition == 'down' && $age < $g_age) return true;
            } elseif ($type == 'last-login-diff') {
                $last_login = self::getLastLogin($udata["id"]);

                if (!$last_login) return false;

                $date_of_last_login = $last_login["date"];

                $g_day = $rule["day"];

                $today = DateManager::Now();
                $diff = date_diff(date_create($date_of_last_login), date_create($today));
                $day = $diff->days;
                if ($day >= $g_day) return true;
            } elseif ($type == 'account-type') {
                $kind = $rule["kind"];
                if ($udata["kind"] == $kind) return true;
            } elseif ($type == "vpn" && UserManager::is_proxy() === true)
                return true;
            elseif ($type == "ip-subnet") {
                $value = $rule["value"];
                if (!$value) return false;

                $ip = UserManager::GetIP();
                $ip_x = explode(".", $ip);
                $value_x = explode(".", $value);
                $ip_new = [];

                foreach ($ip_x as $k => $v) {
                    if ($value_x[$k] == "*") $ip_new[$k] = "*";
                    elseif ($value_x[$k] == $v) $ip_new[$k] = $v;
                }

                $ip_new = implode(".", $ip_new);

                return $ip_new == $value;
            }
            return false;
        }

        static function isRemainingVerification($id = 0)
        {
            $data = self::RemainingVerifications($id);
            return $data["force"] > 0;
        }

        static function RemainingVerifications($id = 0)
        {
            $udata = self::getData($id, "id,lang,country,last_login_time", "array");
            $udata = array_merge($udata, self::getInfo($id, [
                'kind',
                'verified-email',
                'verified-gsm',
                'force-document-verification-filters',
                'birthday',
            ]));
            $udata["address"] = AddressManager::getAddress(0, $id);

            $data = [
                'force'            => 0,
                'document_filters' => [],
            ];

            if (Config::get("options/sign/up/email/verify") && $udata["verified-email"] == null) {
                $data["verified-email"] = true;
                $data['force'] += 1;
            }
            if (Config::get("options/sign/up/gsm/verify") && $udata["verified-gsm"] == null) {
                $data["verified-gsm"] = true;
                $data['force'] += 1;
            }

            $force_d_v_fs = $udata["force-document-verification-filters"];
            if ($force_d_v_fs) $force_d_v_fs = explode(",", $force_d_v_fs);
            else $force_d_v_fs = [];
            $save_force_d_v_fs = false;

            $filters = Models::$init->db->select()->from("users_document_filters");
            $filters->where("status", "=", "active");
            $filters->order_by("id DESC");

            $filters = $filters->build() ? $filters->fetch_assoc() : false;

            if ($filters) {
                foreach ($filters as $filter) {
                    $filter["rules"] = Utility::jdecode($filter["rules"], true);
                    $filter["fields"] = Utility::jdecode($filter["fields"], true);
                    $rules = $filter["rules"];
                    $fields = $filter["fields"];

                    if (in_array($filter["id"], $force_d_v_fs)) $data["document_filters"][$filter["id"]] = $filter;
                    else {
                        $call_filter = false;
                        foreach ($rules as $rule) if (self::checking_filter_rule($rule, $udata)) $call_filter = true;
                        if ($call_filter) {
                            $save_force_d_v_fs = true;
                            $data["document_filters"][$filter["id"]] = $filter;
                            $force_d_v_fs[] = $filter["id"];
                        }
                    }
                }
            }

            if ($save_force_d_v_fs) self::setInfo($id, ['force-document-verification-filters' => implode(',', $force_d_v_fs)]);

            if (isset($data["document_filters"]) && $data["document_filters"]) {
                foreach ($data["document_filters"] as $f_id => $f) {
                    if ($f["fields"]) {
                        foreach ($f["fields"] as $l_k => $fields) {
                            if ($l_k != $udata['lang']) continue;
                            foreach ($fields as $f_key => $field) {
                                $is_record = Models::$init->db->select()->from("users_document_records");
                                $is_record->where("user_id", "=", $id, "&&");
                                $is_record->where("filter_id", "=", $f_id, "&&");
                                $is_record->where("field_lang", "=", $l_k, "&&");
                                $is_record->where("field_key", "=", $f_key);
                                $is_record->order_by("id DESC");
                                $is_record = $is_record->build() ? $is_record->getAssoc() : false;
                                if ($is_record) {
                                    $data["document_filters"][$f_id]["fields"][$l_k][$f_key]["record"] = $is_record;
                                    if ($is_record["status"] !== "verified") $data["force"] += 1;
                                } else $data["force"] += 1;
                            }
                        }
                    }
                }
            }
            return $data;
        }

        static function isRequiredField($id = 0)
        {
            if (!self::$model)
                return false;
            $model = self::$model;

            $udata = self::getData($id, "lang,country", "array");
            $lang = $udata["lang"];
            $country = $udata["country"];
            $fields = $model->db->select("GROUP_CONCAT('field_',id) AS ids")->from("users_custom_fields");
            $fields->where("lang", "=", $lang, "&&");
            $fields->where("status", "=", "active", "&&");
            $fields->where("required", "=", 1);
            $fields = $fields->build() ? $fields->getObject() : null;
            if ($fields->ids != null) $fields1 = explode(",", $fields->ids);
            else $fields1 = [];

            $fields2 = [];
            if (Config::get("options/sign/up/gsm/status") && Config::get("options/sign/up/gsm/required")) $fields2[] = "gsm";
            if (Config::get("options/sign/up/landline-phone/status") && Config::get("options/sign/up/landline-phone/required"))
                $fields2[] = "landline_phone";
            if (Config::get("options/sign/up/kind/individual/identity/status") && Config::get("options/sign/up/kind/individual/identity/required") && $country == 227)
                $fields2[] = "identity";
            if (Config::get("options/sign/birthday/status") && Config::get("options/sign/birthday/required"))
                $fields2[] = "birthday";

            $info = self::getInfo($id, [
                'kind',
                'identity_required',
                'identity_checker',
                'birthday_required',
                'birthday_adult_verify',
                'force_identity',
            ]);

            if (Config::get("options/sign/up/kind/status") && $info["kind"] == "corporate") {
                if (Config::get("options/sign/up/kind/corporate/company_name/required"))
                    $fields2[] = "company_name";
                if (Config::get("options/sign/up/kind/corporate/company_tax_number/required"))
                    $fields2[] = "company_tax_number";
                if (Config::get("options/sign/up/kind/corporate/company_tax_office/required"))
                    $fields2[] = "company_tax_office";
            }

            if (($info['identity_required'] || $info['identity_checker']) && $country == 227)
                $fields2[] = "identity";
            if ($info['force_identity']) {
                $fields2[] = "identity";
                $fields2[] = "birthday";
            }
            if ($info['birthday_required'] || $info['birthday_adult_verify'])
                $fields2[] = "birthday";

            if (Config::get("options/sign/security-question/status") && Config::get("options/sign/security-question/required")) {
                $fields2[] = "security_question";
                $fields2[] = "security_question_answer";
            }

            $allFields = array_merge($fields1, $fields2);

            $return = false;

            if ($allFields) {
                $data = self::getInfo($id, $allFields);
                if ($data) foreach ($data as $d) if ($d == null) $return = true;
            }

            if (!$return && self::isRemainingVerification($id)) $return = true;

            return $return;
        }

        static function requiredFields($id = 0)
        {
            if (!self::$model)
                return false;
            $model = self::$model;

            $udata = self::getData($id, "lang,country", "array");
            $lang = $udata["lang"];
            $country = $udata["country"];
            $fields = $model->db->select("GROUP_CONCAT(name) AS names,GROUP_CONCAT('field_',id) AS ids")->from("users_custom_fields");
            $fields->where("lang", "=", $lang, "&&");
            $fields->where("status", "=", "active", "&&");
            $fields->where("required", "=", 1);
            $fields = $fields->build() ? $fields->getObject() : null;
            if ($fields->ids != null) {
                $fields1 = array_values(explode(",", $fields->ids));
                $names = array_values(explode(",", $fields->names));
                $field_names = array_combine($fields1, $names);
            } else {
                $field_names = [];
                $fields1 = [];
            }

            $fields2 = [];

            if (Config::get("options/sign/up/gsm/status") && Config::get("options/sign/up/gsm/required")) $fields2[] = "gsm";
            if (Config::get("options/sign/up/landline-phone/status") && Config::get("options/sign/up/landline-phone/required"))
                $fields2[] = "landline_phone";
            if (Config::get("options/sign/up/kind/individual/identity/status") && Config::get("options/sign/up/kind/individual/identity/required") && $country == 227)
                $fields2[] = "identity";
            if (Config::get("options/sign/birthday/status") && Config::get("options/sign/birthday/required"))
                $fields2[] = "birthday";

            $info = self::getInfo($id, [
                'kind',
                'identity_required',
                'identity_checker',
                'birthday_required',
                'birthday_adult_verify',
                'force_identity',
            ]);

            if ($info['force_identity']) {
                $fields2[] = "identity";
                $fields2[] = "birthday";
            }

            if (($info['identity_required'] || $info['identity_checker']) && $country == 227)
                $fields2[] = "identity";
            if ($info['birthday_required'] || $info['birthday_adult_verify'])
                $fields2[] = "birthday";


            if (Config::get("options/sign/up/kind/status") && $info["kind"] == "corporate") {
                if (Config::get("options/sign/up/kind/corporate/company_name/required"))
                    $fields2[] = "company_name";
                if (Config::get("options/sign/up/kind/corporate/company_tax_number/required"))
                    $fields2[] = "company_tax_number";
                if (Config::get("options/sign/up/kind/corporate/company_tax_office/required"))
                    $fields2[] = "company_tax_office";
            }

            if (Config::get("options/sign/security-question/status") && Config::get("options/sign/security-question/required")) {
                $fields2[] = "security_question";
                $fields2[] = "security_question_answer";
            }


            $allFields = array_merge($fields1, $fields2);

            $rData = [];

            if ($allFields) {
                $data = self::getInfo($id, $allFields);
                if ($data) {
                    foreach ($data as $k => $v) {
                        if ($v == null) {
                            if (isset($field_names[$k])) $rData[$k] = $field_names[$k];
                            else $rData[$k] = __("website/account/field-" . $k);
                        }
                    }
                }
            }

            $active_services = Models::$init->db->select("COUNT(id) AS count")->from("users_products")->where("status", "=", "active", "&&")->where("owner_id", "=", $id);
            $active_services = $active_services->build() ? $active_services->getObject()->count : 0;

            $address_count = Models::$init->db->select("COUNT(id) AS count")->from("users_addresses")->where("owner_id", "=", $id, "&&")->where("status", "!=", "delete");
            $address_count = $address_count->build() ? $address_count->getObject()->count : 0;

            if ($active_services > 0 && $address_count == 0)
                $rData["billing"] = [
                    'type'    => "billing",
                    'message' => Bootstrap::$lang->get_cm("website/account_info/required-billing-profile"),
                ];

            $hooks = Hook::run("addClientInfoRequireText", $id);
            if ($hooks) {
                foreach ($hooks as $hook) {
                    if ($hook && is_array($hook)) {
                        $rData = array_merge($rData, $hook);
                    }
                }
            }

            if ($rData) {
                $new_r_data = [];
                foreach ($rData as $key => $message) {
                    $field_data = is_array($message) ? $message : [];
                    $field_data["type"] = $field_data["type"] ?? "general";
                    $field_data["message"] = $field_data["message"] ?? (is_array($message) ? $key : $message);
                    $field_data["element_id"] = $field_data["element_id"] ?? '';

                    if (stristr($key, "field_"))
                        $field_data["element_id"] = "#c" . $key;
                    elseif ($key == "gsm" || $key == "identity" || $key == "birthday" || $key == "company_name" || $key == "company_tax_number" || $key == "company_tax_office")
                        $field_data["element_id"] = "#" . $key;

                    $new_r_data[$field_data["type"]][$key] = $field_data;
                }
                $rData = $new_r_data;
            }

            return $rData;
        }

        static function full_access_control_account($udata = [])
        {

            $hooks = Hook::run("redirectClientToURL", $udata["id"]);
            if ($hooks) foreach ($hooks as $hook) if ($hook) return $hook;

            if (self::isRequiredField($udata["id"]))
                return Controllers::$init->CRLink("ac-ps-info");

            $udata = array_merge($udata, self::getInfo($udata["id"], ['contract2']));

            if (Config::get("options/gdpr-status") && Config::get("options/gdpr-required") && !$udata["contract2"])
                return Controllers::$init->CRLink("ac-ps-info") . "?tab=gdpr";

            $active_services = Models::$init->db->select("COUNT(id) AS count")->from("users_products")->where("status", "=", "active", "&&")->where("owner_id", "=", $udata["id"]);
            $active_services = $active_services->build() ? $active_services->getObject()->count : 0;
            $address_count = Models::$init->db->select("COUNT(id) AS count")->from("users_addresses")->where("owner_id", "=", $udata["id"], "&&")->where("status", "!=", "delete");
            $address_count = $address_count->build() ? $address_count->getObject()->count : 0;

            if ($active_services > 0 && $address_count == 0)
                return Controllers::$init->CRLink("ac-ps-info") . "?tab=billing";


            return false;
        }

        static function get_profile_image($id = 0)
        {
            if (!self::$model)
                return false;
            $model = self::$model;
            $sth = $model->db->select("name")->from("pictures");
            $sth->where("owner_id", "=", $id, "&&");
            $sth->where("owner", "=", "user", "&&");
            $sth->where("reason", "=", "profile-image");
            return $sth->build() ? $sth->getObject()->name : false;
        }

        static function privilegesUsers($privilege = '')
        {
            if (!self::$model)
                return false;
            $model = self::$model;
            if (!is_array($privilege)) $privilege = !$privilege ? [] : explode(",", $privilege);
            $size = sizeof($privilege);

            if ($privilege) {
                $sth = $model->db->select("t2.id AS user_id,t1.id AS privilege_id")->from("privileges AS t1");
                $sth->join("LEFT", "users AS t2", "t2.privilege=t1.id");
                $sth->where("t2.id", "IS NOT NULL", "", "&&");
                $sth->where("t2.type", "=", "admin", "&&");
                $sth->where("t2.status", "=", "active", "&&");
                $i = 0;
                $sth->where("(");
                foreach ($privilege as $priv) {
                    $i++;
                    if ($i == $size)
                        $sth->where("FIND_IN_SET('" . $priv . "',t1.privileges)");
                    else
                        $sth->where("FIND_IN_SET('" . $priv . "',t1.privileges)", "", "", "||");
                }
                $sth->where(")");
                return $sth->build() ? $sth->fetch_assoc() : false;
            }
        }

        static function getPrivileges($id = 0, $resultType = 'array')
        {
            $data = self::getData($id, "privilege", "array");
            if (!$data["privilege"]) return false;
            $privilege = self::getPrivilege($data["privilege"]);
            if ($privilege) {
                if ($resultType == "array") {
                    $privileges = explode(",", $privilege["privileges"]);
                    return $privileges;
                } elseif ($resultType == "text") {
                    return $privilege["privileges"];
                }
            }
            return false;
        }

        static function getPrivilege($id = 0)
        {
            return self::$model->db->select()->from("privileges")->where("id", "=", $id)->build() ? self::$model->db->getAssoc() : false;
        }

        static function addAction($id = 0, $reason = '', $detail = '', $data = [])
        {
            $local_desc = self::action_desc($detail, $data, Config::get("general/local"));
            if (!$local_desc) $local_desc = '';
            if (!self::$model)
                return false;
            $model = self::$model;
            $model->db_start();
            $data = (sizeof($data) > 0) ? Utility::jencode($data) : null;
            $sth = $model->db->insert("users_actions", [
                'ip'            => UserManager::GetIP(),
                'owner_id'      => $id,
                'reason'        => $reason,
                'detail'        => $detail,
                'locale_detail' => $local_desc,
                'data'          => $data,
                'ctime'         => DateManager::Now(),
            ]);
            return ($sth);
        }

        static function ActionCount($id = 0, $reason = '', $detail = '')
        {
            if (!self::$model)
                return false;
            $model = self::$model;
            $model->db_start();
            $stmt = $model->db->select("COUNT(id) AS count")->from("users_actions");
            if ($id != 0) $stmt->where("owner_id", "=", $id, "&&");
            $stmt->where("reason", "=", $reason, "&&");
            $stmt->where("detail", "=", $detail);
            return $stmt->build() ? $stmt->getObject()->count : 0;
        }

        static function Login_Refresh($id = 0, $login_token = '')
        {
            if (!self::$model)
                return false;
            $model = self::$model;
            $model->db_start();

            return $model->db->update("users", [
                'ip'              => UserManager::GetIP(),
                'last_login_time' => DateManager::Now(),
                'login_token'     => $login_token,
                'secure_hash'     => self::secure_hash($id),
            ])->where("id", "=", $id)->save();
        }

        static function secure_hash($user_id = 0, $action = 'encrypt')
        {
            if (!self::$model)
                return false;
            $model = self::$model;
            $model->db_start();

            $data = self::getData($user_id, "id,email,phone,secure_hash", "array");

            if ($data) {
                if ($action == "encrypt")
                    return Crypt::encode(Utility::jencode([
                        'id'    => $data["id"],
                        'email' => $data["email"],
                        'phone' => $data["phone"],
                    ]), Config::get("crypt/user"));
                elseif ($action == "decrypt" && $data["secure_hash"])
                    return Utility::jdecode(Crypt::decode($data["secure_hash"], Config::get("crypt/user")), true);
            }
            return null;
        }

        static function addLastLogin($id = 0, $token = '')
        {
            $info = UserManager::ip_info();
            if ($id != 0) {
                if (!self::$model)
                    return false;
                $model = self::$model;
                $model->db_start();
                return $model->db->insert("users_last_logins", [
                    'owner_id'     => $id,
                    'ip'           => UserManager::GetIP(),
                    'port'         => UserManager::GetPort(),
                    'country_code' => isset($info["countryCode"]) ? $info["countryCode"] : '',
                    'city'         => isset($info["city"]) ? $info["city"] : '',
                    'latlng'       => isset($info["lat"]) ? $info["lat"] . "," . $info["lon"] : '',
                    'user_agent'   => UserManager::GetUserAgent(),
                    'timezone'     => isset($info["timezone"]) ? $info["timezone"] : '',
                    'token'        => $token,
                    'ctime'        => DateManager::Now(),
                ]);
            }
        }

        static function setData($id = 0, $data = [])
        {
            if (!self::$model)
                return false;
            $model = self::$model;
            if ($data) {
                $update = $model->db->update("users", $data)->where("id", "=", $id)->save();

                if ($update && (isset($data["phone"]) || isset($data["email"])))
                    $model->db->update("users", ['secure_hash' => self::secure_hash($id)])->where("id", "=", $id)->save();

                return $update;
            }
            return false;
        }

        static function AddInfo($owner_id = 0, $values = [])
        {
            if (!self::$model)
                return false;
            $model = self::$model;
            if (sizeof($values) > 0) {
                $model->db_start();
                $added = [];
                $set_data = [];
                foreach ($values as $k => $v) {
                    $isinfo = self::isInfo($owner_id, $k);
                    if ($isinfo)
                        $sth = $model->db->update("users_informations", [
                            'owner_id'   => $owner_id,
                            'name'       => $k,
                            'content'    => $v,
                            'updated_at' => DateManager::Now(),
                        ])->where("id", "=", $isinfo)->save();
                    else
                        $sth = $model->db->insert("users_informations", [
                            'owner_id'   => $owner_id,
                            'name'       => $k,
                            'content'    => $v,
                            'created_at' => DateManager::Now(),
                            'updated_at' => DateManager::Now(),
                        ]);
                    if ($sth) {
                        if ($k == "phone" || $k == "company_name") $set_data[$k] = $v;
                        $added[] = $k;
                    }
                }
                if ($set_data) self::setData($owner_id, $set_data);
                return (sizeof($added) > 0);
            } else
                return false;
        }

        static function setInfo($owner_id = 0, $values = [])
        {
            return self::AddInfo($owner_id, $values);
        }

        static function deleteInfo($owner_id = 0, $name = '')
        {
            if (is_array($name)) $name = implode(",", $name);
            $handle = Models::$init->db->delete("users_informations")
                ->where("owner_id", "=", $owner_id, "&&")
                ->where("FIND_IN_SET(name,'" . $name . "')")
                ->run();

            if ($handle) {
                $exp = stristr($name, ",") ? explode(",", $name) : [$name];
                if ($exp) {
                    $set_data = [];
                    foreach ($exp as $ex) if ($ex == "company_name" || $ex == "phone") $set_data[$ex] = "";
                    if ($set_data) self::setData($owner_id, $set_data);
                }
            }

            return $handle;
        }

        static function isInfo($owner_id = 0, $name = '')
        {
            if (!self::$model)
                return false;
            $model = self::$model;
            $model->db_start();
            $sth = $model->db->select("id")->from("users_informations");
            $sth->where("owner_id", "=", $owner_id, "&&");
            $sth->where("name", "=", $name);
            return ($sth->build()) ? $sth->getObject()->id : false;
        }

        static function getInfo($owner_id = 0, $names = [])
        {
            if (!self::$model)
                return false;
            $model = self::$model;
            if (!is_array($names) && $names != '')
                $names = explode(",", $names);
            $data = [];
            if (sizeof($names) > 0) {
                $model->db_start();
                foreach ($names as $name) {
                    $sth = $model->db->select("content")->from("users_informations");
                    $sth->where("owner_id", "=", $owner_id, "&&");
                    $sth->where("name", "=", $name);
                    $data[$name] = ($sth->build()) ? $sth->getObject()->content : null;
                }
                return $data;
            } else
                return $data;
        }

        static function findInfo($name = '', $value = '', $first = true)
        {
            if (!$name || !$value) return false;
            $stmt = self::$model->db->select()->from("users_informations");
            $stmt->where("name", "=", $name, "&&");
            $stmt->where("content", "=", $value);
            return $stmt->build() ? ($first ? $stmt->getAssoc() : $stmt->fetch_assoc()) : false;
        }

        static function getData($id = 0, $fields = "*", $fetch = "object")
        {
            if (!self::$model)
                return false;
            $model = self::$model;
            if (is_array($fields)) $fields = implode(",", $fields);
            $sth = $model->db->select($fields)->from("users");
            $sth->where("id", "=", $id);
            if (!$sth->build()) return false;
            return ($fetch == "object") ? $sth->getObject() : $sth->getAssoc();
        }

        static function getLastLogin($id = 0)
        {
            $sth = Models::$init->db->select("*,ctime AS date")->from("users_last_logins");
            $sth->where("owner_id", "=", $id);
            $sth->order_by("id DESC");
            $sth->limit(1, 1);
            return ($sth->build()) ? $sth->getAssoc() : false;
        }

        static function remember_check($token = '')
        {
            if ($token != '') {
                $sth = Models::$init->db->select("us.id,us.currency,us.lang,us.email,us.password,lg.ip")->from("users_last_logins AS lg");
                $sth->join("LEFT", "users AS us", "us.id=lg.owner_id");
                $sth->where("lg.token", "=", $token, "&&");
                $sth->where("us.status", "=", "active");
                return $sth->build() ? $sth->getObject() : false;
            }
        }

        static function Session_Login_Check($id = 0, $ip = '', $password = '')
        {
            $disable_session_ip_check = Config::get("options/disable-session-ip-check");
            $stmt = Models::$init->db->select("usr.id")->from("users AS usr");
            $stmt->where("usr.id", "=", $id, "&&");
            if (!DEMO_MODE && !$disable_session_ip_check) {
                $stmt->where("(");
                $stmt->where("usr.ip", "=", $ip, "||");

                $stmt->where("(SELECT ull.id FROM " . Models::$init->pfx . "users_last_logins AS ull WHERE ull.owner_id=usr.id AND ull.ctime BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE() + INTERVAL 1 DAY AND ull.ip = '" . $ip . "' LIMIT 1)", ">", "0");
                $stmt->where(")", "", "", "&&");
            }
            $stmt->where("usr.password", "=", $password, "&&");
            $stmt->where("usr.status", "=", "active");
            return $stmt->build();
        }

        static function checkBlackList($data = [], $apply_on_module = true)
        {
            if (Config::get("options/blacklist/status") && Config::get("options/blacklist/use-producer-as-source") && $apply_on_module) {
                $name = isset($data["full_name"]) ? $data["full_name"] : '';
                $email = isset($data["email"]) ? $data["email"] : '';
                $phone = isset($data["phone"]) ? $data["phone"] : '';
                $identity = isset($data["identity"]) ? $data["identity"] : '';
                $ip = isset($data["ip"]) ? $data["ip"] : '';
                $id = isset($data["id"]) ? $data["id"] : '';
                $risk_score = (int)Config::get("options/blacklist/risk-score");

                $request = Utility::HttpRequest("https://my.wisecp.com/blacklist/check", [
                    'post'    => [
                        'domain'     => str_replace("www.", "", Utility::getDomain()),
                        'order-key'  => Config::get("general/order-key"),
                        'name'       => $name,
                        'email'      => $email,
                        'phone'      => $phone,
                        'identity'   => $identity,
                        'ip'         => $ip,
                        'id'         => $id,
                        'risk-score' => $risk_score,
                    ],
                    'timeout' => 4,
                ]);
                if ($request) $request = Utility::jdecode($request, true);
                if (isset($request['status']) && $request['status'] == 'true') {
                    if (!$data['blacklist']) self::setBlackList($data, 'add-2', $request['reason'], 0, false);
                    return 2;
                } elseif (isset($request['status']) && $request['status'] == 'false') {
                    if ($data['blacklist'] == 2) self::setBlackList($data, 'remove', '', 0, false);
                }
            }
            return $data['blacklist'] ? 1 : 0;
        }

        static function setBlackList($data = [], $status = '', $reason = '', $admin_id = 0, $apply_on_source = true)
        {
            if (Config::get("options/blacklist/status") && Config::get("options/blacklist/use-producer-as-source") && $apply_on_source) {
                $name = isset($data["full_name"]) ? $data["full_name"] : '';
                $email = isset($data["email"]) ? $data["email"] : '';
                $phone = isset($data["phone"]) ? $data["phone"] : '';
                $identity = isset($data["identity"]) ? $data["identity"] : '';
                $ip = isset($data["ip"]) ? $data["ip"] : '';
                $id = isset($data["id"]) ? $data["id"] : '';

                $request = Utility::HttpRequest("https://my.wisecp.com/blacklist/apply/" . $status, [
                    'post'    => [
                        'domain'    => str_replace("www.", "", Utility::getDomain()),
                        'order-key' => Config::get("general/order-key"),
                        'id'        => $id,
                        'name'      => $name,
                        'email'     => $email,
                        'phone'     => $phone,
                        'identity'  => $identity,
                        'ip'        => $ip,
                        'reason'    => $reason,
                    ],
                    'timeout' => 10,
                ]);
                if (!$request) return Utility::$error;
                if ($request) $request = Utility::jdecode($request, true);
                if ($request && isset($request['status']) && $request['status'] != 'successful') return $request['message'];
            }
            $_status = 0;
            if ($status == 'add') $_status = 1;
            elseif ($status == 'add-2') $_status = 2;

            $apply = self::setData($data['id'], ['blacklist' => $_status]);
            if ($apply && $_status)
                self::setInfo($data['id'], [
                    'blacklist_reason'   => $reason,
                    'blacklist_time'     => DateManager::Now(),
                    'blacklist_by_admin' => $admin_id,
                ]);
            elseif ($apply && $status == 'remove')
                self::deleteInfo($data['id'], 'blacklist_reason,blacklist_time,blacklist_by_admin');

            return $apply;
        }

        static function addBlocked($reason = '', $user_id = 0, $values = [], $endtime = '')
        {
            if (!self::$model)
                return false;
            $model = self::$model;
            if ($reason != '') {
                $set = [
                    'reason'  => $reason,
                    'user_id' => $user_id,
                    'ctime'   => DateManager::Now(),
                    'endtime' => $endtime,
                ];
                $set = array_merge($set, $values);
                return $model->db->insert("blocked", $set);
            }
        }

        static function CheckBlocked($reason = '', $user_id = 0, $values = [])
        {
            if (!self::$model)
                return false;
            $model = self::$model;
            if ($reason != '') {
                $model->db_start();
                $query = $model->db->select("id")->from("blocked");
                $query = $query->where("reason", "=", $reason, "&&");
                if ($user_id != 0)
                    $query = $query->where("user_id", "=", $user_id, "&&");
                $c = sizeof($values);
                if ($c > 0) {
                    $query = $query->where("(");
                    $i = 0;
                    foreach ($values as $k => $v) {
                        $i++;
                        $query = ($i == $c) ? $query->where($k, "=", $v) : $query->where($k, "=", $v, "||");
                    }
                    $query = $query->where(")", "", "", "&&");
                }
                $query = $query->where("endtime", "> '" . DateManager::Now() . "'");
                return $query->build() ? $query->getAssoc() : [];
            }
        }

        static function DeleteBlocked($reason = '', $user_id = 0, $values = [])
        {
            if (!self::$model)
                return false;
            $model = self::$model;
            if ($reason != '') {
                $model->db_start();
                $query = $model->db->delete("blocked");
                $query = $query->where("reason", "=", $reason, "&&");
                if ($user_id != 0)
                    $query = $query->where("user_id", "=", $user_id, "&&");
                $c = sizeof($values);
                if ($c > 0) {
                    $query = $query->where("(");
                    $i = 0;
                    foreach ($values as $k => $v) {
                        $i++;
                        $query = ($i == $c) ? $query->where($k, "=", $v) : $query->where($k, "=", $v, "||");
                    }
                    $query = $query->where(")", "", "", "&&");
                }
                $query = $query->where("endtime", "> '" . DateManager::Now() . "'")->run();
                return $query;
            }
        }

        static function LoginCheck($type = '', $email = '', $password = '', $failed_notification = true)
        {
            Helper::Load("Notification");
            $sth = Models::$init->db->select("id,status,lang,email,password,phone")->from("users");
            if ($type != '') $sth->where("type", "=", $type, "&&");
            $sth->where("email", "=", $email);
            $sth->order_by("id ASC");
            $data = false;
            if ($build = $sth->build(true)) {
                foreach ($build->fetch_object() as $row) {
                    if (self::_password_verify($type, $password, $row->password)) $data = $row;
                    elseif ($failed_notification) {
                        if ($type == 'member' && $row->status == 'active') Notification::failed_member_login_attempt($row->id);
                        elseif ($type == 'admin' && $row->status == 'active') Notification::failed_admin_login_attempt($row->id);
                    }
                }
            }
            return $data;
        }

        static function email_check($arg, $type = 'member')
        {
            if (!self::$model)
                return false;
            $model = self::$model;
            if (!Validation::isEmpty($arg)) {
                $model->db_start();
                $statement = $model->db->select("id")->from("users");
                if ($type) $statement->where("type", "=", $type, "&&");
                $statement->where("email", "=", $arg);
                return $statement->build() ? $statement->getObject()->id : 0;
            }
        }

        static function gsm_check($arg, $code = '', $type = 'member')
        {
            if (!self::$model)
                return false;
            $model = self::$model;
            if (!Validation::isEmpty($arg)) {

                $statement = $model->db
                    ->select("t1.id")
                    ->from("users AS t1")
                    ->join("LEFT", "users_informations AS t2", "t2.owner_id=t1.id AND t2.name='gsm'")
                    ->join("LEFT", "users_informations AS t3", "t3.owner_id=t1.id AND t3.name='gsm_cc'")
                    ->where("t2.id", "IS NOT NULL", "", "&&")
                    ->where("t3.id", "IS NOT NULL", "", "&&")
                    ->where("t1.type", "=", $type, "&&")
                    ->where("t2.content", "=", $arg, "&&")
                    ->where("t3.content", "=", $code)
                    ->build();
                return $statement;
            }

            return false;
        }

        static function landlinep_check($arg)
        {
            if (!self::$model)
                return false;
            $model = self::$model;
            if (!Validation::isEmpty($arg)) {
                $model->db_start();
                $statement = $model->db
                    ->select("owner_id")
                    ->from("users_informations")
                    ->where("name", "=", "landline_phone", "AND")
                    ->where("content", "=", $arg)
                    ->build();
                return $statement;
            }
        }

        static function _crypt($type = 'member', $str = '', $action = 'encrypt', $k = '')
        {
            if (!is_string($type)) return false;
            if (!($type == 'member' || $type == 'admin')) return false;
            if (is_array($str) || is_object($str) || is_array($action) || is_object($action) || !is_string($k)) return false;
            if ($k != '-O@cb6Dxvarhc+Ghp}1eW~Y0yYfrfI#RBWdl') return false;
            if (!function_exists("password_hash")) die("Your server does not have a password_hash() function.");
            if ($action == "decrypt")
                $hash = Crypt::decode($str, $type . "-SECURITY|" . Config::get("crypt/user") . "|" . $type . "-SECURITY");
            elseif ($action == 'encryptH')
                $hash = Crypt::encode($str, $type . "-SECURITY|" . Config::get("crypt/user") . "|" . $type . "-SECURITY");
            else {
                $hash = password_hash($str, PASSWORD_BCRYPT);
                $hash = Crypt::encode($hash, $type . "-SECURITY|" . Config::get("crypt/user") . "|" . $type . "-SECURITY");
            }
            return $hash;
        }

        static function _password_verify($type = 'member', $str = '', $hash = '')
        {
            if (!is_string($type)) return false;
            if (!($type == 'member' || $type == 'admin')) return false;
            if (is_array($str) || is_object($str) || !is_string($hash)) return false;
            if (!function_exists("password_verify")) die("Your server does not have a password_verify() function.");
            if ($hash_d = self::_crypt($type, $hash, "decrypt", '-O@cb6Dxvarhc+Ghp}1eW~Y0yYfrfI#RBWdl')) $hash = $hash_d;
            return password_verify($str, $hash);
        }

        static function identity_check($arg)
        {
            if (!self::$model)
                return false;
            $model = self::$model;
            if (!Validation::isEmpty($arg)) {
                $model->db_start();
                $statement = $model->db
                    ->select("owner_id")
                    ->from("users_informations")
                    ->where("name", "=", "identity", "AND")
                    ->where("content", "=", $arg)
                    ->build();
                return $statement;
            }
        }

        static function getNotifications($user_id = 0)
        {
            $result = [
                'bubble_count' => 0,
                'items'        => [],
            ];
            if (!$user_id)
                if ($udata = UserManager::LoginData("member"))
                    $user_id = $udata["id"];

            if ($user_id) {
                Helper::Load(["Events", "Orders", "Tickets", "Invoices"]);

                Events::set_list_limit(300);
                $rows = Events::getList("notification", false, false, false, false, $user_id, 'unread ASC, id DESC');
                Events::set_list_limit(0);

                if ($rows) {
                    $items = [];
                    foreach ($rows as $row) {
                        $item = $row;
                        $item["data"] = $item["data"] ? Utility::jdecode($item["data"], true) : [];
                        $buttons = [];
                        $icon = "check";
                        $message = $item["name"];
                        $msg_data = [];

                        if ($item["owner"] == "ticket" && $item["owner_id"]) {
                            $ticket = Tickets::get_request($item["owner_id"], "title");
                            if (!$ticket) continue;
                            $msg_data = [
                                '{id}'      => $item["owner_id"],
                                '{subject}' => $ticket["title"],
                                '{link}'    => Controllers::$init->CRLink("ac-ps-detail-ticket", [$item["owner_id"]]),
                            ];
                        } elseif ($item["owner"] == "invoice" && $item["owner_id"]) {
                            $invoice = Invoices::get($item["owner_id"], ['select' => "cdate,duedate"]);
                            if (!$invoice) continue;
                            $msg_data = [
                                '{create-date}' => DateManager::format(Config::get("options/date-format"), $invoice["cdate"]),
                                '{due-date}'    => DateManager::format(Config::get("options/date-format"), $invoice["duedate"]),
                                '{id}'          => $item["owner_id"],
                                '{link}'        => Controllers::$init->CRLink("ac-ps-detail-invoice", [$item["owner_id"]]),
                            ];
                        } elseif ($item["owner"] == "order" && $item["owner_id"]) {
                            $order = Orders::get($item["owner_id"], "options");
                            if (!$order) continue;
                            $msg_data = [
                                '{id}'   => $item["owner_id"],
                                '{link}' => Controllers::$init->CRLink("ac-ps-product", [$item["owner_id"]]),
                            ];
                            if (isset($order["options"]["domain"])) $msg_data["{domain}"] = $order["options"]["domain"];
                        }

                        if ($item["data"]) foreach ($item["data"] as $k => $v) $msg_data["{" . $k . "}"] = $v;

                        if ($msg_text = __("website/events/" . $item["name"], $msg_data)) $message = $msg_text;

                        if (in_array($item["name"], [
                            "invoice-returned",
                            "invoice-cancelled",
                            "invoice-reminder",
                            "invoice-overdue",
                            "order-has-been-suspended",
                            "order-has-been-cancelled",
                            "domain-has-been-suspended",
                            "domain-has-been-cancelled",
                            "sms-origin-has-been-inactivated",
                            "intl-sms-origin-has-been-inactivated",
                            "credit-fell-below-a-minimum",
                        ])) $icon = "warning";


                        $item["ctime"] = DateManager::strtotime($row["cdate"]);
                        $item["icon"] = $icon;
                        $item["message"] = $message;
                        $item["buttons"] = $buttons;
                        $items[] = $item;
                        if (!$row["unread"]) $result["bubble_count"] += 1;
                    }
                    $result["items"] = $items;
                }
            }

            return $result;
        }

        static function get_affiliate($user_id = 0, $id = 0)
        {
            $stmt = Models::$init->db->select()->from("users_affiliates");
            if ($id) $stmt->where("id", "=", $id);
            else $stmt->where("owner_id", "=", $user_id);
            $data = $stmt->build() ? $stmt->getAssoc() : false;
            if ($data) $data['payment_information'] = $data['payment_information'] ? Utility::jdecode($data['payment_information'], true) : [];

            return $data;
        }

        static function set_affiliate($id = 0, $data = [])
        {
            return Models::$init->db->update("users_affiliates", $data)->where("id", "=", $id)->save();
        }

        static function insert_affiliate($data = [])
        {
            return Models::$init->db->insert("users_affiliates", $data) ? Models::$init->db->lastID() : 0;
        }

        static function affiliate_insert_transaction($data = [])
        {
            return Models::$init->db->insert("users_affiliate_transactions", $data) ? Models::$init->db->lastID() : 0;
        }

        static function affiliate_apply_transaction($transaction_type='',$order=[],$product=[],$force_aff=0)
        {
            if(in_array($order['type'],['hosting','server','software','sms','special']) && Config::get("options/affiliate/status") && !(isset($product["affiliate_disable"]) && $product["affiliate_disable"] == 1))
            {
                $aff_id              = self::getData($order["owner_id"],"aff_id")->aff_id;
                if($force_aff) $aff_id = $force_aff;

                $aff_ctime          = current(self::getInfo($order["owner_id"],"aff_ctime"));
                $affiliates         = current(self::getInfo($order["owner_id"],"Affiliates"));
                $affiliates         = $affiliates ? Utility::jdecode($affiliates,true) : [];
                $day                = Config::get("options/affiliate/cookie-duration");
                foreach($affiliates AS $k=>$v)
                {
                    $expires                = DateManager::strtotime(DateManager::next_date([$v, 'day' => $day]));
                    if(DateManager::strtotime() > $expires || $aff_id == $k) unset($affiliates[$k]);
                }
                if($affiliates)
                    self::setInfo($order['owner_id'],['Affiliates' => Utility::jencode($affiliates)]);
                else
                    self::deleteInfo($order['owner_id'],'Affiliates');

                if($aff_id) $affiliates[$aff_id] = $aff_ctime ? $aff_ctime : DateManager::Now();

                $standard_rate      = Config::get("options/affiliate/rate");
                if(isset($product['affiliate_rate']) && $product['affiliate_rate'] > 0.0)
                    $standard_rate = $product['affiliate_rate'];

                foreach($affiliates AS $k => $v)
                {
                    $aff                = self::get_affiliate(0,$k);
                    if($aff)
                    {
                        $_rate              = $aff['commission_value'] > 0.0 ? $aff['commission_value'] : $standard_rate;
                        $commission_delay   = Config::get("options/affiliate/commission-delay");
                        $_exchange          = 0;
                        if($order["amount_cid"] != $aff['currency'])
                            $_exchange = round(Money::exChange(1,$order["amount_cid"],$aff['currency']),2);
                        $_commission        = Money::get_discount_amount($order["amount"],$_rate);
                        $_commission        = Money::exChange($_commission,$order["amount_cid"],$aff['currency']);
                        $_status            = 'approved';
                        if($aff_id == 0) $_status = 'invalid';
                        elseif($k != $aff_id) $_status = 'invalid-another';

                        if($aff['disabled']) continue;

                        if($transaction_type == 'renewal')
                        {
                            if($aff['commission_period']){
                                if($aff['commission_period'] != 'lifetime') continue;
                            }
                            elseif(Config::get("options/affiliate/commission-period") != "lifetime") continue;
                        }

                        $passInsert = false;

                        if($transaction_type == "sale") {
                            $checkPreviously = WDB::select("id")->from("users_affiliate_transactions");
                            $checkPreviously->where("order_id","=",$order["id"]);
                            if($checkPreviously->build())
                                $passInsert = true;
                        }

                        if(!$passInsert)
                            self::affiliate_insert_transaction([
                                'affiliate_id'          => $k,
                                'order_id'              => $order['id'],
                                'clicked_ctime'         => $v,
                                'ctime'                 => DateManager::Now(),
                                'clearing_date'         => DateManager::next_date(['day' => $commission_delay],'Y-m-d'),
                                'amount'                => $order["amount"],
                                'currency'              => $order["amount_cid"],
                                'rate'                  => $_rate,
                                'commission'            => $_commission,
                                'exchange'              => $_exchange,
                                'status'                => $_status,
                            ]);
                    }
                }
                return true;
            }
            return false;
        }


        static function affiliate_cancel_order_transaction($order = [])
        {
            $stmt = Models::$init->db->update("users_affiliate_transactions", ['status' => 'cancelled']);
            $stmt->where("order_id", "=", $order["id"], "&&");
            $stmt->where("status", "=", "approved");
            return $stmt->save();
        }

        static function affiliate_delete_order_transaction($order = [])
        {
            $stmt = Models::$init->db->delete("users_affiliate_transactions");
            $stmt->where("order_id", "=", $order["id"], "&&");
            $stmt->where("status", "!=", "invalid", "&&");
            $stmt->where("status", "!=", "invalid-another", "&&");
            $stmt->where("status", "!=", "completed");
            return $stmt->run();
        }

        static function affiliate_pending_balance_sync($aff = [])
        {
            $changes = [];
            $balance = $aff['balance'];
            $transactions = Models::$init->db->select()->from("users_affiliate_transactions");
            $transactions->where("affiliate_id", "=", $aff['id'], "&&");
            $transactions->where("status", "=", "approved", "&&");
            $transactions->where("clearing_date", "<=", DateManager::Now("Y-m-d"));
            $transactions = $transactions->build() ? $transactions->fetch_assoc() : false;
            if ($transactions) {
                foreach ($transactions as $transaction) {
                    $commission = $transaction['commission'];
                    $balance += $commission;
                    $changes[] = $transaction;
                    Models::$init->db
                        ->update("users_affiliate_transactions")
                        ->set([
                            'status'         => "completed",
                            'completed_time' => DateManager::Now(),
                        ])
                        ->where("id", "=", $transaction['id'])
                        ->save();
                }
            }
            if ($changes) self::set_affiliate($aff['id'], ['balance' => $balance]);
            return $changes;
        }

        static function affiliate_pending_balance($aff_id = 0, $today = false)
        {
            $stmt = Models::$init->db->select("SUM(commission) AS total")->from("users_affiliate_transactions");
            $stmt->where("affiliate_id", "=", $aff_id, "&&");
            if ($today) $stmt->where("ctime", "LIKE", "%" . DateManager::Now("Y-m-d") . "%", "&&");
            $stmt->where("status", "=", "approved");
            return $stmt->build() ? $stmt->getObject()->total : 0;
        }

        static function affiliate_withdrawals_total($aff_id = 0)
        {
            $stmt = Models::$init->db->select("SUM(amount) AS total")->from("users_affiliate_withdrawals");
            $stmt->where("affiliate_id", "=", $aff_id, "&&");
            $stmt->where("status", "=", "completed");
            return $stmt->build() ? $stmt->getObject()->total : 0;
        }

        static function affiliate_references_total($aff_id = 0, $today = false)
        {
            $stmt = Models::$init->db->select("COUNT(id) AS total")->from("users");
            $stmt->where("aff_id", "=", $aff_id, "&&");
            if ($today) $stmt->where("creation_time", "LIKE", "%" . DateManager::Now("Y-m-d") . "%", "&&");
            $stmt->where("status", "=", "active");
            return $stmt->build() ? $stmt->getObject()->total : 0;
        }

        static function affiliate_hits_total($aff_id = 0, $today = false)
        {
            $stmt = Models::$init->db->select("COUNT(id) AS total")->from("users_affiliate_hits");
            if ($today) $stmt->where("ctime", "LIKE", "%" . DateManager::Now("Y-m-d") . "%", "&&");
            $stmt->where("affiliate_id", "=", $aff_id);
            return $stmt->build() ? $stmt->getObject()->total : 0;
        }

        static function affiliate_transactions($aff_id = 0)
        {
            $select = implode(',', [
                't1.*',
                't3.id AS user_id',
                't3.full_name',
                't2.id AS order_id',
                't2.name AS order_name',
            ]);
            $stmt = Models::$init->db->select($select)->from("users_affiliate_transactions AS t1");
            $stmt->join("LEFT", "users_products AS t2", "t1.order_id=t2.id");
            $stmt->join("LEFT", "users AS t3", "t3.id=t2.owner_id");

            $stmt->where("t1.affiliate_id", "=", $aff_id);

            $stmt->group_by("t1.id");
            $stmt->order_by("t1.id DESC");
            return $stmt->build() ? $stmt->fetch_assoc() : [];
        }

        static function affiliate_withdrawals($aff_id = 0)
        {
            $case = "CASE ";
            $case .= "WHEN status = 'awaiting' THEN 0 ";
            $case .= "WHEN status = 'process' THEN 1 ";
            $case .= "WHEN status = 'completed' THEN 2 ";
            $case .= "ELSE 3 ";
            $case .= "END AS rank";
            $select = "*," . $case;
            $stmt = Models::$init->db->select($select)->from("users_affiliate_withdrawals");
            $stmt->where("affiliate_id", "=", $aff_id);
            $stmt->order_by("rank ASC,id DESC");
            return $stmt->build() ? $stmt->fetch_assoc() : [];
        }

        static function affiliate_referrers($aff_id = 0)
        {
            $select = implode(',', [
                't1.id',
                't1.referrer',
                'COUNT(t2.id) AS hits',
            ]);
            $stmt = Models::$init->db->select($select)->from("users_affiliate_referrers AS t1");
            $stmt->join("LEFT", "users_affiliate_hits AS t2", "t2.referrer_id=t1.id");
            $stmt->where("t1.affiliate_id", "=", $aff_id);
            $stmt->group_by("t1.id");
            $stmt->order_by("COUNT(t2.id) DESC");
            return $stmt->build() ? $stmt->fetch_assoc() : [];
        }

        static function dealership_orders($u_id = 0, $d_rates = [])
        {
            Helper::Load("Products");
            $p_ids = [];
            if (!isset($d_rates["default"])) {
                foreach ($d_rates as $dk => $dv) {
                    $c_s = explode("/", $dk);
                    $p_s = explode("-", $dk);
                    $is_g = !isset($c_s[1]) && !isset($p_s[1]);
                    $is_c = isset($c_s[1]);


                    if ($is_g) {
                        $g = $c_s[0];

                        if ($g == "domain") {
                            $sth = Models::$init->db->select("id")->from("tldlist");
                            $sth = $sth->build() ? $sth->fetch_assoc() : [];
                            foreach ($sth as $s) if (!isset($p_ids[$g]) || !in_array($s["id"], $p_ids[$g])) $p_ids[$g][] = $s["id"];
                        } elseif ($c_s[0] == "software") {
                            $sth = Models::$init->db->select("id")->from("pages");
                            $sth->where("type", "=", "software");
                            $sth = $sth->build() ? $sth->fetch_assoc() : [];
                            foreach ($sth as $s) if (!isset($p_ids[$g]) || !in_array($s["id"], $p_ids[$g])) $p_ids[$g][] = $s["id"];
                        } else {
                            $sth = Models::$init->db->select("id")->from("products");
                            $sth->where("type", "=", $g);
                            $sth = $sth->build() ? $sth->fetch_assoc() : [];
                            foreach ($sth as $s) if (!isset($p_ids[$g]) || !in_array($s["id"], $p_ids[$g])) $p_ids[$g][] = $s["id"];
                        }

                    } elseif ($is_c) {
                        $c_t = $c_s[0];
                        $c_id = $c_s[1];

                        $c_ids = Products::get_sub_category_ids($c_id, true);
                        if (!is_array($c_ids)) $c_ids = [];
                        if (!in_array($c_id, $c_ids)) $c_ids[] = $c_id;
                        $c_ids = is_array($c_ids) && $c_ids ? implode(",", $c_ids) : '';
                        if ($c_ids) {
                            if ($c_t == "software") {

                                $sth = Models::$init->db->select("id")->from("pages");
                                if ($c_ids) $sth->where("FIND_IN_SET(category,'" . $c_ids . "')", "", "", "&&");
                                $sth->where("type", "=", "software");
                                $sth = $sth->build() ? $sth->fetch_assoc() : [];
                                foreach ($sth as $s) {
                                    if (!isset($p_ids[$c_t]) || !in_array($s["id"], $p_ids[$c_t])) $p_ids[$c_t][] = $s["id"];
                                }
                            } else {
                                $sth = Models::$init->db->select("id")->from("products");
                                if ($c_ids) $sth->where("FIND_IN_SET(category,'" . $c_ids . "')", "", "", "&&");
                                $sth->where("type", "=", $c_t);
                                $sth = $sth->build() ? $sth->fetch_assoc() : [];
                                foreach ($sth as $s) if (!isset($p_ids[$c_t]) || !in_array($s["id"], $p_ids[$c_t])) $p_ids[$c_t][] = $s["id"];
                            }
                        }
                    } elseif (!in_array($p_s[1], $p_ids[$p_s[0]] ?? []))
                        $p_ids[$p_s[0]][] = $p_s[1];
                }
            }

            $orders = Models::$init->db->select("ord.id")->from("invoices_items AS its");
            $orders->join("LEFT", "invoices AS inv", "its.owner_id=inv.id");
            $orders->join("LEFT", "users_products AS ord", "its.user_pid=ord.id");

            $orders->where("its.user_id", "=", $u_id, "&&");
            $orders->where("its.user_pid", ">", "0", "&&");
            $orders->where("its.amount", ">", "0.00", "&&");
            $orders->where("ord.owner_id", "=", $u_id, "&&");
            $orders->where("ord.status", "=", "active", "&&");

            if (!isset($d_rates["default"])) {
                if ($p_ids) {
                    $orders->where("(");
                    $e_p_t = array_keys($p_ids);
                    $e_p_t = end($e_p_t);

                    foreach ($p_ids as $p_type => $_p_ids) {
                        $_p_ids = implode(",", $_p_ids);
                        $orders->where("(");
                        $orders->where("ord.type", "=", $p_type, "&&");
                        $orders->where("FIND_IN_SET(ord.product_id,'" . $_p_ids . "')");
                        $orders->where(")", "", "", $e_p_t == $p_type ? "" : "||");
                    }
                    $orders->where(")", "", "", "&&");
                }
            }
            $orders->where("inv.status", "=", "paid");
            $orders->group_by("ord.id");
            $orders = $orders->build() ? $orders->fetch_assoc() : [];

            return $orders;
        }

        static function dealership_statistics($id = 0, $u_d_info = [])
        {
            $d_info = (array)Config::get("options/dealership/rates");
            if (is_array(current($u_d_info))) $d_info = array_replace_recursive($d_info, $u_d_info);

            Helper::Load("Invoices");
            Helper::Load("Orders");
            $l_cid = Config::get("general/currency");
            $result = [
                'total_sales'       => 0,
                'total_sales_today' => 0,
                'turnover'          => 0,
                'turnover_today'    => 0,
                'discounts'         => 0,
                'discounts_today'   => 0,
                'currency'          => $l_cid,
                'orders'            => [],
            ];

            $invoices = Models::$init->db->select("id,discounts,currency,datepaid")->from("invoices");
            $invoices->where("user_id", "=", $id, "&&");
            $invoices->where("status", "=", "paid", "&&");
            $invoices->where("discounts", "LIKE", "%dealership%");
            $invoices->order_by("id DESC");
            $invoices = $invoices->build() ? $invoices->fetch_assoc() : false;

            if ($invoices) {
                foreach ($invoices as $invoice) {
                    $items = Invoices::get_items($invoice['id']);
                    $_discounts = Utility::jdecode($invoice["discounts"], true);
                    $_d_items = $_discounts['items']['dealership'];
                    foreach ($_d_items as $item) {
                        $amount = Money::exChange($item['amountd'], $invoice['currency'], $l_cid);
                        $result['discounts'] += $amount;
                        if (stristr($invoice['datepaid'], DateManager::Now("Y-m-d"))) $result['discounts_today'] += $amount;
                    }

                    foreach ($items as $item) {
                        if ($item['user_pid'] && isset($item['options']['event'])) {
                            if (stristr($item['options']['event'], 'Order')) {
                                $o = Models::$init->db->select("id,type");
                                $o->from("users_products");
                                $o->where("id", "=", $item["user_pid"]);
                                $o = $o->build() ? $o->getAssoc() : false;
                                if ($o) {

                                    $amount = Money::exChange($item['amount'], $invoice['currency'], $l_cid);
                                    $result['turnover'] += $amount;
                                    $result["total_sales"] += 1;
                                    if (stristr($invoice['datepaid'], DateManager::Now("Y-m-d"))) {
                                        $result['turnover_today'] += $amount;
                                        $result["total_sales_today"] += 1;
                                    }

                                    if (!isset($result["orders"][$o["id"]])) {
                                        $o = Orders::get($o["id"]);
                                        $o["discount"] = isset($_d_items[$item["id"]]) ? $_d_items[$item["id"]] : [];
                                        $o["inv_curr"] = $invoice["currency"];
                                        $result["orders"][$o["id"]] = $o;
                                    }

                                }
                            }
                        }
                    }
                }
            }

            return $result;
        }

        static function bulk_notification_contact_list($user_type, $type, $user_groups, $departments, $countries, $languages, $services, $servers, $addons, $services_ss, $without_products, $client_ss, $birthday_marketing)
        {
            if (!$user_groups && !$departments && !$countries && !$languages && !$services && !$servers && !$addons && !$services_ss && !$without_products && !$client_ss && !$birthday_marketing) return false;

            $ssi_type = $type == "gsm" || $type == "sms" ? "sms" : "email";

            $select = [
                't1.id',
                't1.full_name',
                't1.email',
                't1.company_name',
            ];
            if ($type == "gsm") $select[] = "t1.phone";

            $select = implode(",", $select);

            $admins = $user_type == "staff";

            if ($admins) $stmt = Models::$init->db->select($select)->from("users AS t1");
            elseif ($services || $addons || $servers || $services_ss) {
                $stmt = Models::$init->db->select($select)->from("users_products AS ss");
                $stmt->join("LEFT", "users AS t1", "ss.owner_id=t1.id");
                if ($addons) $stmt->join("LEFT", "users_products_addons AS ad", "ad.owner_id=ss.id");

                $stmt->where("t1.id", "IS NOT NULL", "", "&&");
                if ($addons) $stmt->where("ad.id", "IS NOT NULL", "", "&&");

                if ($services) {
                    $stmt->where("(");
                    $size = sizeof($services) - 1;
                    foreach ($services as $k => $service) {
                        $parse = explode("/", $service);
                        $logical = $k == $size ? '' : '||';
                        if ($parse[0] == "allOf") {

                            if (
                                $parse[1] == "hosting" ||
                                $parse[1] == "server" ||
                                $parse[1] == "softwares" ||
                                $parse[1] == "sms" ||
                                $parse[1] == "domain"
                            ) {
                                if ($parse[1] == "softwares") $parse[1] = "software";
                                $stmt->where("(");
                                $stmt->where("ss.type", "=", $parse[1]);
                                $stmt->where(")", "", "", $logical);
                            } elseif ($parse[1] == "special") {
                                $stmt->where("(");
                                $stmt->where("ss.type", "=", $parse[1], "&&");
                                $stmt->where("ss.type_id", "=", (int)$parse[2]);
                                $stmt->where(")", "", "", $logical);
                            }
                        } elseif ($parse[0] == "category") {
                            if (
                                $parse[1] == "hosting" ||
                                $parse[1] == "server" ||
                                $parse[1] == "software"
                            ) {
                                $stmt->where("(");
                                $stmt->where("ss.type", "=", $parse[1], "&&");
                                $stmt->where("ss.options", "LIKE", '%\"category_id\":\"' . (int)$parse[2] . '\"%');
                                $stmt->where(")", "", "", $logical);
                            } elseif ($parse[1] == "special") {
                                $type_id = (int)$parse[2];
                                $cat_id = (int)$parse[3];

                                $stmt->where("(");
                                $stmt->where("ss.type", "=", $parse[1], "&&");
                                $stmt->where("ss.type_id", "=", $type_id, "&&");
                                $stmt->where("ss.options", "LIKE", '%\"category_id\":\"' . $cat_id . '\"%');
                                $stmt->where(")", "", "", $logical);
                            }
                        } elseif ($parse[0] == "product") {
                            if (
                                $parse[1] == "hosting" ||
                                $parse[1] == "server" ||
                                $parse[1] == "software" ||
                                $parse[1] == "domain" ||
                                $parse[1] == "special" ||
                                $parse[1] == "sms"
                            ) {
                                $stmt->where("(");
                                $stmt->where("ss.type", "=", $parse[1], "&&");
                                $stmt->where("ss.product_id", "=", (int)$parse[3]);
                                $stmt->where(")", "", "", $logical);

                            }
                        }
                    }
                    $stmt->where(")", "", "", "&&");
                }

                if ($servers) {
                    $servers_size = sizeof($servers) - 1;
                    $stmt->where("(");
                    foreach ($servers as $k => $sv) {
                        $logical = $k == $servers_size ? '' : '||';
                        $stmt->where("JSON_EXTRACT(ss.options, '$.server_id')", "=", $sv, $logical);
                    }
                    $stmt->where(")", "", "", "&&");
                }

                if ($addons) {
                    $addons_size = sizeof($addons) - 1;
                    $stmt->where("(");
                    foreach ($addons as $k => $ad) {
                        $logical = $k == $addons_size ? '' : '||';
                        $stmt->where("ad.addon_id", "=", $ad, $logical);
                    }
                    $stmt->where(")", "", "", "&&");
                }

                if ($services_ss && $addons) {
                    $services_ss_size = sizeof($services_ss) - 1;
                    $stmt->where("(");
                    foreach ($services_ss as $service_ss_i => $service_ss) {
                        $e_logical = ($service_ss_i == $services_ss_size) ? '' : '||';
                        $stmt->where("ad.status", "=", $service_ss, $e_logical);
                    }
                    $stmt->where(")", "", "", "&&");
                } elseif ($services_ss) {
                    $services_ss_size = sizeof($services_ss) - 1;
                    $stmt->where("(");
                    foreach ($services_ss as $service_ss_i => $service_ss) {
                        $e_logical = ($service_ss_i == $services_ss_size) ? '' : '||';
                        $stmt->where("ss.status", "=", $service_ss, $e_logical);
                    }
                    $stmt->where(")", "", "", "&&");
                }

                $stmt->where("(SELECT id FROM " . Models::$init->pfx . "users_informations AS t2 WHERE owner_id=ss.owner_id AND name='" . $ssi_type . "_notifications' AND content='1')", "", "", "&&");

                $stmt->where("(SELECT COUNT(id) FROM " . Models::$init->pfx . "users_informations AS t2 WHERE owner_id=ss.owner_id AND name='" . $ssi_type . "_notifications' AND content='1')", ">", "0", "&&");
            } else {

                $stmt = Models::$init->db->select($select)->from("users_informations AS t2");
                $stmt->join("LEFT", "users AS t1", "t1.id=t2.owner_id");

                $stmt->where("t1.id", "IS NOT NULL", "", "&&");

                if ($without_products)
                    $stmt->where("(SELECT COUNT(id) FROM " . Models::$init->pfx . "users_products WHERE owner_id=t1.id)", "<", "1", "&&");

                $stmt->where("(");
                if ($type == "email") {
                    $stmt->where("t2.name", "=", "email_notifications", "&&");
                } elseif ($type == "gsm") {
                    $stmt->where("t2.name", "=", "sms_notifications", "&&");
                }
                $stmt->where("t2.content", "=", "1");
                $stmt->where(")", "", "", "&&");
            }

            if ($user_groups && $user_groups[0] != 0) {
                $set_ugps = [];
                foreach ($user_groups as $group) {
                    $group = (int)Filter::numbers($group);
                    if ($group) $set_ugps[] = $group;
                }
                $stmt->where("FIND_IN_SET(t1.group_id,'" . implode(",", $set_ugps) . "')", "", "", "&&");
            }

            if ($departments) {
                $size = sizeof($departments);
                $sizecr = 0;
                foreach ($departments as $department) {
                    $department = (int)Filter::numbers($department);
                    if ($department) {
                        $sizecr++;
                        if ($sizecr == 1) $stmt->where("(");
                        $logical = $sizecr == $size ? '' : '||';
                        $query = "(SELECT id FROM " . Models::$init->pfx . "tickets_departments WHERE FIND_IN_SET(t1.id,appointees) AND id=" . $department . ")";
                        $stmt->where($query, "", "", $logical);
                        if ($sizecr == $size) $stmt->where(")", "", "", "&&");
                    }
                }
            }

            if ($countries) {
                $set_crs = [];
                foreach ($countries as $country) {
                    $country = (int)Filter::numbers($country);
                    if ($country) $set_crs[] = $country;
                }
                $stmt->where("FIND_IN_SET(t1.country,'" . implode(",", $set_crs) . "')", "", "", "&&");
            }

            if ($languages) {
                $set_lgs = [];
                foreach ($languages as $language) {
                    $language = substr(Filter::route($language), 0, 5);
                    if ($language) $set_lgs[] = $language;
                }
                $stmt->where("FIND_IN_SET(t1.lang,'" . implode(",", $set_lgs) . "')", "", "", "&&");
            }


            if ($birthday_marketing)
                $stmt->where("(SELECT COUNT(id) FROM " . Models::$init->pfx . "users_informations WHERE owner_id=t1.id AND name='birthday' AND MONTH(content)='" . DateManager::Now("m") . "' AND DAY(content)='" . DateManager::Now("d") . "')", ">", "0", "&&");

            if ($client_ss) {
                $client_ss_size = sizeof($client_ss);
                $stmt->where("(");
                foreach ($client_ss as $client_ss_i => $client_s) {
                    $e_logical = ($client_ss_i + 1 == $client_ss_size) ? '' : '||';
                    $stmt->where("t1.status", "=", $client_s, $e_logical);
                }
                $stmt->where(")", "", "", "&&");
            }

            if ($admins) $stmt->where("t1.type", "=", "admin");
            else $stmt->where("t1.type", "=", "member");

            $stmt->group_by("t1.id");

            $stmt->order_by("t1.id DESC");

            $build = $stmt->build();

            return $build ? $stmt->fetch_assoc() : false;
        }

        static function overwrite_new_address_on_invoices($user_id = 0, $address_id = 0, $inv_id = 0)
        {
            Helper::Load("Invoices");
            $addr = AddressManager::getAddress($address_id, $user_id);
            if (!$addr) return false;

            $invoiceIDs = Models::$init->db->select("id")->from("invoices");
            $invoiceIDs->where("user_id", "=", $user_id, "&&");
            if ($inv_id) $invoiceIDs->where("id", "=", $inv_id, "&&");
            $invoiceIDs->where("status", "=", "unpaid");
            $invoiceIDs = $invoiceIDs->build() ? $invoiceIDs->fetch_object() : [];

            $returnIds = [];

            if ($invoiceIDs) {
                foreach ($invoiceIDs as $inv) {
                    $invoice = Invoices::get($inv->id);
                    $user_data = $invoice["user_data"];

                    $user_data["default_address"] = $address_id;

                    if (Utility::strlen($addr["email"]) > 1) $user_data["email"] = $addr["email"];
                    if (Utility::strlen($addr["name"]) > 1) $user_data["name"] = $addr["name"];
                    if (Utility::strlen($addr["surname"]) > 1) $user_data["surname"] = $addr["surname"];
                    if (Utility::strlen($addr["full_name"]) > 1) $user_data["full_name"] = $addr["full_name"];
                    if (Utility::strlen($addr["full_name"]) > 1) {
                        if (Utility::strlen($addr["phone"]) > 4) {
                            $user_data["phone"] = $addr["phone"];
                            $phone_smash = Filter::phone_smash($addr["phone"]);
                            $user_data["gsm_cc"] = $phone_smash["cc"];
                            $user_data["gsm"] = $phone_smash["number"];

                        } else {
                            $user_data["phone"] = '';
                            $user_data["gsm_cc"] = '';
                            $user_data["gsm"] = '';
                        }
                    }
                    if (Utility::strlen($addr["kind"]) > 1) $user_data["kind"] = $addr["kind"];

                    if (Utility::strlen($addr["full_name"]) > 1) {
                        $user_data["company_name"] = $addr["company_name"];
                        $user_data["company_tax_number"] = $addr["company_tax_number"];
                        $user_data["company_tax_office"] = $addr["company_tax_office"];
                    }

                    $identity_status = Config::get("options/sign/up/kind/individual/identity/status");
                    $identity_required = Config::get("options/sign/up/kind/individual/identity/required");
                    if ($identity_status && $identity_required && !Validation::isEmpty($addr["identity"]))
                        $user_data["identity"] = $addr["identity"];


                    $fake_addr = $addr;

                    unset($fake_addr["name"]);
                    unset($fake_addr["surname"]);
                    unset($fake_addr["full_name"]);
                    unset($fake_addr["kind"]);
                    unset($fake_addr["company_name"]);
                    unset($fake_addr["company_tax_office"]);
                    unset($fake_addr["company_tax_number"]);
                    unset($fake_addr["phone"]);
                    unset($fake_addr["email"]);
                    unset($fake_addr["identity"]);

                    $user_data["address"] = $fake_addr;
                    $set_data = ['user_data' => Utility::jencode($user_data)];

                    $local      = Invoices::isLocal($addr["country_id"],$user_id);
                    $taxation   = Invoices::getTaxation($addr["country_id"],$user_data["taxation"]);
                    $legal      = $local;
                    $city_id    = $addr["city_id"] ? $addr["city_id"] : $addr["city"];

                    $tax_rate   = Invoices::getTaxRate($addr["country_id"],$city_id,$user_id);

                    $set_data["local"] = $local ? 1 : 0;
                    $set_data["legal"] = $legal ? 1 : 0;

                    if($set_data["legal"] > 0 && $taxation && !((float) $invoice["taxrate"] > 0.00))
                        $set_data["taxrate"] = $tax_rate;

                    Invoices::set($invoice["id"], $set_data);
                    $returnIds[] = $invoice["id"];
                }

                return $returnIds;
            }


        }

        static function whois_profiles($uid = 0)
        {
            $select = [
                "*",
                "JSON_UNQUOTE(JSON_EXTRACT(information,'$.Name')) AS person_name",
                "JSON_UNQUOTE(JSON_EXTRACT(information,'$.EMail')) AS person_email",
                "CONCAT_WS('','+',JSON_UNQUOTE(JSON_EXTRACT(information,'$.PhoneCountryCode')),JSON_UNQUOTE(JSON_EXTRACT(information,'$.Phone'))) AS person_phone",
            ];
            $stmt = Models::$init->db->select(implode(",", $select))->from("users_whois_profiles");
            $stmt->where("owner_id", "=", $uid);
            $stmt->order_by("detouse DESC,id DESC");
            return $stmt->build() ? $stmt->fetch_assoc() : [];
        }

        static function create_whois_profile($data = [])
        {
            return Models::$init->db->insert("users_whois_profiles", $data) ? Models::$init->db->lastID() : 0;
        }

        static function remove_detouse_whois_profile($uid = 0)
        {
            return Models::$init->db->update("users_whois_profiles", ['detouse' => 0])->where("owner_id", "=", $uid)->save();
        }

        static function set_whois_profile($id = 0, $set = [])
        {
            return Models::$init->db->update("users_whois_profiles", $set)->where("id", "=", $id)->save();
        }

        static function get_whois_profile($id = 0, $uid = 0)
        {
            $stmt = Models::$init->db->select()->from("users_whois_profiles");
            if ($uid) $stmt->where("owner_id", "=", $uid, "&&");
            $stmt->where("id", "=", $id);
            $stmt = $stmt->build() ? $stmt->getAssoc() : [];

            if ($stmt) $stmt["information"] = Utility::jdecode($stmt["information"], true);

            return $stmt;
        }

        static function delete_whois_profile($id = 0)
        {
            return Models::$init->db->delete("users_whois_profiles")->where("id", "=", $id)->run();
        }

    }