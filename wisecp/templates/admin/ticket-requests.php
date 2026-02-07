<!DOCTYPE html>
<html>
<head>
    <?php
        $privOperation  = Admin::isPrivilege("TICKETS_OPERATION");
        $privDelete     = Admin::isPrivilege("TICKETS_DELETE");
        $plugins        = ['dataTables','select2','mio-icons'];
        include __DIR__.DS."inc".DS."head.php";

        $client         = Filter::init("GET/client","numbers");
        $department     = Filter::init("GET/department","numbers");
        $status         = Filter::init("GET/status");
        $priority       = Filter::init("GET/priority","numbers");
        $ticket_id      = Filter::init("GET/ticket_id","numbers");
        $assigned_to    = Filter::init("GET/assigned_to","numbers");
        $is_search      = $client || $department || $status || $priority || $ticket_id || $assigned_to;
        if($client) $selectedClient = User::getData((int) $client,"id,full_name","assoc");
        $list_limit     = Config::get("options/ticket-list-limit");

        if(!$list_limit || $list_limit < 1) $list_limit = 10;
        $refresh_time   = Config::get("options/ticket-refresh-time");
        if(!$refresh_time || $refresh_time < 1)
            $refresh_time = 50;
    ?>


    <script>
        var table,auto_refresh;
        $(document).ready(function() {

            table = $('#datatable').DataTable({
                "columnDefs": [
                    {
                        "targets": [0],
                        "visible":false,
                        "searchable": false
                    }
                ],
                "lengthMenu": [
                    [<?php echo $list_limit; ?>, 25, 50, -1], [<?php echo $list_limit; ?>, 25, 50, "<?php echo ___("needs/allOf"); ?>"]
                ],
                "bProcessing": true,
                "bServerSide": true,
                "sAjaxSource": "<?php echo $links["ajax"]; ?>&<?php echo http_build_query(['client' => $client,'department' => $department,'status' => $status,'priority' => $priority,'ticket_id' => $ticket_id,'assigned_to' => $assigned_to]); ?>",
                responsive: true,
                "oLanguage":<?php include __DIR__.DS."datatable-lang.php"; ?>
            });

            auto_refresh = setInterval(_auto_refresh,(1000 * <?php echo $refresh_time; ?>));

            $(".sel2").select2({width:'100%'});

            $("#searchClient").select2({
                width:'100%',
                placeholder: "<?php echo __("admin/orders/create-select-user"); ?>",
                ajax: {
                    url: '<?php echo Controllers::$init->AdminCRLink("orders"); ?>?operation=select-users.json',
                    dataType: 'json',
                    data: function (params) {
                        var query = {
                            none:true,
                            search: params.term,
                            type: 'public'
                        }
                        return query;
                    }
                }
            });

            $("#searchClient,#searchDepartment,#searchStatus,#searchPriority,#searchAssignedTo,#searchTicketID").change(function(){
                var searchPattern = {
                    client      : $("#searchClient").val(),
                    department  : $("#searchDepartment").val(),
                    status      : $("#searchStatus").map(function(){return $(this).val();}).get(),
                    priority    : $("#searchPriority").val(),
                    ticket_id   : $("#searchTicketID").val(),
                    assigned_to : $("#searchAssignedTo").val()
                };
                window.location.href = '<?php echo $links["controller"]; ?>?'+$.param(searchPattern);
            });

        });

        function _auto_refresh(){
            table.ajax.reload();
        }
        function deleteRequest(id){
            open_modal("ConfirmModal");

            $("#delete_ok").click(function(){
                var request = MioAjax({
                    button_element:$("#delete_ok"),
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    action: "<?php echo $links["controller"]; ?>",
                    method: "POST",
                    data: {operation:"delete_request",id:id}
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
                                    close_modal("ConfirmModal");
                                    table.ajax.reload();
                                }
                            }else
                                console.log(result);
                        }
                    }else console.log(result);
                });
            });

            $("#delete_no").click(function(){
                close_modal("ConfirmModal");
            });

        }
    </script>

</head>
<body>

<div id="ConfirmModal" style="display: none;" data-izimodal-title="<?php echo __("admin/tickets/requests-td-delete-title"); ?>">
    <div class="padding20" align="center">
        <p><?php echo __("admin/tickets/delete-are-youu-sure"); ?></p>
    </div>
    <div class="modal-foot-btn">
        <a id="delete_ok" href="javascript:void(0);" class="red lbtn"><?php echo __("admin/orders/delete-ok"); ?></a>
    </div>
</div>

<?php include __DIR__.DS."inc/header.php"; ?>

