<?php
    defined('CORE_FOLDER') OR exit('You can not get in here!');

    Class Invoices
    {

        static $message;
        static $added = [];
        static $temp = [];

        static function action_source(){
            if(defined('CRON') && CRON)
                $action_source = 'automation';
            elseif(defined('ADMINISTRATOR') && ADMINISTRATOR)
                $action_source = 'admin';
            elseif(isset($_SERVER["REQUEST_URI"]) && stristr($_SERVER["REQUEST_URI"],"/api/"))
                $action_source = 'api';
            else
                $action_source = 'client';
            return $action_source;
        }

        static function create_pdf($invoice=0)
        {

            $data = [];
            Helper::Load(["Money","Orders","Products"]);

            Controllers::$init->takeDatas("language");

            if(is_array($invoice)) $invoice_id = $invoice["id"];
            else
            {
                $invoice_id     = $invoice;
                $invoice        = self::get($invoice);
            }

            $time       = time();
            $pdf_file   = ROOT_DIR."temp".DS."invoice-".$invoice_id."-".$time.".pdf";
            $pdf_link   = Utility::link_determiner("temp/invoice-".$invoice_id."-".$time.".pdf",false,false);


            $items          = self::item_listing($invoice);

            $udata      = $invoice["user_data"];

            if($invoice["status"] == "unpaid"){
                $udata      = array_merge($udata,User::getData($udata["id"],"lang,balance_currency,balance_min,balance,full_name","array"));
                $udata      = array_merge($udata,User::getInfo($udata["id"],["dealership"]));
            }

            $data["invoice"]    = $invoice;
            $data["items"]      =  $items;
            $data["udata"]      = $udata;


            Bootstrap::$lang->clang = $udata["lang"];


            $tax_rates          = [];
            $total_tax_rates    = 0;
            $allRs              = Config::get("options/tax-rates-names/".$invoice["user_data"]["address"]["country_id"]);
            $city_id            = $invoice["user_data"]["address"]["city_id"] ?? 0;

            if(isset($allRs[$city_id]) && $allRs[$city_id])
            {
                foreach($allRs[$city_id] AS $r)
                {
                    if(strlen($r['name']) > 1 && $r["value"] > 0.00)
                    {
                        $tax_rates[] = $r["name"]." %".$r["value"];
                        $total_tax_rates += $r["value"];
                    }
                }
            }

            if(isset($allRs[0]) && $allRs[0])
            {
                foreach($allRs[0] AS $r)
                {
                    if(strlen($r['name']) > 1 && $r["value"] > 0.00)
                    {
                        $tax_rates[] = $r["name"]." %".$r["value"];
                        $total_tax_rates += $r["value"];
                    }
                }
            }

            $size_tax_rates = sizeof($tax_rates);
            if($size_tax_rates > 0)
                $tax_rates = '('.implode(' + ',$tax_rates).') ';
            else
                $tax_rates = '';

            if($total_tax_rates != $invoice["taxrate"])
                $tax_rates = '';

            $data["tax_rates"] = $tax_rates;

            $stmt   = Models::$init->db->select()->from("users_custom_fields");
            $stmt->where("status","=","active","&&");
            $stmt->where("invoice","=","1","&&");
            $stmt->where("lang","=",$udata["lang"]);
            $stmt->order_by("rank ASC");
            $custom_fields = $stmt->build() ? $stmt->fetch_assoc() : false;

            $user_custom_fields     = [];

            if($custom_fields)
            {
                foreach($custom_fields AS $field)
                {
                    $save_value         = false;
                    $save_data = User::getInfo($udata["id"],['field_'.$field["id"]]);
                    if(isset($save_data["field_".$field["id"]]) && Utility::strlen($save_data["field_".$field["id"]])>0)
                        $save_value = $save_data["field_".$field["id"]];
                    if($save_value)
                        $user_custom_fields[$field["id"]] = [
                            'name'      => $field["name"],
                            'value'     => $save_value,
                        ];
                }
            }
            $data["custom_fields"] = $user_custom_fields;

            if(!($invoice["pmethod"] == "BankTransfer" || $invoice["pmethod"] == "Balance")){
                $case           = "CASE WHEN status='paid' THEN 0 ELSE 1 END AS rank";
                $transID        = Models::$init->db->select("id,data,type,".$case)->from("checkouts");
                $transID->where("JSON_CONTAINS(data, '". ('"'.$invoice["id"].'"') ."','$.invoices')","","","||");
                $transID->where("JSON_CONTAINS(data, '". $invoice["id"] ."','$.invoices')","","","||");
                $transID->where("JSON_UNQUOTE(JSON_EXTRACT(data,'$.invoice_id'))","=",$invoice["id"]);
                $transID->order_by("rank ASC,cdate DESC");
                $transID->limit(1);
                $transID    = $transID->build() ? $transID->getObject() : false;
                $transData  = false;
                $payment_bulk = false;
                if($transID)
                {
                    $transData    = Utility::jdecode($transID->data,true);
                    $payment_bulk = $transID->type == "invoice-bulk-payment";
                    $transID      = $transID->id;
                    if(isset($transData["pmethod_stored_card"]) && $transData["pmethod_stored_card"])
                    {
                        $stored_card     = Models::$init->db->select("ln4")->from("users_stored_cards")->where("id","=",$transData["pmethod_stored_card"]);
                        $stored_card_ln4     = $stored_card->build() ? $stored_card->getObject()->ln4 : 0;
                        if($stored_card_ln4)
                            $data["stored_card_ln4"] = $stored_card_ln4;
                        $data["is_auto_pay"] = isset($transData["pmethod_by_auto_pay"]) && $transData["pmethod_by_auto_pay"] ? 2 : 1;
                    }
                }
                $data["payment_transaction_id"] = $transID;
                $data["payment_transaction"] = $transData;
                $data["payment_bulk"] = $payment_bulk;
            }


            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P','A4','en', true, 'UTF-8',['mL', 'mT', 'mR', 'mB']);

            Hook::run("SetPdfFont",$html2pdf,$invoice);

            try
            {
                $output = View::$init->chose("website")->render("ac-detail-invoice-pdf",$data,true);

                $currencies = Money::getCurrencies();
                foreach($currencies AS $row){
                    if(($row["prefix"] && substr($row["prefix"],-1,1) == ' ') || ($row["suffix"] && substr($row["suffix"],0,1) == ' '))
                        $code = $row["code"];
                    else
                        $code = $row["prefix"] ? $row["code"].' ' : ' '.$row["code"];

                    $convert_if     = !in_array($row["code"],['USD','EUR','GBP']);

                    if(stristr($row["prefix"],'$')) $convert_if = false;
                    if(stristr($row["suffix"],'$')) $convert_if = false;

                    $row["prefix"] = Utility::text_replace($row["prefix"],[' ' => '']);
                    $row["suffix"] = Utility::text_replace($row["suffix"],[' ' => '']);
                    if(!Validation::isEmpty($row["prefix"]) && $row["prefix"] && $convert_if && !preg_match('/[A-Za-z]/',$row["prefix"]))
                        $output = Utility::text_replace($output,[$row["prefix"] => $code]);
                    elseif(!Validation::isEmpty($row["suffix"]) && $row["suffix"] && $convert_if && !preg_match('/[A-Za-z]/',$row["suffix"]))
                        $output = Utility::text_replace($output,[$row["suffix"] => $row["code"]]);
                }

                $html2pdf->writeHTML($output);
                $output = $html2pdf->output('invoice-'.$invoice_id.".pdf","S");
            }
            catch(Exception $e)
            {
                self::$message = $e->getMessage();
                return false;
            }

            FileManager::file_write($pdf_file,$output);

            return [
                'url' => $pdf_link,
                'path' => $pdf_file,
            ];
        }

        static function getTaxationType(){
            $taxation_type = Config::get("options/taxation-type");
            if(!$taxation_type) $taxation_type = "exclusive";
            if(!self::getTaxation()) $taxation_type = "exclusive";
            return $taxation_type;
        }

        static function getTaxation($country=0,$u_taxation=NULL){
            if(!Config::get("options/taxation")) return false;
            if($u_taxation !== NULL && $u_taxation != 1) return false;
            return true;
        }

        static function isLocal($country=0,$u_id=0){
            $status         = false;
            $local_cid      = AddressManager::LocalCountryID();
            if(!$country || $country == $local_cid) $status = true;
            elseif(self::getTaxRate($country,0,$u_id)) $status = true;

            return $status;
        }

        static function eu_isLocal($local_cid,$country=0){
            $countries = [
                14,21,34,54,57,58,60,69,74,75,82,85,100,106,109,122,128,129,137,156,176,177,180,201,202,208,214,234
            ];
            return in_array($local_cid,$countries) && in_array($country,$countries);
        }

        static function getTaxRate($country=0,$city=0,$u_id=0){
            $rate = 0;
            if(!self::getTaxation($country)) return $rate;
            $loc_cid        = AddressManager::LocalCountryID();
            $local_rate     = Config::get("options/tax-rate");
            $country_rates  = Config::get("options/country-tax-rates");
            $city_rates     = Config::get("options/city-tax-rates/".$country);

            if(!$country || $country == $loc_cid) $rate = $local_rate;
            elseif($country && isset($country_rates[$country])){
                $get_rate = $country_rates[$country];
                if(gettype($get_rate) != "string") $rate = $get_rate;
            }

            if($city && !Validation::isInt($city)) $city = AddressManager::getCityID($country,$city);
            if($city && isset($city_rates[$city])){
                $get_rate = $city_rates[$city];
                if(gettype($get_rate) != "string") $rate = $get_rate;
            }

            if($rate === '') $rate = 0;
            return $rate;
        }

        static function MakeOperation($type='',$id=[],$notification=false,$via_module=0){
            $action_source = self::action_source();

            if(!$id) return false;
            if(is_array($id)) $invoice = $id;
            else $invoice = self::get($id);

            if($type == "paid")
            {
                $hooks = Hook::run("PreInvoicePaid",$invoice);
                if($hooks)
                {
                    foreach($hooks AS $hook)
                    {
                        if(is_array($hook) && isset($hook["status"]) && $hook["status"] == "error")
                        {
                            self::$message = $hook["message"] ?? 'N/A';
                            return false;
                        }
                    }
                }
            }

            if($type == "taxed" && $invoice["taxed"]) return true;

            if($type == "refund" && $via_module)
            {
                $p_name     = isset($invoice["pmethod"]) ? $invoice["pmethod"] : 'none';
                if($p_name && $p_name != "none" && $p_name != "BankTransfer" && $p_name != "Balance")
                {
                    $case           = "CASE WHEN status='paid' THEN 0 ELSE 1 END AS rank";
                    $transID        = Models::$init->db->select("id,".$case)->from("checkouts");
                    $transID->where("JSON_UNQUOTE(JSON_EXTRACT(data,'$.invoice_id'))","LIKE",$invoice["id"]);
                    $transID->order_by("rank ASC,cdate DESC");
                    $transID->limit(1);
                    $transID    = $transID->build() ? $transID->getObject()->id : 0;

                    Modules::Load("Payment",$p_name);
                    if(class_exists($p_name))
                    {
                        $p_module = new $p_name();
                        if(method_exists($p_module,'refund') || method_exists($p_module,'refundInvoice'))
                        {
                            if(method_exists($p_module,'refund') && $transID)
                            {
                                Helper::Load("Basket");
                                $checkout           = Basket::get_checkout($transID,0,false,'paid');
                                if($checkout)
                                {
                                    $refund_via_module = $p_module->refund($checkout);
                                    if(!$refund_via_module)
                                    {
                                        self::$message = $p_module->error;
                                        return false;
                                    }
                                }
                            }
                            elseif(method_exists($p_module,'refundInvoice'))
                            {
                                $refund_via_module = $p_module->refundInvoice($invoice);
                                if(!$refund_via_module)
                                {
                                    self::$message = $p_module->error;
                                    return false;
                                }
                            }
                        }
                    }
                }
            }


            if($type == "delete" || $type == "unpaid"){
                $getInex    = self::get_inex(false,$invoice["id"]);
                if($getInex) self::delete_inex($getInex["id"]);
            }
            elseif($type == "paid"){
                Helper::Load(["Money","Invoices","Orders","Products"]);
                $ordInvoice = $invoice;
                $ordInvoice["status"] = "paid";
                $items      = self::get_items($invoice["id"]);
                foreach($items AS $item){
                    if($item["user_pid"])
                    {
                        if($invoice["pmethod"] == "BankTransfer")
                        {
                            if(in_array($item["options"]["event"],['DomainNameRegisterOrder','DomainNameTransferRegisterOrder']))
                            {
                                Orders::set($item["user_pid"],[
                                    'cdate'             => DateManager::Now(),
                                    'renewaldate'       => DateManager::Now()
                                ]);
                            }
                        }
                        Orders::MakeOperation("approve",$item["user_pid"]);
                    }
                    Orders::process($item,$ordInvoice);
                }


                if(isset($invoice["discounts"]) && $invoice["discounts"]){
                    if(isset($invoice["discounts"]["used_coupons"]) && $invoice["discounts"]["used_coupons"])
                        foreach($invoice["discounts"]["used_coupons"] AS $coup) self::reduce_coupon($coup);

                    if(isset($invoice["discounts"]["used_promotions"]) && $invoice["discounts"]["used_promotions"])
                        foreach($invoice["discounts"]["used_promotions"] AS $promo) self::reduce_promotion($promo);
                }

                Orders::module_process($notification);
                Orders::notification_process($notification);

                Hook::run("InvoicePayment",$invoice);
            }
            elseif($type == "refund" || $type == "cancelled"){
                $getInex    = self::get_inex(false,$invoice["id"]);
                if($getInex) self::delete_inex($getInex["id"]);
            }

            if($type == "delete") return self::delete($invoice);
            else{
                if($type == $invoice["status"]) return true;
                $status_data = [];
                if($type == "taxed") $status_data["taxed"] = 1;
                else $status_data["status"] = $type;

                if($type == "paid" || $type == "cancelled" || $type == "taxed" || $type == "refund") $status_data["unread"] = 1;

                if($type == "paid" && $invoice["status"] == "waiting"){
                    $status_data["datepaid"] = DateManager::Now();
                }
                elseif($type == "cancelled" && $invoice["total"]<1){
                    $tax = $invoice["legal"] ? Money::get_tax_amount($invoice["subtotal"],$invoice["taxrate"]) : 0;
                    $status_data["tax"]      = $tax;
                    $status_data["total"]    = $invoice["subtotal"] + $status_data["tax"];
                }

                if($type == "paid" && $invoice["status"] != "paid")
                {
                    $invoice_id_fake    = Config::getd("invoice-id-paid");
                    $ap_id              = $invoice["id"];
                    $i_n_f              = Config::get("options/paid-invoice-num-format");
                    $i_n_f_s            = Config::get("options/paid-invoice-num-format-status");
                    if(!$i_n_f_s) $i_n_f = false;
                    if(!$i_n_f && strlen($invoice["number"]) < 2) $i_n_f = '#{NUMBER}';

                    if($i_n_f_s)
                    {
                        if(strlen($invoice_id_fake) > 0) $ap_id = $invoice_id_fake;

                        $i_n_f = str_replace(
                            [
                                "{NUMBER}",
                                "{DAY}",
                                "{MONTH}",
                                "{YEAR}"
                            ],
                            [
                                $ap_id,
                                DateManager::Now("d"),
                                DateManager::Now("m"),
                                DateManager::Now("Y")
                            ],$i_n_f);
                        $status_data["number"] = $i_n_f;
                        Config::setd("invoice-id-paid",($ap_id + 1));
                    }
                }

                if($type == "taxed")
                {
                    $invoice_vars = $invoice;
                    $invoice_vars['source'] = $action_source;

                    $formalizeHook = Hook::run("formalizeInvoice",$invoice_vars);

                    if($formalizeHook)
                    {
                        foreach($formalizeHook AS $r)
                        {
                            if($r && is_array($r) && isset($r["error"]))
                            {
                                self::$message = $r["error"];
                                return false;
                            }
                        }
                    }
                }

                $change =  self::set($invoice["id"],$status_data);
                if($change){
                    $invoice = self::get($invoice["id"]);

                    if($type == "paid" && ($invoice["pmethod"] != "Balance" || ($invoice["pmethod"] == "Balance" && Config::get("options/balance-taxation") == "n"))){
                        $getInex    = self::get_inex(false,$invoice["id"]);
                        if(!$getInex && $invoice["total"]>0)
                        {
                            self::insert_inex([
                                'invoice_id' => $invoice["id"],
                                'type' => "income",
                                'amount' => $invoice["total"]>0 ? $invoice["total"] : $invoice["subtotal"],
                                'currency' => $invoice["currency"],
                                'cdate' => substr($invoice["datepaid"],0,4) > 2000 ? $invoice["datepaid"] : DateManager::Now(),
                                'description' => Bootstrap::$lang->get("needs/invoice",Config::get("general/local"))." ".$invoice["number"],
                            ]);
                        }
                    }

                    if($notification){
                        Helper::Load(["Notification"]);
                        if($type == "taxed" && $invoice["taxed_file"]) Notification::invoice_has_been_taxed($invoice);
                        elseif($type == "paid") Notification::invoice_has_been_approved($invoice);
                        elseif($type == "unpaid") Notification::invoice_created($invoice);
                        elseif($type == "refund") Notification::invoice_returned($invoice);
                        elseif($type == "cancelled") Notification::invoice_cancelled($invoice);
                    }

                    $invoice_vars = $invoice;
                    $invoice_vars['source'] = $action_source;

                    if($type == "taxed")
                        Hook::run("InvoiceFormalized",$invoice_vars);
                    elseif($type == "paid"){
                        Hook::run("payInvoice",$invoice_vars);
                        Hook::run("InvoicePaid",$invoice_vars);
                    }
                    elseif($type == "unpaid"){
                        Hook::run("InvoiceUnpaid",$invoice_vars);
                    }
                    elseif($type == "refund"){
                        Hook::run("refundInvoice",$invoice_vars);
                        Hook::run("InvoiceRefunded",$invoice_vars);
                    }
                    elseif($type == "cancelled"){
                        Hook::run("cancelInvoice",$invoice_vars);
                        Hook::run("InvoiceCancelled",$invoice_vars);
                    }

                    return true;
                }
                else return false;
            }
        }

        static function get_inex($id=0,$invoice_id=0){
            $stmt   = Models::$init->db->select()->from("income_expense");
            if($invoice_id) $stmt->where("invoice_id","=",$invoice_id);
            else $stmt->where("id","=",$id);
            return $stmt->build() ? $stmt->getAssoc() : false;
        }

        static function set_inex($id=0,$data=[]){
            return Models::$init->db->update("income_expense",$data)->where("id","=",$id)->save();
        }

        static function insert_inex($data=[]){
            return Models::$init->db->insert("income_expense",$data) ? Models::$init->db->lastID() : false;
        }

        static function delete_inex($id=0){
            return Models::$init->db->delete("income_expense")->where("id","=",$id)->run();
        }

        static function cancelled_order($id=0,$order=[]){
            if(!Config::get("options/delete-invoice-item-aoc")) return true;
            $invoice            = is_array($id) ? $id : self::get($id);
            $order              = is_array($order) ? $order : Orders::get($order);
            $items              = self::get_items($invoice["id"]);
            $invoice_cancel     = false;
            if(sizeof($items) == 1) $invoice_cancel = true;
            if($invoice_cancel) return self::MakeOperation("cancelled",$invoice,true);
            $found_items        = [];
            foreach($items AS $item) if($item["user_pid"] == $order["id"]) $found_items[] = $item;
            if(!$found_items) return false;

            if(sizeof($found_items) == sizeof($items)) return self::MakeOperation("cancelled",$invoice,true);

            Helper::Load(["Money"]);

            foreach($found_items AS $f_i) self::delete_item($f_i["id"]);

            $discounts      = $invoice["discounts"];

            if(isset($discounts["items"]) && $discounts["items"])
            {
                foreach(array_keys($discounts["items"]) AS $t)
                {
                    foreach ($found_items AS $f_i)
                    {
                        if(isset($discounts["items"][$t][$f_i["id"]]))
                            unset($discounts["items"][$t][$f_i["id"]]);
                    }
                    if(!$discounts["items"][$t]) unset($discounts["items"][$t]);
                }
            }
            if(isset($discounts["items"]) && !$discounts["items"]) unset($discounts["items"]);
            $invoice["discounts"] = $discounts;

            $items      = self::get_items($invoice["id"]);
            $calculate  = self::calculate_invoice($invoice,$items);

            self::set($invoice["id"],[
                'subtotal'  => $calculate["subtotal"],
                'tax'       => $calculate["tax"],
                'total'     => $calculate["total"],
                'discounts' => $discounts ? Utility::jencode($discounts) : '',
            ]);

            return true;
        }

        static function delete($id=0){
            $action_source = self::action_source();

            if(is_array($id)) {
                $invoice = $id;
                $id      = $invoice["id"];
            }else
                $invoice = self::get($id);

            $invoice_vars = $invoice;
            $invoice_vars['source'] = $action_source;

            Hook::run("deleteInvoice",$invoice_vars);
            Hook::run("PreInvoiceDeleted",$invoice_vars);

            $stmt1   = Models::$init->db->delete("invoices")->where("id","=",$id)->run();
            $stmt2   = Models::$init->db->delete("invoices_items")->where("owner_id","=",$id)->run();
            $stmt3   = Models::$init->db->delete("income_expense")->where("invoice_id","=",$id)->run();

            Hook::run("InvoiceDeleted",$invoice_vars);

            if(in_array($id,self::$added))
            {
                $prefix1         = isset($invoice["status"]) && $invoice["status"] == "paid" ? "-paid" : '';
                $prefix2         = isset($invoice["status"]) && $invoice["status"] == "paid" ? "paid-" : '';

                $invoice_id_fake = Config::getd("invoice-id".$prefix1);

                if(Config::get("options/".$prefix2."invoice-num-format-status"))
                    Config::setd("invoice-id".$prefix1,($invoice_id_fake - 1));

            }

            return $stmt1;
        }

        static function set_wpp($order=[],$price=0,$status='',$pmethod='')
        {
            if($status == "unpaid"){
                $repetition_check   = Models::$init->db->select("t1.id")->from("invoices AS t1");
                $repetition_check->join("LEFT","invoices_items AS t2","t2.owner_id=t1.id");
                $repetition_check->where("t2.id","IS NOT NULL","","&&");
                $repetition_check->where("t1.status","!=","paid","&&");
                $repetition_check->where("t1.status","!=","refund","&&");
                $repetition_check->where("t2.user_pid","=",$order["id"],"&&");
                $repetition_check->where("t2.options","LIKE","%\"event\":\"ModifyDomainWhoisPrivacy\"%");
                $repetition_check->group_by("t1.id");
                $repetition_check   = $repetition_check->build() ? $repetition_check->getObject()->id : false;
                if($repetition_check){
                    self::$message = "repetition";
                    return false;
                }
            }

            $invoice    = self::bill_generate(
                [
                    'user_id' => $order["owner_id"],
                    'status' => $status,
                    'pmethod' => $pmethod,
                    'amount'  => $price,
                    'cid'     => $order["amount_cid"],
                ],
                [
                    [
                        'process' => true,
                        'name' => Bootstrap::$lang->get_cm("admin/orders/whois-privacy-invoice-description",[
                            '{name}' => $order["name"],
                        ]),
                        'user_pid' => $order["id"],
                        'options'  => [
                            'event' => "ModifyDomainWhoisPrivacy"
                        ],
                    ]
                ]
            );

            return $invoice;
        }

        static function generate_create_order($odata=[],$items=[],$notifications=true){
            return self::bill_generate($odata,$items,$notifications,true);
        }

        static function generate_upgrade($order,$product,$sproduct,$sprice,$status,$pmethod=''){
            if($status == "unpaid"){
                $repetition_check   = Models::$init->db->select("t1.id")->from("invoices_items AS t2");
                $repetition_check->join("LEFT","invoices AS t1","t2.owner_id=t1.id");
                $repetition_check->where("t1.id","IS NOT NULL","","&&");
                $repetition_check->where("t1.status","!=","paid","&&");
                $repetition_check->where("t1.status","!=","refund","&&");
                $repetition_check->where("t1.status","!=","cancelled","&&");
                $repetition_check->where("t2.user_pid","=",$order["id"],"&&");
                $repetition_check->where("t2.options","LIKE","%\"event\":\"OrderUpgrade\"%");
                $repetition_check->group_by("t1.id");
                $repetition_check   = $repetition_check->build() ? $repetition_check->getObject()->id : false;
                if($repetition_check){
                    self::$message = "repetition";
                    return false;
                }
            }

            $ulang      = User::getData($order["owner_id"],"lang")->lang;

            $u_product   = Products::get_info_by_fields($product["type"],$product["id"],["t2.title"],$ulang);
            if(!$u_product) $u_product = $product;
            $u_sproduct  = Products::get_info_by_fields($sproduct["type"],$sproduct["id"],["t2.title"],$ulang);

            $taxation_type = self::getTaxationType();

            $invoice    = self::bill_generate(
                [
                    'user_id' => $order["owner_id"],
                    'status' => $status,
                    'pmethod' => $pmethod,
                    'amount'  => $taxation_type == "inclusive" ? $sprice["taxed_payable"] : $sprice["payable"],
                    'cid'     => $order["amount_cid"],
                ],
                [
                    [
                        'name' => Bootstrap::$lang->get_cm("admin/orders/upgrade-invoice-description",[
                                '{old-product-name}' => $u_product["title"],
                                '{new-product-name}' => $u_sproduct["title"],
                            ],$ulang)." (#".$order["id"].")",
                        'user_pid' => $order["id"],
                        'options'  => [
                            'event' => "OrderUpgrade",
                            'event_data' => [
                                'usproduct_id' => $order["id"],
                                'sproduct' => $sproduct["id"],
                                'sprice'   => $sprice["id"],
                            ],
                        ],
                    ]
                ]
            );

            return $invoice;

        }

        static function generate_user_data($id=0){
            $user_data     = [];
            $user_data     = array_merge($user_data,User::getData($id,"id,email,currency,phone,name,surname,full_name,lang","array"));
            $user_data     = array_merge($user_data,User::getInfo($id,"default_address,dealership,taxation,gsm_cc,gsm,landline_cc,landline_phone,identity,kind,company_name,company_tax_number,company_tax_office"));
            $defAd = (int) $user_data["default_address"];
            if(!$defAd) return false;
            $getAddress = AddressManager::getAddress($defAd);
            if(!$getAddress) return false;


            if(Utility::strlen($getAddress["email"]) > 1) $user_data["email"] = $getAddress["email"];
            if(Utility::strlen($getAddress["name"]) > 1) $user_data["name"] = $getAddress["name"];
            if(Utility::strlen($getAddress["surname"]) > 1) $user_data["surname"] = $getAddress["surname"];
            if(Utility::strlen($getAddress["full_name"]) > 1) $user_data["full_name"] = $getAddress["full_name"];
            if(Utility::strlen($getAddress["full_name"]) > 2)
            {
                if(Utility::strlen($getAddress["phone"]) > 4)
                {
                    $user_data["phone"] = $getAddress["phone"];
                    $phone_smash    = Filter::phone_smash($getAddress["phone"]);
                    $user_data["gsm_cc"]    = $phone_smash["cc"];
                    $user_data["gsm"]       = $phone_smash["number"];

                }
                else
                {
                    $user_data["phone"] = '';
                    $user_data["gsm_cc"] = '';
                    $user_data["gsm"] = '';
                }

                if(Utility::strlen($getAddress["kind"]) > 1) $user_data["kind"] = $getAddress["kind"];
            }


            if(Utility::strlen($getAddress["full_name"]) > 1)
            {
                $user_data["company_name"]          = $getAddress["company_name"];
                $user_data["company_tax_number"]    = $getAddress["company_tax_number"];
                $user_data["company_tax_office"]    = $getAddress["company_tax_office"];
            }

            $identity_status        = Config::get("options/sign/up/kind/individual/identity/status");
            $identity_required      = Config::get("options/sign/up/kind/individual/identity/required");
            if($identity_status && $identity_required && !Validation::isEmpty($getAddress["identity"]))
                $user_data["identity"] = $getAddress["identity"];
            


            $fake_addr = $getAddress;

            unset($fake_addr["name"]);
            unset($fake_addr["surname"]);
            unset($fake_addr["full_name"]);
            unset($fake_addr["kind"]);
            unset($fake_addr["company_name"]);
            unset($fake_addr["company_tax_office"]);
            unset($fake_addr["company_tax_number"]);
            unset($fake_addr["phone"]);
            unset($fake_addr["email"]);
            unset($fake_addr["identity"]);

            $user_data["address"]               = $fake_addr;

            Helper::Load("Money");
            if(!Money::Currency($user_data["currency"],true)) $user_data["currency"] = Config::get("general/currency");

            return $user_data;
        }

        static function get_last_invoice($uspid=0,$status='',$fields=''){
            if($fields == '') $fields = "t2.id,t2.datepaid,t2.pmethod,t2.taxed";
            $stmt = Models::$init->db->select($fields)->from("invoices AS t2");
            $stmt->join("LEFT","invoices_items AS t1","t1.owner_id=t2.id");
            $stmt->where("t1.id","IS NOT NULL","","&&");
            if($status) $stmt->where("t2.status","=",$status,"&&");
            $stmt->where("t1.user_pid","=",$uspid,"&&");
            $stmt->where("t1.options","LIKE","%Order%");
            $stmt->group_by("t2.id");
            $stmt->order_by("t2.id DESC");
            return $stmt->build() ? $stmt->getAssoc() : false;
        }

        static function get_last_invoice_addon($addon_id=0,$status='',$fields=''){
            if($fields == '') $fields = "t2.id,t2.datepaid,t2.pmethod,t2.taxed";
            $stmt = Models::$init->db->select($fields)->from("invoices AS t2");
            $stmt->join("LEFT","invoices_items AS t1","t1.owner_id=t2.id");
            $stmt->where("t1.id","IS NOT NULL","","&&");
            if($status) $stmt->where("t2.status","=",$status,"&&");
            $stmt->where("t1.options","LIKE","%Extend%","&&");
            $stmt->where("JSON_EXTRACT(t1.options,'$.event_data.addon_id')","LIKE","%".$addon_id."%");
            $stmt->group_by("t2.id");
            $stmt->order_by("t2.id DESC");
            return $stmt->build() ? $stmt->getAssoc() : false;
        }

        static function get_order_invoices($uspid=0){

            $case = "CASE ";
            $case .= "WHEN t2.status = 'waiting' THEN 0 ";
            $case .= "WHEN t2.status = 'unpaid' THEN 1 ";
            $case .= "WHEN t2.status = 'paid' THEN 2 ";
            $case .= "ELSE 3 ";
            $case .= "END AS rank";

            $select = implode(",",[
                't2.id',
                't2.status',
                't2.cdate',
                't2.datepaid',
                't2.duedate',
                't2.refunddate',
                't2.subtotal',
                't2.total',
                't2.currency',
                $case,
            ]);
            $stmt = Models::$init->db->select($select)->from("invoices_items AS t1");
            $stmt->join("LEFT","invoices AS t2","t1.owner_id=t2.id");
            $stmt->where("t1.id","IS NOT NULL","","&&");
            $stmt->where("t1.user_pid","=",$uspid);
            $stmt->group_by("t2.id");
            $stmt->order_by("rank ASC,t2.datepaid DESC,t2.id DESC");
            return $stmt->build() ? $stmt->fetch_assoc() : false;
        }

        static function get_last_user_invoice($user_id=0,$status='',$fields=''){
            if($fields == '') $fields = "t1.id,t1.datepaid,t1.pmethod,t1.taxed";
            $stmt = Models::$init->db->select($fields)->from("invoices AS t1");
            if($status) $stmt->where("t1.status","=",$status,"&&");
            $stmt->where("t1.user_id","=",$user_id);
            $stmt->order_by("t1.id DESC");
            return $stmt->build() ? $stmt->getAssoc() : false;
        }

        static function get_item_count($id=0){
            $stmt = Models::$init->db->select("COUNT(id) AS count")->from("invoices_items");
            $stmt->where("owner_id","=",$id);
            return $stmt->build() ? $stmt->getObject()->count : 0;
        }

        static function set($id=0,$data=[]){
            return Models::$init->db->update("invoices",$data)->where("id","=",$id)->save();
        }

        static function get($id=0,$opt=[]){
            $select = "*";
            if(isset($opt["select"])){
                $select = $opt["select"];
                unset($opt["select"]);
            }
            $data   = Models::$init->db->select($select)->from("invoices")->where("id","=",$id,$opt ? "&&" : "");
            if(isset($opt["user_id"])) $data->where("user_id","=",$opt["user_id"]);

            $data   = $data->build() ? $data->getAssoc() : false;
            if($data){
                if(isset($data["user_data"]))
                    $data["user_data"] = $data["user_data"] ? Utility::jdecode($data["user_data"],true) : [];
                if(isset($data["data"])) $data["data"] = $data["data"] ? Utility::jdecode($data["data"],true) : [];
                if(isset($data["discounts"])) $data["discounts"] = $data["discounts"] ? Utility::jdecode($data["discounts"],true) : [];
            }
            return $data;
        }

        static function process($checkout=[],$pstatus='',$pstatus_msg='')
        {
            $items  = $checkout["items"];
            $data   = $checkout["data"];

            $date_zero      = "1881-05-19 00:00:00";
            $datepaid       = $pstatus == "SUCCESS" ? DateManager::Now() : $date_zero;
            $status         = $pstatus == "SUCCESS" ? "paid" : "waiting";
            if($pstatus == "UNPAID") $status = "unpaid";
            $pmethod_status = $pstatus;
            $pmethod_msg    = $pstatus_msg;

            $i_data         = [];


            if($data["currency"] != Config::get("general/currency")){
                $curr   = Money::Currency($data["currency"]);
                $rate   = (float) $curr["rate"];
                $i_data["exchange_rate"] = (1 / $rate);
            }

            if(isset($data["subscribed"]) && $data["subscribed"])
                $i_data["subscribed"] = $data["subscribed"];

            $u_taxation         = false;

            if($data["user_data"]["taxation"] != NULL && $data["user_data"]["taxation"] == 0)
                $u_taxation = true;

            $legal_zero         = isset($data["legal"]) && !$data["legal"];
            $btxn               = Config::get("options/balance-taxation");
            if(!$btxn) $btxn = "y";

            if($data["pmethod"] == "Balance")
            {
                if($btxn == "y") $legal_zero = true;
                if($btxn == "n" && !$u_taxation) $legal_zero = false;
            }
            if(isset($data["pmethod"]) && $data["pmethod"] == "Free") $legal_zero = true;

            $data["legal"] = $legal_zero ? 0 : 1;

            $invoice_id     = self::add([
                'user_id'           => $data["user_id"],
                'user_data'         => Utility::jencode($data["user_data"]),
                'cdate'             => DateManager::Now(),
                'duedate'           => DateManager::Now(),
                'datepaid'          => $datepaid,
                'local'             => $data["local"],
                'legal'             => $data["legal"],
                'data'              => $i_data ? Utility::jencode($i_data) : '',
                'status'            => $status,
                'currency'          => $data["currency"],
                'taxation_type'     => Config::get("options/taxation-type"),
                'taxrate'           => $data["taxrate"],
                'tax'               => $data["tax"],
                'subtotal'          => $data["subtotal"],
                'total'             => $data["total"],
                'discounts'         => isset($data["discounts"]) ? Utility::jencode($data["discounts"]) : '',
                'used_coupons'      => isset($data["discounts"]["used_coupons"]) ? implode(",",$data["discounts"]["used_coupons"]) : '',
                'used_promotions'   => isset($data["discounts"]["used_promotions"]) ? implode(",",$data["discounts"]["used_promotions"]) : '',
                'sendbta'           => isset($data["sendbta"]) && $data["sendbta"] ? 1 : 0,
                'sendbta_amount'    => isset($data["sendbta_amount"]) ? $data["sendbta_amount"] : 0,
                'pmethod'           => isset($data["pmethod"]) ? $data["pmethod"] : 'none',
                'pmethod_commission' => isset($data["pmethod_commission"]) ? $data["pmethod_commission"] : 0,
                'pmethod_commission_rate' => isset($data["pmethod_commission_rate"]) ? $data["pmethod_commission_rate"] : 0,
                'pmethod_status'    => $pmethod_status,
                'pmethod_msg'       => $pmethod_msg,
            ]);
            if(!$invoice_id) return false;

            if(!class_exists("Basket")) Helper::Load(["Basket"]);

            if($checkout["id"] != "api"){
                $data["invoice_id"] = $invoice_id;
                Basket::set_checkout($checkout["id"],[
                    'data' => Utility::jencode($data),
                ]);
            }


            $invoice    = self::get($invoice_id);


            $invoice["checkout"] = $checkout;

            if(isset($data["discounts"]) && $data["discounts"] && $status == "paid"){
                if(isset($data["discounts"]["used_coupons"]) && $data["discounts"]["used_coupons"])
                    foreach($data["discounts"]["used_coupons"] AS $coup) self::reduce_coupon($coup);

                if(isset($data["discounts"]["used_promotions"]) && $data["discounts"]["used_promotions"])
                    foreach($data["discounts"]["used_promotions"] AS $promo) self::reduce_promotion($promo);
            }

            if($status == "paid" && ($invoice["pmethod"] != "Balance" || ($invoice["pmethod"] == "Balance" && Config::get("options/balance-taxation") == "n"))){
                $getInex    = self::get_inex(false,$invoice["id"]);
                if(!$getInex && $invoice["total"]>0)
                    self::insert_inex([
                        'invoice_id' => $invoice["id"],
                        'type' => "income",
                        'amount' => $invoice["total"]>0 ? $invoice["total"] : $invoice["subtotal"],
                        'currency' => $invoice["currency"],
                        'cdate' => DateManager::Now(),
                        'description' => Bootstrap::$lang->get("needs/invoice",Config::get("general/local"))." ".$invoice["number"],
                    ]);

                if(isset($data["pmethod_stored_card"]) && $data["pmethod_stored_card"])
                {
                    $stored_card = Models::$init->db->select("ln4")->from("users_stored_cards");
                    $stored_card->where("id","=",$data["pmethod_stored_card"]);
                    $stored_card = $stored_card->build() ? $stored_card->getObject()->ln4 : false;
                    if($stored_card)
                    {
                        $log_k      = "paid-with-stored-card-1";
                        $log_uid    = $invoice["user_id"];

                        if(isset($data["pmethod_by_auto_pay"]) && $data["pmethod_by_auto_pay"])
                        {
                            $log_k      = 'paid-with-stored-card-2';
                            $log_uid    = 0;
                        }
                        User::addAction($log_uid,'transaction',$log_k,[
                            'id'        => $invoice["number"] ? $invoice["number"] : $invoice["id"],
                            'ln4'       => $stored_card,
                        ]);
                    }
                }
            }


            $orders         = [];
            $discounts      = [];

            foreach($items AS $k => $item){
                $order  = Orders::process($item,$invoice);

                $description    = $item["name"];
                if($order && !stristr($description,$order["id"])) $description .= " (#".$order["id"].")";
                elseif(isset($item["options"]["event_data"]["usproduct_id"]) && !stristr($description,$item["options"]["event_data"]["usproduct_id"])) $description .= " (#".$item["options"]["event_data"]["usproduct_id"].")";

                if(isset($item["options"]["domain"]) && $item["options"]["domain"] && $item["options"]["type"] != "domain")
                    $description .= " (".$item["options"]["domain"].")";

                if(isset($item["options"]["hostname"]) && $item["options"]["hostname"])
                    $description .= " (".$item["options"]["hostname"].")";


                if(isset($item["options"]["type"]) && (!isset($item["options"]["id"]) || $item["options"]["type"] == "domain")) if(isset($item["options"]["category"]) && $item["options"]["category"]) $description .= " (".$item["options"]["category"].")";



                if(isset($item["options"]["period"]) && $item["options"]["period"] != "none" && !isset($item["options"]["tcode"])){
                    $description .= ' | '.View::period($item["options"]["period_time"],$item["options"]["period"],$invoice["user_data"]["lang"]).'';
                }


                if(isset($item["options"]["selection"]["setup"]) && $item["options"]["selection"]["setup"] > 0.00)
                    $description .= ' + '.Bootstrap::$lang->get_cm("website/osteps/setup-fee",false,$data["user_data"]["lang"]);


                if($order && isset($order["addons"]) && $order["addons"]) $item["options"]["addons"] = $order["addons"];

                if($order) $orders[] = $order;

                if(isset($item["options"]) && $item["options"]){
                    if(isset($item["amount_including_discount"]))
                        $item["options"]["amount_including_discount"] = $item["amount_including_discount"];
                    if(isset($item["discounts"])) $item["options"]["discounts"] = $item["discounts"];
                }

                $taxexempt      = isset($item['taxexempt']) ? (int) $item['taxexempt'] : 0;


                $i_id = self::add_item([
                    'owner_id'      => $invoice_id,
                    'user_id'       => $data["user_id"],
                    'user_pid'      => $order ? $order["id"] : 0,
                    'options'       => Utility::jencode($item["options"]),
                    'description'   => $description,
                    'quantity'      => $item["quantity"],
                    'amount'        => $item["amount"],
                    'total_amount'  => $item["total_amount"] - ($item["adds_amount"] ?? 0),
                    'taxexempt'     => $taxexempt,
                    'oduedate'      => DateManager::Now(),
                    'currency'      => $data["currency"],
                ]);

                if(isset($data["discounts"]["items"]) && $data["discounts"]["items"])
                    foreach($data["discounts"]["items"] AS $g_k => $is)
                        if(isset($data["discounts"]["items"][$g_k][$k]))
                            $discounts[$g_k][$i_id] = $data["discounts"]["items"][$g_k][$k];
                $invoice["order"] = $order;

                if(isset($item["options"]["addons"]) && $item["options"]["addons"])
                {
                    foreach($item["options"]["addons"] AS $addon)
                    {
                        self::add_item([
                            'owner_id'      => $invoice_id,
                            'user_id'       => $data["user_id"],
                            'user_pid'      => $order ? $order["id"] : 0,
                            'options'       => Utility::jencode([
                                'event' => "ProcessAddon",
                                'event_data' => [
                                    'addon_id' => $addon["id"],
                                ],
                            ]),
                            'description'   => $addon['addon_name'].": ".$addon['option_name'],
                            'quantity'      => 1,
                            'amount'        => $addon["amount"],
                            'total_amount'  => $addon["amount"],
                            'taxexempt'     => $taxexempt,
                            'oduedate'      => DateManager::Now(),
                            'currency'      => $data["currency"],
                        ]);
                    }
                }

            }

            if($discounts)
            {
                $data["discounts"]["items"] = $discounts;
                $invoice["discounts"]["items"] = $discounts;
                Invoices::set($invoice_id,['discounts' => Utility::jencode($data["discounts"])]);
            }


            Helper::Load(["Notification"]);

            Orders::module_process();
            Orders::notification_process();

            if($invoice){

                $invoice_vars = $invoice;
                $invoice_vars['source'] = self::action_source();

                Hook::run("InvoiceCreated",$invoice_vars);
                if($status == "paid") Hook::run("InvoicePaid",$invoice_vars);

                Notification::order_has_been_taken($invoice);
                if($status == "waiting") Notification::invoice_awaiting_confirmation($invoice);
                elseif($status == "paid") Notification::invoice_has_been_approved($invoice);
                elseif($status == "unpaid") Notification::invoice_created($invoice);

                return $invoice;
            }
            else
                return false;

        }

        static function paid_subscription($s=[],$id=0,$commission_rate=0,$module='')
        {
            Helper::Load("Notification");
            $invoice        = self::get($id);
            $items          = self::get_items($invoice["id"]);

            $invoice["pmethod"]                 = $module;
            $invoice["pmethod_commission_rate"] = $commission_rate;

            if($commission_rate > 0.00)
            {
                $invoice["pmethod_commission"] = Money::get_tax_amount($invoice["subtotal"],$commission_rate);
                if($invoice["tax"] > 0.00)
                {
                    $pcommission_tax        = Money::get_tax_amount($invoice["pmethod_commission"],$invoice["taxrate"]);
                    $invoice["tax"]         += $pcommission_tax;
                    $invoice["total"]       += $pcommission_tax;
                }

            }

            $calculate      = self::calculate_invoice($invoice,$items);

            $invoice["subtotal"]    = $calculate["subtotal"];
            $invoice["tax"]         = $calculate["tax"];
            $invoice["total"]       = $calculate["total"];

            $data = [
                'type'       => "bill",
                'user_data'  => $invoice["user_data"],
                'user_id'    => $invoice["user_data"]["id"],
                'invoice_id' => $invoice["id"],
                'local'      => $invoice["local"],
                'legal'      => $invoice["legal"],
                'currency'   => $invoice["currency"],
                'subtotal'   => $invoice['subtotal'],
                'taxrate'    => $invoice["taxrate"],
                'tax'        => $invoice["tax"],
                'total'      => $invoice["total"],
                'sendbta'    => 0,
                'pmethod'    => $module,
                'pmethod_commission' => $invoice["pmethod_commission"],
                'pmethod_commission_rate' => $invoice["pmethod_commission_rate"],
                'subscribable' => [],
                'paid_by_subscription' => true,
            ];

            self::paid([
                'id'            => 0,
                'data'          => $data,
                'items'         => $items,
            ],'SUCCESS',false,true);
        }

        static function paid($checkout=[],$pstatus='',$pstatus_msg='',$notification=false){
            Helper::Load("Notification");
            $ctdata     = $checkout["data"];
            $invoice_id = $ctdata["invoice_id"];
            $legal      = $ctdata["legal"];
            $tax        = is_null($ctdata["tax"]) ? 0 : $ctdata["tax"];
            $subtotal   = $ctdata["subtotal"];
            $total      = $ctdata["total"];
            $sendbta    = $ctdata["sendbta"];
            if(isset($ctdata["id"])) $sendbta = $ctdata["sendbta_amount"];
            $pmethod    = $ctdata["pmethod"];
            $commission = $ctdata["pmethod_commission"];
            $commission_rate = $ctdata["pmethod_commission_rate"];
            $date_zero  = "1881-05-19 00:00:00";
            $datepaid   = $pstatus == "SUCCESS" ? DateManager::Now() : $date_zero;
            $status     = $pstatus == "SUCCESS" ? "paid" : "waiting";
            $invoice    = self::get($invoice_id);
            $iedata     = $invoice["data"];

            if($invoice["currency"] != Config::get("general/currency")){
                $curr   = Money::Currency($invoice["currency"]);
                $rate   = (float) $curr["rate"];
                $iedata["exchange_rate"] = (1 / $rate);
            }

            if(isset($ctdata["subscribed"]) && $ctdata["subscribed"])
                $iedata["subscribed"] = $ctdata["subscribed"];

            if(isset($ctdata["paid_by_subscription"]) && $ctdata["paid_by_subscription"])
                $iedata["paid_by_subscription"] = true;


            $legal_zero         = !$legal;
            $btxn               = Config::get("options/balance-taxation");
            if(!$btxn) $btxn = "y";

            if($pmethod == "Balance")
            {
                if(!$ctdata["reseller_renewal"] ?? false)
                {
                    if($btxn == "y") $legal_zero = true;
                    if($btxn == "n") $legal_zero = false;
                }
            }
            if($pmethod && $pmethod == "Free") $legal_zero = true;


            $legal = $legal_zero ? 0 : 1;

            $data       = [
                'data'  => $iedata ? Utility::jencode($iedata) : '',
                'datepaid' => $datepaid,
                'legal' => $legal,
                'taxrate' => $invoice["local"] ? $invoice["taxrate"] : 0,
                'tax'   => $tax,
                'subtotal' => $subtotal,
                'total' => $total,
                'sendbta' => $sendbta>0 ? 1 : 0,
                'sendbta_amount' => $sendbta,
                'pmethod' => $pmethod,
                'status'  => $status,
                'pmethod_commission' => $commission,
                'pmethod_commission_rate' => $commission_rate,
                'pmethod_status'    => $pstatus,
                'pmethod_msg'       => $pstatus_msg,
            ];

            if($status == "paid") {
                $prefix1         = isset($data["status"]) && $data["status"] == "paid" ? "-paid" : '';
                $prefix2         = isset($data["status"]) && $data["status"] == "paid" ? "paid-" : '';

                if(!isset($data["number"]))
                {
                    $invoice_id_fake = Config::getd("invoice-id".$prefix1);

                    $i_n_f      = Config::get("options/".$prefix2."invoice-num-format");
                    $i_n_f_s    = Config::get("options/".$prefix2."invoice-num-format-status");
                    if(($i_n_f_s && strlen($invoice_id_fake) < 1) || !$i_n_f_s) $invoice_id_fake = $invoice_id;
                    if(!$i_n_f_s) $i_n_f = false;
                    if(!$i_n_f && strlen($data["number"]) < 2) $i_n_f = '#{NUMBER}';


                    if(stristr($i_n_f,'{NUMBER}'))
                    {
                        $i_n_f = str_replace(
                            [
                                "{NUMBER}",
                                "{DAY}",
                                "{MONTH}",
                                "{YEAR}"
                            ],
                            [
                                $invoice_id_fake,
                                DateManager::Now("d"),
                                DateManager::Now("m"),
                                DateManager::Now("Y")
                            ],$i_n_f);
                        $data["number"] = $i_n_f;
                        if($i_n_f_s) Config::setd("invoice-id".$prefix1,($invoice_id_fake + 1));
                    }
                }
            }



            self::set($invoice_id,$data);

            $invoice    = self::get($invoice_id);

            $invoice["checkout"] = $checkout;

            if($status == "paid" && $invoice["pmethod"] != "Free" && $invoice["total"] > 0.00 && ($invoice["pmethod"] != "Balance" || ($invoice["pmethod"] == "Balance" && Config::get("options/balance-taxation") == "n")))
            {
                $getInex    = self::get_inex(false,$invoice["id"]);
                if(!$getInex && $invoice["total"]>0)
                    self::insert_inex([
                        'invoice_id' => $invoice["id"],
                        'type' => "income",
                        'amount' => $invoice["total"]>0 ? $invoice["total"] : $invoice["subtotal"],
                        'currency' => $invoice["currency"],
                        'cdate' => DateManager::Now(),
                        'description' => Bootstrap::$lang->get("needs/invoice",Config::get("general/local"))." ".$invoice["number"],
                    ]);

                if(isset($ctdata["pmethod_stored_card"]) && $ctdata["pmethod_stored_card"])
                {
                    $stored_card = Models::$init->db->select("ln4")->from("users_stored_cards");
                    $stored_card->where("id","=",$ctdata["pmethod_stored_card"]);
                    $stored_card = $stored_card->build() ? $stored_card->getObject()->ln4 : false;
                    if($stored_card)
                    {
                        $log_k      = "paid-with-stored-card-1";
                        $log_uid    = $invoice["user_id"];

                        if(isset($ctdata["pmethod_by_auto_pay"]) && $ctdata["pmethod_by_auto_pay"])
                        {
                            $log_k      = 'paid-with-stored-card-2';
                            $log_uid    = 0;
                        }
                        User::addAction($log_uid,'transaction',$log_k,[
                            'id'        => $invoice["number"] ? $invoice["number"] : $invoice["id"],
                            'ln4'       => $stored_card,
                        ]);
                    }
                }

            }

            $items      = self::get_items($invoice_id);

            foreach($items AS $item) Orders::process($item,$invoice);

            if($status == "paid")
            {
                $invoice_vars = $invoice;
                $invoice_vars['source'] = self::action_source();
                Hook::run("InvoicePaid",$invoice_vars);
            }

            if($status == "paid" && $notification) Notification::invoice_has_been_approved($invoice);

            Orders::module_process();
            Orders::notification_process();

            if($pstatus == "PAPPROVAL") Notification::invoice_awaiting_confirmation($invoice);

        }

        static function bulk_paid($checkout=[],$pstatus='',$pstatus_msg='',$notification=false){
            Helper::Load("Notification");
            $ctdata     = $checkout["data"];

            $pmethod        = $ctdata["pmethod"];
            $commission     = $ctdata["pmethod_commission"];
            $commission_rate = $ctdata["pmethod_commission_rate"];
            $date_zero  = "1881-05-19 00:00:00";
            $datepaid   = $pstatus == "SUCCESS" ? DateManager::Now() : $date_zero;
            $status     = $pstatus == "SUCCESS" ? "paid" : "waiting";

            if(isset($ctdata["invoices"]) && $ctdata["invoices"]){
                $invoices           = $ctdata["invoices"];
                $invoice_count      = sizeof($invoices);
                foreach($invoices AS $invoice_id)
                {
                    $invoice    = self::get($invoice_id);
                    $iedata     = $invoice["data"];

                    if($invoice["currency"] != Config::get("general/currency")){
                        $curr   = Money::Currency($invoice["currency"]);
                        $rate   = (float) $curr["rate"];
                        $iedata["exchange_rate"] = (1 / $rate);
                    }

                    if(isset($ctdata["subscribed"]) && $ctdata["subscribed"])
                        $iedata["subscribed"] = $ctdata["subscribed"];

                    if(isset($ctdata["paid_by_subscription"]) && $ctdata["paid_by_subscription"])
                        $iedata["paid_by_subscription"] = true;


                    $taxrate    = $invoice["taxrate"];
                    $tax        = $invoice["tax"];
                    $subtotal   = $invoice["subtotal"];
                    $total      = $invoice["total"];
                    $legal      = $invoice["legal"];

                    $legal_zero         = !$legal;
                    $btxn               = Config::get("options/balance-taxation");
                    if(!$btxn) $btxn = "y";

                    if($pmethod == "Balance")
                    {
                        if($btxn == "y") $legal_zero = true;
                        if($btxn == "n") $legal_zero = false;
                    }

                    if($pmethod && $pmethod == "Free") $legal_zero = true;

                    $legal      = $legal_zero ? 0 : 1;

                    $commission_amount = $commission;

                    if($commission > 0.00 && $invoice_count > 1)
                    {
                        $commission_amount = Money::get_discount_amount($invoice["subtotal"],$commission_rate);
                        $commission_amount = round($commission_amount,4);
                    }

                    $items = self::get_items($invoice["id"]);

                    /*

                    if($items)
                    {
                        $tax        = 0;
                        $taxrate    = 0;
                        foreach($items AS $i)
                        {
                            if(!$i["taxexempt"])
                            {
                                if($invoice["local"] && $legal){
                                    $taxrate    = $invoice["taxrate"];
                                    $tax_x      = Money::get_tax_amount($i["amount"],$taxrate);
                                    $tax        += $tax_x;
                                    $total      += $tax;
                                }
                            }
                        }
                    }
                    */

                    if(!$legal)
                    {
                        $taxrate    = 0;
                        $tax        = 0;
                    }

                    $data       = [
                        'data'  => $iedata ? Utility::jencode($iedata) : '',
                        'datepaid' => $datepaid,
                        'legal' => $legal,
                        'taxrate' => $taxrate,
                        'tax'      => $tax,
                        'subtotal' => $subtotal,
                        'total' => $total,
                        'pmethod' => $pmethod,
                        'status'  => $status,
                        'pmethod_commission' => $commission_amount,
                        'pmethod_commission_rate' => $commission_rate,
                        'pmethod_status'    => $pstatus,
                        'pmethod_msg'       => $pstatus_msg,
                    ];

                    if(($invoice["legal"] && !$legal) || $invoice["pmethod_commission"] != $data["pmethod_commission"])
                    {
                        $new_invoice    = array_merge($invoice,$data);

                        $calculate      = self::calculate_invoice($new_invoice,$items);

                        $data["subtotal"]   = $calculate["subtotal"];
                        $data["tax"]        = $calculate["tax"];
                        $data["total"]      = $calculate["total"];
                    }



                    $prefix1         = isset($data["status"]) && $data["status"] == "paid" ? "-paid" : '';
                    $prefix2         = isset($data["status"]) && $data["status"] == "paid" ? "paid-" : '';

                    if(!isset($data["number"]))
                    {
                        $invoice_id_fake = Config::getd("invoice-id".$prefix1);

                        $i_n_f      = Config::get("options/".$prefix2."invoice-num-format");
                        $i_n_f_s    = Config::get("options/".$prefix2."invoice-num-format-status");
                        if(!$i_n_f_s) $i_n_f = false;
                        if(!$i_n_f && strlen($data["number"]) < 2) $i_n_f = '#{NUMBER}';


                        if(($i_n_f_s && strlen($invoice_id_fake) < 1) || !$i_n_f_s) $invoice_id_fake = $invoice_id;

                        if(stristr($i_n_f,'{NUMBER}'))
                        {
                            $i_n_f = str_replace(
                                [
                                    "{NUMBER}",
                                    "{DAY}",
                                    "{MONTH}",
                                    "{YEAR}"
                                ],
                                [
                                    $invoice_id_fake,
                                    DateManager::Now("d"),
                                    DateManager::Now("m"),
                                    DateManager::Now("Y")
                                ],
                                $i_n_f
                            );
                            $data["number"] = $i_n_f;
                            if($i_n_f_s) Config::setd("invoice-id".$prefix1,($invoice_id_fake + 1));
                        }
                    }


                    self::set($invoice_id,$data);

                    $invoice    = self::get($invoice_id);

                    if($commission > 0.00 && $invoice_count > 1)
                    {
                        $calculate      = Invoices::calculate_invoice($invoice,$items);
                        self::set($invoice_id,[
                            'tax'      => $calculate["tax"],
                            'subtotal' => $calculate["subtotal"],
                            'total' => $calculate["total"],
                        ]);
                        $invoice    = self::get($invoice_id);
                    }



                    $invoice["checkout"] = $checkout;

                    if($status == "paid" && ($invoice["pmethod"] != "Balance" || ($invoice["pmethod"] == "Balance" && Config::get("options/balance-taxation") == "n")))
                    {
                        $getInex    = self::get_inex(false,$invoice["id"]);
                        if(!$getInex && $invoice["total"]>0)
                            self::insert_inex([
                                'invoice_id' => $invoice["id"],
                                'type' => "income",
                                'amount' => $invoice["total"]>0 ? $invoice["total"] : $invoice["subtotal"],
                                'currency' => $invoice["currency"],
                                'cdate' => DateManager::Now(),
                                'description' => Bootstrap::$lang->get("needs/invoice",Config::get("general/local"))." ".$invoice["number"],
                            ]);

                        if(isset($ctdata["pmethod_stored_card"]) && $ctdata["pmethod_stored_card"])
                        {
                            $stored_card = Models::$init->db->select("ln4")->from("users_stored_cards");
                            $stored_card->where("id","=",$ctdata["pmethod_stored_card"]);
                            $stored_card = $stored_card->build() ? $stored_card->getObject()->ln4 : false;
                            if($stored_card)
                            {
                                $log_k      = "paid-with-stored-card-1";
                                $log_uid    = $invoice["user_id"];

                                if(isset($ctdata["pmethod_by_auto_pay"]) && $ctdata["pmethod_by_auto_pay"])
                                {
                                    $log_k      = 'paid-with-stored-card-2';
                                    $log_uid    = 0;
                                }
                                User::addAction($log_uid,'transaction',$log_k,[
                                    'id'        => $invoice["number"] ? $invoice["number"] : $invoice["id"],
                                    'ln4'       => $stored_card,
                                ]);
                            }
                        }

                    }
                    $items      = self::get_items($invoice_id);
                    foreach($items AS $item) Orders::process($item,$invoice);

                    if($status == "paid")
                    {
                        $invoice_vars = $invoice;
                        $invoice_vars['source'] = self::action_source();
                        Hook::run("InvoicePaid",$invoice_vars);
                    }
                    if($status == "paid" && $notification) Notification::invoice_has_been_approved($invoice);
                }
            }

            Orders::module_process();
            Orders::notification_process();
        }

        static function bill_generate($odata=[],$items=[],$notifications=true,$order_generate=false){
            $user_data      = self::generate_user_data($odata["user_id"]);
            if(!$user_data){
                if(isset($odata["user_data"]) && $odata["user_data"])
                    $user_data = $odata["user_data"];
                else{
                    self::$message = "no-user-address";
                    return false;
                }
            }

            Helper::Load(["Orders","Products"]);

            $getAddress     = $user_data["address"];
            $country        = $getAddress["country_id"];
            $city           = isset($getAddress["city_id"]) ? $getAddress["city_id"] : $getAddress["city"];
            $taxation       = self::getTaxation($country,$user_data["taxation"]);
            $taxation_type  = $odata["taxation_type"] ?? self::getTaxationType();
            $discounts      = isset($odata["discounts"]) ? $odata["discounts"] : [];

            $duedate        = isset($odata["duedate"]) ? $odata["duedate"] : DateManager::Now();

            $pmethod        = $odata["pmethod"];

            $local          = self::isLocal($country,$odata["user_id"]);


            $legal_zero         = isset($odata["legal"]) && !$odata["legal"];
            $btxn               = Config::get("options/balance-taxation");
            if(!$btxn) $btxn = "y";

            if($pmethod == "Balance")
            {
                if($btxn == "y") $legal_zero = true;
                if($btxn == "n") $legal_zero = false;
            }
            if($pmethod && $pmethod == "Free") $legal_zero = true;


            if(isset($odata["legal"])) $legal = $odata["legal"];
            else $legal = $taxation && !$legal_zero ? 1 : 0;

            $tax_rate       = $odata["tax_rate"] ?? self::getTaxRate($country,$city,$odata["user_id"]);

            $currency       = 0;


            if(isset($odata["cid"])) $currency = $odata["cid"];
            if(isset($odata["currency"])) $currency = $odata["currency"];


            $invoice_data   = [
                'user_id'   => $odata["user_id"],
                'user_data' => Utility::jencode($user_data),
                'cdate'     => DateManager::Now(),
                'duedate'   => $duedate,
                'datepaid'  => $odata["status"] == 'paid' ? DateManager::Now() : DateManager::ata(),
                'local'     => $local,
                'legal'     => $legal,
                'status'    => $odata["status"],
                'currency'  => $currency,
                'taxation_type' => $taxation_type,
                'taxrate'   => $tax_rate,
                'tax'       => 0,
                'subtotal'  => 0,
                'total'     => 0,
                'pmethod'   => $pmethod,
                'discounts' => $discounts,
                'unread'    => defined("ADMINISTRATOR") ? 1 : 0,
            ];

            if(!isset($odata["amount"])) $invoice_data = array_merge($invoice_data,$odata);

            if(isset($odata["pmethod"]) && $odata["pmethod"] == "Balance")
            {
                $u_currency  = User::getData($invoice_data["user_id"],"balance_currency")->balance_currency;
                $ucid_choice = $currency == $u_currency ? $currency : $u_currency;
            }
            else
            {
                $ucid_choice    = $user_data["currency"];

                /*
                if(Config::get("general/country") == "tr" && $ucid_choice != 147 && $user_data["address"]["country_id"]==227)
                    $ucid_choice = 147;

                if(Money::Currency(4,true) && Config::get("general/country") == "tr" && $ucid_choice == 147 && $user_data["address"]["country_id"]!=227) $ucid_choice = 4;
                elseif(Money::Currency(5,true) && Config::get("general/country") == "tr" && $ucid_choice == 147 && $user_data["address"]["country_id"]!=227) $ucid_choice = 5;
                */

            }

            $invoice_data["currency"] = $ucid_choice;



            $subtotal       = 0;
            $tax            = 0;
            $total          = 0;


            foreach($items AS $k=>$item){
                $amount     = isset($item["amount"]) ? $item["amount"] : $odata["amount"];
                $t_amount   = isset($item["total_amount"]) ? $item["total_amount"] : $amount;
                $cid        = isset($item["cid"]) ? $item["cid"] : $odata["cid"];
                $taxexempt  = isset($item['taxexempt']) ? (int) $item['taxexempt'] : 0;
                $amount     = Money::exChange($amount,$cid,$ucid_choice);
                $t_amount   = Money::exChange($t_amount,$cid,$ucid_choice);
                $order_id   = isset($item["user_pid"]) ? $item["user_pid"] : 0;
                $_order     = Orders::get($order_id,'type,product_id,period,period_time,options');
                $pricing_type = $_order["options"]["pricing-type"] ?? 1;
                $find_p_price = false;

                if($_order && $pricing_type == 1)
                {
                    $product = Products::get($_order["type"],$_order["product_id"],$user_data["lang"]);
                    if($_order["type"] == "domain") $find_p_price = $product["price"]["renewal"];
                    else
                    {
                        $prices   = $product["price"];
                        if($prices){
                            $find_1     = -1;
                            $find_2     = -1;

                            foreach($prices AS $price){
                                if(isset($_order["options"]["selected_price"]) && $_order["options"]["selected_price"] == $price["id"] && $_order["period"] == $price["period"] && $_order["period_time"] == $price["time"])
                                    $find_1 = $price;
                                if($_order["period"] == $price["period"] && $_order["period_time"] == $price["time"])
                                    $find_2 = $price;
                            }
                            if($find_1 > -1) $find_p_price = $find_1;
                            elseif($find_2 > -1) $find_p_price = $find_2;
                        }
                    }
                    if(!$find_p_price) $pricing_type = 2;
                }

                $o_p_type_2 = $pricing_type == 2;

                if($taxation_type == "inclusive" && !$o_p_type_2)
                {
                    $amount         -= Money::get_inclusive_tax_amount($amount, $tax_rate);
                    $t_amount       -= Money::get_inclusive_tax_amount($t_amount, $tax_rate);
                }

                $total_amount               = $t_amount;
                $items[$k]["amount"]        = $amount;
                $items[$k]["total_amount"]  = $total_amount;
                $items[$k]["amount_including_discount"] = $amount;
                $items[$k]["cid"]           = $ucid_choice;
                $items[$k]["taxexempt"]     = $taxexempt;
            }

            if(isset($odata["sendbta_amount"])){
                $sendbta_amount         = $odata["sendbta_amount"];
                $sendbta_amount         = Money::exChange($sendbta_amount,$currency,$ucid_choice);
                if($taxation_type == "inclusive")
                    $sendbta_amount     -= Money::get_inclusive_tax_amount($sendbta_amount,$tax_rate);
                $sendbta_amount         = round($sendbta_amount,2);
                $invoice_data["sendbta_amount"] = $sendbta_amount;
            }

            if(isset($odata["pmethod_commission"])){
                $pmethod_commission         = $odata["pmethod_commission"];
                $pmethod_commission         = Money::exChange($pmethod_commission,$currency,$ucid_choice);
                $pmethod_commission         = round($pmethod_commission,2);
                $invoice_data["pmethod_commission"] = $pmethod_commission;
            }

            $calculate                          = self::calculate_invoice($invoice_data,$items,[
                'disable_calculate_addons' => $order_generate==true,
            ]);

            if($calculate)
            {
                $subtotal   += $calculate['subtotal'];
                $tax        += $calculate['tax'];
                $total      += $calculate['total'];
            }

            $invoice_data["subtotal"]           = $subtotal;
            $invoice_data["tax"]                = $tax;
            $invoice_data["total"]              = $total;


            $invoice_data_x                     = $invoice_data;
            $invoice_data_x["discounts"] = $invoice_data_x["discounts"] ? Utility::jencode($invoice_data_x["discounts"]) : '';

            if($invoice_data["currency"] != Config::get("general/currency") && !isset($invoice_datax["exchange_rate"]))
            {
                $curr       = Money::Currency($invoice_data["currency"]);
                $rate       = (float) $curr["rate"];
                $exchange   = round((1 / $rate),4);
                if(!$invoice_data_x["data"]) $invoice_data_x["data"]  = ['exchange_rate' => $exchange];
                else $invoice_data_x["data"]["exchange_rate"] = $exchange;
            }
            $invoice_data_x["data"]        = $invoice_data_x["data"] ? Utility::jencode($invoice_data_x["data"]) : '';

            $create                        = self::add($invoice_data_x);

            if(!$create){
                self::$message = "Could not be create invoice";
                return false;
            }

            $invoice        = self::get($create);
            $orders         = [];
            $discounts      = [];

            foreach($items AS $k => $item){
                $description    = isset($item["name"]) ? $item["name"] : $item["description"];
                $rank           = isset($item["rank"]) ? $item["rank"] : 0;

                if(isset($item["process"]) && $item["process"]){
                    $order = Orders::process($item,$invoice);
                    if($order) $orders[] = $order;
                    if($order && !stristr($description,$order["id"])) $description .= " (#".$order["id"].")";
                    elseif(isset($item["options"]["event_data"]["usproduct_id"]) && !stristr($description,$item["options"]["event_data"]["usproduct_id"])) $description .= " (#".$item["options"]["event_data"]["usproduct_id"].")";

                    if(isset($item["options"]["domain"]) && $item["options"]["domain"] && $item["options"]["type"] != "domain")
                        $description .= " (".$item["options"]["domain"].")";

                    if(isset($item["options"]["hostname"]) && $item["options"]["hostname"])
                        $description .= " (".$item["options"]["hostname"].")";

                    if(isset($item["options"]["ip"]) && $item["options"]["ip"])
                        $description .= " - ".$item["options"]["ip"];

                    if($order && isset($order["addons"]) && $order["addons"]) $item["options"]["addons"] = $order["addons"];
                }

                if(isset($order) && $order) $user_pid = $order["id"];
                else $user_pid = isset($item["user_pid"]) ? $item["user_pid"] : 0;

                if(isset($item["options"]) && $item["options"]){
                    if(isset($item["amount_including_discount"]))
                        $item["options"]["amount_including_discount"] = $item["amount_including_discount"];
                    if(isset($item["discounts"])) $item["options"]["discounts"] = $item["discounts"];
                }

                $taxexempt      = isset($item['taxexempt']) ? (int) $item['taxexempt'] : 0;


                if(isset($item["options"]["addons"]) && $item["options"]["addons"])
                {
                    foreach($item["options"]["addons"] AS $addon)
                    {
                        $description .= "\n".$addon['addon_name'].": ".$addon['option_name'];
                    }
                }

                if(isset($item["options"]["selection"]["setup"]) && $item["options"]["selection"]["setup"] > 0.00)
                    $description .= ' + '.Bootstrap::$lang->get_cm("website/osteps/setup-fee",false,($user_data["lang"] ?? false));


                $i_id = self::add_item([
                    'owner_id' => $create,
                    'user_id'  => $odata["user_id"],
                    'user_pid' => $user_pid,
                    'options'  => isset($item["options"]) && $item["options"] ? Utility::jencode($item["options"]) : '',
                    'description' => $description,
                    'amount' => $item["amount"],
                    'total_amount' => $item["total_amount"],
                    'taxexempt' => $taxexempt,
                    'oduedate'  => $duedate,
                    'currency' => $item["cid"],
                    'rank'     => $rank,
                ]);

                if(isset($invoice["discounts"]["items"]) && $invoice["discounts"]["items"])
                    foreach(array_keys($invoice["discounts"]["items"]) AS $g_k)
                        if(isset($invoice["discounts"]["items"][$g_k][$k]))
                            $discounts["items"][$g_k][$i_id] = $invoice["discounts"]["items"][$g_k][$k];

            }

            self::set($invoice["id"],['discounts' => $discounts ? Utility::jencode($discounts) : '']);

            $invoice["discounts"] = $discounts;

            if(sizeof($orders)==1) $invoice["order_id"] = $orders[0]["id"];

            if(!defined("CREATE_ORDER_INVOICE_DELETED") && $invoice["status"] == "paid" && ($invoice["pmethod"] != "Balance" || ($invoice["pmethod"] == "Balance" && Config::get("options/balance-taxation") == "n"))){
                $getInex    = self::get_inex(false,$invoice["id"]);
                if(!$getInex && $invoice["total"]>0)
                    self::insert_inex([
                        'invoice_id' => $invoice["id"],
                        'type' => "income",
                        'amount' => $invoice["total"]>0 ? $invoice["total"] : $invoice["subtotal"],
                        'currency' => $invoice["currency"],
                        'cdate' => substr($invoice["datepaid"],0,4) > 1991 ? $invoice["datepaid"] : DateManager::Now(),
                        'description' => Bootstrap::$lang->get("needs/invoice",Config::get("general/local"))." ".$invoice["number"],
                    ]);
            }

            Orders::module_process($notifications);
            Orders::notification_process($notifications);

            if(!defined("CREATE_ORDER_INVOICE_DELETED"))
            {
                $invoice_vars = $invoice;
                $invoice_vars['source'] = self::action_source();
                Hook::run("InvoiceCreated",$invoice_vars);
                if($invoice["status"] == "paid") Hook::run("InvoicePaid",$invoice_vars);
            }

            return $invoice;
        }

        static function add($data){
            if(isset($data["legal"])) $data["legal"] = (int) $data["legal"];
            if(isset($data["local"])) $data["local"] = (int) $data["local"];

            $insert          = (int) Models::$init->db->insert("invoices",$data) ? Models::$init->db->lastID() : false;
            $invoice_id      = $insert;
            $prefix1         = isset($data["status"]) && $data["status"] == "paid" ? "-paid" : '';
            $prefix2         = isset($data["status"]) && $data["status"] == "paid" ? "paid-" : '';

            if(!isset($data["number"]) && $insert)
            {
                $invoice_id_fake = Config::getd("invoice-id".$prefix1);

                $i_n_f      = Config::get("options/".$prefix2."invoice-num-format");
                $i_n_f_s    = Config::get("options/".$prefix2."invoice-num-format-status");
                if(!$i_n_f_s) $i_n_f = false;
                if(!$i_n_f) $i_n_f = '#{NUMBER}';

                if($i_n_f_s && strlen($invoice_id_fake) > 0) $insert = ($invoice_id_fake);

                if(stristr($i_n_f,'{NUMBER}'))
                {
                    $i_n_f = str_replace(
                        [
                            "{NUMBER}",
                            "{DAY}",
                            "{MONTH}",
                            "{YEAR}"
                        ],
                        [
                            $insert,
                            DateManager::Now("d"),
                            DateManager::Now("m"),
                            DateManager::Now("Y")
                        ],$i_n_f);
                    Models::$init->db->update("invoices",['number' => $i_n_f])->where("id","=",$invoice_id)->save();
                    if($i_n_f_s) Config::setd("invoice-id".$prefix1,($insert + 1));
                }
            }

            self::$added[] = $invoice_id;
            return $invoice_id;
        }

        static function add_item($data){
            if(isset($data["options"]) && strlen($data["options"]) < 1) $data["options"] = NULL;
            return Models::$init->db->insert("invoices_items",$data) ? Models::$init->db->lastID() : false;
        }

        static function set_item($id=0,$data=false){
            if(isset($data["options"]) && strlen($data["options"]) < 1) $data["options"] = NULL;
            return Models::$init->db->update("invoices_items",$data)->where("id","=",$id)->save();
        }

        static function delete_item($id=0){
            return Models::$init->db->delete("invoices_items")->where("id","=",$id)->run();
        }

        static function get_items($id=0){
            $data   = Models::$init->db->select()->from("invoices_items")->where("owner_id","=",$id);
            $data->order_by("rank ASC,id ASC");
            $data   = $data->build() ? Models::$init->db->fetch_assoc() : false;
            if($data){
                $keys = array_keys($data);
                $size = sizeof($keys)-1;
                for($i=0;$i<=$size;$i++){
                    $var = $data[$keys[$i]];
                    $data[$keys[$i]]["options"] = $var["options"] == '' ? [] : Utility::jdecode($var["options"],true);
                }
            }
            return $data;
        }

        static function bring_item($owner_id=0,$id=0,$desc='',$amount=0){
            $data   = Models::$init->db->select()->from("invoices_items");
            if($desc && $amount){
                $data->where("owner_id","=",$owner_id,"&&");
                $data->where("(");
                $data->where("description","=",$desc,"&&");
                $data->where("amount","=",$amount);
                $data->where(")");
            }else{
                $data->where("id","=",$id);
            }

            return $data->build() ? $data->getAssoc() : false;
        }

        static function item_listing($id=0){

            if(is_array($id)){
                $invoice = $id;
                $id      = $invoice["id"];
            }else
                $invoice = self::get($id);

            $data   = Models::$init->db->select()->from("invoices_items")->where("owner_id","=",$id);
            $data->order_by("rank ASC,id ASC");
            $data   = $data->build() ? Models::$init->db->fetch_assoc() : false;
            $ndata  = [];
            if($data){
                foreach($data AS $datum){
                    $datum["options"] = Utility::jdecode($datum["options"],true);
                    $item = $datum;
                    $ndata[$datum["id"] ?? ''] = $item;
                }
                $data   = $ndata;
            }else $data = [];
            return $data;
        }

        static function reduce_coupon($id=0){
            return Models::$init->db->update("coupons")->set(['uses' => "uses+1"],true)->where("id","=",$id)->save();
        }

        static function reduce_promotion($id=0){
            return Models::$init->db->update("promotions")->set(['uses' => "uses+1"],true)->where("id","=",$id)->save();
        }

        static function search_pmethod_msg($word='',$status=''){
            $stmt = Models::$init->db->select("id")->from("invoices");
            if($status) $stmt->where("status","=",$status,"&&");
            $stmt->where("pmethod_msg","LIKE","%".$word."%");
            return $stmt->build() ? $stmt->getObject()->id : false;
        }

        static function get_total_unpaid_invoices_amount($user_id=0,$currency=0){
            $total_amount = 0;
            if(!$currency) $currency = Money::getUCID();
            $stmt   = Models::$init->db->select("SUM(total) AS total,currency")->from("invoices")->where("user_id","=",$user_id,"&&");
            $stmt->where("status","=","unpaid");
            $stmt->group_by("currency");
            $result   = $stmt->build() ? $stmt->fetch_assoc() : false;
            foreach($result AS $row) $total_amount += Money::exChange($row["total"],$row["currency"],$currency);
            return $total_amount;
        }

        static function calculate_invoice(array $attr = [],array $items = [],$options=[])
        {
            Helper::Load("Products");
            $taxation_t = isset($attr['taxation_type']) ? $attr['taxation_type'] : self::getTaxationType();
            $sendbta    = isset($attr['sendbta_amount']) ? round((float) $attr['sendbta_amount'],2) : 0;
            $pcomsn     = isset($attr['pmethod_commission']) ? $attr['pmethod_commission'] : 0;
            $pmethod    = isset($attr['pmethod']) ? $attr['pmethod'] : '';
            $discounts  = isset($attr['discounts']) ? $attr['discounts'] : [];
            $tax_rate   = isset($attr['taxrate']) ? $attr['taxrate'] : 0;
            $cid        = isset($attr['currency']) ? $attr['currency'] : (isset($attr['cid']) ? $attr['cid'] : 0);
            $subtotal   = 0;
            $tax        = 0;
            $total      = 0;
            $d_addons   = isset($options["disable_calculate_addons"]) && $options["disable_calculate_addons"];


            if(isset($attr['legal']) && !$attr['legal']) $tax_rate = 0;

            if($items)
            {
                $new_items = [];
                foreach($items AS $i_id => $item)
                {
                    $_cid   = isset($item['currency']) ? $item['currency'] : (isset($item['cid']) ? $item['cid'] : 0);
                    if(!$_cid) $_cid = $cid;

                    $a_total = isset($item['amount']) ? $item['amount'] : 0;
                    if(isset($item['total_amount'])) $a_total = $item['total_amount'];


                    $w_privacy = false;
                    ## CALCULATE ADDONS START ##
                    if(isset($item["options"]["addons"]) && $item["options"]["addons"] && !$d_addons)
                    {
                        foreach($item["options"]["addons"] AS $ad=>$selected){
                            if(isset($selected["addon_key"]) && $selected["addon_key"] == "whois-privacy") $w_privacy = true;
                            $addon  = $ad > 0 ? Products::addon($ad) : false;
                            if($addon){
                                $adopts = $addon["options"];
                                foreach($adopts AS $opt){
                                    if($selected == $opt["id"]){
                                        $adamount     = $opt["amount"];
                                        if($taxation_t == "inclusive")
                                            $adamount -= Money::get_inclusive_tax_amount($adamount,$tax_rate);
                                        $adamount = Money::exChange($adamount,$opt["cid"],$_cid);
                                        $a_total += $adamount;
                                    }
                                }
                            }
                        }
                    }
                    ## CALCULATE ADDONS END ##

                    /*
                    ## CALCULATE WHO IS PRIVACY START
                    $p_type = isset($item["options"]["type"]) ? $item["options"]["type"] : '';
                    $p_id   = isset($item["options"]["id"]) ? $item["options"]["id"] : 0;

                    $product = Products::get($p_type,$p_id);
                    if($product && $w_privacy)
                    {
                        if($product["module"] != "none"){
                            $rgstrModule        = Modules::Load("Registrars",$product["module"],true);
                            $whidden_amount     = $rgstrModule["config"]["settings"]["whidden-amount"];
                            $whidden_cid        = $rgstrModule["config"]["settings"]["whidden-currency"];
                        }
                        else{
                            $whidden_amount     = Config::get("options/domain-whois-privacy/amount");
                            $whidden_cid        = Config::get("options/domain-whois-privacy/cid");
                        }

                        if($whidden_amount > 0.00)
                        {
                            $whidden_price  = Money::exChange($whidden_amount,$whidden_cid,$_cid);
                            if($taxation_t == "inclusive")
                                $whidden_price -= Money::get_inclusive_tax_amount($whidden_price,$tax_rate);
                            $a_total += $whidden_price;
                        }
                    }
                    ## CALCULATE WHO IS PRIVACY END
                    */
                    // Add subtotal amount
                    if($taxation_t == "exclusive") $a_total = round($a_total,4);



                    $subtotal += $a_total;

                    ## INSERT TAX START ##
                    if($tax_rate > 0.0 && (!isset($item['taxexempt']) || !$item['taxexempt']))
                    {
                        $a_tax      = Money::get_exclusive_tax_amount($a_total,$tax_rate);
                        $tax        += $a_tax;
                    }
                    ## INSERT TAX END ##
                    if(isset($item["id"]) && $item["id"]) $new_items[$item["id"]] = $item;
                }
                if($new_items) $items = $new_items;
            }

            if(($attr["sendbta"] ?? false) && $sendbta > 0.0)
            {

                // Add subtotal amount
                $subtotal += $sendbta;

                ## INSERT TAX START ##
                if($tax_rate > 0.0)
                {
                    $a_tax      = Money::get_tax_amount($sendbta,$tax_rate);
                    $tax        += $a_tax;
                }
                ## INSERT TAX END ##
            }

            if($pcomsn > 0.0 && $pmethod != "BankTransfer" && $pmethod != "Balance")
            {

                // Add subtotal amount
                if($taxation_t == "exclusive") $pcomsn = round($pcomsn,2);
                $subtotal += $pcomsn;

                ## INSERT TAX START ##
                if($tax_rate > 0.0)
                {
                    $a_tax      = Money::get_tax_amount($pcomsn,$tax_rate);
                    $tax        += $a_tax;
                }
                ## INSERT TAX END ##
            }

            ## CALCULATE DISCOUNT START ##
            $d_total        = 0;
            $t_d_total      = 0;
            if(isset($discounts['items']) && $d_items = $discounts['items'])
            {
                foreach($d_items AS $d_v_k => $d_v_s)
                {
                    if($d_v_s)
                    {
                        foreach($d_v_s AS $d_v_i_k => $d_v_i)
                        {
                            $d_total += $d_v_i['amountd'];
                            $tax_exempt     = isset($items[$d_v_i_k]["taxexempt"]) && $items[$d_v_i_k]["taxexempt"];
                            if(isset($items[$d_v_i_k]) && !$tax_exempt)
                                $t_d_total += $d_v_i['amountd'];
                            // We put this because there would be a calculation error in cases where it was not clear which item the discount belonged to, especially if the imported invoices included tax.
                            elseif($taxation_t == "inclusive" && !$tax_exempt && !isset($items[$d_v_i_k]))
                                $t_d_total += $d_v_i['amountd'];


                        }
                    }
                }
            }
            ## CALCULATE DISCOUNT END ##


            $total += $subtotal;

            if($d_total > 0.0) $total -= $d_total;
            if($t_d_total > 0.00 && $tax > 0.00) $tax -= Money::get_exclusive_tax_amount($t_d_total,$tax_rate);


            $legal_zero         = false;
            $btxn               = Config::get("options/balance-taxation");
            if(!$btxn) $btxn = "y";

            if($pmethod == "Balance")
            {
                if($btxn == "y") $legal_zero = true;
                if($btxn == "n") $legal_zero = false;
            }
            if($pmethod && $pmethod == "Free") $legal_zero = true;

            if(!$legal_zero) $total += $tax;
            else $tax = 0;

            $subtotal   = round($subtotal,4);
            $tax        = round($tax,4);
            $total      = round($total,4);

            if(isset($options['included_d_subtotal']) && $d_total > 0.0)
            {
                $subtotal   -= $d_total;
                $subtotal   = round($subtotal,4);
            }

            if($subtotal < 0.00) $subtotal = 0;
            if($tax < 0.00) $tax = 0;
            if($total < 0.00) $total = 0;
            if($d_total < 0.00) $d_total = 0;



            $returnData = [
                'subtotal'      => $subtotal,
                'tax'           => $tax,
                'total'         => $total,
            ];

            if(isset($options['discount_total'])) $returnData['discount_total'] = $d_total;

            return $returnData;
        }

        static function detect_auto_price_and_save($udata=[],$invoice=[],$items=[])
        {
            Helper::Load(["Orders","Products"]);
            $currency           = $invoice['currency'];
            $check_change       = false;
            $discounts          = $invoice["discounts"];


            if($items){
                foreach($items AS $i=>$item){
                    $pricing_type   = 2;
                    $amount         = $item["total_amount"];
                    $item_opt       = $item["options"];
                    $item_id        = $item["id"];


                    if(!isset($item["options"]["event"])) continue;
                    if($item_opt["event"] == "addCredit") continue;

                    if($item_opt["event"] == "ExtendOrderPeriod" || $item_opt["event"] == "RenewalDomain"){
                        if(!$item["user_pid"]) continue;
                        $order  = Orders::get($item["user_pid"],"id,type,product_id,period,period_time,amount,amount_cid,options");
                        if(!$order) continue;
                        $product    = Products::get($order["type"],$order["product_id"],$udata["lang"]);
                        if(!$product) continue;

                        $ordOpt         = $order["options"];
                        $pricing_type   = isset($ordOpt["pricing-type"]) ? $ordOpt["pricing-type"] : 1;

                        if($pricing_type == 2)
                            $amount = Money::exChange($order["amount"],$order["amount_cid"],$currency);

                        if($order["type"] == "domain"){
                            $price    = $product["price"]["renewal"];
                            if($pricing_type == 1)
                                $amount   = Money::exChange($price["amount"],$price["cid"],$currency) * $item_opt['event_data']['year'];
                        }
                        else{
                            $prices   = $product["price"];
                            if($prices){
                                $find_1     = -1;
                                $find_2     = -1;

                                foreach($prices AS $price){
                                    if(isset($item_opt["event_data"]["period"]) && $item_opt["event_data"]["period"] != $order["period"])
                                    {
                                        $order["period"] = $item_opt["event_data"]["period"];
                                        $order["period_time"] = $item_opt["event_data"]["period_time"];
                                    }
                                    elseif(isset($item_opt["period"]) && $item_opt["period"])
                                    {
                                        $order["period"] = $item_opt["period"];
                                        $order["period_time"] = $item_opt["period_time"];
                                    }

                                    if(isset($ordOpt["selected_price"]) && $ordOpt["selected_price"] == $price["id"] && $order["period"] == $price["period"] && $order["period_time"] == $price["time"])
                                        $find_1 = Money::exChange($price["amount"],$price["cid"],$currency);
                                    if($order["period"] == $price["period"] && $order["period_time"] == $price["time"])
                                        $find_2 = Money::exChange($price["amount"],$price["cid"],$currency);
                                }
                                if($pricing_type == 1){
                                    if($find_1 > -1) $amount = $find_1;
                                    elseif($find_2 > -1) $amount = $find_2;
                                    else $amount = Money::exChange($order["amount"],$order["amount_cid"],$currency);
                                }
                            }
                        }
                    }
                    elseif($item_opt["event"] == "ExtendAddonPeriod"){
                        $order    = Orders::get_addon($item_opt["event_data"]["addon_id"]);
                        if(!$order) continue;
                        $product  = Products::addon($order["addon_id"],$udata["lang"]);
                        if(!$product) continue;
                        if($product["options"]){
                            foreach($product["options"] AS $option){
                                if($order["option_id"] == $option["id"]){
                                    $amount         = Money::exChange($option["amount"],$option["cid"],$currency);
                                    $addon_val      = 0;

                                    if($order["option_quantity"] > 0)
                                        $addon_val = $order["option_quantity"];
                                    elseif($product["type"] == "quantity" && stristr($order["option_name"],'x '))
                                    {
                                        $split = explode("x ",$order["option_name"]);
                                        $addon_val = (int) $split[0] ?? 0;
                                    }
                                    if($addon_val > 0) $amount = ($amount * $addon_val);
                                    $pricing_type = 1;
                                }
                            }
                        }
                    }
                    elseif($item_opt["event"] == "AddonOrder"){
                        $product  = Products::addon($item_opt["event_data"]["addon_id"],$udata["lang"]);
                        if(!$product) continue;
                        if($product["options"]){
                            foreach($product["options"] AS $option){
                                if($item_opt["event_data"]["option_id"] == $option["id"]){
                                    $amount = Money::exChange($option["amount"],$option["cid"],$currency);
                                    $option_quantity = $item_opt["event_data"]["option_quantity"];
                                    if($option_quantity > 0) $amount = ($amount * $option_quantity);
                                    $pricing_type = 1;
                                }
                            }
                        }
                    }

                    $o_p_type_2 = $pricing_type == 2;

                    if($invoice["taxation_type"] == "inclusive" && !$o_p_type_2)
                        $amount -= Money::get_inclusive_tax_amount($amount,$invoice["taxrate"]);

                    if($item["total_amount"] != round($amount,4)){
                        if($discounts)
                        {
                            foreach($discounts["items"] AS $d_t_k => $d_t_v)
                            {
                                if(isset($d_t_v[$item_id]))
                                {
                                    $d_body = $d_t_v[$item_id];
                                    $d_rate = $d_body["rate"];
                                    $new_d_a = Money::get_discount_amount($amount,$d_rate);
                                    $new_d_a_f = Money::formatter_symbol($new_d_a,$invoice["currency"]);
                                    $discounts['items'][$d_t_k][$item_id]['amountd'] = $new_d_a;
                                    $discounts['items'][$d_t_k][$item_id]['amount'] = $new_d_a_f;
                                }
                            }
                        }
                        $check_change = true;
                        $items[$i]["amount"] = $amount;
                        $items[$i]["total_amount"] = $amount;
                        self::set_item($item["id"],['amount' => $amount,'total_amount' => $amount]);
                    }
                }
            }
            if($check_change){
                $invoice["discounts"] = $discounts;
                $calculate = self::calculate_invoice($invoice,$items);
                self::set($invoice["id"],[
                    'discounts'         => $discounts ? Utility::jencode($discounts) : '',
                    'subtotal'          => $calculate['subtotal'],
                    'tax'               => $calculate['tax'],
                    'total'             => $calculate['total'],
                ]);

                $invoice['subtotal']    = $calculate['subtotal'];
                $invoice['tax']         = $calculate['tax'];
                $invoice['total']       = $calculate['total'];

                return ['attr' => $invoice,'items' => $items];
            }
            return false;
        }

        static function generate_renewal_bill($order_type='order',$udata=[],$row=[])
        {
            Helper::Load("User");
            $is_dealership_discount = [];
            $taxexempt  = 0;

            $user_data = self::generate_user_data($udata["id"]);

            $amount         = $row["amount"];
            $currency       = $row["cid"];
            $first_amount   = $amount;
            $pricing_type   = 1;
            $find_p_amount  = 0;


            $do_not_equal       = $row["do_not_equal"] ?? false;

            if(isset($row["subscription_id"]) && $row["subscription_id"])
            {
                $sub        = Orders::get_subscription($row["subscription_id"]);
                if($sub && $sub["status"] != "cancelled")
                    $do_not_equal = true;
            }
            else
                $sub = false;


            if($order_type == "addon"){
                $addon  = $row["addon_id"] ? Products::addon($row["addon_id"],$udata["lang"]) : false;
                if($addon){
                    if($addon["options"]){
                        foreach($addon["options"] AS $option){
                            if($row["option_id"] == $option["id"]){
                                $amount = Money::exChange($option["amount"],$option["cid"],$currency);
                                if($addon["type"] == "quantity" || $row["option_quantity"]>0){
                                    if($row["option_quantity"] > 0)
                                        $addon_val = $row["option_quantity"];
                                    elseif(stristr($row["option_name"],"x "))
                                    {
                                        $split          =  explode("x ",$row["option_name"]);
                                        $addon_val      = (int) $split[0] ?? 0;
                                    }
                                    if($addon_val > 0) $amount = ($amount * $addon_val);
                                }
                            }
                        }
                    }
                }

            }
            elseif($order_type == "order"){
                $product    = Products::get($row["type"],$row["product_id"],$udata["lang"]);
                $rowOpt     = Utility::jdecode($row["options"],true);

                $pricing_type = isset($rowOpt["pricing-type"]) ? $rowOpt["pricing-type"] : 1;
                if($sub)
                {
                    $md     =Modules::Load("Payment",$sub["module"],true);
                    $mc     = $md["config"];
                    $csf    = $mc["settings"]["change_subscription_fee"] ?? false;
                    if(!$csf) $pricing_type = 2;
                }

                if($product){
                    if($row["type"] == "domain"){
                        if($pricing_type == 1){
                            $price    = $product["price"]["renewal"];
                            $amount   = Money::exChange($price["amount"],$price["cid"],$currency) * $row["period_time"];
                            $find_p_amount = $amount;
                        }
                    }
                    else{
                        if(isset($product["taxexempt"]) && $product["taxexempt"]) $taxexempt = 1;
                        $prices   = $product["price"];
                        if($prices){
                            $find_1     = -1;
                            $find_2     = -1;

                            foreach($prices AS $price){
                                if(isset($rowOpt["selected_price"]) && $rowOpt["selected_price"] == $price["id"] && $row["period"] == $price["period"] && $row["period_time"] == $price["time"])
                                    $find_1 = Money::exChange($price["amount"],$price["cid"],$currency);
                                if($row["period"] == $price["period"] && $row["period_time"] == $price["time"])
                                    $find_2 = Money::exChange($price["amount"],$price["cid"],$currency);
                            }

                            if($pricing_type == 1){
                                if($find_1 > -1)
                                {
                                    $amount = $find_1;
                                    $find_p_amount = $amount;
                                }
                                elseif($find_2 > -1)
                                {
                                    $amount = $find_2;
                                    $find_p_amount = $amount;
                                }
                            }

                        }
                    }
                    if(Config::get("options/dealership/status") && $udata["dealership"]){
                        $dealership = Utility::jdecode($udata["dealership"],true);
                        if($dealership && isset($dealership["status"]) && $dealership["status"] == "active"){
                            $u_dealership   = $dealership;
                            $dealership     = Config::get("options/dealership");
                            $discounts      = $dealership["rates"];

                            if(isset($u_dealership["discounts"]) && $u_dealership["discounts"])
                                if(is_array(current($u_dealership["discounts"])))
                                    $discounts = array_replace_recursive($discounts,$u_dealership["discounts"]);

                            if(isset($dealership["require_min_discount_amount"]) && $dealership["require_min_discount_amount"] > 0.00){
                                $rqmcdt     = $dealership["require_min_discount_amount"];
                                $rqmcdt_cid = $dealership["require_min_discount_cid"];
                                $myBalance  = Money::exChange($udata["balance"],$udata["balance_currency"],$rqmcdt_cid);
                                if($myBalance < $rqmcdt) $discounts = [];
                            }

                            $o_quantity     = sizeof(User::dealership_orders($udata["id"],$discounts));
                            $d              = Products::find_in_rates($product,$discounts,$o_quantity,$udata["lang"]);


                            if($discounts && $d)
                            {
                                $amount_by_discount = $amount;
                                if(Invoices::getTaxationType() == "inclusive")
                                {
                                    $tax_rate = Invoices::getTaxRate($user_data["address"]["country_id"],$user_data["address"]["city_id"] ?? $user_data["address"]["city"],$user_data["id"] ?? 0);
                                    if($tax_rate > 0.00)
                                        $amount_by_discount -= Money::get_inclusive_tax_amount($amount_by_discount,$tax_rate);
                                }

                                $discount_amount = round(Money::get_discount_amount($amount_by_discount,$d["rate"]),2);
                                $is_dealership_discount = [
                                    'rate' => $d["rate"],
                                    'name' => $d["name"],
                                    'dkey' => $d["k"],
                                    'amountd' => $discount_amount,
                                ];
                            }


                        }
                    }
                }
            }
            if($pricing_type == 1 && $find_p_amount < 0.01) $pricing_type = 2;


            if($order_type == "addon")
                $lastInvoice        = self::get_last_user_invoice($udata["id"],false,"t1.user_id,t1.user_data");
            elseif($order_type == "order")
                $lastInvoice        = self::get_last_invoice($row["id"],false,"t2.user_id,t2.user_data");
            else
                $lastInvoice = [];

            if(($lastInvoice ?? false) && $lastInvoice["user_id"] != $udata["id"])
                $lastInvoice        = self::get_last_user_invoice($udata["id"],false,"t1.user_data");

            if($lastInvoice ?? false) $lastInvoice["user_data"] = Utility::jdecode($lastInvoice["user_data"],true);

            if($amount < 0.1)
            {
                if($order_type == "addon")
                {
                    if($first_amount < 0.01)
                    {
                        if(DEVELOPMENT)
                            Orders::add_history(0,$row["owner_id"],'Invoice could not be generated, because the addon(#'.$row["id"].') is new period amount is below 0.1 units',[
                                'first_amount' => $first_amount
                            ]);

                        return 'continue';
                    }
                    $amount = $first_amount;
                }
                else
                {
                    $previously   = WDB::select("id")->from("events");
                    $previously->where("owner","=","order","&&");
                    $previously->where("owner_id","=",$row["id"],"&&");
                    $previously->where("name","LIKE","%Invoice could not be generated because%","&&");
                    $previously->where("cdate","LIKE","%".DateManager::Now("Y-m")."-%","&&");
                    $previously->where("type","=","log");
                    $previously->order_by("id DESC");
                    $previously = $previously->build() ? $previously->getObject()->id : 0;
                    if(!$previously)
                        Orders::add_history(0,$row["id"],'Invoice could not be generated because the new period amount is below 0.1 units',[
                            'first_amount' => $first_amount
                        ]);

                    return 'continue';
                }
            }


            $idata  = [
                'duedate'   => $row["duedate"],
                'user_id'   => $udata["id"],
                'status'    => "unpaid",
                'pmethod'   => $row["select_pmethod"] ?? "none",
                'amount'    => $amount,
                'cid'       => $currency,
                'user_data' => $user_data && is_array($user_data) ? $user_data : (isset($lastInvoice) && $lastInvoice ? $lastInvoice["user_data"] : []),
            ];

            if($order_type == "addon")
                $order      = Orders::get($row["owner_id"]);
            elseif($order_type == "order")
                $order      = $row;

            $order_name   = Orders::detail_name($order);

            $s_period       = $row["period"];
            $s_duration     = $row["period_time"];
            $s_format       = Config::get("options/date-format");
            if($s_period == 'hour') $s_format .= " - H:i";

            $s_start        = DateManager::format($s_format,$row["duedate"]);
            $s_end          = DateManager::next_date([$row["duedate"], $s_period => $s_duration],$s_format);

            ## Whois Privacy Disable Checking START
            if($order_type == "addon" && $order["type"] == "domain")
            {
                $get_o_addon = Orders::get_addon($row["id"],'addon_key');
                if($get_o_addon["addon_key"] == "whois-privacy")
                {
                    if(!isset($order["options"]["whois_privacy"]) || !$order["options"]["whois_privacy"]) return 'continue';

                }
            }

            ## Whois Privacy Disable Checking END

            if($order_type == "addon"){
                $desc = Bootstrap::$lang->get_cm("website/cronjobs/period-extend-addon",[
                    '{order_name}'  => $order_name,
                    '{addon_name}'  => $row["addon_name"],
                    '{option_name}' => $row["option_name"],
                    '{period}'      => Orders::detail_period($row,$udata["lang"]),
                    '{start}'       => $s_start,
                    '{end}'         => $s_end,
                ],$udata["lang"]);

            }
            elseif($order_type == "order"){
                if($row["type"] == "domain")
                    $desc = Bootstrap::$lang->get_cm("website/cronjobs/period-extend-domain",[
                        '{domain}' => $order_name,
                        '{year}' => $row["period_time"],
                        '{period}'      => Orders::detail_period($row,$udata["lang"]),
                        '{start}'       => $s_start,
                        '{end}'         => $s_end,
                    ],$udata["lang"]);
                else
                    $desc = Bootstrap::$lang->get_cm("website/cronjobs/period-extend-order",[
                        '{name}' => $order_name,
                        '{period}' => Orders::detail_period($row,$udata["lang"]),
                        '{start}'       => $s_start,
                        '{end}'         => $s_end,
                    ],$udata["lang"]);
            }

            $item         = [
                'process' => true,
                'name' => $desc,
                'user_pid' => $order["id"],
            ];

            if($order_type == "addon"){
                $item['options']['event']  = "ExtendAddonPeriod";
                $item['options']['event_data']['addon_id']  = $row["id"];
            }
            elseif($order_type == "order" && $row["type"] == "domain"){
                $item['options']['event']  = "RenewalDomain";
                $item['options']['event_data']['year']  = $row["period_time"];
            }
            elseif($order_type == "order"){
                $item['options']['event']  = "ExtendOrderPeriod";
            }

            if($row["period"] == "hour"){
                $due_date           = DateManager::format("Y-m-d H:i",$row["duedate"]);
                $equal_invoice =self::equal_to_duedate_invoice($due_date, $udata["id"]);
            }
            else{
                $due_date           = DateManager::format("Y-m-d",$row["duedate"]);
                $equal_invoice      = self::equal_to_duedate_invoice($due_date,$udata["id"]);
            }



            if($equal_invoice && !$do_not_equal){
                $equal_invoice  = self::get($equal_invoice);
                $eqbill_items   = self::get_items($equal_invoice["id"]);
                $last_item      = end($eqbill_items);

                $description    = $item["name"];
                $quantity       = isset($item["quantity"]) ? $item["quantity"] : 1;
                $rank           = $last_item["rank"]+1;

                if($is_dealership_discount)
                {
                    $is_dealership_discount['amountd'] = Money::exChange($is_dealership_discount['amountd'],$currency,$equal_invoice["currency"]);
                    $is_dealership_discount['amount'] = Money::formatter_symbol($is_dealership_discount['amountd'],$equal_invoice["currency"]);
                }

                $amount     = Money::exChange($amount,$currency,$equal_invoice["currency"]);
                $currency   = $equal_invoice["currency"];
                $user_pid   = isset($item["user_pid"]) ? $item["user_pid"] : 0;

                $_order     = Orders::get($user_pid,'options');
                $o_p_type_2 = isset($_order["options"]["pricing-type"]) && $_order["options"]["pricing-type"] == 2;

                if($equal_invoice["taxation_type"] == "inclusive" && !$o_p_type_2)
                    $amount -= Money::get_inclusive_tax_amount($amount,$equal_invoice["taxrate"]);


                $new_item_id = self::add_item([
                    'owner_id' => $equal_invoice["id"],
                    'user_id'  => $equal_invoice["user_id"],
                    'user_pid' => $user_pid,
                    'options'  => isset($item["options"]) && $item["options"] ? Utility::jencode($item["options"]) : '',
                    'description' => $description,
                    'amount'    => $amount,
                    'total_amount' => $amount,
                    'quantity' => $quantity,
                    'currency' => $currency,
                    'taxexempt' => $taxexempt,
                    'oduedate'  => $due_date,
                    'rank'     => $rank,
                ]);

                $discounts      = $equal_invoice["discounts"];

                if($is_dealership_discount){
                    $discounts['items']['dealership'][$new_item_id]  = $is_dealership_discount;
                    $equal_invoice['discounts'] = $discounts;
                }

                $items          = self::get_items($equal_invoice["id"]);

                $calculate      = self::calculate_invoice($equal_invoice,$items);

                $subtotal       = $calculate['subtotal'];
                $tax            = $calculate['tax'];
                $total          = $calculate['total'];

                self::set($equal_invoice["id"],[
                    'discounts' => $discounts ? Utility::jencode($discounts) : '',
                    'subtotal'  => $subtotal,
                    'tax'       => $tax,
                    'total'     => $total,
                ]);

                $invoice        = self::get($equal_invoice["id"]);
                $last_i         = current($items);
            }
            else
            {
                $item['taxexempt'] = $taxexempt;
                $invoice    = self::bill_generate($idata,[$item]);
                $old_c      = $currency;
                $currency   = $invoice["currency"];

                if($invoice){
                    $items       = self::get_items($invoice['id']);
                    $last_i      = current($items);

                    if($is_dealership_discount)
                    {
                        $discounts   = $invoice["discounts"];

                        $is_dealership_discount['amountd'] = Money::exChange($is_dealership_discount['amountd'],$old_c,$currency);
                        $is_dealership_discount['amount'] = Money::formatter_symbol($is_dealership_discount['amountd'],$currency);


                        $discounts['items']['dealership'][$last_i['id']]  = $is_dealership_discount;
                        $invoice['discounts'] = $discounts;

                        $calculate      = self::calculate_invoice($invoice,$items);

                        self::set($invoice['id'],[
                            'discounts' => $discounts ? Utility::jencode($discounts) : '',
                            'subtotal'  => $calculate['subtotal'],
                            'tax'       => $calculate['tax'],
                            'total'     => $calculate['total'],
                        ]);
                        $invoice['subtotal']  = $calculate['subtotal'];
                        $invoice['tax']       = $calculate['tax'];
                        $invoice['total']     = $calculate['total'];
                    }
                }
            }

            if(isset($last_i) && $last_i && isset($items) && $items) {
                Helper::Load("Coupon");
                $rc     = Coupon::select_renewal_coupon_for_order($row["id"]);
                if($rc) {
                    $uCoupons       = $invoice["used_coupons"] ?? '';
                    $discounts      = $invoice["discounts"] ?: [];

                    if($uCoupons) $uCoupons = explode(",",$uCoupons);
                    else $uCoupons = [];
                    if(!in_array($rc["id"],$uCoupons)) $uCoupons[] = $rc["id"];

                    $discounts["used_coupons"] = $uCoupons;
                    $uCoupons           = implode(",",$uCoupons);

                    if($rc["type"] == "percentage") {
                        $dValue = $rc["rate"]."%";
                        $dAmount = Money::get_discount_amount($last_i["amount"],$rc["rate"]);
                    }
                    else {
                        $dA         = $rc["amount"];
                        $dC         = $rc["currency"];
                        $dValue     = Money::formatter_symbol($dA,$dC);
                        $dAmount    = Money::exChange($dA,$dC,$invoice["currency"]);
                    }
                    $dAmountFormatted = Money::formatter_symbol($dAmount,$invoice["currency"]);
                    $discounts["items"]["coupon"][$last_i["id"]] = [
                        'id'        => $rc["id"],
                        'name'      => $rc["code"],
                        'rate'      => $rc["rate"],
                        'dvalue'    => $dValue,
                        'amountd'   => $dAmount,
                        'amount'    => $dAmountFormatted,
                    ];
                    $invoice["used_coupons"] = $uCoupons;
                    $invoice['discounts'] = $discounts;
                    $calculate      = self::calculate_invoice($invoice,$items);

                    self::set($invoice['id'],[
                        'used_coupons' => $uCoupons,
                        'discounts' => $discounts ? Utility::jencode($discounts) : '',
                        'subtotal'  => $calculate['subtotal'],
                        'tax'       => $calculate['tax'],
                        'total'     => $calculate['total'],
                    ]);

                    $invoice['subtotal']  = $calculate['subtotal'];
                    $invoice['tax']       = $calculate['tax'];
                    $invoice['total']     = $calculate['total'];
                }
            }

            return $invoice;
        }

        static function equal_to_duedate_invoice($date='',$user_id=0){
            $stmt   = Models::$init->db->select("id")->from("invoices");
            $stmt->where("user_id","=",$user_id,"&&");
            $stmt->where("duedate","LIKE","%".$date."%","&&");
            $stmt->where("status","=","unpaid","");
            $stmt->order_by("id ASC");
            $stmt->limit(1);
            return $stmt->build() ? $stmt->getObject()->id : false;
        }
        
        static function previously_created_check($type='',$data=[],$exactly_equal=false){
            $exactly_equal = $exactly_equal ? "=" : ">=";
            if($type == "order"){
                $stmt   = Models::$init->db->select("t1.id,t1.status,t1.duedate,t2.oduedate")->from("invoices_items AS t2");
                $stmt->join("LEFT","invoices AS t1","t1.id=t2.owner_id AND t2.user_pid=".$data["id"]);

                $stmt->where("t1.id","IS NOT NULL","","&&");
                $stmt->where("t1.user_id","=",$data["user_id"],"&&");

                //$stmt->where("t1.status","!=","cancelled","&&");

                $stmt->where("(");


                if($data["period"] == "hour")
                    $stmt->where("DATE_FORMAT(t2.oduedate,'%Y-%m-%d %H:%i')",$exactly_equal,DateManager::format("Y-m-d H:i",$data["duedate"]));
                else
                    $stmt->where("DATE_FORMAT(t2.oduedate,'%Y-%m-%d')",$exactly_equal,DateManager::format("Y-m-d",$data["duedate"]));
                $stmt->where(")","","","&&");

                $stmt->where("(");
                $stmt->where("JSON_UNQUOTE(JSON_EXTRACT(t2.options,'$.event'))","LIKE",'ExtendOrderPeriod',"||");
                $stmt->where("JSON_UNQUOTE(JSON_EXTRACT(t2.options,'$.event'))","LIKE",'RenewalDomain');
                $stmt->where(")");

                $stmt->group_by("t1.id,t1.status,t1.duedate,t2.oduedate");
                $stmt->order_by("t1.id ASC");
                $stmt = $stmt->build() ? $stmt->fetch_assoc() : [];
                return $stmt;
            }
            elseif($type == "addon")
            {
                $stmt   = Models::$init->db->select("t1.id,t1.status,t1.duedate,t2.oduedate")->from("invoices_items AS t2");
                $stmt->join("LEFT","invoices AS t1","t1.id=t2.owner_id AND t2.user_pid=".$data["owner_id"]);

                $stmt->where("t1.id","IS NOT NULL","","&&");
                $stmt->where("t1.user_id","=",$data["user_id"],"&&");

                //$stmt->where("t1.status","!=","cancelled","&&");


                $stmt->where("(");

                /*
                if(($data["period"] ?? false) == "hour")
                    $stmt->where("DATE_FORMAT(t1.duedate,'%Y-%m-%d %H:%i')",$exactly_equal,DateManager::format("Y-m-d H:i",$data["duedate"]),"||");
                else
                    $stmt->where("DATE_FORMAT(t1.duedate,'%Y-%m-%d')",$exactly_equal,DateManager::format("Y-m-d",$data["duedate"]),"||");
                */


                if($data["period"] == "hour")
                    $stmt->where("DATE_FORMAT(t2.oduedate,'%Y-%m-%d %H:%i')",$exactly_equal,DateManager::format("Y-m-d H:i",$data["duedate"]));
                else
                    $stmt->where("DATE_FORMAT(t2.oduedate,'%Y-%m-%d')",$exactly_equal,DateManager::format("Y-m-d",$data["duedate"]));

                $stmt->where(")","","","&&");

                $stmt->where("JSON_EXTRACT(t2.options,'$.event')","LIKE",'%ExtendAddonPeriod%',"&&");
                $stmt->where("JSON_UNQUOTE(JSON_EXTRACT(t2.options,'$.event_data.addon_id'))","LIKE",$data["id"]);

                $stmt->group_by("t1.id,t1.status,t1.duedate,t2.oduedate");
                $stmt->order_by("t1.id ASC");
                $return = $stmt->build() ? $stmt->fetch_assoc() : [];
                return $return;
            }
        }

    }