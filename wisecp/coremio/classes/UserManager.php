<?php

    class UserManager
    {
        static $model;
        private static $ip_address, $login_data = [];
        private static $ip_info_log = [];
        private static $ip_proxy_log = [];
        private static $mail_temporary_log = [];


        static function is_temporary_mail($email = '', $ip = '')
        {
            if (!$ip) $ip = self::GetIP();
            if (stristr($email, "@")) {
                $split = explode("@", $email);
                $domain = $split[1];
            } else
                $domain = $email;


            if (isset(self::$mail_temporary_log[$domain])) return self::$mail_temporary_log[$domain];

            $log_file = ROOT_DIR . "temp" . DS . $domain . "-temporary-mail.json";


            $error = false;

            if (file_exists($log_file))
                $response = FileManager::file_read($log_file);
            else {

                $overwrite_limit = (int)Config::get("options/temporary-overload-limit");
                $over_limit = $overwrite_limit > 0 ? $overwrite_limit : 100;

                $hit_file = CORE_DIR . "storage" . DS . "temporary-mail-overload-limit.php";
                if (!file_exists($hit_file)) touch($hit_file);
                $gcon = FileManager::file_read($hit_file);
                if ($gcon)
                    $con = include $hit_file;
                else
                    $con = false;
                if ($con && is_array($con) && isset($con["date"]) && isset($con["total"])) {
                    if (DateManager::Now("Y-m-d") == $con["date"])
                        $con["total"]++;
                    else {
                        $con["date"] = DateManager::Now("Y-m-d");
                        $con["total"] = 1;
                    }
                } else
                    $con = [
                        'date'  => DateManager::Now("Y-m-d"),
                        'total' => 1,
                    ];
                if ($con["total"] >= $over_limit) return false;


                $address = "http://sapigateway.com/temporary/check";
                $ch = curl_init($address);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, Utility::jencode([
                    'order-key' => Config::get("general/order-key"),
                    'domain'    => Utility::getDomain(),
                    'target'    => $domain,
                    'ip'        => $ip,
                ]));
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);

                $response = curl_exec($ch);
                if (!$response && curl_errno($ch)) $error = curl_error($ch);
                curl_close($ch);

                FileManager::file_write($hit_file, Utility::array_export($con, ['pwith' => true]));
            }


            $data = Utility::jdecode($response, true);
            if (!$data && !$error) $error = $response;

            if (isset($data["status"]) && isset($data["message"]) && $data["status"] == "error" && $data["message"]) {
                $error = $data["message"];
                $data = false;
            }

            if ($data) $result = $data["result"] == 1;
            else $result = 0;

            if ($data && !file_exists($log_file)) FileManager::file_write($log_file, $response);
            if ($data) self::$mail_temporary_log[$domain] = $result;

            if ($error) {
                if (stristr($error, 'timed out')) return false;
                if (stristr($error, 'timeout')) return false;
                Modules::save_log("TEMPORARY", "WiseTemporary", "check", $domain, $error);
            }

            return $result;
        }

        static function is_proxy($ip = '')
        {
            if (!$ip) $ip = self::GetIP();

            $whitelist = Config::get("options/proxy-block-whitelist");

            if (isset(self::$ip_proxy_log[$ip])) return self::$ip_proxy_log[$ip];


            $log_file = ROOT_DIR . "temp" . DS . $ip . "-proxy.json";

            $error = false;

            if (file_exists($log_file))
                $response = FileManager::file_read($log_file);
            else {

                $overwrite_limit = (int)Config::get("options/proxy-overload-limit");
                $over_limit = $overwrite_limit > 0 ? $overwrite_limit : 100;
                $hit_file = CORE_DIR . "storage" . DS . "proxy-overload-limit.php";
                if (!file_exists($hit_file)) touch($hit_file);
                $gcon = FileManager::file_read($hit_file);
                if ($gcon)
                    $con = include $hit_file;
                else
                    $con = false;
                if ($con && is_array($con) && isset($con["date"]) && isset($con["total"])) {
                    if (DateManager::Now("Y-m-d") == $con["date"])
                        $con["total"]++;
                    else {
                        $con["date"] = DateManager::Now("Y-m-d");
                        $con["total"] = 1;
                    }
                } else
                    $con = [
                        'date'  => DateManager::Now("Y-m-d"),
                        'total' => 1,
                    ];
                if ($con["total"] >= $over_limit) return false;


                $emails = Config::get("contact/email-addresses");

                if ($emails && isset($emails[0])) $email = $emails[0];
                else $email = isset($_SERVER["SERVER_ADMIN"]) ? $_SERVER["SERVER_ADMIN"] : 'hello@wisecp.com';

                $sapigateway = true;
                $host = "sapigateway.com";

                if (Config::get("options/proxy-block-host")) {
                    $sapigateway = false;
                    $host = Config::get("options/proxy-block-host");
                }

                $address = "http://" . $host;

                if ($sapigateway)
                    $address .= '/proxy/' . $ip;
                else
                    $address = Utility::text_replace($address,[
                        '{ip}' => $ip,
                        '{email}' => $email,
                    ]);
                $ch = curl_init($address);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                if ($sapigateway) {
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, Utility::jencode([
                        'order-key' => Config::get("general/order-key"),
                        'domain'    => Utility::getDomain(),
                    ]));
                }
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);

                $response = curl_exec($ch);


                if (!$response && curl_errno($ch)) $error = curl_error($ch);
                curl_close($ch);

                FileManager::file_write($hit_file, Utility::array_export($con, ['pwith' => true]));
            }

            $data = Utility::jdecode($response, true);
            if (!$data && !$error) $error = $response;

            if (isset($data["status"]) && isset($data["message"]) && $data["status"] == "error" && $data["message"]) {
                $error = $data["message"];
                $data = false;
            }
            if ($data) {
                $rate = round($data["result"], 2);
                $result = $rate >= 1 ? 1 : 0;
            } else $result = 0;

            if ($result && !Validation::isEmpty($whitelist)) {

                if ($whitelist) if (stristr($whitelist, $ip)) $result = 0;
                $white_list = explode("\n", $whitelist);
                foreach ($white_list as $wl) {
                    if (stristr($wl, '/')) {
                        if (self::ip_in_range($ip, $wl)) $result = 0;
                    } elseif (stristr($ip, $wl)) $result = 0;
                }

                $ip_info = self::ip_info($ip);

                $as = $ip_info ? ($ip_info["as"] ?? '') : ($data["as"] ?? '');
                $asS = $as ? explode(" ", $as) : [];
                $as = $asS[0] ?? false;
                if ($result && $as && stristr($whitelist, $as)) $result = 0;
            }


            if ($data && !file_exists($log_file)) FileManager::file_write($log_file, $response);
            if ($data) self::$ip_proxy_log[$ip] = $result;

            if ($error) {
                if (stristr($error, 'timed out')) return false;
                if (stristr($error, 'timeout')) return false;
                Modules::save_log("IP", "WiseProxy", "check", $ip, $error);
            }

            return (bool)$result;
        }


        static function ip_info($ip = '')
        {
            if (!$ip) $ip = self::GetIP();
            $ipv4 = filter_var($ip, FILTER_VALIDATE_IP);
            $ipv6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

            if (!($ipv4 || $ipv6)) return false;

            if (isset(self::$ip_info_log[$ip])) return self::$ip_info_log[$ip];
            if ($log_file = FileManager::file_read(ROOT_DIR . "temp" . DS . 'ip-log-' . $ip . '.json'))
                return Utility::jdecode($log_file, true);

            $overwrite_limit = (int)Config::get("options/ip-overload-limit");
            $over_limit = $overwrite_limit > 0 ? $overwrite_limit : 100;
            $hit_file = CORE_DIR . "storage" . DS . "ip-overload-limit.php";
            if (!file_exists($hit_file)) touch($hit_file);
            $gcon = FileManager::file_read($hit_file);
            if ($gcon)
                $con = include $hit_file;
            else
                $con = false;

            if ($con && is_array($con) && isset($con["date"]) && isset($con["total"])) {
                if (DateManager::Now("Y-m-d") == $con["date"])
                    $con["total"]++;
                else {
                    $con["date"] = DateManager::Now("Y-m-d");
                    $con["total"] = 1;
                }
            } else
                $con = [
                    'date'  => DateManager::Now("Y-m-d"),
                    'total' => 1,
                ];
            if ($con["total"] >= $over_limit) return false;

            $ip_module = Config::get("modules/ip");

            Modules::Load("IP", $ip_module);
            $obj = new $ip_module;

            $result = $obj->info($ip);

            FileManager::file_write($hit_file, Utility::array_export($con, ['pwith' => true]));

            if (!$result) {
                if ($obj->error) {
                    if (stristr($obj->error, 'timed out')) return false;
                    if (stristr($obj->error, 'timeout')) return false;
                    Modules::save_log("IP", "WiseIP", "check", $ip, $obj->error);
                }
                return false;
            }

            FileManager::file_write(ROOT_DIR . "temp" . DS . "ip-log-" . $ip . ".json", Utility::jencode($result));
            self::$ip_info_log[$ip] = $result;
            return $result;
        }


        static function LoginData($type = "member", $noremember = false, $recheck = false)
        {
            $key = $type . "_login";
            if (isset(self::$login_data[$key]) && !$recheck) $data = self::$login_data[$key];
            else {
                if (!$noremember) self::Remember_Control($type);
                $data = Session::get($key, true);
                if (false !== $data && !empty($data)) {
                    $data = Utility::jdecode($data, true);
                    if ($data && is_array($data)) {
                        Helper::Load("User");
                        $data["type"] = $type;
                        if ($data && self::Login_Control($data)) self::$login_data[$key] = $data;
                        else {
                            Session::delete($key);
                            $data = false;
                            if (!$noremember && self::Remember_Control($type))
                                return self::LoginData($type, $noremember, true);
                        }
                    } else $data = false;
                }
            }
            return $data;
        }


        static function Login_Control($ses_data = [])
        {
            $ip_address = self::GetIP();
            if (!isset($ses_data["ip"]) || !isset($ses_data["id"]) || !$ses_data["password"]) return false;
            //if(!DEMO_MODE && ($ip_address != $ses_data["ip"])) return false;
            $mainControl = User::_crypt($ses_data["type"] ?? "member", $ses_data["password"], "decrypt", "-O@cb6Dxvarhc+Ghp}1eW~Y0yYfrfI#RBWdl");
            return $mainControl && User::Session_Login_Check($ses_data["id"], $ip_address, $ses_data["password"]);
        }


        static function Remember_Control($type)
        {
            if (Session::get($type . "_login") == false) {
                $key = $type . "_login_remember";
                if (Cookie::get($key)) {
                    $cdata = Cookie::get($key, true);
                    $cdata = Utility::jdecode($cdata, true);
                    if ($cdata !== false) {
                        Helper::Load(["User", "Money"]);
                        $token = $cdata["token"];
                        $check = User::remember_check($token);
                        $ip = self::GetIP();


                        if ($check) {
                            $uip = User::getData($check->id, "ip")->ip;

                            $ip_check = Models::$init->db->select("id")->from("users_last_logins AS ull");
                            $ip_check->where("ull.owner_id", "=", $check->id, "&&");
                            $ip_check->where("ull.ip", "=", $ip, "&&");
                            $ip_check->where("ull.ctime BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE() + INTERVAL 1 DAY");
                            $ip_check->limit(1);
                            $ip_check = $ip_check->build() ? $ip_check->getObject()->id : 0;

                            if (!Config::get("options/disable-session-ip-check"))
                                if (!($uip == $ip || $ip_check)) $check = false;

                        }

                        if ($check) {
                            $new_token = self::Create_Login_Token($check->id, $check->email, $check->password);
                            self::Login($type, $check->id, $check->password, $check->lang, '-O@cb6Dxvarhc+Ghp}1eW~Y0yYfrfI#RBWdl');
                            self::Login_Remember($type, $new_token);
                            User::Login_Refresh($check->id, $new_token);
                            User::addLastLogin($check->id, $new_token);
                            User::addAction($check->id, "in", "logged-on");
                            Money::setCurrency($check->currency);
                            return true;
                        } else
                            Cookie::delete($key);
                    }
                }
            }
            return false;
        }


        static function LoginCheck($type = "member", $noremember = false)
        {
            $data = self::LoginData($type, $noremember);
            if ($data !== false) {
                if ($data && is_array($data)) {
                    return true;
                } else
                    return false;
            } else
                return false;
        }


        static function Logout($type = 'member')
        {
            if ($type != '') {
                Session::delete($type . "_login");
                Cookie::delete($type . "_login_remember");
            }
        }


        static function Login($type = '', $id = 0, $password = '', $lang = '', $token = '')
        {
            if ($token != '-O@cb6Dxvarhc+Ghp}1eW~Y0yYfrfI#RBWdl') return false;
            if ($type != '' && $id != '' && $id != 0) {
                $data = [
                    'id'         => $id,
                    'ip'         => self::GetIP(),
                    'lang'       => $lang,
                    'date'       => DateManager::Now(),
                    'user_agent' => UserManager::GetUserAgent(),
                    'password'   => $password,

                ];
                return Session::set($type . "_login", Utility::jencode($data), true);
            }
        }


        static function Login_Remember($type = 'member', $token = '')
        {
            Cookie::set($type . "_login_remember", Utility::jencode([
                'token'      => $token,
                'user_agent' => self::GetUserAgent(),
                'ip'         => self::GetIP(),
            ]), time() + 60 * 60 * 24 * 30, true);
        }


        static function Create_Login_Token($id, $email, $password)
        {
            return md5(Crypt::encode("+#+" . $id . "+@+" . $email . "+&+" . $password . "+=" . DateManager::Now() . "=.+", Config::get("crypt/user")));
        }


        static function GetIP()
        {
            if (!Validation::isEmpty(self::$ip_address))
                return self::$ip_address;

            if (isset($_SERVER["HTTP_CF_CONNECTING_IP"]) && $_SERVER["HTTP_CF_CONNECTING_IP"])
                $ip = $_SERVER["HTTP_CF_CONNECTING_IP"];
            elseif (isset($_SERVER["HTTP_CLIENT_IP"]) && $_SERVER["HTTP_CLIENT_IP"] && (array_count_values(explode(".", $_SERVER["HTTP_CLIENT_IP"]))[0] ?? 0) < 2) {
                $ip = $_SERVER["HTTP_CLIENT_IP"];
            } elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) && $_SERVER["HTTP_X_FORWARDED_FOR"]) {
                $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
                if (strstr($ip, ',')) {
                    $tmp = explode(',', $ip);
                    $ip = trim($tmp[0]);
                } elseif (strstr($ip, ':')) {
                    $tmp = explode(':', $ip);
                    $ip_ = trim(end($tmp));
                    if (strlen($ip_) >= 5) $ip = $ip_;
                }
                if ((array_count_values(explode(".", $ip))[0] ?? 0) > 1)
                    $ip = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : false;
            } else {
                $ip = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : false;
                if ($ip == "::1") $ip = isset($_SERVER["SERVER_ADDR"]) ? $_SERVER["SERVER_ADDR"] : false;
            }

            if ($ip && !filter_var($ip, FILTER_VALIDATE_IP) && isset($_SERVER["REMOTE_ADDR"]) && $_SERVER["REMOTE_ADDR"]) $ip = $_SERVER["REMOTE_ADDR"];

            if (!$ip) $ip = "UNKNOWN";
            $ip = strip_tags($ip);
            $ip = Filter::letters_numbers($ip, ':.');
            $ip = substr($ip, 0, 50);

            self::$ip_address = $ip;
            return $ip;
        }

        static function GetPort()
        {
            return $_SERVER["REMOTE_PORT"] ?? 37784;
        }


        static function GetUserAgent()
        {
            return isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : '';
        }


        static function formatTimeZone($date = '', $zone = '', $format = 'Y-m-d H:i:s')
        {
            $zone = str_replace("Kiev", "Kyiv", $zone);
            $datetime = new DateTime($date);
            if ($zone) $datetime->setTimezone(new DateTimeZone($zone));
            return $datetime->format($format);
        }

        static function ip_in_range($ip, $range)
        {
            if (strpos($range, '/') === false) {
                $range .= '/32';
            }
            list($range, $netmask) = explode('/', $range, 2);
            $range_decimal = ip2long($range);
            $ip_decimal = ip2long($ip);
            $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
            $netmask_decimal = ~$wildcard_decimal;

            return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
        }
    }