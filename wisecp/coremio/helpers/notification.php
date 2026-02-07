<?php
    defined('CORE_FOLDER') OR exit('You can not get in here!');

    Class Notification {
        private static $department_admins=[],$departments=[];

        private static function departmentAdmins($departments=[]){
            if(!$departments) return false;
            $result = [
                'emails' => [],
                'phones' => [],
            ];
            if(!class_exists("Tickets")) Helper::Load(["Tickets"]);
            if(!class_exists("User")) Helper::Load(["User"]);

            foreach($departments AS $department){
                if(isset(self::$departments[$department])) $department = self::$departments[$department];
                else $department = Tickets::get_department($department,false,"t1.id,t1.appointees");
                if($department && $department["appointees"]){
                    $appointees = explode(",",$department["appointees"]);
                    foreach($appointees AS $user_id){
                        if(!isset(self::$department_admins[$user_id])){
                            $gData = User::getData($user_id,"full_name,email,lang,status","array");
                            if(($gData["status"] ?? "active") != "active") $gData = [];
                            if($gData) $gData = array_merge($gData,User::getInfo($user_id,"gsm,gsm_cc"));
                            self::$department_admins[$user_id] = $gData;
                        }
                        $user = self::$department_admins[$user_id];
                        if($user){
                            $result["emails"][$user["email"]] = $user["full_name"]."|".$user["lang"];
                            if($user["gsm"] != ''){
                                $gsm_body = $user["gsm"]."|".$user["gsm_cc"]."|".$user["lang"];
                                if(!in_array($gsm_body,$result["phones"])) $result["phones"][$user["full_name"]] = $gsm_body;
                            }
                        }
                    }
                }
            }
            return $result;
        }

        private static function admin_contact_handler($function,$settings=[]){
            $emails     = [];
            $phones     = [];
            $admins     = [];

            if($settings["admin-mail"] || $settings["admin-sms"])
                $admins         = self::departmentAdmins($settings["departments"]);

            if($settings["admin-mail"]){
                $emails         = isset($admins["emails"]) ? $admins["emails"] : [];
                if($settings["emails"]){
                    $settingsEmails = explode(",",$settings["emails"]);
                    foreach($settingsEmails AS $row) $emails[$row] = NULL;
                }
                if(!$emails && ($function == "contact_form")){
                    $contactEmails  = Config::get("contact/email-addresses");
                    foreach($contactEmails AS $row) $emails[$row] = NULL;
                }
            }


            if($settings["admin-sms"]){
                $phones         = isset($admins["phones"]) ? $admins["phones"] : [];
                if($settings["phones"]){
                    $settingsPhones = explode(",",$settings["phones"]);
                    foreach($settingsPhones AS $row){
                        $formatter = Filter::phone_smash($row);
                        if($formatter) $phones[] = $formatter["number"]."|".$formatter["cc"];
                    }
                }
            }


            return [
                'emails' => $emails,
                'phones' => $phones
            ];
        }

        static function invoice_created($invoice=0){
            Helper::Load(["Invoices","User","Money"]);
            if(!is_array($invoice)) $invoice    = Invoices::get($invoice);

            $settings       = Config::get("notifications/invoice/invoice-created");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];



            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($invoice["user_id"],"id,email,lang,country,full_name","array");

            if(!$udata) return 'OK';
            
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $token      = Crypt::encode(Utility::jencode([
                'user_id' => $invoice["user_id"],
                'id'      => $invoice["id"],
            ]),Config::get("crypt/user"));

            $items              = Invoices::item_listing($invoice);
            $invoice_calculate  = self::invoice_calculate($invoice);
            $discounts          = isset($invoice_calculate["discounts"]) ? $invoice_calculate["discounts"] : [];
            if(isset($invoice_calculate["data"]) && $invoice_calculate["data"]) $invoice = array_replace_recursive($invoice,$invoice_calculate["data"]);

            $discount_total = $invoice_calculate["total_discount_amount"] ?? 0;

            $variables = [
                'invoice_id'                => $invoice["id"],
                'invoice_num'               => $invoice["number"] ? $invoice["number"] : "#".$invoice["id"],
                'invoice_payment_link'      => Controllers::$init->CRLink("share-invoice",false,$udata["lang"])."?token=".$token,
                'invoice_items'             => self::invoice_items($invoice,$items,$discounts,"text"),
                'invoice_items_html'        => self::invoice_items($invoice,$items,$discounts,"html"),
                'invoice_subtotal'          => Money::formatter_symbol($invoice["subtotal"],$invoice["currency"]),
                'invoice_total'             => Money::formatter_symbol($invoice["total"],$invoice["currency"]),
                'invoice_discount_total'    => Money::formatter_symbol($discount_total,$invoice["currency"]),
                'invoice_tax_rate'          => str_replace(".00","",$invoice["taxrate"]),
                'invoice_tax'               => Money::formatter_symbol($invoice["tax"],$invoice["currency"]),
                'invoice_date_created'      => DateManager::format(Config::get("options/date-format"),$invoice["cdate"]),
                'invoice_date_due'          => DateManager::format(Config::get("options/date-format"),$invoice["duedate"]),
            ];

            $locall     = Config::get("general/local");

            $scm                        = Config::get("modules/card-storage-module");
            $submit_ntf                 = true;
            if($scm && $scm != "none")
            {
                $stmt       = Models::$init->db->select("id")->from("users_stored_cards");
                $stmt->where("user_id","=",$udata["id"],"&&");
                $stmt->where("module","=",$scm);
                if($stmt->build())
                {
                    foreach($items AS $it)
                    {
                        if($it["user_pid"])
                        {
                            $o_check    = Models::$init->db->select("id")->from("users_products");
                            $o_check->where("id","=",$it["user_pid"],"&&");
                            $o_check->where("auto_pay","=",1);
                            if($o_check->build())
                            {
                                $submit_ntf = false;
                            }
                        }
                    }
                }
            }

            foreach($items AS $it)
            {
                if(isset($it["options"]["event"]) && $it["options"]["event"] == "ExtendAddonPeriod")
                {
                    $o_check    = Models::$init->db->select("id,subscription_id")->from("users_products_addons");
                    $o_check->where("id","=",$it["options"]["event_data"]["addon_id"],"&&");
                    $o_check->where("subscription_id","!=",0);
                    if($o_check->build())
                    {
                        $o_check        = $o_check->getObject();
                        $subscription   = Orders::get_subscription($o_check->subscription_id);
                        if($subscription && $subscription["status"] != "cancelled") $submit_ntf = false;
                    }
                }
                elseif($it["user_pid"])
                {
                    $o_check    = Models::$init->db->select("id,subscription_id")->from("users_products");
                    $o_check->where("id","=",$it["user_pid"],"&&");
                    $o_check->where("subscription_id","!=",0);
                    if($o_check->build())
                    {
                        $o_check        = $o_check->getObject();
                        $subscription   = Orders::get_subscription($o_check->subscription_id);
                        if($subscription && $subscription["status"] != "cancelled") $submit_ntf = false;
                    }
                }
            }

            if($settings["send-pdf"] ?? 1)
            {
                $pdf_creator = Invoices::create_pdf($invoice);
                if($pdf_creator)
                {
                    $pdf_name = 'invoice-'.str_replace("#","",$invoice["number"]).'.pdf';
                    $hook     = Hook::run("SetInvoiceMailPdfName",$invoice);
                    if($hook && is_array($hook)) foreach($hook AS $h) $pdf_name = $h;
                }
                else
                    LogManager::core_error_log(500,Invoices::$message,__FILE__,__LINE__);
            }
            else
                $pdf_creator = false;


            if($submit_ntf && $mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "invoice/invoice-created",$variables,$lang,$udata["id"]);
                    if($pdf_creator) $send->addAttachment($pdf_creator["path"],$pdf_name);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"invoice-created",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($submit_ntf && $mail && $settings["user-mail"])
            {
                $send   = $mail->body(null, "invoice/invoice-created",$variables,$udata["lang"],$udata["id"]);
                if($pdf_creator) $send->addAttachment($pdf_creator["path"],$pdf_name);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"invoice-created",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($submit_ntf && $sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "invoice/invoice-created", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"invoice-created",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($submit_ntf && $sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "invoice/invoice-created", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"invoice-created",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "invoice",
                'owner_id' => $invoice["id"],
                'name' => "invoice-created",
            ]);



            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "invoice",
                'name'          => "invoice-created",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);


            return $errors ? $errors : "OK";
        }
        static function invoice_returned($invoice=0){

            Helper::Load(["Invoices","User","Money"]);
            if(!is_array($invoice)) $invoice    = Invoices::get($invoice);

            $settings       = Config::get("notifications/invoice/invoice-returned");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($invoice["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $token      = Crypt::encode(Utility::jencode([
                'user_id' => $invoice["user_id"],
                'id'      => $invoice["id"],
            ]),Config::get("crypt/user"));

            $items      = Invoices::item_listing($invoice);

            $invoice_calculate  = self::invoice_calculate($invoice);
            $discounts          = isset($invoice_calculate["discounts"]) ? $invoice_calculate["discounts"] : [];
            if(isset($invoice_calculate["data"]) && $invoice_calculate["data"]) $invoice = array_replace_recursive($invoice,$invoice_calculate["data"]);

            $discount_total = $invoice_calculate["total_discount_amount"] ?? 0;


            $variables = [
                'invoice_id'                => $invoice["id"],
                'invoice_num'               => $invoice["number"] ? $invoice["number"] : "#".$invoice["id"],
                'invoice_payment_link'      => Controllers::$init->CRLink("share-invoice",false,$udata["lang"])."?token=".$token,
                'invoice_items'             => self::invoice_items($invoice,$items,$discounts,"text"),
                'invoice_items_html'        => self::invoice_items($invoice,$items,$discounts,"html"),
                'invoice_subtotal'          => Money::formatter_symbol($invoice["subtotal"],$invoice["currency"]),
                'invoice_total'             => Money::formatter_symbol($invoice["total"],$invoice["currency"]),
                'invoice_discount_total'    => Money::formatter_symbol($discount_total,$invoice["currency"]),
                'invoice_tax_rate'          => str_replace(".00","",$invoice["taxrate"]),
                'invoice_tax'               => Money::formatter_symbol($invoice["tax"],$invoice["currency"]),
                'invoice_date_created'      => DateManager::format(Config::get("options/date-format"),$invoice["cdate"]),
                'invoice_date_due'          => DateManager::format(Config::get("options/date-format"),$invoice["duedate"]),
                'invoice_refund_date'       => DateManager::format(Config::get("options/date-format"),$invoice["refunddate"]),
            ];

            $locall     = Config::get("general/local");

            if($settings["send-pdf"] ?? 1)
            {
                $pdf_creator = Invoices::create_pdf($invoice);
                if($pdf_creator)
                {
                    $pdf_name = 'invoice-'.str_replace("#","",$invoice["number"]).'.pdf';
                    $hook     = Hook::run("SetInvoiceMailPdfName",$invoice);
                    if($hook && is_array($hook)) foreach($hook AS $h) $pdf_name = $h;
                }
                else
                    LogManager::core_error_log(500,Invoices::$message,__FILE__,__LINE__);
            }
            else
                $pdf_creator = false;


            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "invoice/invoice-returned",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"invoice-returned",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "invoice/invoice-returned",$variables,$udata["lang"],$udata["id"]);
                if($pdf_creator) $send->addAttachment($pdf_creator["path"],$pdf_name);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"invoice-returned",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "invoice/invoice-returned", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"invoice-returned",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "invoice/invoice-returned", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"invoice-returned",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "invoice",
                'owner_id' => $invoice["id"],
                'name' => "invoice-returned"
            ]);


            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "invoice",
                'name'          => "invoice-returned",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function invoice_cancelled($invoice=0){

            Helper::Load(["Invoices","User","Money"]);
            if(!is_array($invoice)) $invoice    = Invoices::get($invoice);

            $settings       = Config::get("notifications/invoice/invoice-cancelled");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($invoice["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $token      = Crypt::encode(Utility::jencode([
                'user_id' => $invoice["user_id"],
                'id'      => $invoice["id"],
            ]),Config::get("crypt/user"));

            $items      = Invoices::item_listing($invoice);

            $invoice_calculate  = self::invoice_calculate($invoice);
            $discounts          = isset($invoice_calculate["discounts"]) ? $invoice_calculate["discounts"] : [];
            if(isset($invoice_calculate["data"]) && $invoice_calculate["data"]) $invoice = array_replace_recursive($invoice,$invoice_calculate["data"]);

            $discount_total = $invoice_calculate["total_discount_amount"] ?? 0;

            $variables = [
                'invoice_id'                => $invoice["id"],
                'invoice_num'               => $invoice["number"] ? $invoice["number"] : "#".$invoice["id"],
                'invoice_payment_link'      => Controllers::$init->CRLink("share-invoice",false,$udata["lang"])."?token=".$token,
                'invoice_items'             => self::invoice_items($invoice,$items,$discounts,"text"),
                'invoice_items_html'        => self::invoice_items($invoice,$items,$discounts,"html"),
                'invoice_subtotal'          => Money::formatter_symbol($invoice["subtotal"],$invoice["currency"]),
                'invoice_total'             => Money::formatter_symbol($invoice["total"],$invoice["currency"]),
                'invoice_discount_total'    => Money::formatter_symbol($discount_total,$invoice["currency"]),
                'invoice_tax_rate'          => str_replace(".00","",$invoice["taxrate"]),
                'invoice_tax'               => Money::formatter_symbol($invoice["tax"],$invoice["currency"]),
                'invoice_date_created'      => DateManager::format(Config::get("options/date-format"),$invoice["cdate"]),
                'invoice_date_due'          => DateManager::format(Config::get("options/date-format"),$invoice["duedate"]),
                'invoice_cancelled_date'    => DateManager::Now(Config::get("options/date-format")),
            ];

            $locall     = Config::get("general/local");

            if($settings["send-pdf"] ?? 1)
            {
                $pdf_creator = Invoices::create_pdf($invoice);
                if($pdf_creator)
                {
                    $pdf_name = 'invoice-'.str_replace("#","",$invoice["number"]).'.pdf';
                    $hook     = Hook::run("SetInvoiceMailPdfName",$invoice);
                    if($hook && is_array($hook)) foreach($hook AS $h) $pdf_name = $h;
                }
                else
                    LogManager::core_error_log(500,Invoices::$message,__FILE__,__LINE__);
            }
            else
                $pdf_creator = false;




            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "invoice/invoice-cancelled",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"invoice-cancelled",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "invoice/invoice-cancelled",$variables,$udata["lang"],$udata["id"]);
                if($pdf_creator) $send->addAttachment($pdf_creator["path"],$pdf_name);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"invoice-cancelled",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "invoice/invoice-cancelled", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"invoice-cancelled",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "invoice/invoice-cancelled", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"invoice-cancelled",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "invoice",
                'owner_id' => $invoice["id"],
                'name' => "invoice-cancelled"
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "invoice",
                'name'          => "invoice-cancelled",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function invoice_reminder($invoice=0,$remaining_day=0){

            Helper::Load(["Invoices","User","Money"]);
            if(!is_array($invoice)) $invoice    = Invoices::get($invoice);

            $invoice_vars = $invoice;
            $invoice_vars['source'] = Invoices::action_source();

            Hook::run("InvoicePaymentReminder",$invoice_vars);


            $settings       = Config::get("notifications/invoice/invoice-reminder");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($invoice["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $token      = Crypt::encode(Utility::jencode([
                'user_id' => $invoice["user_id"],
                'id'      => $invoice["id"],
            ]),Config::get("crypt/user"));

            $items      = Invoices::item_listing($invoice);

            $invoice_calculate  = self::invoice_calculate($invoice);
            $discounts          = isset($invoice_calculate["discounts"]) ? $invoice_calculate["discounts"] : [];
            if(isset($invoice_calculate["data"]) && $invoice_calculate["data"]) $invoice = array_replace_recursive($invoice,$invoice_calculate["data"]);

            $discount_total = $invoice_calculate["total_discount_amount"] ?? 0;

            $variables = [
                'invoice_id'                => $invoice["id"],
                'invoice_num'               => $invoice["number"] ? $invoice["number"] : "#".$invoice["id"],
                'invoice_payment_link'      => Controllers::$init->CRLink("share-invoice",false,$udata["lang"])."?token=".$token,
                'invoice_items'             => self::invoice_items($invoice,$items,$discounts,"text"),
                'invoice_items_html'        => self::invoice_items($invoice,$items,$discounts,"html"),
                'invoice_subtotal'          => Money::formatter_symbol($invoice["subtotal"],$invoice["currency"]),
                'invoice_total'             => Money::formatter_symbol($invoice["total"],$invoice["currency"]),
                'invoice_discount_total'    => Money::formatter_symbol($discount_total,$invoice["currency"]),
                'invoice_tax_rate'          => str_replace(".00","",$invoice["taxrate"]),
                'invoice_tax'               => Money::formatter_symbol($invoice["tax"],$invoice["currency"]),
                'invoice_date_created'      => DateManager::format(Config::get("options/date-format"),$invoice["cdate"]),
                'invoice_date_due'          => DateManager::format(Config::get("options/date-format"),$invoice["duedate"]),
                'invoice_remaining_day'     => $remaining_day,
            ];

            $locall     = Config::get("general/local");

            $scm                        = Config::get("modules/card-storage-module");
            $submit_ntf                 = true;
            if($scm && $scm != "none")
            {
                $stmt       = Models::$init->db->select("id")->from("users_stored_cards");
                $stmt->where("user_id","=",$udata["id"],"&&");
                $stmt->where("module","=",$scm);
                if($stmt->build())
                {
                    foreach($items AS $it)
                    {
                        if($it["user_pid"])
                        {
                            $o_check    = Models::$init->db->select("id")->from("users_products");
                            $o_check->where("id","=",$it["user_pid"],"&&");
                            $o_check->where("auto_pay","=",1);
                            if($o_check->build())
                            {
                                $submit_ntf = false;
                            }
                        }
                    }
                }
            }


            foreach($items AS $it)
            {
                if(isset($it["options"]["event"]) && $it["options"]["event"] == "ExtendAddonPeriod")
                {
                    $o_check    = Models::$init->db->select("id,subscription_id")->from("users_products_addons");
                    $o_check->where("id","=",$it["options"]["event_data"]["addon_id"],"&&");
                    $o_check->where("subscription_id","!=",0);
                    if($o_check->build())
                    {
                        $o_check        = $o_check->getObject();
                        $subscription   = Orders::get_subscription($o_check->subscription_id);
                        if($subscription && $subscription["status"] != "cancelled") $submit_ntf = false;
                    }
                }
                elseif($it["user_pid"])
                {
                    $o_check    = Models::$init->db->select("id,subscription_id")->from("users_products");
                    $o_check->where("id","=",$it["user_pid"],"&&");
                    $o_check->where("subscription_id","!=",0);
                    if($o_check->build())
                    {
                        $o_check        = $o_check->getObject();
                        $subscription   = Orders::get_subscription($o_check->subscription_id);
                        if($subscription && $subscription["status"] != "cancelled") $submit_ntf = false;
                    }
                }
            }

            if($settings["send-pdf"] ?? 1)
            {
                $pdf_creator = Invoices::create_pdf($invoice);
                if($pdf_creator)
                {
                    $pdf_name = 'invoice-'.str_replace("#","",$invoice["number"]).'.pdf';
                    $hook     = Hook::run("SetInvoiceMailPdfName",$invoice);
                    if($hook && is_array($hook)) foreach($hook AS $h) $pdf_name = $h;
                }
                else
                    LogManager::core_error_log(500,Invoices::$message,__FILE__,__LINE__);
            }
            else
                $pdf_creator = false;

            if($submit_ntf && $mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "invoice/invoice-reminder",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"invoice-reminder",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($submit_ntf && $mail && $settings["user-mail"]){
                $send   = $mail->body(null, "invoice/invoice-reminder",$variables,$udata["lang"],$udata["id"]);
                if($pdf_creator) $send->addAttachment($pdf_creator["path"],$pdf_name);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"invoice-reminder",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($submit_ntf && $sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "invoice/invoice-reminder", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"invoice-reminder",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($submit_ntf && $sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "invoice/invoice-reminder", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"invoice-reminder",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "invoice",
                'owner_id' => $invoice["id"],
                'name' => "invoice-reminder",
                'data' => [
                    'remaining_day' => $variables["invoice_remaining_day"],
                ],
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "invoice",
                'name'          => "invoice-reminder",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function invoice_overdue($invoice=0){

            Helper::Load(["Invoices","User","Money"]);
            if(!is_array($invoice)) $invoice    = Invoices::get($invoice);

            $settings       = Config::get("notifications/invoice/invoice-overdue");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($invoice["user_id"],"id,email,lang,country,full_name","array");

            if(!$udata) return 'OK';
            
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $token      = Crypt::encode(Utility::jencode([
                'user_id' => $invoice["user_id"],
                'id'      => $invoice["id"],
            ]),Config::get("crypt/user"));

            $items      = Invoices::item_listing($invoice);

            $invoice_calculate  = self::invoice_calculate($invoice);
            $discounts          = isset($invoice_calculate["discounts"]) ? $invoice_calculate["discounts"] : [];
            if(isset($invoice_calculate["data"]) && $invoice_calculate["data"]) $invoice = array_replace_recursive($invoice,$invoice_calculate["data"]);

            $discount_total = $invoice_calculate["total_discount_amount"] ?? 0;

            $variables = [
                'invoice_id'                => $invoice["id"],
                'invoice_num'               => $invoice["number"] ? $invoice["number"] : "#".$invoice["id"],
                'invoice_payment_link'      => Controllers::$init->CRLink("share-invoice",false,$udata["lang"])."?token=".$token,
                'invoice_items'             => self::invoice_items($invoice,$items,$discounts,"text"),
                'invoice_items_html'        => self::invoice_items($invoice,$items,$discounts,"html"),
                'invoice_subtotal'          => Money::formatter_symbol($invoice["subtotal"],$invoice["currency"]),
                'invoice_total'             => Money::formatter_symbol($invoice["total"],$invoice["currency"]),
                'invoice_discount_total'    => Money::formatter_symbol($discount_total,$invoice["currency"]),
                'invoice_tax_rate'          => str_replace(".00","",$invoice["taxrate"]),
                'invoice_tax'               => Money::formatter_symbol($invoice["tax"],$invoice["currency"]),
                'invoice_date_created'      => DateManager::format(Config::get("options/date-format"),$invoice["cdate"]),
                'invoice_date_due'          => DateManager::format(Config::get("options/date-format"),$invoice["duedate"]),
                'invoice_delayed_day'       => DateManager::diff_day(DateManager::Now(),$invoice["duedate"]),
            ];



            $locall     = Config::get("general/local");

            if($settings["send-pdf"] ?? 1)
            {
                $pdf_creator = Invoices::create_pdf($invoice);
                if($pdf_creator)
                {
                    $pdf_name = 'invoice-'.str_replace("#","",$invoice["number"]).'.pdf';
                    $hook     = Hook::run("SetInvoiceMailPdfName",$invoice);
                    if($hook && is_array($hook)) foreach($hook AS $h) $pdf_name = $h;
                }
                else
                    LogManager::core_error_log(500,Invoices::$message,__FILE__,__LINE__);
            }
            else
                $pdf_creator = false;

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "invoice/invoice-overdue",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"invoice-overdue",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "invoice/invoice-overdue",$variables,$udata["lang"],$udata["id"]);
                if($pdf_creator) $send->addAttachment($pdf_creator["path"],$pdf_name);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"invoice-overdue",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "invoice/invoice-overdue", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"invoice-overdue",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "invoice/invoice-overdue", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"invoice-overdue",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "invoice",
                'owner_id' => $invoice["id"],
                'name' => "invoice-overdue",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "invoice",
                'name'          => "invoice-overdue",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function invoice_has_been_approved($invoice=0){

            Helper::Load(["Invoices","User","Money"]);
            if(!is_array($invoice)) $invoice    = Invoices::get($invoice);

            $settings       = Config::get("notifications/invoice/invoice-has-been-approved");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($invoice["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $token      = Crypt::encode(Utility::jencode([
                'user_id' => $invoice["user_id"],
                'id'      => $invoice["id"],
            ]),Config::get("crypt/user"));

            $items      = Invoices::item_listing($invoice);

            $invoice_calculate  = self::invoice_calculate($invoice);
            $discounts          = isset($invoice_calculate["discounts"]) ? $invoice_calculate["discounts"] : [];
            if(isset($invoice_calculate["data"]) && $invoice_calculate["data"]) $invoice = array_replace_recursive($invoice,$invoice_calculate["data"]);

            $payment_method = false;
            if($invoice["pmethod"] != "none"){
                Modules::$lang = $udata["lang"];
                $method = Modules::Load("Payment",$invoice["pmethod"],true);
                if($method && isset($method["lang"]["option-name"])) $payment_method = $method["lang"]["option-name"];
            }
            if(!$payment_method) $payment_method = Bootstrap::$lang->get("needs/none",$udata["lang"]);


            if(substr($invoice["datepaid"],0,4) == "1881") $invoice["datepaid"] = DateManager::Now();

            $discount_total = $invoice_calculate["total_discount_amount"] ?? 0;

            $variables = [
                'invoice_id'                => $invoice["id"],
                'invoice_num'               => $invoice["number"] ? $invoice["number"] : "#".$invoice["id"],
                'invoice_payment_link'      => Controllers::$init->CRLink("share-invoice",false,$udata["lang"])."?token=".$token,
                'invoice_items'             => self::invoice_items($invoice,$items,$discounts,"text"),
                'invoice_items_html'        => self::invoice_items($invoice,$items,$discounts,"html"),
                'invoice_subtotal'          => Money::formatter_symbol($invoice["subtotal"],$invoice["currency"]),
                'invoice_total'             => Money::formatter_symbol($invoice["total"],$invoice["currency"]),
                'invoice_discount_total'    => Money::formatter_symbol($discount_total,$invoice["currency"]),
                'invoice_tax_rate'          => str_replace(".00","",$invoice["taxrate"]),
                'invoice_tax'               => Money::formatter_symbol($invoice["tax"],$invoice["currency"]),
                'invoice_date_created'      => DateManager::format(Config::get("options/date-format"),$invoice["cdate"]),
                'invoice_date_due'          => DateManager::format(Config::get("options/date-format"),$invoice["duedate"]),
                'invoice_date_paid'         => DateManager::format(Config::get("options/date-format")." - H:i",$invoice["datepaid"]),
                'invoice_payment_method'    => $payment_method,
            ];

            $locall     = Config::get("general/local");

            if($settings["send-pdf"] ?? 1)
            {
                $pdf_creator = Invoices::create_pdf($invoice);
                if($pdf_creator)
                {
                    $pdf_name = 'invoice-'.str_replace("#","",$invoice["number"]).'.pdf';
                    $hook     = Hook::run("SetInvoiceMailPdfName",$invoice);
                    if($hook && is_array($hook)) foreach($hook AS $h) $pdf_name = $h;
                }
                else
                    LogManager::core_error_log(500,Invoices::$message,__FILE__,__LINE__);
            }
            else
                $pdf_creator = false;

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "invoice/invoice-has-been-approved",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"invoice-has-been-approved",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "invoice/invoice-has-been-approved",$variables,$udata["lang"],$udata["id"]);
                if($pdf_creator) $send->addAttachment($pdf_creator["path"],$pdf_name);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"invoice-has-been-approved",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "invoice/invoice-has-been-approved", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"invoice-has-been-approved",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "invoice/invoice-has-been-approved", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"invoice-has-been-approved",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "invoice",
                'owner_id' => $invoice["id"],
                'name' => "invoice-has-been-approved",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "invoice",
                'name'          => "invoice-has-been-approved",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function invoice_has_been_taxed($invoice=0){

            Helper::Load(["Invoices","User","Money"]);
            if(!is_array($invoice)) $invoice    = Invoices::get($invoice);

            $settings       = Config::get("notifications/invoice/invoice-has-been-taxed");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($invoice["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $token      = Crypt::encode(Utility::jencode([
                'user_id' => $invoice["user_id"],
                'id'      => $invoice["id"],
            ]),Config::get("crypt/user"));

            $items      = Invoices::item_listing($invoice);

            $invoice_calculate  = self::invoice_calculate($invoice);
            $discounts          = isset($invoice_calculate["discounts"]) ? $invoice_calculate["discounts"] : [];
            if(isset($invoice_calculate["data"]) && $invoice_calculate["data"]) $invoice = array_replace_recursive($invoice,$invoice_calculate["data"]);

            $payment_method = false;
            if($invoice["pmethod"] != "none"){
                Modules::$lang = $udata["lang"];
                $method = Modules::Load("Payment",$invoice["pmethod"],true);
                if($method && isset($method["lang"]["option-name"])) $payment_method = $method["lang"]["option-name"];
            }
            if(!$payment_method) $payment_method = Bootstrap::$lang->get("needs/none",$udata["lang"]);


            if(substr($invoice["datepaid"],0,4) == "1881") $invoice["datepaid"] = DateManager::Now();

            $discount_total = $invoice_calculate["total_discount_amount"] ?? 0;

            $variables = [
                'invoice_id'                => $invoice["id"],
                'invoice_num'               => $invoice["number"] ? $invoice["number"] : "#".$invoice["id"],
                'invoice_payment_link'      => Controllers::$init->CRLink("share-invoice",false,$udata["lang"])."?token=".$token,
                'invoice_items'             => self::invoice_items($invoice,$items,$discounts,"text"),
                'invoice_items_html'        => self::invoice_items($invoice,$items,$discounts,"html"),
                'invoice_subtotal'          => Money::formatter_symbol($invoice["subtotal"],$invoice["currency"]),
                'invoice_total'             => Money::formatter_symbol($invoice["total"],$invoice["currency"]),
                'invoice_discount_total'    => Money::formatter_symbol($discount_total,$invoice["currency"]),
                'invoice_tax_rate'          => str_replace(".00","",$invoice["taxrate"]),
                'invoice_tax'               => Money::formatter_symbol($invoice["tax"],$invoice["currency"]),
                'invoice_date_created'      => DateManager::format(Config::get("options/date-format"),$invoice["cdate"]),
                'invoice_date_due'          => DateManager::format(Config::get("options/date-format"),$invoice["duedate"]),
                'invoice_date_paid'         => DateManager::format(Config::get("options/date-format")." - H:i",$invoice["datepaid"]),
                'invoice_payment_method'    => $payment_method,
                'invoice_date_taxed'        => DateManager::Now(Config::get("options/date-format")." - H:i"),
                'legal_invoice_download_link' => Controllers::$init->CRLink("download-id",["invoice-file",$invoice["id"]],$udata["lang"]),
            ];

            $taxed_file = $invoice["taxed_file"] ? Utility::jdecode($invoice["taxed_file"],true) : [];

            if(!$taxed_file) return false;

            $file       = ROOT_DIR.RESOURCE_DIR."uploads".DS."invoices".DS.$taxed_file["file_path"];


            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "invoice/invoice-has-been-taxed",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->addAttachment($file,$invoice["id"].".pdf")->submit();
                    if($send) LogManager::Mail_Log(0,"invoice-has-been-taxed",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "invoice/invoice-has-been-taxed",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->addAttachment($file,$invoice["id"].".pdf")->submit();
                if($send) LogManager::Mail_Log($udata["id"],"invoice-has-been-taxed",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "invoice/invoice-has-been-taxed", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"invoice-has-been-taxed",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "invoice/invoice-has-been-taxed", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"invoice-has-been-taxed",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "invoice",
                'owner_id' => $invoice["id"],
                'name' => "invoice-has-been-approved",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "invoice",
                'name'          => "invoice-has-been-taxed",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function invoice_awaiting_confirmation($invoice=0){

            Helper::Load(["Invoices","User","Money"]);
            if(!is_array($invoice)) $invoice    = Invoices::get($invoice);

            $settings       = Config::get("notifications/invoice/invoice-awaiting-confirmation");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($invoice["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $token      = Crypt::encode(Utility::jencode([
                'user_id' => $invoice["user_id"],
                'id'      => $invoice["id"],
            ]),Config::get("crypt/user"));

            $items      = Invoices::item_listing($invoice);

            $invoice_calculate  = self::invoice_calculate($invoice);
            $discounts          = isset($invoice_calculate["discounts"]) ? $invoice_calculate["discounts"] : [];
            if(isset($invoice_calculate["data"]) && $invoice_calculate["data"]) $invoice = array_replace_recursive($invoice,$invoice_calculate["data"]);

            $payment_method = false;
            if($invoice["pmethod"] != "none"){
                Modules::$lang = $udata["lang"];
                $method = Modules::Load("Payment",$invoice["pmethod"],true);
                if($method && isset($method["lang"]["option-name"])) $payment_method = $method["lang"]["option-name"];
            }
            if(!$payment_method) $payment_method = Bootstrap::$lang->get("needs/none",$udata["lang"]);

            if(substr($invoice["datepaid"],0,4) == "1881") $invoice["datepaid"] = DateManager::Now();

            $discount_total = $invoice_calculate["total_discount_amount"] ?? 0;


            $variables = [
                'invoice_id'                => $invoice["id"],
                'invoice_num'               => $invoice["number"] ? $invoice["number"] : "#".$invoice["id"],
                'invoice_payment_link'      => Controllers::$init->CRLink("share-invoice",false,$udata["lang"])."?token=".$token,
                'invoice_items'             => self::invoice_items($invoice,$items,$discounts,"text"),
                'invoice_items_html'        => self::invoice_items($invoice,$items,$discounts,"html"),
                'invoice_subtotal'          => Money::formatter_symbol($invoice["subtotal"],$invoice["currency"]),
                'invoice_total'             => Money::formatter_symbol($invoice["total"],$invoice["currency"]),
                'invoice_discount_total'    => Money::formatter_symbol($discount_total,$invoice["currency"]),
                'invoice_tax_rate'          => str_replace(".00","",$invoice["taxrate"]),
                'invoice_tax'               => Money::formatter_symbol($invoice["tax"],$invoice["currency"]),
                'invoice_date_created'      => DateManager::format(Config::get("options/date-format"),$invoice["cdate"]),
                'invoice_date_due'          => DateManager::format(Config::get("options/date-format"),$invoice["duedate"]),
                'invoice_date_paid'         => DateManager::format(Config::get("options/date-format")." - H:i",$invoice["datepaid"]),
                'invoice_payment_method'    => $payment_method,
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "invoice/invoice-awaiting-confirmation",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"invoice-awaiting-confirmation",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "invoice/invoice-awaiting-confirmation",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"invoice-awaiting-confirmation",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "invoice/invoice-awaiting-confirmation", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"invoice-awaiting-confirmation",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "invoice/invoice-awaiting-confirmation", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"invoice-awaiting-confirmation",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "invoice",
                'owner_id' => $invoice["id"],
                'name' => "invoice-awaiting-confirmation",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "invoice",
                'name'          => "invoice-awaiting-confirmation",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function invoice_auto_payment_failed($invoice=0,$error_msg='',$ln4='****'){

            Helper::Load(["Invoices","User","Money"]);
            if(!is_array($invoice)) $invoice    = Invoices::get($invoice);

            $settings       = Config::get("notifications/invoice/invoice-auto-payment-failed");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($invoice["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $token      = Crypt::encode(Utility::jencode([
                'user_id' => $invoice["user_id"],
                'id'      => $invoice["id"],
            ]),Config::get("crypt/user"));

            $items      = Invoices::item_listing($invoice);

            $invoice_calculate  = self::invoice_calculate($invoice);
            $discounts          = isset($invoice_calculate["discounts"]) ? $invoice_calculate["discounts"] : [];
            if(isset($invoice_calculate["data"]) && $invoice_calculate["data"]) $invoice = array_replace_recursive($invoice,$invoice_calculate["data"]);

            $payment_method = false;

            $discount_total = $invoice_calculate["total_discount_amount"] ?? 0;

            $variables = [
                'invoice_id'                => $invoice["id"],
                'invoice_num'               => $invoice["number"] ? $invoice["number"] : "#".$invoice["id"],
                'invoice_payment_link'      => Controllers::$init->CRLink("share-invoice",false,$udata["lang"])."?token=".$token,
                'invoice_items'             => self::invoice_items($invoice,$items,$discounts,"text"),
                'invoice_items_html'        => self::invoice_items($invoice,$items,$discounts,"html"),
                'invoice_subtotal'          => Money::formatter_symbol($invoice["subtotal"],$invoice["currency"]),
                'invoice_total'             => Money::formatter_symbol($invoice["total"],$invoice["currency"]),
                'invoice_discount_total'    => Money::formatter_symbol($discount_total,$invoice["currency"]),
                'invoice_tax_rate'          => str_replace(".00","",$invoice["taxrate"]),
                'invoice_tax'               => Money::formatter_symbol($invoice["tax"],$invoice["currency"]),
                'invoice_date_created'      => DateManager::format(Config::get("options/date-format"),$invoice["cdate"]),
                'invoice_date_due'          => DateManager::format(Config::get("options/date-format"),$invoice["duedate"]),
                'invoice_date_paid'         => DateManager::format(Config::get("options/date-format")." - H:i",$invoice["datepaid"]),
                'invoice_payment_method'    => $payment_method,
                'error_message'             => $error_msg,
                'card_ln4'                  => $ln4,
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "invoice/invoice-auto-payment-failed",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"invoice-auto-payment-failed",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "invoice/invoice-auto-payment-failed",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"invoice-auto-payment-failed",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "invoice/invoice-auto-payment-failed", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"invoice-auto-payment-failed",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "invoice/invoice-auto-payment-failed", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"invoice-auto-payment-failed",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "invoice",
                'owner_id' => $invoice["id"],
                'name' => "invoice-auto-payment-failed",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "invoice",
                'name'          => "invoice-auto-payment-failed",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function invoice_subscription_payment_failed($sb=[],$invoices=[]){

            Helper::Load(["Invoices","User","Money"]);


            $settings       = Config::get("notifications/invoice/invoice-subscription-payment-failed");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($sb["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $mod                = Modules::Load("Payment",$sb["module"],true);
            $mod_l              = Modules::Lang("Payment",$sb["module"],$udata["lang"]);
            $payment_method     = $mod_l["invoice-name"];


            $variables = [
                'module'        => $payment_method,
                'total'         => Money::formatter_symbol($sb["next_payable_fee"],$sb["currency"]),
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "invoice/invoice-subscription-payment-failed",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"invoice-subscription-payment-failed",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "invoice/invoice-subscription-payment-failed",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"invoice-subscription-payment-failed",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "invoice/invoice-subscription-payment-failed", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"invoice-subscription-payment-failed",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "invoice/invoice-subscription-payment-failed", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"invoice-subscription-payment-failed",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "subscription",
                'owner_id' => $sb["id"],
                'name' => "invoice-subscription-payment-failed",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "invoice",
                'name'          => "invoice-subscription-payment-failed",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }

        static function order_has_been_taken($invoice=0){

            Helper::Load(["Invoices","User","Money"]);
            if(!is_array($invoice)) $invoice    = Invoices::get($invoice);

            $settings       = Config::get("notifications/order/order-has-been-taken");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata      = User::getData($invoice["user_id"],"id,email,lang,country,full_name","array");
            $udata      = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));


            $items      = Invoices::item_listing($invoice);

            $invoice_calculate  = self::invoice_calculate($invoice);
            $discounts          = isset($invoice_calculate["discounts"]) ? $invoice_calculate["discounts"] : [];
            if(isset($invoice_calculate["data"]) && $invoice_calculate["data"]) $invoice = array_replace_recursive($invoice,$invoice_calculate["data"]);

            $payment_method = false;
            if($invoice["pmethod"] != "none"){
                Modules::$lang = $udata["lang"];
                $method = Modules::Load("Payment",$invoice["pmethod"],true);
                if($method && isset($method["lang"]["option-name"])) $payment_method = $method["lang"]["option-name"];
            }
            if(!$payment_method) $payment_method = Bootstrap::$lang->get("needs/none",$udata["lang"]);

            $discount_total = $invoice_calculate["total_discount_amount"] ?? 0;

            $variables = [
                'order_items'             => self::invoice_items($invoice,$items,$discounts,"text"),
                'order_items_html'        => self::invoice_items($invoice,$items,$discounts,"html"),
                'order_subtotal'          => Money::formatter_symbol($invoice["subtotal"],$invoice["currency"]),
                'order_total'             => Money::formatter_symbol($invoice["total"],$invoice["currency"]),
                'order_discount_total'    => Money::formatter_symbol($discount_total,$invoice["currency"]),
                'invoice_discount_total'  => Money::formatter_symbol($discount_total,$invoice["currency"]),
                'order_tax_rate'          => str_replace(".00","",$invoice["taxrate"]),
                'order_tax'               => Money::formatter_symbol($invoice["tax"],$invoice["currency"]),
                'order_date_created'      => DateManager::format(Config::get("options/date-format"),$invoice["cdate"]),
                'invoice_payment_method'    => $payment_method,

            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "order/order-has-been-taken",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"order-has-been-taken",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "order/order-has-been-taken",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"order-has-been-taken",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "order/order-has-been-taken", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"order-has-been-taken",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "order/order-has-been-taken", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"order-has-been-taken",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "order",
                'name'          => "order-has-been-taken",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function order_hosting_server_activation($order=[]){
            Helper::Load(["Orders","User","Money","Products"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/order/hosting-server-activation");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
            if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
            else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);

            $detail_link                = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"]);

            $info_text                  = NULL;
            $info_html                  = NULL;
            $options                    = $order["options"];

            if($order["type"] == "hosting"){
                $ftp_raw                = isset($options["ftp_raw"]) ? $options["ftp_raw"] : false;
                if($order["module"] != "none" && isset($options["server_id"]) && $options["server_id"]){
                    $server             = Products::get_server($options["server_id"]);
                    $type               = $server["type"];
                    Modules::Load("Servers",$type);
                    $module_name        = $type."_Module";
                    if(class_exists($module_name)){
                        $module         = new $module_name($server);
                        if(method_exists($module,'set_order')) $module->set_order($order);
                        $info_text      = $module->activation_infos("text",$order,$udata["lang"]);
                        $info_html      = $module->activation_infos("html",$order,$udata["lang"]);
                    }
                }
                if(!$info_html && $ftp_raw){
                    $info_html          = nl2br($ftp_raw);
                    $info_text          = strip_tags(str_replace("<br>","\n",$ftp_raw),"<br>");
                }
            }
            elseif($order["type"] == "server"){
                if($order["module"] != "none" && isset($options["server_id"]) && $options["server_id"]){
                    $server             = Products::get_server($options["server_id"]);
                    $type               = $server["type"];
                    Modules::Load("Servers",$type);
                    $module_name        = $type."_Module";
                    if(class_exists($module_name)){
                        $module         = new $module_name($server);
                        if(method_exists($module,'set_order')) $module->set_order($order);
                        $info_text      = $module->activation_infos("text",$order,$udata["lang"]);
                        $info_html      = $module->activation_infos("html",$order,$udata["lang"]);
                    }
                }
                else{

                    if(isset($options["login"]["password"])){
                        $password       = $options["login"]["password"];
                        $password_d     = Crypt::decode($password,Config::get("crypt/user"));
                        if($password_d) $options["login"]["password"] = $password_d;
                    }

                    $t_data     = [
                        'options' => $options,
                        'udata'   => $udata,
                    ];
                    $info_html  = View::$init->chose("notifications")->render("server-activation-html",$t_data,true);
                    $info_text  = View::$init->chose("notifications")->render("server-activation-text",$t_data,true);
                }
            }

            $variables = [
                'order_name'            => self::order_name($order),
                'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                'order_date_created'    => $date_start,
                'order_date_start'      => $date_start,
                'order_date_end'        => $date_end,
                'order_date_approved'   => DateManager::Now(Config::get("options/date-format")." - H:i"),
                'order_detail_link'     => $detail_link,
                'order_infos_html'      => $info_html,
                'order_infos_text'      => $info_text,
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "order/hosting-server-activation",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"hosting-server-activation",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "order/hosting-server-activation",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"hosting-server-activation",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "order/hosting-server-activation", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"hosting-server-activation",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "order/hosting-server-activation", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"hosting-server-activation",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "order",
                'owner_id' => $order["id"],
                'name' => "order-activation-ready",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "order",
                'name'          => "hosting-server-activation",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function order_has_been_approved($order=[],$addon=[]){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            if(!$order) return false;

            $settings       = Config::get("notifications/order/order-has-been-approved");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"] ?? 0,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata ?: [],User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            if($addon){
                if(!is_array($addon)) $addon = Orders::get_addon($addon);
                $date_start                 = DateManager::format(Config::get("options/date-format"),$addon["cdate"]);
                if(substr($addon["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
                else $date_end              = DateManager::format(Config::get("options/date-format"),$addon["duedate"]);
            }else{
                $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
                if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
                else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);
            }

            $detail_link                = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"]);

            if($addon){
                $variables = [
                    'order_name'            => self::order_name($order)." - ".$addon["addon_name"]." - ".$addon["option_name"],
                    'order_amount'          => Money::formatter_symbol($addon["amount"],$addon["cid"]),
                    'order_date_created'    => $date_start,
                    'order_date_start'      => $date_start,
                    'order_date_end'        => $date_end,
                    'order_date_approved'   => DateManager::Now(Config::get("options/date-format")." - H:i"),
                    'order_detail_link'     => $detail_link,
                ];
            }else{
                $variables = [
                    'order_name'            => self::order_name($order),
                    'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                    'order_date_created'    => $date_start,
                    'order_date_start'      => $date_start,
                    'order_date_end'        => $date_end,
                    'order_date_approved'   => DateManager::Now(Config::get("options/date-format")." - H:i"),
                    'order_detail_link'     => $detail_link,
                ];
            }

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "order/order-has-been-approved",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"order-has-been-approved",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "order/order-has-been-approved",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"order-has-been-approved",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "order/order-has-been-approved", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"order-has-been-approved",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "order/order-has-been-approved", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"order-has-been-approved",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "order",
                'owner_id' => $order["id"],
                'name' => "order-has-been-approved",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "order",
                'name'          => "order-has-been-approved",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function order_has_been_activated($order=[],$addon=[])
        {
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/order/order-has-been-activated");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            if($addon){
                if(!is_array($addon)) $addon = Orders::get_addon($addon);
                $date_start                 = DateManager::format(Config::get("options/date-format"),$addon["cdate"]);
                if(substr($addon["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
                else $date_end              = DateManager::format(Config::get("options/date-format"),$addon["duedate"]);
            }else{
                $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
                if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
                else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);
            }

            $detail_link                = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"]);

            if($addon){
                $variables = [
                    'order_name'            => self::order_name($order)." - ".$addon["addon_name"]." - ".$addon["option_name"],
                    'order_amount'          => Money::formatter_symbol($addon["amount"],$addon["cid"]),
                    'order_date_created'    => $date_start,
                    'order_date_start'      => $date_start,
                    'order_date_end'        => $date_end,
                    'order_date_approved'   => DateManager::Now(Config::get("options/date-format")." - H:i"),
                    'order_detail_link'     => $detail_link,
                ];
            }else{
                $variables = [
                    'order_name'            => self::order_name($order),
                    'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                    'order_date_created'    => $date_start,
                    'order_date_start'      => $date_start,
                    'order_date_end'        => $date_end,
                    'order_date_approved'   => DateManager::Now(Config::get("options/date-format")." - H:i"),
                    'order_detail_link'     => $detail_link,
                ];
            }

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "order/order-has-been-activated",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"order-has-been-activated",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "order/order-has-been-activated",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"order-has-been-activated",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "order/order-has-been-activated", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"order-has-been-activated",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "order/order-has-been-activated", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"order-has-been-activated",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "order",
                'owner_id' => $order["id"],
                'name' => "order-has-been-activated",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "order",
                'name'          => "order-has-been-activated",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function order_has_been_extended($order=[],$addon=[]){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            if(!$order) return false;

            Hook::run("OrderRenewed",$order);

            $settings       = Config::get("notifications/order/order-has-been-extended");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = $udata ? array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"])) : [];

            if($addon){
                if(!is_array($addon)) $addon = Orders::get_addon($addon);
                $date_start                 = DateManager::format(Config::get("options/date-format"),$addon["cdate"]);
                if(substr($addon["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
                else $date_end              = DateManager::format(Config::get("options/date-format"),$addon["duedate"]);
            }else{
                $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
                if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
                else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);
            }

            $detail_link                = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"]);

            if($addon){
                $variables = [
                    'order_name'            => self::order_name($order)." - ".$addon["addon_name"]." - ".$addon["option_name"],
                    'order_amount'          => Money::formatter_symbol($addon["amount"],$addon["cid"]),
                    'order_date_created'    => $date_start,
                    'order_date_start'      => $date_start,
                    'order_date_end'        => $date_end,
                    'order_date_approved'   => DateManager::Now(Config::get("options/date-format")." - H:i"),
                    'order_detail_link'     => $detail_link,
                ];
            }else{
                $variables = [
                    'order_name'            => self::order_name($order),
                    'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                    'order_date_created'    => $date_start,
                    'order_date_start'      => $date_start,
                    'order_date_end'        => $date_end,
                    'order_date_approved'   => DateManager::Now(Config::get("options/date-format")." - H:i"),
                    'order_detail_link'     => $detail_link,
                ];
            }

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "order/order-has-been-extended",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"order-has-been-extended",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "order/order-has-been-extended",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"order-has-been-extended",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "order/order-has-been-extended", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"order-has-been-extended",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "order/order-has-been-extended", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"order-has-been-extended",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            if(!$addon){
                self::user_notification([
                    'user_id' => $udata["id"],
                    'type' => "notification",
                    'owner' => "order",
                    'owner_id' => $order["id"],
                    'name' => "order-has-been-extended",
                ]);
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "order",
                'name'          => "order-has-been-extended",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function order_has_been_suspended($order=[],$addon=[]){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/order/order-has-been-suspended");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            if($addon){
                if(!is_array($addon)) $addon = Orders::get_addon($addon);
                $date_start                 = DateManager::format(Config::get("options/date-format"),$addon["cdate"]);
                if(substr($addon["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
                else $date_end              = DateManager::format(Config::get("options/date-format"),$addon["duedate"]);
            }else{
                $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
                if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
                else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);
            }

            $detail_link                = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"]);

            if($addon){
                $variables = [
                    'order_name'            => self::order_name($order)." - ".$addon["addon_name"]." - ".$addon["option_name"],
                    'order_amount'          => Money::formatter_symbol($addon["amount"],$addon["cid"]),
                    'order_date_created'    => $date_start,
                    'order_date_start'      => $date_start,
                    'order_date_end'        => $date_end,
                    'order_date_approved'   => DateManager::Now(Config::get("options/date-format")." - H:i"),
                    'order_detail_link'     => $detail_link,
                    'reason'                => $addon["suspended_reason"],
                ];
            }else{
                $variables = [
                    'order_name'            => self::order_name($order),
                    'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                    'order_date_created'    => $date_start,
                    'order_date_start'      => $date_start,
                    'order_date_end'        => $date_end,
                    'order_date_approved'   => DateManager::Now(Config::get("options/date-format")." - H:i"),
                    'order_detail_link'     => $detail_link,
                    'reason'                => $order["suspended_reason"],
                ];
            }

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "order/order-has-been-suspended",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"order-has-been-suspended",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "order/order-has-been-suspended",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"order-has-been-suspended",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "order/order-has-been-suspended", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"order-has-been-suspended",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "order/order-has-been-suspended", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"order-has-been-suspended",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }


            if(!$addon){
                self::user_notification([
                    'user_id' => $udata["id"],
                    'type' => "notification",
                    'owner' => "order",
                    'owner_id' => $order["id"],
                    'name' => "order-has-been-suspended",
                ]);
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "order",
                'name'          => "order-has-been-suspended",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function order_has_been_cancelled($order=[],$addon=[]){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/order/order-has-been-cancelled");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            if($addon){
                if(!is_array($addon)) $addon = Orders::get_addon($addon);
                $date_start                 = DateManager::format(Config::get("options/date-format"),$addon["cdate"]);
                if(substr($addon["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
                else $date_end              = DateManager::format(Config::get("options/date-format"),$addon["duedate"]);
            }else{
                $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
                if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
                else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);
            }

            $detail_link                = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"]);

            if($addon){
                $variables = [
                    'order_name'            => self::order_name($order)." - ".$addon["addon_name"]." - ".$addon["option_name"],
                    'order_amount'          => Money::formatter_symbol($addon["amount"],$addon["cid"]),
                    'order_date_created'    => $date_start,
                    'order_date_start'      => $date_start,
                    'order_date_end'        => $date_end,
                    'order_date_approved'   => DateManager::Now(Config::get("options/date-format")." - H:i"),
                    'order_detail_link'     => $detail_link,
                ];
            }else{
                $variables = [
                    'order_name'            => self::order_name($order),
                    'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                    'order_date_created'    => $date_start,
                    'order_date_start'      => $date_start,
                    'order_date_end'        => $date_end,
                    'order_date_approved'   => DateManager::Now(Config::get("options/date-format")." - H:i"),
                    'order_detail_link'     => $detail_link,
                ];
            }

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "order/order-has-been-cancelled",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"order-has-been-cancelled",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "order/order-has-been-cancelled",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"order-has-been-cancelled",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "order/order-has-been-cancelled", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"order-has-been-cancelled",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "order/order-has-been-cancelled", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"order-has-been-cancelled",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            if(!$addon){
                self::user_notification([
                    'user_id' => $udata["id"],
                    'type' => "notification",
                    'owner' => "order",
                    'owner_id' => $order["id"],
                    'name' => "order-has-been-cancelled",
                ]);
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "order",
                'name'          => "order-has-been-cancelled",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }

        static function domain_registered($order=[]){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/domain/domain-registered");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
            if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
            else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);

            $detail_link                = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"]);

            $options                    = $order["options"];
            $domain                     = $options["domain"];

            $variables = [
                'order_name'            => self::order_name($order),
                'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                'order_date_created'    => $date_start,
                'order_date_start'      => $date_start,
                'order_date_end'        => $date_end,
                'order_detail_link'     => $detail_link,
                'domain'                => $domain,
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "domain/domain-registered",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"domain-registered",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "domain/domain-registered",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"domain-registered",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "domain/domain-registered", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"domain-registered",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "domain/domain-registered", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"domain-registered",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "order",
                'owner_id' => $order["id"],
                'name' => "domain-registered",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "domain",
                'name'          => "domain-registered",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function domain_transferred($order=[]){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/domain/domain-transfered");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
            if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
            else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);

            $detail_link                = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"]);

            $options                    = $order["options"];
            $domain                     = $options["domain"];

            $variables = [
                'order_name'            => self::order_name($order),
                'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                'order_date_created'    => $date_start,
                'order_date_start'      => $date_start,
                'order_date_end'        => $date_end,
                'order_detail_link'     => $detail_link,
                'domain'                => $domain,
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "domain/domain-transfered",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"domain-transfered",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "domain/domain-transfered",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"domain-transfered",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "domain/domain-transfered", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"domain-transfered",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "domain/domain-transfered", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"domain-transfered",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "order",
                'owner_id' => $order["id"],
                'name' => "domain-has-been-transferred",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "domain",
                'name'          => "domain-transfered",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function domain_has_been_activated($order=[]){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/domain/domain-has-been-activated");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
            if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
            else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);

            $detail_link                = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"]);

            $variables = [
                'order_name'            => self::order_name($order),
                'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                'order_date_created'    => $date_start,
                'order_date_start'      => $date_start,
                'order_date_end'        => $date_end,
                'order_detail_link'     => $detail_link,
                'domain'                => $order["options"]["domain"],
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "domain/domain-has-been-activated",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"domain-has-been-activated",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "domain/domain-has-been-activated",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"domain-has-been-activated",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "domain/domain-has-been-activated", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"domain-has-been-activated",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "domain/domain-has-been-activated", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"domain-has-been-activated",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "order",
                'owner_id' => $order["id"],
                'name' => "domain-has-been-activated",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "domain",
                'name'          => "domain-has-been-activated",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function domain_has_been_extended($order=[]){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);


            Hook::run("OrderRenewed",$order);

            $settings       = Config::get("notifications/domain/domain-has-been-extended");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
            if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
            else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);

            $detail_link                = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"]);

            $variables = [
                'order_name'            => self::order_name($order),
                'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                'order_date_created'    => $date_start,
                'order_date_start'      => $date_start,
                'order_date_end'        => $date_end,
                'order_detail_link'     => $detail_link,
                'domain'                => $order["options"]["domain"],
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "domain/domain-has-been-extended",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"domain-has-been-extended",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "domain/domain-has-been-extended",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"domain-has-been-extended",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "domain/domain-has-been-extended", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"domain-has-been-extended",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "domain/domain-has-been-extended", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"domain-has-been-extended",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "order",
                'owner_id' => $order["id"],
                'name' => "domain-has-been-extended",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "domain",
                'name'          => "domain-has-been-extended",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function domain_has_been_suspended($order=[]){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/domain/domain-has-been-suspended");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
            if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
            else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);

            $detail_link                = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"]);

            $variables = [
                'order_name'            => self::order_name($order),
                'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                'order_date_created'    => $date_start,
                'order_date_start'      => $date_start,
                'order_date_end'        => $date_end,
                'order_detail_link'     => $detail_link,
                'domain'                => $order["options"]["domain"],
                'reason'                => $order["suspended_reason"],
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "domain/domain-has-been-suspended",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"domain-has-been-suspended",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "domain/domain-has-been-suspended",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"domain-has-been-suspended",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "domain/domain-has-been-suspended", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"domain-has-been-suspended",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "domain/domain-has-been-suspended", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"domain-has-been-suspended",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "order",
                'owner_id' => $order["id"],
                'name' => "domain-has-been-suspended",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "domain",
                'name'          => "domain-has-been-suspended",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function domain_has_been_cancelled($order=[]){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/domain/domain-has-been-cancelled");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
            if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
            else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);

            $detail_link                = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"]);

            $variables = [
                'order_name'            => self::order_name($order),
                'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                'order_date_created'    => $date_start,
                'order_date_start'      => $date_start,
                'order_date_end'        => $date_end,
                'order_detail_link'     => $detail_link,
                'domain'                => $order["options"]["domain"],
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "domain/domain-has-been-cancelled",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"domain-has-been-cancelled",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "domain/domain-has-been-cancelled",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"domain-has-been-cancelled",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "domain/domain-has-been-cancelled", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"domain-has-been-cancelled",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "domain/domain-has-been-cancelled", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"domain-has-been-cancelled",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "order",
                'owner_id' => $order["id"],
                'name' => "domain-has-been-cancelled",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "domain",
                'name'          => "domain-has-been-cancelled",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function domain_submit_transfer_code($order=[],$code=''){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/domain/domain-submit-transfer-code");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
            if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
            else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);

            $detail_link                = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"]);

            $variables = [
                'order_name'            => self::order_name($order),
                'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                'order_date_created'    => $date_start,
                'order_date_start'      => $date_start,
                'order_date_end'        => $date_end,
                'order_detail_link'     => $detail_link,
                'domain'                => $order["options"]["domain"],
                'domain_transfer_code'  => $code,
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "domain/domain-submit-transfer-code",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"domain-submit-transfer-code",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "domain/domain-submit-transfer-code",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"domain-submit-transfer-code",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()),false,1);
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "domain/domain-submit-transfer-code", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"domain-submit-transfer-code",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "domain/domain-submit-transfer-code", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"domain-submit-transfer-code",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()),false,1);
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "domain",
                'name'          => "domain-submit-transfer-code",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function domain_requires_doc($order=[]){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/domain/domain-requires-doc");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
            if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
            else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);

            $detail_link                = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"]);

            $options                    = $order["options"];
            $domain                     = $options["domain"];

            $variables = [
                'order_name'            => self::order_name($order),
                'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                'order_date_created'    => $date_start,
                'order_date_start'      => $date_start,
                'order_date_end'        => $date_end,
                'order_detail_link'     => $detail_link,
                'domain'                => $domain,
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "domain/domain-requires-doc",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"domain-requires-doc",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "domain/domain-requires-doc",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"domain-requires-doc",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "domain/domain-requires-doc", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"domain-requires-doc",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "domain/domain-requires-doc", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"domain-requires-doc",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            /*
            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "order",
                'owner_id' => $order["id"],
                'name' => "domain-requires-doc",
            ]);
            */

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "domain",
                'name'          => "domain-requires-doc",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }

        static function sms_origin_request_received($order=[],$origin=''){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/sms/sms-origin-request-received");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);

            $variables = [
                'order_name'            => self::order_name($order),
                'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                'order_date_created'    => $date_start,
                'origin_detail_link'    => NULL,
                'origin_name'           => $origin,
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $variables["origin_detail_link"] = Controllers::$init->AdminCRLink("orders-2",["detail",$order["id"]],$lang)."?content=origins";
                    $send   = $mail->body(null, "sms/sms-origin-request-received",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"sms-origin-request-received",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $variables["origin_detail_link"] = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"])."?tab=3";
                $send   = $mail->body(null, "sms/sms-origin-request-received",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"sms-origin-request-received",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $variables["origin_detail_link"] = Controllers::$init->AdminCRLink("orders-2",["detail",$order["id"]],$lang)."?content=origins";
                    $send   = $sms->body(null, "sms/sms-origin-request-received", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"sms-origin-request-received",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $variables["origin_detail_link"] = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"])."?tab=3";
                $send   = $sms->body(null, "sms/sms-origin-request-received", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"sms-origin-request-received",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }


            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "sms",
                'name'          => "sms-origin-request-received",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);


            return $errors ? $errors : "OK";
        }
        static function sms_origin_has_been_approved($order=[],$origin=''){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/sms/sms-origin-has-been-approved");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);

            $variables = [
                'order_name'            => self::order_name($order),
                'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                'order_date_created'    => $date_start,
                'origin_detail_link'    => NULL,
                'origin_name'           => $origin,
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $variables["origin_detail_link"] = Controllers::$init->AdminCRLink("orders-2",["detail",$order["id"]],$lang)."?content=origins";
                    $send   = $mail->body(null, "sms/sms-origin-has-been-approved",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"sms-origin-has-been-approved",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $variables["origin_detail_link"] = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"])."?tab=3";
                $send   = $mail->body(null, "sms/sms-origin-has-been-approved",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"sms-origin-has-been-approved",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $variables["origin_detail_link"] = Controllers::$init->AdminCRLink("orders-2",["detail",$order["id"]],$lang)."?content=origins";
                    $send   = $sms->body(null, "sms/sms-origin-has-been-approved", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"sms-origin-has-been-approved",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $variables["origin_detail_link"] = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"])."?tab=3";
                $send   = $sms->body(null, "sms/sms-origin-has-been-approved", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"sms-origin-has-been-approved",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "order",
                'owner_id' => $order["id"],
                'name' => "sms-origin-has-been-approved",
                'data' => [
                    'origin_name' => $variables["origin_name"],
                ],
            ]);

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "sms",
                'name'          => "sms-origin-has-been-approved",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function sms_origin_has_been_inactivated($order=[],$origin='',$reason=''){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/sms/sms-origin-has-been-inactivated");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);

            $variables = [
                'order_name'            => self::order_name($order),
                'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                'order_date_created'    => $date_start,
                'origin_detail_link'    => NULL,
                'origin_name'           => $origin,
                'reason'                => $reason,
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $variables["origin_detail_link"] = Controllers::$init->AdminCRLink("orders-2",["detail",$order["id"]],$lang)."?content=origins";
                    if(!$variables["reason"]) $variables["reason"] = ___("needs/none",false,$lang);
                    $send   = $mail->body(null, "sms/sms-origin-has-been-inactivated",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"sms-origin-has-been-inactivated",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $variables["origin_detail_link"] = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"])."?tab=3";
                if(!$variables["reason"]) $variables["reason"] = ___("needs/none",false,$udata["lang"]);
                $send   = $mail->body(null, "sms/sms-origin-has-been-inactivated",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"sms-origin-has-been-inactivated",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $variables["origin_detail_link"] = Controllers::$init->AdminCRLink("orders-2",["detail",$order["id"]],$lang)."?content=origins";
                    if(!$variables["reason"]) $variables["reason"] = ___("needs/none",false,$lang);
                    $send   = $sms->body(null, "sms/sms-origin-has-been-inactivated", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"sms-origin-has-been-inactivated",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $variables["origin_detail_link"] = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"])."?tab=3";
                if(!$variables["reason"]) $variables["reason"] = ___("needs/none",false,$udata["lang"]);
                $send   = $sms->body(null, "sms/sms-origin-has-been-inactivated", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"sms-origin-has-been-inactivated",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "order",
                'owner_id' => $order["id"],
                'name' => "sms-origin-has-been-inactivated",
                'data' => [
                    'origin_name' => $variables["origin_name"],
                ],
            ]);

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "sms",
                'name'          => "sms-origin-has-been-inactivated",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function sms_intl_origin_request_received($user_id,$origin='',$cc=''){
            Helper::Load(["User"]);

            $settings       = Config::get("notifications/sms-intl/sms-intl-origin-request-received");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($user_id,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $variables = [
                'origin_detail_link'    => NULL,
                'origin_name'           => $origin,
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $variables["origin_detail_link"] = Controllers::$init->AdminCRLink("products-2",["international-sms","origins"],$lang);
                    $variables["country"] = AddressManager::get_country_name($cc,$lang);
                    $send   = $mail->body(null, "sms-intl/sms-intl-origin-request-received",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"sms-intl-origin-request-received",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $variables["origin_detail_link"] = Controllers::$init->CRLink("ac-ps-sms",false,$udata["lang"])."?tab=origins";
                $variables["country"] = AddressManager::get_country_name($cc,$udata["lang"]);
                $send   = $mail->body(null, "sms-intl/sms-intl-origin-request-received",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"sms-intl-origin-request-received",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $variables["origin_detail_link"] = Controllers::$init->AdminCRLink("products-2",["international-sms","origins"],$lang);
                    $variables["country"] = AddressManager::get_country_name($cc,$lang);
                    $send   = $sms->body(null, "sms-intl/sms-intl-origin-request-received", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"sms-intl-origin-request-received",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $variables["origin_detail_link"] = Controllers::$init->CRLink("ac-ps-sms",false,$udata["lang"])."?tab=origins";
                $variables["country"] = AddressManager::get_country_name($cc,$udata["lang"]);
                $send   = $sms->body(null, "sms-intl/sms-intl-origin-request-received", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"sms-intl-origin-request-received",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "sms-intl",
                'name'          => "sms-intl-origin-request-received",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function sms_intl_origin_has_been_approved($user_id,$origin='',$cc=''){
            Helper::Load(["User"]);

            $settings       = Config::get("notifications/sms-intl/sms-intl-origin-has-been-approved");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($user_id,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $variables = [
                'origin_detail_link'    => NULL,
                'origin_name'           => $origin,
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $variables["origin_detail_link"] = Controllers::$init->AdminCRLink("products-2",["international-sms","origins"],$lang);
                    $variables["country"] = AddressManager::get_country_name($cc,$lang);
                    $send   = $mail->body(null, "sms-intl/sms-intl-origin-has-been-approved",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"sms-intl-origin-has-been-approved",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $variables["origin_detail_link"] = Controllers::$init->CRLink("ac-ps-sms",false,$udata["lang"])."?tab=origins";
                $variables["country"] = AddressManager::get_country_name($cc,$udata["lang"]);
                $send   = $mail->body(null, "sms-intl/sms-intl-origin-has-been-approved",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"sms-intl-origin-has-been-approved",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $variables["origin_detail_link"] = Controllers::$init->AdminCRLink("products-2",["international-sms","origins"],$lang);
                    $variables["country"] = AddressManager::get_country_name($cc,$lang);
                    $send   = $sms->body(null, "sms-intl/sms-intl-origin-has-been-approved", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"sms-intl-origin-has-been-approved",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $variables["origin_detail_link"] = Controllers::$init->CRLink("ac-ps-sms",false,$udata["lang"])."?tab=origins";
                $variables["country"] = AddressManager::get_country_name($cc,$udata["lang"]);
                $send   = $sms->body(null, "sms-intl/sms-intl-origin-has-been-approved", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"sms-intl-origin-has-been-approved",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "intl-sms",
                'owner_id' => 0,
                'name' => "intl-sms-origin-has-been-approved",
                'data' => [
                    'origin_name' => $variables["origin_name"],
                    'country' => $variables["country"],
                ],
            ]);

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "sms-intl",
                'name'          => "sms-intl-origin-has-been-approved",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function sms_intl_origin_has_been_inactivated($user_id,$origin='',$reason='',$cc=''){
            Helper::Load(["User"]);

            $settings       = Config::get("notifications/sms-intl/sms-intl-origin-has-been-inactivated");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($user_id,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $variables = [
                'origin_detail_link'    => NULL,
                'origin_name'           => $origin,
                'reason'                => $reason,
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $variables["origin_detail_link"] = Controllers::$init->AdminCRLink("products-2",["international-sms","origins"],$lang);
                    $variables["country"] = AddressManager::get_country_name($cc,$lang);
                    if(!$variables["reason"]) $variables["reason"] = ___("needs/none",false,$lang);
                    $send   = $mail->body(null, "sms-intl/sms-intl-origin-has-been-inactivated",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"sms-intl-origin-has-been-inactivated",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $variables["origin_detail_link"] = Controllers::$init->CRLink("ac-ps-sms",false,$udata["lang"])."?tab=origins";
                $variables["country"] = AddressManager::get_country_name($cc,$udata["lang"]);
                if(!$variables["reason"]) $variables["reason"] = ___("needs/none",false,$udata["lang"]);
                $send   = $mail->body(null, "sms-intl/sms-intl-origin-has-been-inactivated",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"sms-intl-origin-has-been-inactivated",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $variables["origin_detail_link"] = Controllers::$init->AdminCRLink("products-2",["international-sms","origins"],$lang);
                    $variables["country"] = AddressManager::get_country_name($cc,$lang);
                    if(!$variables["reason"]) $variables["reason"] = ___("needs/none",false,$lang);
                    $send   = $sms->body(null, "sms-intl/sms-intl-origin-has-been-inactivated", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"sms-intl-origin-has-been-inactivated",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $variables["origin_detail_link"] = Controllers::$init->CRLink("ac-ps-sms",false,$udata["lang"])."?tab=origins";
                $variables["country"] = AddressManager::get_country_name($cc,$udata["lang"]);
                if(!$variables["reason"]) $variables["reason"] = ___("needs/none",false,$udata["lang"]);
                $send   = $sms->body(null, "sms-intl/sms-intl-origin-has-been-inactivated", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"sms-intl-origin-has-been-inactivated",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "intl-sms",
                'owner_id' => 0,
                'name' => "intl-sms-origin-has-been-inactivated",
                'data' => [
                    'origin_name' => $variables["origin_name"],
                    'country' => $variables["country"],
                ],
            ]);

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "sms-intl",
                'name'          => "sms-intl-origin-has-been-inactivated",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }

        static function ticket_resolved_automatic($ticket=[]){
            Helper::Load(["Orders","User","Money","Tickets"]);
            if(!is_array($ticket)) $ticket = Tickets::get_request($ticket);

            $settings       = Config::get("notifications/user-tickets/ticket-resolved-automatic");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($ticket["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];

            $ticket_id          = $ticket["id"];
            $detail_link        = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id]);
            $user_last_reply    = Tickets::last_reply($ticket_id,"user_id,message");
            $admin_last_reply   = Tickets::last_reply($ticket_id,"user_id,message",true);
            $user_last_msg      = $user_last_reply ? $user_last_reply["message"] : NULL;
            if(!Validation::isHTML($user_last_msg)) $user_last_msg = nl2br($user_last_msg);
            $admin_last_msg     = $admin_last_reply ? $admin_last_reply["message"] : NULL;
            $department         = Tickets::get_department($ticket["did"],$ulang,"t2.name");
            $department         = $department ? $department["name"] : Bootstrap::$lang->get("needs/other",$ulang);
            $order              = $ticket["service"] ? Orders::get($ticket["service"]) : false;
            $service            = $order ? self::order_name($order) : Bootstrap::$lang->get("needs/none",$ulang);
            $status             = Bootstrap::$lang->get_cm("website/account_tickets/status-".$ticket["status"],false,$ulang);
            $priorities         = [
                1               => Bootstrap::$lang->get_cm("website/account_tickets/priority-low",false,$ulang),
                2               => Bootstrap::$lang->get_cm("website/account_tickets/priority-middle",false,$ulang),
                3               => Bootstrap::$lang->get_cm("website/account_tickets/priority-high",false,$ulang),
            ];
            $priority           = $priorities[$ticket["priority"]];
            $admin_name         = NULL;

            if($ticket["assigned"]){
                $assigned       = User::getData($ticket["assigned"],"id,full_name","array");
                $admin_name     = $assigned["full_name"];
            }elseif($admin_last_reply){
                $fetchAdmin     = User::getData($admin_last_reply["user_id"],"id,full_name","array");
                $admin_name     = $fetchAdmin["full_name"];
            }


            $cstatus            = $ticket["cstatus"] ? Tickets::custom_status($ticket["cstatus"]) : [];
            if($cstatus) if(isset($cstatus["languages"][$ulang])) $status = $cstatus["languages"][$ulang]["name"];


            $variables = [
                'ticket_id' => $ticket_id,
                'ticket_link' => $detail_link,
                'ticket_subject' => $ticket["title"],
                'user_last_message' => $user_last_msg,
                'admin_last_message' => $admin_last_msg,
                'ticket_department' => $department,
                'ticket_service'    => $service,
                'ticket_status'     => $status,
                'ticket_priority'   => $priority,
                'ticket_admin_name' => $admin_name,
            ];



            $svariables = $variables;
            $svariables["admin_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["admin_last_message"],"<br>"));
            $svariables["user_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["user_last_message"],"<br>"));

            $locall     = Config::get("general/local");


            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $variables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $mail->body(null, "user-tickets/ticket-resolved-automatic",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"ticket-resolved-automatic",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $variables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);

                $send   = $mail->body(null, "user-tickets/ticket-resolved-automatic",$variables,$ulang,$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"ticket-resolved-automatic",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $svariables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $sms->body(null, "user-tickets/ticket-resolved-automatic", $svariables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"ticket-resolved-automatic",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $svariables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);

                $send   = $sms->body(null, "user-tickets/ticket-resolved-automatic", $svariables,$ulang,$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"ticket-resolved-automatic",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "ticket",
                'owner_id' => $ticket["id"],
                'name' => "ticket-has-been-resolved-automatic",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user-tickets",
                'name'          => "ticket-resolved-automatic",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);


            return $errors ? $errors : "OK";
        }
        static function ticket_resolved_by_admin($ticket=[]){
            Helper::Load(["Orders","User","Money","Tickets"]);
            if(!is_array($ticket)) $ticket = Tickets::get_request($ticket);

            $settings       = Config::get("notifications/user-tickets/ticket-resolved-by-admin");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($ticket["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];

            $ticket_id          = $ticket["id"];
            $detail_link        = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id]);
            $user_last_reply    = Tickets::last_reply($ticket_id,"user_id,message");
            $admin_last_reply   = Tickets::last_reply($ticket_id,"user_id,message",true);
            $user_last_msg      = $user_last_reply ? $user_last_reply["message"] : NULL;
            if(!Validation::isHTML($user_last_msg)) $user_last_msg = nl2br($user_last_msg);
            $admin_last_msg     = $admin_last_reply ? $admin_last_reply["message"] : NULL;
            $department         = Tickets::get_department($ticket["did"],$ulang,"t2.name");
            $department         = $department ? $department["name"] : Bootstrap::$lang->get("needs/other",$ulang);
            $order              = $ticket["service"] ? Orders::get($ticket["service"]) : false;
            $service            = $order ? self::order_name($order) : Bootstrap::$lang->get("needs/none",$ulang);
            $status             = Bootstrap::$lang->get_cm("website/account_tickets/status-".$ticket["status"],false,$ulang);
            $priorities         = [
                1               => Bootstrap::$lang->get_cm("website/account_tickets/priority-low",false,$ulang),
                2               => Bootstrap::$lang->get_cm("website/account_tickets/priority-middle",false,$ulang),
                3               => Bootstrap::$lang->get_cm("website/account_tickets/priority-high",false,$ulang),
            ];
            $priority           = $priorities[$ticket["priority"]];
            $admin_name         = NULL;

            if($ticket["assigned"]){
                $assigned       = User::getData($ticket["assigned"],"id,full_name","array");
                $admin_name     = $assigned["full_name"];
            }elseif($admin_last_reply){
                $fetchAdmin     = User::getData($admin_last_reply["user_id"],"id,full_name","array");
                $admin_name     = $fetchAdmin["full_name"];
            }

            $cstatus            = $ticket["cstatus"] ? Tickets::custom_status($ticket["cstatus"]) : [];
            if($cstatus) if(isset($cstatus["languages"][$ulang])) $status = $cstatus["languages"][$ulang]["name"];


            $variables = [
                'ticket_id' => $ticket_id,
                'ticket_link' => $detail_link,
                'ticket_subject' => $ticket["title"],
                'user_last_message' => $user_last_msg,
                'admin_last_message' => $admin_last_msg,
                'ticket_department' => $department,
                'ticket_service'    => $service,
                'ticket_status'     => $status,
                'ticket_priority'   => $priority,
                'ticket_admin_name' => $admin_name,
            ];


            $svariables = $variables;
            $svariables["admin_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["admin_last_message"],"<br>"));
            $svariables["user_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["user_last_message"],"<br>"));

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $variables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $mail->body(null, "user-tickets/ticket-resolved-by-admin",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"ticket-resolved-by-admin",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $variables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);

                $send   = $mail->body(null, "user-tickets/ticket-resolved-by-admin",$variables,$ulang,$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"ticket-resolved-by-admin",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $svariables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $sms->body(null, "user-tickets/ticket-resolved-by-admin", $svariables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"ticket-resolved-by-admin",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $svariables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);

                $send   = $sms->body(null, "user-tickets/ticket-resolved-by-admin", $svariables,$ulang,$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"ticket-resolved-by-admin",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "ticket",
                'owner_id' => $ticket["id"],
                'name' => "ticket-has-been-resolved-by-admin",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user-tickets",
                'name'          => "ticket-resolved-by-admin",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function ticket_replied_by_admin($ticket=[]){
            Helper::Load(["Orders","User","Money","Tickets"]);
            if(!is_array($ticket)) $ticket = Tickets::get_request($ticket);
            
            if($ticket && $ticket["pipe"]) return self::ticket_replied_by_admin_pipe($ticket);

            $settings       = Config::get("notifications/user-tickets/ticket-replied-by-admin");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($ticket["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];

            $ticket_id          = $ticket["id"];
            $detail_link        = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id]);
            $user_last_reply    = Tickets::last_reply($ticket_id,"user_id,message");
            $admin_last_reply   = Tickets::last_reply($ticket_id,"user_id,message",true);
            $user_last_msg      = $user_last_reply ? $user_last_reply["message"] : NULL;
            if(!Validation::isHTML($user_last_msg)) $user_last_msg = nl2br($user_last_msg);
            $admin_last_msg     = $admin_last_reply ? $admin_last_reply["message"] : NULL;
            $department         = Tickets::get_department($ticket["did"],$ulang,"t2.name");
            $department         = $department ? $department["name"] : Bootstrap::$lang->get("needs/other",$ulang);
            $order              = $ticket["service"] ? Orders::get($ticket["service"]) : false;
            $service            = $order ? self::order_name($order) : Bootstrap::$lang->get("needs/none",$ulang);
            $status             = Bootstrap::$lang->get_cm("website/account_tickets/status-".$ticket["status"],false,$ulang);
            $priorities         = [
                1               => Bootstrap::$lang->get_cm("website/account_tickets/priority-low",false,$ulang),
                2               => Bootstrap::$lang->get_cm("website/account_tickets/priority-middle",false,$ulang),
                3               => Bootstrap::$lang->get_cm("website/account_tickets/priority-high",false,$ulang),
            ];
            $priority           = $priorities[$ticket["priority"]];
            $admin_name         = NULL;

            if($ticket["assigned"]){
                $assigned       = User::getData($ticket["assigned"],"id,full_name","array");
                $admin_name     = $assigned["full_name"];
            }elseif($admin_last_reply){
                $fetchAdmin     = User::getData($admin_last_reply["user_id"],"id,full_name","array");
                $admin_name     = $fetchAdmin["full_name"];
            }

            $cstatus            = $ticket["cstatus"] ? Tickets::custom_status($ticket["cstatus"]) : [];
            if($cstatus) if(isset($cstatus["languages"][$ulang])) $status = $cstatus["languages"][$ulang]["name"];


            $variables = [
                'ticket_id' => $ticket_id,
                'ticket_link' => $detail_link,
                'ticket_subject' => $ticket["title"],
                'user_last_message' => $user_last_msg,
                'admin_last_message' => $admin_last_msg,
                'ticket_department' => $department,
                'ticket_service'    => $service,
                'ticket_status'     => $status,
                'ticket_priority'   => $priority,
                'ticket_admin_name' => $admin_name,
            ];



            $svariables = $variables;
            $svariables["admin_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["admin_last_message"],"<br>"));
            $svariables["user_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["user_last_message"],"<br>"));

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $variables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $mail->body(null, "user-tickets/ticket-replied-by-admin",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"ticket-replied-by-admin",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $variables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);

                $send   = $mail->body(null, "user-tickets/ticket-replied-by-admin",$variables,$ulang,$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"ticket-replied-by-admin",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $svariables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $sms->body(null, "user-tickets/ticket-replied-by-admin", $svariables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"ticket-replied-by-admin",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $svariables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);

                $send   = $sms->body(null, "user-tickets/ticket-replied-by-admin", $svariables,$ulang,$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"ticket-replied-by-admin",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "ticket",
                'owner_id' => $ticket["id"],
                'name' => "ticket-has-been-replied-by-admin",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user-tickets",
                'name'          => "ticket-replied-by-admin",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function ticket_replied_by_admin_pipe($ticket=[]){
            Helper::Load(["Orders","User","Money","Tickets"]);
            if(!is_array($ticket)) $ticket = Tickets::get_request($ticket);

            $settings       = Config::get("notifications/user-tickets/ticket-replied-by-admin-pipe");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;


            $udata  = User::getData($ticket["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            if($ticket["lang"] != "none") $udata["lang"] = $ticket["lang"];
            $ulang  = $udata["lang"];


            $udata["full_name"] = $ticket["name"];
            $udata["email"]     = $ticket["email"];


            $ticket_id          = $ticket["id"];
            $detail_link        = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id]);
            $user_last_reply    = Tickets::last_reply($ticket_id,"user_id,message");
            $admin_last_reply   = Tickets::last_reply($ticket_id,"id,user_id,message",true);
            $user_last_msg      = $user_last_reply ? $user_last_reply["message"] : NULL;
            if(!Validation::isHTML($user_last_msg)) $user_last_msg = nl2br($user_last_msg);
            $admin_last_msg     = $admin_last_reply ? $admin_last_reply["message"] : NULL;
            $department         = Tickets::get_department($ticket["did"],$ulang,"t2.name");
            $department         = $department ? $department["name"] : Bootstrap::$lang->get("needs/other",$ulang);
            $order              = $ticket["service"] ? Orders::get($ticket["service"]) : false;
            $service            = $order ? self::order_name($order) : Bootstrap::$lang->get("needs/none",$ulang);
            $status             = Bootstrap::$lang->get_cm("website/account_tickets/status-".$ticket["status"],false,$ulang);
            $priorities         = [
                1               => Bootstrap::$lang->get_cm("website/account_tickets/priority-low",false,$ulang),
                2               => Bootstrap::$lang->get_cm("website/account_tickets/priority-middle",false,$ulang),
                3               => Bootstrap::$lang->get_cm("website/account_tickets/priority-high",false,$ulang),
            ];
            $priority           = $priorities[$ticket["priority"]];
            $admin_name         = NULL;

            if($ticket["assigned"]){
                $assigned       = User::getData($ticket["assigned"],"id,full_name","array");
                $admin_name     = $assigned["full_name"];
            }elseif($admin_last_reply){
                $fetchAdmin     = User::getData($admin_last_reply["user_id"],"id,full_name","array");
                $admin_name     = $fetchAdmin["full_name"];
            }


            $smtp_settings      = Config::get("options/ticket-pipe/mail/".$ticket["did"]);

            if(method_exists($mail,'setFromName')) $mail->setFromName($smtp_settings["fname"]);
            if(method_exists($mail,'setFromEmail')) $mail->setFromEmail($smtp_settings["from"] ?? ($smtp_settings["femail"]));

            $name_separator     = Filter::name_smash($udata["full_name"]);
            $udata["name"]      = $name_separator["first"];
            $udata["surname"]   = $name_separator["last"];

            $prefix             = Config::get("options/ticket-pipe/prefix");

            $cstatus            = $ticket["cstatus"] ? Tickets::custom_status($ticket["cstatus"]) : [];
            if($cstatus) if(isset($cstatus["languages"][$ulang])) $status = $cstatus["languages"][$ulang]["name"];


            $variables = [
                'user_full_name'    => $udata["full_name"],
                'user_name'         => $udata["name"],
                'user_surname'      => $udata["surname"],
                'ticket_id'         => $ticket_id,
                'ticket_link'       => $detail_link,
                'ticket_subject'    => $ticket["title"],
                'user_last_message' => $user_last_msg,
                'admin_last_message' => $admin_last_msg,
                'ticket_department' => $department,
                'ticket_service'    => $service,
                'ticket_status'     => $status,
                'ticket_priority'   => $priority,
                'ticket_admin_name' => $admin_name,
                'subject_suffix'    => ' ['.$prefix.':'.$ticket["refnum"].']',
            ];


            $svariables = $variables;
            $svariables["admin_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["admin_last_message"],"<br>"));
            $svariables["user_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["user_last_message"],"<br>"));

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $variables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $mail->body(null, "user-tickets/ticket-replied-by-admin-pipe",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"ticket-replied-by-admin-pipe",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            $attachment_folder      = ROOT_DIR.Config::get("pictures/attachment/folder");

            if($mail && $settings["user-mail"]){
                $variables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);

                $send   = $mail->body(null, "user-tickets/ticket-replied-by-admin-pipe",$variables,$ulang,$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"]);

                $attachments        = Tickets::get_attachments($admin_last_reply["id"]);

                if($attachments)
                {
                    foreach($attachments AS $at)
                        $send->addAttachment($attachment_folder.$at["file_path"],$at["file_name"]);
                }

                $send   = $send->submit();
                if($send) LogManager::Mail_Log($udata["id"],"ticket-replied-by-admin-pipe",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            Hook::run("Notified",[
                'group'         => "user-tickets",
                'name'          => "ticket-replied-by-admin-pipe",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => [],
                'admin_phones'  => [],
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function ticket_has_been_created_by_admin($ticket=[]){
            Helper::Load(["Orders","User","Money","Tickets"]);
            if(!is_array($ticket)) $ticket = Tickets::get_request($ticket);

            $settings       = Config::get("notifications/user-tickets/ticket-has-been-created-by-admin");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($ticket["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];

            $ticket_id          = $ticket["id"];
            $detail_link        = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id]);
            $user_last_reply    = Tickets::last_reply($ticket_id,"user_id,message");
            $admin_last_reply   = Tickets::last_reply($ticket_id,"user_id,message",true);
            $user_last_msg      = $user_last_reply ? $user_last_reply["message"] : NULL;
            if(!Validation::isHTML($user_last_msg)) $user_last_msg = nl2br($user_last_msg);
            $admin_last_msg     = $admin_last_reply ? $admin_last_reply["message"] : NULL;
            $department         = Tickets::get_department($ticket["did"],$ulang,"t2.name");
            $department         = $department ? $department["name"] : Bootstrap::$lang->get("needs/other",$ulang);
            $order              = $ticket["service"] ? Orders::get($ticket["service"]) : false;
            $service            = $order ? self::order_name($order) : Bootstrap::$lang->get("needs/none",$ulang);
            $status             = Bootstrap::$lang->get_cm("website/account_tickets/status-".$ticket["status"],false,$ulang);
            $priorities         = [
                1               => Bootstrap::$lang->get_cm("website/account_tickets/priority-low",false,$ulang),
                2               => Bootstrap::$lang->get_cm("website/account_tickets/priority-middle",false,$ulang),
                3               => Bootstrap::$lang->get_cm("website/account_tickets/priority-high",false,$ulang),
            ];
            $priority           = $priorities[$ticket["priority"]];
            $admin_name         = NULL;

            if($ticket["assigned"]){
                $assigned       = User::getData($ticket["assigned"],"id,full_name","array");
                $admin_name     = $assigned["full_name"];
            }elseif($admin_last_reply){
                $fetchAdmin     = User::getData($admin_last_reply["user_id"],"id,full_name","array");
                $admin_name     = $fetchAdmin["full_name"];
            }

            $cstatus            = $ticket["cstatus"] > 0 ? Tickets::custom_status($ticket["cstatus"]) : [];
            if($cstatus) if(isset($cstatus["languages"][$ulang])) $status = $cstatus["languages"][$ulang]["name"];


            $variables = [
                'ticket_id' => $ticket_id,
                'ticket_link' => $detail_link,
                'ticket_subject' => $ticket["title"],
                'user_last_message' => $user_last_msg,
                'admin_last_message' => $admin_last_msg,
                'ticket_department' => $department,
                'ticket_service'    => $service,
                'ticket_status'     => $status,
                'ticket_priority'   => $priority,
                'ticket_admin_name' => $admin_name,
            ];


            $svariables = $variables;
            $svariables["admin_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["admin_last_message"],"<br>"));
            $svariables["user_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["user_last_message"],"<br>"));

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $variables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $mail->body(null, "user-tickets/ticket-has-been-created-by-admin",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"ticket-has-been-created-by-admin",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $variables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);

                $send   = $mail->body(null, "user-tickets/ticket-has-been-created-by-admin",$variables,$ulang,$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"ticket-has-been-created-by-admin",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $svariables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $sms->body(null, "user-tickets/ticket-has-been-created-by-admin", $svariables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"ticket-has-been-created-by-admin",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){

                $svariables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);

                $send   = $sms->body(null, "user-tickets/ticket-has-been-created-by-admin", $svariables,$ulang,$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"ticket-has-been-created-by-admin",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "ticket",
                'owner_id' => $ticket["id"],
                'name' => "ticket-has-been-created-by-admin",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user-tickets",
                'name'          => "ticket-has-been-created-by-admin",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function ticket_your_has_been_created($ticket=[]){
            Helper::Load(["Orders","User","Money","Tickets"]);
            if(!is_array($ticket)) $ticket = Tickets::get_request($ticket);

            $settings       = Config::get("notifications/user-tickets/ticket-your-has-been-created");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($ticket["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];

            $ticket_id          = $ticket["id"];
            $detail_link        = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id]);
            $user_last_reply    = Tickets::last_reply($ticket_id,"user_id,message");
            $admin_last_reply   = Tickets::last_reply($ticket_id,"user_id,message",true);
            $user_last_msg      = $user_last_reply ? $user_last_reply["message"] : NULL;
            if(!Validation::isHTML($user_last_msg)) $user_last_msg = nl2br($user_last_msg);
            $admin_last_msg     = $admin_last_reply ? $admin_last_reply["message"] : NULL;
            $department         = Tickets::get_department($ticket["did"],$ulang,"t2.name");
            $department         = $department ? $department["name"] : Bootstrap::$lang->get("needs/other",$ulang);
            $order              = $ticket["service"] ? Orders::get($ticket["service"]) : false;
            $service            = $order ? self::order_name($order) : Bootstrap::$lang->get("needs/none",$ulang);
            $status             = Bootstrap::$lang->get_cm("website/account_tickets/status-".$ticket["status"],false,$ulang);
            $priorities         = [
                1               => Bootstrap::$lang->get_cm("website/account_tickets/priority-low",false,$ulang),
                2               => Bootstrap::$lang->get_cm("website/account_tickets/priority-middle",false,$ulang),
                3               => Bootstrap::$lang->get_cm("website/account_tickets/priority-high",false,$ulang),
            ];
            $priority           = $priorities[$ticket["priority"]];
            $admin_name         = NULL;

            if($ticket["assigned"]){
                $assigned       = User::getData($ticket["assigned"],"id,full_name","array");
                $admin_name     = $assigned["full_name"];
            }elseif($admin_last_reply){
                $fetchAdmin     = User::getData($admin_last_reply["user_id"],"id,full_name","array");
                $admin_name     = $fetchAdmin["full_name"];
            }

            $cstatus            = $ticket["cstatus"] ? Tickets::custom_status($ticket["cstatus"]) : [];
            if($cstatus) if(isset($cstatus["languages"][$ulang])) $status = $cstatus["languages"][$ulang]["name"];


            $variables = [
                'ticket_id' => $ticket_id,
                'ticket_link' => $detail_link,
                'ticket_subject' => $ticket["title"],
                'user_last_message' => $user_last_msg,
                'admin_last_message' => $admin_last_msg,
                'ticket_department' => $department,
                'ticket_service'    => $service,
                'ticket_status'     => $status,
                'ticket_priority'   => $priority,
                'ticket_admin_name' => $admin_name,
            ];

            $svariables = $variables;
            $svariables["admin_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["admin_last_message"],"<br>"));
            $svariables["user_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["user_last_message"],"<br>"));

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $variables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $mail->body(null, "user-tickets/ticket-your-has-been-created",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"ticket-your-has-been-created",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $variables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);
                $send   = $mail->body(null, "user-tickets/ticket-your-has-been-created",$variables,$ulang,$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"ticket-your-has-been-created",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $svariables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $sms->body(null, "user-tickets/ticket-your-has-been-created", $svariables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"ticket-your-has-been-created",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $svariables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);
                $send   = $sms->body(null, "user-tickets/ticket-your-has-been-created", $svariables,$ulang,$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"ticket-your-has-been-created",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user-tickets",
                'name'          => "ticket-your-has-been-created",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function ticket_your_has_been_processed($ticket=[]){
            Helper::Load(["Orders","User","Money","Tickets"]);
            if(!is_array($ticket)) $ticket = Tickets::get_request($ticket);

            $settings       = Config::get("notifications/user-tickets/ticket-your-has-been-processed");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($ticket["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];

            $ticket_id          = $ticket["id"];
            $detail_link        = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id]);
            $user_last_reply    = Tickets::last_reply($ticket_id,"user_id,message");
            $admin_last_reply   = Tickets::last_reply($ticket_id,"user_id,message",true);
            $user_last_msg      = $user_last_reply ? $user_last_reply["message"] : NULL;
            if(!Validation::isHTML($user_last_msg)) $user_last_msg = nl2br($user_last_msg);
            $admin_last_msg     = $admin_last_reply ? $admin_last_reply["message"] : NULL;
            $department         = Tickets::get_department($ticket["did"],$ulang,"t2.name");
            $department         = $department ? $department["name"] : Bootstrap::$lang->get("needs/other",$ulang);
            $order              = $ticket["service"] ? Orders::get($ticket["service"]) : false;
            $service            = $order ? self::order_name($order) : Bootstrap::$lang->get("needs/none",$ulang);
            $status             = Bootstrap::$lang->get_cm("website/account_tickets/status-".$ticket["status"],false,$ulang);
            $priorities         = [
                1               => Bootstrap::$lang->get_cm("website/account_tickets/priority-low",false,$ulang),
                2               => Bootstrap::$lang->get_cm("website/account_tickets/priority-middle",false,$ulang),
                3               => Bootstrap::$lang->get_cm("website/account_tickets/priority-high",false,$ulang),
            ];
            $priority           = $priorities[$ticket["priority"]];
            $admin_name         = NULL;

            if($ticket["assigned"]){
                $assigned       = User::getData($ticket["assigned"],"id,full_name","array");
                $admin_name     = $assigned["full_name"];
            }elseif($admin_last_reply){
                $fetchAdmin     = User::getData($admin_last_reply["user_id"],"id,full_name","array");
                $admin_name     = $fetchAdmin["full_name"];
            }

            $cstatus            = $ticket["cstatus"] ? Tickets::custom_status($ticket["cstatus"]) : [];
            if($cstatus) if(isset($cstatus["languages"][$ulang])) $status = $cstatus["languages"][$ulang]["name"];



            $variables = [
                'ticket_id' => $ticket_id,
                'ticket_link' => $detail_link,
                'ticket_subject' => $ticket["title"],
                'user_last_message' => $user_last_msg,
                'admin_last_message' => $admin_last_msg,
                'ticket_department' => $department,
                'ticket_service'    => $service,
                'ticket_status'     => $status,
                'ticket_priority'   => $priority,
                'ticket_admin_name' => $admin_name,
            ];



            $svariables = $variables;
            $svariables["admin_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["admin_last_message"],"<br>"));
            $svariables["user_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["user_last_message"],"<br>"));

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $variables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $mail->body(null, "user-tickets/ticket-your-has-been-processed",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"ticket-your-has-been-processed",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $variables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);
                $send   = $mail->body(null, "user-tickets/ticket-your-has-been-processed",$variables,$ulang,$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"ticket-your-has-been-processed",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $svariables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $sms->body(null, "user-tickets/ticket-your-has-been-processed", $svariables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"ticket-your-has-been-processed",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $svariables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);
                $send   = $sms->body(null, "user-tickets/ticket-your-has-been-processed", $svariables,$ulang,$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"ticket-your-has-been-processed",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "ticket",
                'owner_id' => $ticket["id"],
                'name' => "ticket-has-been-processed",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user-tickets",
                'name'          => "ticket-your-has-been-processed",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function ticket_assigned_to_you($ticket=[]){
            Helper::Load(["Orders","User","Money","Tickets"]);
            if(!is_array($ticket)) $ticket = Tickets::get_request($ticket);

            $settings       = Config::get("notifications/admin-tickets/ticket-assigned-to-you");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($ticket["user_id"],"id,lang","array");
            $ulang  = $udata["lang"];


            $ticket_id          = $ticket["id"];
            $detail_link        = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id]);
            $user_last_reply    = Tickets::last_reply($ticket_id,"user_id,message");
            $admin_last_reply   = Tickets::last_reply($ticket_id,"user_id,message",true);
            $user_last_msg      = $user_last_reply ? $user_last_reply["message"] : NULL;
            if(!Validation::isHTML($user_last_msg)) $user_last_msg = nl2br($user_last_msg);
            $admin_last_msg     = $admin_last_reply ? $admin_last_reply["message"] : NULL;
            $department         = Tickets::get_department($ticket["did"],$ulang,"t2.name");
            $department         = $department ? $department["name"] : Bootstrap::$lang->get("needs/other",$ulang);
            $order              = $ticket["service"] ? Orders::get($ticket["service"]) : false;
            $service            = $order ? self::order_name($order) : Bootstrap::$lang->get("needs/none",$ulang);
            $status             = Bootstrap::$lang->get_cm("website/account_tickets/status-".$ticket["status"],false,$ulang);
            $priorities         = [
                1               => Bootstrap::$lang->get_cm("website/account_tickets/priority-low",false,$ulang),
                2               => Bootstrap::$lang->get_cm("website/account_tickets/priority-middle",false,$ulang),
                3               => Bootstrap::$lang->get_cm("website/account_tickets/priority-high",false,$ulang),
            ];
            $priority           = $priorities[$ticket["priority"]];
            $admin_name         = NULL;

            if($ticket["assigned"]){
                $assigned       = User::getData($ticket["assigned"],"id,full_name,email,phone,lang","array");
                $admin_name     = $assigned["full_name"];
            }elseif($admin_last_reply){
                $fetchAdmin     = User::getData($admin_last_reply["user_id"],"id,full_name","array");
                $admin_name     = $fetchAdmin["full_name"];
            }

            $assignedBy          = User::getData($ticket["assignedBy"],"id,full_name,lang","array");
            $assignedBy_name     = $assignedBy["full_name"];


            $cstatus            = $ticket["cstatus"] ? Tickets::custom_status($ticket["cstatus"]) : [];
            if($cstatus) if(isset($cstatus["languages"][$ulang])) $status = $cstatus["languages"][$ulang]["name"];


            $variables = [
                'ticket_id' => $ticket_id,
                'ticket_link' => $detail_link,
                'ticket_subject' => $ticket["title"],
                'user_last_message' => $user_last_msg,
                'admin_last_message' => $admin_last_msg,
                'ticket_department' => $department,
                'ticket_service'    => $service,
                'ticket_status'     => $status,
                'ticket_priority'   => $priority,
                'ticket_admin_name' => $admin_name,
                'ticket_assigned_by_admin' => $assignedBy_name,
            ];

            $svariables = $variables;
            $svariables["admin_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["admin_last_message"],"<br>"));
            $svariables["user_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["user_last_message"],"<br>"));

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $my_variables   = $variables;
                    if(!$my_variables["ticket_admin_name"]) $my_variables["ticket_admin_name"] = $name;

                    $my_variables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $mail->body(null, "admin-tickets/ticket-assigned-to-you",$my_variables,$lang,$ticket["user_id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"ticket-assigned-to-you",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["admin-mail"]){
                $variables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$assigned["lang"]);
                $send   = $mail->body(null, "admin-tickets/ticket-assigned-to-you",$variables,$assigned["lang"],$ticket["user_id"]);
                $send   = $send->addAddress($assigned["email"],$assigned["full_name"])->submit();
                if($send) LogManager::Mail_Log($assigned["id"],"ticket-assigned-to-you",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$assigned["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $name=>$phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $my_variables   = $svariables;
                    if(!Validation::isInt($name) && !$my_variables["ticket_admin_name"])
                        $my_variables["ticket_admin_name"] = $name;

                    $my_variables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $sms->body(null, "admin-tickets/ticket-assigned-to-you", $my_variables,$lang,$ticket["user_id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"ticket-assigned-to-you",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["admin-sms"] && $assigned["phone"]){
                $svariables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$assigned["lang"]);
                $send   = $sms->body(null, "admin-tickets/ticket-assigned-to-you", $svariables,$assigned["lang"],$ticket["user_id"]);
                $send->addNumber($assigned["phone"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($assigned["id"],"ticket-assigned-to-you",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$assigned["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "admin-tickets",
                'name'          => "ticket-assigned-to-you",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function ticket_resolved_by_user($ticket=[]){
            Helper::Load(["Orders","User","Money","Tickets"]);
            if(!is_array($ticket)) $ticket = Tickets::get_request($ticket);

            $settings       = Config::get("notifications/admin-tickets/ticket-resolved-by-user");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($ticket["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];

            $ticket_id          = $ticket["id"];
            $detail_link        = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id]);
            $user_last_reply    = Tickets::last_reply($ticket_id,"user_id,message");
            $admin_last_reply   = Tickets::last_reply($ticket_id,"user_id,message",true);
            $user_last_msg      = $user_last_reply ? $user_last_reply["message"] : NULL;
            if(!Validation::isHTML($user_last_msg)) $user_last_msg = nl2br($user_last_msg);
            $admin_last_msg     = $admin_last_reply ? $admin_last_reply["message"] : NULL;
            $department         = Tickets::get_department($ticket["did"],$ulang,"t2.name");
            $department         = $department ? $department["name"] : Bootstrap::$lang->get("needs/other",$ulang);
            $order              = $ticket["service"] ? Orders::get($ticket["service"]) : false;
            $service            = $order ? self::order_name($order) : Bootstrap::$lang->get("needs/none",$ulang);
            $status             = Bootstrap::$lang->get_cm("website/account_tickets/status-".$ticket["status"],false,$ulang);
            $priorities         = [
                1               => Bootstrap::$lang->get_cm("website/account_tickets/priority-low",false,$ulang),
                2               => Bootstrap::$lang->get_cm("website/account_tickets/priority-middle",false,$ulang),
                3               => Bootstrap::$lang->get_cm("website/account_tickets/priority-high",false,$ulang),
            ];
            $priority           = $priorities[$ticket["priority"]];
            $admin_name         = NULL;

            if($ticket["assigned"]){
                $assigned       = User::getData($ticket["assigned"],"id,full_name","array");
                $admin_name     = $assigned["full_name"];
            }elseif($admin_last_reply){
                $fetchAdmin     = User::getData($admin_last_reply["user_id"],"id,full_name","array");
                $admin_name     = $fetchAdmin["full_name"];
            }

            $cstatus            = $ticket["cstatus"] ? Tickets::custom_status($ticket["cstatus"]) : [];
            if($cstatus) if(isset($cstatus["languages"][$ulang])) $status = $cstatus["languages"][$ulang]["name"];


            $variables = [
                'ticket_id' => $ticket_id,
                'ticket_link' => $detail_link,
                'ticket_subject' => $ticket["title"],
                'user_last_message' => $user_last_msg,
                'admin_last_message' => $admin_last_msg,
                'ticket_department' => $department,
                'ticket_service'    => $service,
                'ticket_status'     => $status,
                'ticket_priority'   => $priority,
                'ticket_admin_name' => $admin_name,
            ];

            $svariables = $variables;
            $svariables["admin_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["admin_last_message"],"<br>"));
            $svariables["user_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["user_last_message"],"<br>"));

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $my_variables   = $variables;
                    if((!Validation::isInt($name) && !$my_variables["ticket_admin_name"]) || $name)
                        $my_variables["ticket_admin_name"] = $name;

                    $my_variables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $mail->body(null, "admin-tickets/ticket-resolved-by-user",$my_variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"ticket-resolved-by-user",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $variables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);
                $send   = $mail->body(null, "admin-tickets/ticket-resolved-by-user",$variables,$ulang,$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"ticket-resolved-by-user",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $name=>$phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $my_variables   = $svariables;
                    if((!Validation::isInt($name) && !$my_variables["ticket_admin_name"]) || $name)
                        $my_variables["ticket_admin_name"] = $name;

                    $my_variables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $sms->body(null, "admin-tickets/ticket-resolved-by-user", $my_variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"ticket-resolved-by-user",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $svariables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);
                $send   = $sms->body(null, "admin-tickets/ticket-resolved-by-user", $svariables,$ulang,$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"ticket-resolved-by-user",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "admin-tickets",
                'name'          => "ticket-resolved-by-user",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function ticket_replied_by_user($ticket=[]){
            Helper::Load(["Orders","User","Money","Tickets"]);
            if(!is_array($ticket)) $ticket = Tickets::get_request($ticket);

            $settings       = Config::get("notifications/admin-tickets/ticket-replied-by-user");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($ticket["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];

            $ticket_id          = $ticket["id"];
            $detail_link        = '';
            $user_last_reply    = Tickets::last_reply($ticket_id,"user_id,message");
            $admin_last_reply   = Tickets::last_reply($ticket_id,"user_id,message",true);
            $user_last_msg      = $user_last_reply ? $user_last_reply["message"] : NULL;
            if(!Validation::isHTML($user_last_msg)) $user_last_msg = nl2br($user_last_msg);
            $admin_last_msg     = $admin_last_reply ? $admin_last_reply["message"] : NULL;
            $department         = Tickets::get_department($ticket["did"],$ulang,"t2.name");
            $department         = $department ? $department["name"] : Bootstrap::$lang->get("needs/other",$ulang);
            $order              = $ticket["service"] ? Orders::get($ticket["service"]) : false;
            $service            = $order ? self::order_name($order) : Bootstrap::$lang->get("needs/none",$ulang);
            $status             = Bootstrap::$lang->get_cm("website/account_tickets/status-".$ticket["status"],false,$ulang);
            $priorities         = [
                1               => Bootstrap::$lang->get_cm("website/account_tickets/priority-low",false,$ulang),
                2               => Bootstrap::$lang->get_cm("website/account_tickets/priority-middle",false,$ulang),
                3               => Bootstrap::$lang->get_cm("website/account_tickets/priority-high",false,$ulang),
            ];
            $priority           = $priorities[$ticket["priority"]];
            $admin_name         = NULL;

            if($ticket["assigned"]){
                $assigned       = User::getData($ticket["assigned"],"id,full_name","array");
                $admin_name     = $assigned["full_name"];
            }elseif($admin_last_reply){
                $fetchAdmin     = User::getData($admin_last_reply["user_id"],"id,full_name","array");
                $admin_name     = $fetchAdmin["full_name"];
            }

            $cstatus            = $ticket["cstatus"] ? Tickets::custom_status($ticket["cstatus"]) : [];
            if($cstatus) if(isset($cstatus["languages"][$ulang])) $status = $cstatus["languages"][$ulang]["name"];


            $variables = [
                'ticket_id' => $ticket_id,
                'ticket_link' => $detail_link,
                'ticket_subject' => $ticket["title"],
                'user_last_message' => $user_last_msg,
                'admin_last_message' => $admin_last_msg,
                'ticket_department' => $department,
                'ticket_service'    => $service,
                'ticket_status'     => $status,
                'ticket_priority'   => $priority,
                'ticket_admin_name' => $admin_name,
            ];

            $svariables = $variables;
            $svariables["admin_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["admin_last_message"],"<br>"));
            $svariables["user_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["user_last_message"],"<br>"));

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $my_variables   = $variables;
                    if((!Validation::isInt($name) && !$my_variables["ticket_admin_name"]) || $name)
                        $my_variables["ticket_admin_name"] = $name;

                    $my_variables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $mail->body(null, "admin-tickets/ticket-replied-by-user",$my_variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"ticket-replied-by-user",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $variables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);
                $send   = $mail->body(null, "admin-tickets/ticket-replied-by-user",$variables,$ulang,$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"ticket-replied-by-user",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $name=>$phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $my_variables   = $svariables;
                    if((!Validation::isInt($name) && !$my_variables["ticket_admin_name"]) || $name)
                        $my_variables["ticket_admin_name"] = $name;

                    $my_variables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $sms->body(null, "admin-tickets/ticket-replied-by-user", $my_variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"ticket-replied-by-user",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $svariables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);
                $send   = $sms->body(null, "admin-tickets/ticket-replied-by-user", $svariables,$ulang,$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"ticket-replied-by-user",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "admin-tickets",
                'name'          => "ticket-replied-by-user",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function ticket_has_been_created_by_user($ticket=[]){
            Helper::Load(["Orders","User","Money","Tickets"]);
            if(!is_array($ticket)) $ticket = Tickets::get_request($ticket);

            $settings       = Config::get("notifications/admin-tickets/ticket-has-been-created-by-user");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($ticket["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];

            $ticket_id          = $ticket["id"];
            $user_last_reply    = Tickets::last_reply($ticket_id,"user_id,message");
            $admin_last_reply   = Tickets::last_reply($ticket_id,"user_id,message",true);
            $user_last_msg      = $user_last_reply ? $user_last_reply["message"] : NULL;
            if(!Validation::isHTML($user_last_msg)) $user_last_msg = nl2br($user_last_msg);
            $admin_last_msg     = $admin_last_reply ? $admin_last_reply["message"] : NULL;
            $department         = Tickets::get_department($ticket["did"],$ulang,"t2.name");
            $department         = $department ? $department["name"] : Bootstrap::$lang->get("needs/other",$ulang);
            $order              = $ticket["service"] ? Orders::get($ticket["service"]) : false;
            $service            = $order ? self::order_name($order) : Bootstrap::$lang->get("needs/none",$ulang);
            $status             = Bootstrap::$lang->get_cm("website/account_tickets/status-".$ticket["status"],false,$ulang);
            $priorities         = [
                1               => Bootstrap::$lang->get_cm("website/account_tickets/priority-low",false,$ulang),
                2               => Bootstrap::$lang->get_cm("website/account_tickets/priority-middle",false,$ulang),
                3               => Bootstrap::$lang->get_cm("website/account_tickets/priority-high",false,$ulang),
            ];
            $priority           = $priorities[$ticket["priority"]];
            $admin_name         = NULL;

            if($ticket["assigned"]){
                $assigned       = User::getData($ticket["assigned"],"id,full_name","array");
                $admin_name     = $assigned["full_name"];
            }elseif($admin_last_reply){
                $fetchAdmin     = User::getData($admin_last_reply["user_id"],"id,full_name","array");
                $admin_name     = $fetchAdmin["full_name"];
            }

            $cstatus            = $ticket["cstatus"] ? Tickets::custom_status($ticket["cstatus"]) : [];
            if($cstatus) if(isset($cstatus["languages"][$ulang])) $status = $cstatus["languages"][$ulang]["name"];

            $variables = [
                'ticket_id'         => $ticket_id,
                'ticket_link'       => '',
                'ticket_subject'    => $ticket["title"],
                'user_last_message' => $user_last_msg,
                'admin_last_message' => $admin_last_msg,
                'ticket_department' => $department,
                'ticket_service'    => $service,
                'ticket_status'     => $status,
                'ticket_priority'   => $priority,
                'ticket_admin_name' => $admin_name,
            ];

            $svariables = $variables;
            $svariables["admin_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["admin_last_message"],"<br>"));
            $svariables["user_last_message"] = Filter::nl2br_reverse(Filter::html_clear($svariables["user_last_message"],"<br>"));

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $my_variables   = $variables;
                    if((!Validation::isInt($name) && !$my_variables["ticket_admin_name"]) || $name)
                        $my_variables["ticket_admin_name"] = $name;
                    $my_variables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $mail->body(null, "admin-tickets/ticket-has-been-created-by-user",$my_variables,$lang,$udata["id"]);
                    if(($ticket["WChat"] ?? 0) == 1) $mail->setSubject("WChat ".$mail->getSubject());
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"ticket-has-been-created-by-user",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $variables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);
                $send   = $mail->body(null, "admin-tickets/ticket-has-been-created-by-user",$variables,$ulang,$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"ticket-has-been-created-by-user",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $name=>$phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $my_variables   = $svariables;
                    if((!Validation::isInt($name) && !$my_variables["ticket_admin_name"]) || $name)
                        $my_variables["ticket_admin_name"] = $name;

                    $my_variables['ticket_link'] = Controllers::$init->AdminCRLink("tickets-2",["detail",$ticket_id],$lang);

                    $send   = $sms->body(null, "admin-tickets/ticket-has-been-created-by-user", $my_variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"ticket-has-been-created-by-user",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $svariables['ticket_link'] = Controllers::$init->CRLink("ac-ps-detail-ticket",[$ticket_id],$ulang);
                $send   = $sms->body(null, "admin-tickets/ticket-has-been-created-by-user", $svariables,$ulang,$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"ticket-has-been-created-by-user",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "admin-tickets",
                'name'          => "ticket-has-been-created-by-user",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }

        static function welcome($uid=0){
            Helper::Load(["User","Money"]);

            $udata  = User::getData($uid,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];
            

            $settings       = Config::get("notifications/user/welcome");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;
            
            $variables = [];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "user/welcome",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"welcome",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "user/welcome",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"welcome",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "user/welcome", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"welcome",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "user/welcome", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"welcome",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user",
                'name'          => "welcome",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function forget_password($uid=0){
            Helper::Load(["User","Money"]);

            $udata  = User::getData($uid,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];

            $secure      = User::secure_hash($udata["id"],"decrypt");
            if($secure){
                if($uid != $secure["id"]) return ['error' => "Security Problem!"];
                $udata["email"] = $secure["email"];
                if($secure["phone"]){
                    $phone_parse = Filter::phone_smash($secure["phone"]);
                    $udata["gsm_cc"] = $phone_parse["cc"];
                    $udata["gsm"]    = $phone_parse["number"];
                    $udata["phone"] = $secure["phone"];
                }
            }else
                return ['error' => "Security Problem!"];



            $settings       = Config::get("notifications/user/forget-password");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            $verify_link    = Controllers::$init->CRLink("sign/reset-password",false,$ulang);

            $reset_hash     = md5("WISECP_RESET_PASSWORD_<".time().">_WISECP");

            $submitted      = false;

            User::setInfo($uid,[
                'reset_password_key' => Crypt::encode($reset_hash,Config::get("crypt/user")),
                'reset_password_exp' => Crypt::encode(DateManager::strtotime(DateManager::next_date([
                    'hour' => 1,
                ])),Config::get("crypt/user")),
            ]);

            $verify_link .= "?verify=".$reset_hash;

            $locall     = Config::get("general/local");

            if($mail && $settings["user-mail"]){
                $verify_link_x = $verify_link."&by=desktop";
                $variables = [
                    'verify_link' => $verify_link_x
                ];
                $send   = $mail->body(null, "user/forget-password",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send){
                    $body = Utility::text_replace($mail->getBody(),[
                        $verify_link_x => '*** HIDDEN URL ***',
                    ]);
                    LogManager::Mail_Log($udata["id"],"forget-password",$mail->getSubject(),$body,implode(",",$mail->getAddresses()),false,1);
                    $submitted = true;
                }else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $verify_link_x = $verify_link."&by=mobile";
                $variables = [
                    'verify_link' => $verify_link_x
                ];
                $send   = $sms->body(null, "user/forget-password", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send){
                    $body = Utility::text_replace($sms->getBody(),[
                        $verify_link_x => '*** HIDDEN URL ***',
                    ]);
                    LogManager::Sms_Log($udata["id"],"forget-password",$sms->getTitle(),$body,implode(",",$sms->getNumbers()),false,1);
                    $submitted = true;
                }else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            Hook::run("Notified",[
                'group'         => "user",
                'name'          => "forget-password",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => [],
                'admin_phones'  => [],
                'errors'        => $errors,
            ]);


            return $submitted ? "OK" : $errors;
        }
        static function forget_password_admin($uid=0){
            Helper::Load(["User","Money"]);

            $udata  = User::getData($uid,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];

            $secure      = User::secure_hash($udata["id"],"decrypt");
            if($secure){
                if($uid != $secure["id"]) return ['error' => "Security Problem!"];
                $udata["email"] = $secure["email"];
                if($secure["phone"]){
                    $phone_parse = Filter::phone_smash($secure["phone"]);
                    $udata["gsm_cc"] = $phone_parse["cc"];
                    $udata["gsm"]    = $phone_parse["number"];
                    $udata["phone"] = $secure["phone"];
                }
            }


            $settings       = Config::get("notifications/user/forget-password-admin");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $verify_link    = Controllers::$init->AdminCRLink("sign/reset-password",false,$ulang);

            $reset_hash     = md5("WISECP_RESET_PASSWORD_<".time().">_WISECP");

            $submitted = false;

            User::setInfo($uid,[
                'reset_password_key' => Crypt::encode($reset_hash,Config::get("crypt/user")),
                'reset_password_exp' => Crypt::encode(DateManager::strtotime(DateManager::next_date([
                    'hour' => 1,
                ])),Config::get("crypt/user")),
            ]);

            $verify_link .= "?verify=".$reset_hash;

            $locall     = Config::get("general/local");

            if($mail && $settings["admin-mail"]){
                $verify_link_x = $verify_link."&by=desktop";
                $variables = [
                    'verify_link' => $verify_link_x
                ];

                $send   = $mail->body(null, "user/forget-password-admin",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send){
                    $body = Utility::text_replace($mail->getBody(),[
                        $verify_link_x => '*** HIDDEN URL ***',
                    ]);
                    LogManager::Mail_Log($udata["id"],"forget-password-admin",$mail->getSubject(),$body,implode(",",$mail->getAddresses()),false,1);
                    $submitted = true;
                }
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $settings["admin-sms"] && $udata["phone"]){
                $verify_link_x = $verify_link."&by=mobile";
                $variables = [
                    'verify_link' => $verify_link_x
                ];

                $send   = $sms->body(null, "user/forget-password-admin", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send){
                    $body = Utility::text_replace($sms->getBody(),[
                        $verify_link_x => '*** HIDDEN URL ***',
                    ]);
                    LogManager::Sms_Log($udata["id"],"forget-password-admin",$sms->getTitle(),$body,implode(",",$sms->getNumbers()),false,1);
                    $submitted = true;
                }
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            return $submitted ? "OK" : $errors;
        }
        static function email_activation($uid=0,$code=''){
            Helper::Load(["User","Money"]);

            $udata  = User::getData($uid,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];


            $settings       = Config::get("notifications/user/email-activation");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $variables = [
                'activation_code' => $code,
                'activation_link' => Controllers::$init->CRLink("ac-ps-info",false,$ulang)."?tab=5&v_email_code=".$code,
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "user/email-activation",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"email-activation",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "user/email-activation",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"email-activation",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()),$code,1);
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "user/email-activation", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"email-activation",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "user/email-activation", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"email-activation",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()),$code,1);
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            Hook::run("Notified",[
                'group'         => "user",
                'name'          => "email-activation",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => [],
                'admin_phones'  => [],
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function gsm_activation($uid=0,$code=''){
            Helper::Load(["User","Money"]);

            $udata  = User::getData($uid,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];


            $secure      = User::secure_hash($udata["id"],"decrypt");
            if($secure){
                if($uid != $secure["id"]) return ['error' => "Security Problem!"];
                $udata["email"] = $secure["email"];
                if($secure["phone"]){
                    $phone_parse = Filter::phone_smash($secure["phone"]);
                    $udata["gsm_cc"] = $phone_parse["cc"];
                    $udata["gsm"]    = $phone_parse["number"];
                    $udata["phone"] = $secure["phone"];
                }
            }


            $settings       = Config::get("notifications/user/gsm-activation");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $variables = [
                'activation_code' => $code,
            ];

            $locall     = Config::get("general/local");

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "user/gsm-activation",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"gsm-activation",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()),$code,1);
                else $errors["mail"][$udata["email"]] = $mail->error;
            }
            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "user/gsm-activation", $variables,$ulang,$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"gsm-activation",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()),$code,1);
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            Hook::run("Notified",[
                'group'         => "user",
                'name'          => "gsm-activation",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => [],
                'admin_phones'  => [],
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function approve_ctoc_service_transfer($evt=[]){
            Helper::Load(["Orders","User","Money","Events"]);
            if(!is_array($evt)) $evt = Events::get($evt);

            if(!$evt) return false;

            $settings       = Config::get("notifications/user/approve-ctoc-service-transfer");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            $evt_data    = $evt["data"];

            $udata  = User::getData($evt_data["to_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $order  = Orders::get($evt["owner_id"]);

            $evt_data["order_name"]     = Orders::detail_name($order);
            $evt_data["order_amount"]   = Money::formatter_symbol($order["amount"],$order["amount_cid"]);
            $evt_data["approve_link"]   = Controllers::$init->CRLink("ac-ps-products")."?approve_ctoc_s_t=".$evt["id"];

            $variables = $evt_data;

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "user/approve-ctoc-service-transfer",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"need-manually-upgrading",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()),false,1);
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "user/approve-ctoc-service-transfer",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"approve-ctoc-service-transfer",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()),false,1);
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "user/approve-ctoc-service-transfer", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"approve-ctoc-service-transfer",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()),false,1);
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "user/approve-ctoc-service-transfer", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"approve-ctoc-service-transfer",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()),false,1);
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user",
                'name'          => "approve-ctoc-service-transfer",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function account_has_been_blocked($uid=0){
            Helper::Load(["User","Money"]);

            $udata  = User::getData($uid,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];

            $client_data        = array_merge((array) User::getData($udata["id"],
                [
                    'id',
                    'name',
                    'surname',
                    'full_name',
                    'company_name',
                    'email',
                    'phone',
                    'currency',
                    'lang',
                    'country',
                ], "array"), User::getInfo($udata["id"],
                [
                    'company_tax_number',
                    'company_tax_office',
                    'gsm_cc',
                    'gsm',
                    'landline_cc',
                    'landline_phone',
                    'identity',
                    'kind',
                    'taxation',
                ]));
            $client_data["address"] = AddressManager::getAddress(0,$udata["id"]);
            $client_data["source"]  = "admin";

            Hook::run("ClientBlocked",$client_data);


            $settings       = Config::get("notifications/user/account-has-been-blocked");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $variables = [];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "user/account-has-been-blocked",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"account-has-been-blocked",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "user/account-has-been-blocked",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"account-has-been-blocked",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "user/account-has-been-blocked", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"account-has-been-blocked",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "user/account-has-been-blocked", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"account-has-been-blocked",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user",
                'name'          => "account-has-been-blocked",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function account_has_been_activated($uid=0){
            Helper::Load(["User","Money"]);

            $udata  = User::getData($uid,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];


            $client_data        = array_merge((array) User::getData($udata["id"],
                [
                    'id',
                    'name',
                    'surname',
                    'full_name',
                    'company_name',
                    'email',
                    'phone',
                    'currency',
                    'lang',
                    'country',
                ], "array"), User::getInfo($udata["id"],
                [
                    'company_tax_number',
                    'company_tax_office',
                    'gsm_cc',
                    'gsm',
                    'landline_cc',
                    'landline_phone',
                    'identity',
                    'kind',
                    'taxation',
                ]));
            $client_data["address"] = AddressManager::getAddress(0,$udata["id"]);
            $client_data["source"]  = "admin";

            Hook::run("ClientActivated",$client_data);


            $settings       = Config::get("notifications/user/account-has-been-activated");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $variables = [];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "user/account-has-been-activated",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"account-has-been-activated",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "user/account-has-been-activated",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"account-has-been-activated",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "user/account-has-been-activated", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"account-has-been-activated",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "user/account-has-been-activated", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"account-has-been-activated",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user",
                'name'          => "account-has-been-activated",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function dealership_has_been_activated($uid=0){
            Helper::Load(["User","Money","Products"]);

            $udata  = User::getData($uid,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc","dealership"]));
            $ulang  = $udata["lang"];


            $settings       = Config::get("notifications/user/dealership-has-been-activated");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            $dealership  = $udata["dealership"] ? Utility::jdecode($udata["dealership"],true) : [];
            $discounts   = [];

            $dealership  = array_replace_recursive((array) Config::get("options/dealership"),$dealership);


            $dp_information = [];

            if($dealership["require_min_credit_amount"]>0 || $dealership["require_min_discount_amount"]>0 || $dealership["only_credit_paid"]){
                if($dealership["require_min_credit_amount"])
                    $dp_information[] = "» ".Bootstrap::$lang->get_cm("website/account/reseller-condition1",[
                        '{amount}' => '<strong>'.Money::formatter_symbol($dealership["require_min_credit_amount"],$dealership["require_min_credit_cid"]).'</strong>',
                    ],$ulang);

                if($dealership["require_min_discount_amount"])
                    $dp_information[] = "» ".Bootstrap::$lang->get_cm("website/account/reseller-condition2",[
                            '{amount}' => '<strong>'.Money::formatter_symbol($dealership["require_min_discount_amount"],$dealership["require_min_discount_cid"]).'</strong>',
                        ],$ulang);
                if($dealership["only_credit_paid"])
                    $dp_information[] = "» ".Bootstrap::$lang->get_cm("website/account/reseller-condition3",false,$ulang);
            }


            $dp_information = implode("<br>",$dp_information);

            $variables = [
                'dealership_information' => $dp_information,
            ];

            $svariables = $variables;
            $svariables["dealership_information"] = str_replace("<br>","\n",strip_tags(nl2br($svariables["dealership_information"]),"<br>"));

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "user/dealership-has-been-activated",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"dealership-has-been-activated",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "user/dealership-has-been-activated",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"dealership-has-been-activated",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "user/dealership-has-been-activated", $svariables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"dealership-has-been-activated",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "user/dealership-has-been-activated", $svariables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"dealership-has-been-activated",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'name' => "dealership-has-been-activated",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user",
                'name'          => "dealership-has-been-activated",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function credit_fell_below_a_minimum($uid=0){
            Helper::Load(["User","Money"]);

            $udata  = User::getData($uid,"id,email,lang,country,full_name,balance,balance_min,balance_currency","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];


            $settings       = Config::get("notifications/user/credit-fell-below-a-minimum");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            $min_credit = Money::formatter_symbol($udata["balance_min"],$udata["balance_currency"]);
            $credit     = Money::formatter_symbol($udata["balance"],$udata["balance_currency"]);

            $variables = [
                'user_min_credit'   => $min_credit,
                'user_credit'       => $credit,
                'user_credit_link'  => Controllers::$init->CRLink("ac-ps-balance",false,$ulang),
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "user/credit-fell-below-a-minimum",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"credit-fell-below-a-minimum",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "user/credit-fell-below-a-minimum",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"credit-fell-below-a-minimum",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "user/credit-fell-below-a-minimum", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"credit-fell-below-a-minimum",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "user/credit-fell-below-a-minimum", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"credit-fell-below-a-minimum",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'name' => "credit-fell-below-a-minimum",
                'data' => [
                    'credit'     => $variables["user_credit"],
                    'min_credit' => $variables["user_min_credit"],
                ],
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user",
                'name'          => "credit-fell-below-a-minimum",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function email_changed($uid=0,$old='',$new=''){
            Helper::Load(["User","Money"]);

            $udata  = User::getData($uid,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];


            $settings       = Config::get("notifications/user/email-changed");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            Helper::Load("Browser");

            $location               = UserManager::ip_info();
            $browser                = new Browser();

            $variables = [
                'old_email'         => $old,
                'new_email'         => $new,
                'ip'                => UserManager::GetIP(),
                'location_country'  => $location["country"],
                'location_city'     => $location["city"],
                'browser'           => $browser->getBrowser()." ".$browser->getVersion(),
                'platform'          => $browser->getPlatform(),
                'date'              => DateManager::Now(Config::get("options/date-format")." H:i"),
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "user/email-changed",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"email-changed",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "user/email-changed",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($old,$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"email-changed",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$old] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "user/email-changed", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"email-changed",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "user/email-changed", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"email-changed",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user",
                'name'          => "email-changed",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function password_changed($uid=0){
            Helper::Load(["User","Money"]);

            $udata  = User::getData($uid,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];


            $settings       = Config::get("notifications/user/password-changed");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            Helper::Load("Browser");

            $location               = UserManager::ip_info();
            $browser                = new Browser();

            $variables = [
                'ip'                => UserManager::GetIP(),
                'location_country'  => $location["country"],
                'location_city'     => $location["city"],
                'browser'           => $browser->getBrowser()." ".$browser->getVersion(),
                'platform'          => $browser->getPlatform(),
                'date'              => DateManager::Now(Config::get("options/date-format")." H:i"),
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "user/password-changed",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"password-changed",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "user/password-changed",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"password-changed",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "user/password-changed", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"password-changed",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "user/password-changed", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"password-changed",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user",
                'name'          => "password-changed",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function failed_member_login_attempt($uid=0){
            Helper::Load(["User","Money"]);

            $udata  = User::getData($uid,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];


            $settings       = Config::get("notifications/user/failed-member-login-attempt");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            Helper::Load("Browser");

            $location               = UserManager::ip_info();
            $browser                = new Browser();

            $variables = [
                'ip'                => UserManager::GetIP(),
                'location_country'  => $location["country"],
                'location_city'     => $location["city"],
                'browser'           => $browser->getBrowser()." ".$browser->getVersion(),
                'platform'          => $browser->getPlatform(),
                'date'              => DateManager::Now(Config::get("options/date-format")." H:i"),
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "user/failed-member-login-attempt",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"failed-member-login-attempt",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "user/failed-member-login-attempt",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"failed-member-login-attempt",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "user/failed-member-login-attempt", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"failed-member-login-attempt",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "user/failed-member-login-attempt", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"failed-member-login-attempt",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user",
                'name'          => "failed-member-login-attempt",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function failed_admin_login_attempt($uid=0){
            Helper::Load(["User","Money"]);

            $udata  = User::getData($uid,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];


            $settings       = Config::get("notifications/admin-messages/failed-admin-login-attempt");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            Helper::Load("Browser");

            $location               = UserManager::ip_info();
            $browser                = new Browser();

            $variables = [
                'ip'                => UserManager::GetIP(),
                'location_country'  => $location["country"],
                'location_city'     => $location["city"],
                'browser'           => $browser->getBrowser()." ".$browser->getVersion(),
                'platform'          => $browser->getPlatform(),
                'date'              => DateManager::Now(Config::get("options/date-format")." H:i"),
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "admin-messages/failed-admin-login-attempt",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"failed-admin-login-attempt",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["admin-mail"]){
                $send   = $mail->body(null, "admin-messages/failed-admin-login-attempt",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"failed-admin-login-attempt",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "admin-messages/failed-admin-login-attempt", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"failed-admin-login-attempt",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["admin-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "admin-messages/failed-admin-login-attempt", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"failed-admin-login-attempt",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user",
                'name'          => "failed-admin-login-attempt",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function contact_form($data=[]){
            $settings       = Config::get("notifications/admin-messages/contact-form");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            $variables = [
                'user_full_name' => $data["full_name"],
                'user_name'      => $data["name"],
                'user_surname'   => $data["surname"],
                'user_email'     => $data["email"],
                'user_phone'     => $data["phone"],
                'user_message'   => $data["message"],
                'user_ip'        => $data["ip"],
            ];

            $mail_variables = $variables;
            $mail_variables["user_message"] = nl2br($mail_variables["user_message"]);

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "admin-messages/contact-form",$mail_variables,$lang);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"contact-form",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "admin-messages/contact-form",$mail_variables);
                $send   = $send->addAddress($data["email"],$data["full_name"])->submit();
                if($send) LogManager::Mail_Log(0,"contact-form",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$data["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "admin-messages/contact-form", $variables,$lang);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"contact-form",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($settings["user-sms"] && $data["phone"]){
                $send   = $sms->body(null, "admin-messages/contact-form", $variables);
                $send->addNumber($data["phone"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log(0,"contact-form",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$data["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "admin-messages",
                'name'          => "contact-form",
                'user_data'     => [],
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function contact_form_admin_reply($data=[]){
            $settings       = Config::get("notifications/user/contact-form-admin-reply");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            $variables = $data;

            $mail_variables = $variables;
            $mail_variables["visitor_message"] = nl2br($mail_variables["visitor_message"]);
            $mail_variables["admin_message"]   = nl2br($mail_variables["admin_message"]);

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "user/contact-form-admin-reply",$mail_variables,$lang);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"contact-form-admin-reply",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "user/contact-form-admin-reply",$mail_variables,$data["lang"]);
                $send   = $send->addAddress($data["email"],$data["visitor_name"])->submit();
                if($send) LogManager::Mail_Log(0,"contact-form-admin-reply",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$data["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "user/contact-form-admin-reply", $variables,$lang);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"contact-form-admin-reply",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($settings["user-sms"] && $data["phone"]){
                $send   = $sms->body(null, "user/contact-form-admin-reply", $variables,$data["lang"]);
                $send->addNumber($data["phone"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log(0,"contact-form-admin-reply",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$data["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user",
                'name'          => "contact-form-admin-reply",
                'user_data'     => [],
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function message_from_admin($data=[]){
            $settings       = Config::get("notifications/user/message-from-admin");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            $udata  = User::getData($data["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));


            $variables = $data;

            $mail_variables = $variables;
            $mail_variables["admin_message"]   = nl2br($mail_variables["admin_message"]);

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "user/message-from-admin",$mail_variables,$lang,$udata["id"]);
                    if($data["subject"]) $send->subject($data["subject"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"message-from-admin",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "user/message-from-admin",$mail_variables,$udata["lang"],$udata["id"]);
                if($data["subject"]) $send->subject($data["subject"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"message-from-admin",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "user/message-from-admin", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"message-from-admin",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "user/message-from-admin", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"message-from-admin",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user",
                'name'          => "message-from-admin",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function customer_feedback($data=[]){
            $settings       = Config::get("notifications/admin-messages/customer-feedback");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            $variables = $data;

            $locall     = Config::get("general/local");

            if($mail && $emails){
                $mail_variables = $variables;
                $mail_variables["customer_message"] = nl2br($mail_variables["customer_message"]);
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "admin-messages/customer-feedback",$mail_variables,$lang);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"customer-feedback",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($settings["user-mail"] && $data["customer_email"]){
                $send   = $mail->body(null, "admin-messages/customer-feedback",$mail_variables);
                $send   = $send->addAddress($data["customer_email"],$data["customer_full_name"])->submit();
                if($send) LogManager::Mail_Log(0,"customer-feedback",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$data["customer_email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "admin-messages/customer-feedback", $variables,$lang);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"customer-feedback",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "admin-messages",
                'name'          => "customer-feedback",
                'user_data'     => [],
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function need_manually_upgrading($updown=[]){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($updown)) $updown = Orders::get_updown($updown);

            $settings       = Config::get("notifications/admin-messages/need-manually-upgrading");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($updown["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));


            $variables = [
                'product_old_name'      => $updown["options"]["old_name"],
                'product_old_amount'    => Money::formatter_symbol($updown["options"]["old_amount"],$updown["options"]["currency"]),
                'product_new_name'      => $updown["options"]["new_name"],
                'product_new_amount'    => Money::formatter_symbol($updown["options"]["new_amount"],$updown["options"]["currency"]),
                'difference_fee'        => Money::formatter_symbol($updown["options"]["difference_amount"],$updown["options"]["currency"]),
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "admin-messages/need-manually-upgrading",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"need-manually-upgrading",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "admin-messages/need-manually-upgrading",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"need-manually-upgrading",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "admin-messages/need-manually-upgrading", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"need-manually-upgrading",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "admin-messages/need-manually-upgrading", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"need-manually-upgrading",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "admin-messages",
                'name'          => "need-manually-upgrading",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function customer_has_upgraded($updown=[]){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($updown)) $updown = Orders::get_updown($updown);

            $settings       = Config::get("notifications/admin-messages/customer-has-upgraded");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($updown["user_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));


            $variables = [
                'product_old_name'      => $updown["options"]["old_name"],
                'product_old_amount'    => Money::formatter_symbol($updown["options"]["old_amount"],$updown["options"]["currency"]),
                'product_new_name'      => $updown["options"]["new_name"],
                'product_new_amount'    => Money::formatter_symbol($updown["options"]["new_amount"],$updown["options"]["currency"]),
                'difference_fee'        => Money::formatter_symbol($updown["options"]["difference_amount"],$updown["options"]["currency"]),
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "admin-messages/customer-has-upgraded",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"customer-has-upgraded",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "admin-messages/customer-has-upgraded",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"customer-has-upgraded",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "admin-messages/customer-has-upgraded", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"customer-has-upgraded",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "admin-messages/customer-has-upgraded", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"customer-has-upgraded",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "admin-messages",
                'name'          => "customer-has-upgraded",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function failed_order_activation($order=[]){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/admin-messages/failed-order-activation");
            if(!$settings["status"]) return false;

            if(!(Utility::strlen($order["status_msg"]) > 4)) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
            if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
            else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);

            $locall     = Config::get("general/local");

            $detail_link                = Controllers::$init->AdminCRLink("orders-2",["detail",$order["id"]],$locall);

            $variables = [
                'order_name'            => self::order_name($order),
                'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                'order_date_created'    => $date_start,
                'order_date_start'      => $date_start,
                'order_date_end'        => $date_end,
                'order_detail_link'     => $detail_link,
                'error_message'         => $order["status_msg"],
            ];

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "admin-messages/failed-order-activation",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"failed-order-activation",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "admin-messages/failed-order-activation",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"failed-order-activation",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "admin-messages/failed-order-activation", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"failed-order-activation",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "admin-messages/failed-order-activation", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"failed-order-activation",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "admin-messages",
                'name'          => "failed-order-activation",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function need_manually_transaction($order=[],$event=[]){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/admin-messages/need-manually-transaction");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
            if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
            else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);

            $locall     = Config::get("general/local");

            $detail_link                = Controllers::$init->AdminCRLink("orders-2",["detail",$order["id"]],$locall);

            Helper::Load(["Events"]);
            if(!is_array($event)) $event = Events::get($event);
            $reason     = Events::getMessage($event);

            $variables = [
                'order_name'            => self::order_name($order),
                'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                'order_date_created'    => $date_start,
                'order_date_start'      => $date_start,
                'order_date_end'        => $date_end,
                'order_detail_link'     => $detail_link,
                'reason'                => $reason,
            ];

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "admin-messages/need-manually-transaction",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"need-manually-transaction",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "admin-messages/need-manually-transaction",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"need-manually-transaction",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "admin-messages/need-manually-transaction", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"need-manually-transaction",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "admin-messages/need-manually-transaction", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"need-manually-transaction",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "admin-messages",
                'name'          => "need-manually-transaction",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function cancel_request_created($order=[],$urgency='',$reason=''){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/admin-messages/cancel-request-created");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
            if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
            else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);

            $locall     = Config::get("general/local");

            $detail_link                = Controllers::$init->AdminCRLink("orders-2",["detail",$order["id"]],$locall);

            $urgency                    = Bootstrap::$lang->get_cm("admin/orders/cancellation-urgency-".$urgency,false,$locall);

            $variables = [
                'order_name'            => self::order_name($order),
                'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                'order_date_created'    => $date_start,
                'order_date_start'      => $date_start,
                'order_date_end'        => $date_end,
                'order_detail_link'     => $detail_link,
                'cancel_urgency'        => $urgency,
                'cancel_reason'         => $reason,
            ];

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "admin-messages/cancel-request-created",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"cancel-request-created",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "admin-messages/cancel-request-created",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"cancel-request-created",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "admin-messages/cancel-request-created", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"cancel-request-created",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "admin-messages/cancel-request-created", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"cancel-request-created",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "admin-messages",
                'name'          => "cancel-request-created",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function created_backup_db($filepath=''){

            if(!$filepath) return false;

            $settings       = Config::get("notifications/admin-messages/created-backup-db");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail","MailerPHP");
            $mail_module  = "MailerPHP";
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            $locall      = Config::get("general/local");

            $variables   = [];

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "admin-messages/created-backup-db",$variables,$lang);
                    $send   = $send->addAddress($address,$name)->addAttachment($filepath)->submit();
                    if($send) LogManager::Mail_Log(0,"created-backup-db",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }


            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "admin-messages/created-backup-db", $variables,$lang);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"created-backup-db",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            $admin_emails = [];
            $admin_phones = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "admin-messages",
                'name'          => "created-backup-db",
                'user_data'     => [],
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function task_plan_created($task=0){
            $settings       = Config::get("notifications/admin-messages/task-plan-created");
            if(!$settings["status"]) return false;

            if(!is_array($task)){
                $task       = Models::$init->db->select()->from("users_tasks")->where("id","=",$task);
                $task       = $task->build() ? $task->getAssoc() : false;
            }

            if(!$task) return false;


            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            $locall     = Config::get("general/local");

            $emails     = [];
            $phones     = [];

            if($task["departments"]){
                $departments    = explode(",",$task["departments"]);
                $admins         = self::departmentAdmins($departments);
                if($admins){
                    $emails = isset($admins["emails"]) ? $admins["emails"] : [];
                    $phones = isset($admins["phones"]) ? $admins["phones"] : [];
                }
            }

            if($task["admin_id"]){
                $admin_id   = $task["admin_id"];
                $gData      = User::getData($admin_id,"full_name,email,lang","array");
                if($gData) $gData = array_merge($gData,User::getInfo($admin_id,"gsm,gsm_cc"));
                if($gData){
                    $emails[$gData["email"]] = $gData["full_name"]."|".$gData["lang"];
                    if($gData["gsm"] != ''){
                        $gsm_body = $gData["gsm"]."|".$gData["gsm_cc"]."|".$gData["lang"];
                        if(!in_array($gsm_body,$phones)) $phones[$gData["full_name"]] = $gsm_body;
                    }
                }
            }

            $owner_name     = ___("needs/none",false,$locall);
            $departments    = ___("needs/none",false,$locall);

            if($task["owner_id"]){
                $owner_id       = $task["owner_id"];
                $gData      = User::getData($owner_id,"full_name","array");
                if($gData) $owner_name = $gData["full_name"];
            }

            if($task["departments"]){
                Helper::Load(["Tickets"]);
                $departments        = [];
                $task_departments   = explode(",",$task["departments"]);
                foreach(Tickets::get_departments($locall,"t1.id,t2.name") AS $row)
                    if(in_array($row["id"],$task_departments)) $departments[] = $row["name"];
                $departments        = implode(", ",$departments);
            }

            $variables = [
                'title'         => $task["title"],
                'description'   => $task["description"],
                'c_date'        => DateManager::format(Config::get("options/date-format"),$task["c_date"]),
                'due_date'      => ___("needs/none",false,$locall),
                'by_name'       => $owner_name,
                'admin_name'    => NULL,
                'departments'   => $departments,
                'detail_link'   => NULL,
            ];

            if(substr($task["due_date"],0,4) != "1881") $variables["due_date"] = DateManager::format(Config::get("options/date-format"),$task["due_date"]);

            if($mail && $emails && $settings["admin-mail"]){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $variables["admin_name"] = $name;
                    $variables["detail_link"] = Controllers::$init->AdminCRLink("tools-2",["tasks","detail"],$lang)."?id=".$task["id"];
                    $send   = $mail->body(null, "admin-messages/task-plan-created",$variables,$lang);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"task-plan-created",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($sms && $phones && $settings["admin-sms"]){
                foreach($phones AS $name=>$phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $variables["admin_name"] = $name;
                    $variables["detail_link"] = Controllers::$init->AdminCRLink("tools-2",["tasks","detail"],$lang)."?id=".$task["id"];
                    $send   = $sms->body(null, "admin-messages/task-plan-created", $variables,$lang);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"task-plan-created",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "admin-messages",
                'name'          => "task-plan-created",
                'user_data'     => [],
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function remind($reminder=[]){

            if(!$reminder) return false;

            $settings       = Config::get("notifications/admin-messages/reminding");
            if(!$settings["status"]) return false;

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            $locall      = Config::get("general/local");

            $variables   = [
                'note'          => NULL,
                'c_date'        => DateManager::format(Config::get("options/date-format")." H:i",$reminder["creation_time"]),
                'reminder_time' => DateManager::Now(Config::get("options/date-format")." H:i"),
                'admin_name'    => NULL,
            ];

            $emails             = [];
            $phones             = [];


            if($reminder["owner_id"]){
                $admin_id   = $reminder["owner_id"];
                $gData      = User::getData($admin_id,"full_name,email,lang","array");
                if($gData) $gData = array_merge($gData,User::getInfo($admin_id,"gsm,gsm_cc"));
                if($gData){
                    $emails[$gData["email"]] = $gData["full_name"]."|".$gData["lang"];
                    if($gData["gsm"] != ''){
                        $gsm_body = $gData["gsm"]."|".$gData["gsm_cc"]."|".$gData["lang"];
                        if(!in_array($gsm_body,$phones)) $phones[$gData["full_name"]] = $gsm_body;
                    }
                }
            }


            if($mail && $emails && $settings["admin-mail"]){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $variables["admin_name"] = $name;
                    $variables["note"] = nl2br($reminder["note"]);
                    $send   = $mail->body(null, "admin-messages/reminding",$variables,$lang);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"reminding",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($sms && $phones && $settings["admin-sms"]){
                foreach($phones AS $name=>$phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $variables["admin_name"] = $name;
                    $variables["note"]       = $reminder["note"];
                    $send   = $sms->body(null, "admin-messages/reminding", $variables,$lang);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"reminding",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "admin-messages",
                'name'          => "reminding",
                'user_data'     => [],
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function remind_template($template=[]){
            if(!$template) return false;

            $template['user_groups'] = Utility::jdecode($template['user_groups'],true);
            $template['departments'] = Utility::jdecode($template['departments'],true);
            $template['countries'] = Utility::jdecode($template['countries'],true);
            $template['languages'] = Utility::jdecode($template['languages'],true);
            $template['services'] = Utility::jdecode($template['services'],true);
            $template['servers'] = Utility::jdecode($template['servers'],true);
            $template['addons'] = Utility::jdecode($template['addons'],true);
            $template['services_status'] = Utility::jdecode($template['services_status'],true);
            $template['client_status'] = Utility::jdecode($template['client_status'],true);


            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            $locall      = Config::get("general/local");

            $type       = $template['template_type'];

            $filter_users      = User::bulk_notification_contact_list($template["type"],$template["template_type"],$template['user_groups'],$template['departments'],$template['countries'],$template['languages'],$template['services'],$template['servers'],$template['addons'],$template['services_status'],$template['without_products'],$template['client_status'],$template['birthday_marketing']);

            $users              = [];
            if($filter_users) foreach($filter_users AS $u) $users[] = $u["id"];
            $cc                 = $template['cc'];
            $newsletter         = $template['newsletter'];
            $subject            = $template['subject'];
            $message            = $template['message'];
            $submission_type    = $template['submission_type'];

            if(!Validation::isEmpty($cc)){
                $cc_parse = explode("\n",$cc);
                if($cc_parse){
                    $cc = [];
                    foreach($cc_parse AS $c){
                        if($type == 'sms')
                        {
                            $c  = Filter::numbers($c);
                            if(!Validation::isPhone($c)) continue;
                        }
                        else
                        {
                            $c  = Filter::email($c);
                            if(!Validation::isEmail($c)) continue;
                        }
                        $cc[] = $c;
                    }
                }
            }

            if($newsletter)
            {
                $newsletter_d = Models::$init->db->select("content")->from("newsletters");
                $newsletter_d->where("type","=",($type == "sms" ? "sms" : "email"),"&&");
                $newsletter_d->where("lang","=",$newsletter);
                if($newsletter_d->build())
                {
                    $newsletter = [];
                    foreach($newsletter_d->fetch_object() AS $o) $newsletter[] = $o->content;
                }
            }

            if(!$users && !$cc && !$newsletter) return Bootstrap::$lang->get_cm("admin/tools/error1",false,$locall);

            if($type != "sms" && Validation::isEmpty($subject))
                return Bootstrap::$lang->get_cm("admin/tools/error3",false,$locall);

            if(Validation::isEmpty($message))
                return Bootstrap::$lang->get_cm("admin/tools/error4",false,$locall);

            $emails     = [];
            $phones     = [];

            if($users){
                foreach($users AS $uid){
                    $uid    = (int) $uid;
                    if(!$uid) continue;
                    $gData  = User::getData($uid,"id,full_name,email,phone,lang","array");
                    if($gData){
                        if($type == "sms" && $gData["phone"])
                        {
                            $gData = array_merge($gData,User::getInfo($uid,["gsm_cc","gsm"]));
                            $phones[] = $gData["gsm_cc"]."|".$gData["gsm"]."|".$gData["id"]."|".$gData["lang"];
                        }
                        else
                            $emails[$gData["email"]] = $gData["full_name"]."|".$gData["id"]."|".$gData["lang"];
                    }
                }
            }

            $submissions    = [];


            if($type == "sms")
            {
                if($cc){
                    foreach($cc AS $c){
                        $parse_gsm = Filter::phone_smash($c);
                        $gsm_cc    = $parse_gsm["cc"];
                        $gsm_num   = $parse_gsm["number"];
                        if(!array_search($gsm_cc."|".$gsm_num,$phones)) $phones[] = $gsm_cc."|".$gsm_num;
                    }
                }

                if($newsletter){
                    foreach($newsletter AS $nr){
                        $parse_gsm = Filter::phone_smash($nr);
                        $gsm_cc    = $parse_gsm["cc"];
                        $gsm_num   = $parse_gsm["number"];
                        if(!array_search($gsm_cc."|".$gsm_num,$phones)) $phones[] = $gsm_cc."|".$gsm_num;
                    }
                }

                if(!$phones) return Bootstrap::$lang->get_cm("admin/tools/error2",false,$locall);

                if($submission_type == "single"){
                    foreach($phones AS $phone){
                        $parse  = explode("|",$phone);
                        $cc     = $parse[0];
                        $number = $parse[1];
                        $uid    = isset($parse[2]) ? $parse[2] : 0;
                        $lang   = isset($parse[3]) ? $parse[3] : $locall;
                        $msg    = $message;
                        View::variables_handler("sms",$uid,[],$msg,$lang);
                        $send   = $sms->body($msg,false,false,$lang,$uid);
                        $send   = $send->addNumber($number,$cc)->submit();
                        if($send){
                            $submissions[] = "+".$cc.$number;
                            LogManager::Sms_Log(0,"bulk-sms",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                        }
                    }
                }
                elseif($submission_type == "multiple"){
                    $submissions = $phones;
                    $msg    = $message;
                    View::variables_handler("sms",0,[],$msg);
                    $send   = $sms->body($msg,false,false);
                    $send   = $send->addNumber($phones)->submit();
                    if($send){
                        LogManager::Sms_Log(0,"bulk-sms",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    }else
                        $submissions = [];
                }

                if(!$submissions)
                    return Bootstrap::$lang->get_cm("admin/tools/error5",false,$locall)." -> ".$sms->getError();

            }
            else
            {
                if($cc) foreach($cc AS $c) if(!isset($emails[$c]))  $emails[$c] = NULL;
                if($newsletter) foreach($newsletter AS $n) if(!isset($emails[$n]))  $emails[$n] = NULL;

                if(!$emails) return Bootstrap::$lang->get_cm("admin/tools/error1",false,$locall);

                if($submission_type == "single"){
                    foreach($emails AS $address=>$name){
                        $parse  = explode("|",$name);
                        $name   = $parse[0];
                        $uid    = isset($parse[1]) ? $parse[1] : 0;
                        $lang   = isset($parse[2]) ? $parse[2] : $locall;
                        $msg    = $message;
                        View::variables_handler("mail",$uid,[
                            'newsletter_unsubscribe_link' => Controllers::$init->CRLink("newsletter/unsubscribe",false,"none")."?lang=auto&email=".$address,
                        ],$msg,$lang);
                        $send   = $mail->body($msg,false,false,$lang,$uid)->subject($subject);
                        $send   = $send->addAddress($address,$name)->submit();
                        if($send){
                            $submissions[] = $address;
                            LogManager::Mail_Log(0,"bulk-mail",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                        }
                    }
                }
                elseif($submission_type == "multiple")
                {
                    $concats = [];
                    foreach($emails AS $address=>$name){
                        $parse  = explode("|",$name);
                        $name   = $parse[0];
                        $concats[$address] = $name;
                        $submissions[] = $address;
                    }
                    $msg    = $message;
                    View::variables_handler("mail",0,[
                        'newsletter_unsubscribe_link' => Controllers::$init->CRLink("newsletter/unsubscribe",false,"none")."?lang=auto",
                    ],$msg);
                    $mail->body($msg)->subject($subject)->addAddress($concats);
                    if($mail->submit())
                        LogManager::Mail_Log(0,"bulk-mail",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else
                        $submissions = [];
                }

                if(!$submissions)
                    return Bootstrap::$lang->get_cm("admin/tools/error5",false,$locall)." -> ".$mail->error;
            }

            return "OK";
        }
        static function approved_gdpr_request($gdpr_request_id=0)
        {
            Helper::Load(["User","Money"]);

            if(is_array($gdpr_request_id))
                $gdpr_request = $gdpr_request_id;
            else
            {
                $gdpr_request   = Models::$init->db->select()->from("users_gdpr_requests")->where("id","=",$gdpr_request_id);
                $gdpr_request   = $gdpr_request->build() ? $gdpr_request->getAssoc() : false;
            }

            if(!$gdpr_request) return false;

            $uid        = $gdpr_request["user_id"];


            $udata  = User::getData($uid,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];


            $settings       = Config::get("notifications/user/approved-gdpr-request");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            $timezone   = User::getLastLoginZone('member',$udata["id"]);

            $created_at = UserManager::formatTimeZone($gdpr_request["created_at"],$timezone,Config::get("options/date-format")." - H:i");

            $variables = [
                'type' => $gdpr_request["type"] == "remove" ? Bootstrap::$lang->get_cm("website/account_info/gdpr-tx14",false,$ulang) : Bootstrap::$lang->get_cm("website/account_info/gdpr-tx15",false,$ulang),
                'created_at' => $created_at,
                'note'       => $gdpr_request["status_note"],
                'status'     => $gdpr_request["status"] == "approved" ? Bootstrap::$lang->get_cm("admin/orders/subscription-status-approved",false,$ulang) : Bootstrap::$lang->get_cm("admin/orders/subscription-status-cancelled",false,$ulang),
            ];


            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "user/approved-gdpr-request",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"approved-gdpr-request",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "user/approved-gdpr-request",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"approved-gdpr-request",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "user/approved-gdpr-request", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"approved-gdpr-request",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "user/approved-gdpr-request", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"approved-gdpr-request",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user",
                'name'          => "approved-gdpr-request",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function declined_gdpr_request($gdpr_request_id=0)
        {
            Helper::Load(["User","Money"]);

            if(is_array($gdpr_request_id))
                $gdpr_request = $gdpr_request_id;
            else
            {
                $gdpr_request   = Models::$init->db->select()->from("users_gdpr_requests")->where("id","=",$gdpr_request_id);
                $gdpr_request   = $gdpr_request->build() ? $gdpr_request->getAssoc() : false;
            }

            if(!$gdpr_request) return false;

            $uid        = $gdpr_request["user_id"];


            $udata  = User::getData($uid,"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
            $ulang  = $udata["lang"];


            $settings       = Config::get("notifications/user/declined-gdpr-request");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            $timezone   = User::getLastLoginZone('member',$udata["id"]);

            $created_at = UserManager::formatTimeZone($gdpr_request["created_at"],$timezone,Config::get("options/date-format")." - H:i");

            $variables = [
                'type' => $gdpr_request["type"] == "remove" ? Bootstrap::$lang->get_cm("website/account_info/gdpr-tx14",false,$ulang) : Bootstrap::$lang->get_cm("website/account_info/gdpr-tx15",false,$ulang),
                'created_at' => $created_at,
                'note'       => $gdpr_request["status_note"],
                'status'     => $gdpr_request["status"] == "declined" ? Bootstrap::$lang->get_cm("admin/orders/subscription-status-declined",false,$ulang) : Bootstrap::$lang->get_cm("admin/orders/subscription-status-cancelled",false,$ulang),
            ];


            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "user/declined-gdpr-request",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"declined-gdpr-request",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "user/declined-gdpr-request",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"declined-gdpr-request",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "user/declined-gdpr-request", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"declined-gdpr-request",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "user/declined-gdpr-request", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"declined-gdpr-request",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "user",
                'name'          => "declined-gdpr-request",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function user_notification($data=[]){
            if(!class_exists("Events")) Helper::Load(["Events"]);
            return Events::create($data);
        }

        private static function invoice_calculate($invoice=[]){
            $result         = [];

            $discounts      = isset($invoice["discounts"]) && $invoice["discounts"] ? $invoice["discounts"] : [];
            if($discounts && !is_array($discounts)) $discounts = Utility::jdecode($discounts,true);


            if($invoice["status"] == "unpaid"){
                $invoice_calculate = Invoices::calculate_invoice($invoice,Invoices::get_items($invoice["id"]),['discount_total' => true]);
                $result["data"]["subtotal"] = $invoice_calculate["subtotal"];
                $result["data"]["tax"] = $invoice_calculate["tax"];
                $result["data"]["total"] = $invoice_calculate["total"];
            }
            else
                $result["data"]["subtotal"] = $invoice["subtotal"];

            if($discounts){
                $total_discount_amount      = 0;
                $discount_items             = $discounts["items"];

                if(isset($discount_items["coupon"]) && $discount_items["coupon"]){
                    foreach($discount_items["coupon"] AS $item){
                        $name   = $item["name"]." - ".$item["dvalue"];
                        $total_discount_amount += $item["amountd"];
                        $result["discounts"][] = [
                            'name'      => $name,
                            'amount'    => $item["amount"],
                        ];
                    }
                }
                if(isset($discount_items["promotion"]) && $discount_items["promotion"]){
                    foreach($discount_items["promotion"] AS $item){
                        $name   = $item["name"]." - ".$item["dvalue"];
                        $total_discount_amount += $item["amountd"];
                        $result["discounts"][] = [
                            'name'      => $name,
                            'amount'    => $item["amount"],
                        ];
                    }
                }
                if(isset($discount_items["dealership"]) && $discount_items["dealership"]){
                    foreach($discount_items["dealership"] AS $item){
                        $name   = $item["name"]." - %".$item["rate"];
                        $total_discount_amount += $item["amountd"];

                        $result["discounts"][] = [
                            'name'      => $name,
                            'amount'    => $item["amount"],
                        ];
                    }
                }
                $result["data"]["total_discount_amount"] = $total_discount_amount;
            }

            return $result;
        }
        private static function invoice_items($invoice=[],$items=[],$discounts=[],$type='html'){

            $changeTemplateInvoiceItems = Hook::run("changeTemplateInvoiceItems",$invoice,$items,$discounts,$type);
            if($changeTemplateInvoiceItems) foreach($changeTemplateInvoiceItems AS $res) return $res;

            $data       = [];
            $item_size  = sizeof($items);
            foreach($items AS $item){
                $cid    = isset($item["cid"]) ? $item["cid"] : $item["currency"];
                $desc   = nl2br($item["description"]);
                if($type == "html"){
                    $desc .= ' <strong>' . Money::formatter_symbol($item["total_amount"], $cid) . '</strong>';
                    $data[] = $desc;
                }else{
                    $desc .= ' '.Money::formatter_symbol($item["total_amount"],$cid);
                    $data[] = Filter::nl2br_reverse($desc);
                }
            }
            if($discounts){
                foreach($discounts AS $item){
                    $desc   = $item["name"];
                    if($type == "html"){
                        $desc .= '  <strong>-'.$item["amount"].'</strong>';
                        $data[] = $desc;
                    }
                    else{
                        $desc .= ' '.$item["amount"];
                        $data[] = $desc;
                    }
                }
            }
            if($data) $data = $type == "html" ? implode("<br>",$data) : implode("\n",$data);
            if(isset($invoice["sendbta"]) && $invoice["sendbta"] && $invoice["sendbta_amount"]){
                $u_lang = $invoice["user_data"]["lang"];
                if($type == "html"){
                    $data .= "<br>";
                    $data .= Bootstrap::$lang->get_cm("website/account_invoices/sendbta",false,$u_lang);
                    $data .= ' <strong>' . Money::formatter_symbol($invoice["sendbta_amount"], $invoice["currency"]).'</strong>';
                }else{
                    $data .= "\n";
                    $data .= Bootstrap::$lang->get_cm("website/account_invoices/sendbta",false,$u_lang);
                    $data .= ' ' . Money::formatter_symbol($invoice["sendbta_amount"], $invoice["currency"]) . '';
                }
            }
            return $data;
        }
        private static function order_name($order=[]){
            return Orders::detail_name($order);
        }

        static function send($params=[]){
            Helper::Load("User");
            $key            = $params["template"] ?? '';
            $name_d         = '';
            $body           = $params["body"] ?? NULL;
            $subject        = $params["subject"] ?? NULL;

            $settings       = [];

            if($key)
            {
                $notifications  = Config::get("notifications");
                if($notifications)
                {
                    foreach($notifications AS $k => $v)
                    {
                        if(isset($v[$key]))
                        {
                            $settings = $v[$key] ?? [];
                            $name_d = $k."/".$key;
                        }
                    }
                }

                if(!$settings || !$settings["status"])
                    return [
                        'status'    => "error",
                        'message'   => "Status of the template is inactive",
                    ];
            }
            else
                $settings = ['user-mail' => true,'user-sms' => true];

            $contacts       = $key ? self::admin_contact_handler(__FUNCTION__,$settings) : [];

            $emails         = $contacts["emails"] ?? [];
            $phones         = $contacts["phones"] ?? [];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;

            $variables = $params["variables"] ?? [];

            $mail_variables = $variables;

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body($body, $name_d,$mail_variables,$lang);
                    if($subject) $mail->subject($subject);
                    if($params["attachments"] ?? [])
                        foreach($params["attachments"] AS $filename => $fname)
                            $send->addAttachment($filename,$fname);

                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,$key,$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body($body, $name_d, $variables,$lang);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,$key,$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            $uid    = $params["user_id"] ?? 0;

            if($uid > 0)
            {
                $udata  = User::getData($uid,"id,email,lang,country,full_name,balance,balance_min,balance_currency","array");
                if($udata)
                {
                    $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));
                    $ulang  = $udata["lang"];

                    if($mail && $settings["user-mail"]){
                        $send   = $mail->body($body, $name_d,$variables,$ulang,$udata["id"]);
                        if($subject) $mail->subject($subject);
                        if($params["attachments"] ?? [])
                            foreach($params["attachments"] AS $filename => $fname)
                                $send->addAttachment($filename,$fname);
                        $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                        if($send) LogManager::Mail_Log($udata["id"],$key,$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                        else $errors["mail"][$udata["email"]] = $mail->error;
                    }

                    if($sms && $settings["user-sms"] && $udata["phone"]){
                        $send   = $sms->body($body, $name_d, $variables,$ulang,$udata["id"]);
                        $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                        $send = $send->submit();
                        if($send) LogManager::Sms_Log($udata["id"],$key,$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                        else $errors["sms"][$udata["phone"]] = $sms->getError();
                    }
                }
            }
            else
            {
                $ulang = $params["lang"] ?? Bootstrap::$lang->clang;

                if($mail && $settings["user-mail"] && strlen($params["email"] ?? '')>1)
                {
                    $send   = $mail->body($body, $name_d,$mail_variables,$ulang);
                    if($subject) $mail->subject($subject);
                    if($params["attachments"] ?? [])
                        foreach($params["attachments"] AS $filename => $fname)
                            $send->addAttachment($filename,$fname);
                    $send   = $send->addAddress($params["email"],$params["name"] ?? '')->submit();
                    if($send) LogManager::Mail_Log($params["set_user_id"] ?? 0,$key,$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$params["email"]] = $mail->error;
                }

                if($sms && $settings["user-sms"] && strlen($params["phone"] ?? '')>1){
                    $send   = $sms->body($body,$name_d, $variables,$ulang);
                    $send->addNumber($params["phone"]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,$key,$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$params["phone"]] = $sms->getError();
                }
            }


            if($uid > 0)
                self::user_notification([
                    'user_id' => $uid,
                    'type' => "notification",
                    'name' => $key,
                    'data' => $variables,
                ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("SendNotification",[
                'params'         => $params,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
            ]);

            return $errors ? ['status' => "error",'errors' => $errors] : ['status' => "successful"];
        }
        static function domain_upcoming_renewal_notice($order=[]){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/domain/domain-upcoming-renewal-notice");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
            if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
            else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);

            $detail_link                = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"]);
            $upcoming_day               = DateManager::diff_day(DateManager::format("Y-m-d",$order["duedate"]),DateManager::Now("Y-m-d"));


            $variables = [
                'order_name'            => self::order_name($order),
                'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                'order_date_created'    => $date_start,
                'order_date_start'      => $date_start,
                'order_date_end'        => $date_end,
                'order_detail_link'     => $detail_link,
                'domain'                => $order["options"]["domain"],
                'day'                   => $upcoming_day,
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "domain/domain-upcoming-renewal-notice",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"domain-upcoming-renewal-notice",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "domain/domain-upcoming-renewal-notice",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"domain-upcoming-renewal-notice",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "domain/domain-upcoming-renewal-notice", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"domain-upcoming-renewal-notice",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "domain/domain-upcoming-renewal-notice", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"domain-upcoming-renewal-notice",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "order",
                'owner_id' => $order["id"],
                'name' => "domain-upcoming-renewal-notice",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "domain",
                'name'          => "domain-upcoming-renewal-notice",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }
        static function domain_expired_notice($order=[]){
            Helper::Load(["Orders","User","Money"]);
            if(!is_array($order)) $order = Orders::get($order);

            $settings       = Config::get("notifications/domain/domain-expired-notice");
            if(!$settings["status"]) return false;

            $contacts       = self::admin_contact_handler(__FUNCTION__,$settings);

            $emails         = $contacts["emails"];
            $phones         = $contacts["phones"];

            $errors         = [];

            Modules::Load("Mail");
            $mail_module  = Config::get("modules/mail");
            $mail         = $mail_module && $mail_module != "none" ? new $mail_module() : false;

            Modules::Load("SMS");
            $sms_module  = Config::get("modules/sms");
            $sms         = $sms_module && $sms_module != "none" ? new $sms_module() : false;


            $udata  = User::getData($order["owner_id"],"id,email,lang,country,full_name","array");
            $udata  = array_merge($udata,User::getInfo($udata["id"],["phone","gsm","gsm_cc"]));

            $date_start                 = DateManager::format(Config::get("options/date-format"),$order["cdate"]);
            if(substr($order["duedate"],0,4) == "1881") $date_end = Bootstrap::$lang->get("needs/none",$udata["lang"]);
            else $date_end              = DateManager::format(Config::get("options/date-format"),$order["duedate"]);

            $detail_link                = Controllers::$init->CRLink("ac-ps-product",[$order["id"]],$udata["lang"]);
            $upcoming_day               = DateManager::diff_day(DateManager::format("Y-m-d",$order["duedate"]),DateManager::Now("Y-m-d"));
            if($upcoming_day < 0) $upcoming_day = abs($upcoming_day);



            $variables = [
                'order_name'            => self::order_name($order),
                'order_amount'          => Money::formatter_symbol($order["amount"],$order["amount_cid"]),
                'order_date_created'    => $date_start,
                'order_date_start'      => $date_start,
                'order_date_end'        => $date_end,
                'order_detail_link'     => $detail_link,
                'domain'                => $order["options"]["domain"],
                'day'                   => $upcoming_day,
            ];

            $locall     = Config::get("general/local");

            if($mail && $emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;
                    $send   = $mail->body(null, "domain/domain-expired-notice",$variables,$lang,$udata["id"]);
                    $send   = $send->addAddress($address,$name)->submit();
                    if($send) LogManager::Mail_Log(0,"domain-expired-notice",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                    else $errors["mail"][$address] = $mail->error;
                }
            }

            if($mail && $settings["user-mail"]){
                $send   = $mail->body(null, "domain/domain-expired-notice",$variables,$udata["lang"],$udata["id"]);
                $send   = $send->addAddress($udata["email"],$udata["full_name"])->submit();
                if($send) LogManager::Mail_Log($udata["id"],"domain-expired-notice",$mail->getSubject(),$mail->getBody(),implode(",",$mail->getAddresses()));
                else $errors["mail"][$udata["email"]] = $mail->error;
            }

            if($sms && $phones){
                foreach($phones AS $phone){
                    $parse  = explode("|",$phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;
                    $send   = $sms->body(null, "domain/domain-expired-notice", $variables,$lang,$udata["id"]);
                    if(isset($parse[1])) $send->addNumber($parse[0],$parse[1]);
                    else $send->addNumber($parse[0]);
                    $send = $send->submit();
                    if($send) LogManager::Sms_Log(0,"domain-expired-notice",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                    else $errors["sms"][$phone] = $sms->getError();
                }
            }

            if($sms && $settings["user-sms"] && $udata["phone"]){
                $send   = $sms->body(null, "domain/domain-expired-notice", $variables,$udata["lang"],$udata["id"]);
                $send->addNumber($udata["gsm"],$udata["gsm_cc"]);
                $send = $send->submit();
                if($send) LogManager::Sms_Log($udata["id"],"domain-expired-notice",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));
                else $errors["sms"][$udata["phone"]] = $sms->getError();
            }

            self::user_notification([
                'user_id' => $udata["id"],
                'type' => "notification",
                'owner' => "order",
                'owner_id' => $order["id"],
                'name' => "domain-expired-notice",
            ]);

            $admin_emails       = [];
            $admin_phones       = [];

            if($emails){
                foreach($emails AS $address=>$name){
                    $parse  = explode("|",$name);
                    $name   = $parse[0];
                    $lang   = isset($parse[1]) ? $parse[1] : $locall;

                    $admin_emails[] = [
                        'address'       => $address,
                        'name'          => $name,
                        'lang'          => $lang,
                    ];
                }
            }

            if($phones){
                foreach($phones AS $phone){
                    $parse = explode("|", $phone);
                    $lang = isset($parse[2]) ? $parse[2] : $locall;

                    $admin_phones[] = [
                        'gsm'           => $parse[0],
                        'gsm_cc'        => isset($parse[1]) ? $parse[1] : '',
                        'lang'          => $lang,
                    ];
                }
            }

            Hook::run("Notified",[
                'group'         => "domain",
                'name'          => "domain-expired-notice",
                'user_data'     => $udata,
                'variables'     => $variables,
                'admin_emails'  => $admin_emails,
                'admin_phones'  => $admin_phones,
                'errors'        => $errors,
            ]);

            return $errors ? $errors : "OK";
        }

    }