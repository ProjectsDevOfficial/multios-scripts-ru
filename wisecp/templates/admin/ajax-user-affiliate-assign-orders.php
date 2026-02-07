<?php
    Helper::Load(["Orders","Money","Products"]);
    $lang               = Bootstrap::$lang->clang;
    $l_lang             = Config::get("general/local");
    $items  = [];

    if(isset($list) && $list){
        foreach($list AS $i=>$row){
            $id         = $row["id"];
            $aff_id     = $row["affiliate_id"];

            $aff            = User::get_affiliate(0,$aff_id);
            $aff_c_id       = $aff["owner_id"];
            $aff_c          = User::getData($aff_c_id,['id','full_name','company_name'],'assoc');
            $order          = Orders::get($row["order_id"]);
            $client         = User::getData($order["owner_id"],['id','full_name','company_name'],'assoc');


            $client_link        = Controllers::$init->AdminCRLink("users-2",["detail",$client["id"]]);
            $aff_link           = Controllers::$init->AdminCRLink("users-2",["detail",$aff_c_id])."?tab=affiliate";


            $user_name           = Utility::short_text($client["full_name"],0,21,true);
            $user_company_name   = Utility::short_text($client["company_name"],0,21,true);

            $user_detail         = '<a href="'.$client_link.'"><strong title="'.$client["full_name"].'">'.$user_name.'</strong></a><br><span class="mobcomname" title="'.$client["company_name"].'">'.$user_company_name.'</span>';

            $user_name           = Utility::short_text($aff_c["full_name"],0,21,true);
            $user_company_name   = Utility::short_text($aff_c["company_name"],0,21,true);

            $aff_detail         = '<a href="'.$aff_link.'"><strong title="'.$aff_c["full_name"].'">'.$user_name.'</strong></a><br><span class="mobcomname" title="'.$aff_c["company_name"].'">'.$user_company_name.'</span>';

            $rate = $row["rate"];
            $rate_split = explode(".",$rate);
            if(isset($rate_split[1]) && $rate_split[1] < 01)
                $rate = $rate_split[0];

            $rate_html = '<strong>'.Money::formatter_symbol($row["commission"],$aff["currency"]).'</strong> ('.$rate.'%)';

            if($row["exchange"] > 0.00)
            {
                $rate_html .= '<br>('.__("website/account/affiliate-tx51")." ".Money::formatter($row["exchange"],$aff["currency"]).')';
            }

            $status     = '<div class="listingstatus">';

            $status .= $transaction_situations[$row["status"]] ?? '';

            if(in_array($row["status"],['approved','completed']))
                $status .= '<br>('.DateManager::format(Config::get("options/date-format"),$row["ctime"]).')
                <a class="dashboardbox-info tooltip-top" data-tooltip="'.__("website/account/affiliate-tx48",['{date}' => DateManager::format(Config::get("options/date-format"),$row["clearing_date"])]).'"><i class="fa fa-info-circle" aria-hidden="true"></i></a>';
            elseif($row["status"] == "invalid")
                $status .= '<a class="dashboardbox-info tooltip-top" data-tooltip="'.__("website/account/affiliate-tx47").'"><i class="fa fa-info-circle" aria-hidden="true"></i></a>';
            elseif($row["status"] == "invalid-another")
            {
                $status .= '<a class="dashboardbox-info tooltip-top" data-tooltip="'.__("website/account/affiliate-tx60").'"><i class="fa fa-info-circle" aria-hidden="true"></i></a>';
            }

            $status .= '</div>';



            $item   = [];
            array_push($item,$i);
            array_push($item,$aff_detail);
            array_push($item,DateManager::format(Config::get("options/date-format")." H:i",$row['clicked_ctime']));
            array_push($item,'<a href="'.Controllers::$init->AdminCRLink("users-2",["detail",$row["user_id"]]).'"><strong>'.$row["full_name"].'</strong></a><br>('.(in_array($row['status'],['invalid','invalid-another']) ? __("website/account/affiliate-tx46") : __("website/account/affiliate-tx45")).')');
            array_push($item,'<a href="'.Controllers::$init->AdminCRLink("orders-2",["detail",$row["order_id"]]).'">'.($row['order_name'] ? $row["order_name"] : __("website/account/affiliate-tx59")).'</a>');
            array_push($item,Money::formatter_symbol($row["amount"],$row["currency"]));
            array_push($item,$rate_html);
            array_push($item,$status);


            $perms  = '';

            $perms .= '<a href="javascript:void 0;" onclick="delete_assigned_order('.$row["id"].');" data-tooltip="'.__("admin/users/button-delete").'" class="sbtn red"><i class="fa fa-trash"></i></a> ';


            array_push($item,$perms);

            $items[] = $item;
        }
    }


    return $items;