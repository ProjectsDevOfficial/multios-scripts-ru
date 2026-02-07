<!DOCTYPE html>
<html>
<head>
    <?php
        $selected_servers = $group["servers"] ?  explode(",",$group["servers"]) : [];
        $plugins    = ['jquery-ui','dataTables','select2'];
        include __DIR__.DS."inc".DS."head.php";
    ?>

    <script type="text/javascript">
        var table;
        $(document).ready(function(){

            $("#editForm").bind("keypress", function(e) {
                if (e.keyCode == 13) $("#editForm_submit").click();
            });

            $("#editForm_submit").on("click",function(){
                $("#selectedServers option").prop('selected',true);
                MioAjaxElement($(this),{
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                    result:"editForm_handler",
                });
            });

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

        function editForm_handler(result){
            if(result != ''){
                var solve = getJson(result);
                if(solve !== false){
                    if(solve.status == "error"){
                        if(solve.for != undefined && solve.for != ''){
                            $("#editForm "+solve.for).focus();
                            $("#editForm "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                            $("#editForm "+solve.for).change(function(){
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
                <h1><strong><?php echo __("admin/products/page-edit-hosting-shared-server-group"); ?></strong></h1>
                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>

            <div class="clear"></div>

            <div class="adminpagecon">

                <form action="<?php echo $links["controller"]; ?>" method="post" id="editForm">
                    <input type="hidden" name="operation" value="edit_hosting_shared_server_group">

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/products/shared-server-tx7"); ?></div>
                        <div class="yuzde70">
                            <input type="text" name="name" value="<?php echo $group["name"]; ?>" placeholder="">

                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/products/shared-server-tx5"); ?></div>
                        <div class="yuzde70">
                            <input<?php echo $group["fill_type"] == 1 ? ' checked' : ''; ?> type="radio" value="1" name="fill_type" id="fill_type_1" class="radio-custom">
                            <label class="radio-custom-label" for="fill_type_1"><?php echo __("admin/products/shared-server-tx5-1"); ?></label>
                            <div class="clear" style="margin-bottom: 5px;"></div>

                            <input<?php echo $group["fill_type"] == 2 ? ' checked' : ''; ?> type="radio" value="2" name="fill_type" id="fill_type_2" class="radio-custom">
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
                                                if(in_array($s["id"],$selected_servers)) continue;
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
                                <select id="selectedServers" name="servers[]" multiple="multiple" style="height: 300px;">
                                    <?php
                                        if(isset($servers) && $servers)
                                        {
                                            foreach($servers AS $s)
                                            {
                                                if(!in_array($s["id"],$selected_servers)) continue;
                                                ?>
                                                <option selected value="<?php echo $s["id"]; ?>"><?php echo $s["name"]." - ".$s["ip"]." (".$s["type"].")"; ?></option>
                                                <?php
                                            }
                                        }
                                    ?>
                                </select>
                            </div>


                        </div>
                    </div>


                    <div style="float:right;margin-top:10px;" class="guncellebtn yuzde30">
                        <a class="yesilbtn gonderbtn" id="editForm_submit" href="javascript:void(0);"><?php echo __("admin/products/edit-shared-server-button"); ?></a>
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