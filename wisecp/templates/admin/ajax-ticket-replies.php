<?php
    if(isset($replies) && $replies){

        $privOperation  = Admin::isPrivilege("TICKETS_OPERATION");
        $privDelete     = Admin::isPrivilege("TICKETS_DELETE");
        $privOrder      = Admin::isPrivilege("ORDERS_LOOK");
        $privUser       = Admin::isPrivilege("USERS_LOOK");
        $vCheck         = version_compare(License::get_version(),'3.1.9','>');

        foreach($replies AS $k=>$reply){
            $message    = $reply["message"];
            if(!Validation::isHTML($message)) $message = nl2br($message);
            $message    = Filter::link_convert($message,true);

            $user_link      = Controllers::$init->AdminCRLink("users-2",["detail",$reply["user_id"]]);


            ?>
            <div class="<?php echo !$reply["admin"] && $get_last_reply_id > 0 ? 'new-reply ' : ''; ?>destekdetaymsj reply-item-<?php echo $reply["id"]; ?>"<?php echo $reply["admin"] ? ' id="yetkilimsj"' : ''; ?>>
                <div class="destekdetaymsjcon" id="Reply<?php echo $reply["id"]; ?>">
                    <div class="msjyazan">
                        <h4>
                            <?php if($reply["admin"]): ?>
                                <?php echo $reply["name"]; ?>
                                <span><?php echo __("admin/tickets/request-detail-reply-admin"); ?></span>
                            <?php else: ?>
                                <a href="<?php echo $user_link?>"><?php echo $reply["name"]; ?></a>
                                <span><?php echo __("admin/tickets/request-detail-reply-user"); ?></span>
                            <?php endif; ?>

                            <?php if($privOperation): ?>
                                <a class="ticketeditbtn" href="javascript:editReply(<?php echo $reply["id"]; ?>);void 0;"> <i class="fa fa-pencil-square-o" aria-hidden="true"></i> <?php echo __("admin/tickets/request-detail-button-edtreply"); ?></a>
                            <?php endif; ?>

                            <?php if($privDelete): ?>
                                <a class="ticketdelbtn" href="javascript:deleteReply(<?php echo $reply["id"]; ?>);void 0;"> <i class="fa fa-trash-o" aria-hidden="true"></i> <?php echo __("admin/tickets/request-detail-button-delreply"); ?></a>
                            <?php endif; ?>

                            <span class="ticketsip">IP: <a style="text-decoration:underline;" referrerpolicy="no-referrer" href="https://check-host.net/ip-info?host=<?php echo $reply["ip"]; ?>" target="_blank"><?php echo $reply["ip"]; ?></a></span>

                        </h4>
                        <h5><?php echo DateManager::format(Config::get("options/date-format")." - H:i",$reply["ctime"]); ?></h5>
                        <div class="ticket-message-toolbar">
                            <a class="ticket-message-toolbar-mark-custom-field" href="javascript:void 0;" onclick="mark_as_custom_data(this,<?php echo $reply["id"]; ?>);" data-order="3" style="<?php echo $vCheck ? '' : 'display: none;'; ?>"><i class="fa fa-shield"></i> <?php echo __("admin/tickets/mark-as-custom-data"); ?></a>
                        </div>
                    </div>
                    <div class="clear"></div>
                    <div class="reply-message">
                        <?php echo $message; ?>
                        <?php if($reply["admin"]): ?>
                            <div class="goruldu">
                                <?php if($k == 0 && $reply["admin"]): ?>
                                    <?php if($ticket["userunread"]): ?>
                                        <i title="<?php echo htmlentities(__("admin/tickets/request-detail-user-viewed"),ENT_QUOTES); ?>" class="ion-android-done-all"></i>
                                    <?php else: ?>
                                        <i title="<?php echo htmlentities(__("admin/tickets/request-detail-user-unviewed"),ENT_QUOTES); ?>" class="gorulmedi ion-android-done-all"></i>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="clear"></div>
                    <div style="display: none;" id="editReply_<?php echo $reply["id"]; ?>">
                        <textarea class="tinymce-1" id="editReply_<?php echo $reply["id"]; ?>_msg"></textarea>
                        <div class="clear"></div>
                        <div class="ticket-update-btns">
                            <a href="javascript:void(0);" class="red lbtn edit-btn-cancel"><?php echo __("admin/tickets/button-cancel"); ?></a>
                            <a href="javascript:void(0);" class="blue lbtn edit-btn-ok"><i class="fas fa-save"></i> <?php echo __("admin/tickets/button-save"); ?></a>
                        </div>
                    </div>
                    <div class="clear"></div>

                    <?php if($reply["pipe"]): ?>
                        <p><i><?php echo __("admin/tickets/ticket-reply-pipe-note"); ?></i></p>

                        <div class="clear"></div>
                    <?php endif; ?>

                    <?php
                        if($reply["attachments"]){
                            ?>
                            <div class="ticketattachments">
                                <?php
                                    $attachment_images  = [];
                                    $attachment_files   = [];

                                    foreach($reply["attachments"] AS $attachment) {
                                        $ex = explode(".", $attachment["name"]);
                                        $ex = end($ex);
                                        $is_img = in_array($ex, ['jpg', 'png', 'gif', 'jpeg']);

                                        $attachment["is_img"] = $is_img;

                                        if($is_img)
                                            $attachment_images[] = $attachment;
                                        else
                                            $attachment_files[] = $attachment;
                                    }
                                ?>

                                <?php if($attachment_images): ?>
                                    <div class="ticketreplyphotos">
                                        <?php
                                            foreach($attachment_images AS $attachment){

                                                $link = Controllers::$init->AdminCRLink("download-id",["reply-attachment",$attachment["id"]]);
                                                $link_thumbnail = Controllers::$init->AdminCRLink("download-id",["reply-attachment",$attachment["id"]])."?thumbnail=true";
                                                ?>
                                                <a href="<?php echo $link; ?>" target="_blank">

                                                    <img src="<?php echo $link_thumbnail; ?>" height="30" width="auto">
                                                    <span> <?php echo Utility::short_text($attachment["file_name"],0,100,true); ?></span>
                                                </a>
                                                <?php
                                            }
                                        ?>
                                    </div>
                                <?php endif; ?>
                                <?php if($attachment_files): ?>
                                    <div class="ticketreplypfiles">
                                        <?php
                                            foreach($attachment_files AS $attachment){

                                                $link = Controllers::$init->AdminCRLink("download-id",["reply-attachment",$attachment["id"]]);
                                                ?>
                                                <a href="<?php echo $link; ?>" data-balloon-pos="right" data-balloon="<?php echo __("admin/tickets/request-detail-button-rply-atch"); ?>" target="_blank">

                                                    <i class="fa fa-cloud-download" aria-hidden="true"></i>
                                                    <?php echo Utility::short_text($attachment["file_name"],0,100,true); ?>
                                                </a>
                                                <div class="clear"></div>
                                                <?php
                                            }
                                        ?>
                                    </div>
                                <?php endif; ?>

                            </div>
                            <?php

                        }
                    ?>

                    <?php
                        if($reply["encrypted"])
                        {
                            ?>
                            <div class="securemsj"><i title="<?php echo __("website/account_tickets/encrypt-message-3"); ?>" class="fa fa-shield" aria-hidden="true"></i></div>
                            <?php
                        }
                    ?>

                    <div class="clear"></div>
                </div>
            </div>
            <?php
        }
    }
?>

<script type="text/javascript">
    $(document).ready(function(){
        if(!device_mobile){
            tinymce_init('.tinymce-1');
        }
        $('.new-reply').animate({opacity: 1}, 2000);
    });
</script>