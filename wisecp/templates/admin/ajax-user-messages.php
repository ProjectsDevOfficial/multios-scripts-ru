<?php
    $items  = [];

    if(isset($list) && $list){
        foreach($list AS $i=>$row){
            $id     = $row["id"];

            $content = $row["content"];

            $item   = [];
            array_push($item,$i);
            array_push($item,$row["subject"]);
            array_push($item,$row["date"]);
            $perms  = '
            
            <div id="message_'.$id .'" style="display: none;">
            '.base64_encode($content).'
            </div>
            ';

            $perms .= '<a href="javascript:showMessage('.$id.',\''.str_replace(['"',"'"],['\"',"\'"],$row["subject"]).'\');void 0;" data-tooltip="'.__("admin/users/detail-messages-show-message").'" class="sbtn"><i class="fa fa-eye"></i></a> ';

            array_push($item,$perms);



            $items[] = $item;
        }
    }


    return $items;