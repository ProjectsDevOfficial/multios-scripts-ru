<?php
    namespace WISECP\Modules\Pipe;

    class Google
    {
        public $name = "Google";
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
                $source_url         = "https://wisecp.com/files/modules/ConnectGoogle-vendor.zip";
                $source_archive     = ROOT_DIR."temp".DS."ConnectGoogle-vendor.zip";
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
                if($config["tokens"]) throw new \Exception("Already authenticated");
                if(!$department_id) throw new \Exception("Department id not found");

                $code   = \Filter::init("GET/code");

                $client = new \Google\Client();

                $client->setApplicationName('WISECP Pipe Mail Import');
                $client->setScopes(\Google\Service\Gmail::MAIL_GOOGLE_COM);
                $client->setAccessType('offline');
                $client->setPrompt('select_account consent');

                $client->setClientId($config["client_id"] ?? 'na');
                $client->setClientSecret($config["client_secret"] ?? 'na');
                $client->setRedirectUri($this->redirect_uri);

                $accessToken = $client->fetchAccessTokenWithAuthCode($code);

                $client->setAccessToken($accessToken);
                $tokens = \Utility::jencode($client->getAccessToken());
                $tokens = \Crypt::encode($tokens,\Config::get("crypt/system"));

                $write      = $this->save_config([$department_id => ['tokens' => $tokens]]);

                if(!$write) throw new \Exception("Unable to write config.php file");

                \Utility::redirect($redirect);
                echo 'Authorization is successful';
            }
            catch (\Exception $e)
            {
                echo '<h2>Error</h2>';
                echo '<h3>'.$e->getMessage().'</h3>';
                echo '<p><a href="'.$redirect.'">Continue</a></p>';

                return false;
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
                $client = new \Google\Client();

                $client->setApplicationName('WISECP Pipe Mail Import');
                $client->setScopes(\Google\Service\Gmail::MAIL_GOOGLE_COM);
                $client->setAccessType('offline');
                $client->setPrompt('select_account consent');

                $client->setClientId($client_id ?: 'na');
                $client->setClientSecret($client_secret ?: 'na');
                $client->setRedirectUri($this->redirect_uri);


                $redirect = $client->createAuthUrl();
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

                $client = new \Google\Client();

                $client->setApplicationName('WISECP Pipe Mail Import');
                $client->setScopes(\Google\Service\Gmail::MAIL_GOOGLE_COM);
                $client->setAccessType('offline');
                $client->setPrompt('select_account consent');

                $client->setClientId($client_id ?: 'na');
                $client->setClientSecret($client_secret ?: 'na');

                if(!$config["tokens"]) throw new \Exception("Testing is not possible due to lack of authorisation.");
                $tokens         = \Crypt::decode($config["tokens"],\Config::get("crypt/system"));
                $tokens         = \Utility::jdecode($tokens,true);
                $client->setAccessToken($tokens);

                if($client->isAccessTokenExpired())
                {
                    if($client->getRefreshToken())
                    {
                        $newAccessToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                        $tokens         = \Utility::jencode($newAccessToken);
                        $tokens         = \Crypt::encode($tokens,\Config::get("crypt/system"));
                        $this->save_config([$did => ['tokens' => $tokens]]);
                    }
                }

                $service = new \Google\Service\Gmail($client);

                $user = 'me';
                $optParams = [
                    'labelIds' => ['INBOX'],
                    'q' => 'is:unread',
                ];
                $results = $service->users_messages->listUsersMessages($user, $optParams);

            }
            catch (\Exception $e)
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
                try {
                    $client_id          = $config["client_id"] ?? '';
                    $client_secret      = $config["client_secret"] ?? '';
                    $tokens             = $config["tokens"] ?? false;

                    if($tokens)
                    {
                        $tokens             = \Crypt::decode($tokens,\Config::get("crypt/system"));
                        $tokens             = \Utility::jdecode($tokens,true);
                    }

                    $continue = \Config::get("options/ticket-pipe/mail/".$did."/provider") != $this->name;
                    if(!$continue && (!$client_id || !$client_secret || !$tokens)) $continue = true;
                    if($continue) continue;


                    $client = new \Google\Client();

                    $client->setApplicationName('WISECP Pipe Mail Import');
                    $client->setScopes(\Google\Service\Gmail::MAIL_GOOGLE_COM);
                    $client->setAccessType('offline');
                    $client->setPrompt('select_account consent');


                    $client->setClientId($client_id ?: 'na');
                    $client->setClientSecret($client_secret ?: 'na');

                    $client->setAccessToken($tokens);

                    if($client->isAccessTokenExpired())
                    {
                        if($client->getRefreshToken())
                        {
                            $newAccessToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                            $tokens         = \Utility::jencode($newAccessToken);
                            $tokens         = \Crypt::encode($tokens,\Config::get("crypt/system"));
                            $this->save_config([$did => ['tokens' => $tokens]]);
                        }
                    }

                    $service = new \Google\Service\Gmail($client);

                    $user = 'me';
                    $optParams = [
                        'labelIds' => ['INBOX'],
                        'q' => 'is:unread',
                        'maxResults' => 10,
                    ];
                    $results = $service->users_messages->listUsersMessages($user, $optParams);
                    $messageList = $results->getMessages();
                    if($messageList)
                    {
                        foreach($messageList as $message)
                        {
                            $msg = $service->users_messages->get($user, $message->getId());
                            $payload = $msg->getPayload();
                            $headers = $payload->getHeaders();
                            $fromName = '';
                            $fromEmail = '';
                            $toName = '';
                            $toEmail = '';
                            $date = '';
                            $subject = '';
                            $ipAddress = '';
                            $attachments = [];

                            foreach ($headers as $header)
                            {
                                if ($header->getName() == 'From') {
                                    $from = $header->getValue();

                                    if (preg_match('/(.*)<(.*)>/', $from, $matches))
                                    {
                                        $fromName = trim($matches[1]);
                                        $fromEmail = trim($matches[2]);
                                    }
                                    else
                                    {
                                        $fromEmail = $from;
                                        $parseFromEmail = explode("@",$fromEmail);
                                        $fromName = $parseFromEmail[0];
                                    }
                                }
                                if(empty($toEmail) && ($header->getName() == 'To' || $header->getName() == 'Delivered-To'))
                                {
                                    $to = $header->getValue();
                                    if (preg_match('/(.*)<(.*)>/', $to, $matches)) {
                                        $toName = trim($matches[1]);
                                        $toEmail = trim($matches[2]);
                                    }
                                    else
                                    {
                                        $toEmail = $to;
                                        $parseToEmail = explode("@",$toEmail);
                                        $toName = $parseToEmail[0];
                                    }
                                }

                                if($header->getName() == 'Date')
                                    $date = $header->getValue();

                                if($header->getName() == 'Subject')
                                    $subject = $header->getValue();

                                if(empty($ipAddress) && ($header->getName() == 'X-Originating-IP' || $header->getName() == 'Received'))
                                    if (preg_match_all('/\[(\d+\.\d+\.\d+\.\d+)\]/', $header->getValue(), $matches))
                                        $ipAddress = $matches[1][0] ?? '';
                            }

                            $parts = $payload->getParts();
                            foreach ($parts as $part)
                            {
                                if($part->getFilename() && $part->getBody() && $part->getBody()->getAttachmentId())
                                {
                                    $attachmentId = $part->getBody()->getAttachmentId();
                                    $attachment = $service->users_messages_attachments->get($user, $message->getId(), $attachmentId);
                                    $attachmentData = $attachment->getData();
                                    $file_name      = $part->getFilename();
                                    $rand_filename  = strtolower(substr(md5(uniqid(rand())), 0,23));
                                    $ext_arr        = explode(".", $file_name);
                                    $extension      = strtolower(array_pop($ext_arr));
                                    $new_file_name  = $rand_filename.".".$extension;
                                    $file_size      = strlen(base64_decode(strtr($attachmentData, '-_', '+/')));

                                    $attachments[] = [
                                        'file_name' => $file_name,
                                        'name'      => $new_file_name,
                                        'file_ext'  => $extension,
                                        'size'      => $file_size,
                                        'content'   => base64_encode(base64_decode(strtr($attachmentData, '-_', '+/'))),
                                    ];
                                }
                            }


                            $content = $this->getHtmlBody($parts);


                            if(empty($content))
                            {
                                $bodyData = $msg->getPayload()->getBody()->getData();
                                if (!empty($bodyData)) $content = base64_decode(strtr($bodyData, '-_', '+/'));
                            }

                            $message_con = [
                                'ip'          => $ipAddress,
                                'date'        => \DateManager::format("Y-m-d H:i:s", $date),
                                'subject'     => $subject ?: 'Unknown Subject',
                                'spam'        => false,
                                'from'        => [
                                    'name'    => trim($fromName, '"') ?: 'Unknown',
                                    'address' => $fromEmail ?: 'No Email',
                                ],
                                'to'          => [
                                    'name'    => trim($toName, '"') ?: 'Unknown',
                                    'address' => $toEmail ?: 'No Email',
                                ],
                                'message'     => $content ?: 'No Content',
                                'attachments' => $attachments,
                            ];

                            $messages[] = $message_con;

                            $mods = new \Google\Service\Gmail\ModifyMessageRequest();
                            $mods->setRemoveLabelIds(['UNREAD']);
                            $service->users_messages->modify($user, $message->getId(), $mods);

                        }
                    }
                }
                catch (\Exception $e)
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

        private function getHtmlBody($parts) {
            foreach ($parts as $part) {
                $mimeType = $part->getMimeType();
                if ($mimeType == 'text/html') {
                    $body = $part->getBody();
                    $bodyData = $body->getData();
                    return base64_decode(strtr($bodyData, '-_', '+/'));
                } elseif ($mimeType == 'multipart/alternative' || $mimeType == 'multipart/mixed' || $mimeType == 'multipart/related') {
                    $subPartsHtml = $this->getHtmlBody($part->getParts());
                    if ($subPartsHtml) return $subPartsHtml;
                }
            }
            return '';
        }


        public function clear_tokens($did=0)
        {
            $this->save_config([$did => ['tokens' => '']]);

            return [
                'status' => "successful",
            ];
        }

    }