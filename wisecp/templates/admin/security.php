<?php
    $settingsConfigure      = Admin::isPrivilege(['SECURITY_SETTINGS']);
    $backupConfigure        = Admin::isPrivilege(['SECURITY_BACKUP']);
    $activeSms              = Config::get("modules/sms");
    if($activeSms == 'none') $activeSms = false;

    $total_spam_hit         = FileManager::file_read(STORAGE_DIR."SPAM_COUNTER");
    if(!$total_spam_hit) $total_spam_hit = 0;

    $last_spam_records  = WDB::select()->from("last_spam_records")->order_by("id DESC")->build(true)->fetch_assoc();

?>
<!DOCTYPE html>
<html>
<head>
    <?php
        $plugins    = ['jquery-ui','select2','jQtags','dataTables'];
        include __DIR__.DS."inc".DS."head.php";
    ?>
    <script type="text/javascript">
        var
            waiting_text  = '<?php echo ___("needs/button-waiting"); ?>',
            progress_text = '<?php echo ___("needs/button-uploading"); ?>';
    </script>

</head>
<body>

<?php include __DIR__.DS."inc/header.php"; ?>

<div id="wrapper">

    <div class="icerik-container">
        <div class="icerik">

            <div class="icerikbaslik">
                <h1><?php echo __("admin/settings/page-security-name"); ?></h1>
                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>
            <script type="text/javascript">
                $(document).ready(function(){
                    $(".select2").select2({width: '100%'});
                    $(".extension-tags").tagsInput({
                        'width':'100%',
                        'height': '50px',
                        'interactive':true,
                        'defaultText':'<?php echo __("admin/settings/add-file-extension"); ?>',
                        'removeWithBackspace' : true,
                        'placeholderColor' : '#007a7a'
                    });

                    var tab = _GET("group");
                    if(tab != '' && tab != undefined){
                        $("#tab-group .tablinks[data-tab='"+tab+"']").click();
                    }else{
                        $("#tab-group .tablinks:eq(0)").addClass("active");
                        $("#tab-group .tabcontent:eq(0)").css("display","block");
                    }
                });
            </script>

            <div id="tab-group">
                <ul class="tab">

                    <?php if($settingsConfigure): ?>
                        <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'settings','group')" data-tab="settings"> <?php echo __("admin/settings/tab-security-settings"); ?></a></li>

                        <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'BotShield','group')" data-tab="BotShield"> <?php echo __("admin/settings/tab-security-botshield"); ?></a></li>

                        <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'captcha','group')" data-tab="captcha"> <?php echo __("admin/settings/tab-security-captcha"); ?></a></li>
                        <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'transaction-blocking','group')" data-tab="transaction-blocking"> <?php echo __("admin/settings/tab-security-transaction-blocking"); ?></a></li>
                    <?php endif; ?>

                    <?php if($backupConfigure): ?>
                        <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'backup','group')" data-tab="backup"> <?php echo __("admin/settings/tab-security-backup"); ?></a></li>
                    <?php endif; ?>

                    <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'prohibited','group')" data-tab="prohibited"> <?php echo __("admin/settings/tab-security-prohibited"); ?></a></li>

                    <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'spam','group')" data-tab="spam"> <?php echo __("admin/settings/tab-security-spam"); ?></a></li>

                </ul>

                <?php if($settingsConfigure): ?>
                    <div id="group-settings" class="tabcontent"><!-- tab content start -->

                        <div class="adminuyedetay">
                            <form action="<?php echo $links["controller"]; ?>" method="post" id="settingsForm">
                                <input type="hidden" name="operation" value="update_security_settings">

                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/settings/admin-folder"); ?></div>
                                    <div class="yuzde70">
                                        <input type="text" name="admin_folder" value="<?php echo $settings["admfolder"]; ?>" onchange="this.value = convertToSlug(this.value);">
                                        <span class="kinfo"><?php echo __("admin/settings/admin-folder-desc"); ?></span>
                                    </div>
                                </div>

                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/settings/two-factor-verification"); ?></div>
                                    <div class="yuzde70">

                                        <input <?php echo $settings["two-factor-verification"] ? 'checked ' : NULL; ?>type="checkbox" class="checkbox-custom" id="two-factor-verification" name="two-factor-verification" value="1"<?php echo $activeSms ? '' : ' disabled'; ?>>
                                        <label<?php echo $activeSms ? '' : ' onclick="alert(\''.addslashes(__("admin/settings/active-sms-error")).'\');"'; ?> class="checkbox-custom-label" for="two-factor-verification" style="margin-right: 20px;"><?php echo __("admin/settings/two-factor-verification-user"); ?></label>

                                        <input <?php echo $settings["two-factor-verification-admin"] ? 'checked ' : NULL; ?>type="checkbox" class="checkbox-custom" id="two-factor-verification-admin" name="two-factor-verification-admin" value="1"<?php echo $activeSms ? '' : ' disabled'; ?>>
                                        <label<?php echo $activeSms ? '' : ' onclick="alert(\''.addslashes(__("admin/settings/active-sms-error")).'\');"'; ?> class="checkbox-custom-label" for="two-factor-verification-admin"><?php echo __("admin/settings/two-factor-verification-admin"); ?></label>
                                        <div class="clear"></div>

                                        <span class="kinfo"><?php echo __("admin/settings/two-factor-verification-desc"); ?></span>
                                    </div>
                                </div>

                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/settings/location-verification"); ?></div>
                                    <div class="yuzde70">
                                        <input <?php echo $settings["location-verification"] ? 'checked ' : NULL; ?>type="checkbox" class="sitemio-checkbox" id="location-verification" name="location-verification" value="1" onchange="if($(this).prop('checked')) $('#location-verification-content').css('display','block'); else $('#location-verification-content').css('display','none');"<?php echo $activeSms ? '' : ' disabled'; ?>>
                                        <label<?php echo $activeSms ? '' : ' onclick="alert(\''.addslashes(__("admin/settings/active-sms-error")).'\');"'; ?> class="sitemio-checkbox-label" for="location-verification"></label>
                                        <span class="kinfo">(<?php echo __("admin/settings/location-verification-desc"); ?>)</span>

                                        <div class="formcon" id="location-verification-content" style="<?php echo $settings["location-verification"] ? '' : 'display: none;'; ?>margin-top:10px;border:none;">
                                            <div class="yuzde10"><?php echo __("admin/settings/location-verification-type"); ?></div>
                                            <div class="yuzde90">

                                                <input <?php echo $settings["location-verification-type"] == "country" ? 'checked ' : NULL; ?>type="radio" value="country" name="location-verification-type" class="radio-custom" id="location-verification-country"<?php echo $activeSms ? '' : ' disabled'; ?>>
                                                <label<?php echo $activeSms ? '' : ' onclick="alert(\''.addslashes(__("admin/settings/active-sms-error")).'\');"'; ?> class="radio-custom-label" for="location-verification-country" style="margin-right:20px;"><?php echo __("admin/settings/location-verification-country"); ?></label>

                                                <input <?php echo $settings["location-verification-type"] == "city" ? 'checked ' : NULL; ?>type="radio" value="city" name="location-verification-type" class="radio-custom" id="location-verification-city"<?php echo $activeSms ? '' : ' disabled'; ?>>
                                                <label<?php echo $activeSms ? '' : ' onclick="alert(\''.addslashes(__("admin/settings/active-sms-error")).'\');"'; ?> class="radio-custom-label" for="location-verification-city" style="margin-right:5px;"><?php echo __("admin/settings/location-verification-city"); ?></label>


                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/settings/cache-status"); ?></div>
                                    <div class="yuzde70">
                                        <input <?php echo $settings["cache"] ? 'checked ' : NULL; ?>type="checkbox" class="sitemio-checkbox" name="cache" value="1" id="cache">
                                        <label class="sitemio-checkbox-label" for="cache"></label>
                                        <span class="kinfo"><?php echo __("admin/settings/cache-status-desc"); ?></span>
                                    </div>
                                </div>


                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/settings/security-file-upload-settings"); ?></div>
                                    <div class="yuzde70">

                                        <strong class="kinfo"><?php echo __("admin/settings/product-field-file-extension"); ?></strong>
                                        <input class="extension-tags" type="text" name="prfiext" value="<?php echo $settings["prfiext"]; ?>">
                                        <br>

                                        <strong class="kinfo"><?php echo __("admin/settings/attachment-extension"); ?></strong>
                                        <input class="extension-tags" type="text" name="attext" value="<?php echo $settings["attext"]; ?>">

                                        <br>
                                        <input style="width:30px;" type="text" name="prfisize" value="<?php echo $settings["prfisize"]; ?>">
                                        <strong class="kinfo">MB</strong> <span class="kinfo">(<?php echo __("admin/settings/product-field-file-size"); ?>)</span>

                                        <br>
                                        <input style="width:30px;" type="text" name="attsize" value="<?php echo $settings["attsize"]; ?>">
                                        <strong class="kinfo">MB</strong> <span class="kinfo">(<?php echo __("admin/settings/attachment-file-size"); ?>)</span>

                                    </div>
                                </div>

                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/settings/password-length"); ?></div>
                                    <div class="yuzde70">
                                        <input style="width:30px;" type="text" name="password-length" value="<?php echo $settings["password-length"]; ?>">
                                        <span class="kinfo">(<?php echo __("admin/settings/password-length-desc"); ?>)</span>
                                    </div>
                                </div>


                                <div style="float:right;" class="guncellebtn yuzde30"><a id="settings_submit" href="javascript:void(0);" class="yesilbtn gonderbtn"><?php echo __("admin/settings/update-button1"); ?></a></div>

                            </form>
                            <script type="text/javascript">
                                $(document).ready(function(){
                                    $("#settingsForm").bind("keypress", function(e) {
                                        if (e.keyCode == 13) $("#settings_submit").click();
                                    });

                                    $("#settings_submit").on("click",function(){
                                        MioAjaxElement($(this),{
                                            waiting_text: waiting_text,
                                            progress_text: progress_text,
                                            result:"settingsForm_handler",
                                        });
                                    });
                                });

                                function settingsForm_handler(result){
                                    if(result != ''){
                                        var solve = getJson(result);
                                        if(solve !== false){
                                            if(solve.status == "error"){
                                                if(solve.for != undefined && solve.for != ''){
                                                    $("#settingsForm "+solve.for).focus();
                                                    $("#settingsForm "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                                    $("#settingsForm "+solve.for).change(function(){
                                                        $(this).removeAttr("style");
                                                    });
                                                }
                                                if(solve.message != undefined && solve.message != '')
                                                    alert_error(solve.message,{timer:5000});
                                            }else if(solve.status == "successful"){
                                                if(solve.redirect != undefined) window.location.href = solve.redirect;
                                                alert_success(solve.message,{timer:2000});
                                            }
                                        }else
                                            console.log(result);
                                    }
                                }

                                function ClearBlockingData(){
                                    var result = MioAjax({
                                        action:"<?php echo $links["controller"]; ?>",
                                        method:"POST",
                                        data:{operation:"clear_blocking_data"}
                                    },true);

                                    if(result != ''){
                                        var solve = getJson(result);
                                        if(solve !== false){
                                            if(solve.status == "error"){
                                                if(solve.message != undefined && solve.message != '')
                                                    alert_error(solve.message,{timer:5000});
                                            }else if(solve.status == "successful"){
                                                if(solve.redirect != undefined) window.location.href = solve.redirect;
                                                alert_success(solve.message,{timer:2000});
                                            }
                                        }else
                                            console.log(result);
                                    }

                                }
                            </script>
                        </div>


                        <div class="clear"></div>
                    </div><!-- tab content end -->

                    <div id="group-BotShield" class="tabcontent"><!-- tab content start -->
                        <div class="adminpagecon">

                            <div class="green-info">
                                <div class="padding20">
                                    <i class="fa fa-shield" aria-hidden="true"></i>
                                    <p><?php echo __("admin/settings/bot-shield-desc"); ?></p>
                                </div>
                            </div>

                            <form action="<?php echo $links["controller"]; ?>" method="post" id="botShieldForm">
                                <input type="hidden" name="operation" value="update_security_botshield">

                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/settings/bot-shield-status"); ?></div>
                                    <div class="yuzde70">
                                        <input<?php echo $settings["BotShield"]["status"] ? ' checked' : ''; ?> type="checkbox" class="sitemio-checkbox" id="bot-shield" name="bot-shield" value="1">
                                        <label class="sitemio-checkbox-label" for="bot-shield"></label>
                                        <span class="kinfo"><?php echo __("admin/settings/bot-shield-status-desc"); ?></span>
                                    </div>
                                </div>

                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/settings/bot-shield-within-time"); ?></div>
                                    <div class="yuzde70">
                                        <?php
                                            $period     = current(array_keys($settings["BotShield"]["within-time"]));
                                            $input      = '<input style="width:30px;margin-right:5px;" type="text" name="within-time-duration" value="'.$settings["BotShield"]["within-time"][$period].'">';
                                            $input .= '<select style="width:85px;" name="within-time-period">';
                                            $periods    = ___("date/time-periods");
                                            foreach($periods AS $key=>$val){
                                                $active = $key == $period ? ' selected' : NULL;
                                                $input .= '<option value="'.$key.'"'.$active.'>'.$val.'</option>';
                                            }
                                            $input .= '</select>';
                                            echo __("admin/settings/bot-shield-within-time-desc",['{input}' => $input]);
                                        ?>

                                    </div>
                                </div>

                                <style>
                                    .botshieldattempts strong {    width: 200px;display:inline-block;}
                                </style>

                                <div class="formcon">
                                    <div class="yuzde30">
                                        <?php echo __("admin/settings/bot-shield-attempts"); ?>
                                        <br>
                                        <span class="kinfo" style="font-weight: normal;"><?php echo __("admin/settings/bot-shield-attempts-desc"); ?></span>
                                    </div>
                                    <div class="yuzde70 botshieldattempts">
                                        <?php
                                            foreach($settings["BotShield"]["attempts"] AS $k=>$v){
                                                $input = '<input style="width:30px;margin-right:5px;" type="text" name="attempt['.$k.']" value="'.$v.'">';
                                                ?>
                                                <span style="line-height:45px;" class="kinfo"><?php echo __("admin/settings/bot-shield-".$k."-attempt",['{input}' => $input]); ?></span><br>
                                                <?php
                                            }
                                        ?>

                                    </div>
                                </div>



                                <div style="float:right;" class="guncellebtn yuzde30"><a id="botShieldForm_submit" href="javascript:void(0);" class="yesilbtn gonderbtn"><?php echo __("admin/settings/update-button1"); ?></a></div>

                            </form>
                            <script type="text/javascript">
                                $(document).ready(function(){
                                    $("#botShieldForm").bind("keypress", function(e) {
                                        if (e.keyCode == 13) $("#botShieldForm_submit").click();
                                    });

                                    $("#botShieldForm_submit").on("click",function(){
                                        MioAjaxElement($(this),{
                                            waiting_text: waiting_text,
                                            progress_text: progress_text,
                                            result:"botShieldForm_handler",
                                        });
                                    });
                                });

                                function botShieldForm_handler(result){
                                    if(result != ''){
                                        var solve = getJson(result);
                                        if(solve !== false){
                                            if(solve.status == "error"){
                                                if(solve.for != undefined && solve.for != ''){
                                                    $("#botShieldForm "+solve.for).focus();
                                                    $("#botShieldForm "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                                    $("#botShieldForm "+solve.for).change(function(){
                                                        $(this).removeAttr("style");
                                                    });
                                                }
                                                if(solve.message != undefined && solve.message != '')
                                                    alert_error(solve.message,{timer:5000});
                                            }else if(solve.status == "successful"){
                                                if(solve.redirect != undefined) window.location.href = solve.redirect;
                                                alert_success(solve.message,{timer:2000});
                                            }
                                        }else
                                            console.log(result);
                                    }
                                }
                            </script>
                        </div>


                        <div class="clear"></div>
                    </div><!-- tab content end -->

                    <div id="group-captcha" class="tabcontent"><!-- tab content start -->

                        <div class="adminuyedetay">

                            <div class="green-info">
                                <div class="padding20">
                                    <i class="fa fa-shield" aria-hidden="true"></i>
                                    <p>  <?php echo __("admin/settings/captcha-desc"); ?> </p>
                                </div>
                            </div>


                            <form action="<?php echo $links["controller"]; ?>" method="post" id="captchaForm">
                                <input type="hidden" name="operation" value="update_security_captcha">

                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/settings/captcha-status"); ?></div>
                                    <div class="yuzde70">
                                        <input type="checkbox" class="sitemio-checkbox" id="captcha_status" name="captcha_status" value="1"<?php echo $settings["captcha"]["status"] ? ' checked' : NULL; ?>>
                                        <label class="sitemio-checkbox-label" for="captcha_status"></label>
                                    </div>
                                </div>


                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/settings/captcha-type"); ?></div>
                                    <div class="yuzde70">
                                        <?php
                                            if(isset($captcha_modules) && $captcha_modules)
                                            {
                                                $captcha_modules_keys = array_keys($captcha_modules);
                                                if(!in_array($settings["captcha"]["type"],$captcha_modules_keys))
                                                    $settings["captcha"]["type"] = $captcha_modules_keys[0];

                                                foreach($captcha_modules AS $mn => $mc)
                                                {
                                                    $mln        = $mn;
                                                    if(isset($mc["lang"]["name"])) $mln = $mc["lang"]["name"];
                                                    $selected   = $settings["captcha"]["type"] == $mn;
                                                    ?>
                                                    <input<?php echo $selected ? ' checked' : NULL; ?> type="radio" class="radio-custom" name="captcha_type" value="<?php echo $mn; ?>" id="captcha_<?php echo $mn; ?>">
                                                    <label for="captcha_<?php echo $mn; ?>" class="radio-custom-label" style="margin-right: 15px;"><?php echo $mln; ?></label>
                                                    <?php
                                                }
                                            }
                                        ?>
                                    </div>
                                </div>

                                <div id="CaptchaCon_loader" style="text-align:center; display: none;">
                                    <center><i style="font-size:24px;padding:20px;" class="loadingicon fa fa-cog" aria-hidden="true"></i></center>
                                </div>
                                <div  id="CaptchaCon">

                                </div>



                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/settings/captcha-areas"); ?></div>
                                    <div class="yuzde70">
                                        <style>.captchaactiveblock {display:inline-block;width:200px;margin-bottom: 5px;}</style>
                                        <div class="captchaactiveblock" >
                                            <input type="checkbox" class="checkbox-custom" id="captcha_contact_form" name="captcha_contact_form" value="1"<?php echo $settings["captcha"]["contact-form"] ? ' checked' : NULL; ?>>
                                            <label class="checkbox-custom-label" for="captcha_contact_form"></label>
                                            <span class="kinfo"><?php echo __("admin/settings/captcha-contact-form"); ?></span>
                                        </div>


                                        <div class="captchaactiveblock" >
                                            <input type="checkbox" class="checkbox-custom" id="captcha_sign_up" name="captcha_sign_up" value="1"<?php echo $settings["captcha"]["sign-up"] ? ' checked' : NULL; ?>>
                                            <label class="checkbox-custom-label" for="captcha_sign_up"></label>
                                            <span class="kinfo"><?php echo __("admin/settings/captcha-sign-up"); ?></span>
                                        </div>


                                        <div class="captchaactiveblock" >
                                            <input type="checkbox" class="checkbox-custom" id="captcha_sign_in" name="captcha_sign_in" value="1"<?php echo $settings["captcha"]["sign-in"] ? ' checked' : NULL; ?>>
                                            <label class="checkbox-custom-label" for="captcha_sign_in"></label>
                                            <span class="kinfo"><?php echo __("admin/settings/captcha-sign-in"); ?></span>
                                        </div>


                                        <div class="captchaactiveblock" >
                                            <input type="checkbox" class="checkbox-custom" id="captcha_sign_forget" name="captcha_sign_forget" value="1"<?php echo $settings["captcha"]["sign-forget"] ? ' checked' : NULL; ?>>
                                            <label class="checkbox-custom-label" for="captcha_sign_forget"></label>
                                            <span class="kinfo"><?php echo __("admin/settings/captcha-sign-forget"); ?></span>
                                        </div>


                                        <div class="captchaactiveblock" >
                                            <input type="checkbox" class="checkbox-custom" id="captcha_customer_feedback" name="captcha_customer_feedback" value="1"<?php echo $settings["captcha"]["customer-feedback"] ? ' checked' : NULL; ?>>
                                            <label class="checkbox-custom-label" for="captcha_customer_feedback"></label>
                                            <span class="kinfo"><?php echo __("admin/settings/captcha-customer-feedback"); ?></span>
                                        </div>


                                        <div class="captchaactiveblock" >
                                            <input type="checkbox" class="checkbox-custom" id="captcha_newsletter" name="captcha_newsletter" value="1"<?php echo $settings["captcha"]["newsletter"] ? ' checked' : NULL; ?>>
                                            <label class="checkbox-custom-label" for="captcha_newsletter"></label>
                                            <span class="kinfo"><?php echo __("admin/settings/captcha-newsletter"); ?></span>
                                        </div>


                                        <div class="captchaactiveblock" >
                                            <input type="checkbox" class="checkbox-custom" id="captcha_domain_check" name="captcha_domain_check" value="1"<?php echo $settings["captcha"]["domain-check"] ? ' checked' : NULL; ?>>
                                            <label class="checkbox-custom-label" for="captcha_domain_check"></label>
                                            <span class="kinfo"><?php echo __("admin/settings/captcha-domain-check"); ?></span>
                                        </div>

                                        <div class="captchaactiveblock" >
                                            <input type="checkbox" class="checkbox-custom" id="captcha_software_license" name="captcha_software_license" value="1"<?php echo $settings["captcha"]["software-license"] ? ' checked' : NULL; ?>>
                                            <label class="checkbox-custom-label" for="captcha_software_license"></label>
                                            <span class="kinfo"><?php echo __("admin/settings/captcha-software-license"); ?></span>
                                        </div>


                                    </div>
                                </div>


                                <div style="float:right;" class="guncellebtn yuzde30"><a id="captchaForm_submit" href="javascript:void(0);" class="yesilbtn gonderbtn"><?php echo __("admin/settings/update-button1"); ?></a></div>

                            </form>
                            <script type="text/javascript">
                                $(document).ready(function(){
                                    $("input[name=captcha_type]").change(() => changed_captcha_type());
                                    changed_captcha_type();
                                    $("#captchaForm_submit").on("click",function(){
                                        MioAjaxElement($(this),{
                                            waiting_text: waiting_text,
                                            progress_text: progress_text,
                                            result:"captchaForm_handler",
                                        });
                                    });
                                });

                                function changed_captcha_type()
                                {
                                    var selected_captcha = $("input[name=captcha_type]:checked").val();
                                    $("#CaptchaCon_loader").css('display','block');
                                    $('#CaptchaCon').css('display','none');
                                    var request = MioAjax({
                                        action: "<?php echo $links["controller"]; ?>",
                                        method: "POST",
                                        data: {operation:"get_captcha_content",module:selected_captcha}
                                    },true,true);
                                    request.done(function(result){
                                        $("#CaptchaCon_loader").css("display","none");
                                        $("#CaptchaCon").css("display","block").html(result);
                                    });
                                }

                                function captchaForm_handler(result){
                                    if(result != ''){
                                        var solve = getJson(result);
                                        if(solve !== false){
                                            if(solve.status == "error"){
                                                if(solve.for != undefined && solve.for != ''){
                                                    $("#captchaForm "+solve.for).focus();
                                                    $("#captchaForm "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                                    $("#captchaForm "+solve.for).change(function(){
                                                        $(this).removeAttr("style");
                                                    });
                                                }
                                                if(solve.message != undefined && solve.message != '')
                                                    alert_error(solve.message,{timer:5000});
                                            }else if(solve.status == "successful"){
                                                if(solve.redirect != undefined) window.location.href = solve.redirect;
                                                alert_success(solve.message,{timer:2000});
                                            }
                                        }else
                                            console.log(result);
                                    }
                                }
                            </script>
                        </div>


                        <div class="clear"></div>
                    </div><!-- tab content end -->

                    <div id="group-transaction-blocking" class="tabcontent"><!-- tab content start -->

                        <div class="adminuyedetay">


                            <div class="green-info">
                                <div class="padding20">
                                    <i class="fa fa-clock-o" aria-hidden="true"></i>
                                    <p> <?php echo __("admin/settings/security-blocking-times-desc"); ?> </p></div>
                            </div>


                            <form action="<?php echo $links["controller"]; ?>" method="post" id="transactionBlockingForm">
                                <input type="hidden" name="operation" value="update_security_transaction_blocking">

                                <div class="formcon">
                                    <div class="yuzde30">
                                        <?php echo __("admin/settings/security-blocking-times"); ?><br>
                                        <span style="font-weight:normal;" class="kinfo"> </span>
                                        <br><br>
                                        <a href="javascript:ClearBlockingData();void 0;" class="lbtn"><i class="fa fa-trash"></i> <?php echo __("admin/settings/clear-blocking-data-button"); ?></a>

                                    </div>
                                    <div class="yuzde70 botshieldattempts">

                                        <?php
                                            $period     = current(array_keys($settings["blgte"]["sign-in-attempt"]));
                                            $slimit = '<input style="width:30px;margin-right:5px;" type="text" name="sign_in_attempt" value="'.$settings["sign"]["in"]["attempt_limit"].'">';

                                            $blocking = '<input style="width:30px;margin-right:5px;" type="text" name="blgte_sign_in_attempt_time" value="'.$settings["blgte"]["sign-in-attempt"][$period].'">';
                                            $blocking .= '<select style="width:85px;" name="blgte_sign_in_attempt_period">';
                                            $periods    = ___("date/time-periods");
                                            foreach($periods AS $key=>$val){
                                                $active = $key == $period ? ' selected' : NULL;
                                                $blocking .= '<option value="'.$key.'"'.$active.'>'.$val.'</option>';
                                            }
                                            $blocking .= '</select>';
                                        ?>
                                        <span style="line-height:45px;" class="kinfo"><?php echo __("admin/settings/sign-in-attempt",['{limit}' => $slimit,'{blocking}' => $blocking]); ?></span>

                                        <br>

                                        <?php
                                            $period     = current(array_keys($settings["blgte"]["forget-password"]));
                                            $slimit = '<input style="width:30px;margin-right:5px;" type="text" name="sign_fpassword_attempt" value="'.$settings["sign"]["forget"]["attempt_limit"].'">';

                                            $blocking = '<input style="width:30px;margin-right:5px;" type="text" name="blgte_fpassword_time" value="'.$settings["blgte"]["forget-password"][$period].'">';
                                            $blocking .= '<select style="width:85px;" name="blgte_fpassword_period">';
                                            $periods    = ___("date/time-periods");
                                            foreach($periods AS $key=>$val){
                                                $active = $key == $period ? ' selected' : NULL;
                                                $blocking .= '<option value="'.$key.'"'.$active.'>'.$val.'</option>';
                                            }
                                            $blocking .= '</select>';
                                        ?>
                                        <span style="line-height:45px;" class="kinfo"><?php echo __("admin/settings/sign-forget-password-attempt",['{limit}' => $slimit,'{blocking}' => $blocking]); ?></span>

                                        <br>

                                        <?php
                                            $period     = current(array_keys($settings["blgte"]["email-verify"]));
                                            $slimit = '<input style="width:30px;margin-right:5px;" type="text" name="sign_up_email_verify_attempt" value="'.$settings["sign"]["up"]["email"]["verify_checking_limit"].'">';

                                            $blocking = '<input style="width:30px;margin-right:5px;" type="text" name="blgte_email_verify_time" value="'.$settings["blgte"]["email-verify"][$period].'">';
                                            $blocking .= '<select style="width:85px;" name="blgte_email_verify_period">';
                                            $periods    = ___("date/time-periods");
                                            foreach($periods AS $key=>$val){
                                                $active = $key == $period ? ' selected' : NULL;
                                                $blocking .= '<option value="'.$key.'"'.$active.'>'.$val.'</option>';
                                            }
                                            $blocking .= '</select>';
                                        ?>
                                        <span style="line-height:45px;" class="kinfo"><?php echo __("admin/settings/sign-up-email-verify",['{limit}' => $slimit,'{blocking}' => $blocking]); ?></span>

                                        <br>

                                        <?php
                                            $period     = current(array_keys($settings["blgte"]["gsm-verify"]));
                                            $slimit = '<input style="width:30px;margin-right:5px;" type="text" name="sign_up_gsm_verify_attempt" value="'.$settings["sign"]["up"]["gsm"]["verify_checking_limit"].'">';

                                            $blocking = '<input style="width:30px;margin-right:5px;" type="text" name="blgte_gsm_verify_time" value="'.$settings["blgte"]["gsm-verify"][$period].'">';
                                            $blocking .= '<select style="width:85px;" name="blgte_gsm_verify_period">';
                                            $periods    = ___("date/time-periods");
                                            foreach($periods AS $key=>$val){
                                                $active = $key == $period ? ' selected' : NULL;
                                                $blocking .= '<option value="'.$key.'"'.$active.'>'.$val.'</option>';
                                            }
                                            $blocking .= '</select>';
                                        ?>
                                        <span style="line-height:45px;" class="kinfo"><?php echo __("admin/settings/sign-up-gsm-verify-attempt",['{limit}' => $slimit,'{blocking}' => $blocking]); ?></span>

                                        <br>

                                        <?php
                                            $period     = current(array_keys($settings["blgte"]["contact-form"]));
                                            $slimit     = '<input style="width:30px;margin-right:5px;" type="text" name="limit_contact_form_sending" value="'.$settings["limits"]["contact-form-sending"].'">';
                                            $blocking = '<input style="width:30px;margin-right:5px;" type="text" name="blgte_contact_form_time" value="'.$settings["blgte"]["contact-form"][$period].'">';
                                            $blocking .= '<select style="width:85px;" name="blgte_contact_form_period">';
                                            $periods    = ___("date/time-periods");
                                            foreach($periods AS $key=>$val){
                                                $active = $key == $period ? ' selected' : NULL;
                                                $blocking .= '<option value="'.$key.'"'.$active.'>'.$val.'</option>';
                                            }
                                            $blocking .= '</select>';
                                        ?>
                                        <span style="line-height:45px;" class="kinfo"><?php echo __("admin/settings/blocking-times-contact-form",['{limit}' => $slimit,'{blocking}' => $blocking]); ?></span>

                                        <br>

                                        <?php
                                            $period     = current(array_keys($settings["blgte"]["create-ticket"]));
                                            $slimit     = '<input style="width:30px;margin-right:5px;" type="text" name="limit_create_ticket_sending" value="'.$settings["limits"]["create-ticket-sending"].'">';
                                            $blocking = '<input style="width:30px;margin-right:5px;" type="text" name="blgte_create_ticket_time" value="'.$settings["blgte"]["create-ticket"][$period].'">';
                                            $blocking .= '<select style="width:85px;" name="blgte_create_ticket_period">';
                                            $periods    = ___("date/time-periods");
                                            foreach($periods AS $key=>$val){
                                                $active = $key == $period ? ' selected' : NULL;
                                                $blocking .= '<option value="'.$key.'"'.$active.'>'.$val.'</option>';
                                            }
                                            $blocking .= '</select>';
                                        ?>
                                        <span style="line-height:45px;" class="kinfo"><?php echo __("admin/settings/blocking-times-create-ticket",['{limit}' => $slimit,'{blocking}' => $blocking]); ?></span>

                                        <br>

                                        <?php
                                            $period     = current(array_keys($settings["blgte"]["customer-feedback"]));
                                            $slimit     = '<input style="width:30px;margin-right:5px;" type="text" name="limit_customer_feedback_sending" value="'.$settings["limits"]["customer-feedback-sending"].'">';
                                            $blocking = '<input style="width:30px;margin-right:5px;" type="text" name="blgte_customer_feedback_time" value="'.$settings["blgte"]["customer-feedback"][$period].'">';
                                            $blocking .= '<select style="width:85px;" name="blgte_customer_feedback_period">';
                                            $periods    = ___("date/time-periods");
                                            foreach($periods AS $key=>$val){
                                                $active = $key == $period ? ' selected' : NULL;
                                                $blocking .= '<option value="'.$key.'"'.$active.'>'.$val.'</option>';
                                            }
                                            $blocking .= '</select>';
                                        ?>
                                        <span style="line-height:45px;" class="kinfo"><?php echo __("admin/settings/blocking-times-cfeedback",['{limit}' => $slimit,'{blocking}' => $blocking]); ?></span>

                                        <br>

                                        <?php
                                            $period     = current(array_keys($settings["blgte"]["newsletter"]));
                                            $slimit     = '<input style="width:30px;margin-right:5px;" type="text" name="limit_newsletter_sending" value="'.$settings["limits"]["newsletter-sending"].'">';
                                            $blocking = '<input style="width:30px;margin-right:5px;" type="text" name="blgte_newsletter_time" value="'.$settings["blgte"]["newsletter"][$period].'">';
                                            $blocking .= '<select style="width:85px;" name="blgte_newsletter_period">';
                                            $periods    = ___("date/time-periods");
                                            foreach($periods AS $key=>$val){
                                                $active = $key == $period ? ' selected' : NULL;
                                                $blocking .= '<option value="'.$key.'"'.$active.'>'.$val.'</option>';
                                            }
                                            $blocking .= '</select>';
                                        ?>
                                        <span style="line-height:45px;" class="kinfo"><?php echo __("admin/settings/blocking-times-newsletter",['{limit}' => $slimit,'{blocking}' => $blocking]); ?></span>

                                        <br>

                                        <?php
                                            $period     = current(array_keys($settings["blgte"]["domain-check"]));
                                            $slimit     = '<input style="width:30px;margin-right:5px;" type="text" name="limit_domain_check" value="'.$settings["limits"]["domain-check"].'">';
                                            $blocking = '<input style="width:30px;margin-right:5px;" type="text" name="blgte_domain_check_time" value="'.$settings["blgte"]["domain-check"][$period].'">';
                                            $blocking .= '<select style="width:85px;" name="blgte_domain_check_period">';
                                            $periods    = ___("date/time-periods");
                                            foreach($periods AS $key=>$val){
                                                $active = $key == $period ? ' selected' : NULL;
                                                $blocking .= '<option value="'.$key.'"'.$active.'>'.$val.'</option>';
                                            }
                                            $blocking .= '</select>';
                                        ?>
                                        <span style="line-height:45px;" class="kinfo"><?php echo __("admin/settings/blocking-times-domain-check",['{limit}' => $slimit,'{blocking}' => $blocking]); ?></span>

                                    </div>
                                </div>

                                <div style="float:right;" class="guncellebtn yuzde30"><a id="transactionBlockingForm_submit" href="javascript:void(0);" class="yesilbtn gonderbtn"><?php echo __("admin/settings/update-button1"); ?></a></div>

                            </form>
                            <script type="text/javascript">
                                $(document).ready(function(){
                                    $("#transactionBlockingForm").bind("keypress", function(e) {
                                        if (e.keyCode == 13) $("#transactionBlockingForm_submit").click();
                                    });

                                    $("#transactionBlockingForm_submit").on("click",function(){
                                        MioAjaxElement($(this),{
                                            waiting_text: waiting_text,
                                            progress_text: progress_text,
                                            result:"transactionBlockingForm_handler",
                                        });
                                    });
                                });

                                function transactionBlockingForm_handler(result){
                                    if(result != ''){
                                        var solve = getJson(result);
                                        if(solve !== false){
                                            if(solve.status == "error"){
                                                if(solve.for != undefined && solve.for != ''){
                                                    $("#transactionBlockingForm "+solve.for).focus();
                                                    $("#transactionBlockingForm "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                                    $("#transactionBlockingForm "+solve.for).change(function(){
                                                        $(this).removeAttr("style");
                                                    });
                                                }
                                                if(solve.message != undefined && solve.message != '')
                                                    alert_error(solve.message,{timer:5000});
                                            }else if(solve.status == "successful"){
                                                if(solve.redirect != undefined) window.location.href = solve.redirect;
                                                alert_success(solve.message,{timer:2000});
                                            }
                                        }else
                                            console.log(result);
                                    }
                                }
                            </script>
                        </div>


                        <div class="clear"></div>
                    </div><!-- tab content end -->
                <?php endif; ?>

                <?php if($backupConfigure): ?>
                    <div id="group-backup" class="tabcontent"><!-- tab content start -->

                        <div class="adminuyedetay">
                            <form action="<?php echo $links["controller"]; ?>" method="post" id="backupForm">
                                <input type="hidden" name="operation" value="update_backup_settings">

                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/settings/database-backup-status"); ?></div>
                                    <div class="yuzde70">

                                        <input<?php echo $settings["backup-db"]["status"] ? ' checked' : NULL; ?> type="checkbox" name="backup_db_status" value="1" class="sitemio-checkbox" id="backup_db_status">
                                        <label for="backup_db_status" class="sitemio-checkbox-label"></label>
                                        <span class="kinfo"><?php echo substr($settings["backup-db"]["last-run-time"],0,4) == "0000" ? __("admin/automation/never-worked") : __("admin/settings/database-backup-last-update",['{date-time}' => DateManager::format(Config::get("options/date-format")." - H:i",$settings["backup-db"]["last-run-time"])]); ?></span>
                                    </div>
                                </div>

                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/settings/database-backup-period"); ?></div>
                                    <div class="yuzde70">

                                        <input style="width:30px;margin-right:5px;" type="text" name="backup_db_period_time" value="<?php echo $settings["backup-db"]["time"]; ?>">
                                        <select style="width:85px;" name="backup_db_period_type">
                                            <?php
                                                $periods    = ___("date/time-periods");
                                                foreach($periods AS $key=>$val){
                                                    $active = $key == $settings["backup-db"]["period"] ? ' selected' : NULL;
                                                    echo '<option value="'.$key.'"'.$active.'>'.$val.'</option>';
                                                }
                                            ?>
                                        </select>
                                        <br>
                                        <span class="kinfo"><?php echo __("admin/settings/database-backup-period-desc"); ?></span>

                                    </div>
                                </div>

                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/settings/database-backup-notification"); ?></div>
                                    <div class="yuzde70">
                                        <input<?php echo Config::get("notifications/admin-messages/created-backup-db/status") ? ' checked' : ''; ?> type="checkbox" id="backup_db_notification" name="backup_db_notification" value="1" class="checkbox-custom">
                                        <label class="checkbox-custom-label" for="backup_db_notification"><span class="kinfo"><?php echo __("admin/settings/database-backup-notification-desc"); ?></span></label>
                                    </div>
                                </div>

                                <div class="formcon">
                                    <div class="yuzde30">
                                        <?php echo __("admin/settings/database-backup-ftp"); ?>
                                        <br>
                                        <span style="font-weight:normal" class="kinfo"><?php echo __("admin/settings/database-backup-ftp-desc"); ?></span>
                                    </div>
                                    <div class="yuzde70">
                                        <strong class="kinfo" style="display: inline-block;width: 20%;"><?php echo __("admin/settings/database-backup-ftp-host"); ?>:</strong>
                                        <input style="width: 40%" autocomplete="off" type="text" name="backup_db_ftp_host" value="<?php echo $settings["backup-db"]["settings"]["ftp-host"]; ?>">
                                        <span style="margin-left:5px;" class="kinfo"><?php echo __("admin/settings/database-backup-ftp-host-desc"); ?></span>
                                        <br>

                                        <strong class="kinfo" style="display: inline-block;width: 20%;"><?php echo __("admin/settings/database-backup-ftp-port"); ?>:</strong>
                                        <input style="width: 40%" autocomplete="off" type="text" name="backup_db_ftp_port" value="<?php echo $settings["backup-db"]["settings"]["ftp-port"] ? $settings["backup-db"]["settings"]["ftp-port"] : "21"; ?>">
                                        <span style="margin-left:5px;" class="kinfo"><?php echo __("admin/settings/database-backup-ftp-port-desc"); ?></span>
                                        <input<?php echo isset($settings["backup-db"]["settings"]["ftp-ssl"]) && $settings["backup-db"]["settings"]["ftp-ssl"]  ? ' checked' : ''; ?> type="checkbox" name="backup_db_ftp_ssl" value="1" id="backup_db_ftp_ssl" class="checkbox-custom">
                                        <label class="checkbox-custom-label" for="backup_db_ftp_ssl">SSL</label>
                                        <br>

                                        <strong class="kinfo" style="display: inline-block;width: 20%;"><?php echo __("admin/settings/database-backup-ftp-username"); ?>:</strong>
                                        <input style="width: 40%" autocomplete="off" type="text" name="backup_db_ftp_username" value="<?php echo $settings["backup-db"]["settings"]["ftp-username"]; ?>">
                                        <span style="margin-left:5px;" class="kinfo"><?php echo __("admin/settings/database-backup-ftp-username-desc"); ?></span>
                                        <br>

                                        <strong class="kinfo" style="display: inline-block;width: 20%;"><?php echo __("admin/settings/database-backup-ftp-password"); ?>:</strong>
                                        <input style="width: 40%" autocomplete="off" type="password" name="backup_db_ftp_password" value="<?php echo $settings["backup-db"]["settings"]["ftp-password"] ? "*****" : NULL; ?>">
                                        <span style="margin-left:5px;" class="kinfo"><?php echo __("admin/settings/database-backup-ftp-password-desc"); ?></span>
                                        <br>

                                        <strong class="kinfo" style="display: inline-block;width: 20%;"><?php echo __("admin/settings/database-backup-ftp-target"); ?>:</strong>
                                        <input style="width: 40%" type="text" name="backup_db_ftp_target" value="<?php echo $settings["backup-db"]["settings"]["ftp-target"]; ?>">
                                        <span style="margin-left:5px;" class="kinfo"><?php echo __("admin/settings/database-backup-ftp-target-desc"); ?></span>
                                        <br><br>
                                        <a href="javascript:void(0);" id="test_ftp_connect" class="lbtn"><i class="fa fa-plug"></i> <?php echo __("admin/settings/database-backup-ftp-test-button"); ?></a>

                                    </div>
                                </div>


                                <div style="float:right;" class="guncellebtn yuzde30"><a id="backup_submit" href="javascript:void(0);" class="yesilbtn gonderbtn"><?php echo __("admin/settings/update-button1"); ?></a></div>

                            </form>
                            <script type="text/javascript">
                                $(document).ready(function(){
                                    $("#backupForm").bind("keypress", function(e) {
                                        if (e.keyCode == 13) $("#backup_submit").click();
                                    });

                                    $("#test_ftp_connect").on("click",function(){
                                        $("#backupForm input[name='operation']").val('test_ftp_connect');
                                        MioAjaxElement($(this),{
                                            waiting_text: waiting_text,
                                            progress_text: progress_text,
                                            result:"backupForm_handler",
                                        });
                                    });

                                    $("#backup_submit").on("click",function(){
                                        $("#backupForm input[name='operation']").val('update_backup_settings');
                                        MioAjaxElement($(this),{
                                            waiting_text: waiting_text,
                                            progress_text: progress_text,
                                            result:"backupForm_handler",
                                        });
                                    });


                                });

                                function backupForm_handler(result){
                                    if(result != ''){
                                        var solve = getJson(result);
                                        if(solve !== false){
                                            if(solve.status == "error"){
                                                if(solve.for != undefined && solve.for != ''){
                                                    $("#form1 "+solve.for).focus();
                                                    $("#form1 "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                                    $("#form1 "+solve.for).change(function(){
                                                        $(this).removeAttr("style");
                                                    });
                                                }
                                                if(solve.message != undefined && solve.message != '')
                                                    alert_error(solve.message,{timer:5000});
                                            }else if(solve.status == "successful"){
                                                if(solve.redirect != undefined) window.location.href = solve.redirect;
                                                alert_success(solve.message,{timer:2000});
                                            }
                                        }else
                                            console.log(result);
                                    }
                                }
                            </script>
                        </div>


                        <div class="clear"></div>


                    </div><!-- tab content end -->
                <?php endif; ?>

                <div id="group-prohibited" class="tabcontent"><!-- tab content start -->
                    <div class="adminpagecon">

                        <div class="blue-info">
                            <div class="padding20">
                                <i class="fa fa-info-circle" aria-hidden="true"></i>
                                <p><?php echo __("admin/settings/security-prohibited-info"); ?></p>
                            </div>
                        </div>


                        <form action="<?php echo $links["controller"]; ?>" method="post" id="prohibitedForm">
                            <input type="hidden" name="operation" value="update_prohibited_settings">

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/settings/security-user-block-temporary-email1"); ?></div>
                                <div class="yuzde70">
                                    <input<?php echo Config::get("options/block-user-temporary-email") ? ' checked' : ''; ?> name="block-user-temporary-email" type="checkbox" value="1" class="checkbox-custom" id="block-user-temporary-email">
                                    <label class="checkbox-custom-label" for="block-user-temporary-email"><span class="kinfo"><?php echo __("admin/settings/security-user-block-temporary-email2"); ?></span></label>
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30">
                                    <?php echo __("admin/settings/security-prohibited-field1"); ?>
                                    <div class="clear"></div>
                                    <span class="kinfo"><?php echo __("admin/settings/security-prohibited-field1-info"); ?></span>

                                </div>
                                <div class="yuzde70">
                                    <textarea name="domain-list"><?php echo Config::get("options/prohibited/domain-list") ? implode("\n",Config::get("options/prohibited/domain-list")) : ''; ?></textarea>
                                </div>
                            </div>
                            <div class="formcon">
                                <div class="yuzde30">
                                    <?php echo __("admin/settings/security-prohibited-field2"); ?>
                                    <div class="clear"></div>
                                    <span class="kinfo"><?php echo __("admin/settings/security-prohibited-field2-info"); ?></span>
                                </div>
                                <div class="yuzde70">
                                    <textarea name="email-list"><?php echo Config::get("options/prohibited/email-list") ? implode("\n",Config::get("options/prohibited/email-list")) : ''; ?></textarea>
                                </div>
                            </div>
                            <div class="formcon">
                                <div class="yuzde30">
                                    <?php echo __("admin/settings/security-prohibited-field3"); ?>
                                    <div class="clear"></div>
                                    <span class="kinfo"><?php echo __("admin/settings/security-prohibited-field3-info"); ?></span>
                                </div>
                                <div class="yuzde70">
                                    <textarea name="gsm-list"><?php echo Config::get("options/prohibited/gsm-list") ? implode("\n",Config::get("options/prohibited/gsm-list")) : ''; ?></textarea>
                                </div>
                            </div>
                            <div class="formcon">
                                <div class="yuzde30">
                                    <?php echo __("admin/settings/security-prohibited-field4"); ?>

                                    <div class="clear"></div>
                                    <span class="kinfo"><?php echo __("admin/settings/security-prohibited-field4-info"); ?></span>

                                </div>
                                <div class="yuzde70">
                                    <textarea name="word-list"><?php echo Config::get("options/prohibited/word-list") ? implode("\n",Config::get("options/prohibited/word-list")) : ''; ?></textarea>
                                </div>
                            </div>



                            <div style="float:right;" class="guncellebtn yuzde30"><a id="prohibitedForm_submit" href="javascript:void(0);" class="yesilbtn gonderbtn"><?php echo __("admin/settings/update-button1"); ?></a></div>
                            <div class="clear"></div>

                        </form>
                        <script type="text/javascript">
                            $(document).ready(function(){
                                $("#prohibitedForm_submit").on("click",function(){
                                    MioAjaxElement($(this),{
                                        waiting_text: waiting_text,
                                        progress_text: progress_text,
                                        result:"prohibitedForm_handler",
                                    });
                                });

                            });

                            function prohibitedForm_handler(result){
                                if(result != ''){
                                    var solve = getJson(result);
                                    if(solve !== false){
                                        if(solve.status == "error"){
                                            if(solve.for != undefined && solve.for != ''){
                                                $("#prohibitedForm "+solve.for).focus();
                                                $("#prohibitedForm "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                                $("#prohibitedForm "+solve.for).change(function(){
                                                    $(this).removeAttr("style");
                                                });
                                            }
                                            if(solve.message != undefined && solve.message != '')
                                                alert_error(solve.message,{timer:5000});
                                        }else if(solve.status == "successful"){
                                            if(solve.redirect != undefined) window.location.href = solve.redirect;
                                            alert_success(solve.message,{timer:2000});
                                        }
                                    }else
                                        console.log(result);
                                }
                            }

                        </script>


                    </div>
                </div><!-- tab content end -->
                <div id="group-spam" class="tabcontent"><!-- tab content start -->
                    <div class="adminpagecon">

                        <script type="text/javascript">
                            $(document).ready(function(){

                                $("#DeleteSpamRecord").on('click','#DeleteSpamRecord_button',function(){
                                    var request = MioAjax({
                                        button_element:$(this),
                                        waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                                        action: "<?php echo $links["controller"]; ?>",
                                        method: "POST",
                                        data: {operation:"delete_spam_records"}
                                    },true,true);

                                    request.done(function(result){
                                        if(result){
                                            if(result != ''){
                                                var solve = getJson(result);
                                                if(solve !== false){
                                                    if(solve.status == "error"){
                                                        if(solve.message != undefined && solve.message != '')
                                                            alert_error(solve.message,{timer:5000});
                                                    }else if(solve.status == "successful"){
                                                        alert_success(solve.message,{timer:3000});
                                                        close_modal("DeleteSpamRecord");
                                                        setTimeout(function(){
                                                            window.location.reload();
                                                        },3000);
                                                    }
                                                }else
                                                    console.log(result);
                                            }
                                        }else console.log(result);
                                    });

                                });

                            });
                        </script>


                        <div id="DeleteSpamRecord" style="display: none;" data-izimodal-title="<?php echo __("admin/tools/actions-button-clear"); ?>">
                            <div class="padding20">
                                <p style="text-align: center;"><?php echo __("admin/settings/security-spam-tx19"); ?></p>
                            </div>
                            <div class="modal-foot-btn">
                                <a id="DeleteSpamRecord_button" href="javascript:void(0);" class="red lbtn"><?php echo ___("needs/yes"); ?></a>
                            </div>
                        </div>


                        <div class="blue-info">
                            <div class="padding20">
                                <i class="fa fa-shield" aria-hidden="true"></i>
                                <p><?php echo __("admin/settings/security-spam-info"); ?></p>
                            </div>
                        </div>

                        <form action="<?php echo $links["controller"]; ?>" method="post" id="spamForm">
                            <input type="hidden" name="operation" value="update_spam_settings">

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/settings/security-spam-tx1"); ?></div>
                                <div class="yuzde70">
                                    <input<?php echo Config::get("options/spam-control/api-status") ? ' checked' : ''; ?> type="checkbox" name="spam_api_status" value="1" class="sitemio-checkbox" id="spam_api_status" onchange="if($(this).prop('checked')) $('#spam_control_wrap').css('display','block'); else $('#spam_control_wrap').css('display','none');">
                                    <label for="spam_api_status" class="sitemio-checkbox-label"></label>
                                    <span class="kinfo"><?php echo __("admin/settings/security-spam-tx2"); ?></span>
                                    <div class="clear"></div>
                                    <div id="spam_control_wrap" style="<?php echo Config::get("options/spam-control/api-status") ? '' : 'display:none;'; ?>">
                                        <div class="formcon">
                                            <div class="yuzde30">API Key</div>
                                            <div class="yuzde70">
                                                <input type="text" style="width:150px" name="spam_api_key" value="<?php echo Config::get("options/spam-control/api-key"); ?>" placeholder="">
                                            </div>
                                        </div>
                                        <div class="formcon">
                                            <div class="yuzde30">Risk Score</div>
                                            <div class="yuzde70">
                                                <input type="text" style="width:150px" name="spam_api_risk_score" value="<?php echo Config::get("options/spam-control/api-risk-score") ? Config::get("options/spam-control/api-risk-score") : 25; ?>" placeholder="" onkeypress='return event.charCode>= 48 &&event.charCode<= 57'>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/settings/security-spam-tx3"); ?></div>
                                <div class="yuzde70">
                                    <input<?php echo Config::get("options/spam-control/block-temporary") ? ' checked' : ''; ?> type="checkbox" name="spam_block_temporary_service" value="1" class="sitemio-checkbox" id="block_temporary_service">
                                    <label for="block_temporary_service" class="sitemio-checkbox-label"></label>
                                        <span class="kinfo"><?php echo __("admin/settings/security-spam-tx4"); ?></span>
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/settings/security-spam-tx7"); ?></div>
                                <div class="yuzde70">
                                    <input<?php echo Config::get("options/spam-control/contact-check-proxy") ? ' checked' : ''; ?> type="checkbox" name="spam_contact_check_proxy" value="1" class="sitemio-checkbox" id="contact_check_proxy">
                                    <label for="contact_check_proxy" class="sitemio-checkbox-label"></label>
                                    <span class="kinfo"><?php echo __("admin/settings/security-spam-tx8"); ?></span>
                                </div>
                            </div>


                            <div class="formcon">
                                <div class="yuzde30">
                                    <?php echo __("admin/settings/security-spam-tx5"); ?>
                                    <div class="clear"></div>
                                    <span class="kinfo"><?php echo __("admin/settings/security-spam-tx6"); ?></span>
                                </div>
                                <div class="yuzde70">
                                    <textarea rows="5" placeholder="" name="word-list"><?php echo Config::get("options/spam-control/word-list"); ?></textarea>
                                </div>
                            </div>


                            <div style="float:right;" class="guncellebtn yuzde30"><a id="spamForm_submit" href="javascript:void(0);" class="yesilbtn gonderbtn"><?php echo __("admin/settings/update-button1"); ?></a></div>
                            <div class="clear"></div>

                        </form>
                        <script type="text/javascript">
                            $(document).ready(function(){
                                $("#spamForm_submit").on("click",function(){
                                    MioAjaxElement($(this),{
                                        waiting_text: waiting_text,
                                        progress_text: progress_text,
                                        result:"spamForm_handler",
                                    });
                                });

                                $("#spamStatistics").accordion({
                                    heightStyle: "content",
                                    active: 0,
                                    collapsible: true,
                                });

                                $('#lastSpamRecords').DataTable({
                                    "columnDefs": [
                                        {
                                            "targets": [0],
                                            "visible":false,
                                            "searchable": false
                                        }
                                    ],
                                    "lengthMenu": [
                                        [10, 25, 50, -1], [10, 25, 50, "<?php echo ___("needs/allOf"); ?>"]
                                    ],
                                    responsive: true,
                                    "oLanguage":<?php include __DIR__.DS."datatable-lang.php"; ?>
                                });

                            });

                            function spamForm_handler(result){
                                if(result != ''){
                                    var solve = getJson(result);
                                    if(solve !== false){
                                        if(solve.status == "error"){
                                            if(solve.for != undefined && solve.for != ''){
                                                $("#spamForm "+solve.for).focus();
                                                $("#spamForm "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                                $("#spamForm "+solve.for).change(function(){
                                                    $(this).removeAttr("style");
                                                });
                                            }
                                            if(solve.message != undefined && solve.message != '')
                                                alert_error(solve.message,{timer:5000});
                                        }else if(solve.status == "successful"){
                                            if(solve.redirect != undefined) window.location.href = solve.redirect;
                                            alert_success(solve.message,{timer:2000});
                                        }
                                    }else
                                        console.log(result);
                                }
                            }
                        </script>

                        <div class="clear"></div>

                        <div id="spamStatistics" style="margin-top: 50px;">

                            <h2><?php echo __("admin/settings/security-spam-tx9"); ?></h2>
                            <div>

                                <h4 style="float:left;">
                                    <?php echo __("admin/settings/security-spam-tx17"); ?>

                                    <a href="javascript:void 0;open_modal('DeleteSpamRecord');" class="lbtn red"><i class="fa fa-trash"></i> <?php echo __("admin/tools/actions-button-clear"); ?></a>

                                    <?php echo __("admin/settings/security-spam-tx18"); ?>
                                </h4>
                                <h4 style="float:right;"><?php echo __("admin/settings/security-spam-tx10"); ?>: <b><?php echo $total_spam_hit; ?></b></h4>

                                <table width="100%" id="lastSpamRecords" class="table table-striped table-borderedx table-condensed nowrap">
                                    <thead style="background:#ebebeb;">
                                    <tr>
                                        <th align="left">#</th>
                                        <th align="left" data-orderable="false"><?php echo __("admin/settings/security-spam-tx11"); ?></th>
                                        <th align="left" data-orderable="false"><?php echo __("admin/settings/security-spam-tx16"); ?></th>
                                        <th align="center" data-orderable="false"><?php echo __("admin/settings/security-spam-tx14"); ?></th>
                                        <th align="center" data-orderable="false">IP</th>
                                        <th align="center" data-orderable="false"><?php echo __("admin/settings/security-spam-tx15"); ?></th>
                                        <th align="center" data-orderable="false"></th>
                                    </tr>
                                    </thead>
                                    <tbody align="center" style="border-top:none;">
                                    <?php
                                        if($last_spam_records)
                                        {
                                            foreach($last_spam_records AS $k => $r)
                                            {
                                                $i =  $k++;
                                                ?>
                                                <tr>
                                                    <td align="left"><?php echo $i; ?></td>
                                                    <td align="left"><a data-balloon-pos="up" data-balloon="<?php echo str_replace(["'",'"'],["&apos;","&quot;"],$r["subject"]); ?>"><?php echo Utility::short_text($r["subject"],0,30,true); ?></a></td>
                                                    <td align="center">

                                                        <?php echo __("admin/settings/security-spam-tx12"); ?>
                                                        :
                                                        <a data-balloon-pos="up" data-balloon="<?php echo str_replace(["'",'"'],["&apos;","&quot;"],$r["from_name"] ? " &#8810; ".$r["from_name"]." &#8811;" : 'Noname'); ?>"><?php echo $r["from_address"]; ?></a>
                                                        <br>
                                                        <?php echo __("admin/settings/security-spam-tx13"); ?>:
                                                        <a data-balloon-pos="up" data-balloon="<?php echo str_replace(["'",'"'],["&apos;","&quot;"],$r["to_name"] ? " &#8810; ".$r["to_name"]." &#8811;" : ''); ?>"><?php echo $r["to_address"]; ?></a>

                                                    </td>
                                                    <td align="center"><?php echo DateManager::format(Config::get("options/date-format")." H:i",$r["created_at"]); ?></td>
                                                    <td align="center"><?php $a = ($r["ip"] ?? ''); echo $a ? $a : 'N/A' ?></td>
                                                    <td align="center"><a data-balloon-pos="up" data-balloon="<?php echo str_replace(["'",'"'],["&apos;","&quot;"],$r["reason"]); ?>"><?php echo Utility::short_text($r["reason"],0,20,true); ?></a></td>
                                                    <td align="center">
                                                        <div id="message_<?php echo $r["id"]; ?>" style="display: none;">
                                                            <div class="padding20" style="text-align: left;">
                                                                <p><?php echo Filter::link_convert(nl2br($r["message"]),true); ?></p>
                                                            </div>
                                                        </div>
                                                        <a class="lbtn" href="javascript:void 0;" onclick="open_modal('message_<?php echo $r["id"]; ?>',{title:'<?php echo str_replace(["'",'"'],["&apos;","&quot;"],$r["subject"]); ?>'})"> <?php echo __("admin/users/detail-messages-show-message"); ?></a></td>
                                                </tr>
                                                <?php
                                            }
                                        }
                                    ?>
                                    </tbody>
                                </table>


                            </div>
                        </div>


                    </div>
                </div><!-- tab content end -->


            </div>


        </div>
    </div>


</div>

<?php include __DIR__.DS."inc".DS."footer.php"; ?>

</body>
</html>