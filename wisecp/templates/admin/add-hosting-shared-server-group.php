<!DOCTYPE html>
<html>
<head>
    <?php
        $plugins    = ['jquery-ui','select2'];
        include __DIR__.DS."inc".DS."head.php";
    ?>

    <script type="text/javascript">
        $(document).ready(function(){
            $("#addNewForm").bind("keypress", function(e) {
                if (e.keyCode == 13) $("#addNewForm_submit").click();
            });

            $("#addNewForm_submit").on("click",function(){
                $("#selectedServers option").prop('selected',true);
                MioAjaxElement($(this),{
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                    result:"addNewForm_handler",
                });
            });

            $('.select2').select2({width:'100%'});


            $('#addServer').click(function () {
                if ($('#listServers option:selected').val() != null) {
                    $('#listServers option:selected').appendTo('#selectedServers');
                }
            });

            $('#removePop').click(function () {
                if ($('#selectedServers option:selected').val() != null) {
                    $('#selectedServers option:selected').appendTo('#listServers');
                }
            });


        });


        function addNewForm_handler(result){
            if(result != ''){
                var solve = getJson(result);
                if(solve !== false){
                    if(solve.status == "error"){
                        if(solve.for != undefined && solve.for != ''){
                            $("#addNewForm "+solve.for).focus();
                            $("#addNewForm "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                            $("#addNewForm "+solve.for).change(function(){
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
                <h1><strong><?php echo __("admin/products/page-add-hosting-shared-server-group"); ?></strong></h1>
                <?php
                $ui_help_link = 'https://docs.wisecp.com/en/kb/shared-server-settings';
                if($ui_lang == "tr") $ui_help_link = 'https://docs.wisecp.com/tr/kb/paylasimli-sunucu-ayarlari';
                ?>
                <a title="<?php echo __("admin/help/usage-guide"); ?>" target="_blank" class="pagedocslink" href="<?php echo $ui_help_link; ?>"><i class="fa fa-life-ring" aria-hidden="true"></i></a>
                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>

            <div class="clear"></div>

            <div class="adminuyedetay">

                <form action="<?php echo $links["controller"]; ?>" method="post" id="addNewForm">
                    <input type="hidden" name="operation" value="add_new_hosting_shared_server_group">


                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/products/shared-server-tx7"); ?></div>
                        <div class="yuzde70">
                            <input type="text" name="name" value="" placeholder="">
                            
                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/products/shared-server-tx5"); ?></div>
                        <div class="yuzde70">
                            <input checked type="radio" value="1" name="fill_type" id="fill_type_1" class="radio-custom">
                            <label class="radio-custom-label" for="fill_type_1"><?php echo __("admin/products/shared-server-tx5-1"); ?></label>
                            <div class="clear" style="margin-bottom: 5px;"></div>

                            <input type="radio" value="2" name="fill_type" id="fill_type_2" class="radio-custom">
                            <label class="radio-custom-label" for="fill_type_2"><?php echo __("admin/products/shared-server-tx5-2"); ?></label>
                        </div>
                    </div>

                    <div class="formcon server-group-assignment">
                        <div class="yuzde30"><?php echo __("admin/products/shared-server-tx8"); ?></div>
                        <div class="yuzde70">
                            <div class="yuzde45">
                                <h4><strong><?php echo __("admin/products/shared-server-tx13"); ?></strong></h4>
                                <select id="listServers" multiple="multiple" style="height: 300px;">
                                    <?php
                                        if(isset($servers) && $servers)
                                        {
                                            foreach($servers AS $s)
                                            {
                                                ?>
                                                <option value="<?php echo $s["id"]; ?>"><?php echo $s["name"]." - ".$s["ip"]." (".$s["type"].")"; ?></option>
                                                <?php
                                            }
                                        }
                                    ?>
                                </select>
                            </div>

                            <div class="yuzde10 servergroup-addremove-btn">
                                <a href="javascript:void(0);" style="" class="lbtn" id="addServer"><?php echo __("admin/products/shared-server-tx10"); ?> <i class="fa fa-long-arrow-right"></i></a>
                                <div class="clear"></div>
                                <a href="javascript:void(0);" style="" class="lbtn" id="removePop"><i class="fa fa-long-arrow-left"></i> <?php echo __("admin/products/shared-server-tx11"); ?></a>
                            </div>
                            <div class="yuzde45">
                                <h4><strong><?php echo __("admin/products/shared-server-tx14"); ?></strong></h4>
                                <select id="selectedServers" name="servers[]" multiple="multiple" style="height: 300px;"></select>
                            </div>


                        </div>
                    </div>

                    <div style="float:right;margin-top:10px;" class="guncellebtn yuzde30">
                        <a class="yesilbtn gonderbtn" id="addNewForm_submit" href="javascript:void(0);"><?php echo ___("needs/button-create"); ?></a>
                    </div>
                    <div class="clear"></div>


                </form>

            </div>


            <div class="clear"></div>
        </div>
    </div>


</div>

<?php include __DIR__.DS."inc".DS."footer.php"; ?>

</body>
</html>