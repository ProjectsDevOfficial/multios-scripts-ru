<!DOCTYPE html>
<html>
<head>
    <?php
        if(!($departments ?? [])) $departments = [];

        Utility::sksort($lang_list,"local");
        $local_l    = Config::get("general/local");

        $privOperation  = Admin::isPrivilege("TICKETS_OPERATION");
        $privDelete     = Admin::isPrivilege("TICKETS_DELETE");
        $plugins        = [
            'tinymce' => ["height" => 258],
            'tinymce-1',
            'jquery-ui',
            'dataTables',
            'jscolor',
            'select2',
            'mio-icons'
        ];
        include __DIR__.DS."inc".DS."head.php";
    ?>


    <script type="text/javascript">
        var table1;
        var select2_loaded=false;
        $(document).ready(function()
        {
            table1 = $('#table-auto-tasks').DataTable({
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
                "sAjaxSource": "<?php echo $links["controller"]; ?>?operation=ajax-auto-tasks",
                responsive: true,
                "oLanguage":<?php include __DIR__.DS."datatable-lang.php"; ?>
            });

            $(".change-lang-buttons a").click(function(){
                var _wrap   = $(this).parent();
                var _type   = $(_wrap).data("type");
                var k       = $(this).data("key");

                if($(this).attr("id") === "lang-active") return false;
                window[_type+"_selected_lang"] = k;
                $("."+_type+"-names").css("display","none");
                $("."+_type+"-name-"+k).css("display","block");

                $("."+_type+"-values").css("display","none");
                $("."+_type+"-value-"+k).css("display","block");

                $("a",_wrap).removeAttr("id");
                $(this).attr("id","lang-active");
            });

            $("#settingsForm_submit").click(function(){
                MioAjaxElement(this,{
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    result:"settingsForm_handle",
                });
            });

            $("#auto_task_manage_modal").on("click","#manageAutoTaskForm_submit",function(){
                MioAjaxElement(this,{
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    result:"manageAutoTaskForm_handle",
                });
            });


            var tab = _GET("tab");
            if (tab != '' && tab != undefined) {
                $("#tab-tab .tablinks[data-tab='" + tab + "']").click();
            } else {
                $("#tab-tab .tablinks:eq(0)").addClass("active");
                $("#tab-tab .tabcontent:eq(0)").css("display", "block");
            }

            $("#statuses-list").sortable({
                handle:'.status-bearer',
            }).disableSelection();

            $("#statuses-list").on("click",".status-delete",function(){
                var id = $(this).data("id");
                if(id !== undefined) {
                    var before_ids = $("input[name=delete_statuses]").val();
                    var o_ids = before_ids.length > 0 ? before_ids.split(",") : [];
                    o_ids.push(id);
                    $("input[name=delete_statuses]").val(o_ids.length > 1 ? o_ids.join(",") : id);
                }
                $(this).parent().parent().remove();
                $("#statuses-list").sortable("refresh");
            });

            jscolor.presets.default = {
                format:'hex', previewPosition:'right', previewSize:61, mode:'HVS',
                closeButton:true, closeText:'<?php echo ___("needs/ok"); ?>', buttonColor:'rgba(3,24,40,1)',
                controlBorderColor:'rgba(143,111,158,0.58)', shadow:false,
                shadowColor:'rgba(64,135,211,0.2)'
            };

            $('.select-color').each(function(){
                var obj = $(this)[0];
                if (!obj.hasPicker)
                {
                    var picker = new jscolor(obj);
                    obj.hasPicker = true;
                }
            });

            $("#auto_task_manage_modal").iziModal(get_modal_options_generate());

            $(document).on('opening', '#auto_task_manage_modal',function (e){
                $('.select2').select2({width:'100%'});
            });


        });
        function settingsForm_handle(result)
        {
            if(result){
                if(result != ''){
                    var solve = getJson(result);
                    if(solve !== false){
                        if(solve.status == "error"){
                            if(solve.message != undefined && solve.message != '')
                                alert_error(solve.message,{timer:5000});
                        }
                        else if(solve.status == "successful"){
                            alert_success(solve.message,{timer:3000});
                        }
                    }else
                        console.log(result);
                }
            }else console.log(result);
        }

        function manageAutoTaskForm_handle(result)
        {
            if(result){
                if(result != ''){
                    var solve = getJson(result);
                    if(solve !== false){
                        if(solve.status == "error"){
                            if(solve.message != undefined && solve.message != '')
                                alert_error(solve.message,{timer:5000});
                        }
                        else if(solve.status == "successful"){
                            alert_success(solve.message,{timer:3000});
                            $("#auto_task_manage_modal").iziModal('close');
                            table1.ajax.reload();
                        }
                    }else
                        console.log(result);
                }
            }else console.log(result);
        }

        function addStatus(){
            var template = $("#status-item-template").html();

            $("#statuses-list").append(template);
            $("#statuses-list").sortable("refresh");

            $('.select-color').each(function(){
                var obj = $(this)[0];
                if (!obj.hasPicker)
                {
                    var picker = new jscolor(obj);
                    obj.hasPicker = true;
                }
            });
        }

        function auto_task_modal(type,details,btn)
        {

            if(type === "delete")
            {
                if(confirm("<?php echo ___("needs/delete-are-you-sure"); ?>"))
                {
                    let request = MioAjax({
                        button_element:$(btn),
                        waiting_text: '<i class="fa fa-spinner" style="-webkit-animation:fa-spin 2s infinite linear;animation: fa-spin 2s infinite linear;"></i>',
                        action:"<?php echo $links["controller"]; ?>",
                        method:"POST",
                        data:{operation:"cud_auto_task",type:"delete",id:details}
                    },true,true);
                    request.done(function(result){
                        let solve = getJson(result);
                        if(solve !== false)
                        {
                            if(solve.status === "error")
                                alert_error(solve.message,{timer:5000});
                            else if(solve.status === "successful")
                            {
                                alert_success(solve.message,{timer:3000});
                                table1.ajax.reload();
                            }
                        }
                    });

                }
            }
            else
            {
                $("#auto_task_manage_modal").iziModal('open');

                if(type === "create")
                {
                    $("#auto_task_manage_modal").iziModal('setTitle','<?php echo ___("needs/button-create"); ?>');
                    $("#auto_task_manage_modal input[name=type]").val("create");
                }
                else if(type === "update")
                {
                    $("#auto_task_manage_modal").iziModal('setTitle','<?php echo ___("needs/button-update"); ?>');
                    $("#auto_task_manage_modal input[name=type]").val("update");
                }

                if(type === "update")
                {
                    details     = getJson(b64decode(details));

                    if(details.department == 0) details.department = '';
                    if(details.priority == 0) details.priority = '';
                    if(details.assign_to == 0) details.assign_to = '';

                    $("#auto_task_manage_modal input[name=id]").val(details.id);
                    $("#auto_task_manage_modal input[name=name]").val(details.name);
                    $("#auto_task_manage_modal input[name=delay_time]").val(details.delay_time);
                    $("#auto_task_manage_modal select[name=department]").val(details.department);
                    $("#auto_task_manage_modal select[name=status]").val(details.status);
                    $("#auto_task_manage_modal select[name=priority]").val(details.priority);
                    $("#auto_task_manage_modal select[name=assign_to]").val(details.assign_to);
                    $("#auto_task_manage_modal select[name=template]").val('').trigger('change');
                    if(details.template !== '')
                        $("#auto_task_manage_modal select[name=template] option[value='"+details.template+"']").prop('selected',true).trigger('change');
                    $("#auto_task_manage_modal input[name=mark_locked]").prop('checked',details.mark_locked == 1);
                    $("#auto_task_manage_modal input[name=repeat_action]").prop('checked',details.repeat_action == 1);

                    let departments = details.departments.split(",");
                    let statuses    = details.statuses.split(",");
                    let priorities  = details.priorities.split(",");

                    $("#auto_task_manage_modal select[name='departments[]'] option").prop('selected',false).trigger('change');
                    $("#auto_task_manage_modal select[name='statuses[]'] option").prop('selected',false).trigger('change');
                    $("#auto_task_manage_modal select[name='priorities[]'] option").prop('selected',false).trigger('change');

                    $.each(departments,function(k,v){
                        $("#auto_task_manage_modal select[name='departments[]'] option[value='"+v+"']").prop('selected',true).trigger('change');
                    });

                    $.each(statuses,function(k,v){
                        $("#auto_task_manage_modal select[name='statuses[]'] option[value='"+v+"']").prop('selected',true).trigger('change');
                    });

                    $.each(priorities,function(k,v){
                        $("#auto_task_manage_modal select[name='priorities[]'] option[value='"+v+"']").prop('selected',true).trigger('change');
                    });

                    $.each(details.reply,function(k,v){
                        tinymce.get('reply_'+k).setContent(v);
                        $('#reply_'+k).val(v);
                    });

                    $("#manageAutoTaskForm_submit").html('<?php echo ___("needs/button-update"); ?>');
                }
                else if(type === "create")
                {
                    $("#auto_task_manage_modal input[name=id]").val('0');
                    $("#auto_task_manage_modal input[name=name]").val('');
                    $("#auto_task_manage_modal input[name=delay_time]").val('');
                    $("#auto_task_manage_modal select[name=department]").val('');
                    $("#auto_task_manage_modal select[name=status]").val('');
                    $("#auto_task_manage_modal select[name=priority]").val('');
                    $("#auto_task_manage_modal select[name=assign_to]").val('');
                    $("#auto_task_manage_modal select[name=template]").val('').trigger('change');
                    $("#auto_task_manage_modal input[name=mark_locked]").prop('checked',false);
                    $("#auto_task_manage_modal input[name=repeat_action]").prop('checked',false);


                    $("#auto_task_manage_modal select[name='departments[]'] option").prop('selected',false).trigger('change');
                    $("#auto_task_manage_modal select[name='statuses[]'] option").prop('selected',false).trigger('change');
                    $("#auto_task_manage_modal select[name='priorities[]'] option").prop('selected',false).trigger('change');


                    $('.tinymce-1').each(function(){
                        tinymce.get($(this).attr("id")).setContent('');
                        $($(this).attr("id")).val('');
                    });

                    $("#manageAutoTaskForm_submit").html('<?php echo ___("needs/button-create"); ?>');
                }
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
                    <strong><?php echo __("admin/tickets/page-settings"); ?></strong>
                </h1>

                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>

            <div class="clear"></div>


            <div id="tab-tab">
                <ul class="tab">
                    <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'general','tab')" data-tab="general"><?php echo __("admin/tickets/settings-tab-general"); ?></a></li>
                    <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'pipe','tab')" data-tab="pipe"><?php echo __("admin/tickets/settings-tab-pipe"); ?></a></li>
                    <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'statuses','tab')" data-tab="statuses"><?php echo __("admin/tickets/settings-tab-statuses"); ?></a></li>
                    <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'tasks','tab')" data-tab="tasks"><?php echo __("admin/tickets/settings-tab-tasks"); ?></a></li>
                </ul>



                <div id="tab-general" class="tabcontent">

                    <div class="adminpagecon">
                        <form action="<?php echo $links["controller"]; ?>" method="post" id="settingsForm">
                            <input type="hidden" name="operation" value="save_settings">
                            <div class="padding20">

                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/tickets/settings-tx1"); ?></div>
                                    <div class="yuzde70">

                                        <input<?php echo Config::get("options/ticket-show-first") == 1 ? ' checked' : ''; ?> type="radio" name="show_first" value="1" class="radio-custom" id="setting_show_first_1">
                                        <label class="radio-custom-label" for="setting_show_first_1"><span class="kinfo"><strong><?php echo __("admin/tickets/settings-tx2"); ?></strong></span></label>

                                        <div class="clear"></div>

                                        <input<?php echo !Config::get("options/ticket-show-first") || Config::get("options/ticket-show-first") == 2 ? ' checked' : ''; ?> type="radio" name="show_first" value="2" class="radio-custom" id="setting_show_first_2">
                                        <label class="radio-custom-label" for="setting_show_first_2"><span class="kinfo"><strong><?php echo __("admin/tickets/settings-tx3"); ?></strong></span></label>

                                        <div class="line"></div>
                                        <div class="formcon">
                                            <div class="yuzde30" style="vertical-align: top;">
                                                <?php echo __("admin/tickets/settings-tx6"); ?>
                                            </div>
                                            <div class="yuzde70">
                                                <?php
                                                    $sel = '<select style="width: 200px;" name="member_group">';
                                                    if(isset($user_groups) && $user_groups)
                                                    {
                                                        $sel .= '<option value="0">'.___("needs/none").'</option>';
                                                        foreach($user_groups AS $ug)
                                                        {
                                                            $sd = Config::get("options/ticket-member-group") == $ug["id"] ? ' selected' : '';
                                                            $op = '<option'.$sd.' value="'.$ug["id"].'">'.$ug["name"].'</option>';
                                                            $sel .= $op;
                                                        }
                                                    }
                                                    $sel .= '</select>';
                                                    echo $sel;
                                                ?>
                                                <div class="clear"></div>
                                                <span class="kinfo"><?php echo __("admin/tickets/settings-tx7"); ?></span>
                                            </div>
                                        </div>

                                    </div>
                                </div>



                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/tickets/settings-tx8"); ?></div>
                                    <div class="yuzde70">
                                        <input<?php echo Config::get("options/ticket-block-blacklisted") ? ' checked' : ''; ?> type="checkbox" id="block_blacklisted" value="1" name="block_blacklisted" class="checkbox-custom">
                                        <label class="checkbox-custom-label" for="block_blacklisted"><span class="kinfo"><?php echo __("admin/tickets/settings-tx9"); ?></span></label>
                                    </div>
                                </div>

                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/tickets/settings-tx10"); ?></div>
                                    <div class="yuzde70">
                                        <input<?php echo Config::get("options/ticket-block-those-without-service") ? ' checked' : ''; ?> type="checkbox" id="block_those_without_service" value="1" name="block_those_without_service" class="checkbox-custom">
                                        <label class="checkbox-custom-label" for="block_those_without_service"><span class="kinfo"><?php echo __("admin/tickets/settings-tx11"); ?></span></label>
                                    </div>
                                </div>

                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/tickets/settings-tx12"); ?></div>
                                    <div class="yuzde70">
                                        <input<?php echo Config::get("options/ticket-assigned-tickets-only") ? ' checked' : ''; ?> type="checkbox" id="assigned_tickets_only" value="1" name="assigned_tickets_only" class="checkbox-custom">
                                        <label class="checkbox-custom-label" for="assigned_tickets_only"><span class="kinfo"><?php echo __("admin/tickets/settings-tx13"); ?></span></label>
                                    </div>
                                </div>

                                <div class="formcon" id="nsricwv_wrap" style="<?php echo Config::get("options/ticket-system") ? '' : 'display:none;'; ?>">
                                    <div class="yuzde30"><?php echo __("admin/settings/nsricwv"); ?></div>
                                    <div class="yuzde70">
                                        <input<?php echo Config::get("options/nsricwv") ? ' checked' : ''; ?> type="checkbox" class="sitemio-checkbox" name="nsricwv" value="1" id="nsricwv">
                                        <label class="sitemio-checkbox-label" for="nsricwv"></label>
                                        <span class="kinfo"><?php echo __("admin/settings/nsricwv-desc"); ?></span>
                                    </div>
                                </div>

                                <div class="formcon" id="ticket-assignable_wrap" style="<?php echo Config::get("options/ticket-system") ? '' : 'display:none;'; ?>">
                                    <div class="yuzde30"><?php echo __("admin/settings/ticket-assignable"); ?></div>
                                    <div class="yuzde70">
                                        <input<?php echo Config::get("options/ticket-assignable") ? ' checked' : ''; ?> type="checkbox" class="sitemio-checkbox" name="ticket-assignable" value="1" id="ticket-assignable">
                                        <label class="sitemio-checkbox-label" for="ticket-assignable"></label>
                                        <span class="kinfo"><?php echo __("admin/settings/ticket-assignable-desc"); ?></span>
                                    </div>
                                </div>



                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/tickets/settings-tx14"); ?></div>
                                    <div class="yuzde70">
                                        <input type="text" value="<?php $lmt = Config::get("options/ticket-list-limit"); echo $lmt ? $lmt : 10; ?>" name="list_limit" style="width: 50px;text-align: center;font-weight: 600;">
                                        <span class="kinfo"><?php echo __("admin/tickets/settings-tx15"); ?></span>
                                    </div>
                                </div>

                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/tickets/settings-tx16"); ?></div>
                                    <div class="yuzde70">
                                        <input type="text" value="<?php $lmt = Config::get("options/ticket-refresh-time"); echo $lmt ? $lmt : 30; ?>" name="refresh_time" style="width: 50px;text-align: center;font-weight: 600;">
                                        <span class="kinfo"><?php echo __("admin/tickets/settings-tx17"); ?></span>
                                    </div>
                                </div>


                            </div>
                            <div class="yuzde30 guncellebtn" style="float: right">
                                <a id="settingsForm_submit" href="javascript:void(0);" class="yesilbtn gonderbtn"><?php echo ___("needs/button-save"); ?></a>
                            </div>
                        </form>
                    </div>

                </div>

                <div id="tab-pipe" class="tabcontent">
                  <div class="adminpagecon">

                      <div class="green-info">
                          <div class="padding20">
                              <i class="fa fa-info-circle"></i>
                              <p><?php echo __("admin/tickets/settings-pipe-desc"); ?></p>
                          </div>
                      </div>


                      <form action="<?php echo $links["controller"]; ?>" method="post" id="pipeForm">
                          <input type="hidden" name="operation" value="manage_pipe">
                          <input type="hidden" name="operation_button" value="">
                          <input type="hidden" name="operation_button_did" value="0">

                          <div class="formcon">
                              <div class="yuzde30"><?php echo __("admin/tickets/settings-pipe-enable"); ?></div>
                              <div class="yuzde70">
                                  <input<?php echo (int) Config::get("options/ticket-pipe/status") ? ' checked' : NULL; ?> name="status" type="checkbox" value="1" class="checkbox-custom" id="pipe-enable">
                                  <label class="checkbox-custom-label" for="pipe-enable"><span class="kinfo"><?php echo __("admin/tickets/settings-pipe-enable-desc"); ?></span></label>
                              </div>
                          </div>

                          <div class="formcon">
                              <div class="yuzde30">
                                  <?php echo __("admin/tickets/settings-existing-client"); ?>
                                  <div class="clear"></div>
                              </div>
                              <div class="yuzde70">
                                  <input<?php echo (int) Config::get("options/ticket-pipe/existing-client") == 0 ? ' checked' : NULL; ?> type="radio" name="existing_client" value="0" class="radio-custom" id="existing-client-0">
                                  <label class="radio-custom-label" for="existing-client-0">
                                      <?php echo __("admin/tickets/settings-existing-client-title-0"); ?>
                                      <br>
                                      <span class="kinfo"><?php echo __("admin/tickets/settings-existing-client-desc-0"); ?></span>
                                  </label>
                                  <br>
                                  <input<?php echo (int) Config::get("options/ticket-pipe/existing-client") == 1 ? ' checked' : NULL; ?> type="radio" name="existing_client" value="1" class="radio-custom" id="existing-client-1">
                                  <label class="radio-custom-label" for="existing-client-1">
                                      <?php echo __("admin/tickets/settings-existing-client-title-1"); ?>
                                      <br>
                                      <span class="kinfo"><?php echo __("admin/tickets/settings-existing-client-desc-1"); ?></span>
                                  </label>
                                  <br>

                                  <input<?php echo (int) Config::get("options/ticket-pipe/existing-client") == 2 ? ' checked' : NULL; ?> type="radio" name="existing_client" value="2" class="radio-custom" id="existing-client-2">
                                  <label class="radio-custom-label" for="existing-client-2">
                                      <?php echo __("admin/tickets/settings-existing-client-title-2"); ?>
                                      <br>
                                      <span class="kinfo"><?php echo __("admin/tickets/settings-existing-client-desc-2"); ?></span>
                                  </label>

                              </div>
                          </div>


                          <div class="formcon">
                              <div class="yuzde30"><?php echo __("admin/tickets/settings-spam-control"); ?></div>
                              <div class="yuzde70">
                                  <input<?php echo Config::get("options/ticket-pipe/spam-control") ? ' checked' : NULL; ?> type="checkbox" name="spam_control" value="1" class="checkbox-custom" id="spam-control">
                                  <label class="checkbox-custom-label" for="spam-control">
                                      <span class="kinfo"><?php echo __("admin/tickets/settings-spam-control-desc"); ?></span>
                                  </label>
                              </div>
                          </div>

                          <div class="formcon">
                              <div class="yuzde30"><?php echo __("admin/tickets/settings-prefix"); ?></div>
                              <div class="yuzde70">
                                  <input type="text" class="yuzde20" name="prefix" value="<?php echo Config::get("options/ticket-pipe/prefix") ? Config::get("options/ticket-pipe/prefix") : 'REF'; ?>">
                                  <span class="kinfo"><?php echo __("admin/tickets/settings-prefix-desc"); ?></span>
                              </div>
                          </div>

                          <div class="clear"></div>

                          <div class="blue-info">
                              <div class="padding20">
                                  <i class="fa fa-info-circle"></i>
                                  <p><?php echo __("admin/tickets/settings-mail-desc"); ?></p>
                              </div>
                          </div>

                          <div class="clear"></div>
                          <div class="verticaltabs" id="verticaltabs">
                              <div id="tab-department">
                                  <ul class="tab" style="width: 25%;">
                                      <?php
                                          if($departments)
                                          {
                                              foreach($departments AS $k => $d)
                                              {
                                                  ?>
                                                  <li><a href="javascript:void(0)" onclick="open_tab(this, 'd<?php echo $d["id"]; ?>','department')" data-tab="d<?php echo $d["id"]; ?>" class="tablinks"><span><?php echo $d["name"]; ?></span></a></li>
                                                  <?php
                                              }
                                          }
                                      ?>
                                  </ul>

                                  <?php
                                      if($departments)
                                      {
                                          foreach($departments AS $k => $d)
                                          {
                                              $did          = $d["id"];
                                              $provider     = Config::get("options/ticket-pipe/mail/".$did."/provider");
                                              ?>
                                              <div class="tabcontent" id="department-d<?php echo $did; ?>">

                                                  <div class="verticaltabstitle">
                                                      <h2><?php echo $d["name"]; ?></h2>
                                                  </div>

                                                  <div class="module-page-content">

                                                      <div class="biggroup" style="margin:0;">
                                                          <div class="padding20">
                                                              <h4 class="biggrouptitle"><?php echo __("admin/tickets/pipe-text1"); ?></h4>
                                                              <span class="kinfo" style="opacity: 0.7;"><i class="fas fa-info-circle"></i> <?php echo __("admin/tickets/pipe-text2"); ?></span>
                                                              <div class="clear"></div>

                                                              <div class="formcon">
                                                                  <div class="yuzde30"><?php echo __("admin/tickets/settings-mail-from"); ?></div>
                                                                  <div class="yuzde70">
                                                                      <input type="text" name="department[<?php echo $did; ?>][from]" value="<?php echo Config::get("options/ticket-pipe/mail/".$did."/from"); ?>">
                                                                  </div>
                                                              </div>

                                                              <div class="formcon">
                                                                  <div class="yuzde30"><?php echo __("admin/tickets/settings-mail-from-name"); ?></div>
                                                                  <div class="yuzde70">
                                                                      <input type="text" name="department[<?php echo $did; ?>][fname]" value="<?php echo Config::get("options/ticket-pipe/mail/".$did."/fname"); ?>">
                                                                  </div>
                                                              </div>

                                                          </div>
                                                      </div>

                                                      <div class="biggroup" style="margin: 0;">
                                                          <div class="padding20">
                                                              <h4 class="biggrouptitle"><?php echo __("admin/tickets/pipe-text3"); ?></h4>
                                                              <span class="kinfo" style="opacity: 0.7;"><i class="fas fa-info-circle"></i> <?php echo __("admin/tickets/pipe-text4"); ?></span>
                                                              <div class="clear"></div>

                                                              <div class="formcon">
                                                                  <div class="yuzde30"><?php echo __("admin/tickets/pipe-text5"); ?></div>
                                                                  <div class="yuzde70">

                                                                      <select name="department[<?php echo $did; ?>][provider]" onchange="let parentEl = $(this).parent().parent().parent(); $('.pipe-providers',parentEl).css('display','none');$('.pipe-provider-'+$(this).val(),parentEl).css('display','block');">
                                                                          <option<?php echo !$provider || $provider == "server" ? ' selected' : ''; ?> value="server"><?php echo __("admin/tickets/pipe-text6"); ?></option>
                                                                          <?php
                                                                              if($pipe_modules ?? [])
                                                                              {
                                                                                  foreach($pipe_modules AS $mk=>$mv)
                                                                                  {
                                                                                      ?>
                                                                                      <option<?php echo $provider == $mk ? ' selected' : ''; ?> value="<?php echo $mk; ?>"><?php echo $mv["lang"]["name"] ?? $mk; ?></option>
                                                                                      <?php
                                                                                  }
                                                                              }
                                                                          ?>
                                                                      </select>

                                                                  </div>
                                                              </div>


                                                              <div id="provider_<?php echo $did; ?>_content_server" style="<?php echo !$provider || $provider == "server" ? '' : 'display: none;'; ?>" class="pipe-provider-server pipe-providers">
                                                                  <div class="formcon">
                                                                      <div class="yuzde30"><?php echo __("admin/tickets/settings-forward-command"); ?></div>
                                                                      <div class="yuzde70">
                                                                          <span class="croncommand">php -q <?php echo ROOT_DIR."coremio".DS."pipe.php"; ?></span>
                                                                          <br><span class="kinfo"><?php echo __("admin/tickets/settings-forward-command-desc"); ?></span>
                                                                      </div>
                                                                  </div>
                                                              </div>

                                                              <?php
                                                                  if($pipe_modules ?? [])
                                                                  {
                                                                      foreach($pipe_modules AS $mk=>$mv)
                                                                      {
                                                                          ?>
                                                                          <div id="provider_<?php echo $did; ?>_content_<?php echo $mk; ?>" style="<?php echo $provider == $mk ? '' : 'display: none;'; ?>" class="pipe-provider-<?php echo $mk; ?> pipe-providers">

                                                                              <?php
                                                                                  $form_file = MODULE_DIR."Pipe".DS.$mk.DS."views".DS."credentialsForm.php";
                                                                                  if(file_exists($form_file))
                                                                                      include $form_file;
                                                                                  else
                                                                                      echo '<div class="red-info"><div class="padding20"><i class="fa fas fa-exclamation-circle"></i><p>Not found credentials form</p></div></div>';
                                                                              ?>

                                                                          </div>
                                                                          <?php
                                                                      }
                                                                  }
                                                              ?>


                                                          </div>
                                                      </div>

                                                  </div>
                                              </div>
                                              <?php
                                          }
                                      }
                                  ?>

                              </div>
                          </div>



                          <div class="clear"></div>
                          

                          <div class="yuzde30 guncellebtn" style="float:right;margin-top:0;">
                              <a class="gonderbtn yesilbtn" href="javascript:void 0;" id="pipeForm_submit"><?php echo ___("needs/button-save"); ?></a>
                          </div>

                          <div class="clear"></div>


                      </form>

                      <script type="text/javascript">
                          $(document).ready(function(){
                              var tabD = _GET("department");
                              if (tabD != '' && tabD != undefined) {
                                  $("#tab-department .tablinks[data-tab='" + tabD + "']").click();
                                  $('html, body').animate({
                                      scrollTop: $("#tab-department").offset().top
                                  }, 1000);
                              } else {
                                  $("#tab-department .tablinks:eq(0)").addClass("active");
                                  $("#tab-department .tabcontent:eq(0)").css("display", "block");
                              }

                              $("#pipeForm_submit").click(function(){
                                  $("#pipeForm input[name='operation']").val('manage_pipe');
                                  $("#pipeForm input[name='operation_button']").val('');
                                  $("#pipeForm input[name='operation_button_did']").val('');
                                  MioAjaxElement($(this),{
                                      waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                                      progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                                      result:"pipeForm_handler",
                                  });
                              });

                          });

                          function pipeForm_handler(result){
                              if(result !== ''){
                                  var solve = getJson(result);
                                  if(solve !== false)
                                  {
                                      if(solve.status === "error"){
                                          if(solve.message !== undefined && solve.message !== '')
                                              alert_error(solve.message,{timer:5000});
                                      }
                                      else if(solve.status === "successful")
                                      {
                                          if(solve.message !== undefined && solve.message !== '')
                                              alert_success(solve.message,{timer:2500});

                                          if(solve.redirect !== undefined && solve.redirect !== '')
                                              window.location.href = solve.redirect;
                                      }
                                  }
                                  else
                                      console.log(result);
                              }
                          }

                          function buttonPipe(el,did,action)
                          {
                              let result_function = $(el).data("result-function");
                              if(!result_function) result_function = "pipeForm_handler";

                              $("#pipeForm input[name='operation_button']").val(action);
                              $("#pipeForm input[name=operation_button_did]").val(did);

                              MioAjaxElement($(el),{
                                  waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                                  progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                                  result:result_function,
                              });
                          }
                      </script>


                  </div>


                </div>

                <div id="tab-statuses" class="tabcontent">
                    <div class="adminpagecon">

                        <div class="blue-info">
                            <div class="padding20">
                                <i class="fa fa-info-circle"></i>
                                <p><?php echo __("admin/tickets/statuses-text1"); ?></p>
                            </div>
                        </div>

                        <div class="clear"></div>

                        <div class="change-lang-buttons" data-type="name">
                            <?php
                                foreach($lang_list AS $row){
                                    ?>
                                    <a class="lbtn"<?php echo $local_l == $row["key"] ? ' id="lang-active"' : ''; ?> href="javascript:void 0;" data-key="<?php echo $row["key"]; ?>"><?php echo strtoupper($row["key"]); ?></a>
                                    <?php
                                }
                            ?>
                        </div>



                        <form action="<?php echo $links["controller"]; ?>" method="post" id="statusesForm">
                            <input type="hidden" name="operation" value="save_statuses">
                            <input type="hidden" name="delete_statuses" value="">

                            <div class="pricing-thead">
                                <div class="pricing-table-item"><?php echo __("admin/tickets/statuses-text2"); ?></div>
                                <div class="pricing-table-item"><?php echo __("admin/tickets/statuses-text3"); ?></div>
                                <div class="pricing-table-item"><?php echo __("admin/tickets/statuses-text4"); ?></div>
                            </div>
                            <div class="clear"></div>

                            <ul id="statuses-list">
                                <?php
                                    if(isset($statuses) && $statuses)
                                    {
                                        foreach($statuses AS $s)
                                        {
                                            ?>
                                            <li>
                                                <input type="hidden" name="statuses[id][]" value="<?php echo $s["id"]; ?>">

                                                <div class="pricing-table-item">
                                                    <select name="statuses[type][]">
                                                        <?php
                                                            if(isset($situations))
                                                            {
                                                                foreach($situations AS $k => $v)
                                                                {
                                                                    ?>
                                                                    <option<?php echo $s["type"] == $k ? ' selected' : ''; ?> value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                                                    <?php
                                                                }
                                                            }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="pricing-table-item">

                                                    <?php
                                                        foreach($lang_list AS $row){
                                                            $l_k = $row["key"];
                                                            ?>
                                                            <input style="<?php echo $l_k == $local_l ? '' : 'display:none;';?>" name="statuses[name][<?php echo $l_k; ?>][]" type="text" placeholder="" class="name-values name-value-<?php echo $l_k; ?>" value="<?php echo $s["languages"][$l_k]["name"] ?? ''; ?>">
                                                            <?php
                                                        }
                                                    ?>
                                                </div>
                                                <div class="pricing-table-item"><input name="statuses[color][]" type="text" placeholder="" class="select-color" value="<?php echo $s["color"]; ?>"></div>

                                                <div class="pricing-table-item">
                                                    <a href="javascript:void(0);" class="sbtn status-bearer"><i class="fa fa-arrows-alt"></i></a>
                                                    <a href="javascript:void(0);" class="red sbtn status-delete" data-id="<?php echo $s["id"]; ?>"><i class="fa fa-trash"></i></a>
                                                </div>

                                                <div class="clear"></div>

                                            </li>
                                            <?php
                                        }
                                    }
                                ?>
                            </ul>

                            <div class="clear"></div>

                            <a href="javascript:addStatus();void 0;" class="lbtn">+ <?php echo __("admin/tickets/statuses-text5"); ?></a>

                            <div class="clear"></div>


                            <div class="yuzde30 guncellebtn" style="float:right;">
                                <a class="gonderbtn yesilbtn" href="javascript:void 0;" id="statusesForm_submit"><?php echo ___("needs/button-save"); ?></a>
                            </div>

                        </form>
                        <script type="text/javascript">
                            $(document).ready(function(){
                                $("#statusesForm_submit").click(function(){
                                    MioAjaxElement($(this),{
                                        waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                                        progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                                        result:"statusesForm_handler",
                                    });
                                });

                            });

                            function statusesForm_handler(result){
                                if(result != ''){
                                    var solve = getJson(result);
                                    if(solve !== false){
                                        if(solve.status == "error"){
                                            if(solve.message != undefined && solve.message != '')
                                                alert_error(solve.message,{timer:5000});
                                        }else if(solve.status == "successful")
                                        {
                                            alert_success(solve.message,{timer:2500});
                                            setTimeout(function(){
                                                window.location.href = "<?php echo $links["controller"]; ?>?tab=statuses";
                                            },3000);
                                        }
                                    }else
                                        console.log(result);
                                }
                            }
                        </script>


                        <ul id="status-item-template" style="display: none">
                            <li>
                                <div class="pricing-table-item">
                                    <select name="statuses[type][]">
                                        <?php
                                            if(isset($situations))
                                            {
                                                foreach($situations AS $k => $v)
                                                {
                                                    ?>
                                                    <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                                    <?php
                                                }
                                            }
                                        ?>
                                    </select>
                                </div>
                                <div class="pricing-table-item">

                                    <?php
                                        foreach($lang_list AS $row){
                                            $l_k = $row["key"];
                                            ?>
                                            <input style="<?php echo $l_k == $local_l ? '' : 'display:none;';?>" name="statuses[name][<?php echo $l_k; ?>][]" type="text" placeholder="" class="name-values name-value-<?php echo $l_k; ?>">
                                            <?php
                                        }
                                    ?>
                                </div>
                                <div class="pricing-table-item"><input name="statuses[color][]" type="text" placeholder="" class="select-color"></div>

                                <div class="pricing-table-item">
                                    <a href="javascript:void(0);" class="sbtn status-bearer"><i class="fa fa-arrows-alt"></i></a>
                                    <a href="javascript:void(0);" class="red sbtn status-delete"><i class="fa fa-trash"></i></a>
                                </div>

                                <div class="clear"></div>
                            </li>
                        </ul>


                    </div>
                </div>

                <div id="tab-tasks" class="tabcontent">
                    <div class="adminpagecon">

                        <div class="green-info">
                            <div class="padding20">
                                <i class="fa fa-info-circle"></i>
                                <p><?php echo __("admin/tickets/settings-tasks-info"); ?></p>
                            </div>
                        </div>

                        <a href="javascript:auto_task_modal('create');" class="lbtn green"><i class="fa fa-plus"></i> <?php echo ___("needs/button-create"); ?></a>

                        <table style="width: 100%;" id="table-auto-tasks" class="table table-striped table-borderedx table-condensed nowrap">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th data-orderable="false" align="left"><?php echo __("admin/tickets/settings-tasks-text1"); ?></th>
                                <th data-orderable="false" align="center" width="10%"></th>
                            </tr>
                            </thead>
                        </table>


                    </div>
                </div>



            </div>

            <div class="clear"></div>

        </div>
    </div>


