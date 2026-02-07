<?php
    defined('CORE_FOLDER') OR exit('You can not get in here!');
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;

    class MailerPHP
    {
        private $instance;
        public $error=NULL,$lang=[],$config=[],$debug_text='';
        public $credentials;

        function __construct($external_config=[])
        {
            $config         = Modules::Config("Mail",__CLASS__);
            $this->lang     = Modules::Lang("Mail",__CLASS__);
            $this->config   = array_merge($config,$external_config);

            $this->instance = new PHPMailer();
        }

        public function set_credentials($data=[])
        {
            $this->credentials = $data;
            return $this;
        }

        public function testConnect($type,$host,$secure,$port,$from,$username,$password,$fname)
        {
            if($this->instance)
            {
                if($type == "smtp")
                    $this->instance->IsSMTP();
                elseif($type == "mail")
                    $this->instance->IsMail();

                if(!$from) $from = $username;

                $this->instance->SMTPDebug = 3;
                $this->instance->Debugoutput = function($str, $level) {
                    $this->debug_text .= nl2br(mb_convert_encoding($str, "UTF-8", "auto"))."\n";
                };
                $this->instance->From = $from;
                $this->instance->Host = $host;
                $this->instance->Port = $port;
                $this->instance->FromName = $fname;
                $this->instance->SetFrom($from,$fname);
                $this->instance->SMTPSecure = $secure;
                $this->instance->SMTPAutoTLS = false;
                if($username !=''){
                    $this->instance->SMTPAuth = true;
                    $this->instance->Username = $username;
                    $this->instance->Password = $password;
                }

                if($this->instance->smtpConnect()){
                    $this->instance->smtpClose();
                    return true;
                }
                $error_text = $this->instance->ErrorInfo;
                if(!$error_text) $error_text = $this->debug_text;
                $this->error = $error_text;
            }

            if(!$this->error) $this->error = 'Connection Failed';
            return false;
        }


        public function subject($arg=''){
            $this->address_reset();
            if($this->instance) $this->instance->Subject = $arg;
            return $this;
        }

        public function body($text='',$template=false,$variables=[],$lang='',$user=0){
            if($template) {
                $look = View::notifications("mail",$template,$text,$variables,$lang,$user);
                if($look!==false && isset($look["subject"]) && isset($look["content"])){
                    $this->subject($look["subject"]);
                    $text = $look["content"];
                }
            }
            if($this->instance) $this->instance->Body = $text;
            return $this;
        }

        public function setFromEmail($email='')
        {
            $this->config["femail"] = $email;
            return $this;
        }

        public function setFromName($name='')
        {
            $this->config["fname"] = $name;
            return $this;
        }

        public function AddAddress($arg1='',$arg2=''){
            if($this->instance)
            {
                if(is_array($arg1))
                {
                    if(sizeof($arg1) > 1)
                        foreach($arg1 AS $address => $name) $this->instance->addBCC($address,$name);
                    else
                        foreach($arg1 AS $address => $name) $this->instance->addAddress($address,$name);
                }
                else
                    $this->instance->AddAddress($arg1,$arg2);
            }
            return $this;
        }

        public function addAttachment($path='',$name=''){
            if($this->instance) $this->instance->addAttachment($path,$name);
            return $this;
        }

        public function getAddresses(){
            $addresses = $this->instance ? $this->instance->getAllRecipientAddresses() : false;
            return $addresses ? array_keys($addresses) : [];
        }

        public function getSubject(){
            return $this->instance->Subject ?? false;
        }

        public function getBody(){
            return $this->instance->Body ?? false;
        }

        public function address_reset(){
            return $this->instance ? $this->instance->clearAddresses() : false;
        }

        public function submit($isthis=false)
        {
            $config   = $this->credentials ? $this->credentials : $this->config;
            $debug    = false;
            $type     = $config["type"];
            $host     = $config["host"];
            $from     = $config["from"] ?? '';
            $username = $config["username"];
            $password = Crypt::decode($config["password"],Config::get("crypt/user"));
            $port     = $config["port"];
            $secure   = $config["secure"];
            $fromne   = $config["fname"];
            $timeout  = 10;

            if($type == "smtp")
                $this->instance->IsSMTP();
            elseif($type == "mail")
                $this->instance->IsMail();

            if(!$from) $from = $username;

            $this->instance->Timeout  = $timeout;
            $this->instance->CharSet = 'utf-8';
            $this->instance->From = $from;
            $this->instance->Host = $host;
            $this->instance->Port = $port;
            $this->instance->FromName = $fromne;
            $this->instance->SetFrom($from,$fromne);
            $this->instance->WordWrap = 50;
            $this->instance->IsHTML(true);
            if($debug) $this->instance->SMTPDebug  = 1;
            $this->instance->SMTPSecure = $secure;
            $this->instance->SMTPAutoTLS = false;
            if($username !=''){
                $this->instance->SMTPAuth = true;
                $this->instance->Username = $username;
                $this->instance->Password = $password;
            }


            $send = $this->instance->Send();
            if(!$send) $this->error = $this->instance->ErrorInfo;
            return ($isthis) ? $this : $send;
        }
    }