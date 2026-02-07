<?php
    $lang               = Bootstrap::$lang->clang;
    $l_lang             = Config::get("general/local");
    $items  = [];

    if(isset($list) && $list){
        foreach($list AS $i=>$row){
            $id         = $row["id"];
            $aff_id     = $row["aff_id"];

            $aff            = User::get_affiliate(0,$aff_id);
            $aff_c_id       = $aff["owner_id"];
            $aff_c          = User::getData($aff_c_id,['id','full_name','company_name'],'assoc');

            $client_link    = Controllers::$init->AdminCRLink("users-2",["detail",$id]);
            $aff_link       = Controllers::$init->AdminCRLink("users-2",["detail",$aff_c_id])."?tab=affiliate";


            $user_name           = Utility::short_text($row["full_name"],0,21,true);
            $user_company_name   = Utility::short_text($row["company_name"],0,21,true);

            $user_detail         = '<a href="'.$client_link.'"><strong title="'.$row["full_name"].'">'.$user_name.'</strong></a><br><span class="mobcomname" title="'.$row["company_name"].'">'.$user_company_name.'</span>';

            $user_name           = Utility::short_text($aff_c["full_name"],0,21,true);
            $user_company_name   = Utility::short_text($aff_c["company_name"],0,21,true);

            $aff_detail         = '<a href="'.$aff_link.'"><strong title="'.$aff_c["full_name"].'">'.$user_name.'</strong></a><br><span class="mobcomname" title="'.$aff_c["company_name"].'">'.$user_company_name.'</span>';


            $item   = [];
            array_push($item,$i);
            array_push($item,$aff_detail);
            array_push($item,$user_detail);
            $perms  = '';

            $perms .= '<a href="javascript:void 0;" onclick="delete_assigned_client('.$row["id"].');" data-tooltip="'.__("admin/users/button-delete").'" class="sbtn red"><i class="fa fa-trash"></i></a> ';

            array_push($item,$perms);

            $items[] = $item;
        }
    }


    return $items;