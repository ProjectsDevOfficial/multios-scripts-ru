<!DOCTYPE html>
<html>
<head>
    <?php
        $plugins            = ['dataTables','select2'];
        Utility::sksort($lang_list,'local');
        include __DIR__.DS."inc".DS."head.php";
    ?>
    <script type="text/javascript">
        var table1,table2,table3,table4,request_id;

        $(document).ready(function(){
            table1 = $('#requests').DataTable({
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
                "sAjaxSource": "<?php echo $links["ajax-requests"]; ?>",
                responsive: true,
                "oLanguage":<?php include __DIR__.DS."datatable-lang.php"; ?>
            });
            table2 = $('#downloaders').DataTable({
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
                "sAjaxSource": "<?php echo $links["ajax-downloaders"]; ?>",
                responsive: true,
                "oLanguage":<?php include __DIR__.DS."datatable-lang.php"; ?>
            });
            table3 = $('#approvers').DataTable({
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
                "sAjaxSource": "<?php echo $links["ajax-approvers"]; ?>",
                responsive: true,
                "oLanguage":<?php include __DIR__.DS."datatable-lang.php"; ?>
            });
            /*
            table4 = $('#disapprovers').DataTable({
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
                "sAjaxSource": "<?php echo $links["ajax-disapprovers"]; ?>",
                responsive: true,
                "oLanguage":<?php include __DIR__.DS."datatable-lang.php"; ?>
            });
            */

            var tab1 = _GET("group");
            if (tab1 != '' && tab1 != undefined) {
                $("#tab-group .tablinks[data-tab='" + tab1 + "']").click();
            }
            else {
                $("#tab-group .tablinks:eq(0)").addClass("active");
                $("#tab-group .tabcontent:eq(0)").css("display", "block");
            }

            $("#DeleteRequestModal").on("click","#delete_ok",function(){
                var request = MioAjax({
                    button_element:$(this),
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    action: "<?php echo $links["controller"]; ?>",
                    method: "POST",
                    data: {operation: "delete_gdpr_request",id: request_id}
                },true,true);
                request.done(function(result){
                    var solve = getJson(result);
                    if(solve)
                    {
                        if(solve.status === "error") alert_error(solve.message,{timer:3000});
                        else if(solve.status === "successful")
                        {
                            alert_success(solve.message,{timer:3000});
                            table1.ajax.reload();
                        }

                    }
                });
            });

            $("#settingsModal").on("click","#settingsForm_submit",function(){
                MioAjaxElement($(this),{
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                    result:"settingsForm_handler",
                });
            });

        });

        function settingsForm_handler(result){
            if(result != ''){
                var solve = getJson(result);
                if(solve !== false){
                    if(solve.status == "error"){
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

        function DeleteRequest(req_id)
        {
            request_id = req_id;
            open_modal("DeleteRequestModal");
        }
    </script>
    <!--style type="text/css">
        #requests tbody tr td:nth-child(1) { text-align: left;}
    </style-->

</head>
<body>
<?php include __DIR__.DS."inc/header.php"; ?>

<div id="settingsModal" style="display: none;" data-izimodal-title="<?php echo __("admin/users/blacklist-settings-btn"); ?>">
    <div class="padding20">

        <form action="<?php echo $links["controller"]; ?>" method="post" id="settingsForm" enctype="multipart/form-data">
            <input type="hidden" name="operation" value="save_gdpr_settings">

            <div class="formcon">
                <div class="yuzde30"><?php echo __("admin/users/gdpr-tx3"); ?></div>
                <div class="yuzde70">
                    <input<?php echo Config::get("options/gdpr-status") ? ' checked' : ''; ?> type="checkbox" name="status" value="1" id="gdpr-status" class="checkbox-custom">
                    <label class="checkbox-custom-label" for="gdpr-status"><?php echo __("admin/users/gdpr-tx4"); ?></label>
                </div>
            </div>

            <div class="formcon">
                <div class="yuzde30"><?php echo __("admin/users/gdpr-tx5"); ?></div>
                <div class="yuzde70">
                    <input<?php echo Config::get("options/gdpr-required") ? ' checked' : ''; ?> type="checkbox" name="required" value="1" id="gdpr-required" class="checkbox-custom">
                    <label class="checkbox-custom-label" for="gdpr-required"><?php echo __("admin/users/gdpr-tx6"); ?></label>
                </div>
            </div>

            <div class="clear"></div>

            <div style="float: right;" class="guncellebtn yuzde30">
                <a id="settingsForm_submit" href="javascript:void 0;" class="gonderbtn yesilbtn"><?php echo ___("needs/button-update"); ?></a>
            </div>

            <div class="clear"></div>
        </form>
    </div>
</div>

<div id="DeleteRequestModal" data-izimodal-title="<?php echo __("admin/users/button-delete"); ?>" style="display:none;">
    <div class="padding20">
        <div align="center"><p><?php echo ___("needs/delete-are-you-sure"); ?></p></div>
    </div>
    <div class="modal-foot-btn">
        <a id="delete_ok" href="javascript:void(0);" class="red lbtn"><?php echo __("admin/products/delete-ok"); ?></a>
    </div>
</div>

<div id="wrapper">

    <div class="icerik-container">
        <div class="icerik">

            <div class="icerikbaslik">
                <h1>
                    <strong><?php echo __("admin/users/page-gdpr");?></strong>
                </h1>

                <div align="left">
                    <a class="lbtn" href="javascript:void 0;" onclick="open_modal('settingsModal');"><i class="fa fa-cogs"></i> <?php echo __("admin/users/blacklist-settings-btn"); ?></a>

                    <a class="lbtn blue" target="_blank" href="<?php echo $links["contracts"]; ?>"><i class="fa fa-book"></i> <?php echo __("admin/manage-website/button-contracts"); ?></a>

                </div>

                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>


            <div class="clear"></div>



            <div id="tab-group">

                <ul class="tab">
                    <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'requests','group')" data-tab="requests"><?php echo __("admin/users/gdpr-tab-requests"); ?> (<?php echo $requests_total ?? 0; ?>)</a></li>
                    <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'downloaders','group')" data-tab="downloaders"><?php echo __("admin/users/gdpr-tab-downloaders"); ?> (<?php echo $downloaders_total ?? 0; ?>)</a></li>
                    <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'approvers','group')" data-tab="approvers"><?php echo __("admin/users/gdpr-tab-approvers"); ?> (<?php echo $approvers_total ?? 0; ?>)</a></li>
                    <!--
                    <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'disapprovers','group')" data-tab="disapprovers"><?php echo __("admin/users/gdpr-tab-disapprovers"); ?> (<?php echo $disapprovers_total ?? 0; ?>)</a></li>
                    -->

                </ul>

                <div id="group-requests" class="tabcontent">

                    <div class="green-info">
                        <div class="padding20">
                            <i class="fa fa-info-circle"></i>
                            <p><?php echo __("admin/users/gdpr-tab-requests-desc"); ?></p>
                        </div>
                    </div>
                    <div class="clear"></div>


                    <table width="100%" id="requests" class="table table-striped table-borderedx table-condensed nowrap">
                        <thead style="background:#ebebeb;">
                        <tr>
                            <th align="left" data-orderable="false">#</th>
                            <th align="left" data-orderable="false"><?php echo __("admin/invoices/create-user"); ?></th>
                            <th align="left" data-orderable="false"><?php echo __("admin/financial/coupons-list-type"); ?></th>
                            <th align="left" data-orderable="false"><?php echo __("admin/users/list-th-withdrawal-ctime"); ?></th>
                            <th align="left" data-orderable="false"><?php echo __("admin/orders/list-status"); ?></th>

                            <th align="left" data-orderable="false"></th>
                        </tr>
                        </thead>
                        <tbody align="center" style="border-top:none;"></tbody>
                    </table>

                </div>
                <div id="group-downloaders" class="tabcontent">

                    <div class="green-info">
                        <div class="padding20">
                            <i class="fa fa-info-circle"></i>
                            <p><?php echo __("admin/users/gdpr-tab-downloaders-desc"); ?></p>
                        </div>
                    </div>
                    <div class="clear"></div>

                    <table width="100%" id="downloaders" class="table table-striped table-borderedx table-condensed nowrap">
                        <thead style="background:#ebebeb;">
                        <tr>
                            <th align="left" data-orderable="false">#</th>
                            <th align="left" data-orderable="false"><?php echo __("admin/invoices/create-user"); ?></th>
                            <th align="left" data-orderable="false"><?php echo __("admin/users/gdpr-tx1"); ?></th>
                            <th align="left" data-orderable="false"><?php echo __("admin/users/detail-actions-th-ip"); ?></th>
                        </tr>
                        </thead>
                        <tbody align="center" style="border-top:none;"></tbody>
                    </table>
                </div>
                <div id="group-approvers" class="tabcontent">

                    <div class="green-info">
                        <div class="padding20">
                            <i class="fa fa-info-circle"></i>
                            <p><?php echo __("admin/users/gdpr-tab-approvers-desc"); ?></p>
                        </div>
                    </div>
                    <div class="clear"></div>

                    <table width="100%" id="approvers" class="table table-striped table-borderedx table-condensed nowrap">
                        <thead style="background:#ebebeb;">
                        <tr>
                            <th align="left" data-orderable="false">#</th>
                            <th align="left" data-orderable="false"><?php echo __("admin/invoices/create-user"); ?></th>
                            <th align="left" data-orderable="false"><?php echo __("admin/users/gdpr-tx2"); ?></th>
                            <th align="left" data-orderable="false"><?php echo __("admin/users/detail-actions-th-ip"); ?></th>
                        </tr>
                        </thead>
                        <tbody align="center" style="border-top:none;"></tbody>
                    </table>
                </div>

                <!--
                <div id="group-disapprovers" class="tabcontent">

                    <div class="green-info">
                        <div class="padding20">
                            <i class="fa fa-info-circle"></i>
                            <p><?php echo __("admin/users/gdpr-tab-disapprovers-desc"); ?></p>
                        </div>
                    </div>
                    <div class="clear"></div>

                    <table width="100%" id="disapprovers" class="table table-striped table-borderedx table-condensed nowrap">
                        <thead style="background:#ebebeb;">
                        <tr>
                            <th align="left" data-orderable="false">#</th>
                            <th align="left" data-orderable="false"><?php echo __("admin/invoices/create-user"); ?></th>
                            <th align="left" data-orderable="false"></th>
                        </tr>
                        </thead>
                        <tbody align="center" style="border-top:none;"></tbody>
                    </table>
                </div>
                -->

            </div>



            <div class="clear"></div>

        </div>
    </div>


</div>

<?php include __DIR__.DS."inc".DS."footer.php"; ?>

</body>
</html>