<div id="wrapper">

    <div class="icerik-container">
        <div class="icerik">

            <div class="icerikbaslik">
                <h1>
                    <strong><?php echo __("admin/tickets/page-requests"); ?></strong>
                </h1>
                <?php if(Admin::isPrivilege(["ADMIN_PRIVILEGES"])): ?>
                    <a style="margin-right:10px;" class="lbtn" href="<?php echo $links["settings"]; ?>"><i class="fa fa-cog"></i> <?php echo __("admin/manage-website/button-settings"); ?></a>
                <?php endif; ?>

                <a class="lbtn" href="javascript:$('#ticketSearchFilterWrap').slideToggle(); void 0;"><i class="fa fa-search"></i> <?php echo __("admin/tickets/searchFilter"); ?></a>
                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>

            <div class="clear"></div>
            <?php
                if($h_contents = Hook::run("TicketAdminAreaViewList"))
                    foreach($h_contents AS $h_content)
                        if($h_content) echo $h_content;
            ?>

            <div class="advanced-filter-area" id="ticketSearchFilterWrap" style="<?php echo $is_search ? '' : 'display:none;'; ?>;">
               <div class="yuzde50">
                   <div class="formcon">
                       <div class="yuzde30"><?php echo __("admin/invoices/bills-th-customer"); ?></div>
                       <div class="yuzde70">
                           <select id="searchClient">
                               <?php
                                   if(isset($selectedClient) && $selectedClient)
                                   {
                                       ?>
                                       <option value="<?php echo $client; ?>"><?php echo $selectedClient["full_name"]; ?></option>
                                       <?php
                                   }
                               ?>
                           </select>
                       </div>
                   </div>
               </div>
                <div class="yuzde50">
                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/tickets/requests-th-department"); ?></div>
                        <div class="yuzde70">
                            <select id="searchDepartment" class="sel2">
                                <option value=""><?php echo ___("needs/select-your"); ?></option>
                                <?php
                                    foreach($departments AS $k => $v)
                                    {
                                        ?><option<?php echo $v["id"] == $department ? ' selected' : ''; ?> value="<?php echo $v["id"]; ?>"><?php echo $v["name"]; ?></option><?php
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="yuzde50">
                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/tickets/request-detail-status"); ?></div>
                        <div class="yuzde70">
                            <select id="searchStatus" class="sel2" multiple>
                                <?php
                                    if(isset($situations))
                                    {
                                        foreach($situations AS $k => $v)
                                        {
                                            if(is_int($k))
                                            {
                                                $k = $v["type"]."-".$v["id"];
                                                $v = $v["languages"][$ui_lang]["name"];
                                            }

                                            ?><option<?php echo is_array($status) && in_array($k,$status)  ? ' selected' : ''; ?> value="<?php echo $k; ?>"><?php echo $v; ?></option><?php
                                        }
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="yuzde50">
                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/tickets/request-detail-priority"); ?></div>
                        <div class="yuzde70">
                            <select id="searchPriority" class="sel2">
                                <option value=""><?php echo ___("needs/select-your"); ?></option>
                                <?php
                                    foreach($priorities AS $k => $v)
                                    {
                                        ?><option<?php echo $k == $priority ? ' selected' : ''; ?> value="<?php echo $k; ?>"><?php echo $v; ?></option><?php
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="yuzde50">
                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/tickets/search-assigned-to"); ?></div>
                        <div class="yuzde70">
                            <select id="searchAssignedTo" class="sel2">
                                <option value=""><?php echo ___("needs/select-your"); ?></option>
                                <?php
                                    foreach($assignable_users AS $k => $v)
                                    {
                                        ?><option<?php echo $v["id"] == $assigned_to ? ' selected' : ''; ?> value="<?php echo $v["id"]; ?>"><?php echo $v["full_name"]; ?></option><?php
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="yuzde50">
                    <div class="formcon">
                        <div class="yuzde30">ID</div>
                        <div class="yuzde70">
                            <input type="text" id="searchTicketID" value="<?php echo $ticket_id; ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div id="Requests">
                <table width="100%" id="datatable" class="table table-striped table-borderedx table-condensed nowrap">
                    <thead style="background:#ebebeb;">
                    <tr>
                        <th align="left">#</th>
                        <th align="left" data-orderable="false"><?php echo __("admin/tickets/requests-th-user"); ?></th>
                        <th align="left" data-orderable="false"><?php echo __("admin/tickets/requests-th-subject"); ?></th>
                        <th align="center" data-orderable="false"><?php echo __("admin/tickets/requests-th-department"); ?></th>
                        <th align="center" data-orderable="false"><?php echo __("admin/tickets/requests-th-assigned"); ?></th>
                        <th align="center" data-orderable="false"><?php echo __("admin/tickets/requests-th-cdate"); ?></th>
                        <th align="center" data-orderable="false"><i class="ion-android-done-all"></i></th>
                        <th align="center" data-orderable="false"><?php echo __("admin/tickets/requests-th-status"); ?></th>
                        <th align="center" data-orderable="false"></th>
                    </tr>
                    </thead>
                    <tbody align="center" style="border-top:none;"></tbody>
                </table>
            </div>
            <div class="clear"></div>

        </div>
    </div>


</div>

<?php include __DIR__.DS."inc".DS."footer.php"; ?>

</body>
</html>