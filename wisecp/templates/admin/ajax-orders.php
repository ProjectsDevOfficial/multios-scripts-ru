<?php
    $items  = [];

    Helper::Load("Invoices");
    $taxation_type      = Invoices::getTaxationType();
    $main_tax_rate      = Invoices::getTaxRate();

    if(isset($list) && $list){
        foreach($list AS $i=>$row){
            $id     = $row["id"];
            $user_link = Controllers::$init->AdminCRLink("users-2",['detail',$row["user_id"]]);
            $detail_link = Controllers::$init->AdminCRLink("orders-2",["detail",$id]);
            $invoice_link = Controllers::$init->AdminCRLink("invoices-2",["detail",$row["invoice_id"]]);
            $options      = $row["options"] ? Utility::jdecode($row["options"],true) : [];
            if(isset($options["local_group_name"])) $group_name = $options["local_group_name"];
            else $group_name = ___("needs/none");
            if(isset($row["module"]) && $row["module"] && $row["module"] != "none" && $row["type"] != "domain") $automation = "true";
            else $automation = "false";


            $tax_rate           = $main_tax_rate;
            $invoice            = Invoices::get_last_invoice($id,'','t2.taxrate');

            if($invoice && $invoice["taxrate"] > 0.00) $tax_rate = $invoice["taxrate"];
            if($taxation_type == 'inclusive' && $row["amount"] > 0.00 && $tax_rate > 0.00)
                $row["amount"] += Money::get_tax_amount($row["amount"],$tax_rate);



            $cdate         = DateManager::format(Config::get("options/date-format"),$row["cdate"]);
            $rdate         = DateManager::format(Config::get("options/date-format"),$row["renewaldate"]);
            $duedate       = DateManager::format(Config::get("options/date-format"),$row["duedate"]);
            $duedate_year  = substr($row["duedate"],0,4);
            if($duedate_year == "1881" || $duedate_year == 1970) $duedate   = NULL;
            else $duedate = "<br>".$duedate;

            if($row["amount"] > 0.00)
            {
                $period     = View::period($row["period_time"],$row["period"]);
                $price      = Money::formatter_symbol($row["amount"],$row["amount_cid"]);
            }
            else
            {
                $price = ___("needs/free-amount");
                $period = NULL;
            }

            $amount_period = "<strong>".$price."</strong>";
            if($period) $amount_period .= "<br>".$period;

            $status = isset($situations[$row["status"]]) ? $situations[$row["status"]] : 'Transferred';

            $status_msg = "";

            if(!Validation::isEmpty($row["status_msg"])){
                $status_msg = htmlspecialchars($row["status_msg"],ENT_QUOTES);
                $status_msg = '<br><a href="javascript:void(0);" class="status-msg have-event" data-message="'.$status_msg.'"><i class="fa fa-exclamation-triangle"></i></i></a>';
            }

            if($row["isEvent"]>0){
                $status_msg .= '<br><a class="have-event" href="javascript:void(0);" data-balloon="'.__("admin/orders/there-are-pending-events",['{count}' => $row["isEvent"]]).'" data-balloon-pos="up"><i class="fa fa-info-circle"></i></i></a>';
            }

            if($row["type"] == "domain" && $row["status"] == "inprocess")
            {
                $check_verification = WDB::select("id")->from("users_products_docs")->where("owner_id","=",$row["id"],"&&")->where("status","=","pending")->build();
                if($check_verification)
                    $status_msg .= (strlen($status_msg) > 0 ? '' : '<br>').'<a class="have-event" href="javascript:void(0);" data-balloon="'.__("admin/orders/docs-tx19").'" data-balloon-pos="up" style="color:red;"><i class="fas fa-gavel"></i></i></a>';
            }

            $name_beside    = "";
            if(isset($options["domain"]) && $options["domain"] && $row["type"] != "domain" && $row["type"] != "server")
                $name_beside = '<br><a referrerpolicy="no-referrer" href="http://'.$options["domain"].'" target="_blank">'.$options["domain"].'</a>';
            elseif(isset($options["ip"]) && $options["ip"])
                $name_beside = '<br>'.$options["ip"].'';

            if($row["type"] == "domain" || $row["type"] == "hosting")
            {
                $name_beside .= '<div class="orderlist-domain-btns">';
                $domain = $row["type"] == "domain" ? $row["name"] : $options["domain"];
                $name_beside .= '<div class="clear"></div>';
                $name_beside .= '<a target="_blank" href="https://'.$domain.'" class="lbtn" referrerpolicy="no-referrer">WWW</a> ';
                $name_beside .= '<a target="_blank" href="'.Controllers::$init->AdminCRLink("products-2",["domain","whois"]).'?domain='.$domain.'" class="lbtn">WHOIS</a>';
                $name_beside .= '</div>';
            }

            if($row["renewaldate"] == $row["cdate"])
                $name_beside .= '<span class="datatable-list-tag new"><i class="fas fa-star"></i> '.__("admin/orders/tag-new-order").'</span>';
            else
                $name_beside .= '<span class="datatable-list-tag"><i class="fas fa-sync-alt"></i> '.__("admin/orders/tag-renewal").'</span>';


            $user_name           = Utility::short_text($row["user_name"],0,21,true);
            $user_company_name   = Utility::short_text($row["user_company_name"],0,21,true);

            $user_detail         = '<a href="'.$user_link.'"><strong title="'.$row["user_name"].'">'.$user_name.'</strong></a><br><span class="mobcomname" title="'.$row["user_company_name"].'">'.$user_company_name.'</span>';

            $item   = [];
            array_push($item,$i);
            if($from != "user" && $from != "product"){
                array_push($item,'<input type="checkbox" onchange="if($(\'.selected-item:not(:checked)\').length==0) $(\'#allSelect\').prop(\'checked\',true); else $(\'#allSelect\').prop(\'checked\',false);" class="checkbox-custom selected-item" id="order-'.$id.'-select" value="'.$id.'"><label for="order-'.$id.'-select" class="checkbox-custom-label"></label>');
            }

            if($from != "user") array_push($item,$user_detail);

            array_push($item,"<a href='".$detail_link."'>".$row["name"]." (#".$row["id"].")</a>".$name_beside);
            array_push($item,'<strong>'.$group_name.'</strong>');
            array_push($item,$rdate.$duedate);
            array_push($item,$amount_period);
            array_push($item,$status.$status_msg);
            $perms  = '';

            if($from != "product" && $from != "user" && $from != "index" && $privOperation){

                if($row["status"] == "waiting" && !$row["isEvent"])
                    $perms .= '<a href="javascript:applyOperation(\'approve\','.$id.',1);void 0;" data-tooltip="'.__("admin/orders/list-row-operation-approve").'" class="green sbtn"><i class="fa fa-check"></i></a> ';
                elseif($row["status"] == "inprocess" && !$row["isEvent"])
                    $perms .= '<a href="javascript:applyOperation(\'active\','.$id.',1);void 0;" data-tooltip="'.__("admin/orders/list-row-operation-active").'" class="green sbtn"><i class="fa fa-check"></i></a> ';
            }

            $perms .= '<a href="'.$detail_link.'" data-tooltip="'.__("admin/orders/list-row-operation-edit").'" class="sbtn"><i class="fa fa-search"></i></a> ';

            if($privDelete && $from != "product")
                $perms .= '<a id="delete-'.$id.'" data-automation="'.$automation.'" href="javascript:deleteOrder('.$id.');void 0;" data-tooltip="'.__("admin/orders/list-row-operation-delete").'" class="red sbtn"><i class="fa fa-trash"></i></a> ';

            array_push($item,$perms);



            $items[] = $item;
        }
    }


    return $items;