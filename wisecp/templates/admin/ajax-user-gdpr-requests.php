<?php
    $items  = [];

    if(isset($list) && $list){
        foreach($list AS $i=>$row){
            $id     = $row["user_id"];
            $user_link      = Controllers::$init->AdminCRLink("users-2",["detail",$id]);
            $detail_link    = Controllers::$init->AdminCRLink("users-2",["gdpr","detail"])."?id=".$row["id"];

            $user_name           = Utility::short_text($row["full_name"],0,21,true);
            $user_company_name   = Utility::short_text($row["company_name"],0,21,true);

            $user_detail         = '<a href="'.$user_link.'"><strong title="'.$row["full_name"].'">'.$user_name.'</strong></a><br><span class="mobcomname" title="'.$row["company_name"].'">'.$user_company_name.'</span>';


            $item   = [];
            array_push($item,$i);
            array_push($item,$user_detail);
            array_push($item,$row["type"] == 'remove' ? __("website/account_info/gdpr-tx14") : __("website/account_info/gdpr-tx15"));
            array_push($item,DateManager::format(Config::get("options/date-format").' - H:i',$row["created_at"]));
            array_push($item,$situations[$row["status"]] ?? 'NONE');

            $perms  = '';

            $perms .= '<a href="'.$detail_link.'" data-tooltip="'.__("admin/users/button-detail").'" class="sbtn"><i class="fa fa-search"></i></a> ';
            $perms .= '<a href="javascript:void 0;" onclick="DeleteRequest('.$row["id"].');" data-tooltip="'.__("admin/users/button-delete").'" class="sbtn red"><i class="fa fa-trash"></i></a> ';

            array_push($item,$perms);

            $items[] = $item;
        }
    }


    return $items;