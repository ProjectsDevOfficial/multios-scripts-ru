<?php
    namespace WISECP\Api;

    class Billing
    {
        private $format = "Y-m-d H:i:s";
        public $endpoint = [],$query_params = [];
        private $temporary_invoice=[],$temporary_items=[];
        private $temporary_cfields=[];

        public function __construct()
        {
            \Helper::Load(["User","Invoices","Money","Orders"]);
        }
        public function GetInvoice($params)
        {
            $invoice_id         = (int) $this->endpoint[0] ?? 0;
            if(!is_array($params) && strlen($params) > 0) $invoice_id = (int) $params;


            $locall         = \Config::get("general/local");

            if($this->temporary_invoice && $this->temporary_invoice["id"] == $invoice_id)
                $invoice = $this->temporary_invoice;
            else
                $invoice        = \Invoices::get($invoice_id);

            if(!$invoice) throw new \Exception("Invoice not found");

            $ulang      = $invoice["user_data"]["lang"] ?? $locall;
            $uid        = $invoice["user_id"];

            $currency                   = \Money::Currency($invoice["currency"]);

            $pmethod                    = $invoice["pmethod"];
            $method_lang                = \Modules::Lang("Payment",$pmethod,$locall);
            if($method_lang["invoice-name"] ?? '') $pmethod = $method_lang["invoice-name"];

            $pmethod_msg                = $invoice["pmethod_msg"];
            $pmethod_msg                = strlen($pmethod_msg)>0 ? \Utility::jdecode($pmethod_msg,true) : [];


            if($invoice["status"] == "refund") $invoice["status"] = "refunded";
            if($invoice["status"] == "waiting") $invoice["status"] = "pending";

            if(!isset($this->temporary_cfields[$ulang]))
            {
                $cfields   = \Models::$init->db->select()->from("users_custom_fields");
                $cfields->where("status","=","active","&&");
                $cfields->where("invoice","=","1","&&");
                $cfields->where("lang","=",$ulang);
                $cfields->order_by("rank ASC");
                $cfields = $cfields->build() ? $cfields->fetch_assoc() : false;
                $this->temporary_cfields[$ulang] = $cfields;
            }
            else
                $cfields = $this->temporary_cfields[$ulang];

            $custom_fields = [];

            if($cfields)
            {
                foreach($cfields AS $field)
                {
                    $save_data          = \User::getInfo($uid,['field_'.$field["id"]]);
                    $save_value         = $save_data["field_".$field["id"]] ?? NULL;
                    if(strlen($save_value) > 0)
                        $custom_fields[] = [
                            'field_id'  => $field["id"],
                            'name'      => $field["name"],
                            'value'     => $save_value,
                        ];
                }
            }

            $result["id"]               = $invoice["id"];
            $result["number"]           = $invoice["number"];
            $result["user"] = [
                'id'                => $invoice["user_data"]["id"] ?? NULL,
                'lang'              => $invoice["user_data"]["lang"] ?? $locall,
                'type'              => $invoice["user_data"]["address"]["kind"] ?? $invoice["user_data"]["kind"],
                'identity'          => $invoice["user_data"]["address"]["identity"] ?? $invoice["user_data"]["identity"],
                'company_name'      => $invoice["user_data"]["address"]["company_name"] ?? $invoice["user_data"]["company_name"],
                'company_tax_number'=> $invoice["user_data"]["address"]["company_tax_number"] ?? $invoice["user_data"]["company_tax_number"],
                'company_tax_office'=> $invoice["user_data"]["address"]["company_tax_office"] ?? $invoice["user_data"]["company_tax_office"],
                'name'              => $invoice["user_data"]["address"]["name"] ?? $invoice["user_data"]["name"],
                'surname'           => $invoice["user_data"]["address"]["surname"] ?? $invoice["user_data"]["surname"],
                'full_name'         => $invoice["user_data"]["address"]["full_name"] ?? $invoice["user_data"]["full_name"],
                'email'             => $invoice["user_data"]["address"]["email"] ?? $invoice["user_data"]["email"],
                'phone'             => $invoice["user_data"]["address"]["phone"] ?? $invoice["user_data"]["phone"],
                'custom_fields'     => $custom_fields,
                'address'           => [
                    'country_code'      => $invoice["user_data"]["address"]["country_code"] ?? "US",
                    'country_name'      => $invoice["user_data"]["address"]["country_name"] ?? "United States",
                    'city'              => $invoice["user_data"]["address"]["city"] ?? NULL,
                    'state'             => $invoice["user_data"]["address"]["counti"] ?? NULL,
                    'detail'            => $invoice["user_data"]["address"]["address"] ?? NULL,
                    'zipcode'           => $invoice["user_data"]["address"]["zipcode"] ?? NULL,
                ],
            ];
            $result["taxation_type"]    = $invoice["taxation_type"];
            $result["notes"]            = $invoice["notes"];
            $result["created_at"]       = $invoice["cdate"];
            $result["due_date"]         = $invoice["duedate"];
            $result["payment_date"]     = str_starts_with($invoice["datepaid"], "1881") ? \DateManager::zero() : $invoice["datepaid"];
            $result["refund_date"]     = $invoice["status"] != "refund" || str_starts_with($invoice["refunddate"], "1881") ? \DateManager::zero() : $invoice["refunddate"];
            $result["status"]           = $invoice["status"];
            $result["formalize"]        = $invoice["taxed"]==1;
            $result["formalize_file"]   = NULL;
            $result["local"]            = $invoice["local"]==1;
            $result["taxfree"]          = $invoice["legal"]==0;
            $result["exchange_rate"]    = round($invoice["data"]["exchange_rate"] ?? 1,4);
            $result["currency"]         = $currency["code"];
            $result["tax_rate"]         = round($invoice["taxrate"],2);
            $result["subtotal"]         = round($invoice["subtotal"],4);
            $result["tax"]              = round($invoice["tax"],4);
            $result["total"]            = round($invoice["total"],4);
            $result["payment_method"]   = $pmethod;
            $result["payment_method_data"] = $pmethod_msg;
            $result["payment_method_commission"] = round($invoice["pmethod_commission"],4);
            $result["payment_method_commission_rate"] = round($invoice["pmethod_commission_rate"],4);
            $result["send_bill_to_address"] = round($invoice["sendbta_amount"],4);


            if($invoice["taxed_file"])
            {
                $tf         = \Utility::jdecode($invoice["taxed_file"],true);
                if($tf)
                {
                    $folder         = RESOURCE_DIR."uploads".DS."invoices".DS;
                    if(file_exists($folder.$tf["path"]))
                    {
                        $link = \Utility::image_link_determiner($tf["file_path"],$folder);
                        $result["formalize_file"] = $link;
                    }
                }
            }

            $data = [
                'id'    => $invoice["id"],
                'user_id' => $invoice["user_id"],
            ];
            $token = \Crypt::encode(\Utility::jencode($data),\Config::get("crypt/user"));
            $link  = \Controllers::$init->CRLink("share-invoice",false,$ulang)."?token=".$token;

            $result["share"] = [
                'token' => $token,
                'link'  => $link,
            ];

            $items      = [];
            $discounts = $invoice["discounts"] ?? [];
            if($discounts)
            {
                $is  = $discounts["items"] ?? [];

                if($is["coupon"] ?? [])
                    foreach($is["coupon"] AS $item_id => $item)
                        $items[] = [
                            'type'          => "coupon",
                            'item_id'       => (int) $item_id,
                            'rate'          => (float) $item["rate"] ?? 0,
                            'description'   => (\Validation::isEmpty($item["name"] ?? '') ? \Bootstrap::$lang->get_cm("admin/invoices/create-discount",false,$locall) : $item["name"] ?? 'N/A'),
                            'amount'        => round($item["amountd"],4),
                        ];

                if($is["promotions"] ?? [])
                    foreach($is["promotions"] AS $item_id => $item)
                        $items[] = [
                            'type'        => "promotion",
                            'item_id'       => (int) $item_id,
                            'rate'          => (float) $item["rate"] ?? 0,
                            'description' => (\Validation::isEmpty($item["name"] ?? '') ? \Bootstrap::$lang->get_cm("admin/invoices/create-discount",false,$locall) : $item["name"]),
                            'amount'        => round($item["amountd"],4),
                        ];

                if($is["dealership"] ?? [])
                    foreach($is["dealership"] AS $item_id => $item)
                        $items[] = [
                            'type'        => "dealership",
                            'item_id'       => (int) $item_id,
                            'rate'          => (float) $item["rate"] ?? 0,
                            'dkey'          => $item["dkey"] ?? '',
                            'description' => (\Validation::isEmpty($item["name"] ?? '') ? \Bootstrap::$lang->get_cm("admin/invoices/create-discount",false,$locall) : $item["name"] ?? 'N/A'),
                            'amount'        => round($item["amountd"],4),
                        ];
            }
            $result["discounts"] = $items;

            $items      = [];
            if($this->temporary_invoice && $this->temporary_invoice["id"] == $invoice_id && $this->temporary_items)
                $is = $this->temporary_items;
            else
                $is         = \Invoices::get_items($invoice_id);
            foreach($is AS $i)
            {
                if(isset($i["options"]["amount_including_discount"])) unset($i["amount_including_discount"]);
                $items[] =  [
                    'id'            => $i["id"],
                    'rank'          => $i["rank"],
                    'owner_id'      => $i["owner_id"],
                    'description'   => $i["description"],
                    'quantity'      => $i["quantity"],
                    'tax_exempt'    => $i["taxexempt"]==1,
                    'amount'        => round($i["amount"],4),
                    'total'         => round($i["total_amount"],4),
                    'order_id'      => $i["user_pid"],
                    'due_date'      => str_starts_with($i["oduedate"],'1971') ? \DateManager::zero() : $i["oduedate"],
                    'attributes'    => $i["options"],
                ];
            }
            $result["items"] = $items;

            $return = [
                'status' => "successful",
                'data'   => $result,
            ];

            if(!\Api::get_credential()) \Api::save_log(0,"INTERNAL",__FUNCTION__,debug_backtrace()[0] ?? [],$params,$return,\UserManager::GetIP());

            return $return;
        }

        public function GetInvoices($params=[])
        {

            $result     = [];

            if($params && is_array($params))
            {
                $filter     = $params;
            }
            else
            {
                $filter     = $this->query_params["filter"];
                $params     = $this->query_params;
            }


            $status     = (string) \Filter::letters($filter["status"] ?? '');
            $local      = \Filter::letters($filter["local"] ?? '');
            $taxfree    = \Filter::letters($filter["taxfree"] ?? '');
            $formalized = \Filter::letters($filter["formalized"] ?? '');
            $id         = (int) \Filter::numbers($filter["id"]);
            $created_at_from    = \Filter::numbers($filter["created_at_from"] ?? '',' :-');
            $created_at_to      = \Filter::numbers($filter["created_at_to"] ?? '',' :-');
            $due_date_from      = \Filter::numbers($filter["due_date_from"] ?? '',' :-');
            $due_date_to        = \Filter::numbers($filter["due_date_to"] ?? '',' :-');
            $payment_date_from  = \Filter::numbers($filter["payment_date_from"] ?? '',' :-');
            $payment_date_to    = \Filter::numbers($filter["payment_date_to"] ?? '',' :-');
            $refund_date_from  = \Filter::numbers($filter["refund_date_from"] ?? '',' :-');
            $refund_date_to    = \Filter::numbers($filter["refund_date_to"] ?? '',' :-');


            $number     = \Utility::substr((string) \Filter::html_clear($filter["number"]),0,200);

            // Pagination
            $page       = (int) \Filter::numbers($params["page"] ?? 1);
            $limit      = (int) \Filter::numbers($params["limit"] ?? 0);
            $sort       = (string) \Filter::letters($params["sort"] ?? '',"_");
            $sort_type  = (string) \Filter::letters($params["sort_type"] ?? '');

            if($page < 1) $page = 1;
            if($limit > 50) $limit = 50;
            if(!in_array($sort,['id','number','created_at','due_date','payment_date','total'])) $sort = "id";
            if(!in_array($sort_type,['ASC','DESC'])) $sort_type = "DESC";


            if(gettype($local) != "boolean") if(strlen($local) > 0 && !in_array($local,['true','false'])) $local = "";
            if(gettype($taxfree) != "boolean") if(strlen($taxfree) > 0 && !in_array($taxfree,['true','false'])) $taxfree = "";
            if(gettype($formalized) != "boolean") if(strlen($formalized) > 0 && !in_array($formalized,['true','false'])) $formalized = "";
            if($local === "true") $local = true;
            elseif($local === "false") $local = false;
            if($taxfree === "true") $taxfree = true;
            elseif($taxfree === "false") $taxfree = false;
            if($formalized === "true") $formalized = true;
            elseif($formalized === "false") $formalized = false;


            if($sort == "created_at") $sort = "cdate";
            elseif($sort == "due_date") $sort = "duedate";
            elseif($sort == "payment_date") $sort = "datepaid";

            $rows       = \WDB::select("id")->from("invoices");

            if(in_array($status,['unpaid','paid','pending','cancelled','refunded']))
                $rows->where("status","=",str_replace(["refunded","pending"],["refund","waiting"],$status),"&&");

            if(gettype($local) == "boolean")
                $rows->where("local","=",(int) $local,"&&");

            if(gettype($taxfree) == "boolean")
                $rows->where("legal","=",(int) $taxfree,"&&");

            if(gettype($formalized) == "boolean")
                $rows->where("taxed","=",(int) $formalized,"&&");

            if($id > 0) $rows->where("id","=",$id,"&&");
            if(strlen($number) > 0) $rows->where("number","=",$number,"&&");

            if($created_at_from) $rows->where("cdate",">=",$created_at_from,"&&");
            if($created_at_to) $rows->where("cdate","<=",$created_at_to,"&&");

            if($due_date_from) $rows->where("duedate",">=",$due_date_from,"&&");
            if($due_date_to) $rows->where("duedate","<=",$due_date_to,"&&");
            if($payment_date_from) $rows->where("datepaid", ">=", $payment_date_from, "&&");
            if($payment_date_to) $rows->where("datepaid", "<=", $payment_date_to, "&&");
            if($refund_date_from) $rows->where("refunddate", ">=", $refund_date_from, "&&");
            if($refund_date_to) $rows->where("refunddate", "<=", $refund_date_to, "&&");



            $rows->where("id","!=","0");
            $rows->order_by($sort." ".$sort_type);

            $rows       = $rows->build() ? $rows->fetch_object() : [];
            $total      = sizeof($rows);

            if($limit > 0)
            {
                $total_page = ceil($total / $limit);
                if($page > $total_page) $page = $total_page;
                $offset     = ($page - 1) * $limit;
                $rows       = array_slice($rows, $offset, $limit);
            }

            foreach($rows AS $r) if($re = $this->GetInvoice($r->id)) $result[] = $re["data"];

            $returnData = [
                'status' => "successful",
                'total'  => $total,
            ];

            if($limit > 0)
            {
                $returnData["page"] = $page;
                $returnData["limit"] = $limit;
                $returnData["next_page"] = ($page+1) > $total_page ? 0 : $page+1;
            }

            $returnData["data"] = $result;


            if(!\Api::get_credential()) \Api::save_log(0,"INTERNAL",__FUNCTION__,debug_backtrace()[0] ?? [],$params,$returnData,\UserManager::GetIP());

            return $returnData;

        }

        public function CreateInvoice($params=[])
        {
            if(\Api::get_credential() && \Filter::SERVER("REQUEST_METHOD") != "POST")
                throw new \Exception("Please use POST method");

            $format         = $this->format;
            $user_id        = (int) \Filter::numbers($params["user_id"] ?? 0);
            $notes          = (string) $params["notes"] ?? '';
            $status         = \Filter::letters($params["status"] ?? '');
            $formalize      = (bool) \Filter::numbers($params["formalize"] ?? 0) == 1;
            $taxed          = (int) $formalize;
            $taxfree        = (bool) \Filter::numbers($params["taxfree"] ?? 0) == 1;
            $formalize_file = (string) \Filter::html_clear($params["formalize_file"] ?? '');
            $cdate          = (string) \Filter::numbers($params["created_at"] ?? '','\- :');
            $duedate        = (string) \Filter::numbers($params["due_date"] ?? '','\- :');
            $datepaid       = (string) \Filter::numbers($params["payment_date"] ?? '','\- :');
            $refunddate     = (string) \Filter::numbers($params["refund_date"] ?? '','\- :');
            $currency       = (string) \Filter::letters_numbers($params["currency"] ?? '');
            $payment_method = (string) \Filter::html_clear($params["payment_method"] ?? '');
            $pmethod_msg    = (array) $params["payment_method_data"] ?? [];
            $pmethod_commission = (float) \Filter::numbers($params["payment_method_commission"] ?? 0,'.');
            $pmethod_commission_rate = (float) \Filter::numbers($params["payment_method_commission_rate"] ?? 0,'.');
            $sendbta        = (float) \Filter::numbers($params["send_bill_to_address"] ?? 0);
            $items          = $params["items"] ?? [];
            $exchange       = $params["exchange"] ?? '';
            $discounts      = $params["discounts"] ?? [];
            $notification   = (bool) ($params["notification"] ?? false);
            $taxed_file     = NULL;

            if(strlen($exchange) > 0.0000) $exchange = (float) \Filter::numbers($exchange,'.');

            if($cdate && str_starts_with($cdate,"0000")) $cdate = NULL;
            if($duedate && str_starts_with($duedate,"0000")) $duedate = NULL;
            if($datepaid && str_starts_with($datepaid,"0000")) $datepaid = NULL;
            if($refunddate && str_starts_with($refunddate,"0000")) $refunddate = NULL;


            if(strlen($status)==0) $status = "unpaid";
            if($user_id < 1) throw new \Exception("Please fill in 'user_id'");
            if($status && !in_array($status,['unpaid','paid','cancelled','refunded']))
                throw new \Exception("Please fill the posible value for 'status' value.");

            $user       = \User::getData($user_id,"id,email");
            if(!$user) throw new \Exception("There is no such user");


            if($cdate) $cdate_obj = \DateTime::createFromFormat($format, $cdate);
            if(!$cdate) $cdate = \DateManager::Now();
            if($duedate) $duedate_obj = \DateTime::createFromFormat($format, $duedate);
            if($datepaid) $datepaid_obj = \DateTime::createFromFormat($format, $datepaid);
            if($refunddate) $refunddate_obj = \DateTime::createFromFormat($format, $refunddate);

            if($cdate && (!$cdate_obj || $cdate_obj->format($format) !== $cdate))
                throw new \Exception("Please fill a correct value for 'created_at' field.");


            if(!$duedate || !$duedate_obj || $duedate_obj->format($format) !== $duedate)
                throw new \Exception("Please fill a correct value for 'due_date' field.");

            if(($status == "paid" || $status == "pending") && (!$datepaid || !$datepaid_obj || $datepaid_obj->format($format) !== $datepaid))
                throw new \Exception("Please fill a correct value for 'payment_date' field.");
            else
                $datepaid = \DateManager::ata();


            if($status == "refunded" && (!$refunddate || !$refunddate_obj || $refunddate_obj->format($format) !== $refunddate))
                throw new \Exception("Please fill a correct value for 'refund_date' field.");
            else
                $refunddate = \DateManager::ata();

            $curr       = \Money::Currency($currency);
            if(!$curr)
                throw new \Exception("Please fill a correct value for 'currency' field.");

            $currency   = $curr["id"];

            if(strlen($payment_method) > 1 && $payment_method != "none")
            {
                $active_methods         = [];
                $c_active_methods       = \Config::get("modules/payment-methods");
                $o_methods              = [];
                $methods                = \Modules::Load("Payment","All",true);

                foreach($methods AS $mn => $md)
                {
                    $md_nm = $md["lang"]["invoice-name"] ?? ($md["lang"]["name"] ?? $md["config"]["name"]);
                    if(in_array($mn,$c_active_methods))
                        $active_methods[$md_nm] = $mn;
                    else
                        $o_methods[$md_nm] = $mn;
                }
                if(isset($active_methods[$payment_method])) $pmethod = $active_methods[$payment_method];
                elseif(isset($o_methods[$payment_method])) $pmethod = $o_methods[$payment_method];
                else $pmethod = $payment_method;
            }
            else $pmethod = "none";

            $status = str_replace(["pending","refunded"],["waiting","refund"],$status);

            if(!$items || !is_array($items)) throw new \Exception("Please add an invoice item.");

            $set_items      = [];
            $set_discounts  = [];
            $rank           = 0;

            $sequences      = [];

            foreach($items AS $item)
            {
                $rank++;
                $m_rank         = \Filter::numbers($item["sequence"] ?? '');
                if(strlen($m_rank)==0) $m_rank = $rank;
                $sequences[$m_rank] = 1;
                $amount         = (float) \Filter::numbers($item["amount"] ?? 0,'.');
                $total          = (float) \Filter::numbers($item["total"] ?? $amount,'.');
                $quantity       = (int) \Filter::numbers($item["quantity"] ?? 1);
                $tax_exempt     = (bool) $item["tax_exempt"] ?? false;
                $description    = (string) \Filter::html_clear($item["description"] ?? '');
                if(strlen($description) < 1) $description = 'N/A';
                $order_id       = (int) $item["order_id"] ?? 0;
                $item_duedate   = (string) \Filter::numbers($item["due_date"] ?? '','\- :');
                if(str_starts_with($item_duedate,"0000")) $item_duedate = "";
                if($item_duedate) $item_duedate_obj = \DateTime::createFromFormat($format, $item_duedate);
                if(!$item_duedate || !$item_duedate_obj || $item_duedate_obj->format($format) !== $item_duedate)
                    $item_duedate = "1971-01-01 00:00:00";
                $attributes     = $item["attributes"] ?? [];
                if($attributes && !is_array($attributes)) $attributes = [];
                if($quantity < 1) $quantity = 1;
                if($total <= 0 || $amount <= 0)
                    throw new \Exception("The 'total' and 'amount' values of each item you add must be greater than 0.00");

                if($order_id > 0 && !\Orders::get($order_id,'id'))
                    throw new \Exception("The value you set in the 'order_id' field is an invalid order id.");

                $set_items[] = [
                    'rank'      => $m_rank,
                    'name'      => $description,
                    'taxexempt' => (int) $tax_exempt,
                    'amount'    => $amount,
                    'total'     => $total,
                    'cid'       => $currency,
                    'user_pid'  => $order_id,
                    'quantity'  => $quantity,
                    'oduedate'  => $item_duedate,
                    'options'   => $attributes,
                ];
            }


            if($discounts)
            {
                foreach($discounts AS $d)
                {
                    $d_sequence         = \Filter::numbers($d["sequence"] ?? '');
                    $d_key              = \Filter::route($d["dkey"] ?? '');
                    $d_type             = \Filter::letters($d["type"] ?? '');
                    $d_rate             = (float) \Filter::numbers($d["rate"] ?? 0,'.');
                    $d_description      = \Filter::html_clear($d["description"]);
                    $d_amount           = (float) \Filter::numbers($d["amount"],".");
                    if(strlen($d_sequence)==0 || !isset($sequences[$d_sequence]))
                        throw new \Exception("Please use the 'sequence' field on the invoice item and the discount item to specify the discount.");
                    $d_sequence = (int) $d_sequence;

                    if(!in_array($d_type,['coupon','promotion','dealership']))
                        throw new \Exception("Please specify the 'type' field in the discount item.");

                    if(\Utility::strlen($d_description)==0)
                        throw new \Exception("Please specify the 'description' field in the discount item.");

                    if(\Utility::strlen($d_amount)==0)
                        throw new \Exception("Please specify the 'amount' field in the discount item.");

                    $d_real_type = str_replace("promotion","promotions",$d_type);

                    $d_item         = [
                        'rate'      => $d_rate,
                        'name'      => $d_description,
                        'dvalue'    => $d_rate > 0.00 ? $d_rate."%" : \Money::formatter_symbol($d_amount,$currency),
                        'amountd'   => $d_amount,
                        'amount'    => \Money::formatter_symbol($d_amount,$currency),
                    ];
                    if($d_type == "dealership") $d_item["dkey"] = $d_key;

                    if(!isset($set_discounts[$d_real_type])) $set_discounts[$d_real_type] = [];
                    $set_discounts[$d_real_type][$d_sequence] = $d_item;
                }
            }


            if(strlen($formalize_file) > 0)
            {
                $folder         = RESOURCE_DIR."uploads".DS."invoices".DS;
                $remote         = str_starts_with($formalize_file,'http') || str_starts_with($formalize_file,'https');
                $filename       = md5(mt_rand(100000,999999)."*".time());
                $extension      = '.pdf';
                $extensions     = explode(",",\Config::get("options/attachment-extensions"));

                if(\Api::get_credential() && !$remote)
                    throw new \Exception("The value specified for the 'formalize_file' field is invalid.");

                if($remote)
                {
                    $source         = $formalize_file;
                    /*
                    if(stristr($formalize_file,"?"))
                    {
                        $formalize_file = explode("?",$formalize_file);
                        $formalize_file = $formalize_file[0];
                    }
                    */
                    $parse      = explode('.', $formalize_file);
                    $extension  = substr(end($parse),0,10);
                    if(!$extension || !in_array('.'.$extension,$extensions))
                        throw new \Exception("The formalization file you specified does not contain the expected extension.");
                    $newFilename = $filename.'.'.$extension;
                    $upload = \Updates::download_remote_file($source,$folder.$newFilename);
                    if(!$upload) throw new \Exception("Failed to load formalization file.");

                }
                elseif(!\Api::get_credential() && file_exists($formalize_file))
                {
                    $parse      = explode('.', $formalize_file);
                    $extension  = substr(end($parse),0,10);
                    if(!$extension || !in_array('.'.$extension,$extensions))
                        throw new \Exception("The formalization file you specified does not contain the expected extension.");
                    $newFilename = $filename.'.'.$extension;
                    \FileManager::file_rename($formalize_file,$folder.$newFilename);
                }
                else
                    $newFilename = NULL;

                if($newFilename && file_exists($folder.$newFilename))
                    $taxed_file = \Utility::jencode([
                        'size' => filesize($folder.$newFilename),
                        'file_name' => $newFilename,
                        'name' => $newFilename,
                        'file_path' => $newFilename,
                    ]);
                else throw new \Exception("Failed to create formalization file.");
            }

            $data       = [];

            if($exchange > 0.0000) $data["exchange_rate"] = $exchange;

            $invoice_data   = [
                'user_id'                   => $user_id,
                'data'                      => $data,
                'cdate'                     => $cdate,
                'duedate'                   => $duedate,
                'datepaid'                  => $datepaid,
                'refunddate'                => $refunddate,
                'status'                    => $status,
                'currency'                  => $currency,
                'taxed'                     => 0,
                'taxed_file'                => $taxed_file,
                'pmethod'                   => $pmethod,
                'pmethod_commission'        => $pmethod_commission,
                'pmethod_commission_rate'   => $pmethod_commission_rate,
                'pmethod_msg'               => is_array($pmethod_msg) ? \Utility::jencode($pmethod_msg) : $pmethod_msg,
                'sendbta'                   => $sendbta>0.00 ? 1 : 0,
                'sendbta_amount'            => $sendbta,
                'unread'                    => 1,
                'notes'                     => $notes,
            ];
            if($taxfree) $invoice_data["legal"] = 0;

            $create         = \Invoices::bill_generate($invoice_data,$set_items);

            if(!$create){
                if(\Invoices::$message == "no-user-address")
                    throw new \Exception("The user's address is not defined.");
                elseif(\Invoices::$message != '')
                    throw new \Exception(\Invoices::$message);

                throw new \Exception("Failed to Create Invoice.");
            }
            $create = $create["id"];

            if($set_discounts)
            {
                $set_discounts2 = [];
                $items          = \Invoices::get_items($create);
                $sequences      = [];
                if($items) foreach($items AS $item) $sequences[$item["rank"]] = $item["id"];

                foreach($set_discounts AS $gk => $ditems)
                {
                    foreach($ditems AS $dk => $di)
                    {
                        if(isset($sequences[$dk]))
                        {
                            if(!isset($set_discounts2[$gk])) $set_discounts2[$gk] = [];
                            $set_discounts2[$gk][$sequences[$dk]] = $di;
                        }
                    }
                }
                if($set_discounts2)
                {
                    \Invoices::set($create,['discounts' => \Utility::jencode(['items' => $set_discounts2])]);

                    $invoice    = \Invoices::get($create);
                    $calculate  = \Invoices::calculate_invoice($invoice,$items);

                    \Invoices::set($create,[
                        'subtotal'  => $calculate["subtotal"],
                        'tax'       => $calculate["tax"],
                        'total'     => $calculate["total"],
                    ]);
                }
            }


            if($notification)
            {
                \Helper::Load("Notification");

                if($status == "paid")
                    \Notification::invoice_has_been_approved($create);
                elseif($status == "unpaid")
                    \Notification::invoice_created($create);
                elseif($status == "refund")
                    \Notification::invoice_returned($create);
                elseif($status == "cancelled")
                    \Notification::invoice_cancelled($create);
            }

            if($taxed) \Invoices::MakeOperation("taxed",$create,$notification);

            $return = $this->GetInvoice($create);

            if(!\Api::get_credential()) \Api::save_log(0,"INTERNAL",__FUNCTION__,debug_backtrace()[0] ?? [],$params,$return,\UserManager::GetIP());

            return $return;
        }

        public function UpdateInvoice($params=[])
        {
            if(\Api::get_credential() && \Filter::SERVER("REQUEST_METHOD") != "PUT")
                throw new \Exception("Please use PUT method");

            $invoice_id         = (int) $this->endpoint[0] ?? 0;
            if(isset($params["id"]) && $params["id"]) $invoice_id = (int) $params["id"];

            $invoice    = $invoice_id > 0 ? \Invoices::get($invoice_id) : false;
            if(!$invoice) throw new \Exception("Invoice not found");

            $format         = $this->format;
            $number         = (string) \Filter::html_clear($params["number"] ?? '');
            $user_id        = (int) \Filter::numbers($params["user_id"] ?? 0);
            $notes          = (string) $params["notes"] ?? '';
            $status         = \Filter::letters($params["status"] ?? '');
            $formalize      = $params["formalize"] ?? '';
            $taxfree        = $params["taxfree"] ?? '';
            $formalize_file = (string) \Filter::html_clear($params["formalize_file"] ?? '');
            $cdate          = (string) \Filter::numbers($params["created_at"] ?? '','\- :');
            $duedate        = (string) \Filter::numbers($params["due_date"] ?? '','\- :');
            $datepaid       = (string) \Filter::numbers($params["payment_date"] ?? '','\- :');
            $refunddate     = (string) \Filter::numbers($params["refund_date"] ?? '','\- :');
            $currency       = (string) \Filter::letters_numbers($params["currency"] ?? '');
            $payment_method = (string) \Filter::html_clear($params["payment_method"] ?? '');
            $pmethod_msg    =  $params["payment_method_data"] ?? NULL;
            $pmethod_commission = $params["payment_method_commission"] ?? NULL;
            $pmethod_commission_rate = $params["payment_method_commission_rate"] ?? NULL;
            $sendbta        = $params["send_bill_to_address"] ?? NULL;
            $exchange       = $params["exchange"] ?? NULL;
            $discounts      = $params["discounts"] ?? NULL;
            $notification   = (bool) ($params["notification"] ?? false);
            $taxed_file     = NULL;

            if($status == $invoice["status"]) $status = '';


            if(strlen($exchange) > 0.0000) $exchange = (float) \Filter::numbers($exchange,'.');
            if(strlen($pmethod_commission) > 0.0000)
                $pmethod_commission = (float) \Filter::numbers($pmethod_commission,'.');
            if(strlen($pmethod_commission_rate) > 0)
                $pmethod_commission_rate = (float) \Filter::numbers($pmethod_commission_rate,'.');
            if(strlen($sendbta) > 0.0000)
                $sendbta        = (float) \Filter::numbers($sendbta,'.');
            if($cdate && str_starts_with($cdate,"0000")) $cdate = NULL;
            if($duedate && str_starts_with($duedate,"0000")) $duedate = NULL;
            if($datepaid && str_starts_with($datepaid,"0000")) $datepaid = NULL;
            if($refunddate && str_starts_with($refunddate,"0000")) $refunddate = NULL;

            $user_data      = $params["user"] ?? [];

            if($user_data && !is_array($user_data)) $user_data = [];

            if(isset($user_data["id"])) $user_id = (int) $user_data["id"];


            if($user_id > 0)
            {
                $user       = \User::getData($user_id,"id,email");
                if(!$user) throw new \Exception("There is no such user");
            }

            if($status && !in_array($status,['unpaid','paid','cancelled','refunded']))
                throw new \Exception("Please fill the possible value for 'status' value.");

            if(!$datepaid && $status == "paid" && $invoice["status"] != "paid" && str_starts_with($invoice["datepaid"],"1881")) $datepaid = \DateManager::Now();

            if($cdate) $cdate_obj = \DateTime::createFromFormat($format, $cdate);
            if(!$cdate) $cdate = \DateManager::Now();
            if($duedate) $duedate_obj = \DateTime::createFromFormat($format, $duedate);
            if($datepaid) $datepaid_obj = \DateTime::createFromFormat($format, $datepaid);
            if($refunddate) $refunddate_obj = \DateTime::createFromFormat($format, $refunddate);

            if($cdate && (!$cdate_obj || $cdate_obj->format($format) !== $cdate))
                throw new \Exception("Please fill a correct value for 'created_at' field.");

            if($duedate && (!$duedate_obj || $duedate_obj->format($format) !== $duedate))
                throw new \Exception("Please fill a correct value for 'due_date' field.");

            if($datepaid && (!$datepaid_obj || $datepaid_obj->format($format) !== $datepaid))
                throw new \Exception("Please fill a correct value for 'payment_date' field. ".$datepaid);

            if($refunddate && (!$refunddate_obj || $refunddate_obj->format($format) !== $refunddate))
                throw new \Exception("Please fill a correct value for 'refund_date' field.");

            if(strlen($currency) > 0)
            {
                $curr       = \Money::Currency($currency);
                if(!$curr)
                    throw new \Exception("Please fill a correct value for 'currency' field.");
                $currency   = $curr["id"];
            }

            if(strlen($payment_method) > 1 && $payment_method != "none")
            {
                $active_methods         = [];
                $c_active_methods       = \Config::get("modules/payment-methods");
                $o_methods              = [];
                $methods                = \Modules::Load("Payment","All",true);

                foreach($methods AS $mn => $md)
                {
                    $md_nm = $md["lang"]["invoice-name"] ?? ($md["lang"]["name"] ?? $md["config"]["name"]);
                    if(in_array($mn,$c_active_methods))
                        $active_methods[$md_nm] = $mn;
                    else
                        $o_methods[$md_nm] = $mn;
                }
                if(isset($active_methods[$payment_method])) $pmethod = $active_methods[$payment_method];
                elseif(isset($o_methods[$payment_method])) $pmethod = $o_methods[$payment_method];
                else $pmethod = $payment_method;
            }
            else $pmethod = "none";

            $status = $status ? str_replace(["pending","refunded"],["waiting","refund"],$status) : '';

            $set_discounts  = [];
            $sequences      = [];

            $items      = \Invoices::get_items($invoice_id);

            if($items) foreach($items AS $item) $sequences[$item["rank"]] = $item["id"];

            if($discounts)
            {
                foreach($discounts AS $d)
                {
                    $d_sequence         = \Filter::numbers($d["sequence"] ?? '');
                    $d_item_id         = \Filter::numbers($d["item_id"] ?? '');
                    $d_key              = \Filter::route($d["dkey"] ?? '');
                    $d_type             = \Filter::letters($d["type"] ?? '');
                    $d_rate             = (float) \Filter::numbers($d["rate"] ?? 0,'.');
                    $d_description      = \Filter::html_clear($d["description"]);
                    $d_amount           = (float) \Filter::numbers($d["amount"],".");
                    if(strlen($d_item_id)==0 && (strlen($d_sequence)==0 || !isset($sequences[$d_sequence])))
                        throw new \Exception("Please use the 'sequence' or the 'item_id' field on the discount item to specify the discount.");
                    $d_sequence = (int) $d_sequence;
                    $d_item_id  = (int) $d_item_id;

                    if($d_item_id > 0 && !in_array($d_item_id,$sequences))
                        throw new \Exception("The 'item_id' field you set in the discount item contains invalid value.");

                    if(!in_array($d_type,['coupon','promotion','dealership']))
                        throw new \Exception("Please specify the 'type' field in the discount item.");

                    if(\Utility::strlen($d_description)==0)
                        throw new \Exception("Please specify the 'description' field in the discount item.");

                    if(\Utility::strlen($d_amount)==0)
                        throw new \Exception("Please specify the 'amount' field in the discount item.");

                    $d_real_type = str_replace("promotion","promotions",$d_type);

                    $d_item         = [
                        'rate'      => $d_rate,
                        'name'      => $d_description,
                        'dvalue'    => $d_rate > 0.00 ? $d_rate."%" : \Money::formatter_symbol($d_amount,$currency),
                        'amountd'   => $d_amount,
                        'amount'    => \Money::formatter_symbol($d_amount,$currency),
                    ];
                    if($d_type == "dealership") $d_item["dkey"] = $d_key;

                    if(!isset($set_discounts[$d_real_type])) $set_discounts[$d_real_type] = [];
                    $key_id = (int) $sequences[$d_sequence];
                    if($d_item_id > 0) $key_id = $d_item_id;
                    $set_discounts[$d_real_type][$key_id] = $d_item;
                }
            }

            $set_data       = [];

            $data       = [];
            if($exchange != $invoice["data"]["exchange_rate"]) $data["exchange_rate"] = $exchange;

            if(strlen($number) > 0 && $number != $invoice["number"]) $set_data["number"] = $number;

            if($user_id > 0 && $user_id != $invoice["user_id"])
            {
                $set_data["user_id"] = $user_id;
                $set_data["user_data"]["id"] = $user_id;
            }

            if(gettype($taxfree) == "boolean" && ((int) $taxfree ? 0 : 1) != (int) $invoice["legal"]) $set_data["legal"] = $taxfree ? "0" : "1";

            if($user_data)
            {
                if(strlen($user_data["lang"] ?? '') > 0 && $user_data["lang"] != $invoice["user_data"]["lang"])
                {
                    $lang = \Filter::letters_numbers($user_data["lang"],'_\-');
                    if(!\Bootstrap::$lang->LangExists($lang))
                        throw new \Exception("The value you specified in the 'user.lang' field is invalid.");
                    $set_data["user_data"]["lang"] = $lang;
                }

                $value_inv  = $invoice["user_data"]["address"]["identity"] ?? $invoice["user_data"]["identity"];
                $value      = \Filter::identity($user_data["identity"] ?? '');
                if($value != $value_inv)
                {
                    $set_data["user_data"]["identity"] = $value;
                    $set_data["user_data"]["address"]["identity"] = $value;
                }

                $value_inv  = $invoice["user_data"]["address"]["kind"] ?? $invoice["user_data"]["kind"];
                $value      = \Filter::letters($user_data["type"] ?? '');

                if($value != $value_inv)
                {
                    if($value && !in_array($value,['corporate','individual']))
                        throw new \Exception("The value you specified in the 'user.type' field is invalid.");
                    $set_data["user_data"]["kind"] = $value;
                    $set_data["user_data"]["address"]["kind"] = $value;
                }

                $value_inv  = $invoice["user_data"]["address"]["company_name"] ?? $invoice["user_data"]["company_name"];
                $value      = \Filter::html_clear($user_data["company_name"] ?? '');

                if($value != $value_inv)
                {
                    $set_data["user_data"]["company_name"] = $value;
                    $set_data["user_data"]["address"]["company_name"] = $value;
                }

                $value_inv  = $invoice["user_data"]["address"]["company_tax_number"] ?? $invoice["user_data"]["company_tax_number"];
                $value      = \Filter::letters_numbers($user_data["company_tax_number"] ?? '','-');

                if($value != $value_inv)
                {
                    $set_data["user_data"]["company_tax_number"] = $value;
                    $set_data["user_data"]["address"]["company_tax_number"] = $value;
                }

                $value_inv  = $invoice["user_data"]["address"]["company_tax_office"] ?? $invoice["user_data"]["company_tax_office"];
                $value      = \Filter::html_clear($user_data["company_tax_office"] ?? '');

                if($value != $value_inv)
                {
                    $set_data["user_data"]["company_tax_office"] = $value;
                    $set_data["user_data"]["address"]["company_tax_office"] = $value;
                }

                $value_inv  = $invoice["user_data"]["address"]["name"] ?? $invoice["user_data"]["name"];
                $value      = \Filter::html_clear($user_data["name"] ?? '');

                if(strlen($value) > 0 && $value != $value_inv)
                {
                    if(\Validation::isEmpty($value))
                        throw new \Exception("The value you specified in the 'user.name' field is invalid.");

                    $set_data["user_data"]["name"] = $value;
                    $set_data["user_data"]["address"]["name"] = $value;
                }

                $value_inv  = $invoice["user_data"]["address"]["surname"] ?? $invoice["user_data"]["surname"];
                $value      = \Filter::html_clear($user_data["surname"] ?? '');

                if(strlen($value) > 0 && $value != $value_inv)
                {
                    if(\Validation::isEmpty($value))
                        throw new \Exception("The value you specified in the 'user.surname' field is invalid.");

                    $set_data["user_data"]["surname"] = $value;
                    $set_data["user_data"]["address"]["surname"] = $value;
                }

                $value_inv  = $invoice["user_data"]["address"]["full_name"] ?? $invoice["user_data"]["full_name"];
                $value      = \Filter::html_clear($user_data["full_name"] ?? '');

                if(strlen($value) > 0 && $value != $value_inv)
                {
                    if(\Validation::isEmpty($value))
                        throw new \Exception("The value you specified in the 'user.full_name' field is invalid.");

                    $value          = \Utility::ucfirst_space($value,___("package/charset-code",false,$invoice["user_data"]["lang"]));
                    $smash          = \Filter::name_smash($value);
                    $name           = $smash["first"];
                    $surname        = $smash["last"];

                    if(\Validation::isEmpty($surname))
                        throw new \Exception("The value you specified in the 'user.full_name' field is invalid.");


                    $set_data["user_data"]["full_name"] = $value;
                    $set_data["user_data"]["address"]["full_name"] = $value;
                    $set_data["user_data"]["name"] = $name;
                    $set_data["user_data"]["address"]["name"] = $name;
                    $set_data["user_data"]["surname"] = $surname;
                    $set_data["user_data"]["address"]["surname"] = $surname;
                }

                $value_inv  = $invoice["user_data"]["address"]["email"] ?? $invoice["user_data"]["email"];
                $value      = \Filter::email($user_data["email"] ?? '');

                if($value != $value_inv)
                {
                    if(\Validation::isEmpty($value) || !\Validation::isEmail($value))
                        throw new \Exception("The value you specified in the 'user.email' field is invalid.");

                    $set_data["user_data"]["email"] = $value;
                    $set_data["user_data"]["address"]["email"] = $value;
                }

                $value_inv  = $invoice["user_data"]["address"]["phone"] ?? $invoice["user_data"]["phone"];
                $value      = \Filter::numbers($user_data["phone"] ?? '');

                if($value != $value_inv)
                {
                    if(!\Validation::isEmpty($value) && !\Validation::isPhone($value))
                        throw new \Exception("The value you specified in the 'user.phone' field is invalid.");

                    $set_data["user_data"]["phone"] = $value;
                    $set_data["user_data"]["address"]["phone"] = $value;
                }

                $value_inv  = $invoice["user_data"]["address"]["country_code"] ?? '';
                $value      = substr(\Filter::letters($user_data["address"]["country_code"] ?? ''),0,3);

                if($value != $value_inv)
                {
                    if(\Validation::isEmpty($value) || !\AddressManager::get_id_with_cc($value))
                        throw new \Exception("The value you specified in the 'user.address.country_code' field is invalid.");
                    $set_data["user_data"]["address"]["country_id"]     = \AddressManager::get_id_with_cc($value);
                    $set_data["user_data"]["address"]["country_code"]   = $value;
                }

                $value_inv  = $invoice["user_data"]["address"]["country_name"] ?? '';
                $value      = \Filter::html_clear($user_data["address"]["country_name"] ?? '');

                if($value != $value_inv)
                {
                    if(\Validation::isEmpty($value))
                        throw new \Exception("The value you specified in the 'user.address.country_name' field is invalid.");

                    $set_data["user_data"]["address"]["country_name"] = $value;
                }

                $value_inv  = $invoice["user_data"]["address"]["city"] ?? '';
                $value      = \Filter::html_clear($user_data["address"]["city"] ?? '');

                if($value != $value_inv)
                {
                    if(\Validation::isEmpty($value))
                        throw new \Exception("The value you specified in the 'user.address.city' field is invalid.");

                    $city_id = \AddressManager::getCityID(0,$value);

                    $set_data["user_data"]["address"]["city"] = $value;
                    $set_data["user_data"]["address"]["city_id"] = $city_id;
                }

                $value_inv  = $invoice["user_data"]["address"]["counti"] ?? '';
                $value      = \Filter::html_clear($user_data["address"]["state"] ?? '');

                if($value != $value_inv)
                {
                    if(\Validation::isEmpty($value))
                        throw new \Exception("The value you specified in the 'user.address.state' field is invalid.");

                    $set_data["user_data"]["address"]["counti"] = $value;
                }

                $value_inv  = $invoice["user_data"]["address"]["address"] ?? '';
                $value      = \Filter::html_clear($user_data["address"]["detail"] ?? '');

                if($value != $value_inv)
                {
                    if(\Validation::isEmpty($value))
                        throw new \Exception("The value you specified in the 'user.address.detail' field is invalid.");

                    $set_data["user_data"]["address"]["address"] = $value;
                }

                $value_inv  = $invoice["user_data"]["address"]["zipcode"] ?? '';
                $value      = \Filter::numbers($user_data["address"]["zipcode"] ?? '');

                if($value != $value_inv)
                {
                    if(\Validation::isEmpty($value))
                        throw new \Exception("The value you specified in the 'user.address.zipcode' field is invalid.");

                    $set_data["user_data"]["address"]["zipcode"] = $value;
                }
            }

            if($notes != $invoice["notes"]) $set_data["notes"] = $notes;

            if(strlen($cdate) > 0 && $cdate != $invoice["cdate"]) $set_data["cdate"] = $cdate;
            if(strlen($duedate) > 0 && $duedate != $invoice["duedate"]) $set_data["duedate"] = $duedate;
            if(strlen($datepaid) > 0 && $datepaid != $invoice["datepaid"]) $set_data["datepaid"] = $datepaid;
            if(strlen($refunddate) > 0 && $refunddate != $invoice["refunddate"]) $set_data["refunddate"] = $refunddate;
            if(gettype($formalize) == "boolean" && $formalize != (boolean) $invoice["taxed"] && !$formalize)
                $set_data["taxed"] = (int) $formalize;
            if($currency > 0 && $currency != $invoice["currency"]) $set_data["currency"] = $currency;
            if(strlen($pmethod) > 0 && $pmethod != $invoice["pmethod"]) $set_data["pmethod"] = $pmethod;
            $invoice["pmethod_msg"] = $invoice["pmethod_msg"] ? \Utility::jdecode($invoice["pmethod_msg"],true) : [];
            if(gettype($pmethod_msg) == "array" && $pmethod_msg != $invoice["pmethod_msg"])
                $set_data["pmethod_msg"] = $pmethod_msg;
            if($pmethod_commission != NULL && $pmethod_commission != (float) $invoice["pmethod_commission"])
                $set_data["pmethod_commission"] = $pmethod_commission;
            if($pmethod_commission_rate != NULL && $pmethod_commission_rate != (float) $invoice["pmethod_commission_rate"])
                $set_data["pmethod_commission_rate"] = $pmethod_commission_rate;
            if($sendbta != NULL && $sendbta != (float) $invoice["sendbta_amount"])
                $set_data["sendbta_amount"] = $sendbta;

            if($discounts != NULL && $set_discounts != ($invoice["discounts"]["items"] ?? []))
                $set_data["discounts"] = $set_discounts;


            if(strlen($formalize_file) > 0 && $formalize_file != "none")
            {
                $folder         = RESOURCE_DIR."uploads".DS."invoices".DS;
                $remote         = str_starts_with($formalize_file,'http') || str_starts_with($formalize_file,'https');
                $filename       = md5(mt_rand(100000,999999)."*".time());
                $extension      = '.pdf';
                $extensions     = explode(",",\Config::get("options/attachment-extensions"));

                if(\Api::get_credential() && !$remote)
                    throw new \Exception("The value specified for the 'formalize_file' field is invalid.");

                if($remote)
                {
                    $source         = $formalize_file;
                    /*
                    if(stristr($formalize_file,"?"))
                    {
                        $formalize_file = explode("?",$formalize_file);
                        $formalize_file = $formalize_file[0];
                    }
                    */
                    $parse      = explode('.', $formalize_file);
                    $extension  = substr(end($parse),0,10);
                    if(!$extension || !in_array('.'.$extension,$extensions))
                        throw new \Exception("The formalization file you specified does not contain the expected extension.");
                    $newFilename = $filename.'.'.$extension;
                    $upload = \Updates::download_remote_file($source,$folder.$newFilename);
                    if(!$upload) throw new \Exception("Failed to load formalization file.");

                }
                elseif(!\Api::get_credential() && file_exists($formalize_file))
                {
                    $parse      = explode('.', $formalize_file);
                    $extension  = substr(end($parse),0,10);
                    if(!$extension || !in_array('.'.$extension,$extensions))
                        throw new \Exception("The formalization file you specified does not contain the expected extension.");
                    $newFilename = $filename.'.'.$extension;
                    \FileManager::file_rename($formalize_file,$folder.$newFilename);
                }
                else
                    $newFilename = NULL;

                if($newFilename && file_exists($folder.$newFilename))
                    $taxed_file = \Utility::jencode([
                        'size' => filesize($folder.$newFilename),
                        'file_name' => $newFilename,
                        'name' => $newFilename,
                        'file_path' => $newFilename,
                    ]);
                else throw new \Exception("Failed to create formalization file.");
            }
            if($formalize_file) $set_data["taxed_file"] = $taxed_file;


            if(isset($set_data["user_data"]) && $set_data["user_data"])
                $set_data["user_data"] = \Utility::jencode(array_replace_recursive($invoice["user_data"],$set_data["user_data"]));
            if(isset($set_data["pmethod_msg"]))
                $set_data["pmethod_msg"] = $set_data["pmethod_msg"] ? \Utility::jencode($set_data["pmethod_msg"]) : '';
            if(isset($set_data["data"]))
                $set_data["data"] = $set_data["data"] ? \Utility::jencode($set_data["data"]) : '';
            if(isset($set_data["discounts"]))
            {
                $discounts_data = $invoice["discounts"];
                if(isset($discounts_data["items"])) unset($discounts_data["items"]);
                $discounts_data["items"] = $set_data["discounts"];
                $set_data["discounts"] = $discounts_data ? \Utility::jencode($discounts_data) : '';
            }

            if($set_data)
            {
                \Invoices::set($invoice_id,$set_data);
                $invoice        = \Invoices::get($invoice_id);
                $calculate      = \Invoices::calculate_invoice($invoice,$items);

                \Invoices::set($invoice_id,[
                    'subtotal'      => $calculate["subtotal"],
                    'tax'           => $calculate["tax"],
                    'total'         => $calculate["total"],
                ]);

                $invoice            = array_merge($invoice,$calculate);

                $get_inex   = \Invoices::get_inex(0,$invoice_id);
                if($get_inex)
                    \Invoices::set_inex($get_inex["id"],[
                        'amount' => $invoice["total"],
                        'currency' => $invoice["currency"],
                    ]);
            }

            if(strlen($status) > 0 && $status != $invoice["status"])
            {
                if($invoice["pmethod"] == "Balance" && $status == "paid" && $invoice["status"] != "paid")
                {
                    $udata  = \User::getData($invoice["user_id"],"id,balance,balance_currency","array");

                    $u_amount = round($udata["balance"],2);
                    $c_amount = round($invoice["total"],2);

                    if($udata["balance_currency"] != $invoice["currency"])
                        $c_amount   = \Money::exChange($invoice["total"],$invoice["currency"],$udata["balance_currency"]);

                    if($u_amount < $c_amount)
                        throw new \Exception("The invoice cannot be paid because there is not enough credit in the invoice holder's account.");

                    $newBalance = $u_amount - $c_amount;

                    if($newBalance < 0.0000) $newBalance = 0;

                    \User::setData($udata["id"],['balance' => $newBalance]);
                    \User::insert_credit_log([
                        'user_id'   => $udata["id"],
                        'description' => $invoice["number"],
                        'type'      => "down",
                        'amount'    => $c_amount,
                        'cid'       => $udata["balance_currency"],
                        'cdate'     => \DateManager::Now(),
                    ]);
                }

                $apply = \Invoices::MakeOperation($status,$invoice["id"],$notification,1);
                if(!$apply) throw new \Exception(\Invoices::$message);
            }

            if(gettype($formalize) == "boolean" && $formalize)
            {
                $apply = \Invoices::MakeOperation("taxed",$invoice["id"],$notification,1);
                if(!$apply) throw new \Exception(\Invoices::$message);
            }

            $invoice                    = \Invoices::get($invoice_id);

            $this->temporary_invoice    = $invoice;
            $this->temporary_items      = $items;

            \Hook::run("InvoiceModified",$invoice);
            \User::addAction(0,"alteration","changed-bill-detail",[
                'id' => $invoice["id"],
                'via' => "API",
            ]);

            $return = $this->GetInvoice($invoice_id);

            if(!\Api::get_credential()) \Api::save_log(0,"INTERNAL",__FUNCTION__,debug_backtrace()[0] ?? [],$params,$return,\UserManager::GetIP());

            return $return;
        }

        public function DeleteInvoice($params=[])
        {
            if(\Api::get_credential() && \Filter::SERVER("REQUEST_METHOD") != "DELETE")
                throw new \Exception("Please use DELETE method");

            $invoice_id         = (int) $this->endpoint[0] ?? 0;
            if(!is_array($params) && strlen($params) > 0) $invoice_id = (int) $params;

            $invoice        = $invoice_id ? \Invoices::get($invoice_id) : false;

            if(!$invoice) throw new \Exception("Invoice not found");

            $operation      = \Invoices::MakeOperation("delete",$invoice_id,false,1);

            if(!$operation) throw new \Exception(\Invoices::$message ? \Invoices::$message : "Can not remove invoice");


            \User::addAction(0,"deleted","Deleted Invoice via API, Invoice ID #".$invoice_id);


            $return = ["status" => "successful"];

            if(!\Api::get_credential()) \Api::save_log(0,"INTERNAL",__FUNCTION__,debug_backtrace()[0] ?? [],$params,$return,\UserManager::GetIP());

            return $return;

        }

        public function CreateInvoiceItem($params=[])
        {
            if(\Api::get_credential() && \Filter::SERVER("REQUEST_METHOD") != "POST")
                throw new \Exception("Please use POST method");
            $invoice_id         = (int) $this->endpoint[0] ?? 0;
            if(isset($params["invoice_id"]) && $params["invoice_id"]) $invoice_id = (int) $params["invoice_id"];

            $invoice    = $invoice_id > 0 ? \Invoices::get($invoice_id) : false;
            if(!$invoice) throw new \Exception("Invoice not found");
            $format         = $this->format;


            $rank           = ((int) \WDB::select("rank")->from("invoices_items")->where("owner_id","=",$invoice_id)->build(true)->getObject()->rank)+1;


            $m_rank         = \Filter::numbers($params["sequence"] ?? '');
            if(strlen($m_rank)==0) $m_rank = $rank;
            $amount         = (float) \Filter::numbers($params["amount"] ?? 0,'.');
            $total          = (float) \Filter::numbers($params["total"] ?? $amount,'.');
            $quantity       = (int) \Filter::numbers($params["quantity"] ?? 1);
            $tax_exempt     = (bool) $params["tax_exempt"] ?? false;
            $description    = (string) \Filter::html_clear($params["description"] ?? '');
            if(strlen($description) < 1) $description = 'N/A';
            $order_id       = (int) $params["order_id"] ?? 0;
            $item_duedate   = (string) \Filter::numbers($params["due_date"] ?? '','\- :');
            if(str_starts_with($item_duedate,"0000")) $item_duedate = "";
            if($item_duedate) $item_duedate_obj = \DateTime::createFromFormat($format, $item_duedate);
            if(!$item_duedate || !$item_duedate_obj || $item_duedate_obj->format($format) !== $item_duedate)
                $item_duedate = "1971-01-01 00:00:00";
            $attributes     = $params["attributes"] ?? [];
            if($attributes && !is_array($attributes)) $attributes = [];
            if($quantity < 1) $quantity = 1;
            if($total <= 0 || $amount <= 0)
                throw new \Exception("The 'total' and 'amount' values of each item you add must be greater than 0.00");

            if($order_id > 0 && !\Orders::get($order_id,'id'))
                throw new \Exception("The value you set in the 'order_id' field is an invalid order id.");

            $set_item = [
                'owner_id'      => $invoice_id,
                'rank'          => $m_rank,
                'description'   => $description,
                'taxexempt'     => (int) $tax_exempt,
                'amount'        => $amount,
                'total_amount'  => $total,
                'currency'      => $invoice["currency"],
                'user_pid'      => $order_id,
                'quantity'      => $quantity,
                'oduedate'      => $item_duedate,
                'options'       => $attributes && is_array($attributes) ? \Utility::jencode($attributes) : '',
            ];

            $create         = \Invoices::add_item($set_item);

            if(!$create) throw new \Exception("Can not create invoice item");

            $items      = \Invoices::get_items($invoice_id);
            $calculate  = \Invoices::calculate_invoice($invoice,$items);
            \Invoices::set($invoice_id,[
                'subtotal'  => $calculate["subtotal"],
                'tax'       => $calculate["tax"],
                'total'     => $calculate["total"],
            ]);

            $invoice        = array_merge($invoice,$calculate);

            $this->temporary_invoice = $invoice;
            $this->temporary_items = $items;

            $invoice        = $this->GetInvoice($invoice_id);
            $data           = end($invoice["data"]["items"]);

            $return = ['status' => "successful",'data' => $data];

            if(!\Api::get_credential()) \Api::save_log(0,"INTERNAL",__FUNCTION__,debug_backtrace()[0] ?? [],$params,$return,\UserManager::GetIP());

            return $return;
        }

        public function UpdateInvoiceItem($params=[])
        {
            if(\Api::get_credential() && \Filter::SERVER("REQUEST_METHOD") != "PUT")
                throw new \Exception("Please use PUT method");

            $format         = $this->format;

            $item_id         = (int) $this->endpoint[0] ?? 0;
            if(is_array($params) && isset($params["id"])) $item_id = (int) $params["id"];

            if(!$item_id) throw new \Exception("Item id not found");

            $item       = \WDB::select()->from("invoices_items")->where("id","=",$item_id);
            if($item->build()) $item = $item->getAssoc();
            else $item = [];

            if(!$item) throw new \Exception("Invoice item not found");

            $item["options"] = strlen($item["options"]) > 0 ? \Utility::jdecode($item["options"],true) : [];

            $invoice    = \Invoices::get($item["owner_id"]);
            if(!$invoice) throw new \Exception("Invoice not found");

            $owner_id       = \Filter::numbers($params["owner_id"] ?? '');
            $m_rank         = \Filter::numbers($params["sequence"] ?? '');
            $amount         = \Filter::numbers($params["amount"] ?? NULL,'.');
            $total          = \Filter::numbers($params["total"] ?? NULL,'.');
            $quantity       = \Filter::numbers($params["quantity"] ?? '');
            $tax_exempt     = $params["tax_exempt"] ?? '';
            $description    = \Filter::html_clear($params["description"] ?? '');
            $order_id       = $params["order_id"] ?? '';
            $item_duedate   = \Filter::numbers($params["due_date"] ?? '','\- :');
            if(str_starts_with($item_duedate,"0000")) $item_duedate = "";

            if($item_duedate) $item_duedate_obj = \DateTime::createFromFormat($format, $item_duedate);
            if(!$item_duedate || !$item_duedate_obj || $item_duedate_obj->format($format) !== $item_duedate)
                $item_duedate = "1971-01-01 00:00:00";

            $attributes     = $params["attributes"] ?? NULL;

            if(strlen($amount) > 0) $amount = (float) \Filter::numbers($amount,'.');
            if(strlen($total) > 0) $total = (float) \Filter::numbers($total,'.');
            if(strlen($quantity) > 0) $quantity = (int) $quantity;
            if(strlen($order_id) > 0) $order_id = (int) $order_id;
            if(strlen($owner_id) > 0) $owner_id = (int) $owner_id;
            if(strlen($m_rank) > 0) $m_rank = (int) $m_rank;
            if($attributes && !is_array($attributes)) $attributes = [];


            if((strlen($total) > 0 && $total <= 0) || (strlen($amount) > 0 && $amount <= 0))
                throw new \Exception("The 'total' and 'amount' values of each item you add must be greater than 0.00");

            $set_item       = [];

            if(gettype($order_id) == "integer" && $order_id != (int) $item["user_pid"])
            {
                if($order_id > 0)
                {
                    if(\Orders::get($order_id,'id'))
                        $set_item["user_pid"] = $order_id;
                    else
                        throw new \Exception("The value you set in the 'order_id' field is an invalid order id.");
                }
                else
                    $set_item["user_pid"] = 0;
            }

            if(gettype($owner_id) == "integer" && $owner_id != (int) $item["owner_id"])
            {
                $owner_check    = $owner_id > 0 ? \Invoices::get($owner_id,['select' => "id"]) : false;
                if($owner_check)
                    $set_item["owner_id"] = $owner_id;
                else
                    throw new \Exception("The 'owner_id' value you set in the item is an invalid invoice ID.");
            }

            if(gettype($m_rank) == "integer" && $m_rank != (int) $item["rank"])
                $set_item["rank"] = $m_rank;

            if($amount !== NULL && $amount != (float) $item["amount"])
                $set_item["amount"] = $amount;

            if($total !== NULL && $total != (float) $item["total_amount"])
                $set_item["total_amount"] = $total;

            if(gettype($quantity) == "integer" && $quantity != (int) $item["quantity"])
                $set_item["quantity"] = $quantity;

            if(gettype($tax_exempt) == "boolean" && $tax_exempt != (bool) $item["taxexempt"])
                $set_item["taxexempt"] = (int) $tax_exempt;

            if(!\Validation::isEmpty($description) && $description != $item["description"])
                $set_item["description"] = $description;

            if($item_duedate !== NULL && $item_duedate != $item["oduedate"])
                $set_item["oduedate"] = $item_duedate;

            if(gettype($attributes) == "array" && $attributes != $item["options"])
                $set_item["options"] = \Utility::jencode($attributes);


            if($set_item)
            {
                $update         = \Invoices::set_item($item_id,$set_item);

                if(!$update) throw new \Exception("Can not update item");
                \User::addAction(0,"alteratipn","Updated invoice item #".$item_id.", Invoice id #".$invoice["id"]);

                $items      = \Invoices::get_items($invoice["id"]);
                $calculate  = \Invoices::calculate_invoice($invoice,$items);
                $invoice    = array_merge($invoice,$calculate);
                \Invoices::set($invoice["id"],$calculate);

                if(isset($set_item["owner_id"]) && $set_item["owner_id"] != $invoice["id"])
                {
                    $invoice        = \Invoices::get($set_item["owner_id"]);
                    $items          = \Invoices::get_items($invoice["id"]);
                    $calculate      = \Invoices::calculate_invoice($invoice,$items);
                    $invoice        = array_merge($invoice,$calculate);
                    \Invoices::set($invoice["id"],$calculate);
                }


                $this->temporary_invoice = $invoice;
                $this->temporary_items = $items;
            }

            $invoice        = $this->GetInvoice($invoice["id"]);
            $data           = [];

            if($invoice["data"]["items"])
                foreach($invoice["data"]["items"] AS $item)
                    if($item["id"] == $item_id) $data = $item;

            $return = ['status' => "successful",'data' => $data];

            if(!\Api::get_credential()) \Api::save_log(0,"INTERNAL",__FUNCTION__,debug_backtrace()[0] ?? [],$params,$return,\UserManager::GetIP());

            return $return;
        }

        public function DeleteInvoiceItem($params=[])
        {
            if(\Api::get_credential() && \Filter::SERVER("REQUEST_METHOD") != "DELETE")
                throw new \Exception("Please use DELETE method");

            $item_id         = (int) $this->endpoint[0] ?? 0;
            if(!is_array($params) && strlen($params) > 0) $item_id = (int) $params;

            if(!$item_id) throw new \Exception("Item id not found");

            $item       = \WDB::select()->from("invoices_items")->where("id","=",$item_id);
            if($item->build()) $item = $item->getAssoc();
            else $item = [];

            if(!$item) throw new \Exception("Invoice item not found");

            $item["options"] = strlen($item["options"]) > 0 ? \Utility::jdecode($item["options"],true) : [];

            $invoice    = \Invoices::get($item["owner_id"]);
            if(!$invoice) throw new \Exception("Invoice not found");

            $operation      = \Invoices::delete_item($item_id);

            if(!$operation) throw new \Exception(\Invoices::$message ? \Invoices::$message : "Can not remove invoice item");

            $items      = \Invoices::get_items($invoice["id"]);
            $calculate  = \Invoices::calculate_invoice($invoice,$items);
            \Invoices::set($invoice["id"],$calculate);

            \User::addAction(0,"deleted","Deleted invoice via API, Item ID #".$item_id.", Invoice ID: #".$invoice["id"]);


            $return = ["status" => "successful"];

            if(!\Api::get_credential()) \Api::save_log(0,"INTERNAL",__FUNCTION__,debug_backtrace()[0] ?? [],$params,$return,\UserManager::GetIP());

            return $return;
        }



    }