<!DOCTYPE html>
<html>
<head>
    <?php
        $plugins    = ['dataTables'];
        include __DIR__.DS."inc".DS."head.php";
    ?>
    <script type="text/javascript">
        var table;
        $(document).ready(function(){

            table = $('#listTable').DataTable({
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
                "bProcessing": true,
                "bServerSide": true,
                "sAjaxSource": "<?php echo $links["ajax"]; ?>",
                responsive: true,
                "oLanguage":<?php include __DIR__.DS."datatable-lang.php"; ?>
            });

        });
    </script>
  

    <script type="text/javascript">
        $(document).ready(function(){
            $("#listTable").on("click",".view-numbers",function(){
                var id = $(this).data("id");
                open_modal("view_numbers_modal",{width:'300px'});

                $("#get_numbers").html($("#numbers_"+id).html());

            });

            $("#listTable").on("click",".view-message",function(){
                var id = $(this).data("id");
                open_modal("view_message_modal",{width:'400px'});

                $("#get_message").html($("#message_"+id).html());

            });

            $("#clear_actions").on("click","#ok",function(){
                var date = $("#date").val();
                var psw  = $("#password").val();

                $("#password").val('');

                var request = MioAjax({
                    button_element:this,
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    action: "<?php echo $links["controller"]; ?>",
                    method: "POST",
                    data: {operation:"clear_notifications",type:"sms",date:date,password:psw}
                },true,true);

                request.done(function(result){
                    if(result){
                        if(result != ''){
                            var solve = getJson(result);
                            if(solve !== false){
                                if(solve.status == "error"){
                                    if(solve.for != undefined && solve.for != ''){
                                        $("#clear_actions "+solve.for).focus();
                                        $("#clear_actions "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                        $("#clear_actions "+solve.for).change(function(){
                                            $(this).removeAttr("style");
                                        });
                                    }
                                    if(solve.message != undefined && solve.message != '')
                                        alert_error(solve.message,{timer:5000});
                                }else if(solve.status == "successful"){
                                    alert_success(solve.message,{timer:3000});
                                    table.ajax.reload();
                                }
                            }else
                                console.log(result);
                        }
                    }else console.log(result);
                });

            });
        });
    </script>
</head>
<body>

<div style="display: none;" data-izimodal-title="<?php echo __("admin/tools/actions-button-clear"); ?>" id="clear_actions">
    <div class="padding20">

        <div align="center" style="text-align: center;">
            <p><?php echo __("admin/tools/notification-log-clear-note"); ?></p>
            <p>
                <input type="date" name="date" id="date" class="width200" value="<?php echo DateManager::old_date(['month' => 1],"Y-m-d"); ?>">
            </p>

            <div id="password_wrapper">
                <label><?php echo ___("needs/permission-delete-item-password-desc"); ?><br><br><strong><?php echo ___("needs/permission-delete-item-password"); ?></strong> <br><input type="password" id="password" value="" placeholder="********"></label>
                <div class="clear"></div>
                <br>
            </div>
            <div class="clear"></div>
        </div>

    </div>
    <div class="modal-foot-btn">
        <a id="ok" href="javascript:void(0);" class="red lbtn"><?php echo ___("needs/ok"); ?></a>
    </div>
</div>

<div id="view_numbers_modal" data-izimodal-title="<?php echo ___("needs/allOf"); ?>" style="display: none;">
    <div class="padding20">

        <div id="get_numbers" style="text-align: center;"></div>

        <div class="clear"></div>
    </div>
</div>
<div id="view_message_modal" data-izimodal-title="<?php echo __("admin/tools/sms-logs-th-content"); ?>" style="display: none;">
    <div class="padding20">

        <div id="get_message" style="text-align: center;word-wrap: break-word;"></div>

        <div class="clear"></div>
    </div>
</div>

<?php include __DIR__.DS."inc/header.php"; ?>

<div id="wrapper">

    <div class="icerik-container">
        <div class="icerik">

            <div class="icerikbaslik">
                <h1>
                    <strong><?php echo __("admin/tools/page-sms-logs"); ?></strong>
                </h1>
                <a class="lbtn" href="javascript:void 0;" onclick="open_modal('clear_actions');"><?php echo __("admin/tools/actions-button-clear"); ?></a>
                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>

            <table width="100%" id="listTable" class="table table-striped table-borderedx table-condensed nowrap">
                <thead style="background:#ebebeb;">
                <tr>
                    <th align="left">#</th>
                    <th align="left" data-orderable="false"><?php echo __("admin/tools/sms-logs-th-title"); ?></th>
                    <th align="left" data-orderable="false"><?php echo __("admin/tools/sms-logs-th-content"); ?></th>
                    <th align="center" data-orderable="false"><?php echo __("admin/tools/sms-logs-th-numbers"); ?></th>
                    <th align="center" data-orderable="false"><?php echo __("admin/tools/sms-logs-th-date"); ?></th>
                    <th align="center" data-orderable="false"><?php echo __("admin/tools/sms-logs-th-ip"); ?></th>
                </tr>
                </thead>
                <tbody align="center" style="border-top:none;"></tbody>
            </table>


        </div>
    </div>


</div>

<?php include __DIR__.DS."inc".DS."footer.php"; ?>

</body>
</html>