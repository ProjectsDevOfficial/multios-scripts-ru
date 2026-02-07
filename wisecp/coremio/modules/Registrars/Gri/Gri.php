<?php

use Gri\B2B\griB2B;

class Gri
{
    public ?griB2B $api_client = null;
    public $config = [];
    public $lang = [];
    public $error = NULL;
    public $order = [];
    public $docs = [];

    function __construct($args = [])
    {
        $this->config = Modules::Config("Registrars", __CLASS__);
        $this->lang = Modules::Lang("Registrars", __CLASS__);

        if (!class_exists("\Gri\B2B\griB2B")) {
            // Calling API files
            include __DIR__ . DS . "Gri_B2B.php";
        }
    }

    private static function domainAvailableResultConvert($in)
    {
        if($in === 'not_available') return 'unavailable';
        return $in;
    }

    public function set_order($order = [])
    {
        $this->order = $order;
        return $this;
    }

    public function define_docs($docs = [])
    {
        $this->docs = $docs;
    }

    private function getClient($config = null): ?griB2B
    {
        if (!$config) $config = $this->config;
        if ($this->api_client) return $this->api_client;
        $username = $config['settings']["username"] ?? "";
        $password = $config['settings']["password"] ?? "";
        $api_client = $config['settings']["api_client"] ?? "";
        $api_secret = $config['settings']["api_secret"] ?? "";
        if (
            !$username ||
            !$password ||
            !$api_client ||
            !$api_secret
        ) return null;

        $password = Crypt::decode($password, Config::get("crypt/system"));
        $api_secret = Crypt::decode($api_secret, Config::get("crypt/system"));
        $sandbox = (bool)$config['settings']["test-mode"];

        $this->api_client = new griB2B(
            $username, $password, $api_client, $api_secret, $sandbox
        );
        return $this->api_client;
    }

    public function testConnection($config = [])
    {
        if (isset($_POST['controller']) && $_POST['controller'] === 'settings')
            return true;
        $client = $this->getClient($config);
        if (!$client) return false;
        $token = $client->get_token(false, true);
        if (is_object($token) && isset($token->error)) {
            if (isset($token->error_description) && $token->error_description)
                $this->error = $token->error_description;
            return false;
        }
        return true;
    }

    public function questioning($sld = NULL, $tlds = [])
    {
        if ($sld == '' || empty($tlds)) {
            $this->error = $this->lang["error2"];
            return false;
        }
        $sld = idn_to_ascii($sld, 0, INTL_IDNA_VARIANT_UTS46);
        if (!is_array($tlds)) $tlds = [$tlds];
        $allDomains = [];
        $result = [];

        foreach ($tlds as $t) {
            $fullDomain = sprintf("%s.%s", $sld, $t);
            $allDomains[$fullDomain] = [
                'tld' => $t,
                'sld' => $sld,
                'domain' => $fullDomain
            ];
        }
        $checkApi = $this->getClient()->request('/api/v2/domains/check', ['domains' => array_map(function ($item) {
            return $item['domain'];
        }, $allDomains)]);

        if (!isset($checkApi->status) || !$checkApi->status) {
            $this->error = $this->lang["domain-check-error"];
            return false;
        }

        foreach ($checkApi->data as $item) {
            $fullDomain = $item->name;
            $tld = $allDomains[$fullDomain]['tld'];
            $item->detail = self::domainAvailableResultConvert($item->detail);
            $result[$tld] = ['status' => $item->detail];
        }

        return $result;
    }

    public static function toGriWhois($whois_data)
    {
        return [
            "firstname" => $whois_data["FirstName"] ?? '',
            "lastname" => $whois_data["LastName"] ?? '',
            "address1" => $whois_data["AddressLine1"] ?? '',
            "address2" => $whois_data["AddressLine2"] ?? '',
            "city" => $whois_data["City"] ?? '',
            "state" => $whois_data["State"] ?? '',
            "country" => $whois_data["Country"] ?? '',
            "email" => $whois_data["EMail"] ?? '',
            "fax" => $whois_data["Fax"] ?? '',
            "organization" => $whois_data["Company"] ?? '',
            "phone" => $whois_data["Phone"] ?? '',
//            "phone_country_code" => $whois_data["PhoneCountryCode"] ?? '',
            "tax_number" => "",
            "tax_office" => "",
            "www" => "",
            "zip_code" => $whois_data['ZipCode'] ?? ''
        ];
    }

