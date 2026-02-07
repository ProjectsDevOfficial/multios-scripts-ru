<!DOCTYPE html>
<html>
<head>
    <?php
        $privOperation  = Admin::isPrivilege("PRODUCTS_OPERATION");
        $privDelete     = $privOperation;
        $privGroupLook  = Admin::isPrivilege("PRODUCTS_GROUP_LOOK");
        $plugins        = ['dataTables'];
        include __DIR__.DS."inc".DS."head.php";
    ?>


    <script>
        var table;
        $(document).ready(function() {
           table = $('#datatable').DataTable({
                "columnDefs": [
                    {
                        "targets": [0],
                        "visible":false,
                        "searchable": false
                    },
                    {
                        "targets": [1,2,3],
                        "orderable": false
                    }
                ],
                "aaSorting" : [[4, 'asc']],
                "lengthMenu": [
                    [10, 25, 50, -1], [10, 25, 50, "<?php echo ___("needs/allOf"); ?>"]
                ],
                "bProcessing": true,
                "bServerSide": true,
                "sAjaxSource": "<?php echo $links["ajax-product-list"]; ?>",
                responsive: true,
                "oLanguage":<?php include __DIR__.DS."datatable-lang.php"; ?>
            });
        });

        function deleteProduct(id,name){
            var content = "<?php echo __("admin/products/hosting-product-delete-are-youu-sure"); ?>";
            $("#DeleteModal_text").html(content.replace("{name}",name));

            open_modal("DeleteModal",{
                title:"<?php echo __("admin/products/delete-modal-hosting-product-title"); ?>"
            });

            $("#delete_ok").click(function(){
                var request = MioAjax({
                    button_element:this,
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    action: "<?php echo $links["controller"]; ?>",
                    method: "POST",
                    data: {operation:"delete_product",id:id}
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
                                    var elem  = $("#delete_"+id).parent().parent();
                                    table.row(elem).remove().draw();
                                }
                            }else
                                console.log(result);
                        }
                    }else console.log(result);
                });

            });

            $("#delete_no").click(function(){
                close_modal("DeleteModal");
            });

        }
        function copyProduct(id){
            var request = MioAjax({
                button_element:$("#copy_"+id),
                waiting_text: '<i class="fa fa-spinner" style="-webkit-animation:fa-spin 2s infinite linear;animation: fa-spin 2s infinite linear;"></i>',
                action: "<?php echo $links["controller"]; ?>",
                method: "POST",
                data: {operation:"copy_product",id:id}
            },true,true);

            request.done(function(result){
                if(result){
                    if(result !== ''){
                        var solve = getJson(result);
                        if(solve !== false){
                            if(solve.status == "error"){
                                if(solve.message != undefined && solve.message != '')
                                    alert_error(solve.message,{timer:5000});
                            }else if(solve.status == "successful") table.ajax.reload();
                        }else
                            console.log(result);
                    }
                }else console.log(result);
            });
        }

        function applySelection(element){
            var selection = $(element).val();
            if(selection !== '')
            {
                var el_val  = $(element).val();
                var el_text = $("option[value="+el_val+"]",element).text();
                $(element).val('');

                var values = [],value;
                $('.selected-item:checked').each(function(){
                    value       = $(this).val();
                    if(value) values.push(value);
                });
                if(values.length === 0) return false;

                if(selection === "active" || selection === "inactive")
                {
                    open_modal("confirmModal",{title:el_text});

                    $("#confirm_ok").click(function(){
                        close_modal("confirmModal");
                        applyOperation(selection,values);
                    });
                }
                else if(selection === "move")
                {
                    open_modal("moveModal",{title:el_text});

                    $("#moveConfirm_ok").click(function(){
                        close_modal("moveModal");
                        moveProducts(values);
                    });
                }
                else if(selection === "delete")
                {
                    open_modal("DeleteModal",{title:el_text});
                    $("#DeleteModal_text").html('<?php echo ___("needs/delete-are-you-sure"); ?>');
                    $("#delete_ok").click(function(){
                        close_modal("DeleteModal");
                        deleteProducts(values);
                    });
                }
            }
        }
        function applyOperation(selection,ids)
        {
            var request = MioAjax({
                button_element:$('#confirm_ok'),
                waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                action: "<?php echo $links["controller"]; ?>",
                method: "POST",
                data: {operation:"apply_products",ids:ids,selection:selection}
            },true,true);
            request.done(function(result){
                result_handle(selection,result);
            });
        }
        function moveProducts(ids)
        {
            var g_name   = $('#selection_product_group').val();

            if(g_name === '') return false;

            var request = MioAjax({
                button_element:$('#moveConfirm_ok'),
                waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                action: "<?php echo $links["controller"]; ?>",
                method: "POST",
                data: {operation:"apply_products",ids:ids,selection:'move',group:g_name}
            },true,true);
            request.done(function(result){
                result_handle('move',result);
            });
        }
        function deleteProducts(ids)
        {
            var request = MioAjax({
                button_element:$('#delete_ok'),
                waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                action: "<?php echo $links["controller"]; ?>",
                method: "POST",
                data: {operation:"apply_products",ids:ids,selection:'delete'}
            },true,true);
            request.done(function(result){
                result_handle('delete',result);
            });
        }
        function result_handle(selection,result)
        {
            if(result){
                if(result !== ''){
                    var solve = getJson(result);
                    if(solve !== false){
                        if(solve.status === "error"){
                            if(solve.message !== undefined && solve.message !== '')
                                alert_error(solve.message,{timer:5000});
                        }
                        else if(solve.status === "successful"){
                            alert_success(solve.message,{timer:3000});
                            table.ajax.reload();
                            $('#allSelect').prop('checked',false);
                        }
                    }else
                        console.log(result);
                }
            }else console.log(result);
        }
    </script>

