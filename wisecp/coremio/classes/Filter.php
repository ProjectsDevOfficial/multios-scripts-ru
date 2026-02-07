<?php
    defined('CORE_FOLDER') or exit('You can not get in here!');

    class Filter
    {
        static $transliterate_cc = null;
        private static $dialing_codes = false;

        static function html_clear($arg = null, $allow = '')
        {
            if (is_array($arg)) return false;
            $arg = html_entity_decode($arg);
            return $allow ? strip_tags($arg, $allow) : strip_tags($arg);
        }

        static function nl2br_reverse($string)
        {
            return preg_replace('#<br\s*/?>#i', "\n", $string);
        }

        public static function link_convert($value, $noreferer = false)
        {
            $placeholders = [];
            $value = preg_replace_callback(
                '/(<script\b[^>]*>.*?<\/script>|<link\b[^>]*>)/is',
                function ($matches) use (&$placeholders) {
                    $placeholder = '##PLACEHOLDER_' . count($placeholders) . '##';
                    $placeholders[$placeholder] = $matches[0];
                    return $placeholder;
                },
                $value
            );

            $value = preg_replace_callback(
                '/(\s|&quot;|\'|&gt;|&lt;|&#039;|\(|\)|\[|\]|>|<|\(|\)|\{|\})*(http[s]?:\/\/[^\s&\'""><\]\[)(]+)(?=\s|&quot;|\'|&gt;|&lt;|&#039;|\(|\)|\[|\]|>|<|\(|\)|\{|\}|$)/i',
                function ($matches) use ($noreferer) {
                    $before = $matches[1] ?? '';
                    $url = $matches[2];
                    $after = '';

                    $rel = $noreferer ? ' rel="noreferrer"' : '';
                    $referrerPolicy = $noreferer ? ' referrerpolicy="no-referrer"' : '';

                    return "{$before}<a href=\"$url\" target=\"_blank\"$rel$referrerPolicy>$url</a>{$after}";
                },
                $value
            );
            foreach ($placeholders as $placeholder => $original) {
                $value = str_replace($placeholder, $original, $value);
            }
            return $value;
        }

        static function init($arg = null, $mod = false, $special = false)
        {
            if (empty($arg)) return false;

            if (Utility::short_text($arg, 0, 4) == "GET/")
                $arg = self::GET(Utility::short_text($arg, 4));
            elseif (Utility::short_text($arg, 0, 5) == "POST/")
                $arg = self::POST(Utility::short_text($arg, 5));
            elseif (Utility::short_text($arg, 0, 6) == "FILES/")
                $arg = self::FILES(Utility::short_text($arg, 6));
            elseif (Utility::short_text($arg, 0, 8) == "REQUEST/")
                $arg = self::REQUEST(Utility::short_text($arg, 8));
            elseif (Utility::short_text($arg, 0, 7) == "SERVER/")
                $arg = self::SERVER(Utility::short_text($arg, 7));

            if ($mod == "letters_numbers")
                return self::letters_numbers($arg, $special);
            elseif ($mod == "letters")
                return self::letters($arg, $special);
            elseif ($mod == "ip")
                return self::ip($arg, $special);
            elseif ($mod == "numbers")
                return self::numbers($arg, $special);
            elseif ($mod == "rnumbers")
                return self::rnumbers($arg, $special);
            elseif ($mod == "amount")
                return self::amount($arg, $special);
            elseif ($mod == "folder")
                return self::folder($arg, $special);
            elseif ($mod == "file")
                return self::file($arg, $special);
            elseif ($mod == "email")
                return self::email($arg, $special);
            elseif ($mod == "password")
                return self::password($arg, $special);
            elseif ($mod == "noun")
                return self::noun($arg, $special);
            elseif ($mod == "route")
                return self::route($arg, $special);
            elseif ($mod == "identity")
                return self::identity($arg, $special);
            elseif ($mod == "domain")
                return self::domain($arg);
            elseif ($mod == "text")
                return self::text($arg, $special);
            elseif ($mod == "dtext")
                return self::dtext($arg, $special);
            elseif ($mod == "hclear")
                return self::html_clear($arg);
            elseif (!$mod && $special != '')
                return preg_replace('/[^' . $special . ']/', '', self::html_clear($arg));
            else
                return $arg;
        }

        static function letters_numbers($arg = '', $special = '')
        {
            if (is_array($arg)) return false;
            if (is_bool($arg)) return $arg;
            $data = preg_replace('/[^0-9a-zA-Z' . $special . ']/', '', self::html_clear($arg));
            return (string)$data;
        }

        static function letters($arg = '', $special = '')
        {
            if (is_array($arg)) return false;
            $characters = Bootstrap::$lang->get("package/scharacters");
            return preg_replace('/[^a-zA-Z' . $characters . $special . ']/', '', self::html_clear($arg));
        }

        static function numbers($arg = '', $special = '')
        {
            if (is_bool($arg)) return $arg;
            if (is_array($arg)) return false;
            return preg_replace('/[^0-9\-' . $special . ']/', '', self::html_clear($arg));
        }

        static function amount($arg = '', $special = '')
        {
            if (is_bool($arg)) return $arg;
            if (is_array($arg)) return false;
            return preg_replace('/[^0-9\-.,' . $special . ']/', '', self::html_clear($arg));
        }

        static function ip($arg = '', $special = '')
        {
            if (is_bool($arg)) return $arg;
            if (is_array($arg)) return false;
            return preg_replace('/[^a-zA-Z0-9\-.:' . $special . ']/', '', self::html_clear($arg));
        }

        static function rnumbers($arg = '', $special = '')
        {
            if (is_array($arg)) return false;
            return intval(self::numbers($arg, $special));
        }

        static function folder($arg = '', $special = '')
        {
            return preg_replace('/[^0-9a-zA-Z\/\-_.' . $special . ']/', '', self::html_clear($arg));
        }

        static function file($arg = '', $special = '')
        {
            $characters = Bootstrap::$lang->get("package/scharacters");
            return preg_replace('/[^0-9a-zA-Z' . $characters . '\-_.' . $special . ']/', '', self::html_clear($arg));
        }

        static function email($arg = '', $special = '')
        {
            return preg_replace('/[^a-z-A-Z-0-9@.+_]/', '', self::html_clear($arg));
        }

        static function password($arg = '', $special = '')
        {
            return $arg;
        }

        static function noun($arg = '', $special = '')
        {
            return preg_replace('/[^0-9a-zA-Z' . $special . ',. ]/', '', self::html_clear($arg));
        }

        static function route($arg = '', $special = '')
        {
            return preg_replace('/[^0-9a-zA-Z\-_.' . $special . ']/', '', self::html_clear($arg));
        }

        static function dtext($arg = '', $special = '')
        {
            return self::html_clear($arg);
        }

        static function text($arg = '', $special = '')
        {
            return self::quotes(self::html_clear($arg));
        }

        static function quotes($arg, $special = '')
        {
            return str_replace(['"', '\''], ['&#34;', '&#39;'], $arg);
        }

        static function ticket_message($str = '')
        {
            $tags = "<br><p><h1><h2><h3><h4><h5><h6><strong><i></i><span><img><sub><ul><ol><li><a><code><table><caption><head><tbody><tr><td><th>";
            return strip_tags($str, $tags);
        }

        static function identity($arg, $special = '')
        {
            return Filter::numbers($arg);
        }

        static function domain($domain = '')
        {
            $domain = self::html_clear($domain);
            $domain = preg_replace("/[^[:alnum:].-]/u", '', $domain);
            $domain = Utility::strtolower($domain);
            return $domain;
        }

        static function phone_smash($phone = '')
        {
            $phone = self::numbers($phone);
            if (!(strlen($phone) >= 5)) return ['cc' => null, 'number' => null];
            if (substr($phone, 0, 1) == "+") $phone = substr($phone, 1, 20);

            try {
                $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
                $swissNumberProto = $phoneUtil->parse("+" . $phone);
                $result = [
                    'cc'     => $swissNumberProto->getCountryCode(),
                    'number' => $swissNumberProto->getNationalNumber(),
                ];

                if (!self::$dialing_codes) self::$dialing_codes = Config::get("sms/dialing-codes");
                if (isset(self::$dialing_codes[$result["cc"]])) $result["code"] = self::$dialing_codes[$result["cc"]];
            } catch (Exception $e) {
                $result = [
                    'cc'     => null,
                    'number' => null,
                ];
            }
            return $result;
        }

        static function name_smash($full_name = "")
        {
            if (!empty($full_name)) {
                $exp = explode(" ", $full_name);
                $return = [];
                if (count($exp) > 1) {
                    $last = end($exp);
                    array_pop($exp);
                    $return["first"] = implode(" ", $exp);
                    $return["last"] = $last;
                } else {
                    $return["first"] = current($exp);
                    $return["last"] = '';
                }
                return $return;
            } else
                return false;
        }

        static function permalink_check($str = '')
        {
            $route = preg_replace('/[^0-9a-zA-Z]/', '', self::html_clear($str));
            return $route ? true : false;
        }

        static function permalink($str, $options = array())
        {
            $str = mb_convert_encoding((string)$str, 'UTF-8', mb_list_encodings());
            $defaults = array(
                'delimiter'     => '-',
                'limit'         => null,
                'lowercase'     => true,
                'replacements'  => array(),
                'transliterate' => true,
            );
            $options = array_merge($defaults, $options);
            $char_map = array(
                // Latin
                'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
                'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
                'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ő' => 'O',
                'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ű' => 'U', 'Ý' => 'Y', 'Þ' => 'TH',
                'ß' => 'ss',
                'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',
                'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
                'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ő' => 'o',
                'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'þ' => 'th',
                'ÿ' => 'y',
                // Latin symbols
                '©' => '(c)',
                // Greek
                'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Θ' => '8',
                'Ι' => 'I', 'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => '3', 'Ο' => 'O', 'Π' => 'P',
                'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'F', 'Χ' => 'X', 'Ψ' => 'PS', 'Ω' => 'W',
                'Ά' => 'A', 'Έ' => 'E', 'Ί' => 'I', 'Ό' => 'O', 'Ύ' => 'Y', 'Ή' => 'H', 'Ώ' => 'W', 'Ϊ' => 'I',
                'Ϋ' => 'Y',
                'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'h', 'θ' => '8',
                'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => '3', 'ο' => 'o', 'π' => 'p',
                'ρ' => 'r', 'σ' => 's', 'τ' => 't', 'υ' => 'y', 'φ' => 'f', 'χ' => 'x', 'ψ' => 'ps', 'ω' => 'w',
                'ά' => 'a', 'έ' => 'e', 'ί' => 'i', 'ό' => 'o', 'ύ' => 'y', 'ή' => 'h', 'ώ' => 'w', 'ς' => 's',
                'ϊ' => 'i', 'ΰ' => 'y', 'ϋ' => 'y', 'ΐ' => 'i',
                // Turkish
                'Ş' => 'S', 'İ' => 'I', 'Ç' => 'C', 'Ü' => 'U', 'Ö' => 'O', 'Ğ' => 'G',
                'ş' => 's', 'ı' => 'i', 'ç' => 'c', 'ü' => 'u', 'ö' => 'o', 'ğ' => 'g',
                // Russian
                'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
                'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
                'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
                'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sh', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu',
                'Я' => 'Ya',
                'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
                'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
                'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
                'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
                'я' => 'ya',
                // Ukrainian
                'Є' => 'Ye', 'І' => 'I', 'Ї' => 'Yi', 'Ґ' => 'G',
                'є' => 'ye', 'і' => 'i', 'ї' => 'yi', 'ґ' => 'g',
                // Czech
                'Č' => 'C', 'Ď' => 'D', 'Ě' => 'E', 'Ň' => 'N', 'Ř' => 'R', 'Š' => 'S', 'Ť' => 'T', 'Ů' => 'U',
                'Ž' => 'Z',
                'č' => 'c', 'ď' => 'd', 'ě' => 'e', 'ň' => 'n', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ů' => 'u',
                'ž' => 'z',
                // Polish
                'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'e', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'o', 'Ś' => 'S', 'Ź' => 'Z',
                'Ż' => 'Z',
                'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z',
                'ż' => 'z',
                // Latvian
                'Ā' => 'A', 'Č' => 'C', 'Ē' => 'E', 'Ģ' => 'G', 'Ī' => 'i', 'Ķ' => 'k', 'Ļ' => 'L', 'Ņ' => 'N',
                'Š' => 'S', 'Ū' => 'u', 'Ž' => 'Z',
                'ā' => 'a', 'č' => 'c', 'ē' => 'e', 'ģ' => 'g', 'ī' => 'i', 'ķ' => 'k', 'ļ' => 'l', 'ņ' => 'n',
                'š' => 's', 'ū' => 'u', 'ž' => 'z',
            );
            $str = preg_replace(array_keys($options['replacements']), $options['replacements'], $str);
            if ($options['transliterate']) {
                $str = str_replace(array_keys($char_map), $char_map, $str);
            }
            $str = preg_replace('/[^\p{L}\p{Nd}]+/u', $options['delimiter'], $str);
            $str = preg_replace('/(' . preg_quote($options['delimiter'], '/') . '){2,}/', '$1', $str);
            $str = mb_substr($str, 0, ($options['limit'] ? $options['limit'] : mb_strlen($str, 'UTF-8')), 'UTF-8');
            $str = trim($str, $options['delimiter']);
            return $options['lowercase'] ? mb_strtolower($str, 'UTF-8') : $str;
        }

        static function transliterate($str)
        {
            $str = transliterator_transliterate('Any-Latin;Latin-ASCII;', $str);
            return $str;
        }

        static function transliterate2($str = '')
        {
            if (Config::get("options/disable-domain-whois-transliterate")) return $str;
            if (!self::$transliterate_cc) self::$transliterate_cc = Config::get("general/country");
            if (self::$transliterate_cc == "DE" || self::$transliterate_cc == "AT" || self::$transliterate_cc == "CH")
                return self::transliterate(self::replace_germany_umlauts($str));

            return self::transliterate($str);
        }

        static function replace_germany_umlauts($s)
        {
// maps German (umlauts) and other European characters onto two characters before just removing diacritics
            $s = preg_replace('@\x{00c4}@u', "Ae", $s); // umlaut Ä => Ae
            $s = preg_replace('@\x{00d6}@u', "Oe", $s); // umlaut Ö => Oe
            $s = preg_replace('@\x{00dc}@u', "Ue", $s); // umlaut Ü => Ue
            $s = preg_replace('@\x{00e4}@u', "ae", $s); // umlaut ä => ae
            $s = preg_replace('@\x{00f6}@u', "oe", $s); // umlaut ö => oe
            $s = preg_replace('@\x{00fc}@u', "ue", $s); // umlaut ü => ue
            $s = preg_replace('@\x{00f1}@u', "ny", $s); // ñ => ny
            $s = preg_replace('@\x{00ff}@u', "yu", $s); // ÿ => yu

            return $s;
        }

        static function isGET()
        {
            return ($_SERVER["REQUEST_METHOD"] == "GET") ? true : false;
        }

        static function isPOST()
        {
            return $_SERVER["REQUEST_METHOD"] == "POST" ? true : false;
        }

        static function GET($arg = '')
        {
            $method = isset($_GET) ? $_GET : false;

            if (empty($arg))
                return $method;
            elseif ($method != false) {
                $elem = $method;
                $exp = explode("/", $arg);
                if ($exp) {
                    foreach ($exp as $ex) {
                        if (isset($elem[$ex]))
                            $elem = $elem[$ex];
                        else
                            $elem = false;
                    }
                } else $elem = $elem[$arg];
                return $elem;
            }
            return false;
        }

        static function POST($arg = '')
        {
            $method = isset($_POST) ? $_POST : false;

            if (empty($arg))
                return $method;
            elseif ($method != false) {
                $elem = $method;
                $exp = @explode("/", $arg);
                if ($exp) {
                    foreach ($exp as $ex) {
                        if (isset($elem[$ex]))
                            $elem = $elem[$ex];
                        else
                            $elem = false;
                    }
                } else $elem = $elem[$arg];
                return $elem;
            }
            return false;
        }

        static function REQUEST($arg = '')
        {
            $method = isset($_REQUEST) ? $_REQUEST : false;

            if (empty($arg))
                return $method;
            elseif ($method != false) {
                $elem = $method;
                $exp = @explode("/", $arg);
                if (sizeof($exp) > 0) {
                    foreach ($exp as $ex) {
                        if (isset($elem[$ex]))
                            $elem = $elem[$ex];
                        else
                            $elem = false;
                    }
                } else {
                    $elem = $elem[$arg];
                }
                return $elem;
            } else
                return false;
        }

        static function SERVER($arg = '')
        {
            $method = isset($_SERVER) ? $_SERVER : false;

            if (empty($arg))
                return $method;
            elseif ($method != false) {
                $elem = $method;
                $exp = @explode("/", $arg);
                if (sizeof($exp) > 0) {
                    foreach ($exp as $ex) {
                        if (isset($elem[$ex]))
                            $elem = $elem[$ex];
                        else
                            $elem = false;
                    }
                } else {
                    $elem = $elem[$arg];
                }
                return $elem;
            } else
                return false;
        }

        static function FILES($arg = '')
        {
            $method = isset($_FILES) ? $_FILES : false;

            if (empty($arg))
                return $method;
            elseif ($method != false) {
                $elem = $method;
                $exp = @explode("/", $arg);
                if (sizeof($exp) > 0) {
                    foreach ($exp as $ex) {
                        if (isset($elem[$ex]))
                            $elem = $elem[$ex];
                        else
                            $elem = false;
                    }
                } else {
                    $elem = $elem[$arg];
                }
                return $elem;
            } else
                return false;
        }

        static function censored($data = '', $type = '')
        {
            $data = trim($data);
            if (!$data) return $data;

            $str_arr = Utility::str_split($data);
            $str = null;
            $size = sizeof($str_arr) - 1;

            if ($type == "phone") $lastCharC = $size - 4;
            else {
                $average = self::fetch_average($size);
                $average_x = $average / 2;
                $firstCharC = $average_x;
                $lastCharC = $size - $average_x;
            }

            if ($type == "email") {
                $split = explode("@", $data);
                $prefix = $split[0];
                $suffix = $split[1];
                $dots = explode(".", $suffix);
                $str_arr = str_split($prefix);
                $size = sizeof($str_arr) - 1;
                $charC = $size < 5 ? $size - 3 : $size - 6;
                for ($i = 0; $i <= $size; $i++) {
                    $char = isset($str_arr[$i]) ? $str_arr[$i] : '';;
                    if ($i > $charC) $str .= '*';
                    else $str .= $char;
                }
                $str .= "@";

                $str_arr = str_split($dots[0]);
                $size = sizeof($str_arr);
                $str .= str_repeat("*", $size);
                unset($dots[0]);
                $str .= "." . implode(".", $dots);
            } else {
                $size_x = $size;

                if (isset($firstCharC)) {
                    for ($i = 0; $i <= $size; $i++) {
                        $char = isset($str_arr[$i]) ? $str_arr[$i] : '';;
                        if ($i < $firstCharC) $str .= '*';
                        else $str .= $char;
                    }
                    if (isset($lastCharC)) {
                        $str_arr = Utility::str_split($str);
                        $size = $size_x;
                        $str = null;
                    }
                }
                if (isset($lastCharC)) {
                    for ($i = 0; $i <= $size; $i++) {
                        $char = isset($str_arr[$i]) ? $str_arr[$i] : '';;
                        if ($i > $lastCharC) $str .= '*';
                        else $str .= $char;
                    }
                }
            }

            return $str == '' ? $data : $str;
        }

        private static function fetch_average($size = 1, $rate = 50)
        {
            return (float)$size * $rate / 100;

        }

    }