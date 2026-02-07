<?php

    namespace Tripay;

    use Exception;

    class ApiRequestor
    {
        public static function post($url, $data_hash, $apiKey = "")
        {
            return self::remoteCall($url, $data_hash, true, $apiKey);
        }

        public static function remoteCall($url, $data_hash, $post = true, $apiKey = "")
        {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: application/json',
                'Authorization: Bearer '.$apiKey,
                'X-Plugin-Meta: whmcs|'.TRIPAY_PAYMENT_PLUGIN_VERSION,
            ));

            if( $post )
            {
                curl_setopt($ch, CURLOPT_POST, true);

                if( is_array($data_hash) )
                {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data_hash));
                }
                else
                {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, '');
                }
            }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FAILONERROR, false);

            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if( $result === FALSE || $errno )
            {
                throw new Exception('CURL Error: ' . $error, $errno);
            }
            else
            {
                $data = json_decode($result);
                if( $data->success !== true )
                {
                    $message = 'TriPay Error ('.$data->message.'): ';
                    throw new Exception($message);
                }

                return $data;
            }
        }
    }