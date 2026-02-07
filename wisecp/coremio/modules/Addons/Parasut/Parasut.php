<?php
    class Parasut {
        const BASE_URL          = "https://api.parasut.com";
        const ENDPOINT_URL      = "https://api.parasut.com/v4";
        const AUTHORIZATION_URL = "https://api.parasut.com/oauth/authorize";
        const TOKEN_URL         = "https://api.parasut.com/oauth/token";

        public $error           = false;
        private $http_status    = 0;
        private $http_response_header = [];
        private $http_response_body   = NULL;
        public $config          = [];
        private $authorization  = [];
        private $_query         = "";
        private $_params        = [];
        private $company_id     = 0;
        private $valid_currencies = [];
        private $invoice_total    = 0;
        private $formalize_trying_count = [];
        private $create_invoice_err_count = [];
        private $temp               = [];


        function __construct(){
            $config              = include __DIR__.DS."config.php";
            $authorization       = include __DIR__.DS."authorization.php";
            $this->set_configuration($config);
            if($this->config["status"]) $this->check_authorization_status($authorization);
            Helper::Load(["Money","User","Orders","Invoices","Products","Events","Notification"]);
        }
        public function change_addon_status($arg=''){
            $config = include __DIR__.DS."config.php";
            $status = $arg == "enable";

            if(($status && !isset($config["settings"]["first-active-date"])) || !$config["settings"]["first-active-date"])
                $config["settings"]["first-active-date"] = DateManager::Now("Y-m-d");

            if($status && !Config::get("options/taxation"))
                die(Utility::jencode([
                    'status' => "error",
                    'message' => "Lütfen önce fatura vergilendirme özelliğini aktif ediniz.",
                ]));

            if($status && (!$this->config["settings"]["company_id"] || $this->config["settings"]["client_id"] == '' || $this->config["settings"]["client_secret"] == '' || $this->config["settings"]["username"] == '' || $this->config["settings"]["password"] == '' || !$this->check_authorization_status([],true)) )
                die(Utility::jencode([
                    'status' => "error",
                    'message' => "API Bilgileri Geçersizdir.",
                ]));

            $config["status"] = $status;
            $config         = Utility::array_export($config,['pwith' => true]);
            FileManager::file_write(__DIR__.DS."config.php",$config);
            return true;
        }
        public function main(){

            if(Filter::isPOST()){

                if(DEMO_MODE){
                    Controllers::$init->takeDatas(["language"]);
                    die(Utility::jencode([
                        'status' => "error",
                        'message' => __("website/others/demo-mode-error")
                    ]));
                }

                $operation = Filter::init("POST/module_operation","route");
                if($operation == "save_config") return $this->save_config();
            }

            $this->settings_view();
        }
        private function save_config(){
            $config             = include __DIR__.DS."config.php";
            $formalize_day      = (int) Filter::init("POST/formalize_day","numbers");
            $client_id          = Filter::init("POST/client_id","hclear");
            $client_secret      = Filter::init("POST/client_secret","hclear");
            $callback_urls      = Filter::init("POST/callback_urls","hclear");
            $company_id         = (int) Filter::init("POST/company_id","numbers");
            $username           = Filter::init("POST/username","hclear");
            $password           = Filter::init("POST/password","hclear");
            $prefix             = Filter::init("POST/prefix","hclear");
            $test               = Filter::init("POST/test","numbers");
            $vat_exemption_reason_code = Filter::init("POST/vat_exemption_reason_code","numbers");
            $vat_exemption_reason      = Filter::init("POST/vat_exemption_reason","hclear");
            $account_id         = Filter::init("POST/account_id","hclear");
            $ignore_pmethods    = Filter::init("POST/ignore_pmethods");

            if(!$ignore_pmethods) $ignore_pmethods = [];


            if($test)
            {
                if(Validation::isEmpty($client_id))
                    die(Utility::jencode([
                        'status' => "error",
                        'for' => "input[name='client_id']",
                        'message' => "Lütfen Client ID Yazınız.",
                    ]));

                if(Validation::isEmpty($client_secret))
                    die(Utility::jencode([
                        'status' => "error",
                        'for' => "input[name='client_secret']",
                        'message' => "Lütfen Client Secret Yazınız.",
                    ]));

                if(Validation::isEmpty($callback_urls))
                    die(Utility::jencode([
                        'status' => "error",
                        'for' => "input[name='callback_urls']",
                        'message' => "Lütfen Callback urls Yazınız.",
                    ]));

                if(Validation::isEmpty($company_id))
                    die(Utility::jencode([
                        'status' => "error",
                        'for' => "input[name='company_id']",
                        'message' => "Lütfen Firma ID Yazınız.",
                    ]));

                if(Validation::isEmpty($username))
                    die(Utility::jencode([
                        'status' => "error",
                        'for' => "input[name='username']",
                        'message' => "Lütfen E-Posta Yazınız.",
                    ]));

                if(Validation::isEmpty($password))
                    die(Utility::jencode([
                        'status' => "error",
                        'for' => "input[name='password']",
                        'message' => "Lütfen Parola Yazınız.",
                    ]));
            }

            if(Validation::isEmpty($vat_exemption_reason_code))
                die(Utility::jencode([
                    'status' => "error",
                    'for' => "input[name='vat_exemption_reason_code']",
                    'message' => "Lütfen Kdv Muafiyet Kodu Yazınız.",
                ]));

            if(Validation::isEmpty($vat_exemption_reason))
                die(Utility::jencode([
                    'status' => "error",
                    'for' => "input[name='vat_exemption_reason_code']",
                    'message' => "Lütfen Kdv Muafiyet Açıklaması Yazınız.",
                ]));

            if($formalize_day != 999 && $formalize_day > 7)
                die(Utility::jencode([
                    'status' => "error",
                    'message' => '"Vergi Usul Kanunu" gereğince ürünün/hizmetin tesliminden itibaren azami 7 gün içerisinde fatura kesilebilir.',
                ]));


            if($client_secret == "*****")
                $client_secret = $config["settings"]["client_secret"];
            else
                $client_secret = Crypt::encode($client_secret,Config::get("crypt/system"));

            if($password == "*****")
                $password = $config["settings"]["password"];
            else
                $password = Crypt::encode($password,Config::get("crypt/system"));

            $config["settings"]["client_id"] = $client_id;
            $config["settings"]["client_secret"] = $client_secret;
            $config["settings"]["callback_urls"] = $callback_urls;
            $config["settings"]["company_id"]    = $company_id;
            $config["settings"]["username"]      = $username;
            $config["settings"]["password"]      = $password;
            $config["settings"]["vat_exemption_reason_code"] = $vat_exemption_reason_code;
            $config["settings"]["vat_exemption_reason"]      = $vat_exemption_reason;
            $config["settings"]["prefix"]      = $prefix;
            $config["settings"]["account_id"]  = $account_id;
            $config["settings"]["ignore_pmethods"] = $ignore_pmethods;


            $this->set_configuration($config);

            if($test)
            {
                $testConnect                        = $this->check_authorization_status([],true);
                if(!$testConnect)
                    die(Utility::jencode([
                        'status' => "error",
                        'message' => "Bağlantı Sağlanamadı: ".$this->error,
                    ]));
            }


            if(!$test){
                $cronjobs   = include __DIR__.DS."cronjobs.php";
                $cjb_day    = $cronjobs["tasks"]["invoices-to-be-formalized"]["settings"]["day"];
                if($cjb_day != $formalize_day){
                    $cronjobs["tasks"]["invoices-to-be-formalized"]["settings"]["day"] = $formalize_day;
                    FileManager::file_write(__DIR__.DS."cronjobs.php",Utility::array_export($cronjobs,['pwith' => true]));
                }
                FileManager::file_write(__DIR__.DS."config.php",Utility::array_export($config,['pwith' => true]));
            }

            if($test)
                echo Utility::jencode(['status' => "successful",'message' => "Bağlantı Testi Başarılı."]);
            else
                echo Utility::jencode(['status' => "successful",'message' => "Ayarlar Başarıyla Kaydedildi."]);
        }
        private function settings_view(){
            $config         = $this->config;
            $cronjobs       = include __DIR__.DS."cronjobs.php";
            $request_uri    = Controllers::$init->AdminCRLink("tools-1",["addons"]);


            include  __DIR__.DS."views".DS."settings.php";
        }
        private function set_configuration($config=[]){
            $settings   = $config["settings"];
            $ckey       = Config::get("crypt/system");

            if(isset($settings["company_id"]) && $settings["company_id"])
                $this->company_id = $settings["company_id"];

            if(isset($settings["valid_currencies"]) && $settings["valid_currencies"])
                $this->valid_currencies = $settings["valid_currencies"];

            if($settings["client_secret"])
                $settings["client_secret"] = Crypt::decode($settings["client_secret"],$ckey);

            if($settings["password"])
                $settings["password"] = Crypt::decode($settings["password"],$ckey);

            $config["settings"] = $settings;
            $this->config = $config;
        }
        private function download_remote_file($url, $localPathname){
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

            $data = curl_exec($ch);
            curl_close($ch);

            if ($data) {
                $fp = fopen($localPathname, 'wb');

                if ($fp) {
                    fwrite($fp, $data);
                    fclose($fp);
                } else {
                    fclose($fp);
                    return false;
                }
            } else {
                return false;
            }
            return true;
        }
        private function use_curl($site_url,$post_data='',$opt=[]){
            $ch = curl_init();
            $header = [];

            if(isset($opt["is_json"]) && $opt["is_json"]){
                $header[] = "Content-Type:application/json";
            }

            if($post_data){
                $header[] = 'Content-Length: ' .strlen($post_data);
            }

            if(isset($opt["authorization"]) && $opt["authorization"]){
                $header[] = "Authorization: ".$opt["authorization"];
            }

            $set_options = array(
                CURLOPT_URL            => $site_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER      => $header,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_AUTOREFERER    => false,
                CURLOPT_CONNECTTIMEOUT => 120,
                CURLOPT_TIMEOUT        => 90,
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            );

            if($post_data && isset($opt["method_PUT"]) && $opt["method_PUT"]) $set_options[CURLOPT_CUSTOMREQUEST] = "PUT";
            elseif(isset($opt["method_DELETE"]) && $opt["method_DELETE"]) $set_options[CURLOPT_CUSTOMREQUEST] = "DELETE";
            elseif($post_data) $set_options[CURLOPT_POST] = 1;

            if($post_data) $set_options[CURLOPT_POSTFIELDS] = $post_data;

            curl_setopt_array($ch,$set_options);
            $result = curl_exec($ch);
            $this->http_response_body = $result;
            $this->http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->http_response_header = curl_getinfo($ch);
            if(curl_errno($ch)){
                $this->error = curl_error($ch);
                return false;
            }
            curl_close($ch);



            return $result;
        }
        private function check_authorization_status($authorization=[],$test=false){
            $created_at     = isset($authorization["created_at"]) ? $authorization["created_at"] : 0;
            $expires_in     = isset($authorization["expires_in"]) ? $authorization["expires_in"] : 0;
            $expire_time    = $created_at + $expires_in;
            $now_time       = DateManager::strtotime();
            if($expire_time < $now_time){
                $apply = $this->renew_authorization($authorization);
                if(!$apply) $apply = $this->create_new_authorization();
                $authorization = $apply;
            }
            $this->authorization = $authorization;
            if(!$authorization){
                if(!$this->error) $this->error = "Oturum Açılamadı.";
                if(!$test){
                    $check = Models::$init->db->select("id")->from("events");
                    $check->where("type","=","info","&&");
                    $check->where("owner","=","system","&&");
                    $check->where("name","=","module-addon-error","&&");
                    $check->where("data","LIKE","%".$this->error."%","&&");
                    $check->where("unread","=","0","");
                    $check = $check->build() ? $check->getObject()->id : false;
                    if(!$check) $this->add_critical_log("Yetkilendirme Sağlanamadı. ".$this->error);
                }
            }
            return  $this->authorization ? true : false;
        }
        private function create_new_authorization(){
            $params = http_build_query([
                'client_id' => $this->config["settings"]["client_id"],
                'username' => $this->config["settings"]["username"],
                'password' => $this->config["settings"]["password"],
                'grant_type' => "password",
                'redirect_uri' => $this->config["settings"]["callback_urls"],
            ]);

            $result = $this->use_curl(self::TOKEN_URL,$params);
            if($result){
                $response   = $result;
                $result     = Utility::jdecode($result,true);
                if(!$result){
                    $this->error = "Response could not be resolved. Invalid JSON Format";
                    return false;
                }

                if(isset($result["error"])){
                    $this->error = $result["error_description"];
                    return false;
                }

                $export_data = Utility::array_export($result,['pwith' => true]);
                FileManager::file_write(__DIR__.DS."authorization.php",$export_data);
            }
            return $result;
        }
        private function renew_authorization($authorization=[]){
            if(!isset($authorization["refresh_token"])) return false;

            $params = http_build_query([
                'client_id' => $this->config["settings"]["client_id"],
                'client_secret' => $this->config["settings"]["client_secret"],
                'refresh_token' => $authorization["refresh_token"],
                'grant_type' => "refresh_token",
            ]);

            $result = $this->use_curl(self::TOKEN_URL,$params);
            if($result){
                $result = Utility::jdecode($result,true);
                if(!$result){
                    $this->error = "Response could not be resolved. Invalid JSON Format";
                    return false;
                }

                if(isset($result["error"])){
                    $this->error = $result["error_description"];
                    return false;
                }

                $export_data = Utility::array_export($result,['pwith' => true]);
                FileManager::file_write(__DIR__.DS."authorization.php",$export_data);
            }
            return $result;
        }
        private function add_critical_log($error='',$invoice_id=0){
            Helper::Load(["Events"]);
            return Events::create([
                'type' => "info",
                'owner' => "system",
                'owner_id' => $invoice_id,
                'name'  => "module-addon-error",
                'data'  => [
                    'name' => __CLASS__,
                    'message' => $error,
                ],
            ]);
        }
        private function search_critical_log($error='',$invoice_id=0)
        {
            $check = Models::$init->db->select("id")->from("events");
            $check->where("type","=","info","&&");
            $check->where("owner","=","system","&&");
            if($invoice_id) $check->where("owner_id","=",$invoice_id,"&&");
            $check->where("name","=","module-addon-error","&&");
            if($error) $check->where("JSON_EXTRACT(data,'$.message')","LIKE","%".$error."%","&&");
            $check->where("unread","=","0");
            return $check->build() ? $check->getObject()->id : false;
        }
        private function addParam($key='',$value=''){
            if(isset($this->_params[$key])){
                if(!is_array($this->_params[$key])){
                    $new_array = [];
                    $new_array[] = $this->_params[$key];
                    $new_array[] = $value;
                }else $this->_params[$key][] = $value;
            }else $this->_params[$key] = $value;
            return $this;
        }
        private function setParam($data=[]){
            $this->_params = array_replace_recursive($this->_params,$data);
            return $this;
        }
        private function getParams(){
            return $this->_params;
        }
        private function query($route=''){
            $this->_params = [];
            $this->_query = $route;
            return $this;
        }
        private function build($response_format="JSON",$sender_format='GET'){
            if(!$this->authorization) return false;

            $url        = self::ENDPOINT_URL."/".$this->_query;
            $params     = $this->_params;
            $opt        = ['authorization' => "Bearer ".$this->authorization["access_token"]];
            $post_data  = NULL;
            if($params){
                if(!$sender_format || $sender_format == "GET"){
                    $url .= "?".http_build_query($params);
                }elseif($sender_format == "POST")
                    $post_data = http_build_query($params);
                elseif($sender_format == "JSON"){
                    $opt["is_json"] = true;
                    $post_data = Utility::jencode($params);
                }elseif($sender_format == "PUT/JSON"){
                    $opt["is_json"] = true;
                    $opt["method_PUT"] = true;
                    $post_data = Utility::jencode($params);
                }
            }else{
                if($sender_format == "DELETE")
                    $opt["method_DELETE"] = true;
            }


            $response_x   = $this->use_curl($url,$post_data,$opt);
            $response     = $response_x;

            if($response_format == "JSON"){
                $response = Utility::jdecode($response,true);
                if(!$response){
                    $this->error = "Response could not be resolved. Invalid JSON Format";
                }
            }

            if(is_array($response)){
                if(isset($response["errors"]) && $response["errors"]){
                    $errors = [];
                    foreach($response["errors"] AS $error)
                    {
                        $text = isset($error["title"]) ? $error["title"].': ' : '';
                        $text .= $error["detail"] ?? '';
                        $errors[] = $text;
                    }
                    $this->error = implode(" , ",$errors);
                    $response = false;
                }elseif(isset($response["error"]) && $response["error"]){
                    $this->error = $response["error"];
                    $response = false;
                }
            }

            Modules::save_log("Addons","Parasut",$sender_format."/ ".$url,$post_data,$response_x,$response);
            sleep(4);
            return $response;
        }
        private function get_customer_id($email='',$tax_number=0){

            if(isset($this->temp['get_customer_id'][$email][$tax_number]))
                return $this->temp['get_customer_id'][$email][$tax_number];


            $returnData = 0;

            $result = $this->query($this->company_id."/contacts");
            if($tax_number && $tax_number != "11111111111") $result->addParam("filter[tax_number]",$tax_number);
            else $result->addParam("filter[email]",$email);
            $result = $result->build();

            if(!isset($result['data'])) return 'data-error';

            if($result && isset($result["data"]) && $result["data"]){
                foreach($result["data"] AS $row){
                    if($email && $row["attributes"]["email"] == $email)
                    {
                        $returnData = $row["id"];
                        break;
                    }
                    elseif($tax_number && $tax_number != "11111111111" && $row["attributes"]["tax_number"] == $tax_number)
                    {
                        $returnData =  $row["id"];
                        break;
                    }
                }
            }

            if($returnData) $this->temp['get_customer_id'][$email][$tax_number] = $returnData;

            return $returnData;
        }
        public function create_customer($data=[]){

            if($data["kind"] == "corporate"){
                $tax_number = $data["company_tax_number"];
                $length     = strlen($tax_number);
                if(!($length == 10 || $length == 11)){
                    $tax_number = $data["identity"];
                    if(strlen($tax_number)!=11) $tax_number = "11111111111";
                }
            }else{
                $tax_number = $data["identity"];
                if(strlen($tax_number)!=11) $tax_number = "11111111111";
            }


            $name       = $data["kind"] == "corporate" ? $data["company_name"] : $data["full_name"];
            $separator  = explode(" ",$name);
            if(sizeof($separator)==1) $name = $data["full_name"]." - ".$name;

            $result = $this->query($this->company_id."/contacts");
            $params = [
                'type'              => "contacts",
                'attributes'        => [
                    'email'         => $data["email"],
                    'name'          => $name,
                    'contact_type'  => $data["kind"] == "corporate" ? "company" : "person",
                    'tax_office'    => isset($data["company_tax_office"]) ? $data["company_tax_office"] : NULL,
                    'tax_number'    => $tax_number,
                    'district'      => $data["address"]["counti"],
                    'city'          => $data["address"]["city"],
                    'address'       => $data["address"]["address"],
                    'phone'         => "+".$data["gsm_cc"].$data["gsm"],
                    'is_abroad'     => $data["address"]["country_code"] != "TR",
                    'account_type'  => "customer",
                ],
            ];
            $result->addParam("data",$params);
            $result = $result->build("JSON","JSON");

            if(!$result && stristr($this->error,'Too many requests'))
            {
                sleep(5);
                return $this->create_customer($data);
            }
            if(!$result) return false;

            if(isset($result["data"]["id"])) return $result["data"]["id"];

            return false;
        }
        public function edit_customer($id=0,$data=[]){

            $name       = trim($data["kind"] == "corporate" ? $data["company_name"] : $data["full_name"]);
            $separator  = explode(" ",$name);
            if(sizeof($separator)==1) $name = $data["full_name"]." - ".$name;

            $result = $this->query($this->company_id."/contacts/".$id);
            $params = [
                'type'              => "contacts",
                'attributes'        => [
                    'email'         => $data["email"],
                    'name'          => $name,
                    'contact_type'  => $data["kind"] == "corporate" ? "company" : "person",
                    'tax_office'    => isset($data["company_tax_office"]) ? $data["company_tax_office"] : NULL,
                    'tax_number'    => $data["kind"] == "corporate" ? $data["company_tax_number"] : $data["identity"],
                    'district'      => $data["address"]["counti"],
                    'city'          => $data["address"]["city"],
                    'address'       => $data["address"]["address"],
                    'phone'         => "+".$data["gsm_cc"].$data["gsm"],
                    'is_abroad'     => $data["address"]["country_code"] != "TR",
                    'account_type'  => "customer",
                ],
            ];
            $result->addParam("data",$params);
            $result = $result->build("JSON","PUT/JSON");
            if(!$result) return false;

            if(isset($result["data"]["id"])) return true;

            return false;
        }
        public function get_customer($id=0){

            if(isset($this->temp['get_customer'][$id]))
                return $this->temp['get_customer'][$id];


            $result = $this->query($this->company_id."/contacts/".$id);
            $result = $result->build();

            $returnData =  $result;

            if($returnData) $this->temp['get_customer'][$id] = $returnData;

            return $returnData;
        }
        private function generate_product_code($item=[]){
            if(isset($item["options"]["type"]) && isset($item["options"]["id"]))
                return $item["options"]["type"]."-".$item["options"]["id"];
            elseif(isset($item["user_pid"]) && $item["user_pid"])
            {
                Helper::Load("Orders");
                $order      = Orders::get($item["user_pid"],'type,product_id');
                if($order && $order["product_id"]) return $order["type"]."-".$order["product_id"];
            }
            return "other";
        }
        private function get_product_id($code=''){

            if(isset($this->temp['get_product_id'][$code]))
                return $this->temp['get_product_id'][$code];

            $returnData = 0;

            $result = $this->query($this->company_id."/products");
            $result->addParam("filter[code]",$code);
            $result = $result->build();

            if(isset($result["data"]) && $result["data"])
            {
                foreach($result["data"] AS $row)
                {
                    if($row["attributes"]["code"] == $code)
                    {
                        $returnData = $row["id"];
                        break;
                    }
                }
            }

            if($returnData) $this->temp['get_product_id'][$code] = $returnData;

            return $returnData;
        }
        private function get_product_name($code='',$options=[]){
            if($code == "other") return "Diğer";
            if($code == "addons") return "Ek Hizmetler";
            $split      = explode("-",$code);
            $product = Products::get($split[0],$split[1],Config::get("general/local"));
            if(!$product){
                $this->error = "Ürün WiseCP sisteminde kayıtlı değildir.";
                return false;
            }
            if($split[0] == "domain") return $product["name"]." Alan Adı";
            else return $product["title"];
        }
        private function create_product($code,$data=[]){
            $product_name = $this->get_product_name($code,isset($data["options"]) ? $data["options"] : []);
            if(!$product_name) return false;
            $result = $this->query($this->company_id."/products");
            $params = [
                'type'              => "products",
                'attributes'        => [
                    'code'          => $code,
                    'name'          => $product_name,
                    'inventory_tracking' => false,
                ],
            ];
            $result->addParam("data",$params);
            $result = $result->build("JSON","JSON");
            if(!$result) return false;

            if(isset($result["data"]["id"])) return $result["data"]["id"];

            return false;
        }
        private function generate_currency_code($id=0){
            if($id == 147) return "TRL";
            elseif($id == 4) return "USD";
            elseif($id == 5) return "EUR";
            elseif($id == 27) return "GBP";
            return "TRL";
        }
        public function get_sales_invoice($id=0,$include=''){

            if(isset($this->temp['get_sales_invoice'][$id][$include]))
                return $this->temp['get_sales_invoice'][$id][$include];

            $returnData = false;

            $result = $this->query($this->company_id."/sales_invoices/".$id);
            if($include) $result->addParam("include",$include);
            $result = $result->build();

            if($result && isset($result["data"]) && $result["data"]) $returnData = $result["data"];

            if($returnData) $this->temp['get_sales_invoice'][$id][$include] = $returnData;

            return $returnData;
        }
        public function get_sales_invoice_with_invoice_id($id=0,$include=''){

            if(isset($this->temp['get_sales_invoice_with_invoice_id'][$id][$include]))
                return $this->temp['get_sales_invoice_with_invoice_id'][$id][$include];

            $returnData = false;
            $result = $this->query($this->company_id."/sales_invoices");

            $result->addParam("filter[invoice_id]",$id);

            if($include) $result->addParam("include",$include);
            $result = $result->build();

            if($result)
            {
                if(isset($result["data"]) && $result["data"])
                {
                    foreach($result["data"] AS $row)
                    {
                        $returnData = $row;
                        break;
                    }
                }
            }

            if($returnData) $this->temp['get_sales_invoice_with_invoice_id'][$id][$include] = $returnData;
            return $returnData;
        }
        public function show_sales_invoice_with_id($id=0,$include=''){

            if(isset($this->temp['show_sales_invoice_with_id'][$id][$include]))
                return $this->temp['show_sales_invoice_with_id'][$id][$include];

            $returnData = false;

            $result = $this->query($this->company_id."/sales_invoices/".$id);
            $result->addParam("include",$include ? $include : "active_e_document");

            $result = $result->build();

            if(isset($result["data"]) && $result["data"]) $returnData = $result;

            if($returnData) $this->temp['show_sales_invoice_with_id'][$id][$include] = $returnData;

            return $returnData;
        }
        public function create_sales_invoice($data=[],$items=[])
        {
            if(!in_array($data["currency"],$this->valid_currencies)){
                $this->error = "Faturanın para birimi paraşüt tarafından desteklenmiyor.";
                return false;
            }

            $user_data              = $data["user_data"];
            $invoice_type           = "estimate";
            $issue_date             = DateManager::format("Y-m-d",$data["cdate"]);

            if($data["status"] == "paid") $invoice_type = "invoice";
            elseif($data["status"] == "cancelled") $invoice_type = "cancelled";
            elseif($data["status"] == "refund") $invoice_type = "refund";

            if($data["status"] == "paid") $issue_date = DateManager::format("Y-m-d",$data["datepaid"]);
            elseif($data["status"] == "refund") $issue_date = DateManager::format("Y-m-d",$data["refunddate"]);

            $tax_number = "11111111111";

            if($user_data["address"]["country_code"] == "TR"){
                if($user_data["kind"] == "corporate"){
                    $tax_number = $user_data["company_tax_number"];
                    $length     = strlen($tax_number);
                    if(!($length == 10 || $length == 11)){
                        $tax_number = $data["identity"];
                        if(strlen($tax_number)!=11) $tax_number = "11111111111";
                    }
                }else{
                    $tax_number = $user_data["identity"];
                    if(strlen($tax_number)!=11) $tax_number = "11111111111";
                }
            }

            $customer_id     = $this->get_customer_id($user_data["email"],$tax_number);

            if(is_string($customer_id) && $customer_id == "data-error")
            {
                $this->error = "#".$user_data["id"]." numaralı müşteri, paraşütte aranırken bir bağlantı sorunu yaşandı, lütfen resmileştirme işlemini tekrar deneyiniz. ".$this->error;
                return false;
            }

            if(!$customer_id) $customer_id = $this->create_customer($user_data);


            $customer       = $this->get_customer($customer_id);

            if(!$customer){
                $this->error = "#".$user_data["id"]." numaralı müşteri paraşüte ekenirken bir bağlantı sorunu yaşandı, lütfen resmileştirme işlemini tekrar deneyiniz. ".$this->error;
                return false;
            }

            $name       = trim($user_data["kind"] == "corporate" ? $user_data["company_name"] : $user_data["full_name"]);
            $separator  = explode(" ",$name);
            if(sizeof($separator)==1) $name = $user_data["full_name"]." - ".$name;


            $edit_customer = [];

            if($customer["data"]["attributes"]["city"] != $user_data["address"]["city"])
                $edit_customer[] = "city";
            elseif($customer["data"]["attributes"]["district"] != $user_data["address"]["counti"])
                $edit_customer[] = "district";
            elseif($customer["data"]["attributes"]["address"] != $user_data["address"]["address"])
                $edit_customer[] = "address";
            elseif($customer["data"]["attributes"]["phone"] != "+".$user_data["gsm_cc"].$user_data["gsm"])
                $edit_customer[] = "phone";
            elseif($customer["data"]["attributes"]["tax_number"] != $tax_number)
                $edit_customer[] = "tax_number";
            elseif($customer["data"]["attributes"]["tax_office"] != $user_data["company_tax_office"])
                $edit_customer[] = "tax_office";
            elseif($customer["data"]["attributes"]["email"] != $user_data["email"])
                $edit_customer[] = "email";
            elseif($customer["data"]["attributes"]["name"] !== $name)
                $edit_customer[] = "change name";

            if($edit_customer){
                $edit = $this->edit_customer($customer_id,$user_data);
                if(!$edit) return false;
            }

            $relationships = [
                'details' => [
                    'data' => [],
                ],
                'contact' => [
                    'data' => [
                        'id' => $customer_id,
                        'type' => "contacts",
                    ],
                ],
            ];

            if($data["sendbta"])
                $items[] = [
                    'owner_id'      => $data["id"],
                    'user_id'       => $data["user_id"],
                    'user_pid'      => 0,
                    'options'       => [],
                    'description'   => "Adrese Fatura Gönderimi",
                    'quantity'      => 1,
                    'amount'        => $data["sendbta_amount"],
                    'total_amount'  => number_format($data["sendbta_amount"],2,'.',''),
                    'currency'      => $data["currency"],
                ];

            $pmethod_commission = round($data["pmethod_commission"],2);

            if($pmethod_commission > 0.00){
                $com_rate = "";
                if($data["pmethod_commission_rate"]>0) $com_rate = " (%".$data["pmethod_commission_rate"].")";
                $items[] = [
                    'owner_id'      => $data["id"],
                    'user_id'       => $data["user_id"],
                    'user_pid'      => 0,
                    'options'       => [],
                    'description'   => "Ödeme Komisyonu".$com_rate,
                    'quantity'      => 1,
                    'amount'        => number_format($data["pmethod_commission"],2,'.',''),
                    'total_amount'  => number_format($data["pmethod_commission"],2,'.',''),
                    'currency'      => $data["currency"],
                ];
            }

            $this->invoice_total = 0;

            foreach($items AS $item){
                $pcode = $this->generate_product_code($item);
                $pid   = $this->get_product_id($pcode);
                $ex     = explode("-",$pcode);

                if(in_array($ex[0],['domain','hosting','server','special','sms','software']))
                {
                    if(!Products::get($ex[0],$ex[1]))
                    {
                        $pcode = "other";
                        $pid   = $this->get_product_id($pcode);
                    }
                }

                if(!$pid) $pid = $this->create_product($pcode,$item);
                if(!$pid) return false;

                if($item["amount"] < 0.01) continue;

                $this->invoice_total += $item["amount"];
                $relationships["details"]["data"][] = [
                    'type' => "sales_invoice_details",
                    'attributes' => [
                        'description'   => Utility::substr($item["description"],0,255),
                        'quantity'      => $item["quantity"],
                        'unit_price'    => number_format($item["total_amount"],4,'.',''),
                        'vat_rate'      => isset($item["taxexempt"]) && $item["taxexempt"] ? 0 : $data["taxrate"],
                    ],
                    'relationships' => [
                        'product' => [
                            'data' => [
                                'id' => $pid,
                                'type' => "products",
                            ],
                        ],
                    ],
                ];
            }

            $total_discount_amount = 0;
            if($data["discounts"]){
                $discounts = $data["discounts"] ? $data["discounts"] : [];
                if($discounts){
                    $items  = $discounts["items"];
                    if(isset($items["coupon"]) && $items["coupon"])
                        foreach($items["coupon"] AS $item) $total_discount_amount += $item["amountd"];

                    if(isset($items["promotions"]) && $items["promotions"])
                        foreach($items["promotions"] AS $item) $total_discount_amount += $item["amountd"];

                    if(isset($items["dealership"]) && $items["dealership"])
                        foreach($items["dealership"] AS $item) $total_discount_amount += $item["amountd"];
                }
            }

            $exchange_rate = '';
            if($data["currency"] != 147){
                if(isset($data["data"]["exchange_rate"]))
                    $exchange_rate = $data["data"]["exchange_rate"];
                else{
                    $curr           = Money::Currency($data["currency"]);
                    $rate           = (float) $curr["rate"];
                    $exchange_rate  = (1 / $rate);
                }
            }
            if(strlen($exchange_rate) > 0) $exchange_rate = (string) round($exchange_rate,2);


            $account_id = $this->get_account_id();

            $inv_prefix         = $this->config["settings"]["prefix"] ?? '';
            $invoice_desc       = "Fatura ".$inv_prefix."#".$data["id"];


            $result = $this->query($this->company_id."/sales_invoices");
            $params = [
                'type'                  => "sales_invoices",
                'attributes'            => [
                    'item_type'         => $invoice_type,
                    'description'       => $invoice_desc,
                    'issue_date'        => $issue_date,
                    'due_date'          => DateManager::format("Y-m-d",$data["duedate"]),
                    'currency'          => $this->generate_currency_code($data["currency"]),
                    'exchange_rate'     => $exchange_rate,
                    'billing_address'   => $user_data["address"]["address"],
                    'billing_phone'     => "+".$user_data["gsm_cc"].$user_data["gsm"],
                    'tax_office'        => $user_data["company_tax_office"],
                    'tax_number'        => $tax_number,
                    'city'              => $user_data["address"]["city"],
                    'district'          => $user_data["address"]["counti"],
                    'is_abroad'         => $user_data["address"]["country_code"] != "TR",
                    'shipment_included' => false,
                    'cash_sale'         => true,
                    'payment_account_id' => $account_id,
                    'payment_date'       => substr($data["datepaid"],0,4) == "1881" ? DateManager::Now("Y-m-d") : DateManager::format("Y-m-d",$data["datepaid"]),
                ],
                'relationships'         => $relationships,
            ];

            if($total_discount_amount){
                $this->invoice_total -= $total_discount_amount;
                $params["attributes"]["invoice_discount_type"] = "amount";
                $params["attributes"]["invoice_discount"] = $total_discount_amount;
            }

            $invoice_total_tax      = Money::get_tax_amount($this->invoice_total,$data["taxrate"]);
            $this->invoice_total    += $invoice_total_tax;
            $this->invoice_total    = round($this->invoice_total,2);

            $result->addParam("data",$params);

            $result = $result->build("JSON","JSON");
            if(!$result) return false;

            if(isset($result["data"]))
            {
                $result = $result["data"];

                $data["data"]["parasut_id"] = $result["id"] ?? 0;
                Invoices::set($data["id"],['data' => Utility::jencode($data["data"])]);

                return $result;
            }


            return false;
        }
        public function delete_transactions($id=0){
            $result = $this->query($this->company_id."/transactions/".$id);
            $result = $result->build("NONE","DELETE");

            if($this->error && !$result) return false;
            if($this->http_status == 204) return true;

            return false;
        }
        public function cancel_sales_invoice($id=0){
            $result = $this->query($this->company_id."/sales_invoices/".$id."/cancel");
            $result = $result->build("JSON","DELETE");

            if(isset($result["data"]["id"])) return true;

            return false;
        }
        public function delete_sales_invoice($id=0){
            $result = $this->query($this->company_id."/sales_invoices/".$id);
            $result = $result->build("NONE","DELETE");
            if($this->error && !$result) return false;
            if($this->http_status == 204) return true;
            return false;
        }
        public function get_account_id(){
            $config = include __DIR__.DS."config.php";

            if(isset($config["settings"]["account_id"]) && $config["settings"]["account_id"])
                return $config["settings"]["account_id"];

            $result = $this->query($this->company_id."/accounts");
            $result->addParam("page[size]","1");
            $result = $result->build();
            if(!$result) return false;

            $account_id = 0;

            if(isset($result["data"]) && $result["data"]) foreach($result["data"] AS $row) $account_id = $row["id"];
            if(!$account_id) return 0;



            $config["settings"]["account_id"] = $account_id;
            $config         = Utility::array_export($config,['pwith' => true]);
            FileManager::file_write(__DIR__.DS."config.php",$config);

            return $account_id;
        }
        public function e_invoice_inboxes($invoice=[]){
            $user_data  = $invoice["user_data"];
            if($user_data["kind"] == "corporate"){
                $vkn        = $user_data["company_tax_number"];
                $length     = strlen($vkn);
                if(!($length == 10 || $length == 11)){
                    $vkn = $user_data["identity"];
                    if(strlen($vkn)!=11) $vkn = "11111111111";
                }
            }else{
                $vkn = isset($user_data["identity"]) ? $user_data["identity"] : 0;
                if(strlen($vkn)!=11) $vkn = "11111111111";
            }

            $identity = isset($user_data["identity"]) ? $user_data["identity"] : 0;
            if(strlen($identity)!=11) $identity = "11111111111";

            $result = $this->query($this->company_id."/e_invoice_inboxes");
            $result->addParam("filter[vkn]",$vkn);
            $result->addParam("page[size]","1");
            $result = $result->build();

            if(isset($result["data"]) && $result["data"])
            {
                $result = $result["data"];
                Utility::sksort($result);
                return $result;
            }
            return false;
        }
        private function invoice_payment_type($type=''){
            if($type == "Banka Havale/EFT")
                $result = "EFT/HAVALE";
            elseif($type == "Kredi Kartı" || $type == "Kredi Kartı (PAYPAL)")
                $result = "KREDIKARTI/BANKAKARTI";
            elseif($type == "BankTransfer")
                $result = "EFT/HAVALE";
            else
                $result = "ODEMEARACISI";

            return $result;
        }
        public function create_e_invoice($pt_invoice_id,$e_invoice,$invoice=[]){

            $payment_type       = $this->invoice_payment_type($invoice["pmethod"]);
            if($payment_type == "ODEMEARACISI") $payment_platform = $invoice["pmethod"];
            else $payment_platform = "Diğer";

            $result = $this->query($this->company_id."/e_invoices");
            $params = [
                'type'              => "e_invoices",
                'attributes'        => [
                    'vat_exemption_reason_code' => $invoice["tax"] > 0.00 ? NULL : $this->config["settings"]["vat_exemption_reason_code"],
                    'vat_exemption_reason' => $invoice["tax"] > 0.00 ? NULL : $this->config["settings"]["vat_exemption_reason"],
                    'note'          => "ID: #".$invoice["id"],
                    'scenario'      => "commercial",
                    'to'            => $e_invoice["attributes"]["e_invoice_address"],
                    'internet_sale' => [
                        'url'               => APP_URI,
                        'payment_type'      => $payment_type,
                        'payment_platform'  => $payment_platform,
                        'payment_date'      => DateManager::format("Y-m-d",$invoice["datepaid"]),
                    ],
                ],
                'relationships'     => [
                    'invoice' => [
                        'data' => [
                            'id' => $pt_invoice_id,
                            'type' => "sales_invoices",
                        ],
                    ],
                ],
            ];
            $result->addParam("data",$params);
            $result = $result->build("JSON","JSON");
            if(!$result) return false;

            if(isset($result["data"]["id"])) return $result["data"]["id"];

            return false;
        }
        public function create_e_archive($pt_invoice_id,$invoice=[]){
            $payment_type       = $this->invoice_payment_type($invoice["pmethod"]);
            if($payment_type == "ODEMEARACISI") $payment_platform = $invoice["pmethod"];
            else $payment_platform = "Diğer";

            $result = $this->query($this->company_id."/e_archives");
            $params = [
                'type'              => "e_archives",
                'attributes'        => [
                    'vat_exemption_reason_code' => $invoice["tax"]>0 ? NULL : $this->config["settings"]["vat_exemption_reason_code"],
                    'vat_exemption_reason' => $invoice["tax"]>0 ? NULL : $this->config["settings"]["vat_exemption_reason"],
                    'internet_sale' => [
                        'url'               => APP_URI,
                        'payment_type'      => $payment_type,
                        'payment_platform'  => $payment_platform,
                        'payment_date'      => DateManager::format("Y-m-d",$invoice["datepaid"]),
                    ],
                    'note'                  => "ID: #".$invoice["id"],
                ],
                'relationships'     => [
                    'sales_invoice' => [
                        'data' => [
                            'id' => $pt_invoice_id,
                            'type' => "sales_invoices",
                        ],
                    ],
                ],
            ];
            $result->addParam("data",$params);

            $result = $result->build("JSON","JSON");
            if(!$result) return false;

            if(isset($result["data"]["id"])) return $result["data"]["id"];

            return false;
        }
        public function pay_sales_invoice($invoice=[],$pt_invoice=[]){
            $pt_invoice_id = $pt_invoice["id"];

            $account_id = $this->get_account_id();

            if(!$account_id){
                $this->error = "Paraşüt hesabına ait banka/kasa hesabı bulunamadı.";
                return false;
            }

            $result = $this->query($this->company_id."/sales_invoices/".$pt_invoice_id."/payments");
            $params = [
                'type'                  => "payments",
                'attributes'            => [
                    'account_id'        => $account_id,
                    'date'              => DateManager::format("Y-m-d",$invoice["datepaid"]),
                    'amount'            => $pt_invoice["attributes"]["net_total"],
                    'exchange_rate'     => '1.0',
                ],
            ];

            $result->addParam("data",$params);

            $result = $result->build("JSON","JSON");
            if(!$result) return false;

            if(isset($result["data"]["id"])) return $result["data"]["id"];
            return false;
        }
        public function trackablejobs($id=''){
            $result = $this->query($this->company_id."/trackable_jobs/".$id);
            $result = $result->build("JSON");

            if(!$result) return false;

            if(isset($result["data"]["attributes"]["status"])){
                $status = $result["data"]["attributes"]["status"];
                if($status == "error") $this->error = implode(", ",$result["data"]["attributes"]["errors"]);
                return $status;
            }
            return false;
        }
        public function get_e_pdf_link($id='',$type=''){
            $result = $this->query($this->company_id."/".$type."/".$id."/pdf");
            $result = $result->build("JSON");

            if(!$result) return false;

            if(isset($result["data"]["attributes"]["url"])) return $result["data"]["attributes"]["url"];

            return false;
        }
        public function formalize($pt_invoice_id=0,$invoice=[]){
            $e_invoice_inboxes    = $this->e_invoice_inboxes($invoice);
            $create_e             = false;
            $create_e_done        = false;

            if($e_invoice_inboxes)
            {
                foreach($e_invoice_inboxes AS $e_invoice_inbox)
                {
                    $create_e           = $this->create_e_invoice($pt_invoice_id,$e_invoice_inbox,$invoice);

                    if($create_e)
                    {
                        sleep(3);
                        $trackable_status = $this->trackablejobs($create_e);
                        if($trackable_status == "error")
                            $create_e = false;
                        else
                        {
                            $this->error = NULL;
                            if($trackable_status == "done") $create_e_done  = true;
                            break;
                        }
                    }
                }
            }
            else
                $create_e           = $this->create_e_archive($pt_invoice_id,$invoice);


            if($create_e)
                Events::create([
                    'type'      => "operation",
                    'owner'     => "invoice",
                    'owner_id'  => $invoice["id"],
                    'name'      => "trackable_jobs",
                    'status'    => $create_e_done ? "approved" : "pending",
                    'data'      => [
                        'pt_invoice_id' => $pt_invoice_id,
                        'trackable_jobs_id'  => $create_e,
                    ],
                ]);

            return $create_e;
        }
        public function paidInvoice($invoice=[])
        {
            $cron       = include __DIR__.DS."cronjobs.php";
            $day        = is_array($cron) ? ($cron["tasks"]["invoices-to-be-formalized"]["settings"]["day"] ?? 1) : 1;
            $is_manuel  = strlen($invoice["taxed_file"]) > 2 || $day >= 999;
            if(!$is_manuel) if($invoice["taxed"]) Invoices::set($invoice["id"],["taxed" => 0]);
            return 'successful';
        }
        public function formalizeInvoice($invoice=[]){
            if(!$this->authorization) return ['error' => $this->error];
            if($invoice && !is_array($invoice)) $invoice = Invoices::get($invoice);

            $ignore_pmethods = $this->config["settings"]["ignore_pmethods"] ?? [];
            if($ignore_pmethods && in_array($invoice["pmethod"],$ignore_pmethods)) return "successful";
            if($invoice["taxed_file"]) return "successful";

            $items      = Invoices::get_items($invoice["id"]);

            $pt_id      = $invoice["data"]["parasut_id"] ?? 0;
            $already    = false;

            if($pt_id > 0)
            {
                $sales_invoice = $this->get_sales_invoice($pt_id);
                if($sales_invoice) $already = $sales_invoice;
                elseif($this->error && (stristr($this->error,'Too many requests') || stristr($this->error,'authorize')))
                {
                    $this->error = "Paraşüt fatura numarası sorgulanamadı, lütfen tekrar deneyiniz.";
                    return ['error' => $this->error];
                }
            }


            if($this->search_critical_log("fatura paraşüte eklenemedi",$invoice["id"]))
            {
                $this->error = "fatura paraşüte eklenemedi";
                return ['error' => $this->error];
            }
            else
            {
                if($already) $create = $already;
                else
                {
                    $create     = $this->create_sales_invoice($invoice,$items);
                    if(!$create){
                        $trying_count = $this->create_invoice_err_count[$invoice["id"]] ?? 0;

                        if((stristr($this->error,'Too many requests') || stristr($this->error,'authorize') )  && !($trying_count >= 3))
                        {
                            sleep(5);
                            $trying_count++;
                            $this->create_invoice_err_count[$invoice["id"]] = $trying_count;
                            return $this->formalizeInvoice($invoice);
                        }
                        $this->add_critical_log("#".$invoice["id"]." numaralı fatura paraşüte eklenemedi. ".$this->error,$invoice["id"]);
                        Invoices::set($invoice["id"],['taxed' => "0"]);
                        return ['error' => $this->error];
                    }
                }
            }

            if($invoice["status"] == "paid") if($res = $this->formalize_trying($create,$invoice,$already ? 'formalize' : 'pay')) return $res;

            return "successful";
        }

        private function formalize_trying($create=[],$invoice=[],$reason='pay')
        {
            if($reason == 'pay')
            {
                if($this->search_critical_log("tahsilat işlemi yapılamadı",$invoice["id"]))
                {
                    $this->error = "tahsilat işlemi yapılamadı.";
                    return ['error' => $this->error];
                }
                else
                {
                    $pay        = $this->pay_sales_invoice($invoice,$create);
                    if(!$pay && !stristr($this->error,'Bad Request: data->attributes->amount')){
                        if(stristr($this->error,'Too many requests'))
                        {
                            sleep(5);
                            return $this->formalize_trying($create,$invoice,'pay');
                        }
                        $this->add_critical_log("#".$invoice["id"]." numaralı fatura paraşüte eklendi. Fakat tahsilat işlemi yapılamadı. ".$this->error,$invoice["id"]);
                        Invoices::set($invoice["id"],['taxed' => 0]);
                        return ['error' => $this->error];
                    }
                }
                $this->error = NULL;
            }

            if($this->search_critical_log("Fakat Resmileştirilemedi",$invoice["id"]))
            {
                $this->error = "Resmileştirilemedi";
                return ['error' => $this->error];
            }
            else
            {
                $formalize          = $this->formalize($create["id"],$invoice);
                if(!$formalize){
                    $trying_count = $this->formalize_trying_count[$create["id"]] ?? 0;

                    if(stristr($this->error,'Too many requests') && !($trying_count >= 3))
                    {
                        sleep(5);
                        $trying_count++;
                        $this->formalize_trying_count[$create["id"]] = $trying_count;
                        return $this->formalize_trying($create,$invoice,'formalize');
                    }
                    elseif(stristr($this->error,'Fatura daha önce'))
                    {
                        Invoices::set($invoice["id"],['taxed' => 1]);
                        return true;
                    }
                    $this->add_critical_log("#".$invoice["id"]." numaralı fatura paraşüte eklendi. Fakat Resmileştirilemedi. ".$this->error,$invoice["id"]);
                    Invoices::set($invoice["id"],['taxed' => 0]);
                    return ['error' => $this->error];
                }
            }

            return false;
        }
        public function refundInvoice($invoice=[]){
            if(!$this->authorization) return ['error' => $this->error];
            if($invoice && !is_array($invoice)) $invoice = Invoices::get($invoice);

            $inv_id         = $invoice["id"] ?? false;

            if(!is_int($inv_id)) $inv_id = false;

            if(!$inv_id) return "successful";

            $get_invoice    = $this->get_sales_invoice_with_invoice_id($inv_id,"payments,payments.transaction");
            if(!$get_invoice) return "successful";

            $pt_id           = $get_invoice["id"];
            $show_invoice    = $this->show_sales_invoice_with_id($pt_id,"payments.transaction");
            if(isset($show_invoice["included"][0]["relationships"]["transaction"]["data"]["id"])){
                $delete = $this->delete_transactions($show_invoice["included"][0]["relationships"]["transaction"]["data"]["id"]);
                if(!$delete){
                    $this->add_critical_log("#".$inv_id." numaralı fatura silinemedi. Tahsilat Silinemiyor. ".$this->error,$inv_id);
                    return ['error' => $this->error];
                }
            }

            if(!$this->cancel_sales_invoice($get_invoice["id"])){
                $this->add_critical_log("#".$inv_id." numaralı fatura iptal edilemedi. ".$this->error,$inv_id);
                return ['error' => $this->error];
            }


            return "successful";
        }
        public function cancelInvoice($invoice=[]){
            if(!$this->authorization) return ['error' => $this->error];
            if($invoice && !is_array($invoice)) $invoice = Invoices::get($invoice);
            if(!$invoice["taxed"]) return "successful";

            $get_invoice    = $this->get_sales_invoice_with_invoice_id($invoice["id"],"payments,payments.transaction");
            if(!$get_invoice) return "successful";

            $pt_id           = $get_invoice["id"];
            $show_invoice    = $this->show_sales_invoice_with_id($pt_id,"payments.transaction");
            if(isset($show_invoice["included"][0]["relationships"]["transaction"]["data"]["id"])){
                $delete = $this->delete_transactions($show_invoice["included"][0]["relationships"]["transaction"]["data"]["id"]);

                if(!$delete){
                    if(stristr($this->error,'Too many reques')) return "successful";
                    $this->add_critical_log("#".$invoice["id"]." numaralı fatura silinemedi. Tahsilat Silinemiyor. ".$this->error,$invoice["id"]);

                    return ['error' => $this->error];
                }
            }

            if(!$this->cancel_sales_invoice($get_invoice["id"])){
                if(stristr($this->error,'Too many reques')) return "successful";
                $this->add_critical_log("#".$invoice["id"]." numaralı fatura iptal edilemedi. ".$this->error,$invoice["id"]);
                return ['error' => $this->error];
            }

            return "successful";
        }
        public function deleteInvoice($invoice=[]){
            if(!$this->authorization) return ['error' => $this->error];
            if($invoice && !is_array($invoice)) $invoice = Invoices::get($invoice);
            if(!$invoice["taxed"]) return "successful";

            $get_invoice    = $this->get_sales_invoice_with_invoice_id($invoice["id"]);
            if(!$get_invoice) return "successful";

            $pt_id           = $get_invoice["id"];
            $show_invoice    = $this->show_sales_invoice_with_id($pt_id,"payments.transaction");
            if(isset($show_invoice["included"][0]["relationships"]["transaction"]["data"]["id"])){
                $delete = $this->delete_transactions($show_invoice["included"][0]["relationships"]["transaction"]["data"]["id"]);
                if(!$delete){
                    if(stristr($this->error,'Too many reques')) return "successful";
                    $this->add_critical_log("#".$invoice["id"]." numaralı fatura silinemedi. Tahsilat Silinemiyor. ".$this->error,$invoice["id"]);
                    return ['error' => $this->error];
                }
            }


            if(!$this->delete_sales_invoice($get_invoice["id"])){
                if(stristr($this->error,'Too many reques')) return "successful";
                $this->add_critical_log("#".$invoice["id"]." numaralı fatura paraşütten silinemedi. ".$this->error,$invoice["id"]);
                return ['error' => $this->error];
            }


            return "successful";
        }
        public function cronjobs()
        {
            if(!$this->config["status"]) return false;
            if(!$this->authorization) return ['error' => $this->error];
            $cronjobs   = include __DIR__.DS."cronjobs.php";
            $now        = DateManager::strtotime();

            $tasks      = $cronjobs["tasks"];
            if($tasks){
                $sets = [];
                foreach($tasks AS $key=>$task){
                    if($task["next-run-time"] >= $now) continue;
                    $method_name = str_replace("-","_",$key);
                    if(method_exists($this,$method_name)){
                        $delay_period           = $task["delay"] ?? 10;
                        $running_log_f          = __DIR__.DS."cronjobs.json";
                        $running_log            = FileManager::file_read($running_log_f);
                        $running_log            = $running_log ? Utility::jdecode($running_log,true) : [];
                        if(isset($running_log[$key]) && $running_log[$key])
                        {
                            $expiry_date            = $running_log[$key];
                            $expiry_date            = DateManager::strtotime($expiry_date);
                            if($expiry_date > DateManager::strtotime())
                            {
                                //echo $key." is running".EOL;
                                continue;
                            }
                        }
                        $running_log[$key] = DateManager::next_date(['minute' => $delay_period]);
                        FileManager::file_write($running_log_f,Utility::jencode($running_log));
                        $settings   = isset($task["settings"]) ? $task["settings"] : [];
                        $this->$method_name($settings);
                        $sets["tasks"][$key]["last-run-time"] = $now;
                        if($task["period"] != "none"){
                            $sets["tasks"][$key]["next-run-time"] = DateManager::strtotime(DateManager::next_date([$task["period"] => $task["time"]]));
                        }
                        unset($running_log[$key]);
                        FileManager::file_write($running_log_f,Utility::jencode($running_log));
                    }
                }
                $sets["last-run-time"] = $now;
                if($sets){
                    $result     = array_replace_recursive($cronjobs,$sets);
                    $export     = Utility::array_export($result,['pwith' => true]);
                    FileManager::file_write(__DIR__.DS."cronjobs.php",$export);
                }
            }
        }
        private function trackable_checking($settings=[]){

            $processing = Events::isCreated('processing','parasut-cronjob',0,'trackable_checking',false,0,true);
            if($processing)
            {
                $p_data = Utility::jdecode($processing["data"],true);
                if($p_data && $processing["status"] == "pending")
                {
                    $wait_time = DateManager::strtotime(DateManager::next_date([
                        $processing["cdate"],
                        'minute' => 5
                    ]));
                    if(DateManager::strtotime() > $wait_time)
                    {
                        Events::set($processing["id"],['status' => "approved"]);
                    }
                    else
                        return false;
                }
            }

            $ev_data = [
                'type'      => "processing",
                'owner'     => "parasut-cronjob",
                'name'      => "trackable_checking",
                'status'    => "pending",
                'cdate'     => DateManager::Now(),
            ];

            if($processing) Events::set($processing["id"],$ev_data);
            else
            {
                $processing_id  = Events::create($ev_data);
                $processing     = Events::get($processing_id);
            }

            $records = Events::getList("operation","invoice",0,"trackable_jobs","pending",0,'id DESC');
            if($records){
                foreach($records AS $row){
                    $row["data"]        = Utility::jdecode($row["data"],true);
                    $pt_invoice_id      = $row["data"]["pt_invoice_id"] ?? false;
                    $job_id             = $row["data"]["trackable_jobs_id"] ?? false;
                    $invoice            = Invoices::get($row["owner_id"]);

                    if(!$invoice || !$job_id || !$pt_invoice_id){
                        Events::delete($row["id"]);
                        continue;
                    }

                    if(DateManager::diff_day($invoice["datepaid"],DateManager::Now("Y-m-d")) > 31)
                    {
                        $status = "done";
                    }
                    else
                    {
                        $status             = $this->trackablejobs($job_id);

                        if(stristr($this->error,'ord was not found'))
                        {
                            $formalize = $this->formalize($pt_invoice_id,$invoice);
                            if($formalize)
                                Events::delete($row["id"]);
                            else
                            {
                                if($invoice["taxed"] == 1) Invoices::set($invoice["id"],['taxed' => 0]);
                                Events::delete($row["id"]);
                            }
                        }
                        elseif(stristr($this->error,'bu fatura zaten resmi') || stristr($this->error,'e-Fatura daha önce gönde'))
                        {
                            $status = "done";
                            $this->error = NULL;
                        }
                    }



                    if($status == "done" || $invoice["taxed_file"]) Events::approved($row["id"]);
                    elseif($status == "error")
                    {
                        if(stristr($this->error,'e-Fatura daha'))
                        {
                            Events::approved($row["id"]);
                            break;
                        }

                        $error_trying = isset($row["data"]["error_trying"]) ? $row["data"]["error_trying"] : 0;

                        if($error_trying < 5){
                            $error_trying += 1;
                            $row["data"]["error_trying"] = $error_trying;
                            Events::set($row["id"], ['data' => $row["data"]]);
                        }
                        else
                            Events::set($row["id"],['status' => "error", 'status_msg' => $this->error]);

                        if(stristr($this->error,"Record was not found")) continue;
                        if(stristr($this->error,"Try again in")) continue;

                        if(stristr($this->error,"tarihinde e-Fatura"))
                        {
                            Events::set($row["id"],['status' => "error", 'status_msg' => $this->error]);
                            $pt_invoice = $this->get_sales_invoice($pt_invoice_id);
                            if($pt_invoice) $this->formalize_trying($pt_invoice,$invoice,'formalize');
                            continue;
                        }

                        if(!$this->search_critical_log("numaralı fatura resmileştirilemedi",$invoice["id"]))
                        {
                            $this->add_critical_log("#".$invoice["id"]." numaralı fatura resmileştirilemedi. ".$this->error,$invoice["id"]);
                            Events::set($row["id"],['status' => "error", 'status_msg' => $this->error]);
                        }
                        Invoices::set($invoice["id"],['taxed' => "0"]);
                    }
                    break;
                }
            }

            Events::set($processing["id"],['status' => "approved"]);

            return true;
        }
        private function define_pdf_file($settings=[])
        {

            $processing = Events::isCreated('processing','parasut-cronjob',0,'define_pdf_file',false,0,true);
            if($processing)
            {
                $p_data = Utility::jdecode($processing["data"],true);
                if($p_data && $processing["status"] == "pending")
                {
                    $wait_time = DateManager::strtotime(DateManager::next_date([
                        $processing["cdate"],
                        'minute' => 5
                    ]));
                    if(DateManager::strtotime() > $wait_time)
                    {
                        Events::set($processing["id"],['status' => "approved"]);
                    }
                    else
                        return false;
                }
            }

            $ev_data = [
                'type'      => "processing",
                'owner'     => "parasut-cronjob",
                'name'      => "define_pdf_file",
                'status'    => "pending",
                'cdate'     => DateManager::Now(),
            ];
            if($processing) Events::set($processing["id"],$ev_data);
            else
            {
                $processing_id  = Events::create($ev_data);
                $processing     = Events::get($processing_id);
            }


            $stmt   = Models::$init->db->select("t1.id,t1.taxed,t1.taxed_file,t2.id AS event_id,t2.data AS event_data")->from("invoices AS t1");
            $stmt->join("LEFT","events AS t2","t2.type='operation' AND t2.owner='invoice' AND t2.owner_id=t1.id AND t2.name='trackable_jobs' AND t2.status='approved'");
            $stmt->where("t2.id","IS NOT NULL","","&&");
            $stmt->where("(");
            $stmt->where("taxed_file","=","","||");
            $stmt->where("taxed_file","IS NULL","","");
            $stmt->where(")");
            $stmt->limit(3);
            $rows   = $stmt->build() ? $stmt->fetch_assoc() : false;

            if($rows){
                foreach($rows AS $row){
                    $row["event_data"] = Utility::jdecode($row["event_data"],true);
                    $e_data     = $row["event_data"];
                    $invoice    = $this->show_sales_invoice_with_id($e_data["pt_invoice_id"]);
                    $invoice    = $invoice["data"] ?? false;
                    $e_id       = $invoice["relationships"]["active_e_document"]["data"]["id"] ?? false;
                    $e_type     = $invoice["relationships"]["active_e_document"]["data"]["type"] ?? false;
                    $try_count  = $e_data["pdf_try_count"] ?? 0;


                    if($try_count >= 10)
                    {
                        Events::delete($row["event_id"]);
                        continue;
                    }
                    else
                    {
                        if($try_count == 0)
                            $e_data["pdf_try_count"] = 1;
                        else
                            $e_data["pdf_try_count"] += 1;

                        Events::set($row["event_id"],[
                            'data' => $e_data,
                        ]);
                    }


                    if(!$e_id) continue;

                    $pdf_link   = $this->get_e_pdf_link($e_id,$e_type);

                    if(!$pdf_link && $this->error){
                        $check = Models::$init->db->select("id")->from("events");
                        $check->where("type","=","info","&&");
                        $check->where("owner","=","system","&&");
                        $check->where("name","=","module-addon-error","&&");
                        $check->where("data","LIKE","%#".$row["id"]."%","&&");
                        $check->where("data","LIKE","%".$this->error."%","&&");
                        $check->where("unread","=","0","");
                        $check = $check->build() ? $check->getObject()->id : false;
                        if(!$check)
                        {
                            if(stristr($this->error,"Not Found: No route matches")) continue;
                            if(stristr($this->error,"Record was not found")) continue;
                            if(stristr($this->error,"Response could not be resolve")) continue;
                            $this->add_critical_log("#".$row["id"]." numaralı faturanın e-pdf dosyası alınamadı. ".$this->error,$row["id"]);
                        }

                        break;
                    }

                    $random_name = md5(time()).".pdf";
                    $file        = ROOT_DIR.RESOURCE_DIR."uploads".DS."invoices".DS.$random_name;

                    $this->download_remote_file($pdf_link,$file);

                    Invoices::set($row["id"],[
                        'taxed'         => 1,
                        'taxed_file'    => Utility::jencode([
                            'size'      => filesize($file),
                            'file_name' => $random_name,
                            'name'      => $random_name,
                            'file_path' => $random_name,
                        ])
                    ]);

                    Events::delete($row["event_id"]);

                    Notification::invoice_has_been_taxed($row["id"]);
                    break;
                }
            }

            Events::set($processing["id"],['status' => "approved"]);

            return true;
        }
        private function invoices_to_be_formalized($settings=[]){
            $day    = $settings["day"];
            $time   = DateManager::Now();
            $hour       = DateManager::Now("H");
            $first_activation_date = false;

            if(isset($this->config["settings"]["first-active-date"]) && $this->config["settings"]["first-active-date"])
                $first_activation_date = $this->config["settings"]["first-active-date"];


            if($day >= 999) return false;
            if(!($hour >= 8 && $hour <= 18)) return false;
            $btxn               = Config::get("options/balance-taxation");
            if(!$btxn) $btxn = "y";
            $balance_taxation  = $btxn == "n";

            $processing = Events::isCreated('processing','parasut-cronjob',0,'invoices_to_be_formalized',false,0,true);
            if($processing)
            {
                $p_data = Utility::jdecode($processing["data"],true);
                if($p_data && $processing["status"] == "pending")
                {
                    $wait_time = DateManager::strtotime(DateManager::next_date([
                        $processing["cdate"],
                        'minute' => 5
                    ]));
                    if(DateManager::strtotime() > $wait_time)
                    {
                        Events::set($processing["id"],['status' => "approved"]);
                    }
                    else
                        return false;
                }
            }

            $ev_data = [
                'type'      => "processing",
                'owner'     => "parasut-cronjob",
                'name'      => "invoices_to_be_formalized",
                'status'    => "pending",
                'cdate'     => DateManager::Now(),
            ];

            if($processing) Events::set($processing["id"],$ev_data);
            else
            {
                $processing_id  = Events::create($ev_data);
                $processing     = Events::get($processing_id);
            }


            $stmt   = Models::$init->db->select('t1.id,DATEDIFF("'.$time.'",t1.datepaid) AS date_difference')->from("invoices t1");
            $stmt->where("t1.total",">","0","&&");
            $stmt->where("t1.pmethod","!=","Free","&&");
            if($this->config["settings"]["ignore_pmethods"] ?? [])
                foreach($this->config["settings"]["ignore_pmethods"] AS $p) $stmt->where("t1.pmethod","!=",$p,"&&");
            if(!$balance_taxation) $stmt->where("t1.pmethod","!=","Balance","&&");
            $stmt->where("t1.status","=","paid","&&");
            $stmt->where("t1.legal","=","1","&&");
            if($day != 0) $stmt->where("DATEDIFF('".$time."',t1.datepaid)",">=",$day,"&&");
            if($first_activation_date) $stmt->where("t1.datepaid",">=",$first_activation_date,"&&");
            $stmt->where("DATEDIFF('".$time."',t1.datepaid)","<","8","&&");
            $stmt->where("NOT EXISTS(SELECT 1 FROM ".Models::$init->pfx."events t2 WHERE t2.type='info' AND t2.owner='system' AND t2.owner_id=t1.id AND t2.name='module-addon-error' AND t2.unread=0)","","","&&");
            $stmt->where("t1.taxed","=","0");
            $stmt->order_by("t1.datepaid ASC");
            $stmt->limit(1);
            $invoice    = $stmt->build() ? $stmt->getAssoc() : false;
            if($invoice) Invoices::MakeOperation("taxed",$invoice["id"]);

            Events::set($processing["id"],['status' => "approved"]);

            return true;
        }
    }

    Hook::add("InvoicePaid",1,[
        'class' => "Parasut",
        'method' => "paidInvoice",
    ]);
    Hook::add("formalizeInvoice",1,[
        'class' => "Parasut",
        'method' => "formalizeInvoice",
    ]);
    Hook::add("refundInvoice",1,[
        'class' => "Parasut",
        'method' => "refundInvoice",
    ]);
    Hook::add("cancelInvoice",1,[
        'class' => "Parasut",
        'method' => "cancelInvoice",
    ]);
    Hook::add("deleteInvoice",1,[
        'class' => "Parasut",
        'method' => "cancelInvoice",
    ]);
    Hook::add("CronTasks",1,[
        'class' => "Parasut",
        'method' => "cronjobs",
    ]);

    Hook::add("InvoiceModulesLogos",1,function(){
        $config = include __DIR__.DS."config.php";
        $folder = CORE_FOLDER.DS.MODULES_FOLDER.DS."Addons".DS."Parasut".DS;
        $logo   = isset($config["meta"]["logo"]) ? $config["meta"]["logo"] : NULL;
        $logo   = Utility::image_link_determiner($logo,$folder);
        return $logo;
    });