<?php
    namespace WISECP\Modules\Pipe;
    class Pop3
    {
        public $name = "Pop3";
        public $config = [], $lang = [];
        public $test = false;

        public function __construct()
        {
            $this->config           = \Modules::Config("Pipe",$this->name);
            $this->lang             = \Modules::Lang("Pipe",$this->name);
        }

        public function save_config($data=[])
        {
            $data           = array_replace_recursive($this->config ?: [],$data);
            $var_export     = \Utility::array_export($data,['pwith' => true]);
            return \FileManager::file_write(__DIR__.DS."config.php",$var_export);
        }

        public function test_connection($did=0):array
        {
            try {
                $protocol          = \Filter::init("POST/module/".$this->name."/".$did."/protocol") ?: 'pop3';
                $hostname          = \Filter::init("POST/module/".$this->name."/".$did."/hostname") ?: 'na';
                $port              = \Filter::init("POST/module/".$this->name."/".$did."/port") ?: 'na';
                $username          = \Filter::init("POST/module/".$this->name."/".$did."/username") ?: 'na';
                $password          = \Filter::init("POST/module/".$this->name."/".$did."/password") ?: 'na';
                $ssl               = \Filter::init("POST/module/".$this->name."/".$did."/ssl") ?: false;

                if(!stristr($hostname,'{')) $hostname = '{'.$hostname.':'.$port.'/'.$protocol.($ssl ? '/ssl' : '/notls').'}INBOX';

                if(!extension_loaded('imap'))
                    throw new \Exception($this->lang["extension-not-found"]);


                \imap_timeout(IMAP_OPENTIMEOUT, 30);
                \imap_timeout(IMAP_READTIMEOUT, 30);
                \imap_timeout(IMAP_WRITETIMEOUT, 30);

                $inbox = \imap_open($hostname, $username, $password);

                if ($inbox === false) throw new \Exception('Cannot connect to server: ' . (\imap_last_error() ?: 'Timed out in 30 seconds'));

                $numMessages = \imap_num_msg($inbox);
                \imap_close($inbox);
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
            $configs = $this->config;

            if(!\WDB::hasTable("pop3_messages"))
                \WDB::exec("CREATE TABLE `pop3_messages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(20) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `server_email` varchar(255) DEFAULT NULL,
  `from_email` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT '1971-01-01 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            if(\WDB::query("SHOW COLUMNS FROM `pop3_messages` LIKE 'server_email'")->rowCount() == 0)
                \WDB::exec("ALTER TABLE `pop3_messages` ADD `server_email` VARCHAR(255) NULL AFTER `subject`;");

            if(\WDB::query("SHOW COLUMNS FROM `pop3_messages` LIKE 'from_email'")->rowCount() == 0)
                \WDB::exec("ALTER TABLE `pop3_messages` ADD `from_email` VARCHAR(255) NULL AFTER `server_email`;");

            $messages           = [];

            foreach($configs AS $did => $config)
            {
                try
                {
                    $protocol          = $config["protocol"] ?? 'pop3';
                    $hostname          = $config["hostname"] ?? 'localhost';
                    $port              = $config["port"] ?? 995;
                    $username          = $config["username"] ?? 'na';
                    $password          = $config["password"] ?? 'na';
                    $ssl               = $config["ssl"] ?? false;

                    $continue = \Config::get("options/ticket-pipe/mail/".$did."/provider") != $this->name;
                    if(!$continue && (!$hostname || !$username || !$password)) $continue = true;
                    if($continue) continue;

                    if(!stristr($hostname,'{')) $hostname = '{'.$hostname.':'.$port.'/'.$protocol.($ssl ? '/ssl' : '/notls').'}INBOX';

                    \imap_timeout(IMAP_OPENTIMEOUT, 30);
                    \imap_timeout(IMAP_READTIMEOUT, 30);
                    \imap_timeout(IMAP_WRITETIMEOUT, 30);

                    $inbox = \imap_open($hostname, $username, $password);

                    if ($inbox === false) throw new \Exception('Cannot connect to server: ' . (\imap_last_error() ?: 'Timed out in 30 seconds'));

                    if($inbox)
                    {
                        $emails = \imap_search($inbox, 'UNSEEN');
                        if($emails)
                        {
                            rsort($emails);
                            $limit = 0;
                            foreach ($emails as $email_number)
                            {
                                $uid = \imap_uid($inbox, $email_number);

                                if($limit >= 10) break;

                                $header = \imap_headerinfo($inbox, $email_number);

                                $subject    = iconv_mime_decode($header->subject, 0, "UTF-8");
                                $fromName   = isset($header->from[0]->personal) ? iconv_mime_decode($header->from[0]->personal, 0, "UTF-8") : '';
                                $fromEmail  = $header->from[0]->mailbox . '@' . $header->from[0]->host;
                                $toName     = isset($header->to[0]->personal) ? iconv_mime_decode($header->to[0]->personal, 0, "UTF-8") : '';
                                $toEmail    = $header->to[0]->mailbox . '@' . $header->to[0]->host;
                                $date       = \DateManager::format("Y-m-d H:i:s",$header->date);
                                $ipAddress  = isset($header->sender[0]->adl) ? $header->sender[0]->adl : '';


                                $check = \WDB::select("id")->from("pop3_messages")
                                    ->where("server_email","=",$username,"AND")
                                    ->where("created_at","=",$date);
                                $check = $check->build() ? $check->getObject()->id : 0;

                                if($check > 0) continue;


                                $structure = \imap_fetchstructure($inbox, $email_number);

                                $message = $this->get_message_body($inbox, $email_number, $structure, '', true);
                                if (empty($message))
                                    $message = $this->get_message_body($inbox, $email_number, $structure, '', false);



                                $attachments = [];
                                if (isset($structure->parts) && count($structure->parts))
                                {
                                    for ($j = 1; $j < count($structure->parts); $j++)
                                    {
                                        $part = $structure->parts[$j];
                                        if ($part->ifdparameters) {
                                            $filename = $part->dparameters[0]->value;
                                            $fileData = \imap_fetchbody($inbox, $email_number, $j + 1);
                                            if ($part->encoding == 3) { // 3 = BASE64
                                                $fileData = base64_decode($fileData);
                                            } elseif ($part->encoding == 4) { // 4 = QUOTED-PRINTABLE
                                                $fileData = quoted_printable_decode($fileData);
                                            }

                                            $rand_filename  = strtolower(substr(md5(uniqid(rand())), 0,23));
                                            $ext_arr        = explode(".", $filename);
                                            $extension      = strtolower(array_pop($ext_arr));
                                            $new_file_name  = $rand_filename.".".$extension;

                                            $attachments[] = [
                                                'file_name' => $filename,
                                                'name'      => $new_file_name,
                                                'file_ext'  => $extension,
                                                'size'      => strlen($fileData),
                                                'content'   => base64_encode($fileData),
                                            ];
                                        }
                                    }
                                }


                                $messages[] = [
                                    'ip'            => $ipAddress,
                                    'date'          => \DateManager::format("Y-m-d H:i:s",$date),
                                    'subject'       => $subject,
                                    'spam'          => false,
                                    'from'          => [
                                        'name'      => trim($fromName,'"'),
                                        'address'   => $fromEmail,
                                    ],
                                    'to'            => [
                                        'name'      => trim($toName,'"'),
                                        'address'   => $toEmail,
                                    ],
                                    'message'       => $message,
                                    'attachments'   => $attachments,
                                ];
                                $limit++;

                                if(!$this->test)
                                    \WDB::insert("pop3_messages",[
                                        'uid'           => $uid,
                                        'server_email'  => $username,
                                        'from_email'    => $fromEmail,
                                        'subject'       => $subject,
                                        'created_at'    => $date,
                                    ]);

                            }
                        }
                        \imap_close($inbox);
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

        function get_part($connection, $message_number, $part_number, $encoding) {
            $part = \imap_fetchbody($connection, $message_number, $part_number);
            switch ($encoding) {
                case 0: return $part; // 7BIT
                case 1: return $part; // 8BIT
                case 2: return \imap_binary($part); // BINARY
                case 3: return base64_decode($part); // BASE64
                case 4: return quoted_printable_decode($part); // QUOTED-PRINTABLE
                case 5: return $part; // OTHER
                default: return $part;
            }
        }

        function get_message_body($connection, $message_number, $structure, $part_number = '', $prefer_plain_text = true) {
            $body = '';
            if ($structure->type == 0) {
                if (strtolower($structure->subtype) == 'plain') {
                    $body = $this->get_part($connection, $message_number, $part_number ?: 1, $structure->encoding);
                } elseif (strtolower($structure->subtype) == 'html' && !$prefer_plain_text) {
                    $body = $this->get_part($connection, $message_number, $part_number ?: 1, $structure->encoding);
                }
            } elseif ($structure->type == 1) { // Multi-part
                foreach ($structure->parts as $index => $sub_structure) {
                    $prefix = $part_number ? $part_number . '.' : '';
                    $part_body = $this->get_message_body($connection, $message_number, $sub_structure, $prefix . ($index + 1), $prefer_plain_text);
                    if ($prefer_plain_text && strtolower($sub_structure->subtype) == 'plain' && !empty($part_body)) {
                        return $part_body;
                    } elseif (!$prefer_plain_text && strtolower($sub_structure->subtype) == 'html' && !empty($part_body)) {
                        return $part_body;
                    }
                    $body .= $part_body;
                }
            }
            return $body;
        }

    }