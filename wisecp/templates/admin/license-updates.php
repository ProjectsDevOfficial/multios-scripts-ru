<!DOCTYPE html>
<html>
<head>
    <?php
        $plugins        = [];
        include __DIR__.DS."inc".DS."head.php";

        $next_version = Updates::check_new_version();
        $next_version_num   = isset($next_version["version"]) ? $next_version["version"] : false;

        $update_error       = isset($next_version["status"]) && $next_version["status"] == "error";

        $is_beta_update     = $next_version["is_beta_update"] ?? false;
        $beta_version       = $is_beta_update ? $next_version["Beta"]["version"] : false;

        $next_version_nm    = $next_version_num.(isset($next_version["type"]) && $next_version["type"] == "Beta" ? " ".$next_version["type"].($is_beta_update && $beta_version ? ' V'.$beta_version : '') : '');

    ?>
    <style type="text/css">
        .guncellebtn{border-top:1px solid #dbdbdb;width:100%;margin-top:15px}
        .update-confirm {    padding: 0px 25px;margin-top:10px;text-align:center;}
        .update-confirm h4 {font-weight:bold;margin: 20px 0px;color: #8bc34a}
        .update-confirm h5 {margin-bottom:20px;    font-size: 16px;}
        .update-confirm label {margin: 15px; font-size:15px;}
        .confirm_button{cursor:pointer;margin-top:30px;margin-bottom:20px;width:270px}
        .backuppath{border:2px dotted #ccc;padding:10px 20px;border-radius:5px;background:#fafafa;display:inline-block;margin-top:10px;margin-bottom:15px}
        #backupprocesserror h4 {color: #F44336;}
        #backupprocesserror i {font-size:70px;color: #F44336;}
        .backuprocessreason{color:#F44336;}
        .update-progress{height:40px;width:100%;position:absolute;left:0px;    margin-top: 15px;}
        .blob{width:2rem;height:2rem;background:#38647a;border-radius:50%;position:absolute;left:calc(50% - 1rem);top:calc(50% - 1rem)}.blob-2{-webkit-animation:animate-to-2 1.5s infinite;animation:animate-to-2 1.5s infinite}.blob-3{-webkit-animation:animate-to-3 1.5s infinite;animation:animate-to-3 1.5s infinite}.blob-1{-webkit-animation:animate-to-1 1.5s infinite;animation:animate-to-1 1.5s infinite}.blob-4{-webkit-animation:animate-to-4 1.5s infinite;animation:animate-to-4 1.5s infinite}.blob-0{-webkit-animation:animate-to-0 1.5s infinite;animation:animate-to-0 1.5s infinite}.blob-5{-webkit-animation:animate-to-5 1.5s infinite;animation:animate-to-5 1.5s infinite}@-webkit-keyframes animate-to-2{25%,75%{-webkit-transform:translateX(-1.5rem) scale(0.75);transform:translateX(-1.5rem) scale(0.75)}95%{-webkit-transform:translateX(0rem) scale(1);transform:translateX(0rem) scale(1)}}@keyframes animate-to-2{25%,75%{-webkit-transform:translateX(-1.5rem) scale(0.75);transform:translateX(-1.5rem) scale(0.75)}95%{-webkit-transform:translateX(0rem) scale(1);transform:translateX(0rem) scale(1)}}@-webkit-keyframes animate-to-3{25%,75%{-webkit-transform:translateX(1.5rem) scale(0.75);transform:translateX(1.5rem) scale(0.75)}95%{-webkit-transform:translateX(0rem) scale(1);transform:translateX(0rem) scale(1)}}@keyframes animate-to-3{25%,75%{-webkit-transform:translateX(1.5rem) scale(0.75);transform:translateX(1.5rem) scale(0.75)}95%{-webkit-transform:translateX(0rem) scale(1);transform:translateX(0rem) scale(1)}}@-webkit-keyframes animate-to-1{25%{-webkit-transform:translateX(-1.5rem) scale(0.75);transform:translateX(-1.5rem) scale(0.75)}50%,75%{-webkit-transform:translateX(-4.5rem) scale(0.6);transform:translateX(-4.5rem) scale(0.6)}95%{-webkit-transform:translateX(0rem) scale(1);transform:translateX(0rem) scale(1)}}@keyframes animate-to-1{25%{-webkit-transform:translateX(-1.5rem) scale(0.75);transform:translateX(-1.5rem) scale(0.75)}50%,75%{-webkit-transform:translateX(-4.5rem) scale(0.6);transform:translateX(-4.5rem) scale(0.6)}95%{-webkit-transform:translateX(0rem) scale(1);transform:translateX(0rem) scale(1)}}@-webkit-keyframes animate-to-4{25%{-webkit-transform:translateX(1.5rem) scale(0.75);transform:translateX(1.5rem) scale(0.75)}50%,75%{-webkit-transform:translateX(4.5rem) scale(0.6);transform:translateX(4.5rem) scale(0.6)}95%{-webkit-transform:translateX(0rem) scale(1);transform:translateX(0rem) scale(1)}}@keyframes animate-to-4{25%{-webkit-transform:translateX(1.5rem) scale(0.75);transform:translateX(1.5rem) scale(0.75)}50%,75%{-webkit-transform:translateX(4.5rem) scale(0.6);transform:translateX(4.5rem) scale(0.6)}95%{-webkit-transform:translateX(0rem) scale(1);transform:translateX(0rem) scale(1)}}@-webkit-keyframes animate-to-0{25%{-webkit-transform:translateX(-1.5rem) scale(0.75);transform:translateX(-1.5rem) scale(0.75)}50%{-webkit-transform:translateX(-4.5rem) scale(0.6);transform:translateX(-4.5rem) scale(0.6)}75%{-webkit-transform:translateX(-7.5rem) scale(0.5);transform:translateX(-7.5rem) scale(0.5)}95%{-webkit-transform:translateX(0rem) scale(1);transform:translateX(0rem) scale(1)}}@keyframes animate-to-0{25%{-webkit-transform:translateX(-1.5rem) scale(0.75);transform:translateX(-1.5rem) scale(0.75)}50%{-webkit-transform:translateX(-4.5rem) scale(0.6);transform:translateX(-4.5rem) scale(0.6)}75%{-webkit-transform:translateX(-7.5rem) scale(0.5);transform:translateX(-7.5rem) scale(0.5)}95%{-webkit-transform:translateX(0rem) scale(1);transform:translateX(0rem) scale(1)}}@-webkit-keyframes animate-to-5{25%{-webkit-transform:translateX(1.5rem) scale(0.75);transform:translateX(1.5rem) scale(0.75)}50%{-webkit-transform:translateX(4.5rem) scale(0.6);transform:translateX(4.5rem) scale(0.6)}75%{-webkit-transform:translateX(7.5rem) scale(0.5);transform:translateX(7.5rem) scale(0.5)}95%{-webkit-transform:translateX(0rem) scale(1);transform:translateX(0rem) scale(1)}}@keyframes animate-to-5{25%{-webkit-transform:translateX(1.5rem) scale(0.75);transform:translateX(1.5rem) scale(0.75)}50%{-webkit-transform:translateX(4.5rem) scale(0.6);transform:translateX(4.5rem) scale(0.6)}75%{-webkit-transform:translateX(7.5rem) scale(0.5);transform:translateX(7.5rem) scale(0.5)}95%{-webkit-transform:translateX(0rem) scale(1);transform:translateX(0rem) scale(1)}}
        .flicker-1{-webkit-animation:flicker-1 2s linear infinite both;animation:flicker-1 2s linear infinite both}
        @-webkit-keyframes flicker-1{0%,100%{opacity:1}41.99%{opacity:1}42%{opacity:0}43%{opacity:0}43.01%{opacity:1}47.99%{opacity:1}48%{opacity:0}49%{opacity:0}49.01%{opacity:1}}@keyframes flicker-1{0%,100%{opacity:1}41.99%{opacity:1}42%{opacity:0}43%{opacity:0}43.01%{opacity:1}47.99%{opacity:1}48%{opacity:0}49%{opacity:0}49.01%{opacity:1}}
    </style>
    <script type="text/javascript">
        var limit_error_result = {
            status      : 'error',
            message     : '<?php echo addslashes(__("admin/help/error10")); ?>'
        };
        $(document).ready(function(){
            $("#upgrade-version").on("click","#welcome_confirm",function(){
                $("#welcome-content").fadeOut(500,function(){
                    $("#backup-content").fadeIn(500);
                });
            });
            $("#upgrade-version").on("click","#backup_confirm",function(){
                var btn = this;
                $("#backup-content").fadeOut(500,function(){
                    $("#loading-content").fadeIn(500,function(){
                        var request = MioAjax({
                            action:"<?php echo $links["controller"] ?? ''; ?>",
                            method:"POST",
                            data:{
                                operation:"upgrade_version",
                                step: "backup",
                            },
                            button_element:btn,
                            waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                            progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                        },true,true);
                        request.done(backup_done);
                        request.fail(function(){
                            return backup_done('');
                        });
                    });
                });
            });
        });

        function upgrade_request(){
            var content_name = ".onoff-content";
            if($("#upgrade-content").css("display") !== "none")
                content_name = '#upgrade-content';
            else if($("#backup-content").css("display") !== "none")
                content_name = '#backup-content';
            else if($("#backup-content-error").css("display") !== "none")
                content_name = '#backup-content-error';

            if($("#loading-content").css("display") === "none")
            {
                $(content_name).fadeOut(500,function(){
                    $("#loading-content").fadeIn(500);
                });
            }

            var step = $("#this_step").val();

            var request = MioAjax({
                action:"<?php echo $links["controller"]; ?>",
                method:"POST",
                data:{
                    operation:step === "iconfirm" ? "iconfirm_upgrade_new_version" : "upgrade_version",
                    step:step,
                }
            },true,true);
            request.done(request_done);
            request.fail(function(){
                return request_done('');
            });
        }

        function upgrade_new_version(){
            var permission = <?php echo Admin::isPrivilege(["HELP_UPDATES_OPERATION"]) ? "true" : "false"; ?>;
            if(permission){
                open_modal('upgrade-version',{width:'800px'});
            }else{
                alert_error('<?php echo htmlentities(__("admin/help/error5"),ENT_QUOTES); ?>',{timer:3000});
            }


        }

        function backup_done(result)
        {
            if(result === '') result = limit_error_result;
            if(result !== ''){
                var solve = typeof result === "object" ? result : getJson(result);
                if(solve !== false){
                    if(solve.status === "error"){
                        $("#loading-desc").html('<?php echo __("admin/help/updates-text34"); ?>');
                        if(solve.message !== undefined && solve.message !== '')
                            $("#backup_error_desc").html(solve.message);
                        if(solve.version_data !== undefined && solve.version_data)
                            $("#manuel_backup_wrap").css("display","none");
                        else
                            $("#manuel_backup_wrap").css("display","block");

                        $("#loading-content").fadeOut(500,function(){
                            $("#backup-content-error").fadeIn(500);
                        });
                    }
                    else if(solve.status === "successful"){
                        $("#loading-desc").html('<?php echo __("admin/help/updates-text34"); ?>');
                        upgrade_request();
                    }
                }
            }
        }
        function request_done(result){
            if(result === '') result = limit_error_result;
            if(result !== ''){
                var solve = typeof result === "object" ? result : getJson(result);
                if(solve !== false){
                    if(solve.next_step !== undefined)
                    {
                        $("#this_step").val(solve.next_step);
                        $("#loading-desc").html(solve.next_step_desc);
                        upgrade_request();
                    }
                    else if(solve.status === "error")
                    {
                        if(solve.message != undefined && solve.message !== '')
                            $("#error_message").html(solve.message);

                        if(solve.for !== undefined && solve.for === "files"){
                            if(solve.files != undefined){
                                $("#update_files").html('');
                                $("#update_files_con").css("display","block");
                                $(solve.files).each(function(k,v){
                                    $("#update_files").append('<a href="'+v+'" target="_blank">'+v+'</a><br>');
                                });
                            }
                        }
                        else
                            $("#update_files_con").css("display","none");

                        $("#loading-content").fadeOut(500,function(){
                            $("#error_content").css("display","block");
                            $("#upgrade-content").fadeIn(500);
                        });
                    }
                    else if(solve.status === "successful"){
                        $("#loading-content").fadeOut(500,function(){
                            $("#error_content").css("display","none");
                            $("#success_content").css("display","block");
                            $("#upgrade-content").fadeIn(500,function(){
                                setTimeout(function(){
                                    window.location.href = window.location.href;
                                },3000);
                            });
                        });
                    }
                }
            }
        }
    </script>
</head>
<body>
<input type="hidden" value="iconfirm" id="this_step">

<?php include __DIR__.DS."inc/header.php"; ?>

<?php if(Admin::isPrivilege(["HELP_UPDATES_OPERATION"])): ?>
    <div id="upgrade-version" style="display: none" data-izimodal-title="<?php echo __("admin/help/updates-button-upgrade-version",['{version}' => "V".$next_version_nm]); ?>">
        <div class="padding20">
            <div align="center" id="loading-content" style="display: none;">
                <div class="update-confirm">
                    <h4 style="color: inherit;"><?php echo __("admin/help/updates-text10"); ?></h4>
                    <div class="update-progress">
                        <div class="blob blob-0"></div>
                        <div class="blob blob-1"></div>
                        <div class="blob blob-2"></div>
                        <div class="blob blob-3"></div>
                        <div class="blob blob-4"></div>
                        <div class="blob blob-5"></div>
                    </div>
                    <h5 class="flicker-1" style="margin-top: 120px;"><strong id="loading-desc"><?php echo __("admin/help/updates-text12"); ?></strong></h5>

                    <h5>(<?php echo __("admin/help/updates-text11"); ?>)</h5>
                </div>
            </div>
            <div align="center" class="onoff-content" id="welcome-content">
                <div class="update-confirm">
                    <h4><?php echo __("admin/help/updates-text17"); ?></h4>
                    <h5><?php echo __("admin/help/updates-text18"); ?></h5>
                    <h5><?php echo __("admin/help/updates-text19"); ?></h5>
                    <p><strong><?php echo __("admin/help/updates-text20"); ?></strong></p>
                </div>

                <div class="guncellebtn yuzde30">
                    <?php if(isset($button_hide) && $button_hide): ?>
                        <a style="opacity:.4;background:#919191; cursor:no-drop;" class="confirm_button yesilbtn gonderbtn" id="blocked_button"><?php echo __("admin/help/updates-text21"); ?></a>
                    <?php else: ?>
                        <a class="yesilbtn gonderbtn confirm_button" id="welcome_confirm"><?php echo __("admin/help/updates-text21"); ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <div align="center" class="onoff-content" id="backup-content" style="display: none;">

                <?php
                    if(isset($next_version["update_addon"]["status"]) && !$next_version["update_addon"]["status"])
                    {
                        if(($ui_lang ?? 'en') == "tr")
                            $_account_link = 'https://my.wisecp.com/hesabim/siparis-detay/'.$next_version["update_addon"]["order_id"];
                        else
                            $_account_link = 'https://my.wisecp.com/en/myaccount/order-detail/'.$next_version["update_addon"]["order_id"];
                        ?>
                        <div align="center" id="loading-content">
                            <div class="no-update-package">
            <i class="fa fa-exclamation-circle" aria-hidden="true" style="font-size: 50px;    margin: 15px;"></i>
                                <h4 style="    font-weight: 700;"><?php echo __("admin/help/update-addon-tx1"); ?></h4>
                                <h5 style="    margin: 15px;    font-weight: 600;"><?php echo __("admin/help/update-addon-tx2"); ?></h5>
                                <p><?php echo __("admin/help/update-addon-tx3"); ?></p>
                                <p><?php echo __("admin/help/update-addon-tx4"); ?></p>
                                <a target="_blank" href="<?php echo $_account_link; ?>" class="lbtn" style="margin: 15px;"><i class="fa fa-external-link" aria-hidden="true" style="margin-right: 10px;"></i><?php echo __("admin/help/update-addon-tx5"); ?></a>
                            </div>
                            <div class="guncellebtn yuzde30">
                                <a href="<?php echo $links["controller"]; ?>" class="yesilbtn gonderbtn confirm_button"><?php echo __("admin/help/update-addon-tx6"); ?></a>
                            </div>
                        </div>
                        <?php
                    }
                    else
                    {
                        ?>
                        <div class="update-confirm">
                            <h4><?php echo __("admin/help/updates-text22"); ?></h4>
                            <h5><?php echo __("admin/help/updates-text23"); ?></h5>
                            <h5><?php echo __("admin/help/updates-text24"); ?></h5>

                            <p><strong><?php echo __("admin/help/updates-text25"); ?></strong><br>
                                <span class="backuppath"><?php echo ROOT_DIR."backup".DS; ?></span></p>

                            <p><strong><?php echo __("admin/help/updates-text26"); ?></strong><br>
                                <a target="_blank" class="lbtn" href="<?php echo $links["controller"]."?operation=upgrade_version&step=download-database"; ?>" style="margin-top: 10px;padding: 10px 20px;"><i class="fa fa-download" aria-hidden="true"></i> <?php echo __("admin/help/updates-text27"); ?></a></p>

                            <input type="checkbox" class="checkbox-custom" id="backup-takenx" value="1" onchange="if($(this).prop('checked')) $('#backup_confirm').css('display','none'),$('#upgrade_confirmx').css('display','block'); else $('#upgrade_confirmx').css('display','none'),$('#backup_confirm').css('display','block');">
                            <label class="checkbox-custom-label" for="backup-takenx"><?php echo __("admin/help/update-backup-skip"); ?></label>
                            
                            
                        </div>
                        <div class="guncellebtn yuzde30">
                            <a class="confirm_button yesilbtn gonderbtn" id="backup_confirm"><?php echo __("admin/help/updates-text21"); ?></a>

                            <a class="confirm_button yesilbtn gonderbtn" id="upgrade_confirmx" onclick="upgrade_request();" style="display:none;"><?php echo __("admin/help/updates-upgrade-ok"); ?></a>
                        </div>
                        <?php
                    }
                ?>
            </div>
            <div align="center" class="onoff-content" id="backup-content-error" style="display: none;">
                <div class="update-confirm" id="backupprocesserror">
                    <i style="" class="fa fa-exclamation-circle"></i>
                    <h4><?php echo __("admin/help/updates-text28"); ?></h4>
                    <h5><?php echo __("admin/help/updates-text29"); ?></h5>
                    <p><strong><?php echo __("admin/help/updates-text30"); ?></strong><br>
                        <span class="backuprocessreason" id="backup_error_desc">UNKNOWN</span></p>

                    <div id="manuel_backup_wrap">
                        <p><strong><?php echo __("admin/help/updates-text31"); ?></strong></p>

                        <p><?php echo __("admin/help/updates-text32"); ?></p>

                        <input type="checkbox" class="checkbox-custom" id="backup-taken" value="1" onchange="if($(this).prop('checked')) $('#blocked_upgrade_confirm').css('display','none'),$('#upgrade_confirm').css('display','block'); else $('#upgrade_confirm').css('display','none'),$('#blocked_upgrade_confirm').css('display','block');">
                        <label class="checkbox-custom-label" for="backup-taken"><?php echo __("admin/help/updates-text33"); ?></label>
                    </div>
                </div>
                <div class="guncellebtn yuzde30">
                    <a style="opacity:.4;background:#919191; cursor:no-drop;" class="confirm_button yesilbtn gonderbtn" id="blocked_upgrade_confirm"><?php echo __("admin/help/updates-text21"); ?></a>
                    <a class="confirm_button yesilbtn gonderbtn" id="upgrade_confirm" onclick="upgrade_request();" style="display:none;"><?php echo __("admin/help/updates-text21"); ?></a>
                </div>
            </div>
            <div align="center" class="onoff-content" id="upgrade-content" style="display: none;">
                <div id="error_content" style="display: none;">
                    <div class="update-confirm" id="backupprocesserror">
                        <i class="fa fa-exclamation-circle"></i>
                        <h4><?php echo __("admin/help/updates-text36"); ?></h4>
                        <h5><?php echo __("admin/help/updates-text37"); ?></h5>
                        <p><strong><?php echo __("admin/help/updates-text30"); ?></strong><br>
                            <span class="backuprocessreason" id="error_message">UNKNOWN</span></p>
                    </div>
                    <div id="update_files_con" style="display: none;margin-bottom:15px;">
                        <?php echo __("admin/help/updates-upgrade-text4",[
                            '{directory}' => STORAGE_DIR."updates".DS.$next_version_num.DS,
                            '{files}'     => '<div id="update_files"></div>',
                        ]); ?>
                    </div>

                    <div class="guncellebtn yuzde30">
                        <a href="javascript:upgrade_request(); void 0" style="width:200px;" class="confirm_button yesilbtn gonderbtn"><?php echo __("admin/help/updates-upgrade-try-button"); ?></a>
                    </div>
                </div>
                <div id="success_content" style="display: none;">
                    <div style="margin-top:30px;margin-bottom:70px;text-align:center;">
                        <i style="font-size:70px;" class="fa fa-check"></i>
                        <h2 style="font-weight:bold;"><?php echo __("admin/help/updates-upgrade-text5"); ?></h2>
                        <br/>
                        <h4><?php echo __("admin/help/updates-upgrade-text6"); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div id="wrapper">

    <div class="icerik-container">
        <div class="icerik">

            <div class="icerikbaslik">
                <h1>
                    <strong><?php echo __("admin/help/page-updates"); ?></strong>
                </h1>
                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>

            <div class="updatesversion">

                <?php
                    if($update_error){

                        ?>
                        <div class="red-info">
                            <div class="padding10">
                                <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                                <p><?php echo $next_version["message"]; ?></p>
                            </div>
                        </div>
                        <?php

                    }
                    else{
                        if($next_version){
                            $before_version     = $next_version["before_version"];
                            $before_ver_num     = $before_version["version"];
                            $before_ver_date    = $before_version["ctime"];

                            $next_ver_num       = $next_version["version"];
                            $next_ver_date      = $next_version["ctime"];


                            if($before_version["type"] == "Beta" && isset($before_version["is_beta_update"]))
                            {
                                $before_ver_date = $before_version["Beta"]["ctime"];
                                $before_ver_num .= " <span>(Beta";

                                if($before_version["Beta"]["version"])
                                    $before_ver_num  .= " V".$before_version["Beta"]["version"];

                                $before_ver_num .= ")</span>";

                            }

                            if($next_version["type"] == "Beta" && isset($next_version["is_beta_update"]))
                            {
                                $next_ver_date = $next_version["Beta"]["ctime"];
                                $next_ver_num .= " <span>(Beta";

                                if($next_version["Beta"]["version"])
                                    $next_ver_num  .= " V".$next_version["Beta"]["version"];

                                $next_ver_num .= ")</span>";

                            }

                            ?>
                            <div class="updatenotice">
                                <i class="fa fa-thumbs-o-up" aria-hidden="true"></i>
                                <h2><?php echo __("admin/help/updates-text5"); ?></h2>
                                <h5><?php echo __("admin/help/updates-text6"); ?></h5>
                            </div>

                            <div align="center">
                                <div class="releasebox">
                                    <h4><div class="padding20"><?php echo __("admin/help/updates-current-version"); ?></div></h4>
                                    <h1><div class="padding20"><span>v</span><?php echo $before_ver_num; ?></div></h1>
                                    <h3><div class="padding20"><?php echo __("admin/help/updates-text4"); ?>: <?php echo DateManager::format(Config::get("options/date-format"),$before_ver_date); ?></div></h3>
                                    <h5><div class="padding20"><?php echo __("admin/help/updates-text3",['{link}' => $before_version["doc_url"]]); ?></div></h5>
                                </div>

                                <div class="releasebox" id="newrelease">
                                    <h4><div class="padding20"><?php echo __("admin/help/updates-text7"); ?></div></h4>
                                    <h1><div class="padding20"><span>v</span><?php echo $next_ver_num; ?></div></h1>
                                    <h3><div class="padding20"><?php echo __("admin/help/updates-text4"); ?>: <?php echo DateManager::format(Config::get("options/date-format"),$next_ver_date); ?></div></h3>
                                    <h5><div class="padding20"><?php echo __("admin/help/updates-text3",['{link}' => $next_version["doc_url"]]); ?></div></h5>
                                    <i class="fa fa-rocket" aria-hidden="true"></i>
                                </div>

                                <div class="clear"></div>
                                <a class="yesilbtn gonderbtn upgradebtn" id="addForm_submit" href="javascript:upgrade_new_version(); void 0"><?php echo __("admin/help/updates-text8"); ?></a>

                            </div>
                            <?php

                        }
                        else{
                            $versions   = Updates::get_versions();
                            $version    = $versions[0];
                            $beta_ver   = false;
                            $ver_date   = $version["ctime"];

                            if($version["type"] == "Beta" && isset($version["Beta"]) && $version["Beta"])
                            {
                                $last_beta_ver  = $version["Beta"][0];
                                $beta_ver       = $last_beta_ver["version"];
                                $ver_date       = $last_beta_ver["ctime"];
                            }

                            ?>
                            <div class="noupdateblock">
                                <div class="updatenotice">
                                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                                    <h2><?php echo __("admin/help/updates-text1"); ?></h2>
                                    <h5><?php echo __("admin/help/updates-text2"); ?></h5>
                                </div>

                                <div align="center">
                                    <div class="releasebox">
                                        <h4><div class="padding20"><?php echo __("admin/help/updates-current-version"); ?></div></h4>
                                        <h1><div class="padding20"><span>v</span><?php echo License::get_version().($version["type"] == "Beta" ? ' Beta'.($beta_ver ? 'V'.$beta_ver : '') : ''); ?></div></h1>
                                        <h3><div class="padding20"><?php echo __("admin/help/updates-text4"); ?>: <?php echo DateManager::format(Config::get("options/date-format"),$ver_date); ?></div></h3>
                                        <h5><div class="padding20"><?php echo __("admin/help/updates-text3",['{link}' => $version["doc_url"]]); ?></div></h5>
                                    </div>
                                </div>

                            </div>
                            <?php

                        }
                    }
                ?>

                <?php
                    if(!$next_version && isset($versions) && $versions){
                        ?>
                        <div class="versionhistory">
                            <h4><i class="fas fa-history"></i> <?php echo __("admin/help/updates-version-history"); ?></h4>
                            <div class="versionhistory-scroll">
                                <?php
                                    foreach($versions AS $k=>$row){
                                        ?>
                                        <div class="formcon">
                                            <div class="yuzde30" style="text-align:center;"><span>v<?php echo $row["version"]; ?></span></div>
                                            <div class="yuzde60">
                                                <?php echo nl2br($row["description"]); ?>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                ?>
                            </div>
                        </div>
                        <?php
                    }
                ?>
            </div>

            <div class="clear"></div>

        </div>
    </div>


</div>

<?php include __DIR__.DS."inc".DS."footer.php"; ?>

</body>
</html>