</div>

<?php include __DIR__.DS."inc".DS."footer.php"; ?>

<div id="auto_task_manage_modal" data-izimodal-title="<?php echo ___("needs/button-create"); ?>" data-izimodal-width="1024px" style="display: none;">
    <form action="<?php echo $links["controller"]; ?>" method="post" id="manageAutoTaskForm">
        <input type="hidden" name="operation" value="cud_auto_task">
        <input type="hidden" name="type" value="create">
        <input type="hidden" name="id" value="0">

        <div class="padding20">

            <div class="formcon">
                <div class="yuzde30"><?php echo __("admin/tickets/settings-tasks-text1"); ?></div>
                <div class="yuzde70">
                    <input type="text" name="name" value="">
                </div>
            </div>


            <div class="biggroup">
                <div class="padding20">
                    <h4 class="biggrouptitle"><?php echo __("admin/tickets/settings-tasks-text2"); ?></h4>

                    <div class="clear"></div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/tickets/settings-tasks-text3"); ?></div>
                        <div class="yuzde70">
                            <select class="select2" name="departments[]" multiple>
                                <?php
                                    if($departments)
                                    {
                                        foreach($departments AS $d)
                                        {
                                            ?>
                                            <option value="<?php echo $d["id"]; ?>"><?php echo $d["name"]; ?></option>
                                            <?php
                                        }
                                    }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/tickets/settings-tasks-text4"); ?></div>
                        <div class="yuzde70">
                            <select class="select2" name="statuses[]" multiple>
                                <?php
                                    foreach($situations AS $k=>$v)
                                    {
                                        ?>
                                        <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                        <?php
                                    }

                                    if($statuses)
                                    {
                                        foreach($statuses AS $r)
                                        {
                                            ?>
                                            <option value="<?php echo $r["type"]."-".$r["id"]; ?>"><?php echo $r["languages"][$ui_lang]["name"] ?? $situations[$r["type"]]; ?></option>
                                            <?php
                                        }
                                    }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/tickets/settings-tasks-text5"); ?></div>
                        <div class="yuzde70">
                            <select class="select2" name="priorities[]" multiple>
                                <?php
                                    foreach($priorities AS $k => $v)
                                    {
                                        ?>
                                        <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                        <?php
                                    }
                                ?>
                            </select>
                        </div>
                    </div>


                    <div class="clear"></div>
                </div>
            </div>

            <div class="formcon">
                <div class="yuzde30"><?php echo __("admin/tickets/settings-tasks-text6"); ?></div>
                <div class="yuzde70">
                    <?php
                        $input = <<<HTML
