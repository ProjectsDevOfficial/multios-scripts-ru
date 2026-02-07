<?php
    if(!defined("CORE_FOLDER")) die();
    $LANG   = $module->lang;
    $CONFIG = $module->config;
    Helper::Load("Money");
    $module_name = $module->_name;
    $support_domain_import = method_exists($module,'domains');
    $support_adp    = method_exists($module,'cost_prices');
    $support_tld_import =  method_exists($module,'tlds');
?>
<script type="text/javascript">
    function <?php echo $module_name; ?>_open_tab(elem, tabName){
        var owner = "<?php echo $module_name; ?>_tab";
        $("#"+owner+" .modules-tabs-content").css("display","none");
        $("#"+owner+" .modules-tabs .modules-tab-item").removeClass("active");
        $("#"+owner+"-"+tabName).css("display","block");
        $("#"+owner+" .modules-tabs .modules-tab-item[data-tab='"+tabName+"']").addClass("active");
    }
</script>


<div id="<?php echo $module_name; ?>_import_tld" style="display: none;" data-izimodal-title="<?php echo __("admin/modules/title-import-tld"); ?>">
    <script type="text/javascript">
        $(document).ready(function(){

            $("#<?php echo $module_name; ?>_import_tld_submit").on("click",function(){
                var request = MioAjax({
                    button_element:this,
                    action:"<?php echo Controllers::$init->getData("links")["controller"]; ?>",
                    method:"POST",
                    waiting_text:waiting_text,
                    progress_text:progress_text,
                    data:{
                        operation: "module_controller",
                        module: "<?php echo $module_name; ?>",
                        controller: "import-tld",
                    }
                },true,true);

                request.done(function(result){
                    if(result != ''){
                        var solve = getJson(result);
                        if(solve !== false){
                            if(solve.status == "error"){
                                if(solve.for != undefined && solve.for != ''){
                                    $("#detailForm "+solve.for).focus();
                                    $("#detailForm "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                    $("#detailForm "+solve.for).change(function(){
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
                });

            });

        });
    </script>
    <div class="padding20">

        <p style="text-align: center; font-size: 17px;">
            <?php echo __("admin/modules/desc-import-tld-2"); ?>
        </p>
    </div>
    <div class="modal-foot-btn">
        <a class="lbtn green" href="javascript:void 0;" id="<?php echo $module_name; ?>_import_tld_submit"><i class="fa fa-check" aria-hidden="true"></i> <?php echo ___("needs/ok"); ?></a>
    </div>
</div>

<div id="<?php echo $module_name; ?>_tab">
    <ul class="modules-tabs">
        <li><a href="javascript:<?php echo $module_name; ?>_open_tab(this,'detail');" data-tab="detail" class="modules-tab-item active"><?php echo __("admin/modules/tab-detail"); ?></a></li>
        <?php if($support_domain_import): ?>
            <li><a href="javascript:<?php echo $module_name; ?>_open_tab(this,'import');" data-tab="import" class="modules-tab-item"><?php echo __("admin/modules/tab-import"); ?></a></li>
        <?php endif; ?>
    </ul>

    <div id="<?php echo $module_name; ?>_tab-detail" class="modules-tabs-content" style="display: block">

        <form action="<?php echo Controllers::$init->getData("links")["controller"]; ?>" method="post" id="<?php echo $module_name; ?>Settings">
            <input type="hidden" name="operation" value="module_controller">
            <input type="hidden" name="module" value="<?php echo $module_name; ?>">
            <input type="hidden" name="controller" value="settings">

            <?php
                if(isset($LANG["description"]) && strlen($LANG["description"]) > 1)
                {
                    ?>
                    <div class="blue-info" style="margin-bottom:20px;">
                        <div class="padding15">
                            <i class="fa fa-info-circle" aria-hidden="true"></i>
                            <p><?php echo $LANG["description"]; ?></p>
                        </div>
                    </div>
                    <?php
                }
                $module->config_fields_output($module->config_fields($CONFIG["settings"] ?? []));
            ?>

            <?php if(method_exists($module,'modifyPrivacyProtection')): ?>
            <div class="formcon">
                <div class="yuzde30"><?php echo __("admin/modules/title-WHiddenAmount"); ?></div>
                <div class="yuzde70">
                    <input type="text" name="fields[whidden-amount]" value="<?php echo Money::formatter($CONFIG["settings"]["whidden-amount"] ?? 0,$CONFIG["settings"]["whidden-currency"] ?? 4); ?>" style="width: 100px;" onkeypress='return event.charCode==46  || event.charCode>= 48 &&event.charCode<= 57'>
                    <select name="fields[whidden-currency]" style="width: 150px;">
                        <?php
                            foreach(Money::getCurrencies($CONFIG["settings"]["whidden-currency"] ?? 4) AS $currency){
                                ?>
                                <option<?php echo $currency["id"] == ($CONFIG["settings"]["whidden-currency"] ?? 4) ? ' selected' : ''; ?> value="<?php echo $currency["id"]; ?>"><?php echo $currency["name"]." (".$currency["code"].")"; ?></option>
                                <?php
                            }
                        ?>
                    </select>
                    <span class="kinfo"><?php echo __("admin/modules/desc-WHiddenAmount"); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <?php if($support_adp): ?>
                <div class="formcon">
                    <div class="yuzde30"><?php echo __("admin/modules/title-adp"); ?></div>
                    <div class="yuzde70">
                        <input<?php echo ($CONFIG["settings"]["adp"] ?? false) ? ' checked' : ''; ?> type="checkbox" name="fields[adp]" value="1" id="<?php echo $module_name; ?>_adp" class="checkbox-custom">
                        <label class="checkbox-custom-label" for="<?php echo $module_name; ?>_adp">
                            <span class="kinfo"><?php echo __("admin/modules/desc-adp"); ?></span>
                        </label>
                    </div>
                </div>
            <?php endif; ?>


            <?php if($support_adp || $support_tld_import): ?>
                <div class="formcon" id="cost_currency_wrap">
                    <div class="yuzde30"><?php echo __("admin/modules/title-cost-currency"); ?></div>
                    <div class="yuzde70">
                        <select name="fields[cost-currency]" style="width:200px;">
                            <?php
                                foreach(Money::getCurrencies($CONFIG["settings"]["cost-currency"] ?? 4) AS $currency){
                                    ?>
                                    <option<?php echo $currency["id"] == ($CONFIG["settings"]["cost-currency"] ?? 4) ? ' selected' : ''; ?> value="<?php echo $currency["id"]; ?>"><?php echo $currency["name"]." (".$currency["code"].")"; ?></option>
                                    <?php
                                }
                            ?>
                        </select>
                    </div>
                </div>
            <?php endif; ?>

            <?php if($support_tld_import): ?>
                <div class="formcon">
                    <div class="yuzde30"><?php echo __("admin/modules/title-import-tld"); ?></div>
                    <div class="yuzde70">
                        <a class="lbtn" href="javascript:open_modal('<?php echo $module_name; ?>_import_tld');void 0;"><?php echo __("admin/modules/import-tld-button"); ?></a>
                        <div class="clear"></div>
                        <span class="kinfo"><?php echo __("admin/modules/import-tld-1"); ?></span>
                    </div>
                </div>
            <?php endif; ?>


            <div class="clear"></div>
            <br>

            <?php if(method_exists($module,'testConnection')): ?>
                <div style="float:left;" class="guncellebtn yuzde30"><a id="<?php echo $module_name; ?>_testConnect" href="javascript:void(0);" class="lbtn"><i class="fa fa-plug" aria-hidden="true"></i> <?php echo __("admin/modules/test-button"); ?></a></div>
            <?php endif; ?>

            <div style="float:right;" class="guncellebtn yuzde30"><a id="<?php echo $module_name; ?>_submit" href="javascript:void(0);" class="yesilbtn gonderbtn"><?php echo ___("needs/button-save"); ?></a></div>

        </form>
        <script type="text/javascript">
            $(document).ready(function(){
                $("#<?php echo $module_name; ?>_testConnect").click(function(){
                    $("#<?php echo $module_name; ?>Settings input[name=controller]").val("test_connection");
                    MioAjaxElement($(this),{
                        waiting_text:waiting_text,
                        progress_text:progress_text,
                        result:"<?php echo $module_name; ?>_handler",
                    });
                });

                $("#<?php echo $module_name; ?>_submit").click(function(){
                    $("#<?php echo $module_name; ?>Settings input[name=controller]").val("settings");
                    MioAjaxElement($(this),{
                        waiting_text:waiting_text,
                        progress_text:progress_text,
                        result:"<?php echo $module_name; ?>_handler",
                    });
                });
            });

            function <?php echo $module_name; ?>_handler(result){
                if(result != ''){
                    var solve = getJson(result);
                    if(solve !== false){
                        if(solve.status == "error"){
                            if(solve.for != undefined && solve.for != ''){
                                $("#<?php echo $module_name; ?>Settings "+solve.for).focus();
                                $("#<?php echo $module_name; ?>Settings "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                $("#<?php echo $module_name; ?>Settings "+solve.for).change(function(){
                                    $(this).removeAttr("style");
                                });
                            }
                            if(solve.message != undefined && solve.message != '')
                                alert_error(solve.message,{timer:5000});
                        }else if(solve.status == "successful")
                            alert_success(solve.message,{timer:2500});
                    }else
                        console.log(result);
                }
            }
        </script>

    </div>

    <div id="<?php echo $module_name; ?>_tab-import" class="modules-tabs-content" style="display: none;">
        <div class="blue-info">
            <div class="padding15">
                <?php echo $LANG["import-note"] ?? __("admin/modules/import-note"); ?>
            </div>
        </div>

        <script type="text/javascript">

            function <?php echo $module_name; ?>_import_handler(result){
                if(result != ''){
                    var solve = getJson(result);
                    if(solve !== false){
                        if(solve.status == "error"){
                            if(solve.for != undefined && solve.for != ''){
                                $("#<?php echo $module_name; ?>Import "+solve.for).focus();
                                $("#<?php echo $module_name; ?>Import "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                $("#<?php echo $module_name; ?>Import "+solve.for).change(function(){
                                    $(this).removeAttr("style");
                                });
                            }
                            if(solve.message != undefined && solve.message != '')
                                alert_error(solve.message,{timer:5000});
                        }else if(solve.status == "successful"){
                            alert_success(solve.message,{timer:2500});
                            setTimeout(function(){
                                window.location.href = window.location.href;
                            },2500);
                        }
                    }else
                        console.log(result);
                }
            }
            $(document).ready(function(){

                $("#<?php echo $module_name; ?>_import_submit").click(function(){
                    MioAjaxElement($(this),{
                        waiting_text:waiting_text,
                        progress_text:progress_text,
                        result:"<?php echo $module_name; ?>_import_handler",
                    });
                });

                $('#<?php echo $module_name; ?>_list_domains').DataTable({
                    "columnDefs": [
                        {
                            "targets": [0],
                            "visible":false,
                        },
                    ],
                    "lengthMenu": [
                        [10, 25, 50, -1], [10, 25, 50, "<?php echo ___("needs/allOf"); ?>"]
                    ],
                    responsive: true,
                    "language":{"url":"<?php echo APP_URI; ?>/<?php echo ___("package/code"); ?>/datatable/lang.json"}
                });

                $(".select-user").select2({
                    placeholder: "<?php echo __("admin/invoices/create-select-user"); ?>",
                    ajax: {
                        url: '<?php echo Controllers::$init->AdminCRLink("orders"); ?>?operation=user-list.json',
                        dataType: 'json',
                        data: function (params) {
                            var query = {
                                search: params.term,
                                type: 'public',
                            };
                            return query;
                        }
                    }
                });

                $(".select2-element").select2({
                    placeholder: "<?php echo ___("needs/select-your"); ?>",
                });

            });
        </script>
        <form action="<?php echo Controllers::$init->getData("links")["controller"]; ?>" method="post" id="<?php echo $module_name; ?>Import">
            <input type="hidden" name="operation" value="module_controller">
            <input type="hidden" name="module" value="<?php echo $module_name; ?>">
            <input type="hidden" name="controller" value="import">

            <?php
                $list   = $support_domain_import ? $module->domains() : [];
                if($module->error)
                {
                    ?>
                    <div class="red-info">
                        <div class="padding20">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p><?php echo $module->error; ?></p>
                        </div>
                    </div>
                    <?php
                }
            ?>

            <table width="100%" id="<?php echo $module_name; ?>_list_domains" class="table table-striped table-borderedx table-condensed nowrap">
                <thead style="background:#ebebeb;">
                <tr>
                    <th align="center" data-orderable="false">#</th>
                    <th align="left" data-orderable="false"><?php echo __("admin/products/hosting-shared-servers-import-accounts-domain"); ?></th>
                    <th align="center" data-orderable="false"><?php echo __("admin/products/hosting-shared-servers-import-accounts-user"); ?></th>
                    <th align="center" data-orderable="false"><?php echo __("admin/products/hosting-shared-servers-import-accounts-start"); ?></th>
                    <th align="center" data-orderable="false"><?php echo __("admin/products/hosting-shared-servers-import-accounts-end"); ?></th>
                </tr>
                </thead>
                <tbody align="center" style="border-top:none;">
                <?php
                    $i = 0;
                    if($list)
                    {
                        foreach($list AS $row){
                            $i++;
                            ?>
                            <tr<?php echo isset($row["order_id"]) && $row["order_id"] ? ' style="background: #c2edc2;opacity: 0.7;    filter: alpha(opacity=70);"' : ''; ?>>
                                <td align="left"><?php echo $i; ?></td>
                                <td align="left"><?php echo $row["domain"]; ?></td>
                                <td align="center">
                                    <?php
                                        if(isset($row["user_data"]) && $row["user_data"]){
                                            $user_link = Controllers::$init->AdminCRLink("users-2",['detail',$row["user_data"]["id"]]);
                                            $user_name           = Utility::short_text($row["user_data"]["full_name"],0,21,true);
                                            $user_company_name   = Utility::short_text($row["user_data"]["company_name"],0,21,true);

                                            $user_detail         = '<a href="'.$user_link.'" target="_blank"><strong title="'.$row["user_data"]["full_name"].'">'.$user_name.'</strong></a><br><span class="mobcomname" title="'.$row["user_data"]["company_name"].'">'.$user_company_name.'</span>';
                                            echo $user_detail;
                                        }else{
                                            ?>
                                            <select class="width200 select-user" name="data[<?php echo $row["domain"]; ?>][user_id]"></select>
                                            <?php
                                        }
                                    ?>
                                </td>
                                <td align="center">
                                    <?php echo $row["creation_date"] ? DateManager::format("d/m/Y",$row["creation_date"]) : '-'; ?>
                                </td>
                                <td align="center">
                                    <?php echo $row["end_date"] ? DateManager::format("d/m/Y",$row["end_date"]) : '-'; ?>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                ?>
                </tbody>
            </table>

            <div class="clear"></div>
            <div class="guncellebtn yuzde20" style="float: right;">
                <a href="javascript:void(0);" id="<?php echo $module_name; ?>_import_submit" class="gonderbtn mavibtn"><?php echo __("admin/modules/import-button"); ?></a>
            </div>
        </form>

    </div>
</div>