    public function register($domain = '', $sld = '', $tld = '', $year = 1, $dns = [], $whois = [], $wprivacy = false, $eppCode = '')
    {
        $domain = idn_to_ascii($domain);
        $api_params = [
            'domain' => $domain,
        ];

        if ($eppCode) {
            // transfer
            $api_params['auth_code'] = $eppCode;
            $url = '/api/v2/domain-transfer/demand';
        } else {
            // register
            $url = '/api/v2/domains/register';
            $dnsArray = [];
            foreach ($dns as $item) {
                if ($item)
                    $dnsArray[] = [
                        'ip' => '',
                        'name' => $item
                    ];
            }
            $api_params['contact'] = self::toGriWhois($whois['registrant']);
            $api_params['duration'] = $year;
            $api_params["dns"] = $dnsArray;
        }


        try {
            $response = $this->getClient()->request($url, $api_params);
        } catch (\Exception $exception) {
            $this->error = $exception->getMessage();
            return false;
        }

        if ($response && $response->status) {

            $returnData = [
                'status' => "SUCCESS",
                'config' => [
                    'entityID' => $response->id
                ],
            ];

            if ($wprivacy)
                $returnData["whois_privacy"] = ['status' => true, 'message' => NULL];

            return $returnData;
        } else {
            $this->error = $response->message;
            return false;
        }
    }

    public function transfer($domain = '', $sld = '', $tld = '', $year = 1, $dns = [], $whois = [], $wprivacy = false, $eppCode = '')
    {
        return $this->register($domain, $sld, $tld, $year, $dns, $whois, $wprivacy, $eppCode);
    }