<input type="text" name="delay_time" value="" style="width: 5%;text-align: center;">
HTML;
                        echo __("admin/tickets/settings-tasks-text7",['{input}' => $input]);
                    ?>
                </div>
            </div>


            <div class="biggroup">
                <div class="padding20">
                    <h4 class="biggrouptitle"><?php echo __("admin/tickets/settings-tasks-text8"); ?></h4>

                    <div class="clear"></div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/tickets/settings-tasks-text9"); ?></div>
                        <div class="yuzde70">
                            <select name="department">
                                <option value=""><?php echo __("admin/tickets/settings-tasks-text10"); ?></option>
                                <?php
                                    if($departments)
                                    {
                                        foreach($departments AS $d)
                                        {
                                            ?>
                                            <option value="<?php echo $d["id"]; ?>"><?php echo $d["name"]; ?></option>
                                            <?php
                                        }
                                    }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/tickets/settings-tasks-text11"); ?></div>
                        <div class="yuzde70">
                            <select name="status">
                                <option value=""><?php echo __("admin/tickets/settings-tasks-text10"); ?></option>
                                <?php
                                    foreach($situations AS $k=>$v)
                                    {
                                        ?>
                                        <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                        <?php
                                    }

                                    if($statuses)
                                    {
                                        foreach($statuses AS $r)
                                        {
                                            ?>
                                            <option value="<?php echo $r["type"]."-".$r["id"]; ?>"><?php echo $r["languages"][$ui_lang]["name"] ?? $situations[$r["type"]]; ?></option>
                                            <?php
                                        }
                                    }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/tickets/settings-tasks-text12"); ?></div>
                        <div class="yuzde70">
                            <select name="priority">
                                <option value=""><?php echo __("admin/tickets/settings-tasks-text10"); ?></option>
                                <?php
                                    foreach($priorities AS $k => $v)
                                    {
                                        ?>
                                        <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                        <?php
                                    }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/tickets/settings-tasks-text13"); ?></div>
                        <div class="yuzde70">
                            <select name="assign_to">
                                <option value=""><?php echo __("admin/tickets/settings-tasks-text10"); ?></option>
                                <?php
                                    if($assignable_users)
                                    {
                                        foreach($assignable_users AS $s)
                                        {
                                            ?>
                                            <option value="<?php echo $s["id"]; ?>"><?php echo $s["full_name"]; ?></option>
                                            <?php
                                        }
                                    }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/tickets/settings-tasks-text14"); ?></div>
                        <div class="yuzde70">
                            <input type="checkbox" name="mark_locked" value="1" class="checkbox-custom" id="mark_locked">
                            <label class="checkbox-custom-label" for="mark_locked"></label>
                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/tickets/settings-tasks-text15"); ?></div>
                        <div class="yuzde70">
                            <select class="select2" name="template">
                                <option value=""><?php echo ___("needs/none"); ?></option>
                                <?php
                                    foreach($templates AS $gk => $group)
                                    {
                                        if($gk != "user-tickets" && $gk != "admin-tickets") continue;
                                        ?>
                                        <optgroup label="<?php echo $group["name"]; ?>">
                                            <?php
                                                foreach($group["items"] AS $ik => $i)
                                                {
                                                    ?>
                                                    <option value="<?php echo $gk."/".$ik; ?>"><?php echo $i["name"]; ?></option>
                                                    <?php
                                                }
                                            ?>
                                        </optgroup>
                                        <?php
                                    }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/tickets/settings-tasks-text17"); ?></div>
                        <div class="yuzde70">
                            <input type="checkbox" name="repeat_action" value="1" class="checkbox-custom" id="repeat_action">
                            <label class="checkbox-custom-label" for="repeat_action"><span class="kinfo"><?php echo __("admin/tickets/settings-tasks-text18"); ?></span></label>
                        </div>
                    </div>


                    <div class="formcon">
                        <div class="yuzde30">
                            <?php echo __("admin/tickets/settings-tasks-text16"); ?>

                            <br>
                            <?php
                                foreach($lang_list AS $k=>$lang){
                                    $lkey = $lang["key"];
                                    ?>
                                    <a<?php echo $k==0 ? ' id="informations-lang-button-active"' : ''; ?> class="informations-lang-button lbtn" href="javascript:void(0);" onclick="$('.informations-lang-button').removeAttr('id'),$(this).attr('id','informations-lang-button-active'),$('.informationg-area').css('display','none'),$('#informations_area_<?php echo $lkey; ?>').css('display','block');"><?php echo strtoupper($lkey); ?></a>
                                    <?php
                                }
                            ?>


                        </div>
                        <div class="yuzde70">
                            <?php
                                foreach($lang_list AS $k=>$lang){
                                    $lkey = $lang["key"];
                                    ?>
                                        <div<?php echo $k==0 ? '' : ' style="display:none;"'; ?> rows="5" class="informationg-area" id="informations_area_<?php echo $lkey; ?>">
                                            <textarea name="reply[<?php echo $lkey; ?>]" id="reply_<?php echo $lkey; ?>" class="tinymce-1"></textarea>
                                        </div>
                                    <?php
                                }
                            ?>

                            <div class="formcon">
                                <div class="yuzde30">
                                    <?php echo __("admin/notifications/edit-user-variables"); ?>
                                    <div class="clear"></div>
                                    <span class="kinfo"><?php echo __("admin/notifications/edit-user-variables-info"); ?></span>
                                </div>
                                <div class="yuzde70" id="template-variables">
                                    <span>{FULL_NAME}</span>
                                    <span>{NAME}</span>
                                    <span>{SURNAME}</span>
                                    <span>{EMAIL}</span>
                                    <span>{PHONE}</span>
                                    <span>{SERVICE}</span>
                                    <span>{DOMAIN}</span>
                                </div>
                            </div>


                        </div>
                    </div>



                    <div class="clear"></div>
                </div>
            </div>
        </div>

        <div class="modal-foot-btn">
            <a class="lbtn green" id="manageAutoTaskForm_submit" href="javascript:void 0;"><?php echo ___("needs/button-create"); ?></a>
        </div>
    </form>

</div>


</body>
</html>