<?php

namespace Gri\B2B;


class griB2B
{
    const API_URL_SANDBOX = "https://b2b-api-test.gri.net";
    const API_URL_PROD = "https://b2b-prod-api.gri.net";

    private string $apiUser;
    private string $apiPassword;
    private string $apiClientId;
    private string $apiClientSecret;
    private string $baseUrl;
    private bool $test;
    private array $curl_options;
    private bool $debugMode = false;

    private $http_client;

    public function __construct(
        $apiUser, $apiPassword, $apiClientId, $apiClientSecret, $test_mode = true
    )
    {
        $this->apiUser = $apiUser;
        $this->apiPassword = $apiPassword;

        $this->apiClientId = $apiClientId;
        $this->apiClientSecret = $apiClientSecret;

        $custom_options_file = dirname(__FILE__) . '/custom_curl_options.json';

        $custom_options = array();
        $this->test = $test_mode;

        $this->baseUrl = ($this->test) ? self::API_URL_SANDBOX : self::API_URL_PROD;

        if (file_exists($custom_options_file) && file_get_contents($custom_options_file)) {
            $options_data = json_decode(file_get_contents($custom_options_file));
            if (is_array($options_data) && count($options_data) > 0) {
                foreach ($options_data as $k => $data) {
                    if (!isset($data->key) || !isset($data->value)) {
                        continue;
                    }

                    $custom_options[$data->key] = $data->value;
                }

                $this->curl_options = $custom_options;
            }
        }

        $this->init_curl();
    }

    public function enableDebug()
    {
        $this->debugMode = true;
    }

    private function init_curl()
    {
        $this->http_client = curl_init();
        curl_setopt($this->http_client, CURLOPT_HEADER, 0);
        curl_setopt($this->http_client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->http_client, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($this->http_client, CURLOPT_FOLLOWLOCATION, 1);
        if (isset($this->curl_options) && $this->curl_options) {
            foreach ($this->curl_options as $key => $curl_option) {
                curl_setopt($this->http_client, $key, $curl_option);
            }
        }
    }

    private function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function request($url = '', $params = array(), $method = 'POST')
    {
        try {
            if ($url !== '/oauth/v2/token') {
                $t = $this->get_token();
            }

            $headers = [];
            $urlCompleted = $url;
            $paramsCompleted = $params;
            if ($method == 'GET' && !empty($params)) {
                $urlCompleted .= '?' . http_build_query($params);
                $paramsCompleted = array();
            }

            if (!empty($paramsCompleted)) {
                $paramsCompleted = json_encode($paramsCompleted);
                $headers['Content-Type'] = 'application/json';
            }

            if($method !== "GET")
                curl_setopt($this->http_client, CURLOPT_CUSTOMREQUEST, strtoupper($method));

            if($paramsCompleted)
                curl_setopt($this->http_client, CURLOPT_POSTFIELDS, $paramsCompleted);

            curl_setopt($this->http_client, CURLOPT_URL, $this->getBaseUrl() . $urlCompleted);

            if (!empty($t)) {
                $headers['Authorization'] = sprintf('Bearer %s', $t);
            }

            $headersReadyToSent = [];
            if (is_array($headers) && count($headers))
                foreach ($headers as $hKey => $header) {
                    $headersReadyToSent[] = sprintf("%s: %s", $hKey, $header);
                }

            curl_setopt($this->http_client, CURLOPT_HTTPHEADER, $headersReadyToSent);
            $body = curl_exec($this->http_client);

            if($this->debugMode) {
                // TODO: Debug
            }
        } catch (\Exception $exception) {
            $body = (object)[
                'message' => $exception->getMessage(),
                'status' => false
            ];
        }

        if (!function_exists('Gri\B2B\isJson')) {
            function isJson($string)
            {
                json_decode($string);

                return (json_last_error() == JSON_ERROR_NONE);
            }
        }

        $responseBody = isJson($body) ? json_decode($body) : $body;
        if (isset($responseBody->status) && !$responseBody->status) {
            $msg = "UNKNOWN MESSAGE";
            if (isset($responseBody->detail)) {
                $msg = $responseBody->detail;
            }
            if (isset($responseBody->message)) {
                $msg = $responseBody->message;
            }

            throw new \Exception($msg);
        }

        return $responseBody;
    }

    /**
     * @throws \Exception
     */
    public function get_token($force_refresh = false, $force_new_token = false)
    {
        $tokenFile = dirname(__FILE__) . '/token.data';
        if (!file_exists($tokenFile)) {
            fopen($tokenFile, "w");
        }

        $read = file_get_contents($tokenFile);
        $contentObj = json_decode($read);
        if ((!$force_refresh && !$force_new_token) && isset($contentObj->created_at) && ($contentObj->created_at + $contentObj->expires_in) > time()) {
            return $contentObj->access_token;
        }

        $res = null;
        if (!$force_new_token && (($force_refresh) || isset($contentObj->refresh_token) && $contentObj->refresh_token)) {
            try {
                $res = $this->request('/oauth/v2/token',
                    array(
                        'refresh_token' => $contentObj->refresh_token,
                        'client_id' => $this->apiClientId,
                        'client_secret' => $this->apiClientSecret,
                        'grant_type' => 'refresh_token'
                    ),
                    'GET'
                );
            } catch (\Exception $exception) {
                // TODO: Debug
                // "Error occurred while refreshing token! Error message: " . $exception->getMessage()
            }
        }

        if (is_null($res)) {
            try {
                $res = $this->request('/oauth/v2/token',
                    array(
                        'username' => $this->apiUser,
                        'password' => $this->apiPassword,
                        'client_id' => $this->apiClientId,
                        'client_secret' => $this->apiClientSecret,
                        'grant_type' => 'password'
                    ),
                    'GET'
                );
            } catch (\Exception $exception) {
                // TODO: Debug
//                "Error occurred while create new token! Error message: " . $exception->getMessage()
            }
        }

        if (is_null($res)) {
            return false;
        }

        $contents = $res;
        $contents->created_at = time();

        if(isset($contents->error) && $contents->error)
        {
            return $contents;
        }

        if(isset($contents->access_token)) {
            $token = $contents->access_token;

            if (is_object($contents)) {
                $contents = json_encode($contents, JSON_PRETTY_PRINT);
                file_put_contents($tokenFile, $contents);
            }

            return $token;
        }
        return false;
    }
}