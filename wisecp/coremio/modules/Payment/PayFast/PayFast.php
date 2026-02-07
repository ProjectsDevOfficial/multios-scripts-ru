<?php
    class PayFast extends PaymentGatewayModule
    {
        function __construct()
        {
            $this->name             = __CLASS__;

            parent::__construct();

            define( 'PF_SOFTWARE_NAME', 'WISECP' );
            define( 'PF_SOFTWARE_VER',License::get_version());
            define( 'PF_MODULE_NAME', 'PayFast-WHMCS' );
            define( 'PF_MODULE_VER', '2.2.2' );

            define( 'PF_DEBUG', ( isset($this->config['settings']['debug']) && $this->config['settings']['debug'] ? true : false ) );

            $pfFeatures = 'PHP ' . phpversion() . ';';
            if ( in_array( 'curl', get_loaded_extensions() ) )
            {
                define( 'PF_CURL', '' );
                $pfVersion = curl_version();
                $pfFeatures .= ' curl ' . $pfVersion['version'] . ';';
            }
            else
            {
                $pfFeatures .= ' nocurl;';
            }

            define( 'PF_USER_AGENT', PF_SOFTWARE_NAME . '/' . PF_SOFTWARE_VER . ' (' . trim( $pfFeatures ) . ') ' . PF_MODULE_NAME . '/' . PF_MODULE_VER );

            define( 'PF_TIMEOUT', 15 );
            define( 'PF_EPSILON', 0.01 );

            define( 'PF_ERR_AMOUNT_MISMATCH', 'Amount mismatch' );
            define( 'PF_ERR_BAD_ACCESS', 'Bad access of page' );
            define( 'PF_ERR_BAD_SOURCE_IP', 'Bad source IP address' );
            define( 'PF_ERR_CONNECT_FAILED', 'Failed to connect to PayFast' );
            define( 'PF_ERR_INVALID_SIGNATURE', 'Security signature mismatch' );
            define( 'PF_ERR_MERCHANT_ID_MISMATCH', 'Merchant ID mismatch' );
            define( 'PF_ERR_NO_SESSION', 'No saved session found for ITN transaction' );
            define( 'PF_ERR_ORDER_ID_MISSING_URL', 'Order ID not present in URL' );
            define( 'PF_ERR_ORDER_ID_MISMATCH', 'Order ID mismatch' );
            define( 'PF_ERR_ORDER_INVALID', 'This order ID is invalid' );
            define( 'PF_ERR_ORDER_NUMBER_MISMATCH', 'Order Number mismatch' );
            define( 'PF_ERR_ORDER_PROCESSED', 'This order has already been processed' );
            define( 'PF_ERR_PDT_FAIL', 'PDT query failed' );
            define( 'PF_ERR_PDT_TOKEN_MISSING', 'PDT token not present in URL' );
            define( 'PF_ERR_SESSIONID_MISMATCH', 'Session ID mismatch' );
            define( 'PF_ERR_UNKNOWN', 'Unknown error occurred' );

            define( 'PF_MSG_OK', 'Payment was successful' );
            define( 'PF_MSG_FAILED', 'Payment has failed' );
            define( 'PF_MSG_PENDING',
                'The payment is pending. Please note, you will receive another Instant' .
                ' Transaction Notification when the payment status changes to' .
                ' "Completed", or "Failed"' );


        }

        public function config_fields()
        {
            return [
                'merchant_id'          => [
                    'name'              => "Merchant ID",
                    'description'       => 'Your Merchant ID as given on the <a href="https://www.payfast.co.za/acc/integration">Integration</a> page of your PayFast account',
                    'type'              => "text",
                    'value'             => $this->config["settings"]["merchant_id"] ?? '',
                ],
                'merchant_key'          => [
                    'name'              => "Merchant Key",
                    'description'       => 'Your Merchant Key as given on the <a href="https://www.payfast.co.za/acc/integration">Integration</a> page of your PayFast account',
                    'type'              => "text",
                    'value'             => $this->config["settings"]["merchant_key"] ?? '',
                ],
                'passphrase'          => [
                    'name'              => "PassPhrase",
                    'description'       => 'Your PassPhrase as when set on the <a href="http://www.payfast.co.za/acc/integration">Integration</a> page of your PayFast account',
                    'type'              => "text",
                    'value'             => $this->config["settings"]["passphrase"] ?? '',
                ],
                'enable_recurring'          => [
                    'name'              => "Enable Recurring Billing",
                    'description'       => 'Check to enable Recurring Billing after enabling adhoc Payments on the <a href="http://www.payfast.co.za/acc/integration">Integration</a> page of your PayFast account',
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (boolean) (int) ($this->config["settings"]["enable_recurring"] ?? 0),
                ],
                'force_recurring'          => [
                    'name'              => "Force Recurring Billing",
                    'description'       => 'Check to force all clients to use tokenized billing(adhoc subscriptions). This requires "Enable Recurring Billing" to be enabled to take effect.',
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (boolean) (int) ($this->config["settings"]["force_recurring"] ?? 0),
                ],
                'test_mode'          => [
                    'name'              => "Sandbox Test Mode",
                    'description'       => "Check to enable sandbox mode",
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (boolean) (int) ($this->config["settings"]["test_mode"] ?? 0),
                ],
                'debug'          => [
                    'name'              => "Debugging",
                    'description'       => "Check this to turn debug logging on",
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (boolean) (int) ($this->config["settings"]["debug"] ?? 0),
                ],
            ];
        }

        private function pflog( $msg = '', $close = false )
        {
            static $fh = 0;

            // Only log if debugging is enabled
            if ( PF_DEBUG )
            {
                if ( $close )
                {
                    fclose( $fh );
                }
                else
                {
                    // If file doesn't exist, create it
                    if ( !$fh )
                    {
                        $pathinfo = pathinfo( __FILE__ );
                        $fh = fopen( $pathinfo['dirname'] . '/payfast.log', 'a+' );
                    }

                    // If file was successfully created
                    if ( $fh )
                    {
                        $line = date( 'Y-m-d H:i:s' ) . ' : ' . $msg . "\n";

                        fwrite( $fh, $line );
                    }
                }
            }
        }

        private function pfGetData()
        {
            // Posted variables from ITN
            $pfData = $_POST;

            // Strip any slashes in data
            foreach ( $pfData as $key => $val )
            {
                $pfData[$key] = html_entity_decode( stripslashes( $val ), ENT_QUOTES );
            }

            // Return "false" if no data was received
            if ( sizeof( $pfData ) == 0 )
            {
                return ( false );
            }
            else
            {
                return ( $pfData );
            }

        }
        private function pf_create_button( $pfdata, $button_image, $url, $passphrase, $systemUrl )
        {
            // Create output string
            $pfOutput = '';
            foreach ( $pfdata as $key => $val )
            {
                $pfOutput .= $key . '=' . urlencode( trim( $val ) ) . '&';
            }

            if ( empty( $passphrase ) )
            {
                $pfOutput = substr( $pfOutput, 0, -1 );
            }
            else
            {
                $pfOutput = $pfOutput . "passphrase=" . urlencode( $passphrase );
            }

            $pfdata['signature'] = md5( $pfOutput );

            $pfhtml = '<form method="post" action="' . $url . '">';
            foreach ( $pfdata as $k => $v )
            {
                $pfhtml .= '<input type="hidden" name="' . $k . '" value="' . $v . '" />';
            }
            $buttonValue = $button_image == 'light-small-subscribe.png' ? 'Subscribe Now' : 'Pay Now';
            $pfhtml .= '<input type="image" align="centre" src="' . $this->url . 'images/' . $button_image . '" value="' . $buttonValue . '"></form>';
            return $pfhtml;
        }
        private function pfValidSignature( $pfData = null, &$pfParamString = null, $pfPassphrase = null )
        {
            // Dump the submitted variables and calculate security signature
            foreach ( $pfData as $key => $val )
            {
                if ( $key != 'signature' )
                {
                    $pfParamString .= $key . '=' . urlencode( $val ) . '&';
                }
                else
                {
                    break;
                }
            }

            // Remove the last '&' from the parameter string
            $pfParamString = substr( $pfParamString, 0, -1 );

            if ( !is_null( $pfPassphrase ) )
            {
                $pfParamStringWithPassphrase = $pfParamString . "&passphrase=" . urlencode( $pfPassphrase );
                $signature = md5( $pfParamStringWithPassphrase );
            }
            else
            {
                $signature = md5( $pfParamString );
            }

            $result = ( $pfData['signature'] == $signature );

            $this->pflog( 'Signature = ' . ( $result ? 'valid' : 'invalid' ) );
            $this->pflog( 'PFString = ' . $pfParamString );

            return ( $result );
        }
        private function pfValidData( $pfHost = 'www.payfast.co.za', $pfParamString = '', $pfProxy = null )
        {
            $this->pflog( 'Host = ' . $pfHost );
            $this->pflog( 'Params = ' . $pfParamString );

            // Use cURL (if available)
            if ( defined( 'PF_CURL' ) )
            {
                // Variable initialization
                $url = 'https://' . $pfHost . '/eng/query/validate';

                // Create default cURL object
                $ch = curl_init();

                // Set cURL options - Use curl_setopt for freater PHP compatibility
                // Base settings
                curl_setopt( $ch, CURLOPT_USERAGENT, PF_USER_AGENT ); // Set user agent
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true ); // Return output as string rather than outputting it
                curl_setopt( $ch, CURLOPT_HEADER, false ); // Don't include header in output
                curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
                curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );

                // Standard settings
                curl_setopt( $ch, CURLOPT_URL, $url );
                curl_setopt( $ch, CURLOPT_POST, true );
                curl_setopt( $ch, CURLOPT_POSTFIELDS, $pfParamString );
                curl_setopt( $ch, CURLOPT_TIMEOUT, PF_TIMEOUT );

                // Execute CURL
                $response = curl_exec( $ch );
                curl_close( $ch );
            }
            // Use fsockopen
            else
            {
                // Variable initialization
                $header = '';
                $res = '';
                $headerDone = false;

                // Construct Header
                $header = "POST /eng/query/validate HTTP/1.0\r\n";
                $header .= "Host: " . $pfHost . "\r\n";
                $header .= "User-Agent: " . PF_USER_AGENT . "\r\n";
                $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
                $header .= "Content-Length: " . strlen( $pfParamString ) . "\r\n\r\n";

                // Connect to server
                $socket = fsockopen( 'ssl://' . $pfHost, 443, $errno, $errstr, PF_TIMEOUT );

                // Send command to server
                fputs( $socket, $header . $pfParamString );

                // Read the response from the server
                while ( !feof( $socket ) )
                {
                    $line = fgets( $socket, 1024 );

                    // Check if we are finished reading the header yet
                    if ( strcmp( $line, "\r\n" ) == 0 )
                    {
                        // Read the header
                        $headerDone = true;
                    }
                    // If header has been processed
                    else if ( $headerDone )
                    {
                        // Read the main response
                        $response .= $line;
                    }
                }
            }

            $this->pflog( "Response:\n" . print_r( $response, true ) );

            // Interpret Response
            $lines = explode( "\r\n", $response );
            $verifyResult = trim( $lines[0] );

            if ( strcasecmp( $verifyResult, 'VALID' ) == 0 )
            {
                return ( true );
            }
            else
            {
                return ( false );
            }

        }
        private function pfValidIP( $sourceIP )
        {
            // Variable initialization
            $validHosts = array(
                'www.payfast.co.za',
                'sandbox.payfast.co.za',
                'w1w.payfast.co.za',
                'w2w.payfast.co.za',
            );

            $validIps = array();

            foreach ( $validHosts as $pfHostname )
            {
                $ips = gethostbynamel( $pfHostname );

                if ( $ips !== false )
                {
                    $validIps = array_merge( $validIps, $ips );
                }

            }

            // Remove duplicates
            $validIps = array_unique( $validIps );

            $this->pflog( "Valid IPs:\n" . print_r( $validIps, true ) );

            if ( in_array( $sourceIP, $validIps ) )
            {
                return ( true );
            }
            else
            {
                return ( false );
            }

        }
        private function pfAmountsEqual( $amount1, $amount2 )
        {
            if ( abs( floatval( $amount1 ) - floatval( $amount2 ) ) > PF_EPSILON )
            {
                return ( false );
            }
            else
            {
                return ( true );
            }

        }

        public function area($params=[])
        {
            // PayFast Configuration Parameters
            $merchant_id = $this->config['settings']['merchant_id'];
            $merchant_key = $this->config['settings']['merchant_key'];
            $passphrase = $this->config['settings']['passphrase'];
            $enable_recurring = $this->config['settings']['enable_recurring'];
            $force_recurring = $this->config['settings']['force_recurring'];
            $testMode = $this->config['settings']['test_mode'];
            $debug = $this->config['settings']['debug'];

            $invoiceId      = $this->checkout_id;
            $description    = $this->checkout["items"][0]["name"];
            $amount         = $params['amount'];
            $currencyCode   = $params['currency'];
            $baseCurrencyCode = $currencyCode;

            $firstname  = $this->clientInfo->name;
            $lastname   = $this->clientInfo->surname;
            $email      = $this->clientInfo->email;
            $address1   = $this->clientInfo->address->address;
            $address2   = '';
            $city       = $this->clientInfo->address->city;
            $state      = $this->clientInfo->address->counti;
            $postcode   = $this->clientInfo->address->zipcode;
            $country    = $this->clientInfo->address->country_code;
            $phone      = $this->clientInfo->phone;
            $pfToken    = '';

            $companyName        = __("website/index/meta/title");
            $systemUrl          = APP_URI;
            $returnUrl          = $this->links["return"];
            $successUrl         = $this->links["successful"];
            $langPayNow         = $this->l_payNow;
            $moduleDisplayName = $this->lang["invoice-name"];
            $moduleName         = $this->name;
            $wisecpVersion      = License::get_version();


            $pfHost     = (  ( $this->config['settings']['test_mode']) ? 'sandbox' : 'www' ) . '.payfast.co.za';
            $url        = 'https://' . $pfHost . '/eng/process';

            if (  ( $this->config['settings']['test_mode'] ) && ( empty( $this->config['settings']['merchant_id'] ) || empty( $this->config['settings']['merchant_key'] ) ) )
            {
                $merchant_id = '10004002';
                $merchant_key = 'q1cd2rdny4a53';
                $passphrase = 'payfast';
            }

            // Construct data for the form
            $data = array(
                // Merchant details
                'merchant_id' => $merchant_id,
                'merchant_key' => $merchant_key,
                'return_url' => $successUrl,
                'cancel_url' => $returnUrl,
                'notify_url' => $this->links["callback"],

                // Buyer Details
                'name_first' => trim( $firstname ),
                'name_last' => trim( $lastname ),
                'email_address' => trim( $email ),

                // Item details
                'm_payment_id' => $invoiceId,
                'amount' => number_format( $amount, 2, '.', '' ),
                'item_name' => $companyName . ' purchase, Invoice ID #' . $this->checkout_id,
                'item_description' => $description,
                'custom_str1' => 'PF_WISECP_'.substr($wisecpVersion,0,5). '_' . PF_MODULE_VER,
                'custom_str2' => $baseCurrencyCode,
            );

            //Create PayFast button/s on Invoice
            $htmlOutput = '';
            $button_image = 'light-small-paynow.png';

            if ( $enable_recurring && empty( $pfToken ) )
            {
                if ( !$force_recurring )
                {
                    //Create once-off button
                    $htmlOutput = $this->pf_create_button( $data, $button_image, $url, $passphrase, $systemUrl );
                }

                //Set button data to PayFast Subscription
                $data['subscription_type'] = 2;
                $button_image = 'light-small-subscribe.png';
            }
            //Append PayFast button
            $htmlOutput .= $this->pf_create_button( $data, $button_image, $url, $passphrase, $systemUrl );

            return $htmlOutput;
        }


        public function get_subscription($params=[])
        {
            return [
                'status'            => $params['status'],
                'status_msg'        => '',
                'first_paid'        => [
                    'time'              => $params['created_at'],
                    'fee'               => [
                        'amount'    => $params['first_paid_fee'],
                        'currency'  => $this->currency($params['currency']),
                    ],
                ],
                'last_paid'         => [
                    'time'          => $params['last_paid_date'],
                    'fee'           => [
                        'amount'    => $params['last_paid_fee'],
                        'currency'  => $this->currency($params['currency']),
                    ],
                ],
                'next_payable'      => [
                    'time'              => $params['next_payable_date'],
                    'fee'               => [
                        'amount'            => $params['last_paid_fee'],
                        'currency'          => $this->currency($params['currency']),
                    ],
                ],
                'failed_payments'   => 0,
            ];

        }

        public function cancel_subscription($params=[])
        {
            $sub_id     = $params["identifier"];


            $this->pflog( 'PayFast cancel subscription called' );

            // PayFast Configuration Parameters
            $merchant_id = $this->config['settings']['merchant_id'];
            $passphrase = $this->config['settings']['passphrase'];
            $testMode = $this->config['settings']['test_mode'];
            $debug = $this->config['settings']['debug'];

            $guid           = $sub_id;


            //Perform API call to capture payment and interpret result

            //Build URL
            $url = 'https://api.payfast.co.za/subscriptions/' . $guid . '/cancel';

            if ( $testMode)
            {
                $url = $url . '?testing=true';
                //Log testing true
                $this->pflog( "url: ?testing=true" );

                //Use default sandbox credentials if no merchant id set
                if ( empty( $this->config['settings']['merchant_id'] ) )
                {
                    $merchant_id = '10004002';
                    $passphrase = 'payfast';
                }
            }

            $hashArray = array();
            $payload = array();

            $hashArray['version'] = 'v1';
            $hashArray['merchant-id'] = $merchant_id;
            $hashArray['passphrase'] = $passphrase;
            $hashArray['timestamp'] = date( 'Y-m-d' ) . 'T' . date( 'H:i:s' );
            $orderedPrehash = array_merge( $hashArray, $payload );
            ksort( $orderedPrehash );
            $signature = md5( http_build_query( $orderedPrehash ) );

            //log Post data
            $this->pflog( 'version: ' . $hashArray['version'] );
            $this->pflog( 'merchant-id: ' . $hashArray['merchant-id'] );
            $this->pflog( 'signature: ' . $signature );
            $this->pflog( 'timestamp: ' . $hashArray['timestamp'] );

            // configure curl
            $ch = curl_init( $url );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $ch, CURLOPT_HEADER, false );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 2 );
            curl_setopt( $ch, CURLOPT_TIMEOUT, 60 );
            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt( $ch, CURLOPT_VERBOSE, 1 );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
                'version: v1',
                'merchant-id: ' . $merchant_id,
                'signature: ' . $signature,
                'timestamp: ' . $hashArray['timestamp'],
            ) );

            $response = curl_exec( $ch );
            $error      = curl_errno($ch) ? curl_error($ch) : false;
            //Log API response
            $this->pflog( 'response :' . $response );

            curl_close( $ch );

            $pfResponse = json_decode( $response );

            if($error)
            {
                $this->error = $error;
                $this->pflog('Error : '.$error);
                return false;
            }

            if($pfResponse['status'] != 'success')
            {
                $error = $pfResponse['data']['message'];
                $this->error = $error;
                $this->pflog('Error : '.$error);

                return false;
            }

            // Close log
            $this->pflog( '', true );


            return true;
        }

        public function capture_subscription($params=[])
        {
            $sub_id     = $params["identifier"];
            $amount     = $params["next_payable_fee"];
            $curr       = $params["currency"];


            $this->pflog( 'PayFast capture called' );

            // PayFast Configuration Parameters
            $merchant_id = $this->config['settings']['merchant_id'];
            $passphrase = $this->config['settings']['passphrase'];
            $testMode = $this->config['settings']['test_mode'];
            $debug = $this->config['settings']['debug'];

            // Invoice Parameters
            $invoiceId      = $params["invoices"][0];
            $guid           = $sub_id;


            //Perform API call to capture payment and interpret result

            //Build URL
            $url = 'https://api.payfast.co.za/subscriptions/' . $guid . '/adhoc';

            if ( $testMode)
            {
                $url = $url . '?testing=true';
                //Log testing true
                $this->pflog( "url: ?testing=true" );

                //Use default sandbox credentials if no merchant id set
                if ( empty( $this->config['settings']['merchant_id'] ) )
                {
                    $merchant_id = '10004002';
                    $passphrase = 'payfast';
                }
            }

            $hashArray = array();
            $payload = array();

            $payload['amount'] = $amount * 100;
            $payload['item_name'] = ___("website/index/meta/title") . ' purchase, Invoice ID #' . $invoiceId;

            //Prevention of race condition on adhoc ITN check
            $payload['item_description'] = 'tokenized-adhoc-payment-dc0521d355fe269bfa00b647310d760f';

            $payload['m_payment_id'] = $invoiceId;

            $hashArray['version'] = 'v1';
            $hashArray['merchant-id'] = $merchant_id;
            $hashArray['passphrase'] = $passphrase;
            $hashArray['timestamp'] = date( 'Y-m-d' ) . 'T' . date( 'H:i:s' );
            $orderedPrehash = array_merge( $hashArray, $payload );
            ksort( $orderedPrehash );
            $signature = md5( http_build_query( $orderedPrehash ) );

            //log Post data
            $this->pflog( 'version: ' . $hashArray['version'] );
            $this->pflog( 'merchant-id: ' . $hashArray['merchant-id'] );
            $this->pflog( 'signature: ' . $signature );
            $this->pflog( 'timestamp: ' . $hashArray['timestamp'] );

            // configure curl
            $ch = curl_init( $url );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $ch, CURLOPT_HEADER, false );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 2 );
            curl_setopt( $ch, CURLOPT_TIMEOUT, 60 );
            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $payload ) );
            curl_setopt( $ch, CURLOPT_VERBOSE, 1 );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
                'version: v1',
                'merchant-id: ' . $merchant_id,
                'signature: ' . $signature,
                'timestamp: ' . $hashArray['timestamp'],
            ) );

            $response = curl_exec( $ch );
            $error      = curl_errno($ch) ? curl_error($ch) : false;
            //Log API response
            $this->pflog( 'response :' . $response );

            curl_close( $ch );

            $pfResponse = json_decode( $response );

            if($error)
            {
                $this->error = $error;
                $this->pflog('Error : '.$error);
                return false;
            }

            if($pfResponse['status'] != 'success')
            {
                $error = $pfResponse['data']['message'];
                $this->error = $error;
                $this->pflog('Error : '.$error);

                return false;
            }

            // Close log
            $this->pflog( '', true );


            return true;
        }

        public function change_subscription_fee($params=[],$value = 0,$currency=0)
        {
            return true;
        }


        public function callback()
        {

            // Variable Initialization
            $pfError = false;
            $pfErrMsg = '';
            $pfData = array();
            $pfHost = (  ( $this->config['settings']['test_mode'] == 'on' ) ? 'sandbox' : 'www' ) . '.payfast.co.za';
            $pfOrderId = '';
            $pfParamString = '';

            $this->pflog( 'PayFast ITN call received' );

// Notify PayFast that information has been received
            if ( !$pfError )
            {
                header( 'HTTP/1.0 200 OK' );
                flush();
            }


            // Retrieve data returned in PayFast callback
            if ( !$pfError )
            {
                $this->pflog( 'Get posted data' );

                // Posted variables from ITN
                $pfData = $this->pfGetData();

                $this->pflog( 'PayFast Data: ' . print_r( $pfData, true ) );
                //logActivity( 'PayFast Data: '. print_r( $pfData, true ) );

                if ( $pfData === false )
                {
                    $pfError = true;
                    $pfErrMsg = PF_ERR_BAD_ACCESS;
                }
            }

            $invoiceId = (int) $pfData['m_payment_id'];

            // Verify security signature
            if ( !$pfError )
            {
                $this->pflog( 'Verify security signature' );

                $passphrase = null;

                if ( !empty( $this->config['settings']['passphrase'] ) )
                {
                    $passphrase = $this->config['settings']['passphrase'];
                }

                if($this->config['settings']['test_mode'] && empty( $this->config['settings']['merchant_id'] ) )
                {
                    $passphrase = 'payfast';
                }

                // If signature different, log for debugging
                if ( !$this->pfValidSignature( $pfData, $pfParamString, $passphrase ) )
                {
                    $pfError = true;
                    $pfErrMsg = PF_ERR_INVALID_SIGNATURE;
                }
            }

// Get internal order and verify it hasn't already been processed
            if ( !$pfError )
            {
                $this->pflog( "Check order hasn't been processed" );
                // Checks invoice ID is a valid invoice number or ends processing

                $checkout       = $this->get_checkout($invoiceId);

                // Checkout invalid error
                if(!$checkout)
                {
                    $this->error = 'Checkout ID unknown';
                    return false;
                }
            }

            if ( !$pfError )
            {
                $this->pflog( 'Verify data received' );

                $pfValid = $this->pfValidData( $pfHost, $pfParamString );

                if ( !$pfValid )
                {
                    $pfError = true;
                    $pfErrMsg = PF_ERR_BAD_ACCESS;
                }
            }

            $transactionStatus = 'Unsuccessful';
            $token             = '';

            if ( $pfData['payment_status'] == "COMPLETE" && !$pfError )
            {
                $this->pflog( 'Checking order' );
                $transactionStatus = 'Successful';

                $amountGross = $pfData['amount_gross'];
                $amountFee = $pfData['amount_fee'];


                //Check if response is adhoc
                if ( $pfData['item_description'] == 'tokenized-adhoc-payment-dc0521d355fe269bfa00b647310d760f' )
                {
                    $this->pflog( "adhoc payment" );

                    return [
                        'status'            => 'successful',
                    ];

                }


                //Add token on adhoc subscription
                if ( !empty( $pfData['token'] ) && !empty($pfData['custom_str1']) )
                {

                    $token = $pfData['token'];

                }
            }

// If an error occurred
            if ($pfError) {
                $this->pflog('Error occurred: ' . $pfErrMsg);
            }


            $this->pflog( '', true );

            if($token && $items = $this->subscribable_items())
            {
                $subscribed = [];
                foreach($items AS $item)
                    $subscribed[$item['identifier']] = $token;
                if($subscribed) $this->set_subscribed_items($subscribed);
            }

            return  [
                'status'            => 'successful',
            ];
        }

    }