    public function renewal($params = [], $domain = '', $sld = '', $tld = '', $year = 1, $oduedate = '', $nduedate = '')
    {
        try {
            $renew = $this->getClient()->request('/api/v2/domain/' . $domain . '/renew?duration=' . $year, '', 'GET');
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
        return (isset($renew->status) && $renew->status);
    }

    public function ModifyDns($params = [], $dns = [])
    {
        $domain = idn_to_ascii($params["domain"], 0, INTL_IDNA_VARIANT_UTS46);
        $request_params = [];
        foreach ($dns as $item) {
            $request_params['dns'][] = ['ip' => '', 'name' => $item];
        }
        try {
            $change_dns = $this->getClient()->request('/api/v2/domain/' . $domain . '/change-dns', $request_params);
        } catch (\Exception $exception) {
            $this->error = $exception->getMessage();
            return false;
        }
        return (isset($change_dns->status) && $change_dns->status);
    }

    public function CNSList($params = [])
    {
        $data = [];
        try {
            $getDomain = $this->getClient()->request('/api/v2/domain/' . $params["domain"] . '/get-sub-dns', '', 'GET');
            $dnsList = $getDomain->dns_result;
            foreach ($dnsList as $item) {
                $data[] = ['ns' => $item->name, 'ip' => $item->ip];
            }
        } catch (\Exception $exception) {
            $this->error = $exception->getMessage();
            return false;
        }

        return $data;
    }

    public static function str_lreplace($search, $replace, $subject)
    {
        $pos = strrpos($subject, $search);

        if ($pos !== false) {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
    }

    public function saveCNS($array, $params)
    {
        try {
            foreach ($array as $item_count => $item) {
                $array[$item_count]['name'] = self::str_lreplace('.' . $params['domain'], '', $item['name']);
            }
            $change = $this->getClient()->request('/api/v2/domain/' . $params["domain"] . '/change-sub-dns', [
                'dns' => $array
            ]);
        } catch (\Exception $exception) {
            $this->error = $exception->getMessage();
            return false;
        }
        return $change;
    }

    public function addCNS($params = [], $ns = '', $ip = '')
    {
        $exists_dns_array = [];
        try {
            $getDomain = $this->getClient()->request('/api/v2/domain/' . $params["domain"] . '/get-sub-dns', '', 'GET');
            $dnsList = $getDomain->dns_result;
            foreach ($dnsList as $item) {
                if ($item->name === $ns) {
                    $this->error = $this->lang['custom-ns-exists-error'];
                    return false;
                }
                $exists_dns_array[] = [
                    'ip' => $item->ip ?? '',
                    'name' => $item->name ?? ''
                ];
            }
        } catch (\Exception $exception) {
            $this->error = $exception->getMessage();
            return false;
        }

        $exists_dns_array[] = [
            'ip' => $ip,
            'name' => $ns
        ];

        $change = $this->saveCNS($exists_dns_array, $params);
        return isset($change->status) && $change->status;
    }

    public function ModifyCNS($params = [], $old = [], $new_ns = '', $new_ip = '')
    {
        $oldNs = $old['ns'] ?? '';
        $oldIp = $old['ip'] ?? '';

        if (!$oldNs || !$oldIp) {
            $this->error = $this->lang['old-ns-access-error'];
            return false;
        }

        $exists_dns_array = [];
        try {
            $getDomain = $this->getClient()->request('/api/v2/domain/' . $params["domain"] . '/get-sub-dns', '', 'GET');
            $dnsList = $getDomain->dns_result;
            foreach ($dnsList as $item) {
                if ($item->name !== $oldNs) {
                    $exists_dns_array[] = [
                        'ip' => $item->ip ?? '',
                        'name' => $item->name ?? ''
                    ];
                } else {
                    $exists_dns_array[] = [
                        'ip' => $new_ip,
                        'name' => $new_ns
                    ];
                }
            }
        } catch (\Exception $exception) {
            $this->error = $exception->getMessage();
            return false;
        }

        $change = $this->saveCNS($exists_dns_array, $params);
        return isset($change->status) && $change->status;

    }

    public function DeleteCNS($params = [], $ns = '', $ip = '')
    {
        $exists_dns_array = [];
        try {
            $getDomain = $this->getClient()->request('/api/v2/domain/' . $params["domain"] . '/get-sub-dns', '', 'GET');
            $dnsList = $getDomain->dns_result;
            foreach ($dnsList as $item) {
                if ($item->name !== $ns) {
                    $exists_dns_array[] = [
                        'ip' => $item->ip ?? '',
                        'name' => $item->name ?? ''
                    ];
                }
            }
        } catch (\Exception $exception) {
            $this->error = $exception->getMessage();
            return false;
        }

        $change = $this->saveCNS($exists_dns_array, $params);
        return isset($change->status) && $change->status;

    }

    public function ModifyWhois($params = [], $whois = [])
    {
        $contact_data = [
            'owner' => self::toGriWhois($whois['registrant']),
            'admin' => self::toGriWhois($whois['administrative']),
            'billing' => self::toGriWhois($whois['billing']),
            'technical' => self::toGriWhois($whois['technical'])
        ];
        try {
            $url = sprintf('/api/v2/domain/%s/update-contact', $params['domain']);
            $this->getClient()->request($url, $contact_data, 'PATCH');

        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }

        return true;
    }

    public function getWhoisPrivacy($params = [])
    {
        try {
            $details = $this->api_client->request('/api/v2/domain/get-privacy/' . $params["domain"], '', 'GET');
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }

        return isset($details->status) && $details->status;
    }

    public function getTransferLock($params = [])
    {
        try {
            $domain_details = $this->getClient()->request('/api/v2/domain/' . $params['domain'], '', 'GET');
            if (!isset($domain_details->data) || !isset($domain_details->data->attributes)) {
                $this->error = $this->lang['domain-details-access-error'];
                return false;
            }
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
        $attributes = $domain_details->data->attributes;
        return $attributes->locked;
    }

    public function isInactive($params = [])
    {
        try {
            $domain_details = $this->getClient()->request('/api/v2/domain/' . $params['domain']);
            if (!isset($domain_details->data) || !isset($domain_details->data->attributes)) {
                $this->error = $this->lang['domain-details-access-error'];
                return false;
            }
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
        $status = $domain_details->data->attributes->status;
        return $status !== "active";
    }

    public function ModifyTransferLock($params = [], $status = '')
    {
        $toLocked = $status == 'enable';
        $currentState = $this->getTransferLock($params);
        if ($toLocked === $currentState) return true;
        try {
            $this->getClient()->request('/api/v2/domain/' . $params['domain'], [
                'locked' => $toLocked
            ], 'PATCH');

        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
        return true;
    }

    public function modifyPrivacyProtection($params = [], $status = '')
    {
        $toEnable = $status == "enable";
        $url = sprintf('/api/v2/domain/set-privacy/%s/%d', $params['domain'], $toEnable ? '1' : '0');
        try {
            $this->getClient()->request($url, '', 'GET');
        } catch (\Exception $exception) {
            $this->error = $exception->getMessage();
            return false;
        }
        return true;
    }

    public function purchasePrivacyProtection($params = [])
    {
        return $this->modifyPrivacyProtection($params, 'enable');
    }

    public function suspend($params = [])
    {
        return true;
    }

    public function unsuspend($params = [])
    {
        return true;
    }

    public function terminate($params = [])
    {
        return true;
    }

    public function getAuthCode($params = [])
    {
        $url = '/api/v2/domain-transfer/get-auth-code';
        try {
            $req = $this->getClient()->request($url . '?domain=' . $params['domain'] . '&email=false', '', 'GET');
            return $req->detail;
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public static function statusConvert($status)
    {
        $wiseStatus = 'unknown';
        switch ($status) {
            case 'active':
                $wiseStatus = 'active';
                break;
            case 'expired':
                $wiseStatus = 'expired';
                break;
            case 'incoming_transfer':
            case 'reserved':
                $wiseStatus = 'pending';
                break;
            case 'cancelled':
            case 'transferred_away':
            case 'transfer_cancelled':
                $wiseStatus = 'cancelled';
                break;
        }
        return $wiseStatus;
    }

    public function sync($params = [])
    {
        try {
            $details = $this->getClient()->request('/api/v2/domain/' . $params["domain"], '', 'GET');
            if (!isset($details->data) || !isset($details->data->attributes)) return false;
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }

        $details = $details->data->attributes;

        $start = DateManager::format("c", $details->registration_date);
        $end = DateManager::format("c", $details->expiry_date);
        $status = $details->status;

        $return_data = [
            'creationtime' => $start,
            'endtime' => $end,
            'status' => self::statusConvert($details->data->attributes->status),
        ];

        if ($status == "active") {
            $return_data["status"] = "active";
        } elseif ($status == "expired")
            $return_data["status"] = "expired";

        return $return_data;
    }

    public function transfer_sync($params = [])
    {
        return $this->sync($params);
    }

    public function get_info($params = [])
    {
        try {
            $details = $this->getClient()->request('/api/v2/domain/' . $params["domain"], '', 'GET');

            if (!isset($details->status) || $details->status !== true)
                return false;
        } catch (\Exception $exception) {
            $this->error = $exception->getMessage();
            return false;
        }

        $data = $details->data;
        $domainAttrs = $data->attributes ?? (object)[];

        $result = [];

        $cdate = DateManager::format("c", $domainAttrs->registration_date);
        $duedate = DateManager::format("c", $domainAttrs->expiry_date);
        $result["creation_time"] = $cdate;
        $result["end_time"] = $duedate;
        $result["transferlock"] = $domainAttrs == "1";

        return $result;

    }

    public function domains()
    {
        Helper::Load(["User"]);
        $pageSize = Filter::POST("pageSize");
        $page = Filter::POST("page");
        $draw = Filter::POST("draw");

        $client = $this->getClient();
        if($client)
        {
            try {
                $data = $client->request('/api/v2/domains?pageSize=' . $pageSize . '&page=' . ($page - 1), '', 'GET');
                $meta = $data->meta;
                $data = $data->data;
            } catch (\Exception $exception) {
                echo '<strong style="color: red">' . $exception->getMessage() . '</strong>';
            }
        }
        else
        {
            $data = [];
            $meta = false;
        }


        $result = [
            'draw' => ($draw + 1),
            'recordsTotal' => $meta ? $meta->total_result : 0,
            'recordsFiltered' => $meta ? $meta->total_result : 0,
        ];

        if ($data && is_array($data)) {
            foreach ($data as $res) {

                $detail = $res->attributes;

                $cdate = isset($detail->registration_date) ? DateManager::format("c", $detail->registration_date) : '';
                $edate = isset($detail->expiry_date) ? DateManager::format("c", $detail->expiry_date) : '';
                $domain = $detail->domain ?? '';

                if ($domain) {
                    $domain = idn_to_utf8($domain, 0, INTL_IDNA_VARIANT_UTS46);
                    $order_id = 0;
                    $user_data = [];
                    $is_imported = Models::$init->db->select("id,owner_id AS user_id")->from("users_products");
                    $is_imported->where("type", '=', "domain", "&&");
                    $is_imported->where("name", '=', $domain);
                    $is_imported = $is_imported->build() ? $is_imported->getAssoc() : false;
                    if ($is_imported) {
                        $order_id = $is_imported["id"];
                        $user_data = User::getData($is_imported["user_id"], "id,full_name,company_name", "array");
                    }

                    $result['data'][] = [
                        'id' => $detail->id,
                        'domain' => $domain,
                        'creation_date' => $cdate,
                        'end_date' => $edate,
                        'order_id' => $order_id,
                        'user_data' => $user_data,
                    ];
                }
            }
        }

        if (isset($result['recordsTotal']))
            $result['recordsTotal'] = intval($result['recordsTotal']);
        if (isset($result['resultsFiltered']))
            $result['recordsFiltered'] = intval($result['recordsFiltered']);
        if (!isset($result['data'])) $result['data'] = [];

        return $result;
    }

    public function cost_prices($type = 'domain')
    {
        if ($type != "domain") {
            $this->error = $this->lang['cost-only-domain-error'];
            return false;
        }
        $currentPage = 0;
        $maxPage = 0;
        $result = [];
        while ($currentPage <= $maxPage) {
            $prices = $this->getClient()->request('/api/v2/pricing?page=' . $currentPage, '', 'GET');
            $maxPage = $prices->meta->max_page ?? 0;
            if (!isset($prices->data) || !is_array($prices->data)) {
                $this->error = "Hata oluştu!";
                return false;
            }

            foreach ($prices->data as $val) {
                if (!isset($val->attributes)) continue;
                if (!isset($val->attributes->domain_tld)) continue;

                $name = $val->attributes->domain_tld;
                $currency = 'TRY';

                $registerAmount = number_format($val->attributes->register_amount, 2, '.', '');
                $transferAmount = number_format($val->attributes->transfer_amount, 2, '.', '');
                $renewAmount = number_format($val->attributes->renew_amount, 2, '.', '');
                if( is_object($val) && isset($val->attributes) && isset($val->attributes->currency))
                    $currency = $val->attributes->currency;

                $result[$name] = [
                    'register' => $registerAmount,
                    'transfer' => $transferAmount,
                    'renewal' => $renewAmount,
                    'currency' => $currency
                ];
            }
            $currentPage++;
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
                'name' => $sld,
                'tld' => $tld,
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
                "established" => true,
                "group_name" => Bootstrap::$lang->get_cm("website/account_products/product-type-names/domain", false, $ulang),
                "local_group_name" => Bootstrap::$lang->get_cm("website/account_products/product-type-names/domain", false, $locallang),
                "category_id" => 0,
                "domain" => $domain,
                "name" => $sld,
                "tld" => $tld,
                "dns_manage" => true,
                "whois_manage" => true,
                "transferlock" => $info["transferlock"],
                "cns_list" => $info["cns"] ?? [],
                "whois" => $info["whois"] ?? [],
            ];

            $order_data = [
                "owner_id" => (int)$user_id,
                "type" => "domain",
                "product_id" => (int)$productID,
                "name" => $domain,
                "period" => "year",
                "period_time" => (int)$year,
                "amount" => (float)$productPrice_amt,
                "total_amount" => (float)$productPrice_amt,
                "amount_cid" => (int)$productPrice_cid,
                "status" => "active",
                "cdate" => $start_date,
                "duedate" => $end_date,
                "renewaldate" => DateManager::Now(),
                "module" => $config["meta"]["name"],
                "options" => Utility::jencode($options),
                "unread" => 1,
            ];
            $insert = Orders::insert($order_data);
            if (!$insert) continue;
            $imports[] = $order_data["name"] . " (#" . $insert . ")";
        }

        if ($imports) {
            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "domain-imported", [
                'module' => $config["meta"]["name"],
                'imported' => implode(", ", $imports),
            ]);
        }
        return $imports;
    }

    public function apply_import_tlds()
    {
        $cost_cid = $this->config["settings"]["cost-currency"]; // Currency ID
        $prices = $this->cost_prices();
        if (!$prices) return false;

        Helper::Load(["Products", "Money"]);
        $allSystemCurrencies = Money::getCurrencies($cost_cid);

        $currenciesWithCode = [];
        foreach ($allSystemCurrencies as $systemCurrency)
        {
            $currenciesWithCode[$systemCurrency['code']] = $systemCurrency['id'];
        }

        $profit_rate = Config::get("options/domain-profit-rate");
        foreach ($prices as $name => $val) {
            $api_cost_prices = [
                'register' => $val["register"],
                'transfer' => $val["transfer"],
                'renewal' => $val["renewal"],
            ];

            if(!isset($currenciesWithCode[$val['currency']]))
                continue;
            $cost_cid = $currenciesWithCode[$val['currency']];

            $paperwork = 0;
            $epp_code = 1;
            $dns_manage = 1;
            $whois_privacy = 1;
            $module = $this->config["meta"]["name"];

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
                    'paperwork' => $paperwork,
                    'epp_code' => $epp_code,
                    'dns_manage' => $dns_manage,
                    'whois_privacy' => $whois_privacy,
                    'register_cost' => $register_cost,
                    'renewal_cost' => $renewal_cost,
                    'transfer_cost' => $transfer_cost,
                    'module' => $module,
                ]);

                Models::$init->db->update("prices", [
                    'amount' => $register_sale,
                    'cid' => $tld_cid,
                ])->where("id", "=", $reg_price["id"])->save();


                Models::$init->db->update("prices", [
                    'amount' => $renewal_sale,
                    'cid' => $tld_cid,
                ])->where("id", "=", $ren_price["id"])->save();


                Models::$init->db->update("prices", [
                    'amount' => $transfer_sale,
                    'cid' => $tld_cid,
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
                    'status' => "inactive",
                    'cdate' => DateManager::Now(),
                    'name' => $name,
                    'paperwork' => $paperwork,
                    'epp_code' => $epp_code,
                    'dns_manage' => $dns_manage,
                    'whois_privacy' => $whois_privacy,
                    'currency' => $tld_cid,
                    'register_cost' => $register_cost,
                    'renewal_cost' => $renewal_cost,
                    'transfer_cost' => $transfer_cost,
                    'module' => $module,
                ]);

                if ($insert) {
                    $tld_id = Models::$init->db->lastID();

                    Models::$init->db->insert("prices", [
                        'owner' => "tld",
                        'owner_id' => $tld_id,
                        'type' => 'register',
                        'amount' => $register_sale,
                        'cid' => $tld_cid,
                    ]);


                    Models::$init->db->insert("prices", [
                        'owner' => "tld",
                        'owner_id' => $tld_id,
                        'type' => 'renewal',
                        'amount' => $renewal_sale,
                        'cid' => $tld_cid,
                    ]);


                    Models::$init->db->insert("prices", [
                        'owner' => "tld",
                        'owner_id' => $tld_id,
                        'type' => 'transfer',
                        'amount' => $transfer_sale,
                        'cid' => $tld_cid,
                    ]);
                }

            }
        }
        return true;
    }
}