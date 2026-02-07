<?php
    class OnlineNICSSL {
        public $api                = false;
        public $config             = [];
        public $lang               = [];
        public  $error             = NULL;
        private $order             = [];
        private $user              = [];
        private $product           = [];
        private $_temp             = [];

        function __construct(){

            $this->config   = Modules::Config("Product",__CLASS__);
            $this->lang     = Modules::Lang("Product",__CLASS__);

            if(!class_exists("OnlineNICSSL_Api")) include __DIR__.DS."api.php";

            $username   = $this->config["settings"]["username"];
            $password   = $this->config["settings"]["password"];
            $api_key    = $this->config["settings"]["api-key"];
            $password   = Crypt::decode($password,Config::get("crypt/system"));
            $api_key    = Crypt::decode($api_key,Config::get("crypt/system"));
            $tmode      = (bool)$this->config["settings"]["test-mode"];

            $this->api =  new OnlineNICSSL_Api($username,$password,$api_key,$tmode);
        }
        private function setConfig($username,$password,$api_key,$tmode){
            $this->config["settings"]["username"] = $username;
            $this->config["settings"]["password"] = $password;
            $this->config["settings"]["api-key"] = $api_key;
            $this->config["settings"]["test-mode"] = $tmode;

            $this->api =  new OnlineNICSSL_Api($username,$password,$api_key,$tmode);

            return $this;
        }
        public function testConnection($config=[]){
            $username   = $config["settings"]["username"];
            $password   = $config["settings"]["password"];
            $api_key    = $config["settings"]["api-key"];

            if(!$username || !$password || !$api_key){
                $this->error = $this->lang["error2"];
                return false;
            }

            $password   = Crypt::decode($password,Config::get("crypt/system"));
            $api_key    = Crypt::decode($api_key,Config::get("crypt/system"));
            $tmode      = false;
            $this->setConfig($username,$password,$api_key,$tmode);

            if(!$this->list_ssl(true)) return false;

            return true;
        }
        public function set_order($order=[]){
            $this->order =  $order;
            Helper::Load(["Products","User"]);
            $this->product = Products::get($order["type"],$order["product_id"]);
            $this->user    = User::getData($order["owner_id"],"id,name,surname,full_name,company_name,email,phone,lang","array");
        }

        public function add_requirements($product=[],$step_data=[]){
            if(!$product) return false;
            if(!($product["type"] == "special" && $product["module"] == __CLASS__)) return false;
            $domain = "<span class='get_require_domain'>...</span>";

            $requirements           = [];

            $define_end_of_element_1   = "
<script type='text/javascript'>
    $(document).ready(function(){
        $('#requirement-domain').keyup(function(){
            var val = $(this).val();
            val = val.toLowerCase();
            val = val.replace('www.','');
            if(val === '')  val = '...';
            $('.get_require_domain').html(val);
        });
    });
</script>
";


            $requirements[] = [
                'id'                => "domain",
                'name'              => $this->lang["domain"],
                'description'       => $this->lang["domain-desc"],
                'type'              => "input",
                'properties'        => [
                    "compulsory" => true,
                    "placeholder" => "example.com",
                    "define_attribute_to_basket_item_options" => "domain",
                    "define_end_of_element" => $define_end_of_element_1,
                ],
                'options'           => [],
            ];

            $requirements[] = [
                'id'                => "csr-code",
                'name'              => $this->lang["csr-code"],
                'description'       => $this->lang["csr-code-desc"],
                'type'              => "textarea",
                'properties'        => [
                    "compulsory" => true,
                    "define_attribute_to_basket_item_options" => "csr-code",
                    "placeholder"   => '-----BEGIN CERTIFICATE REQUEST-----
                    
-----END CERTIFICATE REQUEST-----',
                ],
                'options'           => [],
            ];

            $requirements[] = [
                'id'                => "verification-email",
                'name'              => $this->lang["verification-email"],
                'description'       => $this->lang["verification-email-desc"],
                'type'              => "radio",
                'properties'        => [
                    "compulsory"    => true,
                    "define_attribute_to_basket_item_options" => "verification-email",
                ],
                'options'           => [
                    [
                        'id'     => "webmaster",
                        'name'   => "webmaster@".$domain,
                    ],
                    [
                        'id'     => "hostmaster",
                        'name'   => "hostmaster@".$domain,
                    ],
                    [
                        'id'     => "admin",
                        'name'   => "admin@".$domain,
                    ],
                    [
                        'id'     => "administrator",
                        'name'   => "administrator@".$domain,
                    ],
                    [
                        'id'     => "postmaster",
                        'name'   => "postmaster@".$domain,
                    ],
                ],
            ];

            return $requirements;
        }
        public function filter_requirement($data=[]){
            $product        = $data["product"];
            $step_data      = $data["step_data"];
            $requirement    = $data["requirement"];
            $value          = $data["value"];

            if(!$product) return false;
            if(!($product["type"] == "special" && $product["module"] == __CLASS__)) return false;

            if($requirement["id"] == "domain"){
                $value     = Filter::domain($value);

                $value     = str_replace("www.","",$value);
                $value     = trim($value);
                $sld        = NULL;
                $tld        = NULL;
                $parse      = Utility::domain_parser("http://".$value);
                if($parse["host"] != '' && strlen($parse["host"]) >= 2){
                    $sld    = $parse["host"];
                    $tld    = $parse["tld"];
                }
                if(!$sld || !$tld) $value = '';
            }

            $this->_temp["requirements"][$requirement["id"]] = $value;

            return ["value" => $value];
        }
        public function checking_requirement($data=[]){
            $product        = $data["product"];
            $step_data      = $data["step_data"];
            $requirement    = $data["requirement"];
            $value          = $data["value"];

            if(!$product) return false;
            if(!($product["type"] == "special" && $product["module"] == __CLASS__)) return false;
            $domain = false;
            if(isset($this->_temp["requirements"]["domain"])) $domain = $this->_temp["requirements"]["domain"];

            if($requirement["id"] == "csr-code"){
                $csr_data = openssl_csr_get_subject($value);
                if(!$csr_data || !$csr_domain = Utility::strtolower($csr_data["CN"]))
                    return [
                        'status' => "error",
                        'message' => $this->lang["error3"],
                    ];

                $csr_domain     = str_replace("www.","",$csr_domain);

                if($domain !== $csr_domain)
                    return [
                        'status' => "error",
                        'message' => $this->lang["error4"],
                    ];
            }

            return false;
        }

        public function use_method($method=''){
            if($method == "apply_changes") return $this->ac_edit_order_params();
            return true;
        }
        public function run_action($data=[]){
            if($data["command"] == "checking-ssl-enroll") return $this->checking_enroll($data);
            return true;
        }

        public function checking_enroll($data=[]){
            $u_lang = Modules::Lang("Product",__CLASS__,$this->user["lang"]);

            if(isset($this->order["options"]["config"]["order_id"]) && $this->order["options"]["config"]["order_id"]){
                $certificate   = $this->get_cert_details();

                if(!$certificate && $this->error !== "Certificate not enrolled"){
                    $this->error = $this->api->error;
                    return false;
                }

                if(!$certificate) return "continue";

                $folder         = ROOT_DIR.RESOURCE_DIR."uploads".DS."orders".DS;
                $name           = Utility::generate_hash(20,false,'ld').".txt";
                $file_name      = $folder.$name;

                $save           = FileManager::file_write($file_name,$certificate);
                if(!$save){
                    $this->error = "Path: ".$file_name." failed to open stream: No such file or directory";
                    return false;
                }

                $options        = $this->order["options"];
                $options["delivery_file"] = $name;
                $options["delivery_file_button_title"] = $u_lang["delivery_file_button_name"];
                $options["delivery_title_name"] = $u_lang["delivery_title"];
                $options["delivery_title_description"] = '';

                if(isset($options["checking-ssl-enroll"])) unset($options["checking-ssl-enroll"]);

                $this->order["options"] = $options;
                Orders::set($this->order["id"],['options' => Utility::jencode($this->order["options"])]);
            }
            return true;
        }

        public function edit_order_params(){
            $options        = $this->order["options"];
            if(!isset($options["creation_info"])) $options["creation_info"] = [];
            if(!isset($options["config"])) $options["config"] = [];
            $creation_info  = (array) Filter::POST("creation_info");
            $config         = (array) Filter::POST("config");
            $csr_code       = (string) Filter::POST("csr-code");
            $vrf_email      = (string) Filter::POST("verification-email");
            $vrf_email_ntf  = (int) Filter::init("POST/verification-email-notification","numbers");
            $setup          = (int) Filter::init("POST/setup","numbers");
            $reissue        = (int) Filter::init("POST/reissue","numbers");

            if($config) $options["config"] = array_replace_recursive($options["config"],$config);
            $options["creation_info"] = array_replace_recursive($options["creation_info"],$creation_info);

            if(!isset($options["config"]["order_id"]) || !$options["config"]["order_id"]) unset($options["config"]);
            if($csr_code) $options["csr-code"] = $csr_code;
            if($vrf_email) $options["verification-email"] = $vrf_email;

            $old_options            = $this->order["options"];
            $this->order["options"] = $options;

            $established            = false;
            if(isset($options["config"]["order_id"]) && $options["config"]["order_id"]) $established = true;

            if($established){

                $cert_details            = $this->get_cert_details();

                if($cert_details && $reissue){
                    $reissue  = $this->reissue();
                    if(!$reissue) return false;
                }elseif(!$cert_details && isset($old_options["verification-email"]) && $old_options["verification-email"] !== $vrf_email){
                    $change  = $this->change_verification_email();
                    if(!$change) return false;
                }elseif(!$cert_details && $vrf_email_ntf){
                    $change  = $this->resend_verification_email();
                    if(!$change) return false;
                }
            }


            if($setup){
                $setup  = $this->create();
                if(!$setup) return false;
                if($setup && is_array($setup))
                    $this->order["options"] = array_replace_recursive($this->order["options"],$setup);
            }
            return $this->order["options"];
        }
        public function ac_edit_order_params(){
            $options        = $this->order["options"];
            if(!isset($options["creation_info"])) $options["creation_info"] = [];
            if(!isset($options["config"])) $options["config"] = [];

            $csr_code       = (string) Filter::POST("csr-code");
            $vrf_email      = (string) Filter::POST("verification-email");
            $vrf_email_ntf  = (int) Filter::init("POST/verification-email-notification","numbers");
            $reissue        = (int) Filter::init("POST/reissue","numbers");

            if($csr_code) $options["csr-code"] = $csr_code;
            if($vrf_email) $options["verification-email"] = $vrf_email;

            $old_options            = $this->order["options"];
            $this->order["options"] = $options;

            $established            = false;
            if(isset($options["config"]["order_id"]) && $options["config"]["order_id"]) $established = true;

            if($established){

                $cert_details            = $this->get_cert_details();

                if($cert_details && $reissue){
                    $reissue  = $this->reissue();
                    if(!$reissue)
                        die(Utility::jencode([
                            'status' => "error",
                            'message' => __("website/account_products/error2",['{error}' => $this->error]),
                        ]));

                }
                elseif(!$cert_details && isset($old_options["verification-email"]) && $old_options["verification-email"] !== $vrf_email){
                    $change  = $this->change_verification_email();
                    if(!$change)
                        die(Utility::jencode([
                            'status' => "error",
                            'message' => __("website/account_products/error2",['{error}' => $this->error]),
                        ]));
                }
                elseif(!$cert_details && $vrf_email_ntf){
                    $change  = $this->resend_verification_email();
                    if(!$change)
                        die(Utility::jencode([
                            'status' => "error",
                            'message' => __("website/account_products/error2",['{error}' => $this->error]),
                        ]));
                }
            }

            Orders::set($this->order["id"],['options' => Utility::jencode($this->order["options"])]);

            echo Utility::jencode([
                'status' => "successful",
                'message' => $this->lang["success4"],
                'redirect' => Controllers::$init->CRLink("ac-ps-product",[$this->order["id"]]),
            ]);


            return true;
        }

        public function get_products(){

            $list   = [];
            if($page_list = $this->api->getSSLProductDetails(1)) $list = array_merge($list,$page_list);
            if($page_list = $this->api->getSSLProductDetails(2)) $list = array_merge($list,$page_list);
            if($page_list = $this->api->getSSLProductDetails(3)) $list = array_merge($list,$page_list);

            if(!$list && $this->api->error){
                $this->error = $this->api->error;
                return false;
            }

            $return     = [];

            if($list) foreach($list AS $item) $return[$item["productid"]] = $item["productname"];

            return $return;
        }
        public function get_details($order_id=0){
            if(!$order_id && isset($this->order["options"]["config"]["order_id"]) && $this->order["options"]["config"]["order_id"])
                $order_id = $this->order["options"]["config"]["order_id"];
            elseif(!Validation::isInt($order_id)){
                $order_id = $this->api->getSSLOrderId($order_id);
                if(!$order_id){
                    $this->error = $this->api->error;
                    return false;
                }
            }

            $response   = $this->api->getSSLOrderInfo($order_id);
            if(!$response){
                $this->error = $this->api->error;
                return false;
            }
            return $response;
        }
        public function get_cert_details($order_id=0){
            if(!$order_id && isset($this->order["options"]["config"]["order_id"]) && $this->order["options"]["config"]["order_id"])
                $order_id = $this->order["options"]["config"]["order_id"];
            elseif(!Validation::isInt($order_id)){
                $order_id = $this->api->getSSLOrderId($order_id);
                if(!$order_id){
                    $this->error = $this->api->error;
                    return false;
                }
            }

            $response   = $this->api->getSSLCert($order_id);
            if(!$response){
                $this->error = $this->api->error;
                return false;
            }
            $certificate = $response["cert"];

            if(!$certificate || $certificate == "-----BEGIN CERTIFICATE-----null-----END CERTIFICATE-----" || $certificate == "Empty"){
                $this->error = "Certificate not enrolled";
                return false;
            }

            return $certificate;
        }

        public function create($params=[]){
            if(!$params) $params = $this->order["options"];
            $domain     = isset($params["domain"]) ? $params["domain"] : false;
            if(!$domain){
                $this->error = $this->lang["error6"];
                return false;
            }
            $check  = $this->get_details($domain);
            if($check){
                $this->error = $this->lang["error7"];
                return false;
            }

            if(!isset($params["creation_info"]["product-id"]) || !$params["creation_info"]["product-id"]){
                $this->error = $this->lang["error8"];
                return false;
            }

            if(!isset($params["csr-code"]) || !$params["verification-email"]){
                $this->error = $this->lang["error5"];
                return false;
            }

            $months = 12;

            if($this->order["period"] == "month")
                $months = $this->order["period_time"];
            elseif($this->order["period"] == "year")
                $months = ((int) $this->order["period_time"]) * 12;


            $fields     = [
                'productid'         => $params["creation_info"]["product-id"],
                'period'            => $months,
                'servertype'        => "-1",
                'csr'               => $params["csr-code"],
                'approvalemail'     => $params["verification-email"]."@".$domain,
            ];

            $contact_types          = ['admin','tech'];
            foreach($contact_types AS $type){
                $fields[$type.'firstname'] = $this->user["name"];
                $fields[$type.'lastname'] = $this->user["surname"];
                $fields[$type.'title'] = 'Mr.';
                $fields[$type.'phone'] = "+".$this->user["phone"];
                $fields[$type.'email'] = $this->user["email"];
            }

            $fields['orgname'] = $this->user["company_name"];

            $get_address                = AddressManager::getAddress(0,$this->user["id"]);
            $fields["orgaddressline1"]  = NULL;
            if($get_address){
                $fields["orgaddressline1"] = $get_address["address"];
                $fields["orgcity"]         = $get_address["city"];
                $fields["orgstate"]        = $get_address["counti"];
                $fields["orgcountry"]      = $get_address["country_code"];
                $fields["orgpostalcode"]   = $get_address["zipcode"];
            }
            $fields["orgphone"]         = $this->user["phone"] ? "+".$this->user["phone"] : '';



            $result                 = $this->api->orderSSL($fields);

            if(!$result){
                $this->error = $this->api->error;
                return false;
            }

            $orderID = $result["orderid"];
            if(!$orderID) {
                $this->error = "Unable to obtain Order-ID";
                return false;
            }

            if(!class_exists("Events")) Helper::Load(["Events"]);
            Events::add_scheduled_operation([
                'owner'             => "order",
                'owner_id'          => $this->order["id"],
                'name'              => "run-action-for-order-module",
                'period'            => 'minute',
                'time'              => 5,
                'module'            => __CLASS__,
                'command'           => "checking-ssl-enroll",
            ]);

            $u_lang = Modules::Lang("Product",__CLASS__,$this->user["lang"]);

            return [
                'delivery_title_name' => $u_lang["delivery_title"],
                'delivery_title_description' => $u_lang["delivery_description"],
                'config'    => ['order_id' => $orderID],
            ];
        }
        public function extend($data=[]){

            if(isset($this->order["options"]["config"]["order_id"])){
                $params     = $this->order["options"];
                $order_id   = $params["config"]["order_id"];
                $domain     = isset($params["domain"]) ? $params["domain"] : false;

                $months = 12;

                if($data["period"] == "month") $months = $data["time"];
                elseif($data["period"] == "year") $months = ((int) $data["time"]) * 12;

                $fields     = [
                    'orderid'               => $order_id,
                    'period'                => $months,
                    'csr'                   => $params["csr-code"],
                    'approvalemail'         => $params["verification-email"]."@".$domain,
                ];

                $contact_types          = ['admin','tech'];
                foreach($contact_types AS $type){
                    $fields[$type.'firstname'] = $this->user["name"];
                    $fields[$type.'lastname'] = $this->user["surname"];
                    $fields[$type.'title'] = $this->user["company_name"];
                    $fields[$type.'phone'] = "+".$this->user["phone"];
                    $fields[$type.'email'] = "+".$this->user["email"];
                }

                $fields['orgname'] = $this->user["company_name"];

                $get_address                = AddressManager::getAddress(0,$this->user["id"]);
                $fields["orgaddressline1"]  = NULL;
                if($get_address){
                    $fields["orgaddressline1"] = $get_address["address"];
                    $fields["orgcity"]         = $get_address["city"];
                    $fields["orgstate"]        = $get_address["counti"];
                    $fields["orgcountry"]      = $get_address["country_code"];
                    $fields["orgpostalcode"]   = $get_address["zipcode"];
                }
                $fields["orgphone"]         = "+".$this->user["phone"];
                $fields["orgfax"]           = "";


                $result         = $this->api->renewSSL($fields);

                if(!$result){
                    $this->error = $this->api->error;
                    return false;
                }
            }
            return true;
        }
        public function delete(){
            if(isset($this->order["options"]["config"]["order_id"])){
                $order_id   = $this->order["options"]["config"]["order_id"];
                $result     = $this->api->cancelSSL($order_id);
                if(!$result){
                    $this->error = $this->api->error;
                    return false;
                }
            }
            return true;
        }
        public function change_verification_email($params=[]){
            if(!$params) $params = $this->order["options"];

            $order_id                   = $params["config"]["order_id"];

            $fields                     = [
                'orderid'               => $order_id,
                'newaddress'            => $params["verification-email"]."@".$params["domain"],
            ];

            $result                         = $this->api->changeValidationEmail($fields);

            if(!$result){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }
        public function resend_verification_email($params=[]){
            if(!$params) $params = $this->order["options"];

            $order_id                   = $params["config"]["order_id"];

            $fields                     = [
                'orderid'               => $order_id,
            ];

            $result                         = $this->api->resendApprovalEmail($fields);

            if(!$result){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }
        public function reissue($params=[]){
            if(!$params) $params = $this->order["options"];

            $order_id                   = $params["config"]["order_id"];
            $domain                     = $params["domain"];

            $email                      = $params["verification-email"]."@".$domain;

            $fields                     = [
                'orderid'                  => $order_id,
                'csr'                       => $params["csr-code"],
                'approvalemail'             => $email,
            ];

            $result                         = $this->api->reissueSSL($fields);

            if(!$result){
                $this->error = $this->api->error;
                return false;
            }

            if(!class_exists("Events")) Helper::Load(["Events"]);
            Events::add_scheduled_operation([
                'owner'             => "order",
                'owner_id'          => $this->order["id"],
                'name'              => "run-action-for-order-module",
                'period'            => 'minute',
                'time'              => 10,
                'module'            => __CLASS__,
                'command'           => "checking-ssl-enroll",
            ]);

            $u_lang = Modules::Lang("Product",__CLASS__,$this->user["lang"]);

            $options        = $this->order["options"];
            if(isset($options["delivery_file"])) unset($options["delivery_file"]);
            if(isset($options["delivery_file_button_title"])) unset($options["delivery_file_button_title"]);
            $options["delivery_title_name"] = $u_lang["delivery_title"];
            $options["delivery_title_description"] = $u_lang["delivery_description"];
            $this->order["options"] = $options;

            return true;
        }

        public function list_ssl($test=false){

            $list       = [];

            if($response = $this->api->getSSLOrderList(1)) $list = array_merge($list,$response);

            if(!$response && $this->api->error && !stristr($this->api->error,'1033')) $this->error = $this->api->error;
            if($test) return $this->error ? false : true;

            $result     = [];

            if($list){
                foreach($list AS $res){
                    if(isset($res["status"]) && $res["status"] != "COMPLETE") continue;
                    $cdate = isset($res["addtime"]) ? $res["addtime"] : '';
                    if($cdate) $cdate = DateManager::format("Y-m-d",$cdate);
                    $edate = isset($res["expire"]) ? $res["expire"] : '';
                    if($edate){
                        list($m,$d,$y) = explode("/",$edate);
                        $edate  = $y."-".$m."-".$d;
                    }
                    $domain = isset($res["domain"]) ? $res["domain"] : '';
                    if($domain){
                        $order_id    = 0;
                        $user_data   = [];
                        $is_imported = Models::$init->db->select("id,owner_id AS user_id")->from("users_products");
                        $is_imported->where("type",'=',"special","&&");
                        $is_imported->where("module",'=',__CLASS__,"&&");
                        $is_imported->where("options",'LIKE','%"domain":"'.$domain.'"%');
                        $is_imported = $is_imported->build() ? $is_imported->getAssoc() : false;
                        if($is_imported){
                            $order_id   = $is_imported["id"];
                            $user_data  =  User::getData($is_imported["user_id"],"id,full_name,company_name","array");
                        }


                        $result[] = [
                            'api_orderid'       => $res["orderid"],
                            'domain'            => $domain,
                            'creation_date'     => $cdate,
                            'end_date'          => $edate,
                            'order_id'          => $order_id,
                            'user_data'        => $user_data,
                        ];
                    }
                }
            }

            return $result;
        }
        public function import($data=[]){
            $config     = $this->config;

            $imports = [];

            Helper::Load(["Orders","Products","Money","Events"]);

            if(function_exists("ini_set")) ini_set("max_execution_time",3600);

            foreach($data AS $domain=>$datum){
                $user_id        = isset($datum["user_id"]) ? (int) $datum["user_id"] : 0;
                $api_orderid    = isset($datum["api_orderid"]) ? (int) $datum["api_orderid"] : 0;

                if(!$user_id) continue;
                $info           = $this->get_details($api_orderid);
                if(!$info) continue;

                $user_data          = User::getData($user_id,"id,lang","array");
                $ulang              = $user_data["lang"];
                $locallang          = Config::get("general/local");
                $product_id         = $info["productid"];

                $product          = Models::$init->db->select("id,type_id,module_data")->from("products");
                $product->where("type","=","special","&&");
                $product->where("module","=",__CLASS__,"&&");
                $product->where("module_data","LIKE",'%"product-id":"'.$product_id.'"%');
                $product          = $product->build() ? $product->getAssoc() : false;

                if(!$product) continue;
                $productID  = $product["id"];

                $productPrice       = Products::get_price("periodicals","products",$productID);
                if(!$productPrice) continue;

                $productPrice_amt   = $productPrice["amount"];
                $productPrice_cid   = $productPrice["cid"];

                $start_date         = $datum["start_date"]." 00:00:00";
                $end_date           = $datum["end_date"]." 00:00:00";

                $group_u              = Products::getCategoryName($product["type_id"],$ulang);
                $group_l              = Products::getCategoryName($product["type_id"],$locallang);
                $productName          = Products::get_info_by_fields("special",$productID,["t2.title"],$ulang);
                $productName          = $productName["title"];

                $vrf_email            = '';
                if(isset($info["approvalemail"]) && $info["approvalemail"]){
                    $split_email = explode("@",$info["approvalemail"]);
                    $vrf_email = $split_email[0];
                }

                $options            = [
                    "established"         => true,
                    "group_name"          => $group_u,
                    "local_group_name"    => $group_l,
                    "category_id"         => 0,
                    "domain"              => $domain,
                    "csr-code"            => $info["csr"],
                    "verification-email"  => $vrf_email,
                    "config"              => ["order_id" => $api_orderid],
                    "creation_info"       => Utility::jdecode($product["module_data"],true),
                ];


                $u_lang = Modules::Lang("Product",__CLASS__,$ulang);

                $certificate   = $this->get_cert_details($api_orderid);

                if($certificate){
                    $folder         = ROOT_DIR.RESOURCE_DIR."uploads".DS."orders".DS;
                    $name           = Utility::generate_hash(20,false,'ld').".txt";
                    $file_name      = $folder.$name;

                    $save           = FileManager::file_write($file_name,$certificate);
                    if($save){
                        $options["delivery_file"] = $name;
                        $options["delivery_file_button_title"] = $u_lang["delivery_file_button_name"];
                        $options["delivery_title_name"] = $u_lang["delivery_title"];
                        $options["delivery_title_description"] = '';
                    }else{
                        $options["delivery_title_name"] = $u_lang["delivery_title"];
                        $options["delivery_title_description"] = $u_lang["delivery_description"];
                    }
                }


                $order_data             = [
                    "owner_id"          => (int) $user_id,
                    "type"              => "special",
                    "type_id"           => $product["type_id"],
                    "product_id"        => (int) $productID,
                    "name"              => $productName,
                    "period"            => "year",
                    "period_time"       => 1,
                    "amount"            => (float) $productPrice_amt,
                    "total_amount"      => (float) $productPrice_amt,
                    "amount_cid"        => (int) $productPrice_cid,
                    "status"            => "active",
                    "cdate"             => $start_date,
                    "duedate"           => $end_date,
                    "renewaldate"       => $start_date,
                    "module"            => __CLASS__,
                    "options"           => Utility::jencode($options),
                    "unread"            => 1,
                ];

                $insert                 = Orders::insert($order_data);
                if(!$insert) continue;

                if(!$certificate && $this->error === "Certificate not enrolled")
                    Events::add_scheduled_operation([
                        'owner'             => "order",
                        'owner_id'          => $insert,
                        'name'              => "run-action-for-order-module",
                        'period'            => 'minute',
                        'time'              => 5,
                        'module'            => __CLASS__,
                        'command'           => "checking-ssl-enroll",
                    ]);


                $imports[] = $order_data["name"]." (#".$insert.")";
            }

            if($imports){
                $adata      = UserManager::LoginData("admin");
                User::addAction($adata["id"],"alteration","imported-ssl-orders",[
                    'module'   => $config["meta"]["name"],
                    'imported'  => implode(", ",$imports),
                ]);
            }

            return $imports;
        }


    }

    Hook::add("addRequirementToOrderSteps",1,[
        'class'     => "OnlineNICSSL",
        'method'    => "add_requirements",
    ]);

    Hook::add("filterRequirementToOrderSteps",1,[
        'class'     => "OnlineNICSSL",
        'method'    => "filter_requirement",
    ]);

    Hook::add("checkingRequirementToOrderSteps",1,[
        'class'     => "OnlineNICSSL",
        'method'    => "checking_requirement",
    ]);