</head>
<body>

<div id="DeleteModal" style="display: none;">
    <div class="padding20">
        <div align="center"><p id="DeleteModal_text"></p></div>
    </div>
    <div class="modal-foot-btn">
        <a id="delete_ok" href="javascript:void(0);" class="red lbtn"><?php echo __("admin/products/delete-ok"); ?></a>
    </div>
</div>

<div id="confirmModal" style="display: none;">
    <div class="padding20">
        <div align="center">

            <p><?php echo ___("needs/apply-are-you-sure"); ?></p>
            <div class="clear"></div>
        </div>
    </div>
    <div class="modal-foot-btn">
        <a href="javascript:void(0);" id="confirm_ok" class="lbtn green"><?php echo ___("needs/yes"); ?></a>
    </div>
</div>

<div id="moveModal" style="display: none;">
    <div class="padding20">
        <div align="center">

            <p><?php echo __("admin/products/move-alert"); ?></p>
            <div class="clear"></div>
            <select id="selection_product_group" style="width: 220px;">
                <option value="">-- <?php echo ___("needs/select-your"); ?> ---</option>
                <?php
                    /*
                    if(Config::get("options/pg-activation/hosting"))
                    {
                        ?>
                        <option value="hosting"><?php echo __("admin/index/menu-group-hosting-products"); ?></option>
                        <?php
                    }
                    */

                    if(Config::get("options/pg-activation/server"))
                    {
                        ?>
                        <option value="server"><?php echo __("admin/index/menu-group-server-products"); ?></option>
                        <?php
                    }

                    if(Config::get("options/pg-activation/software"))
                    {
                        ?>
                        <option value="software"><?php echo __("admin/index/menu-group-software"); ?></option>
                        <?php
                    }

                    if(Config::get("options/pg-activation/sms") && Config::get("general/local") == "tr")
                    {
                        ?>
                        <option value="sms"><?php echo __("admin/index/menu-group-sms-products"); ?></option>
                        <?php
                    }

                    $groups         = AdminModel::getSpecialGroups();
                    if($groups){
                        foreach($groups AS $group){
                            if($group["id"] == 240)
                            {
                                $m = Modules::Load("Product",'WISECPReseller',true);
                                if($m && !$m["config"]["status"]) continue;
                            }
                            ?>
                            <option value="special-<?php echo $group["id"]; ?>"><?php echo $group["title"]; ?></option>
                            <?php
                        }
                    }
                ?>
            </select>
            <div class="clear"></div>
        </div>
    </div>
    <div class="modal-foot-btn">
        <a href="javascript:void(0);" id="moveConfirm_ok" class="lbtn green"><?php echo ___("needs/ok"); ?></a>
    </div>
</div>


<?php include __DIR__.DS."inc/header.php"; ?>

<div id="wrapper">

    <div class="icerik-container">
        <div class="icerik">

            <div class="icerikbaslik">
                <h1>
                    <strong><?php echo __("admin/products/page-hosting-list"); ?></strong>
                    <a href="<?php echo $links["hosting-group-redirect"]; ?>" target="_blank" class="sbtn"><i class="fa fa-external-link" aria-hidden="true"></i></a>
                </h1>
                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>

            <?php if($privOperation): ?>
                <a href="<?php echo $links["add-new-product"]; ?>" class="green lbtn"><i class="fa fa-plus"></i> <?php echo __("admin/products/add-new-product"); ?></a>
            <?php endif; ?>

            <a href="<?php echo $links["product-categories"]; ?>" class="blue lbtn"><i class="fa fa-table"></i> <?php echo __("admin/products/categories-button"); ?></a>

            <a href="<?php echo $links["settings"]; ?>" class="lbtn"><i class="fa fa-cog"></i> <?php echo __("admin/products/category-group-settings-button"); ?></a>


            <a href="<?php echo $links["shared-servers"]; ?>" style="float: right;" class="lbtn"><i class="fa fa-server"></i> <?php echo __("admin/products/shared-hosting-server-button"); ?></a>


            <?php if($privOperation): ?>
                <div class="clear"></div>
                <br>
                <select class="applyselect" id="selectApply" onchange="applySelection(this);">
                    <option value=""><?php echo __("admin/orders/list-apply-to-selected"); ?></option>
                    <?php if($privDelete): ?>
                        <option value="delete"><?php echo __("admin/products/list-apply-to-selected-delete"); ?></option>
                    <?php endif; ?>
                    <option value="active"><?php echo __("admin/products/list-apply-to-selected-active"); ?></option>
                    <option value="inactive"><?php echo __("admin/products/list-apply-to-selected-inactive"); ?></option>
                    <option value="move"><?php echo __("admin/products/list-apply-to-selected-move"); ?></option>
                </select>
            <?php endif; ?>

            <div class="clear"></div>


            <table width="100%" id="datatable" class="table table-striped table-borderedx table-condensed nowrap">
                <thead style="background:#ebebeb;">
                <tr>
                    <th align="left">#</th>
                    <th align="left" data-orderable="false">
                        <input type="checkbox" class="checkbox-custom" id="allSelect" onchange="$('.selected-item').prop('checked',$(this).prop('checked'));"><label for="allSelect" class="checkbox-custom-label"></label>
                    </th>
                    <th align="left"><?php echo __("admin/products/list-name"); ?></th>
                    <th align="center"><?php echo __("admin/products/list-category"); ?></th>
                    <th align="center"><?php echo __("admin/products/list-amount"); ?></th>
                    <th align="center"><?php echo __("admin/products/list-status"); ?></th>
                    <th align="center"></th>
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