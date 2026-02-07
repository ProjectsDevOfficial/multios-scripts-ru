<?php
    $items  = [];

    if(isset($list) && $list){
        foreach($list AS $i=>$row){
            $id     = $row["id"];


            $item   = [];

            $name   =  $row["api"];


            $name               = '<span title="'.htmlentities($name,ENT_QUOTES).'">'.Utility::short_text($name,0,30,true).'</span>';

            $response_json      = Utility::jdecode($row["response"],true);
            $response           = json_encode($row["response"] ? $response_json : [],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);

            if(!$response_json) $response = $row["response"];

            $details            = base64_encode(Utility::jencode([
                'request_header'    => json_encode($row["request_header"] ? Utility::jdecode($row["request_header"],true) : [],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),
                'request_body'      => json_encode($row["request_body"] ? Utility::jdecode($row["request_body"],true) : [],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),
                'response'          => $response,
            ]));

            array_push($item,$i);
            array_push($item,strlen($row["api"]) > 1 ? $name : "None");
            array_push($item,'<strong>'.($row["method"] ?? "GET").' / </strong>'.$row["action"]);
            array_push($item,DateManager::format(Config::get("options/date-format")." H:i:s",$row["created_at"]));
            array_push($item,$row["ip"]);
            array_push($item,'<a class="sbtn" data-tooltip="'.___("needs/button-detail").'" href="javascript:void 0;" onclick="ApiLogDetail(this);" data-details="'.$details.'"><i class="fa fa-search"></i></a>');

           

            $items[] = $item;
        }
    }


    return $items;