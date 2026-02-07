<?php
    $items  = [];

    if(isset($list) && $list){
        foreach($list AS $i=>$row){
            $user_id        = $row["user_id"];
            $user_link      = Controllers::$init->AdminCRLink("users-2",["detail",$user_id]);

            $user_name           = Utility::short_text($row["full_name"],0,21,true);
            $user_company_name   = Utility::short_text($row["company_name"],0,21,true);

            $user_detail         = '<a href="'.$user_link.'"><strong title="'.$row["full_name"].'">'.$user_name.'</strong></a><br><span class="mobcomname" title="'.$row["company_name"].'">'.$user_company_name.'</span>';


            $item   = [];
            array_push($item,$i);
            array_push($item,$user_detail);
            array_push($item,DateManager::format(Config::get("options/date-format").' - H:i',$row["ctime"]));
            array_push($item,$row["ip"]);

            $items[] = $item;
        }
    }


    return $items;