<?php
    namespace WISECP\Modules\Pipe;

    class Microsoft
    {
        public $name = "Microsoft";
        public $config = [], $lang = [], $redirect_uri = NULL;

        public function __construct()
        {
            $this->config           = \Modules::Config("Pipe",$this->name);
            $this->lang             = \Modules::Lang("Pipe",$this->name);
            $this->redirect_uri     =  \Controllers::$init->AdminCRLink("tickets-2",["pipe-callback",$this->name],"none");
        }

        private function load_vendor()
        {
            $vendor_dir = __DIR__.DS."vendor";

            if(!file_exists($vendor_dir.DS."autoload.php"))
            {
                mkdir($vendor_dir,0755);
                $source_url         = "https://wisecp.com/files/modules/MicrosoftGraphSDK-vendor.zip";
                $source_archive     = ROOT_DIR."temp".DS."MicrosoftGraphSDK-vendor.zip";
                $download_zip       = \Updates::download_remote_file($source_url,$source_archive);

                if(!$download_zip || !file_exists($source_archive))
                    return [
                        'status' => "error",
                        'message' => "Resource files cannot be downloaded from the server.",
                    ];

                if(!class_exists("\\ZipArchive"))
                    return [
                        'status' => "error",
                        'message' => "ZipArchive class not found",
                    ];

                $zip = new \ZipArchive;

                if ($zip->open($source_archive) === TRUE) {
                    $zip->extractTo($vendor_dir);
                    $zip->close();
                    \FileManager::file_delete($source_archive);
                }
                else
                    return [
                        'status' => "error",
                        'message' => "ZipArchive can not open file",
                    ];
            }
            require __DIR__.DS.'vendor'.DS.'autoload.php';
            return true;
        }

        public function save_config($data=[])
        {
            $data           = array_replace_recursive($this->config ?: [],$data);
            $var_export     = \Utility::array_export($data,['pwith' => true]);
            return \FileManager::file_write(__DIR__.DS."config.php",$var_export);
        }

        public function callback():bool
        {
            $vendor = $this->load_vendor();
            if($vendor !== true)
            {
                echo $vendor["message"];
                return false;
            }

            $department_id = \Session::get($this->name."_did");

            $redirect   = \Controllers::$init->AdminCRLink("tickets-1",["settings"])."?tab=pipe&department=d".$department_id;

            $config     = $this->config[$department_id] ?? [];

            try {
                $code                   = \Filter::init("GET/code");
                $error_description      = \Filter::init("GET/error_description");

                if(!$department_id) throw new \Exception("Department ID not found");
                if($config["tokens"]) throw new \Exception("Already authenticated");

                if($error_description) throw new \Exception($error_description);

                $oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
                    'clientId'                => $config["client_id"] ?? 'na',
                    'clientSecret'            => $config["client_secret"] ?? 'na',
                    'redirectUri'             => $this->redirect_uri,
                    'urlAuthorize'            => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                    'urlAccessToken'          => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                    'urlResourceOwnerDetails' => '',
                    'scopes'                  => 'openid profile offline_access User.Read Mail.Read Mail.ReadWrite'
                ]);

                $accessToken = $oauthClient->getAccessToken('authorization_code', [
                    'code' => $code
                ]);


                $tokens = \Utility::jencode([
                    'access_token' => $accessToken->getToken(),
                    'refresh_token' => $accessToken->getRefreshToken(),
                    'expires_in' => $accessToken->getExpires(),
                ]);
                $tokens = \Crypt::encode($tokens,\Config::get("crypt/system"));
                $write      = $this->save_config([$department_id => ['tokens' => $tokens]]);

                if(!$write) throw new \Exception("Unable to write config.php file");

                \Utility::redirect($redirect);
                echo 'Authorization is successful';
            }
            catch (\Exception | \League\OAuth2\Client\Provider\Exception\IdentityProviderException $e)
            {
                echo '<h2>Error</h2>';
                echo '<h3>'.$e->getMessage().'</h3>';
                echo '<p><a href="'.$redirect.'">Continue</a></p>';
            }

            return true;
        }

        public function oAuth2($did=0):array
        {
            $vendor = $this->load_vendor();
            if($vendor !== true) return $vendor;

            $client_id          = \Filter::init("POST/module/".$this->name."/".$did."/client_id") ?: 'na';
            $client_secret      = \Filter::init("POST/module/".$this->name."/".$did."/client_secret") ?: 'na';

            \Session::set($this->name."_did",$did);

            try {

                $oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
                    'clientId'                => $client_id,
                    'clientSecret'            => $client_secret,
                    'redirectUri'             => $this->redirect_uri,
                    'urlAuthorize'            => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                    'urlAccessToken'          => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                    'urlResourceOwnerDetails' => '',
                    'scopes'                  => 'openid profile offline_access User.Read Mail.Read Mail.ReadWrite'
                ]);

                \Session::set("oauth2state",$oauthClient->getState());

                $redirect = $oauthClient->getAuthorizationUrl();

            }
            catch(\Exception $e)
            {
                return [
                    'status' => "error",
                    'message' => $e->getMessage(),
                ];
            }

            return [
                'status'    => "successful",
                'redirect'  => $redirect,
                'saved'   => true,
            ];
        }

        public function test_connection($did=0):array
        {
            $vendor = $this->load_vendor();
            if($vendor !== true) return $vendor;

            $config         = $this->config[$did] ?? [];

            try {
                $client_id          = \Filter::init("POST/module/".$this->name."/".$did."/client_id") ?: 'na';
                $client_secret      = \Filter::init("POST/module/".$this->name."/".$did."/client_secret") ?: 'na';

                $oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
                    'clientId'                => $client_id,
                    'clientSecret'            => $client_secret,
                    'redirectUri'             => $this->redirect_uri,
                    'urlAuthorize'            => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                    'urlAccessToken'          => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                    'urlResourceOwnerDetails' => '',
                    'scopes'                  => 'openid profile offline_access User.Read Mail.Read Mail.ReadWrite'
                ]);

                $tokens                     = $config["tokens"] ?? '';
                $tokens                     = $tokens ? \Crypt::decode($tokens,\Config::get("crypt/system")) : false;
                $tokens                     = $tokens ? \Utility::jdecode($tokens,true) : [];

                if(!$tokens) throw new \Exception("The test cannot be performed because authorisation has not been granted.");

                if($tokens['expires_in'] < time())
                {
                    if(!$tokens['refresh_token']) throw new \Exception("Refresh token not found");
                    $accessToken = $oauthClient->getAccessToken('refresh_token',[
                        'refresh_token' => $tokens['refresh_token']
                    ]);

                    $tokens = [
                        'access_token' => $accessToken->getToken(),
                        'refresh_token' => $accessToken->getRefreshToken(),
                        'expires_in' => $accessToken->getExpires(),
                    ];

                    $write      = $this->save_config([
                        $did => [
                            'tokens' => \Crypt::encode(\Utility::jencode($tokens),\Config::get("crypt/system"))
                        ]
                    ]);
                    if(!$write) throw new \Exception("Unable to write config.php file");
                }

                $client = new \GuzzleHttp\Client([
                    'base_uri' => 'https://graph.microsoft.com/v1.0/',
                    'headers' => [
                        'Authorization' => 'Bearer '.$tokens["access_token"]
                    ]
                ]);

                $response = $client->get('me/messages');
                $messages = json_decode($response->getBody(), true);
                $messages = $messages['value'] ?? [];

            }
            catch (\GuzzleHttp\Exception\ClientException | \Exception $e)
            {
                return [
                    'status' => "error",
                    'message' => $e->getMessage(),
                ];
            }

            if(!isset($this->config["activation_date"])) $this->save_config(['activation_date' => \DateManager::Now()]);


            return [
                'status'    => "successful",
                'message'   => __("admin/products/success12"),
            ];
        }

        public function inbox():array
        {
            $vendor = $this->load_vendor();
            if($vendor !== true) return $vendor;

            $configs = $this->config;

            $messages           = [];

            foreach($configs AS $did => $config)
            {
                try
                {
                    $client_id          = $config["client_id"] ?? '';
                    $client_secret      = $config["client_secret"] ?? '';
                    $tokens             = $config["tokens"] ?? false;
                    $tokens             = $tokens ? \Crypt::decode($tokens,\Config::get("crypt/system")) : false;
                    $tokens             = $tokens ? \Utility::jdecode($tokens,true) : [];

                    $continue = \Config::get("options/ticket-pipe/mail/".$did."/provider") != $this->name;
                    if(!$continue && (!$client_id || !$client_secret || !$tokens)) $continue = true;
                    if($continue) continue;

                    $oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
                        'clientId'                => $client_id,
                        'clientSecret'            => $client_secret,
                        'redirectUri'             => $this->redirect_uri,
                        'urlAuthorize'            => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                        'urlAccessToken'          => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                        'urlResourceOwnerDetails' => '',
                        'scopes'                  => 'openid profile offline_access User.Read Mail.Read Mail.ReadWrite'
                    ]);

                    if($tokens['expires_in'] < time())
                    {
                        if(!$tokens['refresh_token']) throw new \Exception("Refresh token not found");
                        $accessToken = $oauthClient->getAccessToken('refresh_token',[
                            'refresh_token' => $tokens['refresh_token']
                        ]);

                        $tokens = [
                            'access_token' => $accessToken->getToken(),
                            'refresh_token' => $accessToken->getRefreshToken(),
                            'expires_in' => $accessToken->getExpires(),
                        ];

                        $write      = $this->save_config([
                            $did => [
                                'tokens' => \Crypt::encode(\Utility::jencode($tokens),\Config::get("crypt/system"))
                            ]
                        ]);
                        if(!$write) throw new \Exception("Unable to write config.php file");
                    }

                    $client = new \GuzzleHttp\Client([
                        'base_uri' => 'https://graph.microsoft.com/v1.0/',
                        'headers' => [
                            'Authorization' => 'Bearer '.$tokens["access_token"]
                        ]
                    ]);

                    $response = $client->get('me/messages?$filter=isRead eq false&$top=10');
                    $messageList = json_decode($response->getBody(), true);
                    $messageList = $messageList['value'] ?? [];

                    if($messageList)
                    {
                        foreach($messageList AS $message)
                        {
                            $messages[] = [
                                'ip'        => '',
                                'date'      => \DateManager::format("Y-m-d H:i:s",$message['receivedDateTime']),
                                'subject'       => $message['subject'],
                                'spam'          => false,
                                'from'          => [
                                    'name'      => trim($message['from']['emailAddress']['name'] ?? '','"'),
                                    'address'   => $message['from']['emailAddress']['address'] ?? '',
                                ],
                                'to'            => [
                                    'name'      => trim($message['toRecipients'][0]['emailAddress']['name'] ?? '','"'),
                                    'address'   => $message['toRecipients'][0]['emailAddress']['address'] ?? '',
                                ],
                                'message'       => $message['body']['content'] ?? '',
                                'attachments'   => $message['hasAttachments'] ? $this->getAttachments($tokens["access_token"], $message['id']) : [],
                            ];
                            $this->markMessageAsRead($tokens["access_token"], $message['id']);
                        }
                    }
                }
                catch (\GuzzleHttp\Exception\ClientException | \Exception $e)
                {
                    $event_type = version_compare(\License::get_version(),"4.0","<") ? "info" : "error";
                    \Helper::Load("Events");
                    $check = \Events::isCreated($event_type,"system",0,"email-pipe-error",'pending');
                    if(!$check)
                    {
                        \Events::create([
                            'type'          => $event_type,
                            'owner'         => "system",
                            'owner_id'      => 0,
                            'name'          => "email-pipe-error",
                            'data'          => [
                                'provider' => $this->lang["name"] ?: $this->name,
                                'email'    => \Config::get("options/ticket-pipe/mail/".$did."/from"),
                                'did'      => $did,
                                'message'  => $e->getMessage()
                            ],
                        ]);
                    }
                }
            }

            return [
                'status'    => "successful",
                'data'      => $messages,
            ];
        }

        public function clear_tokens($did=0)
        {
            $this->save_config([$did => ['tokens' => '']]);

            return [
                'status' => "successful",
            ];
        }

        private function getAttachments($accessToken, $messageId)
        {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', "https://graph.microsoft.com/v1.0/me/messages/$messageId/attachments", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);
            $response   =  json_decode($response->getBody(), true)['value'] ?? [];
            $files      = [];
            if($response)
            {
                foreach($response AS $a)
                {
                    $attachmentData = $a["contentBytes"];
                    $file_name      = $a["name"];
                    $rand_filename  = strtolower(substr(md5(uniqid(rand())), 0,23));
                    $ext_arr        = explode(".", $file_name);
                    $extension      = strtolower(array_pop($ext_arr));
                    $new_file_name  = $rand_filename.".".$extension;
                    $file_size      = $a["size"];

                    $files[] = [
                        'file_name' => $file_name,
                        'name'      => $new_file_name,
                        'file_ext'  => $extension,
                        'size'      => $file_size,
                        'content'   => $attachmentData,
                    ];
                }
            }
            return $files;
        }
        private function markMessageAsRead($accessToken, $messageId) {
            $client = new \GuzzleHttp\Client();
            $client->request('PATCH', "https://graph.microsoft.com/v1.0/me/messages/$messageId", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'isRead' => true,
                ],
            ]);
        }

    }