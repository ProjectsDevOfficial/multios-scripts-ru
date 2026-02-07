<?php
    $items  = [];

    if(isset($list) && $list){
        foreach($list AS $i=>$row){
            $id     = $row["id"];
            $row["reply"] = Utility::jdecode($row["reply"],true);
            $reply        = [];

            foreach(Bootstrap::$lang->rank_list() AS $item)
                $reply[$item["key"]] = $row["reply"][$item["key"]] ?? '';

            $row["reply"] = $reply;

            $item   = [];

            array_push($item,$i);
            array_push($item,$row["name"]);
            array_push($item,'<a href="javascript:void 0;" class="sbtn" onclick="auto_task_modal(\'update\',\''.base64_encode(Utility::jencode($row)).'\')" ><i class="fa fa-search"></i></a> <a class="sbtn red" href="javascript:void 0;" onclick="auto_task_modal(\'delete\','.$id.',this)"><i class="fa fa-trash"></i></a>');


            $items[] = $item;
        }
    }


    return $items;