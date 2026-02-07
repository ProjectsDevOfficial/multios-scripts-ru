<!DOCTYPE html>
<html>
<head>
    <?php
        $plugins            = ['dataTables','select2'];
        Utility::sksort($lang_list,'local');
        include __DIR__.DS."inc".DS."head.php";
    ?>
    <script type="text/javascript">
        $(document).ready(function(){
            
            $("#warning_modal").on("click","#saveForm_submit",function(){
                MioAjaxElement($(this),{
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                    result:"saveForm_handler",
                });
            });
            
        });

        function saveForm_handler(result){
            if(result != ''){
                var solve = getJson(result);
                if(solve !== false){
                    if(solve.status == "error"){
                        if(solve.for != undefined && solve.for != ''){
                            $("#saveForm "+solve.for).focus();
                            $("#saveForm "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                            $("#saveForm "+solve.for).change(function(){
                                $(this).removeAttr("style");
                            });
                        }
                        if(solve.message != undefined && solve.message != '')
                            alert_error(solve.message,{timer:5000});
                    }else if(solve.status == "successful"){
                        alert_success(solve.message,{timer:2000});
                        if(solve.redirect != undefined && solve.redirect != ''){
                            setTimeout(function(){
                                window.location.href = solve.redirect;
                            },2000);
                        }
                    }
                }else
                    console.log(result);
            }
        }
    </script>

</head>
<body>
<?php include __DIR__.DS."inc/header.php"; ?>

<div id="wrapper">

    <div class="icerik-container">
        <div class="icerik">

            <div class="icerikbaslik">
                <h1>
                    <strong><?php echo __("admin/users/page-gdpr-detail");?></strong>
                </h1>

                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>


            <div class="clear"></div>

            <div class="adminpagecon">


                <form action="<?php echo $links["controller"]; ?>" method="post" id="saveForm">
                    <input type="hidden" name="operation" value="update_gdpr_request">

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/users/gdpr-tx8"); ?></div>
                        <div class="yuzde70"><a href="<?php echo $links["user_link"]; ?>" target="_blank"><?php echo $user["full_name"]; ?></a></div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/users/gdpr-tx9"); ?></div>
                        <div class="yuzde70"><?php echo DateManager::format(Config::get("options/date-format")." H:i",$rq["created_at"]); ?></div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/users/gdpr-tx10"); ?></div>
                        <div class="yuzde70">
                            <?php echo $rq["type"] == "remove" ? __("admin/users/gdpr-tx11") : __("admin/users/gdpr-tx12"); ?>
                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/users/gdpr-tx13"); ?></div>
                        <div class="yuzde70">

                            <a href="<?php echo $links["user_link"]; ?>?tab=services" target="_blank">
                                <?php echo __("admin/users/gdpr-tx14"); ?>: <strong><?php echo $active_orders; ?></strong> / <?php echo __("admin/users/gdpr-tx15"); ?> : <strong><?php echo $inactive_orders; ?></strong>

                            </a>

                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/users/gdpr-tx16"); ?></div>
                        <div class="yuzde70">

                            <a href="<?php echo $links["user_link"]; ?>?tab=invoices" target="_blank">
                                <?php echo $invoice_count; ?>
                            </a>

                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/users/gdpr-tx17"); ?></div>
                        <div class="yuzde70">
                            <select name="status" style="width: 250px;">
                                <option value=""><?php echo ___("needs/select-your"); ?></option>
                                <option value="anonymize"><?php echo __("admin/users/gdpr-tx19"); ?></option>
                                <option value="destroy"><?php echo __("admin/users/gdpr-tx20"); ?></option>
                                <option value="cancelled"><?php echo __("admin/users/gdpr-tx21"); ?></option>
                            </select>


                            <div class="formcon" id="status_note_wrap" style="<?php echo $rq["status"] == "cancelled" ? '' : 'display: none;'; ?>">
                                <div class="yuzde30"><?php echo __("admin/users/gdpr-tx30"); ?></div>
                                <div class="yuzde70">
                                    <textarea name="status_note"><?php echo $rq["status_note"]; ?></textarea>
                                    <span class="kinfo"><?php echo __("admin/users/gdpr-tx31"); ?></span>
                                </div>
                            </div>

                            <script type="text/javascript">
                                $(document).ready(function(){
                                    $('select[name=status]').change(function(){
                                        var chosen = $(this).val();

                                        $('.remove-type-con').css('display','none');
                                        $('#status_note_wrap').css('display','none');
                                        $('.remove-type-con input[type=radio]').prop('checked',false);

                                        if(chosen === "destroy")
                                        {
                                            $("#all_remove_wrap").css("display","block");
                                        }
                                        else if(chosen === "anonymize")
                                        {
                                            $("#block_access_wrap").css("display","block");
                                            $("#blacklist_wrap").css("display","block");
                                            $("#identifying_data_remove_wrap").css("display","block");
                                        }
                                        else if(chosen === "cancelled")
                                        {
                                            $('#status_note_wrap').css('display','block');
                                        }

                                        if('<?php echo $rq["remove_type"]; ?>' !== '')
                                        {
                                            $('input[value=<?php echo $rq["remove_type"]; ?>]').prop('checked',true);
                                        }
                                    });

                                    if('<?php echo $rq["status_admin"]; ?>' !== '')
                                    {
                                        $("select[name=status] option[value=<?php echo $rq["status_admin"]; ?>]").prop('selected',true).trigger('change');
                                    }
                                });
                            </script>
                            <div id="block_access_wrap" class="remove-type-con" style="display: none;">
                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/users/gdpr-tx22"); ?></div>
                                    <div class="yuzde70">
                                        <input type="radio" name="remove_type" value="block_access" class="radio-custom" id="block_access">
                                        <label class="radio-custom-label" for="block_access"><span class="kinfo"><?php echo __("admin/users/gdpr-tx23"); ?></span></label>
                                    </div>
                                </div>
                            </div>
                            <div id="all_remove_wrap" class="remove-type-con" style="display: none;">
                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/users/gdpr-tx26"); ?></div>
                                    <div class="yuzde70">
                                        <input type="radio" name="remove_type" value="all" class="radio-custom" id="all_remove">
                                        <label class="radio-custom-label" for="all_remove"><span class="kinfo"><?php echo __("admin/users/gdpr-tx27"); ?></span></label>
                                    </div>
                                </div>
                            </div>
                            <div id="identifying_data_remove_wrap" class="remove-type-con" style="display: none;">
                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/users/gdpr-tx28"); ?></div>
                                    <div class="yuzde70">
                                        <input type="radio" name="remove_type" value="identifying_data" class="radio-custom" id="identifying_data_remove">
                                        <label class="radio-custom-label" for="identifying_data_remove"><span class="kinfo"><?php echo __("admin/users/gdpr-tx29"); ?></span></label>
                                    </div>
                                </div>
                            </div>

                            <?php if(Config::get("options/blacklist/status")): ?>
                                <div id="blacklist_wrap" class="remove-type-con" style="display: none;">
                                    <div class="formcon">
                                        <div class="yuzde30"><?php echo __("admin/users/gdpr-tx24"); ?></div>
                                        <div class="yuzde70">
                                            <input<?php echo $rq["blacklist"] ? ' checked' : ''; ?> type="checkbox" name="blacklist" value="1" class="checkbox-custom" id="blacklist">
                                            <label class="checkbox-custom-label" for="blacklist"><span class="kinfo"><?php echo __("admin/users/gdpr-tx25"); ?></span></label>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>


                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/users/gdpr-tx32"); ?></div>
                        <div class="yuzde70">
                            <input type="checkbox" name="notification" value="1" id="notification" class="checkbox-custom">
                            <label class="checkbox-custom-label" for="notification"><span class="kinfo"><?php echo __("admin/users/gdpr-tx33"); ?></span></label>
                        </div>
                    </div>


                    <div class="guncellebtn yuzde30" style="float: right;">
                        <a class="gonderbtn yesilbtn" href="javascript:void 0;" onclick="open_modal('warning_modal',{title: $('select[name=status] option:selected').text()});"><?php echo ___("needs/button-apply"); ?></a>
                    </div>

                    <div id="warning_modal" style="display: none;">
                        <div class="padding20">
                            <div align="center">
                                <p><?php echo ___("needs/apply-are-you-sure"); ?></p>
                            </div>
                        </div>

                        <div class="modal-foot-btn">
                            <a id="saveForm_submit" href="javascript:void(0);" class="red lbtn"><?php echo ___("needs/button-apply"); ?></a>
                        </div>
                    </div>


                </form>

            </div>

            <div class="clear"></div>

        </div>
    </div>


</div>

<?php include __DIR__.DS."inc".DS."footer.php"; ?>



</body>
</html>