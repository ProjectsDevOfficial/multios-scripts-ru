<?php
    $items  = [];

    if(isset($list) && $list){
        foreach($list AS $i=>$row){
            $id     = $row["id"];


            $item   = [];

            $name               = $row["name"];
            $identifier         = $row["identifier"];
            $permissions        = Utility::jdecode($row["permissions"],true);
            $ips                = $row["ips"];
            if($ips) $ips = explode(",",$ips);
            else
                $ips = [];

            $details    = base64_encode(Utility::jencode([
                'id'            => $id,
                'name'          => $name,
                'identifier'    => $identifier,
                'permissions'   => $permissions,
                'ips'           => $ips,
            ]));

            $controls   = '<a data-tooltip="'.___("needs/button-detail").'" href="javascript:void 0;" onclick="DetailApiCredential(this);" data-details=\''.$details.'\' class="sbtn"><i class="fa fa-search"></i></a> ';
            $controls .= '<a data-tooltip="'.___("needs/button-delete").'" href="javascript:void 0;" onclick="DeleteApiCredential(this,'.$id.');" class="sbtn red"><i class="fa fa-trash"></i></a>';


            $name               = '<span title="'.htmlentities($name,ENT_QUOTES).'">'.Utility::short_text($name,0,30,true).'</span>';
            $identifier         = '<a data-tooltip="'.__("admin/settings/api-text22").'" href="javascript:void 0;" onclick="copyApiKey(this);" data-key="'.$identifier.'">'.Utility::substr($identifier,0,5).str_repeat("*",10).Utility::substr($identifier,-5).' <i class="fa fa-copy"></i></span>';
            $permissions        = '<span>'.current($permissions).(sizeof($permissions) > 1 ? '...' : '').'</span>';
            $ips                = '<span>'.($ips ? $ips[0] : "---").(sizeof($ips)>1 ? '...' : '').'</span>';



            array_push($item,$i);
            array_push($item,$name);
            array_push($item,$identifier);
            array_push($item,$permissions);
            array_push($item,$ips);
            array_push($item, str_starts_with($row["last_access"], "1970") ? "---" : DateManager::format(Config::get("options/date-format")." H:i:s",$row["last_access"]));
            array_push($item,$controls);



            $items[] = $item;
        }
    }


    return $items;