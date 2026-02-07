<!DOCTYPE html>
<html>
<head>
    <?php
        $privOperation  = Admin::isPrivilege("PRODUCTS_OPERATION");
        $plugins        = [
            'jquery-ui',
            'dataTables',
            'drag-sort',
        ];
        include __DIR__.DS."inc".DS."head.php";
    ?>
    <script type="text/javascript">

        var table;
        var doc_id;
        $(document).ready(function() {

            table = $('#datatable').DataTable({
                "columnDefs": [
                    {
                        "targets": [0],
                        "visible":false,
                        "searchable": false
                    },
                ],
                "aaSorting" : [[0, 'asc']],
                "pageLength" : 20,
                "lengthMenu": [
                    [20, 50, -1], [20, 50, "<?php echo ___("needs/allOf"); ?>"]
                ],
                "bProcessing": true,
                "bServerSide": true,
                "sAjaxSource": "<?php echo $links["controller"] ?? ''; ?>?operation=ajax-domain-docs",
                responsive: true,
                "oLanguage":<?php include __DIR__.DS."datatable-lang.php"; ?>
            });


            $("#deleteSelected_submit").on("click",function(){

                if(!confirm('<?php echo str_replace("'","\'",___("needs/delete-are-you-sure")); ?>')) return false;

                var get_ids = $(".selected-item:checked").map(function(){return $(this).val();}).get();
                var request = MioAjax({
                    action : "<?php echo $links["controller"]; ?>",
                    method : "POST",
                    data:{
                        operation: "deleteSelected_tld_docs",
                        ids:get_ids
                    },
                    button_element:this,
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                },true,true)
                request.done(updateForm_handler);
            });

            $("#DeleteModal").on("click","#delete_ok",function(){
                var request = MioAjax({
                    button_element:$(this),
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    action: "<?php echo $links["controller"]; ?>",
                    method: "POST",
                    data: {operation:"delete_tld_doc",id:doc_id}
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
                                    close_modal("DeleteModal");
                                    var elem  = $("#delete_"+doc_id).parent().parent();
                                    table.row(elem).remove().draw();
                                }
                            }else
                                console.log(result);
                        }
                    }else console.log(result);
                });
            });

        });

        function updateForm_handler(result){
            $('.unchecked-input').remove();
            if(result !== ''){
                var solve = getJson(result);
                if(solve !== false){
                    if(solve.status == "error"){
                        if(solve.message != undefined && solve.message != '')
                            alert_error(solve.message,{timer:5000});
                    }
                    else if(solve.status == "successful"){
                        alert_success(solve.message,{timer:2000});
                        table.ajax.reload(null,false);
                        $("#allSelect").prop('checked',false);
                    }
                }else
                    console.log(result);
            }
        }

        function deleteDoc(id){
            doc_id = id;
            open_modal("DeleteModal",{
                title:"<?php echo ___("needs/button-delete"); ?>"
            });
        }
    </script>
    <style type="text/css">
        #datatable  thead th:nth-child(2) {text-align: left;}
        #datatable  thead th:nth-child(3) {text-align: right;}
        #datatable  tbody td:nth-child(3) {text-align: right;}
    </style>

</head>
<body>


<div id="DeleteModal" style="display: none;">
    <div class="padding20">
        <div align="center">
            <p id="DeleteModal_text"><?php echo ___("needs/delete-are-you-sure"); ?></p>
        </div>
    </div>
    <div class="modal-foot-btn">
        <a id="delete_ok" href="javascript:void(0);" class="red lbtn"><?php echo __("admin/products/delete-ok"); ?></a>
    </div>
</div>


<?php include __DIR__.DS."inc/header.php"; ?>

<div id="wrapper">

    <div class="icerik-container">
        <div class="icerik">

            <div class="icerikbaslik">
                <h1>
                    <strong><?php echo __("admin/products/page-domain-docs"); ?></strong>
                </h1>
                <?php
                    $ui_help_link = 'https://docs.wisecp.com/en/kb/domain-name-service-management';
                    if($ui_lang == "tr") $ui_help_link = 'https://docs.wisecp.com/tr/kb/alan-adi-hizmet-yonetimi';
                ?>
                <a title="<?php echo __("admin/help/usage-guide"); ?>" target="_blank" class="pagedocslink" href="<?php echo $ui_help_link; ?>"><i class="fa fa-life-ring" aria-hidden="true"></i></a>
                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>

            <div class="green-info">
                <div class="padding20">
                    <i class="fa fa-info-circle"></i>
                    <p><?php echo __("admin/products/domain-docs-tx16"); ?></p>
                </div>
            </div>



            <div style="float: left;">
                <a style="float: none;" href="<?php echo $links["new"]; ?>" class="blue lbtn">+ <?php echo __("admin/products/add-new-domain-doc-button"); ?></a>
            </div>
            <div class="clear"></div>

           <div class="domaindoclist">
               <table width="100%" id="datatable" class="table table-striped table-borderedx table-condensed nowrap">
                   <thead style="background:#ebebeb;">
                   <tr>
                       <th align="left">#</th>
                       <th width="5%" data-orderable="false" align="center">
                           <input type="checkbox" class="checkbox-custom" id="allSelect" onchange="$('.selected-item').prop('checked',$(this).prop('checked'));"><label for="allSelect" class="checkbox-custom-label"></label>
                       </th>
                       <th data-orderable="false" align="left"><?php echo __("admin/products/domain-docs-tx1"); ?></th>
                       <th data-orderable="false" align="right"></th>
                   </tr>
                   </thead>
                   <tbody align="center" style="border-top:none;"></tbody>
               </table>

               <div class="clear"></div>
               <div class="line"></div>
               <div class="clear"></div>

               <div style="float: right;width:49%;text-align:right;">
                   <a id="deleteSelected_submit" style="float: none;" href="javascript:void(0);" class="red lbtn"><i class="fa fa-trash"></i> <?php echo __("admin/orders/list-apply-to-selected-delete"); ?></a>
               </div>
               <div class="clear"></div>
               <br>
           </div>

            <div class="clear"></div>
        </div>


    </div>


</div>

<?php include __DIR__.DS."inc".DS."footer.php"; ?>

</body>
</html>