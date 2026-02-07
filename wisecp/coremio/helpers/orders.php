<?php
    defined('CORE_FOLDER') OR exit('You can not get in here!');

    Class Orders {
        public static $module_processing=[];
        public static $notification_processing=[];
        private static $product_temp=[];
        private static $markers;
        public static $message,$delete_hosting = true,$set_data,$realized_on_module,$suspended_reason;
        static $eventList = [
            'DomainNameRegisterOrder'           => "domain_formation",
            'DomainNameTransferRegisterOrder'   => "domain_formation",
            'RenewalDomain'                     => "domain_modification",
            'ModifyDomainWhoisPrivacy'          => "domain_modification",
            'HostingOrder'                      => "hosting_formation",
            'ServerOrder'                       => "server_formation",
            'SoftwareOrder'                     => "software_formation",
            'SpecialProductOrder'               => "special_formation",
            'AddonOrder'                        => "order_modification",
            'OrderUpgrade'                      => "order_modification",
            'ExtendOrderPeriod'                 => "order_modification",
            'ExtendAddonPeriod'                 => "order_modification",
            'SmsProductOrder'                   => "sms_formation",
            'RenewalSmsCredit'                  => "sms_modification",
            'addCredit'                         => "user_modification",
        ];
        private static $notification_logs = [];
        static $temp;

        static function detail_period($data=[],$lang=''){
            if(!$lang) $lang = Bootstrap::$lang->clang;
            return View::period($data["period_time"],$data["period"],$lang);
        }

        static function detail_name($order=[]){
            if(!$order) return false;
            if($order["options"] && !is_array($order["options"])) $order["options"] = Utility::jdecode($order["options"],true);
            $options    = isset($order["options"]) ? $order["options"] : [];
            $str        = $order["name"]." (#".$order["id"].")";

            //if($order["type"] == "domain") $str .= " (".$options["group_name"].")";

            if(isset($options["domain"]) && $options["domain"] && $order["type"] != "domain")
                $str .= " (".$options["domain"].")";
            if(isset($options["hostname"]) && $options["hostname"]){
                $str .= " (";
                $str .= $options["hostname"];
                if(isset($options["ip"]) && $options["ip"]) $str .= " - ".$options["ip"];
                $str .= ")";
            }

            return $str;
        }

        static function get_orders($uid=0,$status=false){
            $stmt   = Models::$init->db->select()->from("users_products");
            if($status) $stmt->where("status","=",$status,"&&");
            $stmt->where("owner_id","=",$uid);
            $stmt->order_by("id DESC");
            $stmt   = $stmt->build() ? $stmt->fetch_assoc() : false;
            if($stmt){
                $keys   = array_keys($stmt);
                $size   = sizeof($keys)-1;
                for($i=0;$i<=$size;$i++){
                    $row = $stmt[$keys[$i]];
                    if(isset($row["options"])){
                        $stmt[$keys[$i]]["options"] = $row["options"] ? Utility::jdecode($row["options"],true) : [];
                    }
                }
            }
            return $stmt;
        }

        static function get_updown($id=0){
            $stmt   = Models::$init->db->select()->from("users_products_updowngrades");
            $stmt->where("id","=",$id);
            $data   = $stmt->build() ? $stmt->getAssoc() : false;
            if($data){
                $data["options"] = $data["options"] ? Utility::jdecode($data["options"],true) : [];
            }
            return $data;
        }

        static function set_updown($id=0,$data=[]){
            return Models::$init->db->update("users_products_updowngrades",$data)->where("id","=",$id)->save();
        }

        static function generate_updown($grade='',$invoice='',$order=[],$product=[],$sproduct=[],$sprice=[],$refund=''){
            $data   = [
                'user_id'  => $order["owner_id"],
                'owner_id' => $order["id"],
                'invoice_id' => $invoice ? $invoice["id"] : 0,
                'old_pid' => $product["id"],
                'new_pid' => $sproduct["id"],
                'type'    => $grade,
                'cdate'   => DateManager::Now(),
            ];
            if($refund == "money") $refund = "cash";
            $data["refund"] = $refund ? $refund : "none";

            if(!$invoice || ($invoice && $invoice["status"] == "paid") ) $data["status"] = "inprocess";
            else $data["status"] = "waiting";

            $ordinfo = self::period_info($order);

            $data["options"]                        = [];
            $data["options"]["currency"]            = $order["amount_cid"];
            $data["options"]["difference_amount"]   = $grade == "up" ? $sprice["payable"] : $sprice["difference"];
            $data["options"]["old_name"]            = $order["name"];
            $data["options"]["old_period"]          = $order["period"];
            $data["options"]["old_period_time"]     = $order["period_time"];
            $data["options"]["old_amount"]          = $order["amount"];
            $data["options"]["new_name"]            = $sproduct["title"];
            $data["options"]["new_period"]          = $sprice["period"];
            $data["options"]["new_period_time"]     = $sprice["time"];
            $data["options"]["new_amount"]          = $sprice["amount"];
            $data["options"]["old_renewaldate"]     = $order["renewaldate"];
            $data["options"]["old_duedate"]         = $order["duedate"];
            $data["options"]["times_used"]          = $ordinfo["times-used-day"];
            $data["options"]["times_used_amount"]   = $ordinfo["times-used-amount"];
            $data["options"]["remaining_day"]       = $ordinfo["remaining-day"];
            $data["options"]["remaining_amount"]    = $ordinfo["remaining-amount"];

            $data["options"] = Utility::jencode($data["options"]);
            $stmt = Models::$init->db->insert("users_products_updowngrades",$data);
            $stmt = $stmt ? Models::$init->db->lastID() : false;
            if(!$stmt) return false;
            $get    = Models::$init->db->select()->from("users_products_updowngrades")->where("id","=",$stmt);
            $get    = $get->build() ? $get->getAssoc() : false;
            $get["options"] = $data["options"];
            return $get;
        }

        static function updown($order,$type='up',$info=[]){
            $ulang          = User::getData($order["owner_id"],"lang")->lang;
            $u_sproduct     = Products::get_info_by_fields($order["type"],$info["new_pid"],["t2.title"],$ulang);
            $product        = Products::get($order["type"],$info["new_pid"]);
            $order_result   = [
                'status'    => "inprocess",
                'status_msg' => NULL,
            ];
            $return_result  = [
                'status'    => "inprocess",
                'status_msg' => NULL,
            ];

            $p_server_id = isset($product["module_data"]["server_id"]) ? $product["module_data"]["server_id"] : 0;
            if(isset($product["options"]["server_id"])) $p_server_id = $product["options"]["server_id"];

            $ordopptions    = $order["options"];
            $module_grade   = false;

            if(($order["type"] == "hosting" || $order["type"] == "server") && $order["module"] != "none" && $order["module"] == $product["module"] && $p_server_id && $order["options"]["server_id"] == $p_server_id)
                $module_grade = true;
            elseif(!($order["type"] == "hosting" || $order["type"] == "server") && $order["module"] != "none" && $order["module"] == $product["module"])
                $module_grade = true;

            if($module_grade){
                $handle = self::ModuleHandler($order,$product,$type."grade");
                if(!$handle || $handle == "failed"){
                    $return_result["status"]         = "inprocess";
                    $return_result["status_msg"]     = self::$message;
                    $order_result["status"]         = "inprocess";
                    $order_result["status_msg"]     = self::$message;
                    $return_result["unread"]        = 0;
                }else{
                    $order_result["status"]         = "active";
                    $return_result["status"]        = "completed";
                    $return_result["unread"]        = 1;
                }
            }

            elseif($order["type"] == "server"){

                if($order["module"] != "none" && $product["module"] == "none"){
                    $handle_x = self::ModuleHandler($order,false,"terminate",[
                        'updowngrade_remove_server' => true,
                    ]);
                    if(!$handle_x || $handle_x == "failed") $handle = $handle_x;
                }

                elseif($order["module"] == "none" && $product["module"] != "none")
                    $handle = self::ModuleHandler($order, $product,$type."grade",['order-module-none' => true]);

                elseif($order["module"] == $product["module"] && isset($product["options"]["server_id"]) && $order["options"]["server_id"] == $product["options"]["server_id"])
                    $handle = self::ModuleHandler($order,$product,$type."grade",['synchronized' => true]);

                elseif($order["module"] != $product["module"]){

                    $handle = self::ModuleHandler($order,false,"terminate",[
                        'updowngrade_remove_server' => true,
                    ]);
                    if($handle == "successful"){
                        $order  = self::get($order["id"]);
                        $handle = self::ModuleHandler($order,$product,$type."grade");
                    }
                }

                if(isset($handle)){
                    if(!$handle || $handle == "failed"){
                        $return_result["status"]         = "inprocess";
                        $return_result["status_msg"]     = self::$message;
                        $order_result["status"]         = "inprocess";
                        $order_result["status_msg"]     = self::$message;
                        $return_result["unread"]        = 0;
                    }
                    else{
                        $order_result["status"]         = "active";
                        $return_result["status"]        = "completed";
                        $return_result["unread"]        = 1;
                    }
                }
            }

            if(isset($handle) && $handle == 'successful'){
                $order          = self::get($order["id"]);
                $ordopptions    = $order["options"];
            }

            if($info["new_pid"] != $order["product_id"]){

                $local_catName = $product["category"] ? Products::getCategoryName($product["category"],Config::get("general/local")) : false;
                $u_catName    = $product["category"] ? Products::getCategoryName($product["category"],$ulang) : false;
                if($order["type"] == "special" && $product["category"] == $order["type_id"]){
                    unset($ordopptions["local_category_name"]);
                    unset($ordopptions["category_name"]);
                }
                elseif($local_catName && $u_catName){
                    $ordopptions["local_category_name"] = $local_catName;
                    $ordopptions["category_name"] = $u_catName;
                }

                if((isset($ordopptions["category_id"]) && $ordopptions["category_id"] != $product["category"]) && $local_catName)
                    $ordopptions["category_id"] = $product["category"];

                $module     = $product["module"];

                if($order["type"] == "hosting"){

                    $disk_limit         = $product["options"]["disk_limit"];
                    $bandwidth_limit     = $product["options"]["bandwidth_limit"];
                    $email_limit         = $product["options"]["email_limit"];
                    $database_limit      = $product["options"]["database_limit"];
                    $addons_limit        = $product["options"]["addons_limit"];
                    $subdomain_limit     = $product["options"]["subdomain_limit"];
                    $ftp_limit           = $product["options"]["ftp_limit"];
                    $park_limit          = $product["options"]["park_limit"];
                    $max_email_per_hour  = $product["options"]["max_email_per_hour"];
                    $cpu_limit           = $product["options"]["cpu_limit"];
                    $dns                 = isset($product["options"]["dns"]) ? $product["options"]["dns"] : [];
                    $server_features     = $product["options"]["server_features"];

                    $ordopptions    = array_merge($ordopptions,[
                        'disk_limit'            => $disk_limit,
                        'bandwidth_limit'       => $bandwidth_limit,
                        'email_limit'           => $email_limit,
                        'database_limit'        => $database_limit,
                        'addons_limit'          => $addons_limit,
                        'subdomain_limit'       => $subdomain_limit,
                        'ftp_limit'             => $ftp_limit,
                        'park_limit'            => $park_limit,
                        'max_email_per_hour'    => $max_email_per_hour,
                        'cpu_limit'             => $cpu_limit,
                        'server_features'       => $server_features,
                        'dns'                   => $dns,
                    ]);

                    if($order["module"] != "none"){
                        $creation_info      = isset($product["module_data"]["create_account"]) ? $product["module_data"]["create_account"] : ($product["module_data"] ? $product["module_data"] : []);
                        $ordopptions["creation_info"] = $creation_info;
                    }
                }
                elseif($order["type"] == "server"){
                    $ordopptions        = array_merge($ordopptions,[
                        'server_features' => [
                            'processor'     => $product["options"]["processor"],
                            'ram'           => $product["options"]["ram"],
                            'disk-space'    => $product["options"]["disk-space"],
                            'raid'          => $product["options"]["raid"],
                            'bandwidth'     => $product["options"]["bandwidth"],
                            'location'      => $product["optionsl"]["location"],
                        ],
                    ]);

                    if($order["module"] != "none"){
                        $creation_info      = $product["module_data"];
                        $ordopptions["creation_info"] = $creation_info;
                    }
                }

                $period         = $info["new_period"];
                $period_time    = $info["new_period_time"];
                $next_date      = DateManager::next_date([$period => $period_time],"Y-m-d H:i:s");
                $due_date       = $next_date;
                $set_data   = [
                    'product_id' => $info["new_pid"],
                    'name'       => $u_sproduct["title"],
                    'period' => $period,
                    'period_time' => $period_time,
                    'total_amount' => $info["new_amount"],
                    'amount' => $info["new_amount"],
                    'status' => $order_result["status"],
                    'status_msg' => $order_result["status_msg"],
                    'renewaldate' => DateManager::Now(),
                    'duedate' => $due_date,
                    'options' => Utility::jencode($ordopptions),
                    'module'  => $module,
                ];
                self::set($order["id"],$set_data);

                self::add_history(0,$order['id'],'order-'.$type.'graded',[
                    'old_id'    => $order["product_id"],
                    'new_id'    => $set_data["product_id"],
                    'old_name'  => $order["name"],
                    'new_name'  => $set_data["name"],
                ]);

            }
            else self::set($order["id"],$order_result);

            return $return_result;
        }

        static function period_info($order=[]){
            if($order["period"] == "none") return false;
            if($order["period"] == "hour") return false;
            if(substr($order["duedate"],0,4) =="1881" || substr($order["duedate"],0,4) == "1970") return false;
            if(substr($order["renewaldate"],0,4) =="1881" || substr($order["renewaldate"],0,4) == "1970") return false;

            $duedate        = substr($order["duedate"],0,10);
            $duedate_time   = DateManager::strtotime($order["duedate"]);
            $renewaldate    = substr($order["renewaldate"],0,10);
            $renewal_time   = DateManager::strtotime($order["renewaldate"]);

            if($duedate == $renewaldate) return false;

            $period        = DateManager::remaining_day($duedate,$renewaldate);


            $period        = DateManager::special_time(['day' => $period]);
            $period        = DateManager::second_to_day($period);

            $now           = DateManager::Now("Y-m-d");
            $now_time      = DateManager::strtotime();
            $remaining_day = DateManager::remaining_day($duedate,$now);
            $remaining_day = $remaining_day < 0 ? 0 : $remaining_day;
            $times_used    = 0;
            if($duedate_time > $now_time) $times_used = $now_time - $renewal_time;


            $times_used               = DateManager::second_to_day($times_used);

            $period_daily_amount      = ($order["amount"] / $period);
            if($duedate_time > $now_time){
                $remaining_amount         =  ($period_daily_amount * $times_used);
                $remaining_amount         = $order["amount"] - $remaining_amount;
            }else
                $remaining_amount         = 0;

            if($remaining_amount < 0) $remaining_amount = 0;
            $times_used_amount  = ($period_daily_amount * $times_used);

            return [
                'renewal-date' => $renewaldate,
                'due-date' => $duedate,
                'period-day' => $period,
                'period-daily-amount' => $period_daily_amount,
                'times-used-day' => $times_used,
                'remaining-day' => $remaining_day,
                'remaining-amount' => $remaining_amount,
                'times-used-amount' => $times_used_amount,
                'format-times-used-amount' => Money::formatter_symbol($times_used_amount,$order["amount_cid"]),
                'format-remaining-amount' => Money::formatter_symbol($remaining_amount,$order["amount_cid"]),
            ];
        }

        static function insert($data=[]){
            return Models::$init->db->insert("users_products",$data) ? Models::$init->db->lastID() : false;
        }

        static function set($id=0,$data=[]){
            return Models::$init->db->update("users_products",$data)->where("id","=",$id)->save();
        }

        static function delete($order=0,$apply_on_module=true){
            if(!is_array($order)) $order = self::get($order);
            if(!$order) return false;
            $id    = $order["id"];

            Hook::run("PreOrderDeleted",$order);

            if($order["type"] == "domain"){
                $h_operations = Hook::run("DomainDelete",$order);
                if($h_operations){
                    foreach($h_operations AS $h_operation){
                        if($h_operation && isset($h_operation["error"]) && $h_operation["error"]){
                            self::$message = $h_operation["error"];
                            self::set($order["id"],['status_msg' => self::$message]);
                            return false;
                        }
                    }
                }
            }

            $addons = Models::$init->db->select("id")->from("users_products_addons")->where("owner_id","=",$id);
            if($addons->build())
            {
                foreach($addons->fetch_object() AS $a)
                {
                    $handle = self::MakeOperationAddon('delete',$id,$a->id,false,$apply_on_module);
                    if(!$handle) return false;
                }
            }


            $requirements   = Models::$init->db->select()->from("users_products_requirements");
            $requirements->where("owner_id","=",$id);
            $requirements   = $requirements->build() ? $requirements->fetch_assoc() : false;
            if($requirements){
                foreach($requirements AS $require){
                    if($require["response_type"]=="file"){
                        if($require["response"]){
                            $response  = Utility::jdecode($require["response"],true);
                            foreach($response AS $re){
                                FileManager::file_delete(RESOURCE_DIR."uploads".DS."product-requirements".DS.$re["file_path"]);
                            }
                        }
                    }
                    Models::$init->db->delete("users_products_requirements")->where("id","=",$require["id"])->run();
                }
            }
            if($order["type"] == "sms"){
                $origins   = Models::$init->db->select()->from("users_sms_origins");
                $origins->where("pid","=",$id);
                $origins   = $origins->build() ? $origins->fetch_assoc() : false;
                if($origins){
                    foreach($origins AS $origin){
                        if($origin["attachments"]){
                            $attachments    = Utility::jdecode($origin["attachments"],true);
                            foreach($attachments AS $attachment){
                                FileManager::file_delete(RESOURCE_DIR."uploads".DS."attachments".DS.$attachment["file_path"]);
                            }
                        }
                        Models::$init->db->delete("users_sms_origins")->where("id","=",$origin["id"])->run();
                    }
                }
            }
            if(isset($order["invoice_id"]) && $order["invoice_id"]){
                Helper::Load(["Invoices"]);
                $invoice_item_count = Invoices::get_item_count($order["invoice_id"]);
                if($invoice_item_count==1){
                    $invoice    = Invoices::get($order["invoice_id"],['select' => "id,status"]);
                    if($invoice && $invoice["status"] != "paid") Invoices::delete($order["invoice_id"]);
                }
            }

            if($order["module"] != "none" && $apply_on_module){
                if(($order["type"] == "hosting" && self::$delete_hosting) || $order["type"] == "server"){
                    $handle = self::ModuleHandler($order,false,"terminate");
                    if(!$handle || $handle == "failed"){
                        self::set($order["id"],['status_msg' => self::$message]);
                        return false;
                    }
                }
            }

            Models::$init->db->delete("users_products_updowngrades")
                ->where("owner_id","=",$id)
                ->run();



            Models::$init->db->delete("events")
                ->where("owner_id","=",$id,"&&")
                ->where("owner","=","order")
                ->run();

            if($order["type"] == "sms")
                Models::$init->db->delete("users_sms_groups")
                    ->where("pid","=",$id)
                    ->run();

            $delete     = Models::$init->db->delete("users_products")->where("id","=",$id)->run();

            Helper::Load(["User"]);
            if($delete) User::affiliate_delete_order_transaction($order);

            Hook::run("OrderDeleted",$order);

            return $delete;
        }

        static function get($id=0,$select=''){
            $stmt   = Models::$init->db->select($select)->from("users_products");
            $stmt->where("id","=",$id);
            $data   = $stmt->build() ? $stmt->getAssoc() : false;
            if($data && isset($data["options"])){
                $data["options"] = $data["options"] ? Utility::jdecode($data["options"],true) : [];
            }
            if(empty($data["module"] ?? '')) $data["module"] = "none";
            return $data;
        }

        static function insert_addon($data=[]){
            return Models::$init->db->insert("users_products_addons",$data) ? Models::$init->db->lastID() : false;
        }

        static function get_addon($id=0,$select=''){
            $stmt = Models::$init->db->select($select)->from("users_products_addons")->where("id","=",$id);
            return $stmt->build() ? $stmt->getAssoc() : false;
        }

        static function set_addon($id=0,$data=[]){
            $unsuspended = false;
            $addon = self::get_addon($id);
            if(isset($data['status']) && $data['status'] != $addon['status'])
            {
                if($data['status'] == "active" && $addon['status'] == 'suspended') $unsuspended = true;
                if($data['status'] == "active" && !$unsuspended) Hook::run("AddonActivation",$addon);
            }
            $apply = Models::$init->db->update("users_products_addons",$data)->where("id","=",$id)->save();

            if(isset($data['status']) && $data['status'] != $addon['status'])
            {
                $addon = self::get_addon($id);
                if($unsuspended) Hook::run("AddonUnsuspended",$addon);
                if($data['status'] == "active" && !$unsuspended) Hook::run("AddonActivated",$addon);
                if($data['status'] == "inprocess") Hook::run("AddonApproved",$addon);
                if($data['status'] == "cancelled") Hook::run("AddonCancelled",$addon);
                if($data['status'] == "suspended") Hook::run("AddonSuspended",$addon);
            }
            Hook::run("AddonModified",$addon);

            return $apply;
        }

        static function delete_addon($id=0){
            if(is_array($id))
            {
                $addon  = $id;
                $id     = $addon["id"];
            }
            else $addon = self::get_addon($id);

            Hook::run("PreAddonDeleted",$addon);
            $remove = Models::$init->db->delete("users_products_addons")->where("id","=",$id)->run();
            if($remove) Hook::run("AddonDeleted",$addon);
            return $remove;
        }

        static function insert_requirement($data=[]){
            return Models::$init->db->insert("users_products_requirements",$data) ? Models::$init->db->lastID() : false;
        }

        static function set_requirement($id=0,$data=[]){
            return Models::$init->db->update("users_products_requirements",$data)->save();
        }

        static function delete_requirement($id=0){
            return Models::$init->db->delete("users_products_requirements")->where("id","=",$id)->run();
        }

        static function addon_process($invoice,$order,$extra=[])
        {
            $addons         = isset($extra["addons"]) ? $extra["addons"] : [];
            $addons_values  = isset($extra["addons_values"]) ? $extra["addons_values"] : [];
            $requirements   = isset($extra["requirements"]) ? $extra["requirements"] : [];

            if(!$order || !$addons) return false;

            if(isset($invoice["user_data"]["lang"])) $ulang = $invoice["user_data"]["lang"];
            else $ulang = User::getData($order["owner_id"],"lang")->lang;

            $addons_data    = [];
            foreach ($addons as $id=>$selected){
                if(is_array($selected) && $selected["product_id"] == "whois-privacy")
                {
                    $getAddon           = $selected;
                    $old_name           = $getAddon["name"];
                    $getAddon["name"]   = Bootstrap::$lang->get_cm("website/account_products/whois-privacy",false,$ulang);
                    $opt_name           = Bootstrap::$lang->get_cm("website/osteps/iwant",false,$ulang);
                    $opt_qy             = 0;
                    $option_id          = 0;
                    $amount             = Money::exChange($getAddon["amount"],$getAddon["currency"],$order["amount_cid"]);
                    $amount_x           = $amount;

                    $start              = DateManager::Now();
                    $end                = $getAddon["period"] == "none" ? DateManager::ata() : DateManager::next_date([
                        $getAddon["period"] => $getAddon["period_time"],
                    ]);

                    if(($getAddon["period"] == "day" || $getAddon["period"] == "week" || $getAddon["period"] == "month" || $getAddon["period"] == "year") && isset($invoice["data"]["subscribed"]) && $invoice["data"]["subscribed"])
                    {
                        $sub_hash = md5("addon"."|whois-privacy|year|1");
                        if(isset($invoice["data"]["subscribed"][$sub_hash]))
                            $sub_id = $invoice["data"]["subscribed"][$sub_hash];
                        else
                            $sub_id = 0;
                    }
                    else
                        $sub_id = 0;

                    $data = [
                        'subscription_id'   => $sub_id,
                        'invoice_id'        => $order["invoice_id"],
                        'owner_id'          => $order["id"],
                        'addon_key'         => "whois-privacy",
                        'addon_id'          => 0,
                        'addon_name'        => $getAddon["name"],
                        'option_id'         => $option_id,
                        'pmethod'           => $invoice["pmethod"],
                        'option_name'       => $opt_name,
                        'option_quantity'   => $opt_qy,
                        'period'            => $getAddon["period"],
                        'period_time'       => $getAddon["period_time"],
                        'cdate'             => $start,
                        'renewaldate'       => $start,
                        'duedate'           => $end,
                        'amount'            => $amount_x,
                        'cid'               => $order["amount_cid"],
                    ];

                    if($invoice["status"] == "paid") $data["status"] = "inprocess";

                    $insert             = self::insert_addon($data);
                    if($insert)
                    {
                        $data["name"]       = $old_name;
                        $data["id"]         = $insert;
                        $addons_data[]      = $data;
                    }
                }
                else
                {
                    $getAddon   = Products::addon($id,$ulang);
                    if($getAddon){
                        $options    = $getAddon["options"] ?? [];
                        $found      = false;
                        if($options) foreach($options AS $opt) if($selected == $opt["id"]) $found = $opt;
                        if($found)
                        {
                            $opt_name   = $found["name"];
                            $opt_qy     = 0;
                            $amount     = Money::exChange($found["amount"],$found["cid"],$order["amount_cid"]);
                            if($getAddon["type"] == "quantity"){
                                $opt_qy = 1;
                                if(isset($addons_values[$getAddon["id"]])){
                                    $opt_qy    = $addons_values[$getAddon["id"]];
                                    $amount    = ($amount * $opt_qy);
                                }
                            }
                            $amount_x    = $amount;

                            if($invoice["taxrate"] > 0 && $invoice["taxation_type"] == "inclusive")
                                $amount_x -= Money::get_inclusive_tax_amount($amount_x,$invoice["taxrate"]);

                            $start  = DateManager::Now();
                            $end    = $found["period"] == "none" ? DateManager::ata() : DateManager::next_date([
                                $found["period"] => $found["period_time"],
                            ]);

                            if(($found["period"] == "day" || $found["period"] == "week" || $found["period"] == "month" || $found["period"] == "year") && isset($invoice["data"]["subscribed"]) && $invoice["data"]["subscribed"])
                            {
                                $sub_hash = md5("addon"."|".$id."|".$found["period"]."|".$found["period_time"]);
                                if(isset($invoice["data"]["subscribed"][$sub_hash]))
                                    $sub_id = $invoice["data"]["subscribed"][$sub_hash];
                                else
                                    $sub_id = 0;
                            }
                            else
                                $sub_id = 0;

                            $addon_data = [
                                'subscription_id'   => $sub_id,
                                'invoice_id'        => $order["invoice_id"],
                                'owner_id'          => $order["id"],
                                'addon_id'          => $id,
                                'addon_name'        => $getAddon["name"],
                                'option_id'         => $selected,
                                'pmethod'           => $invoice["pmethod"],
                                'option_name'       => $opt_name,
                                'option_quantity'   => $opt_qy,
                                'period'            => $found["period"],
                                'period_time'       => $found["period_time"],
                                'cdate'             => $start,
                                'renewaldate'       => $start,
                                'duedate'           => $end,
                                'amount'            => $amount_x,
                                'cid'               => $order["amount_cid"],
                            ];
                            if($id == "whois-privacy")
                            {
                                $addon_data["addon_key"] = $id;
                                $addon_data["addon_id"] = 0;
                            }

                            if($invoice["status"] == "paid") $addon_data["status"] = "inprocess";

                            if(isset($getAddon["product_link"])){
                                $product_link                   = $getAddon["product_link"];
                                $eventDetector                  = '';
                                if($product_link["type"] == "domain") $eventDetector = 'DomainNameRegisterOrder';
                                elseif($product_link["type"] == "hosting") $eventDetector = 'HostingOrder';
                                elseif($product_link["type"] == "server") $eventDetector = 'ServerOrder';
                                elseif($product_link["type"] == "software") $eventDetector = 'SoftwareOrder';
                                elseif($product_link["type"] == "special") $eventDetector = 'SpecialProductOrder';
                                elseif($product_link["type"] == "sms") $eventDetector = 'SmsProductOrder';
                                $selection                      = [];
                                foreach($product_link["price"] AS $p_row)
                                    if($selected == $p_row["id"]) $selection = $p_row;


                                $data_opt                       = [
                                    'event'                     => $eventDetector,
                                    'type'                      => $product_link["type"],
                                    'id'                        => $product_link["id"],
                                    'selection'                 => $selection,
                                    'category'                  => $product_link["category_title"],
                                    'period'                    => $selection["period"],
                                    'period_time'               => $selection["time"],
                                ];

                                if(isset($order["options"]["domain"]))
                                    $data_opt["domain"] = $order["options"]["domain"];

                                if(isset($order["options"]["ip"]))
                                    $data_opt["ip"] = $order["options"]["ip"];

                                if(isset($order["options"]["hostname"]))
                                    $data_opt["hostname"] = $order["options"]["hostname"];


                                if($requirements) $data_opt["requirements"] = $requirements;

                                $data                           = [
                                    'name'                      => $product_link["title"],
                                    'quantity'                  => 1,
                                    'adds_amount'               => 0,
                                    'amount'                    => $addon_data["amount"],
                                    'total_amount'              => $addon_data["amount"],
                                    'amount_including_discount' => $addon_data["amount"],
                                    'options'                   => $data_opt,
                                ];



                                $insert             = self::process($data,$invoice);
                            }
                            else
                                $insert             = self::insert_addon($addon_data);
                            if($insert)
                            {
                                if($addon_data["option_quantity"] > 0) $addon_data["option_name"] = $addon_data["option_quantity"]."x ".$addon_data["option_name"];
                                $addon_data["id"] = $insert;
                                $addons_data[] = $addon_data;
                                $opt_module     = $found["module"] ?? false;
                                $ord_module     = $order["module"] && $order["module"] != "none" ? $order["module"] : false;

                                if($invoice["status"] == "paid" && $opt_module && $ord_module && isset($opt_module[$ord_module]) && $opt_module[$ord_module])
                                    self::$module_processing[] = [
                                        'order' => $order,
                                        'addon' => self::get_addon($insert),
                                    ];

                            }
                        }
                    }
                }
            }
            return $addons_data;
        }

        static function addons($order_id=0){
            $case = "CASE ";
            $case .= "WHEN t1.status = 'inprocess' THEN 1 ";
            $case .= "WHEN t1.status = 'waiting' THEN 2 ";
            $case .= "WHEN t1.status = 'active' THEN 3 ";
            $case .= "WHEN t1.status = 'suspended' THEN 4 ";
            $case .= "WHEN t1.status = 'cancelled' THEN 5 ";
            $case .= "WHEN t1.status_msg !='' THEN 0 ";
            $case .= "ELSE 5 ";
            $case .= "END AS rank";

            $select =  [
                't1.*',
                $case,
            ];

            $stmt   = Models::$init->db->select(implode(",",$select))->from("users_products_addons AS t1")->where("owner_id","=",$order_id);
            $stmt->order_by("rank ASC,t1.id DESC");
            return $stmt->build() ? $stmt->fetch_assoc() : false;
        }

        static function requirements($order_id=0){
            $stmt   = Models::$init->db->select()->from("users_products_requirements")->where("owner_id","=",$order_id);
            return $stmt->build() ? $stmt->fetch_assoc() : false;
        }

        static function requirement_process($invoice,$order, $requirements){
            if(!$order || !$requirements) return false;
            if(isset($invoice["user_data"]["lang"])) $ulang = $invoice["user_data"]["lang"];
            else
            {
                $udata          = User::getData($order["owner_id"],"lang");
                $ulang          = $udata->lang;
            }
            foreach ($requirements as $id=>$values){
                $getRequirement = Products::requirement($id,$ulang);
                if($getRequirement){
                    $options    = $getRequirement["options"];

                    $response2  = '';

                    $type = $getRequirement["type"];
                    if($type == "file" || $type == "input" || $type == "password" || $type == "textarea") $response = $values;
                    else{
                        if(!is_array($values)) $values = [$values];
                        $founds     = [];
                        $founds2    = [];
                        foreach($options AS $opt)
                        {
                            if(in_array($opt["id"],$values))
                            {
                                $founds[]   = $opt["name"];
                                $founds2[]  = isset($opt["mkey"]) ? $opt["mkey"] : '';
                            }
                        }
                        if($type == "radio" && $founds)
                        {
                            $response       = $founds[0];
                            $response2      = $founds2[0];
                        }
                        else
                        {
                            $response   = $founds ? Utility::jencode($founds) : '';
                            $response2  = $founds2 ? Utility::jencode($founds2) : '';
                        }
                    }
                    if($type == "file"){
                        $solve  = Utility::jdecode($response,true);
                        if($solve){
                            foreach($solve AS $so){
                                $fpathd  = "temp".DS.$so["file_path"];
                                $fpathr  = RESOURCE_DIR."uploads".DS."product-requirements".DS.$so["file_path"];
                                FileManager::file_rename($fpathd,$fpathr);
                            }
                        }
                    }
                    self::insert_requirement([
                        'owner_id' => $order["id"],
                        'requirement_id' => $id,
                        'requirement_name' => $getRequirement["name"],
                        'response_type' => $type,
                        'response'          => $response,
                        'response_mkey'     => $response2,
                        'module_co_names' => $getRequirement["module_co_names"],
                    ]);

                }
            }
            return false;
        }

        static function process($item=[], $invoice_id=false){
            $options    = $item["options"];
            $event      = isset($options["event"]) ? $options["event"] : '';
            if(is_array($invoice_id)) $invoice = $invoice_id;
            else $invoice = $invoice_id ? Invoices::get($invoice_id) : false;
            if($event){
                $processName = isset(self::$eventList[$event]) ? self::$eventList[$event] : false;
                if($processName) return self::$processName($item,$invoice);
            }
            return false;
        }

        private static function formation_builder($item, $invoice=false){
            $product_type = isset($item["options"]["type"]) ? $item["options"]["type"] : false;
            $product_id   = isset($item["options"]["id"]) ? $item["options"]["id"] : false;
            $user_id      = $invoice ? $invoice["user_id"] : $item["user_id"];
            if(!$product_type || !$product_id) return false;

            $ulang      = Bootstrap::$lang->clang;
            if(isset($invoice["user_data"]["lang"])) $ulang = $invoice["user_data"]["lang"];

            $product        =  Products::get($product_type,$product_id,$ulang);

            $cdate          = DateManager::Now();
            $date_zero      = "1881-05-19 00:00:00";
            $period         = $item["options"]["period"];
            $period_time    = $item["options"]["period_time"];
            $next_date      = $period == "none" ? false: DateManager::next_date([$period => $period_time],"Y-m-d H:i:s");
            $due_date       = $period == "none" ? $date_zero : $next_date;
            $renewaldate    = DateManager::Now();

            if(isset($item["options"]["cdate"])) $cdate = $item["options"]["cdate"];
            if(isset($item["options"]["duedate"])) $due_date = $item["options"]["duedate"];
            if(isset($item["options"]["renewaldate"])) $renewaldate = $item["options"]["renewaldate"];


            if($item["options"]["type"] == "domain" && $item["options"]["event"] == "DomainNameTransferRegisterOrder") $due_date = $date_zero;

            $set_amount                 = $item["amount"];
            $set_total_amount           = $item["total_amount"];
            if(isset($item["amount_including_discount"])){
                $set_amount             = $item["amount_including_discount"];
                $set_total_amount       = $set_amount;
                if(isset($item["adds_amount"]) && $item["adds_amount"]) $set_total_amount += $item["adds_amount"];
                $set_total_amount       = round($set_total_amount,2);
            }


            if(($period == "day" || $period == "week" || $period == "month" || $period == "year") && isset($invoice["data"]["subscribed"]) && $invoice["data"]["subscribed"])
            {
                $sub_hash = md5($product_type."|".$product_id."|".$period."|".$period_time);
                if(isset($invoice["data"]["subscribed"][$sub_hash]))
                    $order_data["subscription_id"] = $invoice["data"]["subscribed"][$sub_hash];
            }


            $order_data["owner_id"]     = $user_id;
            $order_data["invoice_id"]   = $invoice ? $invoice["id"] : 0;
            $order_data["type"]         = $product_type;
            if($product_type == "special")
                $order_data["type_id"]  = $product["type_id"];
            $order_data["product_id"]   = $product_id;
            $order_data["name"]         = isset($item["name"]) ? $item["name"] : '';
            $order_data["period"]       = $period;
            $order_data["period_time"]  = $period_time;
            $order_data["total_amount"] = $set_total_amount;
            $order_data["amount"]       = $set_amount;
            $order_data["amount_cid"]   = $invoice ? $invoice["currency"] : $item["currency"];
            $order_data["status"]       = "waiting";
            if(!(isset($item["user_pid"]) && $item["user_pid"]))
                $order_data["cdate"]        = $cdate;
            $order_data["renewaldate"]  = $renewaldate;
            $order_data["duedate"]      = $due_date;
            $order_data["pmethod"]      = $invoice ? $invoice["pmethod"] : '';
            if(isset($item["options"]["module"]) && $item["options"]["module"])
                $order_data["module"]       = $item["options"]["module"];
            else
                $order_data["module"]       = $product["module"] ? $product["module"] : "none";

            $order_data["options"]      = [];

            $locall                     = Config::get("general/local");
            if($product_type == "special"){
                $local_gname        = Products::getCategory($order_data["type_id"],$locall);
                if($local_gname) $local_gname = $local_gname["title"];
                $user_gname         = Products::getCategory($order_data["type_id"],$ulang);
                if($user_gname) $user_gname = $user_gname["title"];
            }else{
                $local_gname        = Bootstrap::$lang->get_cm("website/account_products/product-type-names/".$product_type,false,$locall);
                $user_gname         = Bootstrap::$lang->get_cm("website/account_products/product-type-names/".$product_type,false,$ulang);
            }

            $localc                 = false;
            $userc                  = false;
            if(isset($product["category_id"])){
                if($product_type == "special" && $product["category_id"] && $product["category_id"] == $order_data["type_id"])
                    $product["category_id"] = 0;
                if($product["category_id"]){
                    $localc         = Products::getCategory($product["category_id"],$locall);
                    $userc          = Products::getCategory($product["category_id"],$ulang);
                }
            }

            $order_data["options"]["group_name"] = $user_gname;
            $order_data["options"]["local_group_name"] = $local_gname;

            if($localc && $userc){
                $order_data["options"]["local_category_name"] = $localc["title"];
                $order_data["options"]["category_name"] = $userc["title"];
            }
            if(isset($product["category_id"]))
                $order_data["options"]["category_id"] = $product["category_id"];

            if(isset($item["options"]["selection"]) && $item["options"]["selection"])
                $order_data["options"]["selected_price"] = $item["options"]["selection"]["id"];

            if(isset($item["unread"])) $order_data["unread"] = $item["unread"];

            $auto_pay = 0;
            $user_info      = User::getInfo($user_id,['auto_pay']);
            if($user_info && strlen($user_info['auto_pay']) > 0) $auto_pay = (int) $user_info["auto_pay"];

            if(isset($invoice["checkout"]["data"]["pmethod_auto_pay"]) && $invoice["checkout"]["data"]["pmethod_auto_pay"])
                $auto_pay = 1;

            $order_data["auto_pay"] = $auto_pay;


            return [
                'ulang'     => $ulang,
                'product'   => $product,
                'data'      => $order_data,
            ];
        }

        static function domain_formation($item=[],$invoice=false){
            Helper::Load(["Events"]);

            $iopt           = $item["options"];

            if(isset($item["user_pid"]) && $item["user_pid"])
            {
                $insert         = $item["user_pid"];
                $builder        = self::formation_builder($item,$invoice);
                $product        = $builder["product"];
                $order_data     = $builder["data"];
                $order          = self::get($insert);
            }
            else{
                $builder        = self::formation_builder($item,$invoice);
                $order_data     = $builder["data"];
                $product        = $builder["product"];
                $oropt          = $order_data["options"];

                if(isset($iopt["domain"])) $oropt["domain"] = $iopt["domain"];
                else $oropt["domain"] = $iopt["sld"].".".$iopt["tld"];
                if(isset($iopt["tcode"])) $oropt["tcode"] = $iopt["tcode"];
                if(isset($iopt["dns"])) $oropt = array_merge($oropt,$iopt["dns"]);
                if(isset($iopt["sld"])) $oropt["name"] = $iopt["sld"];
                if(isset($iopt["tld"])) $oropt["tld"] = $iopt["tld"];
                if(isset($iopt["sld"]) && isset($iopt["tld"])) $oropt["domain"] = $iopt["sld"].".".$iopt["tld"];

                if(isset($iopt["wprivacy"]) && $iopt["wprivacy"]){

                    $oropt["whois_privacy"] = isset($iopt["whois_privacy"]) ? $iopt["whois_privacy"] : true;

                    $whidden_amount     = Config::get("options/domain-whois-privacy/amount");
                    $whidden_cid        = Config::get("options/domain-whois-privacy/cid");

                    if($order_data["module"] != "none"){
                        $mdata = Modules::Load("Registrars",$order_data["module"]);
                        if($mdata){
                            $whidden_amount     = $mdata["config"]["settings"]["whidden-amount"];
                            $whidden_cid        = $mdata["config"]["settings"]["whidden-currency"];
                        }
                    }

                    $whidden_price  = Money::exChange($whidden_amount,$whidden_cid,$invoice["currency"]);

                    if($whidden_price) $oropt["whois_privacy_endtime"] = DateManager::next_date(['year' => 1]);

                    if(isset($iopt["whois_privacy_endtime"])) $oropt["whois_privacy_endtime"] = $iopt["whois_privacy_endtime"];

                }
                if($product["dns_manage"]) $oropt["dns_manage"] = true;
                if($product["epp_code"]) $oropt["epp_code_manage"] = true;
                if($product["whois_privacy"]) $oropt["whois_manage"] = true;
                $oropt["transferlock"] = isset($iopt["transferlock"]) ? $iopt["transferlock"] : true;
                $oropt["cns_list"]          = isset($iopt["cns_list"]) ? $iopt["cns_list"] : [];
                if(isset($iopt["established"]) && $iopt["established"]) $oropt["established"] = true;

                if(isset($iopt["whois"]) && $iopt["whois"]){
                    $oropt["whois"] = $iopt["whois"];
                }
                elseif($invoice["user_data"]){
                    if(!isset($oropt["whois"]))
                    {
                        $contact_types          = ['registrant','administrative','technical','billing'];
                        $user_data              = $invoice["user_data"];
                        $whois                  = [];
                        $profiles               = User::whois_profiles($user_data["id"]);
                        $profile                = [];
                        if(sizeof($profiles) > 0) $profile = $profiles[0];

                        if($profile)
                        {
                            foreach($contact_types AS $ct)
                            {
                                $whois[$ct] = Utility::jdecode($profile["information"],true);
                                $whois[$ct]["profile_id"] = $profile["id"];
                            }
                        }
                        else
                        {
                            $zipcode                = AddressManager::generate_postal_code($user_data["address"]["country_code"]);

                            $state_x                  = Filter::transliterate2($user_data["address"]["counti"]);
                            $city_y                   = Filter::transliterate2($user_data["address"]["city"]);
                            $country_code             = $user_data["address"]["country_code"];

                            Filter::$transliterate_cc = $country_code;


                            if($country_code == "TR")
                            {
                                $state      = $state_x;
                                $city       = $city_y;
                            }
                            else
                            {
                                $state      = $city_y;
                                $city       = $state_x;
                            }

                            $x_whois  = [
                                'Name'              => Filter::transliterate2($user_data["full_name"]),
                                'FirstName'         => Filter::transliterate2($user_data["name"]),
                                'LastName'          => Filter::transliterate2($user_data["surname"]),
                                'Company'           => Filter::transliterate2($user_data["company_name"]),
                                'AddressLine1'      => Filter::transliterate2($user_data["address"]["address"]),
                                'AddressLine2'      => NULL,
                                'ZipCode'           => $user_data["address"]["zipcode"] ? $user_data["address"]["zipcode"] : $zipcode,
                                'State'             => $state,
                                'City'              => $city,
                                'Country'           => $country_code,
                                'EMail'             => $user_data["email"],
                                'Phone'             => $user_data["gsm"],
                                'PhoneCountryCode'  => $user_data["gsm_cc"],
                                'Fax'     => NULL,
                                'FaxCountryCode' => NULL,
                            ];
                            foreach($contact_types AS $ct) $whois[$ct] = $x_whois;
                        }

                        $oropt["whois"] = $whois;
                    }
                }

                $order_data["options"] = $oropt;
                $order_data["options"] = Utility::jencode($order_data["options"]);

                if(isset($iopt["transaction"]) && $iopt["transaction"] == "none"){
                    $order_data["unread"] = 1;
                    $order_data["status"] = "active";
                }

                $insert         = self::insert($order_data);
                if(!$insert) return false;

                $order          = self::get($insert);

                $defined_docs   = $iopt["docs"] ?? [];
                $manuel_docs    = false;

                if($defined_docs)
                {
                    foreach($defined_docs AS $d_id => $d)
                    {
                        if($d["file"])
                        {
                            $before_dir     = ROOT_DIR."temp".DS;
                            $after_dir      = RESOURCE_DIR."uploads".DS."attachments".DS;
                            $f_name         = $d["file"]["local_name"];
                            $d["file"]["path"] = str_replace($before_dir,$after_dir,$d["file"]["path"]);
                            $d["file"]["path"] = str_replace($before_dir,$after_dir,$d["file"]["path"]);
                            if($d["module_data"])
                                $d["module_data"]["value"] = str_replace($before_dir,$after_dir,$d["module_data"]["value"]);
                            FileManager::file_rename($before_dir.$f_name,$after_dir.$f_name);
                        }

                        $module_data    = $d["module_data"];
                        $file_data      = $d["file"];
                        $value          = $d["value"];

                        $p_module_data  = ($module_data ? Utility::jencode($module_data) : '');
                        $p_file_data    = ($file_data ? Utility::jencode($file_data) : '');

                        if(!$module_data) $manuel_docs = true;

                        WDB::insert("users_products_docs",[
                            'owner_id'      => $order["id"],
                            'doc_id'        => $d_id,
                            'name'          => $d['name'],
                            'value'         => $value ? Crypt::encode($value,Config::get("crypt/user")) : '',
                            'module_data'   => $p_module_data ? Crypt::encode($p_module_data,Config::get("crypt/user")) : '',
                            'file'          => $p_file_data ? Crypt::encode($p_file_data,Config::get("crypt/user")) : '',
                            'created_at'    => DateManager::Now(),
                            'updated_at'    => DateManager::Now(),
                            'status'        => $module_data ? "verified" : "pending",
                        ]);
                    }
                }
            }

            if($order["status"] == "waiting" && $invoice["status"] == "paid")
                if(self::MakeOperation("approve",$order,$product,false))
                    $order["status"] = "inprocess";

            if($iopt["event"] == "DomainNameTransferRegisterOrder"){
                if(!class_exists("Events")) Helper::Load(["Events"]);
                if($order["module"] == "none" && !Events::isCreated("operation","order",$order["id"],"transfer-request-to-us-with-manuel")){
                    Events::create([
                        'type'      => "operation",
                        'owner'     => "order",
                        'owner_id'  => $order["id"],
                        'name'      => "transfer-request-to-us-with-manuel",
                        'data'      => [
                            'domain' => $order["options"]["domain"],
                        ],
                    ]);
                }
                if($order["module"] != "none") self::$markers["formation"][$order["id"]] = ['transfer-request-to-us-with-api'];
                self::set($order["id"],['unread' => 1]);
            }

            if($invoice["status"] == "paid")
            {
                if($iopt["event"] == "DomainNameTransferRegisterOrder")
                    Hook::run("DomainTransfer",$order);
                else
                    Hook::run("DomainRegister",$order);
            }

            $check_docs     = self::detect_docs_in_domain($order);

            if($check_docs)
            {
                if(isset($order["options"]) && is_array($order["options"]))
                {
                    $order["options"]["verification"] = true;
                    self::set($order["id"],['options' => Utility::jencode($order["options"])]);
                    $order = self::get($order["id"]);
                }
            }

            if(isset($defined_docs) && isset($manuel_docs) && $defined_docs && !$manuel_docs) $check_docs = false;

            if($check_docs)
                self::$notification_processing[] = [
                    'name' => "domain_requires_doc",
                    'params' => [$order],
                ];

            if($iopt["addon_items"] ?? [])
            {
                $addon_process   = self::addon_process($invoice,$order,[
                    'addons'            => $iopt["addon_items"] ?? [],
                    'addons_values'     => $iopt["addons_values"] ?? [],
                    'requirements'      => $iopt["requirements"] ?? [],
                ]);
                $order["addons"] = $addon_process;
            }


            if((!isset($iopt["transaction"]) || $iopt["transaction"] != "none") && $order["status"] == "inprocess" && $order["module"] != "none" && !$check_docs)
            {
                self::$module_processing[] = [
                    'order' => $order,
                    'product' => $product,
                    'iopt'    => $iopt,
                ];
            }

            return $order;
        }

        static function hosting_formation($item=[],$invoice=false){

            $iopt           = $item["options"];

            if(isset($item["user_pid"]) && $item["user_pid"]){
                $insert = $item["user_pid"];
                $builder        = self::formation_builder($item,$invoice);
                $product        = $builder["product"];
                $popt           = $product["options"];
            }
            else{
                $builder        = self::formation_builder($item,$invoice);
                $order_data     = $builder["data"];
                $product        = $builder["product"];
                $oropt          = $order_data["options"];
                $popt           = $product["options"];

                if(isset($iopt["domain"])) $oropt["domain"] = $iopt["domain"];

                if(isset($popt["panel_type"])) $oropt["panel_type"] = $popt["panel_type"];
                if(isset($popt["panel_link"])) $oropt["panel_link"] = $popt["panel_link"];

                if(isset($popt["disk_limit"])) $oropt["disk_limit"] = $popt["disk_limit"] == "unlimited" ? $popt["disk_limit"] : $popt["disk_limit"];
                if(isset($popt["bandwidth_limit"])) $oropt["bandwidth_limit"] = $popt["bandwidth_limit"] == "unlimited" ? $popt["bandwidth_limit"] : $popt["bandwidth_limit"];
                if(isset($popt["email_limit"])) $oropt["email_limit"] = $popt["email_limit"];
                if(isset($popt["database_limit"])) $oropt["database_limit"] = $popt["database_limit"];
                if(isset($popt["addons_limit"])) $oropt["addons_limit"] = $popt["addons_limit"];
                if(isset($popt["subdomain_limit"])) $oropt["subdomain_limit"] = $popt["subdomain_limit"];
                if(isset($popt["ftp_limit"])) $oropt["ftp_limit"] = $popt["ftp_limit"];
                if(isset($popt["park_limit"])) $oropt["park_limit"] = $popt["park_limit"];
                if(isset($popt["max_email_per_hour"])) $oropt["max_email_per_hour"] = $popt["max_email_per_hour"];
                if(isset($popt["cpu_limit"])) $oropt["cpu_limit"] = $popt["cpu_limit"];
                if(isset($popt["server_features"])) $oropt["server_features"] = $popt["server_features"];
                if(isset($popt["dns"])) $oropt["dns"] = $popt["dns"];

                if(isset($product["module"]) && $product["module"] && $product["module"] != "none"){
                    $order_data["module"] = $product["module"];
                    if(isset($product["module_data"]["server_id"]) && $product["module_data"]["server_id"])
                        $oropt["server_id"] = $product["module_data"]["server_id"];
                    elseif(isset($popt["server_id"]) && $popt["server_id"])
                        $oropt["server_id"] = $popt["server_id"];


                    if(isset($product["module_data"]) && isset($product["module_data"]["create_account"]) && $product["module_data"]["create_account"])
                        $oropt["creation_info"] = $product["module_data"]["create_account"];

                    elseif(isset($product["module_data"]) && $product["module_data"])
                        $oropt["creation_info"] = $product["module_data"];

                    $group_id       = $popt["server_group_id"] ?? 0;
                    $group          = $group_id > 0  ? Products::get_server_group($group_id) : false;

                    if($group)
                    {
                        $catch_server = Products::catch_server_in_group($group["servers"],$group["fill_type"]);
                        if($catch_server)
                        {
                            $oropt["server_id"] = $catch_server;
                            $oropt["creation_info"]["server_id"] = $catch_server;
                        }
                    }
                }

                $order_data["options"] = Utility::jencode($oropt);

                $insert         = self::insert($order_data);
                if(!$insert) return false;
            }



            $order          = self::get($insert);

            if($order["status"] == "waiting" && $invoice["status"] == "paid")
                if(self::MakeOperation("approve",$order,$product,false)) $order["status"] = "inprocess";

            Helper::Load(["Notification"]);

            if($order["status"] == "inprocess" && $order["module"] && $order["module"] != "none" && isset($popt["auto_install"]) && $popt["auto_install"]){
                self::$module_processing[] = [
                    'order' => $order,
                    'product' => $product,
                ];
            }

            if(!(isset($item["user_pid"]) && $item["user_pid"])){
                if(isset($item["options"]["addons"]))
                    $order["addons"] = self::addon_process($invoice,$order,[
                        'addons' => $item["options"]["addons"],
                        'addons_values' => isset($item["options"]["addons_values"]) ? $item["options"]["addons_values"] : [],
                        'requirements' => isset($item["options"]["requirements"]) ? $item["options"]["requirements"] : [],
                    ]);
                if(isset($item["options"]["requirements"])) self::requirement_process($invoice,$order,$item["options"]["requirements"]);
            }

            return $order;
        }

        static function server_formation($item=[],$invoice=false){

            $iopt           = $item["options"];

            if(isset($item["user_pid"]) && $item["user_pid"]){
                $insert = $item["user_pid"];
                $builder        = self::formation_builder($item,$invoice);
                $product        = $builder["product"];

                $popt           = $product["options"];
                $poptl          = $product["optionsl"];
            }
            else{
                $builder        = self::formation_builder($item,$invoice);
                $order_data     = $builder["data"];
                $product        = $builder["product"];

                $popt           = $product["options"];
                $poptl          = $product["optionsl"];
                $iopt           = $item["options"];
                $oropt          = $order_data["options"];

                if(isset($iopt["hostname"])) $oropt["hostname"] = $iopt["hostname"];
                if(isset($iopt["ns1"])) $oropt["ns1"] = $iopt["ns1"];
                if(isset($iopt["ns2"])) $oropt["ns2"] = $iopt["ns2"];
                if(isset($iopt["password"])) $oropt["login"]["password"] = $iopt["password"];

                if(isset($popt["processor"]) && $popt["processor"]) $oropt["server_features"]["processor"] = $popt["processor"];
                if(isset($popt["ram"]) && $popt["ram"]) $oropt["server_features"]["ram"] = $popt["ram"];
                if(isset($popt["disk-space"]) && $popt["disk-space"]) $oropt["server_features"]["disk"] = $popt["disk-space"];
                if(isset($popt["raid"]) && $popt["raid"]) $oropt["server_features"]["raid"] = $popt["raid"];
                if(isset($popt["bandwidth"]) && $popt["bandwidth"]) $oropt["server_features"]["bandwidth"] = $popt["bandwidth"];
                if(isset($poptl["location"]) && $poptl["location"]) $oropt["server_features"]["location"] = $poptl["location"];

                if(isset($product["module"]) && $product["module"] && $product["module"] != "none"){
                    $order_data["module"] = $product["module"];
                    if(isset($product["module_data"]) && isset($popt["server_id"]))
                        if($popt["server_id"]) $oropt["server_id"] = $popt["server_id"];

                    if(isset($product["module_data"]) && $product["module_data"])
                        $oropt["creation_info"] = $product["module_data"];


                    $group_id       = $product["module_data"]["server_group_id"] ?? 0;
                    $group          = $group_id > 0  ? Products::get_server_group($group_id) : false;

                    if($group)
                    {
                        $catch_server = Products::catch_server_in_group($group["servers"],$group["fill_type"]);
                        if($catch_server)
                        {
                            $oropt["server_id"] = $catch_server;
                            $oropt["creation_info"]["server_id"] = $catch_server;
                        }
                    }

                }


                $order_data["options"] = Utility::jencode($oropt);

                $insert         = self::insert($order_data);
                if(!$insert) return false;
            }

            $order          = self::get($insert);

            if($order["status"] == "waiting" && $invoice["status"] == "paid")
                if(self::MakeOperation("approve",$order,$product,false)) $order["status"] = "inprocess";

            Helper::Load(["Notification"]);

            if($order["status"] == "inprocess" && isset($popt["auto_install"]) && $popt["auto_install"])
                if($order["module"] && $order["module"] != "none")
                    self::$module_processing[] = [
                        'order' => $order,
                        'product' => $product,
                    ];


            if(!(isset($item["user_pid"]) && $item["user_pid"])){
                if(isset($item["options"]["addons"]))
                    $order["addons"] = self::addon_process($invoice,$order,[
                        'addons' => $item["options"]["addons"],
                        'addons_values' => isset($item["options"]["addons_values"]) ? $item["options"]["addons_values"] : [],
                        'requirements' => isset($item["options"]["requirements"]) ? $item["options"]["requirements"] : [],
                    ]);
                if(isset($item["options"]["requirements"])) self::requirement_process($invoice,$order,$item["options"]["requirements"]);
            }

            return $order;
        }

        static function software_formation($item=[], $invoice=[]){
            $iopt           = $item["options"];
            if(isset($item["user_pid"]) && $item["user_pid"])
            {
                $insert         = $item["user_pid"];
                $builder        = self::formation_builder($item,$invoice);
                $product        = $builder["product"];
            }
            else
            {
                $builder        = self::formation_builder($item,$invoice);
                $order_data     = $builder["data"];
                $product        = $builder["product"];
                $oropt          = $order_data["options"];

                if(isset($iopt["domain"])) $oropt["domain"] = $iopt["domain"];

                if(isset($product["options"]["license_type"]) && $product["options"]["license_type"] == "key")
                {
                    $code           = self::generate_software_key($product["options"]);
                    $oropt["code"] = $code;
                }

                $order_data["options"] = Utility::jencode($oropt);

                $insert         = self::insert($order_data);
                if(!$insert) return false;
            }
            $order          = self::get($insert);

            if($order["status"] == "waiting" && $invoice["status"] == "paid")
                if(self::MakeOperation("approve",$order,$product,false)) $order["status"] = "inprocess";

            if($order["status"] == "inprocess" && isset($product["options"]["auto_approval"])){
                if($product["options"]["auto_approval"]){
                    if(self::MakeOperation("active",$order,$product)) $order["status"] = "active";
                    elseif(self::$message)
                        self::$notification_processing[] = [
                            'name' => "failed_order_activation",
                            'params' => [$order["id"]],
                        ];
                }
            }

            if(!(isset($item["user_pid"]) && $item["user_pid"])){
                if(isset($item["options"]["addons"]))
                    $order["addons"] = self::addon_process($invoice,$order,[
                        'addons' => $item["options"]["addons"],
                        'addons_values' => isset($item["options"]["addons_values"]) ? $item["options"]["addons_values"] : [],
                        'requirements' => isset($item["options"]["requirements"]) ? $item["options"]["requirements"] : [],
                    ]);
                if(isset($item["options"]["requirements"])) self::requirement_process($invoice,$order,$item["options"]["requirements"]);
            }
            return $order;
        }

        static function special_formation($item=[],$invoice=false){
            $iopt           = $item["options"];

            $builder        = self::formation_builder($item,$invoice);

            $product        = $builder["product"];
            $order_data     = $builder["data"];
            $oropt          = $order_data["options"];
            $popt           = $product["options"];
            $popt_l         = $product["optionsl"];

            if(isset($item["user_pid"]) && $item["user_pid"]) $insert = $item["user_pid"];
            else{
                if(isset($popt_l["delivery_title_name"]) && $popt_l["delivery_title_name"])
                    $oropt["delivery_title_name"] = $popt_l["delivery_title_name"];

                if(isset($popt_l["delivery_title_description"]) && $popt_l["delivery_title_description"])
                    $oropt["delivery_title_description"] = $popt_l["delivery_title_description"];

                $new_iopt   = $iopt;
                unset($new_iopt["event"]);
                unset($new_iopt["type"]);
                unset($new_iopt["id"]);
                unset($new_iopt["period"]);
                unset($new_iopt["period_time"]);
                unset($new_iopt["category"]);
                unset($new_iopt["category_route"]);
                if(isset($new_iopt["selection"])) unset($new_iopt["selection"]);
                if(isset($new_iopt["addons"])) unset($new_iopt["addons"]);
                if(isset($new_iopt["requirements"])) unset($new_iopt["requirements"]);
                $oropt      = array_replace_recursive($oropt,$new_iopt);


                if(isset($product["module"]) && $product["module"] && $product["module"] != "none"){
                    $order_data["module"] = $product["module"];
                    if(isset($product["module_data"]) && $product["module_data"])
                        $oropt["creation_info"] = $product["module_data"];
                }

                $order_data["options"] = Utility::jencode($oropt);

                $insert         = self::insert($order_data);
                if(!$insert) return false;
            }
            $order          = self::get($insert);

            if($order["status"] == "waiting" && $invoice["status"] == "paid")
                if(self::MakeOperation("approve",$order,$product,false)) $order["status"] = "inprocess";

            Helper::Load(["Notification"]);

            if($order["status"] == "inprocess" && isset($popt["auto_install"]) && $popt["auto_install"])
                if($order["module"] && $order["module"] != "none")
                    self::$module_processing[] = [
                        'order' => $order,
                        'product' => $product,
                    ];


            if(!(isset($item["user_pid"]) && $item["user_pid"])){
                if(isset($item["options"]["addons"]))
                    $order["addons"] = self::addon_process($invoice,$order,[
                        'addons' => $item["options"]["addons"],
                        'addons_values' => isset($item["options"]["addons_values"]) ? $item["options"]["addons_values"] : [],
                        'requirements' => isset($item["options"]["requirements"]) ? $item["options"]["requirements"] : [],
                    ]);
                if(isset($item["options"]["requirements"])) self::requirement_process($invoice,$order,$item["options"]["requirements"]);
            }

            return $order;
        }

        static function sms_formation($item=[],$invoice=false){

            $iopt           = $item["options"];

            if(isset($item["user_pid"]) && $item["user_pid"]){
                $insert = $item["user_pid"];
                $builder        = self::formation_builder($item,$invoice);
                $product        = $builder["product"];

                $order          = self::get($insert);

            }else{
                $builder        = self::formation_builder($item,$invoice);
                $order_data     = $builder["data"];
                $product        = $builder["product"];
                $oropt          = $order_data["options"];

                if(isset($iopt["fields"]) && $iopt["fields"]){
                    if(isset($iopt["fields"]["attachments"]) && $iopt["fields"]["attachments"]){
                        $attachments    = $iopt["fields"]["attachments"];
                        if($attachments){
                            foreach($attachments AS $attachment){
                                $fpathd  = "temp".DS.$attachment["file_path"];
                                $fpathr  = RESOURCE_DIR."uploads".DS."attachments".DS.$attachment["file_path"];
                                FileManager::file_rename($fpathd,$fpathr);
                            }
                        }
                    }
                    $oropt["name"]      = $iopt["fields"]["name"];
                    $oropt["birthday"]  = $iopt["fields"]["birthday"];
                    $oropt["identity"]  = $iopt["fields"]["identity"];
                }

                $order_data["options"] = Utility::jencode($oropt);

                $insert         = self::insert($order_data);
                if(!$insert) return false;

                $order          = self::get($insert);

                if($order["status"] == "waiting" && $invoice["status"] == "paid")
                    if(self::MakeOperation("approve",$order,$product,false)) $order["status"] = "inprocess";

                if(isset($iopt["fields"]["origin"]) && $iopt["fields"]["origin"]){
                    Models::$init->db->insert("users_sms_origins",[
                        'user_id'   => $order["owner_id"],
                        'pid'       => $order["id"],
                        'name'      => $iopt["fields"]["origin"],
                        'ctime'     => DateManager::Now(),
                        'attachments' => isset($attachments) && $attachments ? Utility::jencode($attachments) : '',
                    ]);
                }
            }
            return $order;
        }

        static function domain_modification($item=[],$invoice=[]){
            $iopt           = isset($item["options"]) ? $item["options"] : [];
            $event          = isset($iopt["event"]) ? $iopt["event"] : false;
            $event_data     = isset($iopt["event_data"]) ? $iopt["event_data"] : [];
            if(isset($event_data["usproduct_id"])) $order = self::get($event_data["usproduct_id"]);
            else $order     = isset($item["user_pid"]) && $item["user_pid"] ? self::get($item["user_pid"]) : [];
            $orderopt       = isset($order["options"]) ? $order["options"] : [];
            if($event == "RenewalDomain" && $event_data && $order){

                Helper::Load(["Notification","Events"]);

                $year         = $event_data["year"];

                $order_duedate       = $order["duedate"];
                $order_p_duedate     = $order["process_exemption_date"];

                $new_duedate        = DateManager::next_date([
                    $order_duedate,
                    'year'  => $year,
                ]);


                if($invoice["status"] == "paid")
                {
                    $set_order["period_time"] = $year;
                    $set_order["duedate"] = $new_duedate;
                    $set_order["process_exemption_date"] = str_replace("00:00:00","23:59:59",DateManager::ata());
                    $set_order["status"]        = "active";
                    $set_order["unread"]        = 0;
                    $set_order["amount"]        = $item["amount"];
                    $set_order["total_amount"]  = $item["amount"];
                    $set_order["amount_cid"]    = $invoice["currency"];

                    if($order["module"] != "none"){
                        $set_order["status_msg"] = '';
                        $set_order["unread"] = 1;

                        self::$module_processing[] = [
                            'order'         => $order,
                            'product'       => false,
                            'event'         => "renewal",
                            'event_data'    => [
                                'year'        => $year,
                                'old_duedate' => $order["duedate"],
                                'new_duedate' => $new_duedate,
                            ]
                        ];
                    }
                    else
                        Events::create([
                            'type' => "operation",
                            'owner' => "order",
                            'owner_id' => $order["id"],
                            'name' => "domain-extended",
                            'data' => [
                                'start'  => $order["duedate"],
                                'end'    => $new_duedate,
                                'year'   => $year,
                                'domain' => $orderopt["domain"],
                            ],
                        ]);
                }

                $set_order["options"] = Utility::jencode($orderopt);


                if($invoice["status"] == "paid" && isset($invoice["checkout"]["data"]["pmethod_stored_card"]))
                {
                    $auto_pay = isset($order["auto_pay"]) ? $order["auto_pay"] : 0;

                    if(isset($invoice["checkout"]["data"]["pmethod_auto_pay"]) && $invoice["checkout"]["data"]["pmethod_auto_pay"])
                        $auto_pay = 1;
                    $set_order["auto_pay"] = $auto_pay;
                }


                $sub_added      = false;
                if(isset($invoice["data"]["subscribed"]) && $invoice["data"]["subscribed"])
                {
                    $sub_hash = md5($order["type"]."|".$order["product_id"]."|year|".$year);
                    if(isset($invoice["data"]["subscribed"][$sub_hash]))
                    {
                        $set_order["subscription_id"] = $invoice["data"]["subscribed"][$sub_hash];
                        $set_order["auto_pay"] = 0;
                        $sub_added = true;
                    }
                }

                /*
                if($order["subscription_id"] > 0 && !$sub_added && !isset($invoice["data"]["paid_by_subscription"]))
                {
                    $s      = self::get_subscription($order["subscription_id"]);
                    self::cancel_subscription($s,$order);
                }
                */

                self::set($order["id"],$set_order);

                if($invoice["status"] == "paid")
                    self::$notification_processing[] = [
                        'name' => "domain_has_been_extended",
                        'params' => [$order["id"]],
                    ];

                if(isset($set_order["status_msg"]) && $set_order["status_msg"])
                    self::$notification_processing[] = [
                        'name' => "failed_order_activation",
                        'params' => [$order["id"]],
                    ];

                $order = self::get($order["id"]);

                if($invoice["status"] == "paid") Hook::run("DomainRenewal",$order);

                return $order;
            }
            elseif($event == "ModifyDomainWhoisPrivacy"){
                if($invoice["status"] == "paid"){

                    Helper::Load(["Events","Notification"]);

                    $set_order  = [];
                    $orderopt["whois_privacy"] = true;
                    $orderopt["whois_privacy_endtime"] = DateManager::next_date(['year' => 1]);

                    if($orderopt != $order["options"]) $set_order["options"] = Utility::jencode($orderopt);

                    if(isset($invoice["checkout"]["data"]["pmethod_stored_card"]))
                    {
                        $auto_pay = isset($order["auto_pay"]) ? $order["auto_pay"] : 0;

                        if(isset($invoice["checkout"]["data"]["pmethod_auto_pay"]) && $invoice["checkout"]["data"]["pmethod_auto_pay"])
                            $auto_pay = 1;
                        $set_order["auto_pay"] = $auto_pay;
                    }

                    if($set_order) self::set($order["id"],$set_order);

                    $ulang  = $invoice["user_data"]["lang"];

                    $isAddon   = Models::$init->db->select("id")->from("users_products_addons");
                    $isAddon->where("owner_id","=",$order["id"],"&&");
                    $isAddon->where("addon_key","=","whois-privacy");
                    $isAddon   = $isAddon->build() ? $isAddon->getObject()->id : false;

                    if($isAddon){
                        self::set_addon($isAddon,[
                            "invoice_id"    => $invoice["id"],
                            "renewaldate"   => DateManager::Now(),
                            "duedate"       => $orderopt["whois_privacy_endtime"],
                            "amount"        => $item["amount"],
                            "cid"           => $invoice["currency"],
                            "status"        => "active",
                            "unread"        => 1,
                        ]);
                    }else
                        self::insert_addon([
                            "invoice_id"    => $invoice["id"],
                            "owner_id"      => $order["id"],
                            "addon_key"     => "whois-privacy",
                            "addon_id"      => 0,
                            "addon_name"    => Bootstrap::$lang->get_cm("website/account_products/whois-privacy",false,$ulang),
                            "option_id"     => 0,
                            "option_name"   => Bootstrap::$lang->get("needs/iwwant",$ulang),
                            "period"        => "year",
                            "period_time"   => 1,
                            "cdate"         => DateManager::Now(),
                            "renewaldate"   => DateManager::Now(),
                            "duedate"       => $orderopt["whois_privacy_endtime"],
                            "amount"        => $item["amount"],
                            "cid"           => $invoice["currency"],
                            "status"        => "active",
                            "unread"        => 1,
                        ]);


                    if($order["module"] != "none"){
                        $set_order = [];

                        $set_order["status_msg"] = '';

                        $module_process = self::ModuleHandler($order,false,"purchase_privacy");
                        self::$module_processing[] = [
                            'order'         => $order,
                            'product'       => false,
                            'event'         => "purchase_privacy",
                            'event_data'    => [],
                        ];
                        if($set_order) self::set($order["id"],$set_order);
                    }
                    else
                    {
                        $evID   = Events::isCreated("operation","order",$order["id"],"modify-whois-privacy-enable","pending");

                        if(!$evID)
                            $evID = Events::create([
                                'type' => "operation",
                                'owner' => "order",
                                'owner_id' => $order["id"],
                                'name' => "modify-whois-privacy-enable",
                                'data' => [
                                    'domain' => $orderopt["domain"],
                                ],
                            ]);

                        self::$notification_processing[] = [
                            'name' => "need_manually_transaction",
                            'params' => [$order,$evID],
                        ];
                    }

                }
                return self::get($order["id"]);
            }
        }

        static function hosting_modification($item=[],$invoice=[]){
            $iopt           = isset($item["options"]) ? $item["options"] : [];
            $event          = isset($iopt["event"]) ? $iopt["event"] : false;
            $event_data     = isset($iopt["event_data"]) ? $iopt["event_data"] : [];
            if(isset($event_data["usproduct_id"])) $order = self::get($event_data["usproduct_id"]);
            else $order     = isset($item["user_pid"]) && $item["user_pid"] ? self::get($item["user_pid"]) : [];
            $orderopt       = isset($order["options"]) ? $order["options"] : [];
        }

        static function server_modification($item=[],$invoice=[]){
            $iopt           = isset($item["options"]) ? $item["options"] : [];
            $event          = isset($iopt["event"]) ? $iopt["event"] : false;
            $event_data     = isset($iopt["event_data"]) ? $iopt["event_data"] : [];
            if(isset($event_data["usproduct_id"])) $order = self::get($event_data["usproduct_id"]);
            else $order     = isset($item["user_pid"]) && $item["user_pid"] ? self::get($item["user_pid"]) : [];
            $orderopt       = isset($order["options"]) ? $order["options"] : [];
        }

        static function special_modification($item=[],$invoice=[]){
            $iopt           = isset($item["options"]) ? $item["options"] : [];
            $event          = isset($iopt["event"]) ? $iopt["event"] : false;
            $event_data     = isset($iopt["event_data"]) ? $iopt["event_data"] : [];
            if(isset($event_data["usproduct_id"])) $order = self::get($event_data["usproduct_id"]);
            else $order     = isset($item["user_pid"]) && $item["user_pid"] ? self::get($item["user_pid"]) : [];
            $orderopt       = isset($order["options"]) ? $order["options"] : [];
        }

        static function order_modification($item=[],$invoice=[]){
            $iopt           = isset($item["options"]) ? $item["options"] : [];
            $event          = isset($iopt["event"]) ? $iopt["event"] : false;
            $event_data     = isset($iopt["event_data"]) ? $iopt["event_data"] : [];
            if(isset($event_data["usproduct_id"])) $order = self::get($event_data["usproduct_id"]);
            else $order     = isset($item["user_pid"]) && $item["user_pid"] ? self::get($item["user_pid"]) : [];
            $orderopt       = isset($order["options"]) ? $order["options"] : [];
            if($event == "OrderUpgrade" && $event_data){

                $getRequest     = Models::$init->db->select()->from("users_products_updowngrades");
                $getRequest->where("owner_id","=",$order["id"],"&&");
                $getRequest->where("type","=","up","&&");
                $getRequest->where("status","=","waiting");
                $getRequest = $getRequest->build() ? $getRequest->getAssoc() : false;
                if(!$getRequest) return false;
                $getRequest["options"] = Utility::jdecode($getRequest["options"],true);

                /*
                $locall         = Config::get("general/local");
                $old_product    = Products::get($order["type"],$getRequest["old_pid"],$locall);
                $new_product    = Products::get($order["type"],$getRequest["new_pid"],$locall);
                */



                Helper::Load(["Notification"]);

                if($invoice["status"] == "paid" && isset($invoice["checkout"]["data"]["pmethod_stored_card"]))
                {
                    $set_order      = [];
                    $auto_pay       = isset($order["auto_pay"]) ? $order["auto_pay"] : 0;

                    if(isset($invoice["checkout"]["data"]["pmethod_auto_pay"]) && $invoice["checkout"]["data"]["pmethod_auto_pay"])
                        $auto_pay = 1;
                    $set_order["auto_pay"] = $auto_pay;
                    self::set($order["id"],$set_order);
                }
                if($invoice["status"] == "paid"){
                    $apply          = self::updown($order,$getRequest["type"],[
                        'new_pid' => $getRequest["new_pid"],
                        'new_period' => $getRequest["options"]["new_period"],
                        'new_period_time' => $getRequest["options"]["new_period_time"],
                        'new_amount' => $getRequest["options"]["new_amount"],
                    ]);
                    if($apply){

                        self::set_updown($getRequest["id"],$apply);

                        if($apply["status"] == "inprocess")
                            self::$notification_processing[] = [
                                'name' => "need_manually_upgrading",
                                'params' => [$getRequest],
                            ];
                        if($apply["status"] == "completed")
                            self::$notification_processing[] = [
                                'name' => "customer_has_upgraded",
                                'params' => [$getRequest],
                            ];
                    }
                    else{
                        self::set($order["id"],['status' => "inprocess"]);
                        Models::$init->db->update("users_products_updowngrades",[
                            'status_msg' => self::$message,
                        ])->where("id","=",$getRequest["id"])->save();
                    }

                    $period         = $order["period"];
                    $period_time    = $order["period_time"];


                    if($order["subscription_id"] != '' && $order["pmethod"] && $order["pmethod"] != "none") self::cancel_subscription(self::get_subscription($order["subscription_id"]));


                    if(($period == "day" || $period == "week" || $period == "month" || $period == "year") && isset($invoice["data"]["subscribed"]) && $invoice["data"]["subscribed"])
                    {
                        $sub_hash = md5($order["type"]."|".$order["product_id"]."|".$period."|".$period_time);

                        if(isset($invoice["data"]["subscribed"][$sub_hash]))
                            self::set($order["id"],['auto_pay' => 0,'subscription_id' =>  $invoice["data"]["subscribed"][$sub_hash]]);
                    }
                }
                else
                    self::set($order["id"],['status' => "inprocess"]);

                return self::get($order["id"]);
            }
            elseif($event == "ExtendOrderPeriod"){
                if($order["type"] == "domain"){
                    $item['options']['event']  = "RenewalDomain";
                    $item['options']['event_data']['year']  = $order["period_time"];
                    return self::domain_modification($item,$invoice);
                }
                if($invoice["status"] == "paid"){

                    $period         = $order["period"];
                    $period_time    = $order["period_time"];

                    if($event_data && isset($event_data["period"]) && isset($event_data["period_time"])){
                        $period          = $event_data["period"];
                        $period_time     = $event_data["period_time"];
                    }

                    $renewal_type       = Config::get("options/order-renewal-type");

                    if($order["period"] == "hour") $renewal_type = "datepaid";

                    $order_p_duedate     = $order["process_exemption_date"];
                    $order_duedate       = $order["duedate"];

                    $type_duedate      = DateManager::next_date([
                        $order_duedate,
                        $period => $period_time,
                    ]);
                    $type_datepaid       = DateManager::next_date([$period => $period_time]);

                    if($order["status"] == "active")
                    {
                        if(DateManager::strtotime($type_duedate) > DateManager::strtotime())
                        {
                            if($renewal_type == "datepaid" && DateManager::strtotime($order_duedate) < DateManager::strtotime())
                                $next_due_date       = $type_datepaid;
                            elseif($renewal_type == "duedate")
                                $next_due_date = $type_duedate;
                            else
                                $next_due_date = $type_duedate;
                        }
                        else
                            $next_due_date       = $type_duedate;
                    }
                    elseif($order["status"] == "suspended")
                        $next_due_date       = $renewal_type == "duedate" ? $type_duedate : $type_datepaid;
                    else
                    {
                        if($order_p_duedate == DateManager::zero() || $order_p_duedate == "1881-05-19 23:59:59" || $order_p_duedate == "1881-05-19 00:00:00")
                            $next_due_date           = $type_datepaid;
                        else
                            $next_due_date          = $type_duedate;
                    }

                    if(!isset($next_due_date) || !$next_due_date) $next_due_date = $renewal_type == "duedate" ? $type_duedate : $type_datepaid;

                    $set_data       = [
                        'pmethod'     => $invoice["pmethod"],
                        'period'      => $period,
                        'period_time' => $period_time,
                        'renewaldate' => DateManager::Now(),
                        'duedate'     => $period == 'none' ? DateManager::ata() : $next_due_date,
                        'process_exemption_date' => $period != 'none' && DateManager::strtotime($next_due_date) > DateManager::strtotime() ? str_replace("00:00:00","23:59:59",DateManager::ata()) : $order_p_duedate,
                        'unread'        => $order["module"] == "none" ? 0 : 1,
                    ];

                    if(($period == "day" || $period == "week" || $period == "month" || $period == "year") && isset($invoice["data"]["subscribed"]) && $invoice["data"]["subscribed"])
                    {
                        $sub_hash = md5($order["type"]."|".$order["product_id"]."|".$period."|".$period_time);

                        if(isset($invoice["data"]["subscribed"][$sub_hash]))
                        {
                            $set_data["subscription_id"] = $invoice["data"]["subscribed"][$sub_hash];
                            $set_data["auto_pay"] = 0;
                        }
                    }


                    if($order["options"]["pricing-type"] != 2)
                    {
                        if(isset($item["amount"])) $set_data["amount"] = $item["amount"];
                        if(isset($item["total_amount"])) $set_data["total_amount"] = $item["total_amount"];
                        $set_data["amount_cid"] = $invoice["currency"];
                    }



                    if($order["type"] == "software"){
                        $set_data["status"] = "active";
                        $set_data["unread"] = 1;
                    }

                    if(isset($item["options"]["selection"]) && $item["options"]["selection"])
                    {
                        $orderopt["selected_price"] = $item["options"]["selection"]["id"];
                        $set_data["amount"]         = $item["options"]["selection"]["amount"];
                        $set_data["amount_cid"]     = $item["options"]["selection"]["cid"];
                    }

                    $set_data["options"] = Utility::jencode($orderopt);

                    if(isset($invoice["checkout"]["data"]["pmethod_stored_card"]))
                    {
                        $auto_pay       = isset($order["auto_pay"]) ? $order["auto_pay"] : 0;

                        if(isset($invoice["checkout"]["data"]["pmethod_auto_pay"]) && $invoice["checkout"]["data"]["pmethod_auto_pay"]) $auto_pay = 1;
                        $set_data["auto_pay"] = $auto_pay;
                    }

                    self::set($order["id"],$set_data);

                    /*
                    $cur_user   = [];
                    $cur_admin  = [];

                    if(UserManager::LoginCheck("member"))
                        $cur_user = UserManager::LoginData();
                    elseif(UserManager::LoginCheck("admin"))
                        $cur_admin = UserManager::LoginData();
                    */

                    Orders::add_history(0,$order["id"],'order-duedate-changed',[
                        'old' => DateManager::format(Config::get("options/date-format")." H:i",$order['duedate']),
                        'new' => DateManager::format(Config::get("options/date-format")." H:i",$set_data['duedate']),
                        'period' => $period,
                        'period_time' => $period_time,
                    ]);

                    // In case, you need it.
                    /*'log' => [
                            'invoice'   => $invoice,
                            'cur_user'  => $cur_user["id"] ?? 0,
                            'cur_admin' => $cur_admin["id"] ?? 0,
                            'ip'        => UserManager::GetIP(),
                            'uri'       => $_SERVER["REQUEST_URI"] ?? '',
                            'request'   => $_REQUEST,
                            'trace' => debug_backtrace(),
                        ]*/

                    Helper::Load(["Notification","Events","User","Products"]);
                    $product = Products::get($order["type"],$order["product_id"]);
                    User::affiliate_apply_transaction('renewal',$order,$product);

                    if(!isset(self::$notification_logs["order_has_been_extended"][$order["id"]]))
                    {
                        self::$notification_processing[] = [
                            'name' => "order_has_been_extended",
                            'params' => [$order["id"]],
                        ];

                        self::$notification_logs["order_has_been_extended"][$order["id"]] = true;
                    }

                    if($order["status"] != "active")
                    {
                        #self::MakeOperation("active",$order);
                        self::$module_processing[] = [
                            'order'         => $order,
                            'product'       => $product,
                        ];
                    }

                    $if_ex = true;
                    if(!defined("SOFTWARE_PRODUCT_NOTIFICATION"))
                        $if_ex = $order["type"] != "software";


                    if($order["module"] == "none" && $if_ex){
                        self::set($order["id"],['unread' => 0]);
                        if(!Events::isCreated('operation','order',$order["id"],'service-time-renewed','pending'))
                        {
                            $evID  = Events::create([
                                'type' => "operation",
                                'owner' => "order",
                                'owner_id' => $order["id"],
                                'name'     => "service-time-renewed",
                                'data'     => [
                                    'period' => $order["period"],
                                    'period_time' => $order["period_time"],
                                ],
                            ]);
                            self::$notification_processing[] = [
                                'name' => "need_manually_transaction",
                                'params' => [$order["id"],$evID],
                            ];
                        }
                    }

                    $order = self::get($order["id"]);

                    if($order["module"] != "none" && $order["type"] == "special"){
                        /*
                        $process = self::ModuleHandler($order,false,"extend",[
                            'period' => $period,
                            'time' => $period_time,
                        ]);
                        if((!$process || $process == "failed") && self::$message)
                            self::set($order["id"],['unread' => 0,'status_msg' => self::$message]);
                        */
                        self::$module_processing[] = [
                            'order'         => $order,
                            'product'       => $product,
                            'event'         => "extend",
                            'event_data'    => [
                                'period' => $period,
                                'time' => $period_time,
                            ]
                        ];

                    }
                }
                return $order;
            }
            elseif($event == "ExtendAddonPeriod" && $event_data){
                if($invoice["status"] == "paid"){
                    $addon  = self::get_addon($event_data["addon_id"]);

                    $period                 = $addon["period"];
                    $period_time            = $addon["period_time"];
                    $order_duedate          = $addon["duedate"];
                    $renewal_type           = Config::get("options/order-renewal-type");

                    if($addon["period"] == "hour") $renewal_type = "datepaid";

                    $type_duedate      = DateManager::next_date([
                        $order_duedate,
                        $period => $period_time,
                    ]);
                    $type_datepaid       = DateManager::next_date([$period => $period_time]);

                    if($addon["status"] == 'active' || $addon["status"] == "completed")
                    {
                        if(DateManager::strtotime($type_duedate) > DateManager::strtotime())
                        {
                            if($renewal_type == "datepaid" && DateManager::strtotime($order_duedate) < DateManager::strtotime())
                                $next_due_date       = $type_datepaid;
                            elseif($renewal_type == "duedate")
                                $next_due_date = $type_duedate;
                        }
                        else
                            $next_due_date       = $type_duedate;
                    }
                    else
                        $next_due_date = $type_datepaid;

                    $set_data = [
                        'renewaldate' => DateManager::Now(),
                        'duedate'     => $next_due_date,
                        'pmethod'     => $invoice["pmethod"],
                    ];

                    if(isset($item["amount"])) $set_data["amount"] = $item["amount"];
                    $set_data["cid"] = $invoice["currency"];


                    $sub_added      = false;

                    if(($period == "day" || $period == "week" || $period == "month" || $period == "year") && isset($invoice["data"]["subscribed"]) && $invoice["data"]["subscribed"])
                    {
                        $sub_hash = md5("addon"."|".$addon["addon_id"]."|".$addon["period"]."|".$addon["period_time"]);
                        if(isset($invoice["data"]["subscribed"][$sub_hash]))
                        {
                            $set_data["subscription_id"] = $invoice["data"]["subscribed"][$sub_hash];
                            $set_data["auto_pay"] = 0;
                            $sub_added = true;
                        }
                    }

                    /*
                    if($addon["subscription_id"] > 0 && !$sub_added && !isset($invoice["data"]["paid_by_subscription"]))
                    {
                        $s      = self::get_subscription($addon["subscription_id"]);
                        self::cancel_subscription($s,false,$addon);
                    }
                    */


                    self::set_addon($addon["id"],$set_data);

                    if(isset($invoice["checkout"]["data"]["pmethod_stored_card"]))
                    {
                        $set_order      = [];
                        $auto_pay       = isset($order["auto_pay"]) ? $order["auto_pay"] : 0;

                        if(isset($invoice["checkout"]["data"]["pmethod_auto_pay"]) && $invoice["checkout"]["data"]["pmethod_auto_pay"])
                            $auto_pay = 1;
                        $set_order["auto_pay"] = $auto_pay;
                        self::set($order["id"],$set_order);
                    }


                    $handle = self::MakeOperationAddon('active',$order,$addon["id"]);

                    Helper::Load(["Notification"]);

                    if(!isset(self::$notification_logs["order_has_been_extended"][$order["id"]."-".$addon["id"]]))
                    {
                        self::$notification_processing[] = [
                            'name' => "order_has_been_extended",
                            'params' => [$order,$addon["id"]],
                        ];
                        self::$notification_logs["order_has_been_extended"][$order["id"]."-".$addon["id"]] = true;
                    }

                    if($handle && !(is_string($handle) && $handle == "realized-on-module"))
                    {
                        $is_created = Events::isCreated('operation','order-addon',$addon["id"],'addon-service-time-renewed','pending');

                        if(!$is_created)
                            Events::create([
                                'type' => "operation",
                                'owner' => "order-addon",
                                'owner_id' => $addon["id"],
                                'name'     => "addon-service-time-renewed",
                                'data'     => [
                                    'order_id' => $order["id"],
                                    'addon_name' => $addon["addon_name"],
                                    'option_name' => $addon["option_name"],
                                    'period'      => $addon["period"],
                                    'period_time' => $addon["period_time"],
                                ],
                            ]);
                        self::set_addon($addon["id"],['unread' => 0]);
                    }


                    if($addon["addon_key"] == "whois-privacy"){
                        $set_order  = [];
                        $orderopt["whois_privacy"] = true;
                        $orderopt["whois_privacy_endtime"] = DateManager::next_date(['year' => 1]);

                        if($orderopt != $order["options"]) $set_order["options"] = Utility::jencode($orderopt);
                        if($set_order) self::set($order["id"],$set_order);


                        if($order["module"] != "none")
                        {
                            self::$module_processing[] = [
                                'order'         => $order,
                                'product'       => false,
                                'event'         => "set_privacy",
                                'event_data'    => [],
                            ];
                        }
                        else
                        {
                            $evID   = Events::isCreated("operation","order",$order["id"],"modify-whois-privacy-enable","pending");
                            if(!$evID)
                                $evID = Events::create([
                                    'type' => "operation",
                                    'owner' => "order",
                                    'owner_id' => $order["id"],
                                    'name' => "modify-whois-privacy-enable",
                                    'data' => [
                                        'domain' => $orderopt["domain"],
                                    ],
                                ]);
                            if(!isset(self::$notification_logs["need_manually_transaction"][$order["id"]."-ev-".$evID]))
                            {
                                self::$notification_logs["need_manually_transaction"][$order["id"]."-ev-".$evID] = true;
                                self::$notification_processing[] = [
                                    'name' => "need_manually_transaction",
                                    'params' => [$order,$evID],
                                ];
                            }
                        }
                    }

                    Hook::run("AddonRenewal",self::get_addon($addon["id"]));
                }
                return self::get($order["id"]);
            }
            elseif($event == "AddonOrder" && $event_data){
                if($invoice["status"] == "paid"){
                    self::addon_process($invoice,$order,[
                        'addons'            => [$event_data["addon_id"] => $event_data["option_id"]],
                        'addons_values'     => [$event_data["addon_id"] => $event_data["option_quantity"]],
                    ]);
                }
                return self::get($order["id"]);
            }
        }

        static function user_modification($item=[],$invoice=[]){
            $iopt           = isset($item["options"]) ? $item["options"] : [];
            $event          = isset($iopt["event"]) ? $iopt["event"] : false;
            $event_data     = isset($iopt["event_data"]) ? $iopt["event_data"] : [];
            if(isset($event_data["usproduct_id"])) $order = self::get($event_data["usproduct_id"]);
            else $order     = isset($item["user_pid"]) && $item["user_pid"] ? self::get($item["user_pid"]) : [];
            $orderopt       = isset($order["options"]) ? $order["options"] : [];
            if($event == "addCredit" && $event_data){
                Helper::Load(["Notification"]);

                if($invoice["status"] == "paid"){
                    $user_id    = $event_data["user_id"];
                    $amount     = $event_data["amount"];
                    $currency   = $event_data["currency"];

                    $user_data  = User::getData($user_id,"id,balance,balance_currency","array");
                    if($user_data){
                        $user_data  = array_merge($user_data,User::getInfo($user_id,['pay_latest_balance']));

                        $pay_latest_balance = $user_data["pay_latest_balance"] ?? false;
                        $diff       = $pay_latest_balance && strlen($pay_latest_balance) > 0 ? DateManager::strtotime() - DateManager::strtotime($pay_latest_balance) : 0;

                        if($diff == 0 || $diff >=  5)
                        {
                            $curr       = $user_data["balance_currency"];
                            $exch       = Money::exChange($amount,$currency,$curr);
                            $balance    = $user_data["balance"];
                            $n_balance  = $balance + $exch;
                            User::setData($user_id,['balance' => $n_balance]);
                            User::setInfo($user_id,['pay_latest_balance' => DateManager::Now()]);

                            Models::$init->db->insert("users_credit_logs",[
                                'description' => $invoice["number"],
                                'user_id'   => $user_id,
                                'type'      => "up",
                                'amount'    => $exch,
                                'cid'       => $curr,
                                'cdate'     => DateManager::Now(),
                            ]);

                            User::addAction($user_id,"alteration","balance-credit-has-been-purchased",[
                                'amount' => Money::formatter_symbol($exch,$curr),
                            ]);
                        }
                    }
                }

            }
        }

        static function sms_modification($item=[],$invoice=[]){
            $iopt           = isset($item["options"]) ? $item["options"] : [];
            $event          = isset($iopt["event"]) ? $iopt["event"] : false;
            $event_data     = isset($iopt["event_data"]) ? $iopt["event_data"] : [];
            if(isset($event_data["usproduct_id"])) $order = self::get($event_data["usproduct_id"]);
            else $order     = isset($item["user_pid"]) && $item["user_pid"] ? self::get($item["user_pid"]) : [];
            $orderopt       = isset($order["options"]) ? $order["options"] : [];


            if($event == "RenewalSmsCredit" && $event_data){

                $product    = Products::get("sms",$event_data["product_id"],"tr");

                $amount     = $item["amount"];
                $amount_cid = $invoice["currency"];

                Helper::Load(["Events","User"]);
                $evID = Events::create([
                    'type' => "operation",
                    'owner' => "order",
                    'owner_id' => $order["id"],
                    'name' => "uploading-new-credit",
                    'data' => [
                        'amount' => $amount,
                        'amount_cid' => $amount_cid,
                        'name' => $product["title"],
                        'id' => $order["id"],
                    ],
                ]);

                $set_order = [];

                if($invoice["status"] == "paid"){

                    self::MakeOperation("inprocess",$order,false);

                    $set_order["amount"]        = $amount;
                    $set_order["amount_cid"]    = $amount_cid;
                    $set_order["renewaldate"]   = DateManager::Now();
                    $set_order["name"]          = $product["title"];
                    $set_order["status"]        = "inprocess";

                    $order                      = array_merge($order,$set_order);

                    Helper::Load(["Notification"]);
                    self::$notification_processing[] = [
                        'name' => "need_manually_transaction",
                        'params' => [$order,$evID],
                    ];

                }else $set_order["status"] = "waiting";

                self::set($order["id"],$set_order);

                User::affiliate_apply_transaction('renewal',$order,$product);

                return self::get($order["id"]);
            }
        }

        static function MakeOperation($type='',$id=0,$pid=0,$submitNtfn=true,$apply_on_module=true)
        {
            Helper::Load(["Notification","Events","Products","Invoices","User"]);
            if(!$id) return false;
            if(is_array($id)) $order = $id;
            else $order = self::get($id);

            $ulang = User::getData($order["owner_id"],"lang")->lang;
            if(!Bootstrap::$lang->LangExists($ulang)) $ulang = Config::get("general/local");


            if(!$order)
            {
                self::$message = "Order not found #".$id;
                return false;
            }

            if($order["status"] != $type && ($order["status"] == "active" || $order["status"] == "cancelled" || $order["status"] == "suspended") && $type == "approve") return true;
            if($order["status"] == $type) return true;


            if(isset($order["type"]) && isset($order["product_id"]) && $order["type"] && $order["product_id"]){
                if($pid){
                    if(is_array($pid))
                        self::$product_temp[$order["type"]][$order["product_id"]] = $pid;
                    else{
                        if(!isset(self::$product_temp[$order["type"]][$pid])){
                            if($product = Products::get($order["type"],$pid))
                                self::$product_temp[$order["type"]][$pid] = $product;
                        }
                    }
                    $product = self::$product_temp[$order["type"]][$order["product_id"]];
                }
                else{
                    $product = false;
                    if(!isset(self::$product_temp[$order["type"]][$order["product_id"]]))
                        self::$product_temp[$order["type"]][$order["product_id"]] = Products::get($order["type"],$order["product_id"]);
                    if(isset(self::$product_temp[$order["type"]][$order["product_id"]]))
                        $product = self::$product_temp[$order["type"]][$order["product_id"]];
                }
            }

            $status     = $type;
            if($status == "approve") $status = "inprocess";

            $status_data = [
                'status' => $status,
                'status_msg' => '',
            ];

            if($type == "cancelled" || $type == "delete"){

                if($order["subscription_id"] > 0 && $order["pmethod"] && $order["pmethod"] != "none")
                {
                    $other_subscribers_count = 0;
                    $other_subscribers = Models::$init->db->select("COUNT(id) AS total")->from("users_products");
                    $other_subscribers->where("subscription_id","=",$order["subscription_id"],"&&");
                    $other_subscribers->where("id","!=",$order["id"],"&&");
                    $other_subscribers->where("status","!=","cancelled");
                    $other_subscribers  = $other_subscribers->build() ? $other_subscribers->getObject()->total : 0;
                    if($other_subscribers) $other_subscribers_count += $other_subscribers;

                    $other_subscribers = Models::$init->db->select("COUNT(id) AS total")->from("users_products_addons");
                    $other_subscribers->where("subscription_id","!=","cancelled","&&");
                    $other_subscribers->where("subscription_id","=",$order["subscription_id"]);
                    $other_subscribers  = $other_subscribers->build() ? $other_subscribers->getObject()->total : 0;
                    if($other_subscribers) $other_subscribers_count += $other_subscribers;
                    if($other_subscribers_count == 0)
                    {
                        $subscription = self::get_subscription($order["subscription_id"]);
                        $cancel     = self::cancel_subscription($subscription,$order);
                        if(!$cancel)
                        {
                            self::$message = 'Unsubscribe Error: '.self::$message;
                            return false;
                        }
                    }
                    else
                    {
                        $subscription           = self::get_subscription($order["subscription_id"]);
                        $subscription["items"]  = Utility::jdecode($subscription["items"],true);
                        if($subscription["items"])
                        {
                            foreach($subscription["items"] AS $k => $i)
                            {
                                if($i["product_type"] == $order["type"] && $i["product_id"] == $order["product_id"] && $i["period"] == $order["period"] && $i["period_time"] == $order["period_time"])
                                {
                                    unset($subscription["items"][$k]);
                                    break;
                                }
                            }
                            self::set_subscription($subscription["id"],['items' => Utility::jencode($subscription["items"])]);
                            self::set($order["id"],['subscription_id' => 0]);
                        }
                    }
                }

                $addons = Models::$init->db->select("id")->from("users_products_addons");
                $addons->where("owner_id","=",$order["id"],"&&");
                $addons->where("status","!=","cancelled");
                $addons = $addons->build() ? $addons->fetch_object() : false;
                if($addons)
                {
                    foreach($addons AS $a)
                    {
                        if(!self::MakeOperationAddon('cancelled',$order,$a->id,$submitNtfn,$apply_on_module))
                        {
                            return false;
                        }
                    }
                }

                self::cancel_order_invoices($order);
            }


            if($status == "active" || ($order["module"] != "none" && $status == "cancelled"))
                $status_data["unread"] = 1;
            elseif(!(defined("ADMINISTRATOR") && ($status == "suspended" || $status == "cancelled")))
                $status_data["unread"] = 0;


            if($status == "approve" || $status == "active")
            {
                $addons         = self::addons($order["id"]);
                if($addons)
                    foreach($addons AS $ad) if($ad["status"] == "waiting") self::set_addon($ad["id"],['status' => "inprocess"]);
            }

            if($apply_on_module){
                if($order["module"] && $order["module"] != "none" && ($type == "active" || $type == "suspended" || (!(defined("CRON") && ($order["type"] == "hosting" || $order["type"] == "server")) && ($type == "cancelled" || $type == "delete")))) $module_handler = self::ModuleHandler($order,(isset($product) ? $product : false),$type);
                elseif($order["type"] == "domain" && $order["module"] != "none" && ($type == "cancelled" || $type == "delete")) $module_handler = self::ModuleHandler($order,(isset($product) ? $product : false),$type);
            }

            if($type != "approve"){
                if(isset($module_handler) && $module_handler){
                    if(gettype($module_handler) != "boolean" && $module_handler == "failed"){
                        self::set($order["id"],['status_msg' => self::$message,'unread' => 0]);
                        return false;
                    }elseif(gettype($module_handler) != "boolean" && $module_handler == "inprocess"){
                        self::set($order["id"],['status_msg' => self::$message,'unread' => 0]);
                        $type = "inprocess";
                        $status_data["status"] = "inprocess";
                        $status_data["status_msg"] = self::$message;
                        $status_data["unread"] = 0;
                    }
                }
                else{
                    if(defined("CRON") && $type == "cancelled"){

                        $if_ex = false;
                        if(!defined("SOFTWARE_PRODUCT_NOTIFICATION"))
                            $if_ex = $order["type"] == 'software';

                        if($if_ex) $status_data["unread"] = 1;
                        elseif(!$order["module"] || $order["module"] == "none"){
                            $evID = Events::create([
                                'type'      => "operation",
                                'owner'     => "order",
                                'owner_id'  => $order["id"],
                                'name'      => "order-has-been-cancelled",
                                'data'      => [],
                            ]);
                            self::$notification_processing[] = [
                                'name' => "need_manually_transaction",
                                'params' => [$order,$evID],
                            ];
                        }
                    }
                }

                $order      = self::get($order["id"]);

                if($type == "active" && !isset($order["options"]["established"])){
                    if($order["type"] == "hosting" || $order["type"] == "server" || $order["type"] == "domain" || $order["type"] == "special" || $order["type"] == "sms"){
                        $opt    = $order["options"];
                        $opt["established"] = true;
                        $status_data["options"] = Utility::jencode($opt);
                    }
                }
            }

            if($order["type"] == "domain"){
                if(!class_exists("Events")) Helper::Load(["Events"]);
                if($type == "active")
                {

                    if(defined("ADMINISTRATOR") && Events::isCreated("operation","order",$order["id"],"transfer-request-to-us-with-api","pending"))
                    {
                        self::$message = __("admin/events/transfer-request-to-us-with-api");
                        return false;
                    }

                    $evID = Events::isCreated("operation","order",$order["id"],"transfer-request-to-us-with-manuel","pending");
                    if($evID) Events::approved($evID);

                    if(isset(self::$markers["formation"][$order["id"]]))
                        if(in_array("transfer-request-to-us-with-api",self::$markers["formation"][$order["id"]])) return true;
                }
            }

            if($type == "approve")
                User::affiliate_apply_transaction('sale',$order,$product);
            elseif($type == "cancelled")
                User::affiliate_cancel_order_transaction($order);

            if($type == "approve" && ($order["type"] == "server" || $order["type"] == "special") && $product["stock"]){
                $stock = (int) $product["stock"];
                Products::set($order["type"],$order["product_id"],[
                    'stock' => $stock-1,
                ]);
            }

            if($type == "delete")
                return self::delete($order,$apply_on_module);
            else{

                if($status == 'active' && $order["server_terminated"] == 1)
                {
                    $status_data["server_terminated"] = 0;
                    $status_data["terminated_date"] = DateManager::ata();
                }

                if($type == "suspended")
                {
                    $suspended_reason = Bootstrap::$lang->get_cm("admin/orders/default-suspend-reason",false,$ulang);
                    if(self::$suspended_reason) $suspended_reason = self::$suspended_reason;
                    $status_data["suspended_reason"] = $suspended_reason;
                }
                else
                    $status_data["suspended_reason"] = NULL;


                $make = self::set($order["id"],$status_data);

                if($make){
                    if($type == "approve")
                        Hook::run("OrderApproved",$order);
                    elseif($type == "active")
                    {
                        $adata  = defined("CRON") ? [] : UserManager::LoginData("admin");
                        $uid    = isset($adata["id"]) ? $adata["id"] : 0;
                        self::add_history($uid,$order["id"],'order-has-been-activated');
                        Hook::run("OrderActivated",$order);
                    }
                    elseif($type == "suspended")
                    {
                        $adata  = defined("CRON") ? [] : UserManager::LoginData("admin");
                        $uid    = isset($adata["id"]) ? $adata["id"] : 0;
                        self::add_history($uid,$order["id"],'order-has-been-suspended');
                        Hook::run("OrderSuspended",$order);
                    }
                    elseif($type == "cancelled"){

                        $adata  = defined("CRON") ? [] : UserManager::LoginData("admin");
                        $uid    = isset($adata["id"]) ? $adata["id"] : 0;
                        self::add_history($uid,$order["id"],'order-has-been-cancelled');
                        Hook::run("OrderCancelled",$order);
                    }

                    if($submitNtfn){
                        if($type == "active" && $order["type"] == "domain" && !isset($order["options"]["established"]))
                            self::$notification_processing[] = [
                                'name' => "domain_registered",
                                'params' => [$order["id"]],
                            ];
                        elseif($type == "active" && $order["type"] == "domain")
                            self::$notification_processing[] = [
                                'name' => "domain_has_been_activated",
                                'params' => [$order["id"]],
                            ];
                        elseif($type == "approve")
                            self::$notification_processing[] = [
                                'name' => "order_has_been_approved",
                                'params' => [$order["id"]],
                            ];
                        elseif($type == "active")
                            self::$notification_processing[] = [
                                'name' => "order_has_been_activated",
                                'params' => [$order["id"]],
                            ];
                        elseif($type == "suspended")
                            self::$notification_processing[] = [
                                'name' => "order_has_been_suspended",
                                'params' => [$order["id"]],
                            ];
                        elseif($type == "cancelled")
                            self::$notification_processing[] = [
                                'name' => "order_has_been_cancelled",
                                'params' => [$order["id"]],
                            ];

                        if($type == "active" && ($order["type"] == "hosting" || $order["type"] == "server") && !isset($order["options"]["established"]))
                            self::$notification_processing[] = [
                                'name' => "order_hosting_server_activation",
                                'params' => [$order["id"]],
                            ];
                    }
                    self::notification_process($submitNtfn);
                }
                return $make;
            }
        }

        static function MakeOperationAddon($type='',$order_id=0,$addon_id=0,$submitNtfn=true,$apply_on_module=true)
        {
            Helper::Load(["Notification","Events","Products"]);
            if(!$order_id) return false;
            if(is_array($order_id)) $order = $order_id;
            else $order = self::get($order_id);

            $ulang = User::getData($order["owner_id"],"lang")->lang;
            if(!Bootstrap::$lang->LangExists($ulang)) $ulang = Config::get("general/local");


            if(!$addon_id) return false;
            if(is_array($addon_id)) $addon = $addon_id;
            else $addon = self::get_addon($addon_id);

            $status             = $type;
            $status_msg         = '';
            $unread             = 1;
            $module_data        = $addon["module_data"] ? Utility::jdecode($addon["module_data"],true) : [];
            self::$realized_on_module = false;

            if($status == $addon["status"]) return true;

            if($status == "cancelled" || $status == "delete")
            {
                if($addon["subscription_id"] > 0)
                {
                    $other_subscribers_count = 0;
                    $other_subscribers = Models::$init->db->select("COUNT(id) AS total")->from("users_products");
                    $other_subscribers->where("subscription_id","=",$addon["subscription_id"],"&&");
                    $other_subscribers->where("status","!=","cancelled");
                    $other_subscribers  = $other_subscribers->build() ? $other_subscribers->getObject()->total : 0;
                    if($other_subscribers) $other_subscribers_count += $other_subscribers;

                    $other_subscribers = Models::$init->db->select("COUNT(id) AS total")->from("users_products_addons");
                    $other_subscribers->where("status","!=","cancelled","&&");
                    $other_subscribers->where("subscription_id","=",$order["subscription_id"],"&&");
                    $other_subscribers->where("id","!=",$addon["id"]);
                    $other_subscribers  = $other_subscribers->build() ? $other_subscribers->getObject()->total : 0;
                    if($other_subscribers) $other_subscribers_count += $other_subscribers;
                    if($other_subscribers_count == 0)
                    {
                        $subscription = self::get_subscription($addon["subscription_id"]);
                        $cancel     = self::cancel_subscription($subscription,false,$addon);
                        if(!$cancel)
                        {
                            self::$message = 'Unsubscribe Error: '.self::$message;
                            return false;
                        }
                    }
                    else
                    {
                        $subscription           = self::get_subscription($addon["subscription_id"]);
                        $subscription["items"]  = Utility::jdecode($subscription["items"],true);
                        if($subscription["items"])
                        {
                            foreach($subscription["items"] AS $k => $i)
                            {
                                if($i["product_type"] == "addon" && $i["product_id"] == $addon["addon_id"] && $i["period"] == $addon["period"] && $i["period_time"] == $addon["period_time"])
                                {
                                    unset($subscription["items"][$k]);
                                    break;
                                }
                            }
                            self::set_subscription($subscription["id"],['items' => Utility::jencode($subscription["items"])]);
                            self::set_addon($addon["id"],['subscription_id' => 0]);
                        }
                    }
                }
            }

            if($status == 'change') $status = $addon['status'];

            if($order["module"] && $order["module"] != "none" && $apply_on_module)
            {
                $run_module_action = true;
                if($type == "active") {
                    if($addon["status"] == "suspended") $type = "unsuspend";
                    elseif($addon["status"] == "active" || $addon["status"] == "completed") $run_module_action = false;
                }

                if($run_module_action) {
                    $addon["module_data"] = $module_data;
                    $handle = self::ModuleHandler($order,$addon,'addon-'.$type,self::$set_data);
                    if((!$handle || $handle == 'failed') && self::$message)
                    {
                        $status         = false;
                        $status_msg     = self::$message;
                        $unread         = 0;
                    }
                    elseif(is_array($handle)) $module_data = $handle;
                }
            }

            $set_data = [
                'status_msg'        => $status_msg,
                'unread'            => $unread,
                'module_data'       => $module_data ? Utility::jencode($module_data) : '',
            ];

            if($status == "suspended")
            {
                $suspended_reason = Bootstrap::$lang->get_cm("admin/orders/default-suspend-reason",false,$ulang);
                if(self::$suspended_reason) $suspended_reason = self::$suspended_reason;
                $set_data["suspended_reason"] = $suspended_reason;
            }
            else
                $set_data["suspended_reason"] = NULL;

            if($status == 'delete') $set_data = [];

            if($set_data){
                if($status) $set_data['status'] = $status;
                self::set_addon($addon["id"],$set_data);
            }

            if($status == 'delete') self::delete_addon($addon);

            if($submitNtfn)
            {
                if($status == 'active')
                    self::$notification_processing[] = [
                        'name' => "order_has_been_activated",
                        'params' => [$order["id"],$addon["id"]],
                    ];
                elseif($status == 'suspended')
                    self::$notification_processing[] = [
                        'name' => "order_has_been_suspended",
                        'params' => [$order["id"],$addon["id"]],
                    ];
                elseif($status == 'cancelled')
                    self::$notification_processing[] = [
                        'name' => "order_has_been_cancelled",
                        'params' => [$order["id"],$addon["id"]],
                    ];
            }

            $return_status = $status_msg ? false : true;

            if(self::$realized_on_module) $return_status = 'realized-on-module';
            $addon_params = [
                'order' => $order,
                'addon' => $addon,
            ];

            if($status == "active") Hook::run("OrderAddonActivated",$addon_params);
            elseif($status == "suspended") Hook::run("OrderAddonSuspended",$addon_params);
            elseif($status == "cancelled") Hook::run("OrderAddonCancelled",$addon_params);
            elseif($status == "delete") Hook::run("OrderAddonDeleted",$addon_params);
            else Hook::run("OrderAddonModified",$addon_params);

            self::notification_process($submitNtfn);

            return $return_status;
        }

        static function ModuleHandler($order,$product,$operation='',$data=[]){
            if($order["type"] == "software") return self::software_module_operation($order,$product,$operation,$data);
            if($order["type"] == "domain") return self::domain_module_operation($order,$product,$operation,$data);
            if($order["type"] == "hosting") return self::hosting_module_operation($order,$product,$operation,$data);
            if($order["type"] == "server") return self::server_module_operation($order,$product,$operation,$data);
            if($order["type"] == "sms") return self::sms_module_operation($order,$product,$operation,$data);
            if($order["type"] == "special") return self::special_module_operation($order,$product,$operation,$data);
            return true;
        }

        static function module_process($notification=true){
            if(self::$module_processing){
                foreach(self::$module_processing AS $k=>$process){
                    $order      = $process["order"] ?? [];
                    $product    = $process["product"] ?? [];
                    $addon      = $process["addon"] ?? [];
                    $event      = $process["event"] ?? false;
                    $event_data = $process["event_data"] ?? [];

                    if($order && $event)
                    {
                        $process    = self::ModuleHandler($order,$product,$event,$event_data);
                        if((!$process || $process == "failed") && self::$message)
                        {
                            Orders::set($order["id"],['status_msg' => self::$message]);
                            self::$notification_processing[] = [
                                'name' => "failed_order_activation",
                                'params' => [$order["id"]],
                            ];
                        }
                    }
                    elseif($addon)
                    {
                        $handle = self::MakeOperationAddon('active',$order["id"],$addon,$notification);
                        if($handle && $handle !== "realized-on-module") self::set_addon($addon["id"],['status' => 'inprocess','unread' => 0]);
                    }
                    else
                    {
                        $process    = self::MakeOperation("active",$order,$product,$notification);
                        if(!$process && self::$message)
                            self::$notification_processing[] = [
                                'name' => "failed_order_activation",
                                'params' => [$order["id"]],
                            ];
                    }

                    unset(self::$module_processing[$k]);
                }
            }
        }
        static function notification_process($send=true) {
            if(self::$notification_processing && $send){
                Helper::Load(["Notification"]);
                foreach(self::$notification_processing AS $k=>$process)
                {
                    $name       = $process["name"];
                    $params     = $process["params"] ?? [];
                    $result     = Notification::$name(...$params);
                    unset(self::$notification_processing[$k]);
                }
            }
        }

        static function domain_module_operation($order,$product,$operation,$data=[]){
            if($operation == "active" && $order["status"] == "cancelled") $operation = "restore";
            if(($operation == "approve" || $operation == "active" || $operation == "unsuspended") && $order["status"] == "suspended"){ // Unsuspended START
                $orderopt       = $order["options"];
                if(isset($order["module"]) && $order["module"] != "none" && $order["module"] != ''){
                    $module_name = $order["module"];
                    Modules::Load("Registrars",$module_name);
                    if(class_exists($module_name))
                    {
                        $module = new $module_name;
                        if(method_exists($module,"set_order")) $module->set_order($order);
                        if(method_exists($module,"define_docs")) $module->define_docs(self::domain_module_docs($order));
                        if(method_exists($module,"unsuspend")){
                            $handle         = $module->unsuspend($orderopt);
                            if(!$handle){
                                self::$message = "Unsuspend Issue: ".$module->error;
                                return "failed";
                            }
                        }
                    }
                    else
                    {
                        self::$message = "Unsuspend Issue: Module ".$module_name." class not found";
                        return "failed";
                    }
                }
            } // Unsuspended END
            elseif($operation == "approve" || $operation == "active")
            { // Active START
                Helper::Load(["Events"]);
                $is_event = Events::isCreated("operation","order",$order["id"],"transfer-request-to-us-with-api","pending");

                if(isset(self::$markers["formation"][$order["id"]]))
                    if(in_array("transfer-request-to-us-with-api",self::$markers["formation"][$order["id"]])) $is_event = false;

                if($order["status"] == "waiting") $is_event = false;

                if($is_event){
                    if(defined("ADMINISTRATOR")){
                        self::$message = Bootstrap::$lang->get_cm("admin/events/transfer-request-to-us-with-api",false,Config::get("general/local"));
                        return "failed";
                    }else
                        return "successful";
                }
                elseif(!isset($order["options"]["config"])){
                    $set_order      = [];
                    $orderopt       = $order["options"];
                    $module_name    = $order["module"];

                    Modules::Load("Registrars",$module_name);
                    if(class_exists($module_name))
                    {
                        $module = new $module_name;
                        if($module)
                        {
                            if(method_exists($module,"set_order")) $module->set_order($order);
                            if(method_exists($module,"define_docs")) $module->define_docs(self::domain_module_docs($order));

                            if(isset($orderopt["whois"]) && $orderopt["whois"]){
                                $whois          = $orderopt["whois"];

                                if(!isset($whois["registrant"]))
                                {
                                    $new_whois = [];
                                    foreach(['registrant','administrative','technical','billing'] AS $ct)
                                        $new_whois[$ct] = $whois;
                                    $whois = $new_whois;
                                }


                                $wprivacy       = false;
                                if(isset($orderopt["whois_privacy"]) && $orderopt["whois_privacy"]) $wprivacy = true;

                                $tcode          = isset($orderopt["tcode"]) ? $orderopt["tcode"] : NULL;
                                $domain         = $orderopt["domain"];
                                $sld            = $orderopt["name"];
                                $tld            = $orderopt["tld"];
                                $year           = $order["period_time"];
                                $dns            = [];
                                if(isset($orderopt["ns1"]) && $orderopt["ns1"] != '')
                                    $dns["ns1"] = $orderopt["ns1"];
                                if(isset($orderopt["ns2"]) && $orderopt["ns2"] != '')
                                    $dns["ns2"] = $orderopt["ns2"];
                                if(isset($orderopt["ns3"]) && $orderopt["ns3"] != '')
                                    $dns["ns3"] = $orderopt["ns3"];
                                if(isset($orderopt["ns4"]) && $orderopt["ns4"] != '')
                                    $dns["ns4"] = $orderopt["ns4"];

                                if($tcode)
                                    $h_operation = Hook::run("PreRegistrarTransferDomain",$order);
                                else
                                    $h_operation = Hook::run("PreRegistrarRegisterDomain",$order);

                                $h_error = '';

                                if($h_operation)
                                    foreach($h_operation AS $h_opt)
                                        if($h_opt && isset($h_opt["error"]) && $h_opt["error"])
                                            $h_error = $h_opt["error"];

                                $create = false;

                                if($h_error) $module->error = $h_error;
                                elseif($tcode)
                                    $create = $module->transfer($domain,$sld,$tld,$year,$dns,$whois,$wprivacy,$tcode);
                                else
                                    $create = $module->register($domain,$sld,$tld,$year,$dns,$whois,$wprivacy);


                                if($create){

                                    if($tcode)
                                        Events::create([
                                            'type'      => "operation",
                                            'owner'     => "order",
                                            'owner_id'  => $order["id"],
                                            'name'      => "transfer-request-to-us-with-api",
                                            'data'      => [
                                                'domain' => $domain,
                                            ],
                                        ]);


                                    if(isset($create["config"])) $orderopt["config"] = $create["config"];
                                    if(isset($create["creation_info"])) $orderopt["creation_info"] = $create["creation_info"];

                                    $set_order["options"] = Utility::jencode($orderopt);

                                    if(isset($create["status"]) && $create["status"] == "FAIL"){
                                        $is_event = Events::isCreated("checking","order",$order["id"],"domain-activation-status","pending");
                                        if(!$is_event)
                                            Events::create([
                                                'type'      => "checking",
                                                'owner'     => "order",
                                                'owner_id'  => $order["id"],
                                                'name'      => "domain-activation-status",
                                                'data'      => ['domain' => $domain],
                                            ]);

                                        self::$message = "Activation Issue: ".$create["message"];
                                        return "failed";
                                    }
                                    if(isset($create["change"]) && $create["change"])
                                    {

                                        if(isset($create["change"]["options"]))
                                            if(is_array($create["change"]["options"]) && $create["change"]["options"])
                                                $create["change"]["options"] = Utility::jencode(array_merge($orderopt,$create["change"]["options"]));

                                        $set_order = array_merge($set_order,$create["change"]);
                                    }

                                    if($wprivacy){
                                        $isAddon   = Models::$init->db->select("id")->from("users_products_addons");
                                        $isAddon->where("owner_id","=",$order["id"],"&&");
                                        $isAddon->where("addon_key","=","whois-privacy");
                                        $isAddon   = $isAddon->build() ? $isAddon->getObject()->id : false;
                                        if($isAddon){
                                            $addon_data = [
                                                'status' => "active",
                                                'status_msg' => "",
                                                'unread' => "1",
                                            ];
                                            if(isset($create["whois_privacy"])){
                                                if(!$create["whois_privacy"]["status"]){
                                                    $msg = $create["whois_privacy"]["message"];
                                                    if(!$msg) $msg = "Could not configure whois privacy protection";
                                                    $addon_data["status"] = "inprocess";
                                                    $addon_data["unread"] = 0;
                                                    $addon_data["status_msg"] = $msg;
                                                }
                                            }
                                            self::set_addon($isAddon,$addon_data);
                                        }
                                    }
                                    if($set_order) self::set($order["id"],$set_order);
                                }
                                else{
                                    self::$message = "Activation Issue: ".$module->error;
                                    return "failed";
                                }
                            }
                            else return false;

                        }
                    }
                    else
                    {
                        self::$message = "Activation Issue: Module ".$module_name." class not found";
                        return "failed";
                    }
                }
            } // Active END
            elseif($operation == "renewal"){ // Renewal START
                $orderopt       = $order["options"];
                if($data){
                    $year           = $data["year"];
                    $old_duedate    = $data["old_duedate"];
                    $new_duedate    = $data["new_duedate"];

                    if(isset($order["module"]) && $order["module"] != "none" && $order["module"] != ''){
                        $module_name = $order["module"];
                        Modules::Load("Registrars",$module_name);
                        if(class_exists($module_name))
                        {
                            $module = new $module_name;
                            if(method_exists($module,"set_order")) $module->set_order($order);
                            if(method_exists($module,"define_docs")) $module->define_docs(self::domain_module_docs($order));
                            if($module && isset($data["year"]))
                            {
                                $domain         = $orderopt["domain"];
                                $sld            = $orderopt["name"];
                                $tld            = $orderopt["tld"];


                                $h_error = '';

                                if($h_operation = Hook::run("PreRegistrarRenewDomain",$order))
                                    foreach($h_operation AS $h_opt)
                                        if($h_opt && isset($h_opt["error"]) && $h_opt["error"])
                                            $h_error = $h_opt["error"];

                                $handle = false;

                                if($h_error) $module->error = $h_error;
                                else
                                    $handle = $module->renewal($orderopt,$domain,$sld,$tld,$year,$old_duedate,$new_duedate);

                                if($handle && is_array($handle) && isset($handle["change"]) && $handle["change"])
                                {
                                    if(isset($handle["change"]["options"]))
                                        if(is_array($handle["change"]["options"]) && $handle["change"]["options"])
                                            $handle["change"]["options"] = Utility::jencode(array_merge($orderopt,$handle["change"]["options"]));
                                    Orders::set($order["id"],$handle["change"]);
                                }

                                if(!$handle){
                                    self::$message = "Renewal Issue: ".$module->error;
                                    return "failed";
                                }
                            }
                        }
                        else
                        {
                            self::$message = "Renewal Issue: Module ".$module_name." class not found";
                            return "failed";
                        }
                    }
                }
                else
                    return false;
            } // Renewal END
            elseif($operation == "set_privacy"){ // set_privacy START
                $orderopt       = $order["options"];

                if(isset($order["module"]) && $order["module"] != "none" && $order["module"] != ''){
                    $module_name = $order["module"];
                    Modules::Load("Registrars",$module_name);
                    if(class_exists($module_name))
                    {
                        $module = new $module_name;
                        if(method_exists($module,"set_order")) $module->set_order($order);
                        if(method_exists($module,"define_docs")) $module->define_docs(self::domain_module_docs($order));
                        if($module){
                            $handle         = $module->modifyPrivacyProtection($orderopt,"enable");
                            if(!$handle){
                                self::$message = "Whois Protection Setting Issue: ".$module->error;
                                return "failed";
                            }
                        }
                    }
                    else
                    {
                        self::$message = "Whois Protection Setting Issue: Module ".$module_name." class not found";
                        return "failed";
                    }
                }
            } // set_privacy END
            elseif($operation == "purchase_privacy"){ // purchase_privacy START
                $orderopt       = $order["options"];

                if(isset($order["module"]) && $order["module"] != "none" && $order["module"] != ''){
                    $module_name = $order["module"];
                    Modules::Load("Registrars",$module_name);
                    if(class_exists($module_name))
                    {
                        $module = new $module_name;
                        if(method_exists($module,"set_order")) $module->set_order($order);
                        if(method_exists($module,"define_docs")) $module->define_docs(self::domain_module_docs($order));
                        if($module)
                        {
                            $handle         = $module->purchasePrivacyProtection($orderopt);
                            if(!$handle){
                                self::$message = "Whois Protection Purchase Issue: ".$module->error;
                                return "failed";
                            }
                        }
                    }
                    else
                    {
                        self::$message = "Whois Protection Purchase Issue: Module ".$module_name." class not found";
                        return "failed";
                    }
                }
            } // purchase_privacy END
            elseif($operation == "restore"){ // Suspended START
                $orderopt       = $order["options"];
                if(isset($order["module"]) && $order["module"] != "none" && $order["module"] != ''){
                    $module_name = $order["module"];
                    Modules::Load("Registrars",$module_name);
                    if(class_exists($module_name))
                    {
                        $module = new $module_name;
                        if(method_exists($module,"set_order")) $module->set_order($order);
                        if(method_exists($module,"define_docs")) $module->define_docs(self::domain_module_docs($order));
                        if(method_exists($module,"restore")){
                            $handle         = $module->restore($orderopt);
                            if(!$handle){
                                self::$message = "Restore Issue: ".$module->error;
                                return "failed";
                            }
                        }
                    }
                    else
                    {
                        self::$message = "Restore Issue: Module ".$module_name." class not found";
                        return "failed";
                    }
                }
            } // Restore END
            elseif($operation == "suspended"){ // Suspended START
                $orderopt       = $order["options"];
                if(isset($order["module"]) && $order["module"] != "none" && $order["module"] != ''){
                    $module_name = $order["module"];
                    Modules::Load("Registrars",$module_name);
                    if(class_exists($module_name))
                    {
                        $module = new $module_name;
                        if(method_exists($module,"set_order")) $module->set_order($order);
                        if(method_exists($module,"define_docs")) $module->define_docs(self::domain_module_docs($order));
                        if(method_exists($module,"suspend")){
                            $handle         = $module->suspend($orderopt);
                            if(!$handle){
                                self::$message = "Suspend Issue: ".$module->error;
                                return "failed";
                            }
                        }
                    }
                    else
                    {
                        self::$message = "Suspend Issue: Module ".$module_name." class not found";
                        return "failed";
                    }
                }
            } // Suspended END
            elseif($operation == "terminate" || $operation == "cancelled"){ // Terminate START
                $orderopt       = $order["options"];
                if(isset($order["module"]) && $order["module"] != "none" && $order["module"] != ''){
                    $module_name = $order["module"];
                    Modules::Load("Registrars",$module_name);
                    if(class_exists($module_name))
                    {
                        $module = new $module_name;
                        if(method_exists($module,"set_order")) $module->set_order($order);
                        if(method_exists($module,"define_docs")) $module->define_docs(self::domain_module_docs($order));
                        $method_name = "cancelled";
                        if(!method_exists($module,$method_name)) $method_name = "terminate";

                        if(method_exists($module,$method_name)){
                            $handle         = $module->$method_name($orderopt);
                            if(!$handle){
                                self::$message = "Cancel Issue: ".$module->error;
                                return "failed";
                            }
                        }
                    }
                    else
                    {
                        self::$message = "Cancel Issue: Module ".$module_name." class not found";
                        return "failed";
                    }
                }
            } // Terminate END

            return "successful";
        }

        static function UsernameGenerator($domain='',$half_mixed=false){
            $exp            = explode(".",$domain);
            $domain         = Filter::transliterate($exp[0]);
            $username       = $domain;
            $fchar          = substr($username,0,1);
            $size           = strlen($username);
            if($fchar == "0" || (int)$fchar)
                $username   = Utility::generate_hash(1,false,"l").substr($username,1,$size-1);

            if($size>=8){
                $username   = substr($username,0,5);
                $username .= Utility::generate_hash(3,false,"l");
            }elseif($size>4 && $size<9){
                $username   = substr($username,0,5);
                $username .= Utility::generate_hash(3,false,"l");
            }elseif($size>=1 && $size<5){
                $how        = (8 - $size);
                $username   = substr($username,0,$size);
                $username .= Utility::generate_hash($how,false,"l");
            }

            return $username;
        }

        static function hosting_module_operation($order,$product,$operation,$data=[]){
            Helper::Load(["Products"]);
            if(!isset($order["options"]["config"]["user"]) && ($operation == "create" || $operation == "active" || $operation == "approve")){ // Create START

                $set_order  = [];
                $orderopt   = $order["options"];

                if($order["module"] != "none" && isset($orderopt["server_id"]) && $orderopt["server_id"]){
                    $server = Products::get_server($orderopt["server_id"]);
                    if($server && $server["status"] == "active"){

                        if($server["maxaccounts"] > 0)
                        {
                            $used           = self::linked_server_count($server["id"]);
                            $remaining      =  $server["maxaccounts"] - $used;
                            if($remaining < 1)
                            {
                                self::$message = Bootstrap::$lang->get_cm("admin/products/shared-server-tx32");
                                return "failed";
                            }
                        }

                        $user           = User::getData($order["owner_id"],"lang","array");
                        $createopt      = ['lang' => $user["lang"]];

                        $dns = [];
                        if($server["ns1"] != NULL) $dns["ns1"] = $server["ns1"];
                        if($server["ns2"] != NULL) $dns["ns2"] = $server["ns2"];
                        if($server["ns3"] != NULL) $dns["ns3"] = $server["ns3"];
                        if($server["ns4"] != NULL) $dns["ns4"] = $server["ns4"];
                        if($dns) $orderopt["dns"] = $dns;


                        $module_name    = $server["type"]."_Module";
                        if(!class_exists($module_name)) Modules::Load("Servers",$server["type"]);
                        if(class_exists($module_name)){
                            $operations     = new $module_name($server);
                            if(method_exists($operations,"set_order")) $operations->set_order($order);


                            if((!property_exists($operations,'force_setup') || $operations->force_setup) && $order["status"]  == "inprocess")
                            {
                                $domain         = idn_to_ascii($orderopt["domain"],0,INTL_IDNA_VARIANT_UTS46);
                                $username       = self::UsernameGenerator($domain);
                                $username       = str_replace("-","",$username);
                                $password       = str_replace("%",".",self::generate_password(16));

                                if($server["type"] == "Plesk")
                                    $password = self::generate_password(5).self::generate_password(3,false,'l').self::generate_password_force(3,false,'s').self::generate_password(2,false,'u').self::generate_password(3,false,'d');

                                $create = [
                                    'username' => $username,
                                    'password' => $password,
                                    'ftp_info' => [
                                        'ip'   => $server["ip"],
                                        'host' => "ftp.".$domain,
                                        'username' => $username,
                                        'password' => $password,
                                        'port' => 21,
                                    ],
                                ];
                                $createopt["username"] = $username;
                                $createopt["password"] = $password;

                                $orderopt["server_id"] = $server["id"];
                                $orderopt["config"]["user"] = $create["username"];
                                $orderopt["config"]["password"]   = Crypt::encode($create["password"],Config::get("crypt/user"));
                                if(isset($create["ftp_info"]["password"]) && $create["ftp_info"]["password"]){
                                    $create["ftp_info"]["password"] = Crypt::encode($create["ftp_info"]["password"],Config::get("crypt/user"));
                                    $orderopt["ftp_info"] = $create["ftp_info"];
                                }

                                $set_order["status"] = "active";
                                $set_order["options"] = Utility::jencode($orderopt);
                                if($set_order) self::set($order["id"],$set_order);
                            }


                            if(isset($orderopt["disk_limit"])) $createopt["disk_limit"] = $orderopt["disk_limit"];

                            if(isset($orderopt["bandwidth_limit"]))
                                $createopt["bandwidth_limit"] = $orderopt["bandwidth_limit"];

                            if(isset($orderopt["email_limit"])) $createopt["email_limit"] = $orderopt["email_limit"];

                            if(isset($orderopt["database_limit"])) $createopt["database_limit"] = $orderopt["database_limit"];

                            if(isset($orderopt["addons_limit"])) $createopt["addons_limit"] = $orderopt["addons_limit"];

                            if(isset($orderopt["subdomain_limit"]))
                                $createopt["subdomain_limit"] = $orderopt["subdomain_limit"];

                            if(isset($orderopt["ftp_limit"]))
                                $createopt["ftp_limit"] = $orderopt["ftp_limit"];

                            if(isset($orderopt["park_limit"]))
                                $createopt["park_limit"] = $orderopt["park_limit"];

                            if(isset($orderopt["max_email_per_hour"]))
                                $createopt["max_email_per_hour"] = $orderopt["max_email_per_hour"];

                            if(isset($orderopt["creation_info"]))
                                $createopt["creation_info"] = $orderopt["creation_info"];

                            if(isset($data["user"])) $createopt["username"] = $data["user"];
                            if(isset($data["password"])) $createopt["password"] = Crypt::decode($data["password"],Config::get("crypt/user"));
                            $domain         = $orderopt["domain"];
                            $domain         = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
                            if(method_exists($operations,'createAccount'))
                                $create         = $operations->createAccount($domain,$createopt);
                            else
                                $create         = $operations->create($domain,$createopt);

                            if($create){
                                $orderopt["server_id"] = $server["id"];
                                $orderopt["config"]["user"] = $create["username"];
                                $orderopt["config"]["password"]   = Crypt::encode($create["password"],Config::get("crypt/user"));
                                if(isset($create["ftp_info"]["password"]) && $create["ftp_info"]["password"]){
                                    $create["ftp_info"]["password"] = Crypt::encode($create["ftp_info"]["password"],Config::get("crypt/user"));
                                    $orderopt["ftp_info"] = $create["ftp_info"];
                                }

                                if(isset($create["properties"])) $orderopt["properties"] = $create["properties"];


                                $id_of_conf_opt = $operations->id_of_conf_opt ?? false;
                                if($id_of_conf_opt)
                                    foreach($id_of_conf_opt AS $ad_id => $ad) self::MakeOperationAddon("active",$order,$ad_id);

                            }
                            else{
                                self::$message = $operations->error;
                                return "failed";
                            }
                        }else return false;
                    }
                }else return false;

                $set_order["status"] = "active";
                $set_order["options"] = Utility::jencode($orderopt);
                if($set_order) self::set($order["id"],$set_order);
                Hook::run("AccountCreatedInHostingModule",$order["id"],($operations ?? false));
                return "successful";

            } // Create END
            elseif($operation == "suspended"){ // Suspended START
                if(isset($order["options"]["config"]["user"]) && $order["options"]["config"]["user"]){
                    $orderopt   = $order["options"];

                    if($order["status"] != "suspended"){
                        if($order["module"] != "none" && isset($orderopt["server_id"]) && $orderopt["server_id"]){
                            $server = Products::get_server($orderopt["server_id"]);
                            if($server && $server["status"] == "active"){

                                $module_name    = $server["type"]."_Module";
                                if(!class_exists($module_name)) Modules::Load("Servers",$server["type"]);
                                if(class_exists($module_name)){
                                    $operations     = new $module_name($server,$orderopt);

                                    if(method_exists($operations,"set_order")) $operations->set_order($order);

                                    if(isset($orderopt["creation_info"]["reseller"]) && $orderopt["creation_info"]["reseller"] && method_exists($operations,'suspend_reseller'))
                                        $suspended      = $operations->suspend_reseller();
                                    else
                                        $suspended      = $operations->suspend();

                                    if(!$suspended){
                                        self::$message = $operations->error;
                                        return "failed";
                                    }
                                }else return false;
                            }
                        }else return false;
                    }
                    return "successful";
                }
            } // Suspended END
            elseif($operation == "active" && ($order["status"] == "suspended")){ // Unsuspended START
                if(isset($order["options"]["config"]["user"]) && $order["options"]["config"]["user"]){
                    $orderopt   = $order["options"];

                    if($order["module"] != "none" && isset($orderopt["server_id"]) && $orderopt["server_id"]){
                        $server = Products::get_server($orderopt["server_id"]);
                        if($server && $server["status"] == "active"){

                            $module_name    = $server["type"]."_Module";
                            if(!class_exists($module_name)) Modules::Load("Servers",$server["type"]);
                            if(class_exists($module_name)){
                                $operations     = new $module_name($server,$orderopt);

                                if(method_exists($operations,'set_order')) $operations->set_order($order);

                                if(isset($orderopt["creation_info"]["reseller"]) && $orderopt["creation_info"]["reseller"] && method_exists($operations,'unsuspend_reseller'))
                                    $unsuspended    = $operations->unsuspend_reseller();
                                else
                                    $unsuspended    = $operations->unsuspend();

                                if(!$unsuspended){
                                    self::$message = $operations->error;
                                    return "failed";
                                }
                            }else return false;
                        }else{
                            self::$message = Bootstrap::$lang->get("errors/error2",Config::get("general/local"));
                            return "failed";
                        }
                    }else return false;

                    return "successful";
                }
            } // Unsuspended END
            elseif($operation == "terminate" || $operation == "cancelled"){ // Terminate START
                if(isset($order["options"]["config"]["user"]) && $order["options"]["config"]["user"]){

                    $set_order  = [];
                    $orderopt   = $order["options"];

                    if($order["module"] != "none" && isset($orderopt["server_id"]) && $orderopt["server_id"]){
                        $server = Products::get_server($orderopt["server_id"]);
                        if($server && $server["status"] == "active"){

                            $module_name    = $server["type"]."_Module";
                            if(!class_exists($module_name)) Modules::Load("Servers",$server["type"]);
                            if(class_exists($module_name)){
                                $operations     = new $module_name($server,$orderopt);
                                if(method_exists($operations,"set_order")) $operations->set_order($order);

                                if(isset($orderopt["creation_info"]["reseller"]) && $orderopt["creation_info"]["reseller"] && method_exists($operations,'removeReseller'))
                                    $deleted        = $operations->removeReseller();
                                elseif(method_exists($operations,'removeAccount'))
                                    $deleted        = $operations->removeAccount();
                                else
                                    $deleted        = $operations->terminate();

                                if(!$deleted){
                                    self::$message = $operations->error;
                                    return "failed";
                                }

                                if(isset($orderopt["config"])) unset($orderopt["config"]);
                                if(isset($orderopt["ftp_info"])) unset($orderopt["ftp_info"]);
                            }
                        }
                    }

                    $set_order["options"] = Utility::jencode($orderopt);
                    $set_order["server_terminated"] = 1;
                    $set_order["terminated_date"] = DateManager::Now();

                    if($set_order) self::set($order["id"],$set_order);
                }
            } // Terminate END
            elseif($operation == "change-password"){ // Change Password START
                if(isset($order["options"]["config"]["user"]) && $order["options"]["config"]["user"]){

                    $set_order  = [];
                    $orderopt   = $order["options"];

                    if($order["module"] != "none" && isset($orderopt["server_id"]) && $orderopt["server_id"]){
                        $server = Products::get_server($orderopt["server_id"]);
                        if($server && $server["status"] == "active"){

                            $module_name    = $server["type"]."_Module";
                            if(!class_exists($module_name)) Modules::Load("Servers",$server["type"]);
                            if(class_exists($module_name)){
                                $operations     = new $module_name($server,$orderopt);

                                if(method_exists($operations,"set_order")) $operations->set_order($order);

                                $cpassword      = $data["password"];

                                if(method_exists($operations,'change_password'))
                                    $changed        = $operations->change_password($cpassword);
                                else
                                    $changed        = $operations->changePassword(false,$cpassword);

                                if(!$changed){
                                    self::$message = $operations->error;
                                    return "failed";
                                }

                                $cpassword  = Crypt::encode($cpassword,Config::get("crypt/user"));
                                $orderopt["config"]["password"] = $cpassword;
                                if(isset($orderopt["ftp_info"]["username"]) && $orderopt["ftp_info"]["username"])
                                    $orderopt["ftp_info"]["password"] = $cpassword;

                            }else return false;
                        }else{
                            self::$message = Bootstrap::$lang->get("errors/error2",Config::get("general/local"));
                            return "failed";
                        }
                    }else return false;

                    $set_order["options"] = Utility::jencode($orderopt);
                    if($set_order) self::set($order["id"],$set_order);

                    return "successful";
                }
            } // Change Password END
            elseif($operation == "re-install"){ // Re-Install START
                $product    = Products::get($order["type"],$order["product_id"],Config::get("general/local"));
                $config     = [];
                if(isset($order["options"]["config"])){
                    $terminate  = self::hosting_module_operation($order,$product,"terminate");
                    if(!$terminate) return false;
                    $config = $order["options"]["config"];
                }
                if($config) unset($order["options"]["config"]);
                $install    = self::hosting_module_operation($order,$product,"create",$config);
                if(!$install) return false;
            } // Re-Install END
            elseif($operation == "upgrade" || $operation == "downgrade"){ // Updowngrade START
                if(isset($order["options"]["config"]["user"]) && $order["options"]["config"]["user"]){

                    $set_order  = [];
                    $orderopt   = $order["options"];

                    if($order["module"] != "none" && isset($orderopt["server_id"]) && $orderopt["server_id"]){
                        $server = Products::get_server($orderopt["server_id"]);
                        if($server && $server["status"] == "active"){
                            $module_name    = $server["type"]."_Module";
                            if(!class_exists($module_name)) Modules::Load("Servers",$server["type"]);
                            if(class_exists($module_name)){
                                $module     = new $module_name($server,$orderopt);

                                if(method_exists($module,"set_order")) $module->set_order($order);

                                if(method_exists($module,"apply_updowngrade")){
                                    $apply  = $module->apply_updowngrade($orderopt,$product);
                                    if(!$apply){
                                        self::$message = $module->error;
                                        return 'failed';
                                    }
                                }

                            }
                            else return false;
                        }
                        else{
                            self::$message = Bootstrap::$lang->get("errors/error2",Config::get("general/local"));
                            return "failed";
                        }
                    }else return false;

                    $set_order["options"] = Utility::jencode($orderopt);
                    if($set_order) self::set($order["id"],$set_order);

                    return "successful";
                }
            }
            elseif(stristr($operation,'addon-') && $product)
            {
                $orderopt   = $order["options"];
                if(isset($orderopt["server_id"]) && $orderopt["server_id"]){
                    $server = Products::get_server($orderopt["server_id"]);
                    if($server && $server["status"] == "active"){

                        $module_name    = $server["type"]."_Module";
                        if(!class_exists($module_name)) Modules::Load("Servers",$server["type"]);
                        if(class_exists($module_name)){
                            $operations     = new $module_name($server,$orderopt);

                            $split          = explode("-",$operation);
                            $operation      = $split[1];
                            $m_data         = isset($product["module_data"]) ? $product["module_data"] : '';
                            $m_data         = Utility::jdecode($m_data,true);


                            if(($operation == 'active' || $operation == 'completed') && !$m_data)
                                $method_name = 'addon_create';
                            elseif(($operation == 'active' || $operation == 'completed') && $product["status"] == "suspended")
                                $method_name = 'addon_unsuspend';
                            elseif($operation == 'delete')
                                $method_name = "addon_cancelled";
                            elseif($operation == 'suspended')
                                $method_name = "addon_suspend";
                            else
                                $method_name = 'addon_'.$operation;

                            if(method_exists($operations,$method_name))
                            {
                                if(method_exists($operations,"set_order")) $operations->set_order($order);

                                self::$realized_on_module = property_exists($operations,'id_of_conf_opt') && (($operations->id_of_conf_opt[$product["id"] ?? 0]) ?? false);

                                $apply      = $operations->{$method_name}($product,$data);
                                if(!$apply && !is_array($apply)){
                                    self::$message = $operations->error;
                                    return "failed";
                                }
                                if(is_array($apply)) return $apply;
                            }
                        }
                    }
                }
            }
            return "successful";
        }

        static function server_module_operation($order,$product,$operation,$data=[]){
            Helper::Load(["Products","Events"]);
            if(!isset($order["options"]["config"]) && ($operation == "create" || $operation == "active" || $operation == "approve")){ // Create START

                $set_order  = [];
                $orderopt   = $order["options"];

                if($order["module"] != "none" && isset($orderopt["server_id"]) && $orderopt["server_id"]){
                    $server = Products::get_server($orderopt["server_id"]);
                    if($server && $server["status"] == "active"){

                        if($server["maxaccounts"] > 0)
                        {
                            $used           = self::linked_server_count($server["id"]);
                            $remaining      =  $server["maxaccounts"] - $used;
                            if($remaining < 1)
                            {
                                self::$message = Bootstrap::$lang->get_cm("admin/products/shared-server-tx32");
                                return "failed";
                            }
                        }


                        $module_name    = $server["type"]."_Module";
                        if(!class_exists($module_name)) Modules::Load("Servers",$server["type"]);
                        if(class_exists($module_name)){
                            $operations     = new $module_name($server);

                            if(method_exists($operations,"set_order")) $operations->set_order($order);

                            $create         = $operations->create($orderopt);
                            if($create){
                                if(is_array($create)) $orderopt = array_replace_recursive($orderopt,$create);

                                $id_of_conf_opt = $operations->id_of_conf_opt ?? false;
                                if($id_of_conf_opt)
                                    foreach($id_of_conf_opt AS $ad_id => $ad) self::MakeOperationAddon("active",$order,$ad_id);

                            }
                            else
                            {
                                self::$message = $operations->error;
                                return "failed";
                            }
                        }else return false;
                    }
                    else{
                        self::$message = Bootstrap::$lang->get("errors/error2",Config::get("general/local"));
                        return "failed";
                    }
                }else return false;

                $set_order["status"] = "active";
                if(isset($orderopt["login"]["password"]) && $orderopt["login"]["password"]){
                    $pw   = $orderopt["login"]["password"];
                    $pw_d = Crypt::decode($pw,Config::get("crypt/user"));
                    if($pw_d) $pw = $pw_d;
                    $pw   = Crypt::encode($pw,Config::get("crypt/user"));
                    $orderopt["login"]["password"] = $pw;
                }
                if(isset($orderopt["assigned_ips"]) && $orderopt["assigned_ips"]){
                    $assigned_ips = $orderopt["assigned_ips"];
                    if(is_array($assigned_ips)) $assigned_ips = implode("\n",$assigned_ips);
                    if(stristr($assigned_ips,',')) $assigned_ips = str_replace(',',"\n",$assigned_ips);
                    $orderopt['assigned_ips'] = $assigned_ips;
                }
                $set_order["options"] = Utility::jencode($orderopt);
                if($set_order)
                {
                    self::set($order["id"],$set_order);
                }
            } // Create END
            elseif($operation == "suspended"){ // Suspended START
                if(isset($order["options"]["config"]) && $order["options"]["config"]){
                    $orderopt   = $order["options"];

                    if($order["status"] != "suspended"){
                        if(isset($orderopt["server_id"]) && $orderopt["server_id"]){
                            $server = Products::get_server($orderopt["server_id"]);
                            if($server && $server["status"] == "active"){

                                $module_name    = $server["type"]."_Module";
                                if(!class_exists($module_name)) Modules::Load("Servers",$server["type"]);
                                if(class_exists($module_name)){
                                    $operations     = new $module_name($server,$orderopt);
                                    if(method_exists($operations,"set_order")) $operations->set_order($order);

                                    $suspended      = $operations->suspend();
                                    if(!$suspended){
                                        self::$message = $operations->error;
                                        return "failed";
                                    }
                                }else return false;
                            }
                        }else return false;
                    }
                    return "successful";
                }
            } // Suspended END
            elseif($operation == "active" && ($order["status"] == "suspended")){ // Unsuspended START
                if(isset($order["options"]["config"]) && $order["options"]["config"]){
                    $orderopt   = $order["options"];

                    if($order["module"] != "none" && isset($orderopt["server_id"]) && $orderopt["server_id"]){
                        $server = Products::get_server($orderopt["server_id"]);
                        if($server && $server["status"] == "active"){
                            $module_name    = $server["type"]."_Module";
                            if(!class_exists($module_name)) Modules::Load("Servers",$server["type"]);
                            if(class_exists($module_name)){
                                $operations     = new $module_name($server,$orderopt);
                                $operations->set_order($order);
                                $unsuspended    = $operations->unsuspend();

                                if(!$unsuspended){
                                    self::$message = $operations->error;
                                    return "failed";
                                }
                            }else return false;
                        }else{
                            self::$message = Bootstrap::$lang->get("errors/error2",Config::get("general/local"));
                            return "failed";
                        }
                    }else return false;

                    return "successful";
                }
            } // Unsuspended END
            elseif($operation == "terminate" || $operation == "cancelled" || $operation == "delete"){ // Terminate START
                if(isset($order["options"]["config"]) && $order["options"]["config"]){

                    $set_order  = [];
                    $orderopt   = $order["options"];

                    if(isset($data["remove-server-for-updowngrade"])){
                        $event = Events::isCreated("scheduled-operations","order",$order["id"],"remove-server-for-updowngrade",'',0,true);
                        if($event){
                            $order["module"] = $event["data"]["module"];
                            $orderopt = $event["data"]["needs"]["options"];
                            $order["options"] = $orderopt;
                        }
                    }

                    if($order["module"] != "none" && isset($orderopt["server_id"]) && $orderopt["server_id"]){
                        $server = Products::get_server($orderopt["server_id"]);
                        if($server && $server["status"] == "active"){
                            $module_name    = $server["type"]."_Module";
                            if(!class_exists($module_name)) Modules::Load("Servers",$server["type"]);
                            if(class_exists($module_name)){

                                $apply      = true;

                                if(isset($data["updowngrade_remove_server"])){
                                    $updowngrade_remove = $server["updowngrade_remove_server"];
                                    if($updowngrade_remove == "none") $apply = false;
                                    if(substr($updowngrade_remove,0,4) == "then"){
                                        $apply = false;
                                        Events::add_scheduled_operation([
                                            'owner'     => "order",
                                            'owner_id'  => $order["id"],
                                            'name'      => "remove-server-for-updowngrade",
                                            'period'    => "day",
                                            'time'      => substr($updowngrade_remove,5,4),
                                            'module'    => $server["type"],
                                            'command'   => "terminate",
                                            'needs'     => ['options' => $orderopt],
                                        ]);
                                    }
                                }

                                $operations     = new $module_name($server,$orderopt);
                                if(method_exists($operations,"set_order")) $operations->set_order($order);

                                if($apply){
                                    $terminate      = $operations->terminate();
                                    if(!$terminate){
                                        self::$message = $operations->error;
                                        return "failed";
                                    }
                                }
                                unset($orderopt["config"]);
                            }
                        }
                    }

                    if(!isset($data["remove-server-for-updowngrade"])){
                        $set_order["options"] = Utility::jencode($orderopt);
                        if($set_order) self::set($order["id"],$set_order);
                    }
                }
            } // Terminate END
            elseif($operation == "upgrade" || $operation == "downgrade"){ // Updowngrade START

                $set_order  = [];
                $orderopt   = $order["options"];

                $popt           = $product["options"];
                $order["options"]["creation_info"] = $product["module_data"];
                $orderopt["creation_info"] = $product["module_data"];
                $order["product_id"] = $product["id"];

                // We removed the shared server control and it was said that it was not necessary.
                #if(isset($data["synchronized"])){
                if(isset($orderopt["server_id"]) && $orderopt["server_id"]){
                    $server = Products::get_server($orderopt["server_id"]);
                    if($server && $server["status"] == "active"){
                        $module_name    = $server["type"]."_Module";
                        if(!class_exists($module_name)) Modules::Load("Servers",$server["type"]);
                        if(class_exists($module_name)){
                            $operations     = new $module_name($server);
                            if(method_exists($operations,"set_order")) $operations->set_order($order);
                            if(method_exists($operations,"apply_updowngrade")){
                                $apply         = $operations->apply_updowngrade($orderopt);
                                if($apply){
                                    $orderopt["established"] = true;
                                    if(is_array($apply)) $orderopt = array_replace_recursive($orderopt,$apply);
                                }else{
                                    self::$message = $operations->error;
                                    return "failed";
                                }

                                if(isset($orderopt["login"]["password"]) && $orderopt["login"]["password"]){
                                    $pw   = $orderopt["login"]["password"];
                                    $pw_d = Crypt::decode($pw,Config::get("crypt/user"));
                                    if($pw_d) $pw = $pw_d;
                                    $pw   = Crypt::encode($pw,Config::get("crypt/user"));
                                    $orderopt["login"]["password"] = $pw;
                                }
                                if(isset($orderopt["assigned_ips"]) && $orderopt["assigned_ips"]){
                                    $assigned_ips = $orderopt["assigned_ips"];
                                    if(is_array($assigned_ips)) $assigned_ips = implode("\n",$assigned_ips);
                                    if(stristr($assigned_ips,',')) $assigned_ips = str_replace(',',"\n",$assigned_ips);
                                    $orderopt['assigned_ips'] = $assigned_ips;
                                }

                            }
                        }else return false;
                    }else{
                        self::$message = Bootstrap::$lang->get("errors/error2",Config::get("general/local"));
                        return "failed";
                    }
                }else return false;
                #}

                /*
                else
                {
                    if(isset($popt["server_id"]) && $popt["server_id"]){
                        $server = Products::get_server($popt["server_id"]);
                        if($server && $server["status"] == "active"){
                            $module_name    = $server["type"]."_Module";
                            if(!class_exists($module_name)) Modules::Load("Servers",$server["type"]);
                            if(class_exists($module_name)){
                                $operations     = new $module_name($server);
                                if(method_exists($operations,"set_order")) $operations->set_order($order);

                                $apply         = $operations->create($orderopt);
                                if($apply){
                                    $orderopt["established"] = true;
                                    if(is_array($apply)) $orderopt = array_replace_recursive($orderopt,$apply);
                                }else{
                                    self::$message = $operations->error;
                                    return "failed";
                                }

                                if(isset($orderopt["login"]["password"]) && $orderopt["login"]["password"]){
                                    $pw   = $orderopt["login"]["password"];
                                    $pw_d = Crypt::decode($pw,Config::get("crypt/user"));
                                    if($pw_d) $pw = $pw_d;
                                    $pw   = Crypt::encode($pw,Config::get("crypt/user"));
                                    $orderopt["login"]["password"] = $pw;
                                }
                                if(isset($orderopt["assigned_ips"]) && $orderopt["assigned_ips"]){
                                    $assigned_ips = $orderopt["assigned_ips"];
                                    if(is_array($assigned_ips)) $assigned_ips = implode("\n",$assigned_ips);
                                    if(stristr($assigned_ips,',')) $assigned_ips = str_replace(',',"\n",$assigned_ips);
                                    $orderopt['assigned_ips'] = $assigned_ips;
                                }

                            }else return false;
                        }else{
                            self::$message = Bootstrap::$lang->get("errors/error2",Config::get("general/local"));
                            return "failed";
                        }
                    }else return false;
                }
                */
                $set_order["options"] = Utility::jencode($orderopt);
                if($set_order) self::set($order["id"],$set_order);

            } // Updowngrade END
            elseif(stristr($operation,'addon-') && $product)
            {
                $orderopt   = $order["options"];
                if(isset($orderopt["server_id"]) && $orderopt["server_id"]){
                    $server = Products::get_server($orderopt["server_id"]);
                    if($server && $server["status"] == "active"){
                        $module_name    = $server["type"]."_Module";
                        if(!class_exists($module_name)) Modules::Load("Servers",$server["type"]);
                        if(class_exists($module_name)){
                            $operations     = new $module_name($server,$orderopt);

                            $split          = explode("-",$operation);
                            $operation      = $split[1];
                            $m_data         = isset($product["module_data"]) ? $product["module_data"] : '';
                            $m_data         = Utility::jdecode($m_data,true);


                            if(($operation == 'active' || $operation == 'completed') && !$m_data)
                                $method_name = 'addon_create';
                            elseif(($operation == 'active' || $operation == 'completed') && $product["status"] == "suspended")
                                $method_name = 'addon_unsuspend';
                            elseif($operation == 'delete')
                                $method_name = "addon_cancelled";
                            elseif($operation == 'suspended')
                                $method_name = "addon_suspend";
                            else
                                $method_name = 'addon_'.$operation;

                            if(method_exists($operations,$method_name))
                            {
                                if(method_exists($operations,"set_order")) $operations->set_order($order);

                                self::$realized_on_module = property_exists($operations,'id_of_conf_opt') && (($operations->id_of_conf_opt[$product["id"] ?? 0]) ?? false);

                                $apply      = $operations->{$method_name}($product,$data);
                                if(!$apply && !is_array($apply)){
                                    self::$message = $operations->error;
                                    return "failed";
                                }
                                if(is_array($apply)) return $apply;
                            }
                        }
                    }
                }
            }
            return "successful";
        }

        static function software_module_operation($order,$product,$operation,$data=[]){
            return "successful";
        }

        static function sms_module_operation($order,$product,$operation,$data=[]){
            return "successful";
        }

        static function special_module_operation($order,$product,$operation,$data=[]){
            Helper::Load(["Products","Events"]);

            if($operation == "active" && ($order["status"] == "suspended")){ // Unsuspended START
                $module_name    = $order["module"];
                if(!class_exists($module_name)) Modules::Load("Product",$module_name);
                if(class_exists($module_name)){
                    $operations     = new $module_name();
                    if(method_exists($operations,"set_order")) $operations->set_order($order);

                    if(method_exists($operations,"unsuspend")){
                        $unsuspend      = $operations->unsuspend();
                        if(!$unsuspend){
                            self::$message = $operations->error;
                            return "failed";
                        }
                    }
                }else return false;

            } // Unsuspended END
            elseif($operation == "suspended"){ // Suspended START
                if($order["status"] != "suspended"){
                    $module_name    = $order["module"];
                    if(!class_exists($module_name)) Modules::Load("Product",$module_name);
                    if(class_exists($module_name)){
                        $operations     = new $module_name();
                        if(method_exists($operations,"set_order")) $operations->set_order($order);

                        if(method_exists($operations,"suspend")){
                            $suspended      = $operations->suspend();
                            if(!$suspended){
                                self::$message = $operations->error;
                                return "failed";
                            }
                        }
                    }else return false;
                }
            } // Suspended END
            elseif($operation == "create" || $operation == "active" || $operation == "approve"){ // Create START

                $set_order  = [];
                $orderopt   = $order["options"];
                $inprocess  = false;

                if($order["module"] != "none"){
                    $module_name    = $order["module"];
                    if(!class_exists($module_name)) Modules::Load("Product",$module_name);
                    if(class_exists($module_name)){
                        $operations     = new $module_name();
                        if(method_exists($operations,"set_order")) $operations->set_order($order);

                        $create         = $operations->create($orderopt);
                        if($create){
                            if(is_array($create)){
                                if(isset($create["status"])){
                                    if($create["status"] == "inprocess") $inprocess = true;
                                    unset($create["status"]);
                                }
                                $orderopt = array_replace_recursive($orderopt,$create);

                                if(!$inprocess)
                                {
                                    $id_of_conf_opt = $operations->id_of_conf_opt ?? false;
                                    if($id_of_conf_opt)
                                        foreach($id_of_conf_opt AS $ad_id => $ad) self::MakeOperationAddon("active",$order,$ad_id);
                                }

                            }
                        }else{
                            self::$message = $operations->error;
                            return "failed";
                        }
                    }else return false;
                }else return false;

                if(!$inprocess) $set_order["status"] = "active";
                $set_order["options"] = Utility::jencode($orderopt);
                if($set_order) self::set($order["id"],$set_order);
                if($inprocess){
                    self::$message = $operations->error;
                    return "inprocess";
                }
            } // Create END
            elseif($operation == "terminate" || $operation == "cancelled" || $operation == "delete"){ // Terminate START
                $set_order  = [];
                $orderopt   = $order["options"];

                $module_name    = $order["module"];
                if(!class_exists($module_name)) Modules::Load("Product",$module_name);
                if(class_exists($module_name)){
                    $operations     = new $module_name();
                    if(method_exists($operations,"set_order")) $operations->set_order($order);

                    if(method_exists($operations,"delete")){
                        $delete      = $operations->delete();
                        if(!$delete){
                            self::$message = $operations->error;
                            return "failed";
                        }
                    }
                }else return false;

                $set_order["options"] = Utility::jencode($orderopt);
                if($set_order) self::set($order["id"],$set_order);
            } // Terminate END
            elseif($operation == "extend"){
                if($order["module"] != "none"){
                    $module_name    = $order["module"];
                    if(!class_exists($module_name)) Modules::Load("Product",$module_name);
                    if(class_exists($module_name)){
                        $operations     = new $module_name();
                        if(method_exists($operations,"set_order")) $operations->set_order($order);
                        if(method_exists($operations,"extend")){
                            $extend = $operations->extend($data);
                            if(!$extend){
                                self::$message = $operations->error;
                                return "failed";
                            }
                        }
                    }else return false;
                }
            } // Extend END
            elseif($operation == "upgrade" || $operation == "downgrade"){ // Updowngrade START
                if($order["module"] != "none"){
                    $module_name    = $order["module"];
                    if(!class_exists($module_name)) Modules::Load("Product",$module_name);
                    if(class_exists($module_name)){
                        $operations     = new $module_name();
                        if(method_exists($operations,"set_order")) $operations->set_order($order);
                        if(method_exists($operations,"apply_updowngrade")){
                            $apply  = $operations->apply_updowngrade($product);
                            if(!$apply){
                                self::$message = $operations->error;
                                return 'failed';
                            }
                        }
                    }else return false;
                }
            } // Updowngrade END
            elseif($operation == "run-action"){
                if(isset($data["module"]) && $data["module"] != "none"){
                    $module_name    = $data["module"];
                    if(!class_exists($module_name)) Modules::Load("Product",$module_name);
                    if(class_exists($module_name)){
                        $operations     = new $module_name();
                        if(method_exists($operations,"set_order")) $operations->set_order($order);
                        if(method_exists($operations,"run_action")){
                            $run      = $operations->run_action($data);
                            if(!$run){
                                self::$message = $operations->error;
                                return "failed";
                            }elseif($run == "continue") return $run;
                        }
                    }else return false;
                }
            } // Run Action END
            elseif(stristr($operation,'addon-') && $product){ // Addon START
                if($order["module"] != "none"){
                    $module_name    = $order["module"];
                    if(!class_exists($module_name)) Modules::Load("Product",$module_name);
                    if(class_exists($module_name))
                    {
                        $operations     = new $module_name();

                        $split          = explode("-",$operation);
                        $operation      = $split[1];
                        $m_data         = isset($product["module_data"]) ? $product["module_data"] : '';
                        $m_data         = Utility::jdecode($m_data,true);


                        if(($operation == 'active' || $operation == 'completed') && !$m_data)
                            $method_name = 'addon_create';
                        elseif(($operation == 'active' || $operation == 'completed') && $product["status"] == "suspended")
                            $method_name = 'addon_unsuspend';
                        elseif($operation == 'delete')
                            $method_name = "addon_cancelled";
                        elseif($operation == 'suspended')
                            $method_name = "addon_suspend";
                        else
                            $method_name = 'addon_'.$operation;

                        if(method_exists($operations,$method_name))
                        {
                            if(method_exists($operations,"set_order")) $operations->set_order($order);

                            self::$realized_on_module = property_exists($operations,'id_of_conf_opt') && (($operations->id_of_conf_opt[$product["id"] ?? 0]) ?? false);

                            $apply      = $operations->{$method_name}($product,$data);
                            if(!$apply && !is_array($apply))
                            {
                                self::$message = $operations->error;
                                return "failed";
                            }
                            if(is_array($apply)) return $apply;
                        }
                    }
                }
            } // Addon END
            return "successful";
        }

        static function import_hosting($user_id=0,$opt=[],$cdate='',$duedate='',$product=0,$price=[]){
            $item           = [
                'name'      => $product["title"],
                'user_id'   => $user_id,
                'amount'    => $price["amount"],
                'total_amount' => $price["amount"],
                'currency' => $price["cid"],
                'options'   => [
                    'period'    => $price["period"],
                    'period_time' => $price["time"],
                    'type'      => "hosting",
                    'id'        => $product["id"],
                    'domain'  => $opt["domain"],
                    'cdate'   => $cdate,
                    'duedate' => $duedate,
                    'module'  => $opt["module"],
                    'category_id' => $product["category"],
                ],
                'unread'    => 1,
            ];
            $builder        = self::formation_builder($item);
            $order_data     = $builder["data"];
            $product        = $builder["product"];

            $iopt           = $item["options"];
            $oropt          = $order_data["options"];

            $oropt["established"] = true;

            if(isset($iopt["domain"])) $oropt["domain"] = $iopt["domain"];

            if(isset($product["options"]["panel_type"])) $oropt["panel_type"] = $product["options"]["panel_type"];
            if(isset($product["options"]["panel_link"])) $oropt["panel_link"] = $product["options"]["panel_link"];

            if(isset($product["options"]["disk_limit"])) $oropt["disk_limit"] = $product["options"]["disk_limit"] == "unlimited" ? $product["options"]["disk_limit"] : $product["options"]["disk_limit"];
            if(isset($product["options"]["bandwidth_limit"])) $oropt["bandwidth_limit"] = $product["options"]["bandwidth_limit"] == "unlimited" ? $product["options"]["bandwidth_limit"] : $product["options"]["bandwidth_limit"];
            if(isset($product["options"]["email_limit"])) $oropt["email_limit"] = $product["options"]["email_limit"];
            if(isset($product["options"]["database_limit"])) $oropt["database_limit"] = $product["options"]["database_limit"];
            if(isset($product["options"]["addons_limit"])) $oropt["addons_limit"] = $product["options"]["addons_limit"];
            if(isset($product["options"]["subdomain_limit"])) $oropt["subdomain_limit"] = $product["options"]["subdomain_limit"];
            if(isset($product["options"]["ftp_limit"])) $oropt["ftp_limit"] = $product["options"]["ftp_limit"];
            if(isset($product["options"]["park_limit"])) $oropt["park_limit"] = $product["options"]["park_limit"];
            if(isset($product["options"]["max_email_per_hour"])) $oropt["max_email_per_hour"] = $product["options"]["max_email_per_hour"];
            if(isset($product["options"]["cpu_limit"])) $oropt["cpu_limit"] = $product["options"]["cpu_limit"];
            if(isset($product["options"]["server_features"])) $oropt["server_features"] = $product["options"]["server_features"];
            if(isset($product["options"]["dns"])) $oropt["dns"] = $product["options"]["dns"];

            if(isset($product["module"]) && $product["module"] && $product["module"] != "none"){
                $order_data["module"] = $product["module"];
                if(isset($opt["server_id"]) && $opt["server_id"])
                    $oropt["server_id"] = $opt["server_id"];
                elseif(isset($product["module_data"]) && isset($product["module_data"]["server_id"]) && $product["module_data"]["server_id"]) $oropt["server_id"] = $product["module_data"]["server_id"];

                if(isset($product["module_data"]) && isset($product["module_data"]["create_account"]) && $product["module_data"]["create_account"])
                    $oropt["creation_info"] = $product["module_data"]["create_account"];
                elseif(isset($product["module_data"]) && $product["module_data"])
                    $oropt["creation_info"] = $product["module_data"];
            }

            $oropt["config"]["user"] = $opt["username"];

            $order_data["options"] = Utility::jencode($oropt);
            $order_data["status"]  = "active";

            $insert         = self::insert($order_data);
            if(!$insert) return false;
            $order          = self::get($insert);

            return $order;
        }

        static function import_server($user_id=0,$opt=[],$cdate='',$duedate='',$product=0,$price=[],$status='active'){
            $item           = [
                'name'      => $product["title"],
                'user_id'   => $user_id,
                'amount'    => $price["amount"],
                'total_amount' => $price["amount"],
                'currency' => $price["cid"],
                'options'   => [
                    'period'    => $price["period"],
                    'period_time' => $price["time"],
                    'type'      => "server",
                    'id'        => $product["id"],
                    'cdate'   => $cdate,
                    'duedate' => $duedate,
                    'category_id' => $product["category"],
                ],
                'unread'    => 1,
            ];
            $builder        = self::formation_builder($item);
            $order_data     = $builder["data"];
            $product        = $builder["product"];
            $oropt          = $order_data["options"];
            $popt           = $product["options"];
            $poptl          = $product["optionsl"];

            $oropt["hostname"]      = $opt["hostname"];
            $oropt["ip"]            = $opt["ip"];
            $oropt["assigned_ips"]  = is_array($opt["assigned_ips"]) ? implode("\n",$opt["assigned_ips"]) : '';
            $oropt["login"]         = $opt["login"];
            $oropt["established"] = true;

            $order_data["module"] = $product["module"];
            if(isset($opt["server_id"]) && $opt["server_id"])
                $oropt["server_id"] = $opt["server_id"];

            if(isset($popt["processor"]) && $popt["processor"]) $oropt["server_features"]["processor"] = $popt["processor"];
            if(isset($popt["ram"]) && $popt["ram"]) $oropt["server_features"]["ram"] = $popt["ram"];
            if(isset($popt["disk-space"]) && $popt["disk-space"]) $oropt["server_features"]["disk"] = $popt["disk-space"];
            if(isset($popt["raid"]) && $popt["raid"]) $oropt["server_features"]["raid"] = $popt["raid"];
            if(isset($popt["bandwidth"]) && $popt["bandwidth"]) $oropt["server_features"]["bandwidth"] = $popt["bandwidth"];
            if(isset($poptl["location"]) && $poptl["location"]) $oropt["server_features"]["location"] = $poptl["location"];


            elseif(isset($product["options"]["server_id"]) && $product["options"]["server_id"]) $oropt["server_id"] = $product["options"]["server_id"];

            if(isset($product["module_data"]) && $product["module_data"])
                $oropt["creation_info"] = $product["module_data"];


            if(isset($opt["access_data"]) && $opt["access_data"]) $oropt  = array_replace_recursive($oropt,$opt["access_data"]);


            if(isset($opt["add_options"]) && $opt["add_options"])
                $oropt = array_replace_recursive($oropt,$opt["add_options"]);

            $order_data["options"] = Utility::jencode($oropt);
            $order_data["status"]  = $status;

            $insert         = self::insert($order_data);
            if(!$insert) return false;
            $order          = self::get($insert);

            return $order;
        }

        static function add_history($user_id=0,$order_id=0,$name='',$data=[])
        {
            $data['ip'] = UserManager::GetIP();
            Helper::Load("Events");
            return Events::create([
                'user_id'   => $user_id,
                'type'      => "log",
                'owner'     => "order",
                'owner_id'  => $order_id,
                'name'      => $name,
                'data'      => $data,
            ]);
        }

        static function download($order=0,$lang='')
        {
            if(!$order)
            {
                self::$message = "Order not found";
                return false;
            }
            if(!$lang) $lang = Bootstrap::$lang->clang;

            Helper::Load("Products");

            if(!is_array($order)) $order      = self::get($order);
            if(!$order)
            {
                self::$message = "Order not found";
                return false;
            }

            $product    = Products::get($order["type"],$order["product_id"],$lang);

            $delivery_file  = isset($order["options"]["delivery_file"]) ? $order["options"]["delivery_file"] : false;
            $product_file   = isset($product["options"]["download_file"]) ? $product["options"]["download_file"] : false;
            $download_link  = isset($product["options"]["download_link"]) ? $product["options"]["download_link"] : false;

            if($delivery_file)
                $download_file = RESOURCE_DIR."uploads".DS."orders".DS.$delivery_file;
            elseif($product_file)
                $download_file = RESOURCE_DIR."uploads".DS."products".DS.$product_file;
            else
                $download_file = false;


            $hooks           = Hook::run("OrderDownload",$order,$product,[
                'download_file'     => $download_file,
                'download_link'     => $download_link,
            ]);

            if($hooks)
            {
                foreach($hooks AS $hook)
                {
                    if($hook && is_array($hook))
                    {
                        if(isset($hook["error"]) && $hook["error"])
                        {
                            self::$message = $hook["error"];
                            return false;
                        }
                        else
                        {
                            if(isset($hook["download_file"]) && $hook["download_file"])
                                $download_file = $hook["download_file"];
                            if(isset($hook["download_link"]) && $hook["download_link"])
                                $download_link = $hook["download_link"];
                        }
                    }
                }
            }

            if(!$download_file && !$download_link)
            {
                self::$message = "No file to download was found.";
                return false;
            }

            if(!$download_link && $download_file && (!file_exists($download_file) || !is_file($download_file)))
            {
                self::$message = "Does not exists file.";
                return false;
            }

            if(UserManager::LoginCheck("member") || defined("API_CL")){
                $ses_udata  = UserManager::LoginData("member");
                $uid        = 0;
                if($ses_udata) $uid = $ses_udata["id"];
                elseif(defined("API_CL")) $uid = $order["owner_id"];
                if($uid) User::addAction($uid,"alteration","downloaded-order-file",[
                    'id' => $order["id"],
                    'time' => DateManager::Now(Config::get("options/date-format")." H:i"),
                ]);
            }

            if(!$download_file && $download_link){
                Utility::redirect(Utility::link_determiner($download_link,false,false));
                return true;
            }

            $time           = time();
            $ext            = explode(".",$download_file);
            $ext            = end($ext);
            $size           = filesize($download_file);
            $quoted         = $order["name"];
            if(isset($order["options"]["domain"]) && $order["options"]["domain"]) $quoted .= "-".$order["options"]["domain"];
            if(isset($order["options"]["version"]) && $order["options"]["version"]) $quoted .= "-v".$order["options"]["version"];
            $quoted         = Filter::permalink($quoted);
            $quoted         .= "-".$time;
            $quoted         .= ".".$ext;


            echo FileManager::file_read($download_file,$size);

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $quoted);
            header('Content-Transfer-Encoding: binary');
            header('Connection: Keep-Alive');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' .$size);

            return true;
        }

        static function generate_password($length = 9, $add_dashes = false, $available_sets = 'luds'){
            $sets = array();
            if(strpos($available_sets, 'l') !== false)
                $sets[] = 'abcdefghjkmnpqrstuvwxyz';
            if(strpos($available_sets, 'u') !== false)
                $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
            if(strpos($available_sets, 'd') !== false)
                $sets[] = '23456789';
            if(strpos($available_sets, 's') !== false)
                $sets[] = '+_-*#!+_-*#!';
            $all = '';
            $password = '';
            foreach($sets as $set)
            {
                $password .= $set[array_rand(str_split($set))];
                $all .= $set;
            }
            $all = str_split($all);
            for($i = 0; $i < $length - count($sets); $i++)
                $password .= $all[array_rand($all)];
            $password = str_shuffle($password);
            if(!$add_dashes)
                return $password;
            $dash_len = floor(sqrt($length));
            $dash_str = '';
            while(strlen($password) > $dash_len)
            {
                $dash_str .= substr($password, 0, $dash_len) . '-';
                $password = substr($password, $dash_len);
            }
            $dash_str .= $password;
            return $dash_str;
        }

        static function generate_password_force($length = 9, $add_dashes = false, $available_sets = 'luds'){
            $sets = array();
            if(strpos($available_sets, 'l') !== false)
                $sets[] = 'abcdefghjkmnpqrstuvwxyz';
            if(strpos($available_sets, 'u') !== false)
                $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
            if(strpos($available_sets, 'd') !== false)
                $sets[] = '23456789';
            if(strpos($available_sets, 's') !== false)
                $sets[] = '!@#$%^&*?,';
            $all = '';
            $password = '';
            foreach($sets as $set)
            {
                $password .= $set[array_rand(str_split($set))];
                $all .= $set;
            }
            $all = str_split($all);
            for($i = 0; $i < $length - count($sets); $i++)
                $password .= $all[array_rand($all)];
            $password = str_shuffle($password);
            if(!$add_dashes)
                return $password;
            $dash_len = floor(sqrt($length));
            $dash_str = '';
            while(strlen($password) > $dash_len)
            {
                $dash_str .= substr($password, 0, $dash_len) . '-';
                $password = substr($password, $dash_len);
            }
            $dash_str .= $password;
            return $dash_str;
        }

        static function generate_key($prefix = '',$dashes=true,$sets='ud',$character_length=15)
        {
            $character_sets      = '';
            $character_l         = 'abcdefghijklmnopqrstuvwxyz';
            $character_u         = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $character_d         = '0123456789';
            $character_s         = '+*!@';

            if(strpos($sets, 'l') !== false) $character_sets .= $character_l;
            if(strpos($sets, 'u') !== false) $character_sets .= $character_u;
            if(strpos($sets, 'd') !== false) $character_sets .= $character_d;
            if(strpos($sets, 's') !== false) $character_sets .= $character_s;
            if($character_length < 15) $character_length = 15;

            if($character_sets === '')
            {
                self::$message = Bootstrap::$lang->get_cm("admin/products/software-licensing-30");
                return false;
            }

            if(strlen($prefix) > 0)
            {
                $prefix .= "-";
                $character_length -= 1;
            }

            if($character_length < (15 - (strlen($prefix) > 0 ? 1 : 0)))
            {
                self::$message = Bootstrap::$lang->get_cm("admin/products/software-licensing-31");
                return false;
            }

            $chars      =  str_split($character_sets);
            $key        = '';
            for($i=0; $i < $character_length;$i++) $key .= $chars[array_rand($chars)];

            if($dashes)
            {
                $key_n    = '';
                $key_c    = 0;
                $key_r    = $character_length;

                foreach(str_split($key) AS $k => $v)
                {
                    $key_r--;
                    if($key_c === 4)
                    {
                        $key_n .= $key_r === 0 ? $v : '-';
                        $key_c = 0;
                    }
                    else
                    {
                        $key_n .= $v;
                        $key_c++;
                    }
                }
                $key             = $key_n;
                $key             = rtrim($key,"-");
            }

            if(strlen($prefix) > 0) $key  = $prefix . $key;
            return $key;
        }

        static function generate_software_key($options=[])
        {
            $key_sets   = '';
            $key_sets   .= isset($options["key_l"]) && $options["key_l"] ? 'l' : '';
            $key_sets   .= isset($options["key_u"]) && $options["key_u"] ? 'u' : '';
            $key_sets   .= isset($options["key_d"]) && $options["key_d"] ? 'd' : '';
            $key_sets   .= isset($options["key_s"]) && $options["key_s"] ? 's' : '';
            $key_dashes = isset($options["key_dashes"]) && $options["key_dashes"];
            $key_prefix = isset($options["key_prefix"]) ? $options["key_prefix"] : '';
            $key_length = isset($options["key_length"]) ? $options["key_length"] : '';
            return self::generate_key($key_prefix,$key_dashes,$key_sets,$key_length);
        }

        static function get_subscription($id=0,$identifier = '',$module='')
        {
            $id = (int) $id;

            if(!($id > 0 || strlen($identifier) > 0)) return [];

            $stmt = Models::$init->db->select()->from("users_products_subscriptions");

            if($module) $stmt->where("module","=",$module,"&&");

            $stmt->where("(");
            if($id) $stmt->where("id","=",$id,$identifier ? "||" : '');
            if($identifier) $stmt->where("identifier","=",$identifier);
            $stmt->where(")","","","&&");

            $stmt->where("status","!=","deleted");

            return $stmt->build() ? $stmt->getAssoc() : [];
        }

        static function set_subscription($id=0,$data=[])
        {
            if(!isset($data["updated_at"])) $data["updated_at"] = DateManager::Now();
            return Models::$init->db->update("users_products_subscriptions",$data)->where("id","=",$id)->save();
        }

        static function remove_subscription($id=0)
        {
            return Models::$init->db->delete("users_products_subscriptions")->where("id","=",$id)->run();
        }

        static function create_subscription($data=[])
        {
            return Models::$init->db->insert("users_products_subscriptions",$data) ? Models::$init->db->lastID() : 0;
        }

        static function auto_d_i_subscription($subscription = [])
        {
            /*
            $g_module       = $subscription["module"];
            $h_items        = Utility::jdecode($subscription["items"],true);
            $n_items        = [];
            $link_orders    =  Models::$init->db->select("type,product_id")->from("users_products");
            $link_orders->where("subscription_id","=",$subscription["id"]);
            $link_orders    = $link_orders->build() ? $link_orders->fetch_object() : [];

            $link_addons    =  Models::$init->db->select("type,product_id")->from("users_products_addons");
            $link_addons->where("subscription_id","=",$subscription["id"]);
            $link_addons    = $link_addons->build() ? $link_addons->fetch_object() : [];

            */
        }

        static function cancel_subscription($subscription=[],$order=[],$addon=[])
        {
            if(!$subscription) return true;
            if($subscription["status"] == "cancelled") return true;
            if($subscription["items"]) $subscription["items"] = Utility::jdecode($subscription["items"],true);

            $g_module       = $subscription["module"] ?? NULL;

            if(!Validation::isEmpty($g_module))
            {
                $mod            = Modules::Load("Payment",$g_module);
                if(!class_exists($g_module))
                {
                    self::$message = 'Module class not found in payment gateway : '.$g_module;
                    return false;
                }

                $class      = new $g_module();

                if(!method_exists($class,'cancel_subscription'))
                {
                    self::$message = 'This payment gateway does not support unsubscribe.';
                    return false;
                }

                $cancel         = $class->cancel_subscription($subscription);

                if(!$cancel)
                {
                    self::$message = $class->error;
                    return false;
                }
            }

            self::set_subscription($subscription["id"],['status' => 'cancelled']);

            return true;
        }

        static function sync_subscription($subscription=[])
        {
            $subscription["items"]      = Utility::jdecode($subscription["items"],true);

            $module_name        = $subscription["module"];
            $mod                = Modules::Load("Payment",$module_name);

            if(!$mod || !class_exists($module_name)) return $subscription;

            $class              = new $module_name();

            if(method_exists($class,'get_subscription'))
            {
                $get                        = $class->get_subscription($subscription);
                if($get)
                {
                    $set_subscription       = [];

                    if($get["status"] != $subscription["status"]) $set_subscription["status"] = $get["status"];
                    if($subscription["status_msg"] != $get["status_msg"])
                        $set_subscription["status_msg"] = $get["status_msg"];

                    if($subscription["last_paid_date"] != $get["last_paid"]["time"])
                        $set_subscription["last_paid_date"] = $get["last_paid"]["time"];

                    if($subscription["next_payable_date"] != $get["next_payable"]["time"])
                        $set_subscription["next_payable_date"] = $get["next_payable"]["time"];

                    $last_paid_fee      = round(Money::exChange($get["last_paid"]["fee"]["amount"],$get["last_paid"]["fee"]["currency"],$subscription["currency"]),2);

                    if(round($subscription["last_paid_fee"],2) != $last_paid_fee)
                        $set_subscription["last_paid_fee"] = $last_paid_fee;

                    $next_payable_fee      = round(Money::exChange($get["next_payable"]["fee"]["amount"],$get["next_payable"]["fee"]["currency"],$subscription["currency"]),2);

                    if(round($subscription["next_payable_fee"],2) != $next_payable_fee)
                        $set_subscription["next_payable_fee"] = $next_payable_fee;

                    if($set_subscription)
                    {
                        self::set_subscription($subscription["id"],$set_subscription);
                        $subscription = self::get_subscription($subscription["id"]);
                        $subscription["items"] = Utility::jdecode($subscription["items"],true);
                    }

                    return $subscription;
                }
            }
            return $subscription;
        }

        static function linked_server_count($server_id=0,$status='')
        {
            $stmt = Models::$init->db->select("id")->from("users_products");
            if($status)
                $stmt->where("status","=",$status,"&&");
            else
                $stmt->where("FIND_IN_SET(status,'suspended,active')","","","&&");
            $stmt->where("JSON_UNQUOTE(JSON_EXTRACT(options,'$.server_id'))","=",$server_id);
            return $stmt->build() ? $stmt->rowCounter() : 0;
        }

        static function detect_docs_in_domain($order=[],$tld_info=[])
        {
            $options                    = $order["options"] ?? [];
            $ll                         = Config::get("general/local");
            $ulang                      = $ll;
            $module                     = $order["module"] ?? '';
            $tld                        = $options["tld"] ?? '';

            if(!defined("ADMINISTRATOR")) $ulang = Bootstrap::$lang->clang;


            if($tld_info)
            {
                $module     = $tld_info["module"];
                $tld        = $tld_info["name"];
            }

            // External Verification Docs
            $operator_docs          = $options["verification_operator_docs"] ?? [];

            // Found Module Information/Document Fields
            if($module && $module !== "none")
            {
                $fetchModule        = Modules::Load("Registrars",$module);
                $module_config      = $fetchModule["config"] ?? [];
                $module_docs        = $module_config["settings"]["doc-fields"][$tld] ?? [];
            }

            // Found Manuel Information/Document Fields
            $found_doc_fields       = Models::$init->db->select()->from("tldlist_docs");
            $found_doc_fields->where("tld","=",$tld);
            $found_doc_fields->order_by("sortnum ASC");
            if($found_doc_fields->build()) $manuel_doc_fields = $found_doc_fields->fetch_assoc();


            $info_docs      = [];

            if(isset($module_docs) && is_array($module_docs) && sizeof($module_docs) > 0)
                foreach($module_docs AS $md_k => $md_c)
                {

                    if(isset($md_c["name"])) $md_c["name"] = RegistrarModule::get_doc_lang($md_c["name"]);
                    if(isset($md_c["description"])) $md_c["description"] = RegistrarModule::get_doc_lang($md_c["description"]);
                    if(isset($md_c["options"]))
                    {
                        $opts = $md_c["options"];
                        if($opts) foreach($opts AS $k => $v) $opts[$k] = RegistrarModule::get_doc_lang($v);
                        $md_c["options"] = $opts;
                    }
                    $info_docs["mod_".$md_k] = $md_c;
                }
            if(isset($manuel_doc_fields) && is_array($manuel_doc_fields) && sizeof($manuel_doc_fields) > 0)
            {
                foreach($manuel_doc_fields AS $md)
                {
                    $md["languages"]    = Utility::jdecode($md["languages"],true);
                    $md["options"]      = Utility::jdecode($md["options"],true);

                    $first_d_ch         = current($md["languages"]);
                    $d_name             = $first_d_ch["name"] ?? 'Noname';

                    if(isset($md["languages"][$ulang]["name"]))
                        $d_name         = $md["languages"][$ulang]["name"] ?? 'Noname';

                    if(!$d_name) $d_name = "Noname";


                    $d_opts             = [];

                    if($md["type"] == "select" && $md["options"] && sizeof($md["options"]) > 0)
                    {
                        if(is_array($md["options"]) && sizeof($md["options"]) > 0)
                        {
                            foreach($md["options"] AS $d_opt_k => $d_opt)
                            {
                                $d_opt_name = $d_opt[$ll]["name"] ?? 'Noname';
                                if(isset($d_opt[$ulang])) $d_opt_name = $d_opt[$ulang]["name"] ?? 'Noname';
                                $d_opts[$d_opt_k] = $d_opt_name;
                            }
                        }

                    }

                    $info_docs["d_".$md["id"]] = [
                        'type'  => $md["type"],
                        'name'  => $d_name,
                    ];
                    if(isset($md["options"]["allowed_ext"])) $info_docs["d_".$md["id"]]["allowed_ext"] = $md["options"]["allowed_ext"];
                    if(isset($md["options"]["max_file_size"])) $info_docs["d_".$md["id"]]["max_file_size"] = $md["options"]["max_file_size"];
                    if(sizeof($d_opts) > 0) $info_docs["d_".$md["id"]]["options"] = $d_opts;
                }
            }
            if(is_array($operator_docs) && sizeof($operator_docs))
            {
                foreach($operator_docs AS $od_k => $od)
                {
                    $info_docs["op_".$od_k] = [
                        'type' => $od["type"],
                        'name' => $od["name"],
                    ];
                    if(isset($od["options"]) && $od["options"]) $info_docs["op_".$od_k]["options"] = $od["options"];
                }
            }

            return $info_docs;
        }

        static function domain_module_docs($order=[])
        {
            $module_docs        = [];
            $order_docs         = Models::$init->db->select()->from("users_products_docs");
            $order_docs->where("owner_id","=",$order["id"],"&&");
            $order_docs->where("(");
            $order_docs->where("status","=","pending","||");
            $order_docs->where("status","=","verified");
            $order_docs->where(")");
            $order_docs->order_by("id ASC");
            $order_docs         = $order_docs->build() ? $order_docs->fetch_assoc() : [];
            if($order_docs)
            {
                foreach($order_docs AS $d)
                {
                    $m_data     = $d["module_data"] ? Crypt::decode($d["module_data"],Config::get("crypt/user")) : '';
                    if($m_data)
                    {
                        $m_data = Utility::jdecode($m_data,true);
                        if(isset($m_data["key"]) && isset($m_data["value"])) $module_docs[$m_data["key"]] = $m_data["value"];
                    }
                    /*
                    else
                    {
                        if($d["file"])
                        {
                            $decode_file    = Crypt::decode($d["file"],Config::get("crypt/user"));
                            $decode_file    = Utility::jdecode($decode_file);
                            $d_data         = $decode_file["path"];
                        }
                        else
                            $d_data = Crypt::decode($d["value"],Config::get("crypt/user"));

                        $m_data[$d["doc_id"]."|".$d["name"]] = $d_data;
                    }
                    */
                }
            }

            return $module_docs;
        }

        static function cancel_order_invoices($order=[])
        {
            Helper::Load(["Invoices"]);

            $invoices = Models::$init->db->select("its.id AS item_id,inv.id AS invoice_id")->from("invoices_items AS its");
            $invoices->join("LEFT","invoices AS inv","inv.id=its.owner_id");
            $invoices->where("user_pid","=",$order["id"],"&&");
            $invoices->where("inv.status","=","unpaid");
            $invoices->order_by("inv.id DESC");
            $invoices = $invoices->build() ? $invoices->fetch_assoc() : [];

            if($invoices)
                foreach($invoices AS $r)
                    Invoices::cancelled_order($r["invoice_id"],$order);

            return true;
        }
    }