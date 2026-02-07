<?php
    $bring      = Filter::init("GET/bring");

    $ttl_times      = [
        60          => '1 min',
        120          => '2 min',
        300          => '5 min',
        600          => '10 min',
        900          => '15 min',
        1800         => '30 min',
        3600         => '1 hr',
        7200         => '2 hr',
        18000        => '5 hr',
        43200        => '12 hr',
        86400        => '1 day',
    ];


    $allow_dns_cns          = (isset($module_con) && method_exists($module_con,'CNSList'));
    $allow_dns_records      = (isset($module_con) && method_exists($module_con,'getDnsRecords'));
    $allow_dns_sec_records  = (isset($module_con) && method_exists($module_con,'getDnsSecRecords'));
    $allow_forwarding_dmn   = (isset($module_con) && method_exists($module_con,'getForwardingDomain'));
    $allow_forwarding_eml   = (isset($module_con) && method_exists($module_con,'getEmailForwards'));

    if($bring)
    {
        $options = $order["options"];

        if($bring == "forwarding-domain")
        {
            if(!$allow_forwarding_dmn) return false;

            $getForwarding      = $module_con->getForwardingDomain();

            if(!$getForwarding)
            {
                ?>
                <div class="red-info">
                    <div class="padding20">
                        <i class="fa fa-warning"></i>
                        <p><?php echo $module_con->error; ?></p>
                    </div>
                </div>
                <?php
            }
            else
            {
                if($getForwarding["status"])
                {
                    ?>
                    <div class="domainforwarding">

                        <div class="formcon">
                            <div class="yuzde30"><?php echo __("website/account_products/domain-forwarding-tx9"); ?></div>
                            <div class="yuzde70">
                                <select disabled style="width: 100px;" id="forward_protocol">
                                    <option<?php echo $getForwarding["protocol"] == "http" ? ' selected' : ''; ?>>http://</option>
                                    <option<?php echo $getForwarding["protocol"] == "https" ? ' selected' : ''; ?>>https://</option>
                                </select>
                                <input disabled style="width:78%;" id="forward_domain" value="<?php echo $getForwarding["domain"]; ?>" type="text" placeholder="<?php echo __("website/account_products/domain-forwarding-tx4"); ?>"></div>
                        </div>
                        <div class="formcon">
                            <div class="yuzde30"><?php echo __("website/account_products/domain-forwarding-tx10"); ?></div>
                            <div class="yuzde70">
                                <div class="yuzde75">

                                    <input<?php echo $getForwarding["method"] == 301 ? ' checked' : ''; ?> disabled id="method_301" class="radio-custom" name="method" value="301" type="radio">
                                    <label for="method_301" class="radio-custom-label" style="margin-right: 28px;"><span class="checktext">301 (<?php echo __("website/account_products/domain-forwarding-tx11"); ?>)</span></label>

                                    <input<?php echo $getForwarding["method"] == 302 ? ' checked' : ''; ?> disabled id="method_302" class="radio-custom" name="method" value="302" type="radio">
                                    <label for="method_302" class="radio-custom-label"><span class="checktext">302 (<?php echo __("website/account_products/domain-forwarding-tx12"); ?>)</span></label>

                                </div>
                            </div>
                        </div>


                        <div class="line"></div>
                        <a style="width:240px;float:right;font-weight: 600;" href="javascript:void(0);" onclick="cancel_forward_domain(this);" class="yesilbtn gonderbtn"><?php echo __("website/account_products/domain-forwarding-tx6"); ?></a>
                    </div>
                    <?php
                }
                else
                {
                    ?>
                    <div class="domainforwarding">

                        <div class="formcon">
                            <div class="yuzde30"><?php echo __("website/account_products/domain-forwarding-tx9"); ?></div>
                            <div class="yuzde70">
                                <select style="width: 100px;" id="forward_protocol">
                                    <option>http://</option>
                                    <option>https://</option>
                                </select>
                                <input style="width:78%;" id="forward_domain" value="" type="text" placeholder="<?php echo __("website/account_products/domain-forwarding-tx4"); ?>"></div>
                        </div>
                        <div class="formcon">
                            <div class="yuzde30"><?php echo __("website/account_products/domain-forwarding-tx10"); ?></div>
                            <div class="yuzde70">
                                <div class="yuzde75">

                                    <input checked id="method_301" class="radio-custom" name="method" value="301" type="radio">
                                    <label for="method_301" class="radio-custom-label" style="margin-right: 28px;"><span class="checktext">301 (<?php echo __("website/account_products/domain-forwarding-tx11"); ?>)</span></label>

                                    <input id="method_302" class="radio-custom" name="method" value="302" type="radio">
                                    <label for="method_302" class="radio-custom-label"><span class="checktext">302 (<?php echo __("website/account_products/domain-forwarding-tx12"); ?>)</span></label>

                                </div>
                            </div>
                        </div>


                        <div class="line"></div>
                        <a style="width:240px;float:right;font-weight: 600;" href="javascript:void(0);" onclick="set_forward_domain(this);" class="yesilbtn gonderbtn"><?php echo __("website/account_products/domain-forwarding-tx5"); ?></a>
                        <div class="clear"></div>
                    </div>
                    <?php
                }
            }

            exit();
        }
        elseif($bring == "cns-list")
        {
            if(!$allow_dns_cns) return false;
            $cns_list = $module_con->CNSList($options);
            if(is_array($cns_list) && $cns_list){

                foreach($cns_list AS $id=>$row){
                    ?>
                    <form action="<?php echo $links["controller"]; ?>" method="post" id="ModifyCns<?php echo $id; ?>">
                        <input type="hidden" name="operation" value="domain_modify_cns">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">

                        <div class="yuzde33"><input name="ns" type="text" class="" placeholder="ns1.<?php echo $order["options"]["domain"] ?? $order["name"]; ?>" value="<?php echo $row["ns"]; ?>"></div>
                        <div class="yuzde33"> <input name="ip" type="text" class="" placeholder="192.168.1.1" value="<?php echo $row["ip"]; ?>"></div>
                        <div class="yuzde33">
                            <a href="javascript:void(0);" class="sbtn" onclick="editCnsBtn(this);"><i class="fa fa-pencil-square-o" aria-hidden="true"></i></a>
                            <a href="javascript:void(0);" class="sbtn" onclick="if(delete_confirm()) deleteCnsBtn(this,<?php echo $id; ?>);"><i class="fa fa-trash-o" aria-hidden="true"></i></a>
                        </div>
                    </form>
                    <?php
                }
            }
            else
            {
                ?><div align="center" style="margin-top:20px;"><?php echo __("website/account_products/none-cns"); ?></div><?php
            }

            exit();
        }
        elseif($bring == "dns-records")
        {
            if(!$allow_dns_records) return false;

            $getRecords = $module_con->getDnsRecords();
            if($getRecords)
            {
                foreach($getRecords AS $k => $r)
                {
                    $ttl_text   = $r["ttl"]." sec";
                    $minute      = round($r["ttl"] / 60);
                    $hour        = round($minute / 60);

                    if($hour > 0) $ttl_text = $hour.' hr';
                    elseif($minute > 0) $ttl_text = $minute.' min';

                    ?>
                    <tr id="DnsRecord_<?php echo $k; ?>">
                        <input type="hidden" name="type" value="<?php echo $r["type"]; ?>">
                        <input type="hidden" name="identity" value="<?php echo $r["identity"]; ?>">
                        <input type="hidden" name="name" value="<?php echo $r["name"]; ?>">
                        <input type="hidden" name="value" value="<?php echo htmlentities($r["value"],ENT_QUOTES); ?>">
                        <td align="left" class="dns-record-type"><?php echo $r["type"]; ?></td>
                        <td align="left" class="dns-record-name">
                            <div class="edit-wrap" style="display: none"><input type="text" value=""></div>
                            <div class="show-wrap"><?php echo $r["name"]; ?></div>
                        </td>
                        <td align="left" class="dns-record-value">
                            <div class="edit-wrap" style="display: none;"><input type="text" value=""></div>
                            <div class="show-wrap"><?php echo $r["value"]; ?></div>
                        </td>
                        <td align="center" class="dns-record-ttl">
                            <div class="edit-wrap-ttl" style="width:80px; display: none;">
                                <select>
                                    <option value="">Auto</option>
                                    <?php
                                        if($ttl_times)
                                        {
                                            foreach($ttl_times AS $ttl_k => $ttl_v)
                                            {
                                                ?>
                                                <option<?php echo $ttl_text == $ttl_v ? ' selected' : ''; ?> value="<?php echo $ttl_k; ?>"><?php echo $ttl_v; ?></option>
                                                <?php
                                            }
                                        }
                                    ?>
                                </select>
                            </div>

                            <?php if($r["type"] == "MX"): ?>
                                <div class="edit-wrap-priority" style="width: 80px;display: none;">
                                    <input type="number" name="priority" value="<?php echo $r["priority"]; ?>">
                                </div>
                            <?php endif; ?>

                            <div class="show-wrap-ttl">
                                <?php
                                    echo $ttl_text;
                                ?>
                            </div>
                            <?php if($r["type"] == "MX"): ?>
                                <div class="show-wrap-priority"> - Priority <?php echo $r["priority"]; ?></div>
                            <?php endif; ?>
                        </td>
                        <td align="center">
                            <div class="edit-content" style="display: none;">
                                <a data-tooltip="<?php echo ___("needs/button-save"); ?>" href="javascript:void 0;" onclick="saveDnsRecord(<?php echo $k; ?>,this);" class="sbtn green"><i class="fa fa-check"></i></a>
                                <a data-tooltip="<?php echo __("website/account_products/preview-turn-back"); ?>" href="javascript:void 0;" onclick="cancelDnsRecord(<?php echo $k; ?>);" class="sbtn"><i class="fa fa-reply"></i></a>
                            </div>
                            <div class="no-edit-content">
                                <a data-tooltip="<?php echo ___("needs/button-edit"); ?>" href="javascript:void 0;" onclick="editDnsRecord(<?php echo $k; ?>);" class="sbtn"><i class="fa fa-pencil"></i></a>
                                <a data-tooltip="<?php echo ___("needs/button-delete"); ?>" href="javascript:void 0;" onclick="removeDnsRecord(<?php echo $k; ?>,this);" class="sbtn red"><i class="fa fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php
                }
            }
            else
            {
                ?>
                <tr style="background: none;box-shadow: none;">
                    <td colspan="5" style="box-shadow: none;">
                        <div align="center"><?php echo __("website/account_products/domain-dns-records-6"); ?></div>
                    </td>
                </tr>
                <?php
            }

            exit();
        }
        elseif($bring == "dns-sec-records")
        {
            if(!$allow_dns_sec_records) return false;

            $getRecords = $module_con->getDnsSecRecords();
            if($getRecords)
            {
                foreach($getRecords AS $k => $r)
                {
                    ?>
                    <tr id="DnsRecord_<?php echo $k; ?>">
                        <input type="hidden" name="identity" value="<?php echo $r["identity"]; ?>">
                        <input type="hidden" name="digest" value="<?php echo $r["digest"]; ?>">
                        <input type="hidden" name="key_tag" value="<?php echo $r["key_tag"]; ?>">
                        <input type="hidden" name="digest_type" value="<?php echo $r["digest_type"]; ?>">
                        <input type="hidden" name="algorithm" value="<?php echo $r["algorithm"]; ?>">
                        <td align="left" class="dns-sec-record-digest"><?php echo $r["digest"]; ?></td>
                        <td align="left" class="dns-sec-record-key_tag"><?php echo $r["key_tag"]; ?></td>
                        <td align="left" class="dns-sec-record-digest-type"><?php echo $module_con->config["settings"]["dns-digest-types"][$r["digest_type"]] ?? $r["digest_type"]; ?></td>
                        <td align="left" class="dns-sec-record-algorithm"><?php echo $module_con->config["settings"]["dns-algorithms"][$r["algorithm"]] ?? $r["algorithm"]; ?></td>
                        <td align="center">
                            <a data-tooltip="<?php echo ___("needs/button-delete"); ?>" href="javascript:void 0;" onclick="removeDnsSecRecord(<?php echo $k; ?>,this);" class="sbtn red"><i class="fa fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php
                }
            }
            else
            {
                ?>
                <tr style="background: none;box-shadow: none;">
                    <td colspan="5" style="box-shadow: none;">
                        <div align="center"><?php echo __("website/account_products/domain-dns-records-6"); ?></div>
                    </td>
                </tr>
                <?php
            }

            exit();
        }
        elseif($bring == "email-forwards")
        {
            if(!$allow_forwarding_eml) return false;

            $getRecords = $module_con->getEmailForwards();
            if($getRecords)
            {
                foreach($getRecords AS $k => $r)
                {
                    ?>
                    <tr id="EmailForward_<?php echo $k; ?>">
                        <input type="hidden" name="identity" value="<?php echo $r["identity"] ?? ''; ?>">
                        <input type="hidden" name="prefix" value="<?php echo $r["prefix"]; ?>">
                        <input type="hidden" name="target" value="<?php echo $r["target"]; ?>">
                        <td align="left" class="email-forward-prefix"><?php echo $r["prefix"]; ?></td>
                        <td align="left">
                            <i class="fas fa-long-arrow-alt-right" style="font-size: 30px;"></i>
                        </td>
                        <td align="left" class="email-forward-target">
                            <div class="edit-wrap" style="display: none"><input type="text" value="" placeholder="email@example.com"></div>
                            <div class="show-wrap"><?php echo $r["target"]; ?></div>
                        </td>
                        <td align="center">
                            <div class="edit-content" style="display: none;">
                                <a data-tooltip="<?php echo ___("needs/button-save"); ?>" href="javascript:void 0;" onclick="saveEmailForward(<?php echo $k; ?>,this);" class="sbtn green"><i class="fa fa-check"></i></a>
                                <a data-tooltip="<?php echo __("website/account_products/preview-turn-back"); ?>" href="javascript:void 0;" onclick="cancelEmailForward(<?php echo $k; ?>);" class="sbtn"><i class="fa fa-reply"></i></a>
                            </div>
                            <div class="no-edit-content">
                                <a data-tooltip="<?php echo ___("needs/button-edit"); ?>" href="javascript:void 0;" onclick="editEmailForward(<?php echo $k; ?>);" class="sbtn"><i class="fa fa-pencil"></i></a>
                                <a data-tooltip="<?php echo ___("needs/button-delete"); ?>" href="javascript:void 0;" onclick="removeEmailForward(<?php echo $k; ?>,this);" class="sbtn red"><i class="fa fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php
                }
            }
            else
            {
                ?>
                <tr style="background: none;box-shadow: none;">
                    <td colspan="3" style="box-shadow: none;">
                        <div align="center"><?php echo __("website/account_products/domain-forwarding-tx19"); ?></div>
                    </td>
                </tr>
                <?php
            }

            exit();
        }
    }

?>
<!DOCTYPE html>
<html>
<head>
    <?php
        $options    = $order["options"];
        Utility::sksort($lang_list,"local");
        $plugins    = ['jquery-ui','select2','voucher_codes','dataTables'];
        include __DIR__.DS."inc".DS."head.php";

        $cancellation_request = false;

        if(isset($pending_events) && $pending_events)
        {
            foreach($pending_events AS $k=>$event)
            {
                if($event['name'] == "cancelled-product-request")
                {
                    $cancellation_request = $event;
                    unset($pending_events[$k]);
                }
            }
        }

        $pendingTransferwthApi = isset($pending_events[0]["name"]) && $pending_events[0]["name"] == "transfer-request-to-us-with-api" && $order["status"] == "inprocess" && $order["module"] != "none";

        if(isset($options["verification_operator_docs"]) && $options["verification_operator_docs"])
        {
            $doc_keys           = array_keys($options["verification_operator_docs"]);
            $doc_last_id        = current($doc_keys);
        }

    ?>

    <script type="text/javascript">
        var doc_last_id = <?php echo $doc_last_id ?? "-1"; ?>;
        $(document).ready(function(){

            var tab = _GET("content");
            if (tab != '' && tab != undefined) {
                $("#tab-content .tablinks[data-tab='" + tab + "']").click();
            } else {
                $("#tab-content .tablinks:eq(0)").addClass("active");
                $("#tab-content .tabcontent:eq(0)").css("display", "block");
            }

            $("#transferUser").select2({
                placeholder: "<?php echo __("admin/orders/detail-transfer-to-another-user-select"); ?>",
                ajax: {
                    url: '<?php echo $links["select-users.json"]; ?>',
                    dataType: 'json',
                    data: function (params) {
                        var query = {
                            search: params.term,
                            type: 'public'
                        }
                        return query;
                    }
                }
            });

            $("#linkedProduct").select2({
                placeholder: "<?php echo ___("needs/none"); ?>",
                ajax: {
                    url: '<?php echo $links["controller"]; ?>?operation=select-linked-products.json',
                    dataType: 'json',
                    data: function (params) {
                        var query = {
                            search: params.term,
                            type: 'public',
                            none: 'true',
                        }
                        return query;
                    }
                }
            });

            $("#detailForm_submit").on("click",function(){
                MioAjaxElement($(this),{
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                    result:"detailForm_handler",
                });
            });

            <?php if(!$privOperation): ?>
            $("#detailForm input,#detailForm select,textarea").attr("disabled",true);
            <?php endif; ?>

        });

        function detailForm_handler(result){
            $("#detailForm input[name=from]").val("detail");
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
        }

        function applyOperation(type){
            $("#content-detail").addClass("tab-blur-content");
            $("#operation-loading").fadeIn(500,function(){
            });

            var request = MioAjax({
                action: "<?php echo $links["list"]; ?>",
                method: "POST",
                data: {operation:"apply_operation",from:"detail",type:type,id:<?php echo $order["id"]; ?>}
            },true,true);

            request.done(function(result){

                $("#operation-loading").fadeOut(500,function(){
                    $("#content-detail").removeClass("tab-blur-content");
                });

                if(result){
                    if(result != ''){
                        var solve = getJson(result);
                        if(solve !== false){
                            if(solve.status == "error"){
                                if(solve.message != undefined && solve.message != ''){

                                    alert_error(solve.message,{timer:5000});

                                    if(solve.for != undefined && solve.for == "status"){
                                        $("#statusMsg").css("display","block");
                                        $("#statusMsg .statusMsg_text").html(solve.message);
                                    }
                                }

                            }else if(solve.status == "successful"){
                                $("#statusMsg").css("display","none");
                                alert_success(solve.message,{timer:3000});
                                if(solve.redirect != undefined && solve.redirect){
                                    setTimeout(function(){
                                        window.location.href = solve.redirect;
                                    },3000);
                                }
                            }
                        }else
                            console.log(result);
                    }
                }else console.log(result);
            });

        }

        function applyDelete(){
            open_modal("deleteModal",{
                title:"<?php echo __("admin/orders/delete-modal-title-list"); ?>"
            });

            $("#delete_ok").click(function(){
                var password = $('#password1').val();
                var request = MioAjax({
                    action: "<?php echo $links["controller"]; ?>",
                    method: "POST",
                    data: {operation:"apply_operation",from:"detail",type:"delete",id:<?php echo $order["id"]; ?>}
                },true,true);

                request.done(function(result){
                    if(result){
                        if(result != ''){
                            var solve = getJson(result);
                            if(solve !== false){
                                if(solve.status == "error"){

                                    if(solve.message != undefined && solve.message != ''){

                                        alert_error(solve.message,{timer:5000});

                                        if(solve.for != undefined && solve.for == "status"){
                                            $("#statusMsg").css("display","block");
                                            $("#statusMsg .statusMsg_text").html(solve.message);
                                        }
                                    }
                                }else if(solve.status == "successful"){
                                    $("#statusMsg").css("display","none");
                                    alert_success(solve.message,{timer:3000});
                                    close_modal("deleteModal");
                                    if(solve.redirect != undefined && solve.redirect){
                                        setTimeout(function(){
                                            window.location.href = solve.redirect;
                                        },3000);
                                    }
                                }
                            }else
                                console.log(result);
                        }
                    }else console.log(result);
                });

            });

            $("#delete_no").click(function(){
                close_modal("deleteModal");
                $("#password1").val('');
            });

        }

        function _EventOK(id,el){
            var request = MioAjax({
                waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                button_element:el !== undefined ? el : $("#event_"+id+" .event-ok-button"),
                action:"<?php echo $links["controller"]; ?>",
                method:"POST",
                data:{operation:"event_ok",id:id}
            },true,true);
            request.done(function(result){
                if(result != ''){
                    var solve = getJson(result);
                    if(solve !== false){
                        if(solve.status == "error")
                        {
                            if(solve.message != undefined && solve.message != '')
                                alert_error(solve.message,{timer:5000});
                        }
                        else if(solve.status == "successful"){
                            $("#event_"+id).fadeOut(300);
                            if(id === <?php echo  $cancellation_request ? $cancellation_request["id"] : 0; ?>)
                                $(".cancellation_request_operations").fadeOut(300);
                        }
                    }else
                        console.log(result);
                }
            });
        }
        function _EventDel(id,el){
            var request = MioAjax({
                waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                button_element:el !== undefined ? el : $("#event_"+id+" .event-ok-button"),
                action:"<?php echo $links["controller"]; ?>",
                method:"POST",
                data:{operation:"event_del",id:id}
            },true,true);
            request.done(function(result){
                if(result != ''){
                    var solve = getJson(result);
                    if(solve !== false){
                        if(solve.status == "error")
                        {
                            if(solve.message != undefined && solve.message != '')
                                alert_error(solve.message,{timer:5000});
                        }
                        else if(solve.status == "successful"){
                            $("#event_"+id).fadeOut(300);
                            if(id === <?php echo  $cancellation_request ? $cancellation_request["id"] : 0; ?>)
                                $("#CancellationRequestWrap").fadeOut(300);
                        }
                    }else
                        console.log(result);
                }
            });
        }

        function MsgOK(){
            var request = MioAjax({
                waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                button_element:$("#statusMsg_OK"),
                action:"<?php echo $links["controller"]; ?>",
                method:"POST",
                data:{operation:"msg_ok"}
            },true,true);
            request.done(function(result){
                if(result != ''){
                    var solve = getJson(result);
                    if(solve !== false){
                        if(solve.status == "error"){
                            if(solve.message != undefined && solve.message != '')
                                alert_error(solve.message,{timer:5000});
                        }else if(solve.status == "successful"){
                            $("#statusMsg").fadeOut(300,function(){
                                $("#statusMsg_text").html('');
                            });
                        }
                    }else
                        console.log(result);
                }
            });
        }

        function changeType(el)
        {
            el              = $(el);
            section         = el.val();
            fn_el           = $(el).parent().parent().parent();
            otr_el          = $("."+section+"-wrap",fn_el);
            opts_el         = $(".options-wrap",fn_el);

            $(".other-wrappers",fn_el).css("display","none");

            if(in_array(section,['select'])){
                opts_el.css("display","block");

                if($(".field-options-wrap .field-option-item",fn_el).length < 1)
                    add_option_in_doc($('.add-option-in-doc',opts_el));
                else
                    console.log($(".field-options-wrap .field-option-item",fn_el));
            }else{
                opts_el.css("display","none");
            }

            if(otr_el !== undefined){
                otr_el.css("display","block");
                $("select,input",otr_el).attr("disabled",false);
            }
        }

        function add_operator_doc(){
            var template = $("#template-doc-item").html();

            doc_last_id++;

            template = template.replace(/operator_docs\[x\]/g,"operator_docs["+doc_last_id+"]");

            $("#operator_docs_wrapper").append(template);
        }

        function DeleteDoc(el,id)
        {
            if(!confirm("<?php echo ___("needs/delete-are-you-sure"); ?>")) return false;

            var request = MioAjax({
                waiting_text: '<i class="fa fa-spinner" style="-webkit-animation:fa-spin 2s infinite linear;animation: fa-spin 2s infinite linear;"></i>',
                progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                button_element: el,
                action:"<?php echo $links["controller"]; ?>",
                method:"POST",
                data:{operation:"delete_domain_doc",id:id}
            },true,true);

            request.done(function(result){
                if(result !== ''){
                    var solve = getJson(result);
                    if(solve !== false){
                        if(solve.status == "error"){
                            if(solve.message != undefined && solve.message != '')
                                alert_error(solve.message,{timer:5000});
                        }else if(solve.status == "successful"){
                            alert_success(solve.message,{timer:3000});
                            setTimeout(function(){
                                window.location.reload();
                            },2000);
                        }
                    }
                }
            });
        }

    </script>
</head>
<body>

<div id="template-doc-item" style="display: none;">
    <div class="field-item">
        <div class="fieldcon">
            <div class="fieldcon2">
                <input type="text" name="operator_docs[x][name]" class="" placeholder="<?php echo __("admin/products/domain-docs-tx2"); ?>">
            </div>
            <div class="fieldcon3">
                <select name="operator_docs[x][type]" onchange="changeType(this);">
                    <option value="text"><?php echo __("admin/products/domain-docs-tx5"); ?></option>
                    <option value="file"><?php echo __("admin/products/domain-docs-tx6"); ?></option>
                </select>

                <div class="file-wrap other-wrappers formcon" style="display: none;">

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/users/document-filter-f-allowed-ext"); ?></div>
                        <div class="yuzde70">
                            <input type="text" name="operator_docs[x][allowed_ext]" placeholder="<?php echo __("admin/users/document-filter-f-allowed-ext-info"); ?>">
                        </div>
                    </div>
                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/users/document-filter-f-max-upload-fsz"); ?></div>
                        <div class="yuzde70">
                            <input type="text" name="operator_docs[x][max_file_size]" value="3">
                        </div>
                    </div>

                </div>

            </div>
            <div class="fieldcon1">
                <a href="javascript:void 0;" onclick="$(this).parent().parent().parent().remove();" class="sbtn red"><i class="fa fa-trash"></i></a>
            </div>
        </div>


        <div class="clear"></div>
    </div>
</div>


<table style="display: none;">
    <tbody id="template-loader">
    <tr style="background: none;box-shadow: none;">
        <td colspan="5" style="box-shadow: none;">
            <div id="template-loaderx">
                <div id="block_module_loader" align="center">
                    <div class="load-wrapp">
                        <p style="margin-bottom:20px;font-size:17px;"><strong><?php echo ___("needs/processing"); ?>...</strong><br><?php echo ___("needs/please-wait"); ?></p>
                        <div class="load-7">
                            <div class="square-holder">
                                <div class="square"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </td>
    </tr>
    </tbody>
    <tbody id="template-loader2">
    <tr style="background: none;box-shadow: none;">
        <td colspan="4" style="box-shadow: none;">
            <div id="block_module_loader" align="center">
                <div class="load-wrapp">
                    <p style="margin-bottom:20px;font-size:17px;"><strong><?php echo ___("needs/processing"); ?>...</strong><br><?php echo ___("needs/please-wait"); ?></p>
                    <div class="load-7">
                        <div class="square-holder">
                            <div class="square"></div>
                        </div>
                    </div>
                </div>
            </div>
        </td>
    </tr>
    </tbody>
</table>

<div id="invoices_modal" style="display: none;" data-izimodal-title="<?php echo __("admin/orders/detail-all-invoices"); ?>">
    <script type="text/javascript">
        var invoices;
        function view_all_invoices_btn(){
            open_modal("invoices_modal",{width:'1200px'});
            if(invoices) invoices.destroy();
            if(!invoices){
                invoices = $('#invoicesTable').DataTable({
                    "columnDefs": [
                        {
                            "targets": [0],
                            "visible":false,
                        },
                    ],
                    "lengthMenu": [
                        [10, 25, 50, -1], [10, 25, 50, "<?php echo ___("needs/allOf"); ?>"]
                    ],
                    "searching" : false,
                    responsive: true,
                    "language":{"url":"<?php echo APP_URI; ?>/<?php echo ___("package/code"); ?>/datatable/lang.json"}
                });
            }
        }
    </script>
    <div class="padding20">
        <table width="100%" id="invoicesTable">
            <thead style="background:#ebebeb;">
            <tr>
                <th align="left">#</th>
                <th align="center" data-orderable="false"><?php echo __("admin/invoices/bills-th-id"); ?></th>
                <th align="center" data-orderable="false"><?php echo __("admin/invoices/bills-th-amount"); ?></th>
                <th align="center" data-orderable="false"><?php echo __("admin/invoices/bills-th-date"); ?></th>
                <th align="center" data-orderable="false"><?php echo __("admin/invoices/bills-th-status"); ?></th>
                <th align="center" data-orderable="false"></th>
            </tr>
            </thead>
            <tbody align="center" style="border-top:none;">
            <?php
                if(isset($invoices) && $invoices){
                    foreach($invoices AS $k=>$row){
                        $id     = $row["id"];
                        $amount_detail       = Money::formatter_symbol($row["subtotal"],$row["currency"]);
                        if($row["status"] != "unpaid")
                            $amount_detail   = Money::formatter_symbol($row["total"],$row["currency"]);

                        if($row["status"] == "paid" || $row["status"] == "taxed" || $row["status"] == "untaxed")
                            $date_detail = DateManager::format(Config::get("options/date-format")." - H:i",$row["datepaid"]);
                        elseif($row["status"] == "unpaid")
                            $date_detail = DateManager::format(Config::get("options/date-format")." - H:i",$row["duedate"]);
                        elseif($row["status"] == "cancelled-refund"){
                            if(substr($row["refunddate"],0,4) == "1881")
                                $date_detail = DateManager::format(Config::get("options/date-format")." - H:i",$row["duedate"]);
                            else
                                $date_detail = DateManager::format(Config::get("options/date-format")." - H:i",$row["refunddate"]);
                        }
                        else{
                            if($row["status"] == "paid") $date_detail = '<strong>'.__("admin/invoices/bills-th-datepaid").'</strong><br>'.DateManager::format(Config::get("options/date-format")." - H:i",$row["datepaid"]);
                            elseif($row["status"] == "unpaid" || $row["status"] == "waiting") $date_detail = '<strong>'.__("admin/invoices/bills-th-duedate").'</strong><br>'.DateManager::format(Config::get("options/date-format")." - H:i",$row["duedate"]);
                            elseif($row["status"] == "refund") $date_detail = '<strong>'.__("admin/invoices/bills-th-refunddate").'</strong><br>'.DateManager::format(Config::get("options/date-format")." - H:i",$row["refunddate"]);
                            else
                                $date_detail = '<strong>'.__("admin/invoices/bills-th-cdate").'</strong><br>'.DateManager::format(Config::get("options/date-format")." - H:i",$row["cdate"]);
                        }

                        $detail_link = Controllers::$init->AdminCRLink("invoices-2",["detail",$id]);
                        ?>
                        <tr>
                            <td><?php echo $k; ?></td>
                            <td><?php echo "#".$id; ?></td>
                            <td><?php echo $amount_detail; ?></td>
                            <td><?php echo $date_detail; ?></td>
                            <td><?php echo $invoice_situations[$row["status"]]; ?></td>
                            <td><a href="<?php echo $detail_link; ?>" target="_blank" data-tooltip="<?php echo ___("needs/button-edit"); ?>" class="sbtn"><i class="fa fa-edit" aria-hidden="true"></i></a></td>
                        </tr>
                        <?php
                    }
                }
            ?>
            </tbody>
        </table>

    </div>
</div>

<div id="deleteModal" style="display: none;">
    <div class="padding20">
        <div align="center">

            <p id="deleteModal_text1"><?php echo __("admin/orders/delete-are-youu-sure-list"); ?></p>
            <div class="clear"></div>
            <div class="yuzde50">
                <a href="javascript:void(0);" id="delete_ok" class="gonderbtn redbtn"><i class="fa fa-check"></i> <?php echo __("admin/orders/delete-ok"); ?></a>
            </div>
            <div class="yuzde50">
                <a href="javascript:void(0);" id="delete_no" class="gonderbtn yesilbtn"><i class="fa fa-ban"></i> <?php echo __("admin/orders/delete-no"); ?></a>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__.DS."inc/header.php"; ?>

<div id="wrapper">

    <div class="icerik-container">
        <div class="icerik">

            <div class="icerikbaslik">
                <h1><strong><?php echo __("admin/orders/page-domain-detail",['{name}' => $order["name"]]); ?></strong></h1>
                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>


            <div id="tab-content"><!-- tab wrap content start -->
                <ul class="tab">
                    <li>
                        <a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'detail','content')" data-tab="detail"><i class="fa fa-info" aria-hidden="true"></i>  <?php echo __("admin/orders/detail-content-tab-detail"); ?></a>
                    </li>
                    <li>
                        <a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'verification','content')" data-tab="verification"><i class="fas fa-gavel"></i>  <?php echo __("admin/orders/docs-tx20"); ?></a>
                    </li>

                    <li>
                        <a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'whois','content')" data-tab="whois"><i class="far fa-id-card" aria-hidden="true"></i>  <?php echo __("admin/orders/detail-content-tab-whois"); ?></a>
                    </li>

                    <li>
                        <a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'dns','content')" data-tab="dns"><i class="fa fa-globe" aria-hidden="true"></i>  <?php echo __("admin/orders/detail-content-tab-dns"); ?></a>
                    </li>

                    <?php if($allow_forwarding_dmn || $allow_forwarding_eml): ?>
                        <li>
                            <a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'forwarding','content')" data-tab="forwarding"><i class="fa fa-share" aria-hidden="true"></i> <?php echo __("website/account_products/forwarding"); ?></a>
                        </li>
                    <?php endif; ?>

                    <li>
                        <a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'history','content')" data-tab="history"><i class="fa fa-history" aria-hidden="true"></i>  <?php echo __("admin/orders/detail-content-tab-history"); ?></a>
                    </li>

                </ul>

                <div id="operation-loading" class="blur-text" style="display: none">
                    <i class="fa fa-cog loadingicon" aria-hidden="true"></i>
                    <div class="clear"></div>
                    <strong><?php echo __("admin/orders/list-row-operation-processing"); ?></strong>
                </div>

                <div id="content-detail" class="tabcontent"><!-- detail tab content start -->

                    <?php
                        if($order["status"] == "inprocess" && isset($uploaded_docs) && $uploaded_docs)
                        {
                            foreach($uploaded_docs AS $ud)
                            {
                                if($ud["status"] == "pending")
                                {
                                    ?>
                                    <div class="red-info">
                                        <div class="padding20">
                                            <i class="fa fa-info-circle"></i>
                                            <p><?php echo __("admin/orders/docs-tx19"); ?></p>
                                            <br>
                                            <a class="lbtn red" href="javascript:void 0;" onclick="$('a[data-tab=verification]').click();"><?php echo __("admin/orders/docs-tx20"); ?></a>

                                        </div>
                                    </div>
                                    <div class="clear"></div>
                                    <?php
                                    break;
                                }
                            }
                        }
                    ?>


                    <?php
                        if($cancellation_request)
                        {
                            $cancellation_request["data"] = Utility::jdecode($cancellation_request["data"],true);
                            ?>
                            <div class="red-info" id="CancellationRequestWrap">
                                <div class="padding20">
                                    <i class="fa fa-meh-o"></i>

                                    <p>
                                        <strong style="display: block;margin-bottom: 10px;font-size: 18px;"><?php echo __("admin/events/cancelled-product-request"); ?></strong>
                                        <span style="display: block;margin-bottom: 10px;">   <strong><?php echo __("admin/orders/cancellation-reason"); ?></strong>: <?php echo $cancellation_request["data"]["reason"]; ?></span>

                                        <span style="display: block;"> <strong><?php echo __("admin/orders/cancellation-urgency"); ?></strong>: <?php echo __("admin/orders/cancellation-urgency-".$cancellation_request["data"]["urgency"]); ?></span>
                                        <span style="display:block;margin-bottom:15px;">
                                                <strong><?php echo __("admin/tools/reminders-creation-date"); ?></strong>: <?php echo DateManager::format(Config::get("options/date-format")." - H:i",$cancellation_request["cdate"])?></span>

                                        <a style="<?php echo $cancellation_request["status"] == "approved" ? "display: none;" : ''; ?>" class="red lbtn cancellation_request_operations" href="javascript:void 0;" onclick="_EventOK(<?php echo $event["id"]; ?>,this);"><?php echo __("admin/orders/detail-operation-button-approve"); ?></a>
                                        <a class="green lbtn" href="javascript:void 0;" onclick="_EventDel(<?php echo $event["id"]; ?>,this);"><?php echo ___("needs/button-delete"); ?></a>
                                        <a class="lbtn" href="<?php echo Controllers::$init->AdminCRLink("tickets-1",["create"]); ?>?user_id=<?php echo $order["owner_id"]; ?>&order_id=<?php echo $order["id"]; ?>"><?php echo __("admin/index/menu-tickets-create"); ?></a>

                                    </p>

                                </div>
                            </div>
                            <?php
                        }
                    ?>

                    <div class="adminpagecon">

                        <form action="<?php echo $links["controller"]; ?>" method="post" id="detailForm" enctype="multipart/form-data">
                            <input type="hidden" name="operation" value="update_detail">
                            <input type="hidden" name="from" value="detail">

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/orders/detail-content-tab-detail-user"); ?></div>
                                <div class="yuzde70">
                                    <a href="<?php echo $links["detail-user-link"]; ?>" target="_blank">
                                        <strong><?php echo $user["full_name"]; ?></strong>
                                        <?php echo $user["company_name"] ? "(".$user["company_name"].")" : ''; ?>
                                        <?php
                                            if($user['blacklist']){
                                                ?>
                                                <span class="flaggeduser"><i class="fa fa-exclamation-circle" aria-hidden="true"></i><?php echo __("admin/orders/user-blacklist"); ?></span>
                                                <?php
                                            }
                                        ?>
                                    </a>
                                </div>
                            </div>

                            <?php if($privOperation && $order["status"] != "waiting"): ?>
                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/orders/detail-transfer-to-another-user"); ?></div>
                                    <div class="yuzde70">
                                        <select name="transfer_user" id="transferUser" style="width: 100%;"></select>
                                    </div>
                                </div>

                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/orders/detail-linked-product"); ?></div>
                                    <div class="yuzde70">
                                        <select name="product_id" id="linkedProduct" style="width: 100%;">
                                            <?php
                                                if(isset($product) && $product){
                                                    ?>
                                                    <option value="<?php echo $product["id"]; ?>"><?php echo $product["name"]; ?></option>
                                                    <?php
                                                }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/orders/detail-content-tab-detail-invoice"); ?></div>
                                <div class="yuzde70">
                                    <?php if(isset($invoice) && $invoice): ?>
                                        <a href="<?php echo $links["invoice-link"]; ?>" target="_blank">
                                            <?php echo "#".$invoice["id"]; ?>
                                        </a>
                                    <?php endif;?>

                                    <a class="lbtn" href="javascript:void 0;" onclick="view_all_invoices_btn();"><?php echo __("admin/orders/detail-view-all-invoices"); ?></a>


                                    <?php
                                        if($order["period"] !== "none"){
                                            ?>
                                            <a class="lbtn" href="javascript:void 0;" onclick="generate_renew_invoice_btn(this);"><?php echo __("admin/orders/detail-generate-renew-invoice"); ?></a>

                                            <script type="text/javascript">
                                                function generate_renew_invoice_btn(btn_el){
                                                    var request = MioAjax({
                                                        waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                                                        progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                                                        button_element: btn_el,
                                                        action:"<?php echo $links["controller"]; ?>",
                                                        method:"POST",
                                                        data:{operation:"generate_renew_invoice"}
                                                    },true,true);

                                                    request.done(function(result){
                                                        if(result !== ''){
                                                            var solve = getJson(result);
                                                            if(solve !== false){
                                                                if(solve.status == "error"){
                                                                    if(solve.message != undefined && solve.message != '')
                                                                        alert_error(solve.message,{timer:5000});
                                                                }else if(solve.status == "successful"){
                                                                    alert_success(solve.message,{timer:2000});
                                                                    if(solve.redirect !== undefined && solve.redirect !== ''){
                                                                        setTimeout(function(){
                                                                            window.location.href = solve.redirect;
                                                                        },2000);
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    });
                                                }
                                            </script>
                                            <?php
                                        }
                                    ?>
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/orders/detail-ordernum"); ?></div>
                                <div class="yuzde70">
                                    #<?php echo $order["id"]; ?>
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/orders/detail-content-tab-detail-domain-module"); ?></div>
                                <div class="yuzde70">
                                    <select name="module">
                                        <option value="none"><?php echo ___("needs/none"); ?></option>
                                        <?php
                                            if(isset($registrar_modules) && $registrar_modules){
                                                foreach($registrar_modules AS $k=>$v){
                                                    $selected = $order["module"] == $k ? ' selected' : '';
                                                    ?>
                                                    <option<?php echo $selected; ?> value="<?php echo $k; ?>"><?php echo $v["lang"]["name"]; ?></option>
                                                    <?php
                                                }
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>


                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/orders/detail-status"); ?></div>
                                <div class="yuzde70">
                                    <?php echo $situations[$order["status"]]; ?>
                                    <div class="clear"></div>
                                    <div class="red-info" id="statusMsg" style="<?php echo $order["status_msg"] ? '' : 'display:none;'; ?>">
                                        <div class="padding20">
                                            <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                                            <p class="statusMsg_text"><?php echo $order["status_msg"]; ?></p>
                                            <a class="lbtn" id="statusMsg_OK" href="javascript:MsgOK();void 0;"><?php echo ___("needs/ok"); ?></a>
                                        </div>
                                    </div>
                                    <div class="clear"></div>
                                    <?php
                                        if($pending_events){
                                            foreach($pending_events AS $k=>$event){
                                                ?>
                                                <div class="order-event-item" id="event_<?php echo $event["id"]; ?>">
                                                    <?php echo Events::getMessage($event); ?>
                                                    <?php if(!($event["name"] == "transfer-request-to-us-with-manuel" || $event["name"] == "transfer-request-to-us-with-api")): ?>
                                                        <a class="lbtn event-ok-button" href="javascript:_EventOK(<?php echo $event["id"]; ?>);void 0;"><?php echo ___("needs/ok"); ?></a>
                                                    <?php endif; ?>
                                                </div>
                                                <?php
                                            }
                                        }
                                    ?>

                                </div>
                            </div>


                            <?php if($privOperation): ?>
                                <div class="formcon" id="applyOperation_wrap">
                                    <div class="yuzde30"><?php echo __("admin/orders/detail-operation"); ?></div>
                                    <div class="yuzde70">

                                        <?php if($order["status"] == "waiting" && !$pendingTransferwthApi): ?>
                                            <input type="radio" class="radio-custom" id="apply_approve" name="apply" value="approve">
                                            <label class="radio-custom-label" for="apply_approve" style="margin-left: 10px;"><?php echo __("admin/orders/detail-operation-button-approve"); ?></label>
                                        <?php endif; ?>

                                        <?php if($order["status"] != "waiting" && $order["status"] != "active" && !$pendingTransferwthApi): ?>
                                            <input <?php echo $order["status"] != "suspended" ? 'data-supported="true"' : ''; ?> type="radio" class="radio-custom" id="apply_active" name="apply" value="active">
                                            <label class="radio-custom-label" for="apply_active" style="margin-left: 10px;"><?php echo __("admin/orders/detail-operation-button-active"); ?></label>
                                        <?php endif; ?>

                                        <?php if($order["status"] != "suspended" && $order["status"] != "waiting"): ?>
                                            <input type="radio" class="radio-custom" id="apply_suspended" name="apply" value="suspended">
                                            <label class="radio-custom-label" for="apply_suspended" style="margin-left: 10px;"><?php echo __("admin/orders/detail-operation-button-suspended"); ?></label>
                                        <?php endif; ?>

                                        <?php if($order["status"] !== "inprocess"): ?>
                                            <input type="radio" class="radio-custom" id="apply_inprocess" name="apply" value="inprocess">
                                            <label class="radio-custom-label" for="apply_inprocess" style="margin-left: 10px;"><?php echo __("admin/orders/detail-operation-button-inprocess"); ?></label>
                                        <?php endif; ?>

                                        <?php if($order["status"] != "cancelled" && $order["status"] != "waiting"): ?>
                                            <input type="radio" class="radio-custom" id="apply_cancelled" name="apply" value="cancelled">
                                            <label class="radio-custom-label" for="apply_cancelled" style="margin-left: 10px;"><?php echo __("admin/orders/detail-operation-button-cancelled"); ?></label>
                                        <?php endif; ?>

                                        <?php if($privDelete): ?>
                                            <input type="radio" class="radio-custom" id="apply_delete" name="apply" value="delete">
                                            <label class="radio-custom-label" for="apply_delete" style="margin-left: 10px;"><?php echo __("admin/orders/detail-operation-button-delete"); ?></label>
                                        <?php endif; ?>

                                        <div class="clear"></div>
                                        <div id="apply_note_cancelled" style="display: none;" class="red-info apply-notes">
                                            <div class="padding15">
                                                <i class="fa fa-info-circle" aria-hidden="true"></i>
                                                <p><?php echo __("admin/orders/detail-operation-cancelled-info"); ?></p>
                                            </div>
                                        </div>

                                        <?php if(version_compare(License::get_version(),'3.1.9','>')): ?>
                                            <div id="apply_note_suspended" style="<?php echo $order["status"] != "suspended" ? 'display: none;' : ''; ?>" class="formcon apply-notes">
                                                <input type="text" name="suspended_reason" value="<?php echo $order["suspended_reason"] ?? ''; ?>" placeholder="<?php echo __("admin/orders/suspended-reason"); ?>">
                                            </div>
                                        <?php endif; ?>

                                        <script type="text/javascript">
                                            $(document).ready(function(){
                                                $("input[name=apply]").change(function(){
                                                    var val = $(this).val();
                                                    $(".apply-notes").css("display","none");
                                                    if(document.getElementById("apply_note_"+val))
                                                        $("#apply_note_"+val).fadeIn(300);
                                                });
                                            });
                                        </script>

                                        <?php if($order["module"] != "none"): ?>
                                            <div class="clear"></div>
                                            <div style="margin-top: 10px;margin-left: 10px;" id="module_permission">
                                                <input checked name="apply_on_module" type="checkbox" class="sitemio-checkbox" id="apply-module" value="1">
                                                <label class="sitemio-checkbox-label" for="apply-module"></label>
                                                <span class="kinfo"><?php echo __("admin/orders/apply-on-module"); ?></span>
                                            </div>
                                            <script type="text/javascript">
                                                $(document).ready(function(){
                                                    checkApplyOperationSelected();
                                                    $("#applyOperation_wrap input").change(checkApplyOperationSelected);
                                                });
                                                function checkApplyOperationSelected(){
                                                    var s_el = $("#applyOperation_wrap input:checked");
                                                    if(s_el.data("supported"))
                                                        $("#module_permission").css("display","inline-block");
                                                    else
                                                        $("#module_permission").css("display","none");
                                                }
                                            </script>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if(isset($options["tcode"])): ?>
                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/orders/domain-incoming-transfer-code"); ?></div>
                                    <div class="yuzde70">
                                        <input type="text" name="tcode" value="<?php echo htmlentities($options["tcode"],ENT_QUOTES); ?>" style="width: 200px;">
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if($order["status"] == "active"): ?>
                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/orders/domain-transfer-code"); ?></div>
                                    <div class="yuzde70">
                                        <script type="text/javascript">
                                            function send_transfer_code(element){
                                                var input,code;
                                                input = $("input[name=transfer-code]");
                                                code = input.val();
                                                if(code == ''){
                                                    alert_error("<?php echo htmlspecialchars(__("admin/orders/error11")); ?>",{timer:3000});
                                                    input.focus();
                                                    return false;
                                                }

                                                var request = MioAjax({
                                                    action:"<?php echo $links["controller"]; ?>",
                                                    method:"POST",
                                                    data:{operation:"domain_send_transfer_code",code:code},
                                                    button_element:element,
                                                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                                                    progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                                                },true,true);
                                                request.done(function(result){
                                                    detailForm_handler(result);
                                                });

                                            }

                                            function generate_transfer_code(element){
                                                var input = $('input[name=transfer-code]');
                                                var code = voucher_codes.generate({length:12,charset: '0123456789*-+ABCDEFGHIJKLMNOPQRSTUVWXYZ'});
                                                input.val(code);
                                                $("#detailForm input[name=from]").val("transfer-code");
                                                $("#detailForm_submit").trigger("click");
                                            }
                                        </script>
                                        <input type="text" name="transfer-code" value="<?php echo isset($options["transfer-code"]) ? htmlentities($options["transfer-code"],ENT_QUOTES) : ''; ?>" style="width: 200px;">
                                        <a href="javascript:void(0);" onclick="generate_transfer_code(this);" class="lbtn"><i class="fa fa-refresh"></i> <?php echo __("admin/orders/auto-generate-button"); ?></a>
                                        <a href="javascript:void(0);" onclick="send_transfer_code(this);" class="lbtn"><?php echo __("admin/orders/domain-send-transfer-code"); ?></a>
                                    </div>
                                </div>
                            <?php endif; ?>


                            <?php if($order["status"] == "active"): ?>
                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/orders/domain-transfer-lock"); ?></div>
                                    <div class="yuzde70">
                                        <input<?php echo isset($options["transferlock"]) && $options["transferlock"] ? ' checked' : ''; ?> type="checkbox" class="sitemio-checkbox" name="transferlock" value="1" id="transferLock">
                                        <label for="transferLock" class="sitemio-checkbox-label"></label>
                                    </div>
                                </div>
                            <?php endif; ?>




                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/orders/detail-product-name"); ?></div>
                                <div class="yuzde70">
                                    <input name="name" type="text" value="<?php echo $order["name"]; ?>">
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/orders/detail-product-group"); ?></div>
                                <div class="yuzde70">
                                    <a href="<?php echo $links["group-link"]; ?>" target="_blank">
                                        <?php echo $order["options"]["local_group_name"]; ?>
                                    </a>
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/orders/detail-cdate"); ?></div>
                                <div class="yuzde70">
                                    <input class="yuzde25" id="cdate" name="cdate" type="date" value="<?php echo DateManager::format("Y-m-d",$order["cdate"])?>" placeholder="YYYY-MM-DD" autocomplete="off">
                                    <input class="yuzde25" onkeypress='return event.charCode==58 || event.charCode>= 48 &&event.charCode<= 57' maxlength="5" type="time" name="ctime" value="<?php echo DateManager::format("H:i",$order["cdate"]); ?>">
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/orders/detail-renewaldate"); ?></div>
                                <div class="yuzde70">
                                    <input class="yuzde25" id="renewaldate" name="renewaldate" type="date" value="<?php echo substr($order["renewaldate"],0,4) != "1881" ? DateManager::format("Y-m-d",$order["renewaldate"]) : ''; ?>" placeholder="YYYY-MM-DD" autocomplete="off">
                                    <input class="yuzde25" onkeypress='return event.charCode==58 || event.charCode>= 48 &&event.charCode<= 57' maxlength="5" type="time" name="renewaltime" value="<?php echo substr($order["renewaldate"],0,4) != "1881" ? DateManager::format("H:i",$order["renewaldate"]) : ''; ?>">
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/orders/detail-duedate"); ?></div>
                                <div class="yuzde70">
                                    <input class="yuzde25" id="duedate" name="duedate" type="date" value="<?php echo substr($order["duedate"],0,4) != "1881" ? DateManager::format("Y-m-d",$order["duedate"]) : '';?>" placeholder="YYYY-MM-DD" autocomplete="off">
                                    <input class="yuzde25" onkeypress='return event.charCode==58 || event.charCode>= 48 &&event.charCode<= 57' maxlength="5" type="time" name="duetime" value="<?php echo substr($order["duedate"],0,4) != "1881" ? DateManager::format("H:i",$order["duedate"]) : ''; ?>">
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/orders/detail-process-exemption"); ?></div>
                                <div class="yuzde70">
                                    <input class="yuzde50" id="process_exemption_date" name="process_exemption_date" type="date" value="<?php echo substr($order["process_exemption_date"],0,4) != "1881" ? DateManager::format("Y-m-d",$order["process_exemption_date"]) : '';?>" placeholder="YYYY-MM-DD" autocomplete="off">
                                    <div class="clear"></div>
                                    <span class="kinfo"><?php echo __("admin/orders/detail-process-exemption-info"); ?></span>

                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/orders/detail-pmethod"); ?></div>
                                <div class="yuzde70">
                                    <select name="pmethod">
                                        <option value="none"><?php echo ___("needs/none"); ?></option>
                                        <?php
                                            if($pmethods){
                                                foreach($pmethods AS $k=>$v){
                                                    ?><option<?php echo $k == $order["pmethod"] ? ' selected' : ''; ?> value="<?php echo $k; ?>"><?php echo $v; ?></option><?php
                                                }
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <?php
                                if(isset($subscription) && $subscription)
                                {
                                    ?>
                                    <div class="formcon">
                                        <div class="yuzde30"><?php echo __("admin/orders/link-subscription"); ?></div>
                                        <div class="yuzde70" id="subscription_content">

                                            <div id="subscription_loader">
                                                <div class="load-wrapp">
                                                    <p style="margin-bottom:20px"><strong><?php echo ___("needs/processing"); ?>...</strong><br><?php echo ___("needs/please-wait"); ?></p>
                                                    <div class="load-7">
                                                        <div class="square-holder">
                                                            <div class="square"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <script type="text/javascript">
                                        $(document).ready(function(){
                                            $.get("<?php echo $links["controller"]; ?>?bring=subscription_detail",function(res){
                                                $("#subscription_loader").html(res);
                                            });
                                        });
                                        function cancel_subscription(el)
                                        {
                                            if(!confirm("<?php echo ___("needs/apply-are-you-sure"); ?>")) return false;
                                            var request = MioAjax({
                                                button_element:el,
                                                waiting_text: "<?php echo __("website/others/button1-pending"); ?>",
                                                action: "<?php echo $links["controller"]; ?>",
                                                method: "POST",
                                                data:{
                                                    operation: "cancel_subscription",
                                                    id: <?php echo $subscription["id"]; ?>,
                                                    order_id: <?php echo $order["id"]; ?>
                                                }
                                            },true,true);
                                            request.done(function(result){
                                                var solve = getJson(result);
                                                if(solve !== undefined && solve !== false)
                                                {
                                                    if(solve.status === "error")
                                                        alert_error(solve.message,{timer:4000});
                                                    else if(solve.status === "successful")
                                                    {
                                                        window.location.href = '<?php echo $links["controller"]; ?>';
                                                    }
                                                }
                                            });
                                        }
                                    </script>
                                <?php
                                    }
                                    else
                                    {
                                ?>
                                    <div class="formcon">
                                        <div class="yuzde30"><?php echo __("admin/orders/subscription-identifier"); ?></div>
                                        <div class="yuzde70">
                                            <input type="text" name="subscription[identifier]" placeholder="" value="">
                                        </div>
                                    </div>
                                    <?php
                                }
                            ?>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/orders/detail-pricing-type"); ?></div>
                                <div class="yuzde70">
                                    <select name="pricing-type">
                                        <?php
                                            $pricing_type = false;
                                            if(isset($options["pricing-type"]))
                                                $pricing_type = $options["pricing-type"];
                                        ?>
                                        <option value="1"<?php echo !$pricing_type || $pricing_type == 1 ? ' selected' : ''; ?>><?php echo __("admin/orders/detail-pricing-type-1"); ?></option>
                                        <option value="2"<?php echo $pricing_type == 2 ? ' selected' : ''; ?>><?php echo __("admin/orders/detail-pricing-type-2"); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/orders/detail-pricing"); ?></div>
                                <div class="yuzde70">

                                    <input class="yuzde15" name="period_time" type="text" placeholder="<?php echo __("admin/orders/detail-pricing-period"); ?>" value="<?php echo $order["period_time"]; ?>"> -
                                    <input type="hidden" name="period" value="year">
                                    <select disabled class="yuzde15" name="period">
                                        <?php
                                            foreach(___("date/periods") AS $k=>$v){
                                                ?>
                                                <option<?php echo $order["period"] == $k ? ' selected' : ''; ?> value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                                <?php
                                            }
                                        ?>
                                    </select> -
                                    <input class="yuzde15" name="amount" type="text" placeholder="<?php echo __("admin/orders/detail-pricing-amount"); ?>" value="<?php echo $order["amount"] ? Money::formatter($order["amount"],$order["amount_cid"]) : ''; ?>" onkeypress='return event.charCode==44 || event.charCode==46 || event.charCode>= 48 &&event.charCode<= 57'> -
                                    <select class="yuzde20" name="amount_cid">
                                        <?php
                                            foreach(Money::getCurrencies($order["amount_cid"]) AS $curr){
                                                ?>
                                                <option<?php echo $order["amount_cid"] == $curr["id"] ? ' selected' : ''; ?> value="<?php echo $curr["id"]; ?>"><?php echo $curr["name"]." (".$curr["code"].")"; ?></option>
                                                <?php
                                            }
                                        ?>
                                    </select>
                                    <div class="clear"></div>

                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/orders/detail-notes"); ?></div>
                                <div class="yuzde70">
                                    <textarea name="notes" placeholder="<?php echo __("admin/orders/detail-notes-ex"); ?>"><?php echo $order["notes"]; ?></textarea>
                                </div>
                            </div>


                            <?php if($privOperation): ?>
                                <div style="float:right;margin-bottom:20px;" class="guncellebtn yuzde30">
                                    <a id="detailForm_submit" class="yesilbtn gonderbtn" href="javascript:void(0);"><?php echo __("admin/orders/update-button"); ?></a>
                                </div>
                            <?php endif; ?>


                        </form>


                        <div class="clear"></div>
                    </div>


                    <div class="clear"></div>
                </div><!-- detail tab content end -->

                <div id="content-verification" class="tabcontent"><!-- verification tab content start -->

                    <div class="adminpagecon">

                        <script type="text/javascript">
                            $(document).ready(function(){

                                $("#VerificationForm_submit").on("click",function(){
                                    MioAjaxElement($(this),{
                                        waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                                        progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                                        result:"verificationForm_handler",
                                    });
                                });

                            });

                            function verificationForm_handler(result){
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
                        </script>
                        <form action="<?php echo $links["controller"]; ?>" method="post" id="VerificationForm" enctype="multipart/form-data">
                            <input type="hidden" name="operation" value="domain_verification">

                            <div class="biggroup">
                                <div class="padding20">
                                    <h4 class="biggrouptitle"><?php echo __("admin/orders/docs-tx1"); ?></h4>

                                    <div class="blue-info" style="margin:15px 0;">
                                        <div class="padding15">
                                            <i class="fa fa-info-circle" aria-hidden="true"></i>
                                            <p><?php echo __("admin/orders/docs-tx2"); ?></p>
                                        </div>
                                    </div>

                                    <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                        <thead>
                                        <tr style="    background-color: #eee;">
                                            <th align="left"><?php echo __("admin/orders/docs-tx3"); ?></th>
                                            <th align="left"><?php echo __("admin/orders/docs-tx4"); ?></th>
                                            <th align="center"><?php echo __("admin/orders/docs-tx5"); ?></th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                            if(isset($uploaded_docs) && $uploaded_docs)
                                            {
                                                foreach($uploaded_docs AS $ud)
                                                {
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <?php
                                                                if(isset($info_docs[$ud["doc_id"]]))
                                                                {
                                                                    if(isset($info_docs) && $info_docs)
                                                                    {
                                                                        ?>
                                                                        <select name="docs[<?php echo $ud["id"]; ?>][doc_id]">
                                                                            <option value="0"><?php echo ___("needs/none"); ?></option>
                                                                            <?php
                                                                                foreach($info_docs AS $d_k => $ind)
                                                                                {
                                                                                    ?>
                                                                                    <option<?php echo $d_k == $ud["doc_id"] ? ' selected' : ''; ?> value="<?php echo $d_k; ?>"><?php echo $ind["name"]; ?></option>
                                                                                    <?php
                                                                                }
                                                                            ?>
                                                                        </select>
                                                                        <?php
                                                                    }
                                                                }
                                                                else
                                                                {
                                                                    ?>
                                                                    <input type="hidden" name="docs[<?php echo $ud["id"]; ?>][doc_id]" value="<?php echo $ud["doc_id"]; ?>">
                                                                    <input type="text" name="docs[<?php echo $ud["id"]; ?>][name]" value="<?php echo $ud["name"]; ?>">
                                                                    <?php
                                                                }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                                if($ud["file"])
                                                                {
                                                                    ?><a href="<?php echo $links["controller"]; ?>?operation=download_domain_doc_file&id=<?php echo $ud["id"]; ?>" target="_blank"><?php echo $ud["file"]["name"]; ?> <i class="fas fa-cloud-download-alt"></i></a><?php
                                                                }
                                                                else
                                                                {
                                                                    ?>
                                                                    <input type="text" name="docs[<?php echo $ud["id"]; ?>][value]" value="<?php echo $ud["value"]; ?>">
                                                                    <?php
                                                                }
                                                            ?>
                                                            <div class="clear"></div>
                                                            <span class="kinfo"><?php echo __("admin/orders/docs-tx6",['{date}' => DateManager::format(Config::get("options/date-format")." - H:i",$ud["created_at"])]); ?></span>
                                                        </td>
                                                        <td>
                                                            <select name="docs[<?php echo $ud["id"]; ?>][status]" onchange="if($(this).val() === 'declined') $(this).next('.status-msg').css('display','inline-block'); else $(this).next('.status-msg').css('display','none');">
                                                                <?php
                                                                    if($ud["status"] == "unsent")
                                                                    {
                                                                        ?>
                                                                        <option<?php echo $ud["status"] == "unsent" ? ' selected' : ''; ?> value="unsent"><?php echo __("admin/orders/docs-tx18"); ?></option>
                                                                        <?php
                                                                    }
                                                                ?>
                                                                <option<?php echo $ud["status"] == "verified" ? ' selected' : ''; ?> value="verified"><?php echo __("admin/orders/docs-tx7"); ?></option>
                                                                <option<?php echo $ud["status"] == "declined" ? ' selected' : ''; ?> value="declined"><?php echo __("admin/orders/docs-tx8"); ?></option>
                                                                <option<?php echo $ud["status"] == "pending" ? ' selected' : ''; ?> value="pending"><?php echo __("admin/orders/docs-tx9"); ?></option>
                                                            </select>
                                                            <textarea class="status-msg" style="<?php echo $ud["status"] == "declined" ? '' : 'display:none;'; ?>" name="docs[<?php echo $ud["id"]; ?>][status_msg]" cols="" rows="" placeholder="<?php echo __("admin/orders/docs-tx10"); ?>"><?php echo $ud["status_msg"]; ?></textarea>
                                                            <div class="clear"></div>
                                                            <span class="kinfo"><?php echo __("admin/orders/docs-tx11",['{date}' => DateManager::format(Config::get("options/date-format")." - H:i",$ud["created_at"])]); ?></span>
                                                        </td>
                                                        <td><a href="javascript:void 0;" onclick="DeleteDoc(this,<?php echo $ud["id"]; ?>);" class="sbtn red"><i class="fa fa-trash"></i></a></td>
                                                    </tr>
                                                    <?php
                                                }
                                            }
                                        ?>

                                        </tbody>
                                    </table>

                                    <div class="clear"></div>

                                </div>
                            </div>

                            <div class="biggroup">
                                <div class="padding20">
                                    <h4 class="biggrouptitle"><?php echo __("admin/orders/docs-tx12"); ?></h4>

                                    <div class="blue-info" style="margin:15px 0;">
                                        <div class="padding15">
                                            <i class="fa fa-info-circle" aria-hidden="true"></i>
                                            <p><?php echo __("admin/orders/docs-tx13"); ?></p>
                                        </div>
                                    </div>


                                    <div class="fieldcon fieldconhead">
                                        <div class="fieldcon2"><strong><?php echo __("admin/orders/docs-tx3"); ?></strong></div>
                                        <div class="fieldcon3"><strong><?php echo __("admin/orders/docs-tx14"); ?></strong></div>
                                    </div>
                                    <div id="operator_docs_wrapper">
                                        <?php
                                            if(isset($options["verification_operator_docs"]))
                                            {
                                                foreach($options["verification_operator_docs"] AS $k => $od)
                                                {
                                                    ?>
                                                    <div class="field-item">
                                                        <div class="fieldcon">
                                                            <div class="fieldcon2">

                                                                <input type="text" name="operator_docs[<?php echo $k; ?>][name]" value="<?php echo $od["name"]; ?>" placeholder="<?php echo __("admin/orders/docs-tx3"); ?>">

                                                            </div>
                                                            <div class="fieldcon3">
                                                                <select name="operator_docs[<?php echo $k; ?>][type]" onchange="changeType(this);">
                                                                    <option<?php echo $od["type"] == "text" ? ' selected' : ''; ?> value="text"><?php echo __("admin/products/domain-docs-tx5"); ?></option>
                                                                    <option<?php echo $od["type"] == "file" ? ' selected' : ''; ?> value="file"><?php echo __("admin/products/domain-docs-tx6"); ?></option>
                                                                </select>

                                                                <div class="file-wrap other-wrappers formcon" style="<?php echo $od["type"] == "file" ? '' : 'display: none;'; ?>">

                                                                    <div class="formcon">
                                                                        <div class="yuzde30"><?php echo __("admin/users/document-filter-f-allowed-ext"); ?></div>
                                                                        <div class="yuzde70">
                                                                            <input type="text" name="operator_docs[<?php echo $k; ?>][allowed_ext]" value="<?php echo $od["options"]["allowed_ext"] ?? ''; ?>" placeholder="<?php echo __("admin/users/document-filter-f-allowed-ext-info"); ?>">
                                                                        </div>
                                                                    </div>
                                                                    <div class="formcon">
                                                                        <div class="yuzde30"><?php echo __("admin/users/document-filter-f-max-upload-fsz"); ?></div>
                                                                        <div class="yuzde70">
                                                                            <input type="text" name="operator_docs[<?php echo $k; ?>][max_file_size]" value="<?php echo $od["options"]["max_file_size"] ?? ''; ?>">
                                                                        </div>
                                                                    </div>

                                                                </div>

                                                            </div>
                                                            <div class="fieldcon1">
                                                                <a href="javascript:void(0);" class="sbtn doc-bearer"><i class="fa fa-arrows-alt"></i></a>

                                                                <a href="javascript:void 0;" onclick="$(this).parent().parent().parent().remove(); $('#docs_wrapper').sortable('refresh');" class="sbtn red"><i class="fa fa-trash"></i></a>
                                                            </div>
                                                        </div>


                                                        <div class="clear"></div>
                                                    </div>
                                                    <?php
                                                }
                                            }
                                        ?>
                                    </div>
                                    <div class="clear"></div>
                                    <br>
                                    <a href="javascript:void 0;" onclick="add_operator_doc();" class="sbtn green"><i class="fa fa-plus"></i></a>


                                    <div class="clear"></div>

                                </div>
                            </div>


                            <div class="biggroup">
                                <div class="padding20">
                                    <h4 class="biggrouptitle"><?php echo __("admin/orders/docs-tx17"); ?></h4>

                                    <div class="red-info" style="margin:15px 0;">
                                        <div class="padding15">
                                            <i class="fas fa-exclamation-circle"></i>
                                            <p><?php echo __("admin/orders/docs-tx16"); ?></p>
                                        </div>
                                    </div>

                                    <textarea rows="3" name="verification_operator_note" placeholder="<?php echo __("admin/orders/docs-tx15"); ?>"><?php echo $options["verification_operator_note"] ?? ''; ?></textarea>

                                    <div class="clear"></div>

                                </div>
                            </div>




                            <div class="clear"></div>
                            <div style="float:right;margin-top:10px;" class="guncellebtn yuzde30">
                                <a class="yesilbtn gonderbtn" id="VerificationForm_submit" href="javascript:void(0);"><?php echo ___("needs/button-update"); ?></a>
                            </div>

                        </form>
                    </div>


                    <div class="clear"></div>
                </div><!-- verification tab content end -->

                <div id="content-whois" class="tabcontent"><!-- whois tab content start -->

                    <div class="adminpagecon">

                        <div class="green-info" style="margin-bottom:20px;">
                            <div class="padding15">
                                <i class="fa fa-info-circle" aria-hidden="true"></i>
                                <p><?php echo __("admin/orders/update-whois-info-desc"); ?></p>
                            </div>
                        </div>



                        <script type="text/javascript">
                            $(document).ready(function(){

                                var tab_ct = _GET("contact-type");
                                if (tab_ct != '' && tab_ct != undefined) {
                                    $("#tab-contact-type .tablinks[data-tab='" + tab_ct + "']").click();
                                }
                                else
                                {
                                    $("#tab-contact-type .tablinks:eq(0)").addClass("active");
                                    $("#tab-contact-type .tabcontent:eq(0)").css("display", "block");
                                }


                                $("#whoisInfoForm_submit").on("click",function(){
                                    MioAjaxElement($(this),{
                                        waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                                        progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                                        result:"whoisInfoForm_handler",
                                    });
                                });

                                $(".select-whois-profile").change(function(){
                                    tab_ct              = _GET("contact-type");

                                    if(tab_ct === "" || tab_ct === null) tab_ct = "registrant";

                                    var profile         =  $(this).val();
                                    var profile_o       = $("option[value="+profile+"]",$(this));
                                    var wrap            = $("#contact-type-"+tab_ct);

                                    $(".profile-name-wrap",wrap).css("display","none");

                                    if(profile === "new")
                                    {
                                        $(".profile-name-wrap",wrap).css("display","block");
                                        $(".profile-name-wrap input",wrap).focus();
                                    }
                                    else
                                    {
                                        var info            = profile_o.data("information");
                                        var info_keys       = Object.keys(info);

                                        $(info_keys).each(function(k,v){
                                            $(".whois-"+tab_ct+"-"+v).val(info[v]);
                                        });
                                    }

                                });

                            });

                            function whoisInfoForm_handler(result){
                                if(result != ''){
                                    var solve = getJson(result);
                                    if(solve !== false){
                                        if(solve.status == "error"){
                                            if(solve.for != undefined && solve.for != ''){
                                                $("#whoisInfoForm "+solve.for).focus();
                                                $("#whoisInfoForm "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                                $("#whoisInfoForm "+solve.for).change(function(){
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
                        <form action="<?php echo $links["controller"]; ?>" method="post" id="whoisInfoForm">
                            <input type="hidden" name="operation" value="update_whois">
                            <div id="tab-contact-type">
                                <ul class="tab">
                                    <?php
                                        if(isset($contact_types) && $contact_types)
                                        {
                                            foreach($contact_types AS $k => $v)
                                            {
                                                ?>
                                                <li><a href="javascript:void 0;" class="tablinks" onclick="open_tab(this,'<?php echo $k; ?>','contact-type');" data-tab="<?php echo $k; ?>"><?php echo $v; ?></a></li>
                                                <?php
                                            }
                                        }
                                    ?>
                                </ul>

                                <?php
                                    if(isset($contact_types) && $contact_types)
                                    {
                                        foreach($contact_types AS $k => $v)
                                        {
                                            ?>
                                            <div id="contact-type-<?php echo $k; ?>" class="tabcontent">

                                                <div class="formcon">
                                                    <div class="yuzde30"><?php echo __("website/account_products/domain-whois-tx18"); ?></div>
                                                    <div class="yuzde70">
                                                        <select class="select-whois-profile" name="profile_id[<?php echo $k; ?>]">
                                                            <option data-information='<?php echo Utility::jencode($user_whois_info ?? []); ?>' value="0"><?php echo __("website/account_products/domain-whois-tx20"); ?></option>
                                                            <?php
                                                                if(isset($whois_profiles) && $whois_profiles)
                                                                {
                                                                    foreach($whois_profiles AS $pf)
                                                                    {
                                                                        $s_pf = $whois[$k]["profile_id"] ?? 0;
                                                                        $pf_s = $s_pf == $pf["id"];
                                                                        ?>
                                                                        <option<?php echo $pf_s ? ' selected' : ''; ?> data-information='<?php echo $pf["information"]; ?>' value="<?php echo $pf["id"]; ?>"><?php echo $pf["name"].' - '.$pf["person_name"]." - ".$pf["person_email"]." - ".$pf["person_phone"];  ?></option>
                                                                        <?php
                                                                    }
                                                                }
                                                            ?>
                                                            <option value="new">+ <?php echo __("website/account_products/domain-whois-tx19"); ?></option>
                                                        </select>

                                                        <div class="formcon profile-name-wrap" style="display: none;">
                                                            <div class="yuzde30"><?php echo __("website/account_products/domain-whois-tx7"); ?></div>
                                                            <div class="yuzde70">
                                                                <input name="profile_name[<?php echo $k; ?>]" value="" type="text" placeholder="<?php echo __("website/account_products/domain-whois-tx7"); ?>" style="padding: 8px;width: 100%;">
                                                            </div>
                                                        </div>

                                                        <div style="margin-top: 15px;display: inline-block;">
                                                            <input type="checkbox" name="apply_to_all[<?php echo $k; ?>]" value="1" class="checkbox-custom" id="apply_to_all_<?php echo $k; ?>">
                                                            <label class="checkbox-custom-label" for="apply_to_all_<?php echo $k; ?>"><?php echo __("website/account_products/domain-whois-tx21"); ?></label>
                                                        </div>

                                                    </div>
                                                </div>


                                                <input name="info[<?php echo $k; ?>][Name]" value="<?php echo $whois[$k]["Name"] ?? ''; ?>" type="text" class="yuzde33 whois-<?php echo $k; ?>-Name" placeholder="<?php echo __("website/account_products/whois-full_name"); ?>">
                                                <input name="info[<?php echo $k; ?>][Company]" value="<?php echo $whois[$k]["Company"] ?? ''; ?>" type="text" class="yuzde33 whois-<?php echo $k; ?>-Company" placeholder="<?php echo __("website/account_products/whois-company_name"); ?>">
                                                <input name="info[<?php echo $k; ?>][EMail]" value="<?php echo $whois[$k]["EMail"] ?? ''; ?>" type="text" class="yuzde33 whois-<?php echo $k; ?>-EMail" placeholder="<?php echo __("website/account_products/whois-email"); ?>">
                                                <input name="info[<?php echo $k; ?>][PhoneCountryCode]" value="<?php echo $whois[$k]["PhoneCountryCode"] ?? ''; ?>" type="text" class="yuzde33 whois-<?php echo $k; ?>-PhoneCountryCode" placeholder="<?php echo __("website/account_products/whois-phoneCountryCode"); ?>">
                                                <input name="info[<?php echo $k; ?>][Phone]" type="text" value="<?php echo $whois[$k]["Phone"] ?? ''; ?>" class="yuzde33 whois-<?php echo $k; ?>-Phone" placeholder="<?php echo __("website/account_products/whois-phone"); ?>">
                                                <input name="info[<?php echo $k; ?>][FaxCountryCode]" type="text" value="<?php echo $whois[$k]["FaxCountryCode"] ?? ''; ?>" class="yuzde33 whois-<?php echo $k; ?>-FaxCountryCode" placeholder="<?php echo __("website/account_products/whois-faxCountryCode"); ?>">
                                                <input name="info[<?php echo $k; ?>][Fax]" type="text" value="<?php echo $whois[$k]["Fax"] ?? ''; ?>" class="yuzde33 whois-<?php echo $k; ?>-Fax" placeholder="<?php echo __("website/account_products/whois-fax"); ?>">
                                                <input name="info[<?php echo $k; ?>][City]" type="text" value="<?php echo $whois[$k]["City"] ?? ''; ?>" class="yuzde33 whois-<?php echo $k; ?>-City" placeholder="<?php echo __("website/account_products/whois-city"); ?>">
                                                <input name="info[<?php echo $k; ?>][State]" type="text" value="<?php echo $whois[$k]["State"] ?? ''; ?>" class="yuzde33 whois-<?php echo $k; ?>-State" placeholder="<?php echo __("website/account_products/whois-state"); ?>">
                                                <input name="info[<?php echo $k; ?>][Address]" type="text" value="<?php echo ($whois[$k]["AddressLine1"] ?? ''); ?>" class="yuzde33 whois-<?php echo $k; ?>-Address" placeholder="<?php echo __("website/account_products/whois-address"); ?>">
                                                <input name="info[<?php echo $k; ?>][Country]" type="text" value="<?php echo $whois[$k]["Country"] ?? ''; ?>" class="yuzde33 whois-<?php echo $k; ?>-Country" placeholder="<?php echo __("website/account_products/whois-CountryCode"); ?>">
                                                <input name="info[<?php echo $k; ?>][ZipCode]" type="text" value="<?php echo $whois[$k]["ZipCode"] ?? ''; ?>" class="yuzde33 whois-<?php echo $k; ?>-ZipCode" placeholder="<?php echo __("website/account_products/whois-zipcode"); ?>">
                                            </div>
                                            <?php
                                        }
                                    }
                                ?>

                            </div>


                            <a href="javascript:void(0);" id="whoisInfoForm_submit" class="yesilbtn gonderbtn"><?php echo __("admin/orders/update-whois-info-button"); ?></a>

                        </form>

                        <script type="text/javascript">
                            $(document).ready(function(){

                                $("#whois_privacy_purchase").on("click","#whoisPrivacyForm_submit",function(){
                                    MioAjaxElement($(this),{
                                        waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                                        progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                                        result:"whoisPrivacyForm_handler",
                                    });
                                });

                                $("#wprivacy_show").click(function(){
                                    var request = MioAjax({
                                        button_element:$(this),
                                        waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                                        progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                                        action:"<?php echo $links["controller"]; ?>",
                                        method:"POST",
                                        data:{operation:"update_whois_privacy",status:"disable"},
                                    },true,true);

                                    request.done(function(result){
                                        whoisPrivacyForm_handler(result);
                                    });

                                });

                                $("#wprivacy_hide").click(function(){
                                    var wprivacy_purchase = <?php echo $wprivacy_purchase ? 'true' : 'false'; ?>;

                                    if(wprivacy_purchase){
                                        open_modal('whois_privacy_purchase');
                                    }else{

                                        var request = MioAjax({
                                            button_element:$(this),
                                            waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                                            progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                                            action:"<?php echo $links["controller"]; ?>",
                                            method:"POST",
                                            data:{operation:"update_whois_privacy",status:"enable"},
                                        },true,true);

                                        request.done(function(result){
                                            whoisPrivacyForm_handler(result);
                                        });

                                    }
                                });

                            });

                            function whoisPrivacyForm_handler(result){
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
                        </script>

                        <div id="whois_privacy_purchase" style="display: none" data-izimodal-title="<?php echo __("admin/orders/domain-whois-hide"); ?>">
                            <div class="padding20">

                                <form action="<?php echo $links["controller"]; ?>" method="post" id="whoisPrivacyForm">
                                    <input type="hidden" name="operation" value="update_whois_privacy">
                                    <input type="hidden" name="status" value="enable">

                                    <div class="formcon">
                                        <div class="yuzde30"><?php echo __("admin/orders/domain-whois-privacy-price"); ?></div>
                                        <div class="yuzde70">
                                            <?php echo $wprivacy_price; ?>
                                        </div>
                                    </div>

                                    <div class="formcon">
                                        <div class="yuzde30"><?php echo __("admin/orders/domain-whois-privacy-invoice"); ?></div>
                                        <div class="yuzde70">
                                            <input onclick="$('#pmethods').fadeOut(200);" type="radio" name="invoice_status" value="unpaid" id="wprivacy-invoice-unpaid" class="radio-custom">
                                            <label for="wprivacy-invoice-unpaid" class="radio-custom-label" style="margin-right: 15px;"><?php echo __("admin/orders/invoice-unpaid"); ?></label>

                                            <input onclick="$('#pmethods').fadeIn(200);" type="radio" name="invoice_status" value="paid" id="wprivacy-invoice-paid" class="radio-custom">
                                            <label for="wprivacy-invoice-paid" class="radio-custom-label" style="margin-right: 15px;"><?php echo __("admin/orders/invoice-paid"); ?></label>
                                        </div>
                                    </div>

                                    <div class="formcon" id="pmethods" style="display: none;">
                                        <div class="yuzde30"><?php echo __("admin/orders/detail-pmethod"); ?></div>
                                        <div class="yuzde70">
                                            <select name="pmethod">
                                                <option value="none"><?php echo ___("needs/none"); ?></option>
                                                <?php
                                                    if($pmethods){
                                                        foreach($pmethods AS $k=>$v){
                                                            ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php
                                                        }
                                                    }
                                                ?>
                                            </select>
                                        </div>
                                    </div>

                                    <?php if($privOperation): ?>

                                        <a href="javascript:void(0);" id="whoisPrivacyForm_submit" class="yesilbtn gonderbtn"> <?php echo __("admin/orders/save-button"); ?></a>
                                    <?php endif; ?>

                                </form>


                                <div class="clear"></div>
                            </div>
                        </div>



                        <?php if($wprivacy): ?>
                            <a href="javascript:void(0);" id="wprivacy_show" class="turuncbtn gonderbtn"><?php echo __("admin/orders/domain-whois-show"); ?></a>
                        <?php else: ?>
                            <a href="javascript:void(0);" id="wprivacy_hide" class="mavibtn gonderbtn"><i class="fa fa-user-secret" aria-hidden="true"></i> <?php echo __("admin/orders/domain-whois-hide"); ?></a>
                        <?php endif; ?>
                        <br>
                        <?php if($wprivacy_endtime): ?>
                            <span class="kinfo" style="margin-left:15px;"> <?php echo __("admin/orders/domain-whois-privacy-endtime"); ?>: <strong><?php echo $wprivacy_endtime; ?></strong></span>
                        <?php endif; ?>
                        <div class="clear"></div>
                    </div>
                    <div class="clear"></div>
                </div><!-- whois tab content end -->

                <div id="content-dns" class="tabcontent"><!-- dns tab content start -->

                    <script type="text/javascript">
                        var record_k,record_type,record_name,record_value,record_identity,record_ttl,record_priority,record_names = [],record_values = [],record_identities = [];
                        var record_digest,record_key_tag,record_digest_type,record_algorithm;

                        $(document).ready(function(){

                            var tab_dns = _GET("dns");
                            if (tab_dns != '' && tab_dns != undefined) {
                                $("#tab-dns .tablinks[data-tab='" + tab_dns + "']").click();
                            }
                            else
                            {
                                $("#tab-dns .tablinks:eq(0)").addClass("active");
                                $("#tab-dns .tabcontent:eq(0)").css("display", "block");
                            }


                            <?php if($allow_dns_cns): ?>
                            $("#cns-wrap").html($("#template-loaderx").html());
                            setTimeout(function(){
                                reload_dns_cns();
                            },500);
                            <?php endif; ?>

                            <?php if($allow_dns_records): ?>
                            $("#getDnsRecords_tbody").html($("#template-loader").html());
                            setTimeout(function(){
                                reload_dns_records();
                            },500);
                            <?php endif; ?>

                            <?php if($allow_dns_sec_records): ?>
                            $("#getDnsSecRecords_tbody").html($("#template-loader").html());
                            setTimeout(function(){
                                reload_dns_sec_records();
                            },500);
                            <?php endif; ?>


                            $("#DnsRecord_type").change(function(){
                                var type = $(this).val();

                                $("#DnsRecord_priority").css("display","none");

                                if(type === "MX")
                                {
                                    $("#DnsRecord_priority").css("display","inline-block");
                                }

                                if(type === "A") $("#DnsRecord_value").attr("placeholder","The IPV4 Address");
                                else if(type === "AAAA") $("#DnsRecord_value").attr("placeholder","The IPV6 Address");
                                else if(type === "CNAME" || type === "MX") $("#DnsRecord_value").attr("placeholder","The Target Hostname");
                                else if(type === "TXT") $("#DnsRecord_value").attr("placeholder","The Text");
                                else
                                    $("#DnsRecord_value").attr("placeholder","");
                            });

                            $("#modifyCNS_submit").on("click",function(){
                                MioAjaxElement($(this),{
                                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                                    progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                                    result:"CNS_handler",
                                });
                            });

                            $("#ModifyDns_submit").on("click",function(){
                                MioAjaxElement($(this),{
                                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                                    progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                                    result:"ModifyDns_handler",
                                });
                            });

                        });

                        function deleteCNS(id){
                            if(!confirm("<?php echo htmlspecialchars(__("admin/orders/delete-are-youu-sure-cns")); ?>")) return false;

                            var request = MioAjax({
                                action:"<?php echo $links["controller"]; ?>",
                                method:"POST",
                                data:{operation:"domain_delete_cns",id:id},
                            },true,true);
                            request.done(function(result){
                                CNS_handler(result);
                            });
                        }

                        function CNS_handler(result){
                            if(result != ''){
                                var solve = getJson(result);
                                if(solve !== false){
                                    if(solve.status == "error"){
                                        if(solve.for != undefined && solve.for != ''){
                                            $("#addCNS "+solve.for).focus();
                                            $("#addCNS "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                            $("#addCNS "+solve.for).change(function(){
                                                $(this).removeAttr("style");
                                            });
                                        }
                                        if(solve.message != undefined && solve.message != '')
                                            alert_error(solve.message,{timer:5000});
                                    }else if(solve.status == "successful"){
                                        alert_success(solve.message,{timer:2000});
                                        setTimeout(function(){
                                           reload_dns_cns(true);
                                        },3000);
                                    }
                                }else
                                    console.log(result);
                            }
                        }

                        function reload_dns_cns(add_loader)
                        {
                            if(add_loader) $("#cns-wrap").html($("#template-loaderx").html());
                            $("#addCNS input[type=text]").val('');
                            $.get("<?php echo $links["controller"]; ?>?bring=cns-list",function(result){
                                $("#cns-wrap").html(result);
                            });
                        }

                        function reload_dns_records(add_loader)
                        {
                            if(add_loader) $("#getDnsRecords_tbody").html($("#template-loader").html());

                            $.get("<?php echo $links["controller"]; ?>?bring=dns-records",function(result){
                                $("#getDnsRecords_tbody").html(result);
                            });
                        }

                        function editDnsRecord(k)
                        {
                            record_k        = k;
                            record_type     = $("#DnsRecord_"+k+" input[name=type]").val();
                            record_name     = $("#DnsRecord_"+k+" .dns-record-name .show-wrap").html();
                            record_value    = $("#DnsRecord_"+k+" .dns-record-value .show-wrap").html();

                            record_names[k]         = record_name;
                            record_values[k]        = record_value;


                            $("#getDnsRecords_tbody .edit-wrap").css("display","none");
                            $("#getDnsRecords_tbody .edit-wrap-ttl").css("display","none");
                            $("#getDnsRecords_tbody .edit-wrap-priority").css("display","none");
                            $("#getDnsRecords_tbody .edit-content").css("display","none");
                            $("#getDnsRecords_tbody .show-wrap").css("display","block");
                            $("#getDnsRecords_tbody .show-wrap-ttl").css("display","block");
                            $("#getDnsRecords_tbody .show-wrap-priority").css("display","inline-block");
                            $("#getDnsRecords_tbody .no-edit-content").css("display","block");
                            $("#getDnsRecords_tbody tr").removeClass("editing-active");

                            $("#DnsRecord_"+k+" .dns-record-name input").val(record_name);
                            $("#DnsRecord_"+k+" .dns-record-value input").val(record_value);

                            $("#DnsRecord_"+k+" .edit-content").css("display","block");
                            $("#DnsRecord_"+k+" .edit-wrap").css("display","block");
                            $("#DnsRecord_"+k+" .edit-wrap-ttl").css("display","block");
                            $("#DnsRecord_"+k+" .edit-wrap-priority").css("display","inline-block");
                            $("#DnsRecord_"+k+" .no-edit-content").css("display","none");
                            $("#DnsRecord_"+k+" .show-wrap").css("display","none");
                            $("#DnsRecord_"+k+" .show-wrap-ttl").css("display","none");
                            $("#DnsRecord_"+k+" .show-wrap-priority").css("display","none");
                            $("#DnsRecord_"+k).addClass('editing-active');
                        }

                        function cancelDnsRecord(k)
                        {
                            $("#getDnsRecords_tbody .edit-wrap").css("display","none");
                            $("#getDnsRecords_tbody .edit-wrap-ttl").css("display","none");
                            $("#getDnsRecords_tbody .edit-wrap-priority").css("display","none");
                            $("#getDnsRecords_tbody .edit-content").css("display","none");
                            $("#getDnsRecords_tbody .show-wrap").css("display","block");
                            $("#getDnsRecords_tbody .show-wrap-ttl").css("display","block");
                            $("#getDnsRecords_tbody .show-wrap-priority").css("display","inline-block");
                            $("#getDnsRecords_tbody .no-edit-content").css("display","block");
                            $("#getDnsRecords_tbody tr").removeClass("editing-active");
                        }

                        function saveDnsRecord(k,el)
                        {
                            record_type         = $("#DnsRecord_"+k+" input[name=type]").val();
                            record_identity     = $("#DnsRecord_"+k+" input[name=identity]").val();
                            record_name         = $("#DnsRecord_"+k+" .dns-record-name input").val();
                            record_value        = $("#DnsRecord_"+k+" .dns-record-value input").val();
                            record_ttl          = $("#DnsRecord_"+k+" .dns-record-ttl .edit-wrap-ttl select").val();
                            record_priority     = $("#DnsRecord_"+k+" .dns-record-ttl .edit-wrap-priority input").val();

                            $("#DnsRecord_"+k+" .dns-record-name .show-wrap").html(record_name);
                            $("#DnsRecord_"+k+" .dns-record-value .show-wrap").html(record_value);

                            var request = MioAjax({
                                button_element:$(el),
                                waiting_text: '<i style="-webkit-animation:fa-spin 2s infinite linear;animation: fa-spin 2s infinite linear;" class="fa fa-spinner" aria-hidden="true"></i>',
                                action: "<?php echo $links["controller"]; ?>",
                                method: "POST",
                                data:
                                    {
                                        operation: "domain_update_dns_record",
                                        type:record_type,
                                        name:record_name,
                                        value:record_value,
                                        identity:record_identity,
                                        ttl:record_ttl,
                                        priority:record_priority,
                                    }
                            },true,true);

                            request.done(function(result){
                                if(result !== ''){
                                    var solve = getJson(result);
                                    if(solve !== false){
                                        if(solve.status === "error")
                                            alert_error(solve.message,{timer:3000});
                                        else if(solve.status === "successful")
                                        {
                                            alert_success("<?php echo __("website/account_products/domain-dns-records-8"); ?>",{timer:3000});
                                            reload_dns_records(true);
                                        }
                                    }else
                                        console.log(result);
                                }
                            });

                        }
                        function removeDnsRecord(k,el)
                        {
                            if(!confirm("<?php echo ___("needs/delete-are-you-sure"); ?>")) return false;

                            record_type         = $("#DnsRecord_"+k+" input[name=type]").val();
                            record_identity     = $("#DnsRecord_"+k+" input[name=identity]").val();
                            record_name         = $("#DnsRecord_"+k+" input[name=name]").val();
                            record_value        = $("#DnsRecord_"+k+" input[name=value]").val();


                            var request = MioAjax({
                                button_element:$(el),
                                waiting_text: "<?php echo addslashes(__("website/others/button1-pending")); ?>",
                                action: "<?php echo $links["controller"]; ?>",
                                method: "POST",
                                data:
                                    {
                                        operation: "domain_delete_dns_record",
                                        type:record_type,
                                        name:record_name,
                                        value:record_value,
                                        identity:record_identity
                                    }
                            },true,true);

                            request.done(function(result){
                                if(result !== ''){
                                    var solve = getJson(result);
                                    if(solve !== false){
                                        if(solve.status === "error")
                                            alert_error(solve.message,{timer:3000});
                                        else if(solve.status === "successful")
                                        {
                                            $("#DnsRecord_"+k).remove();

                                            alert_success("<?php echo __("website/account_products/domain-dns-records-9"); ?>",{timer:3000});
                                            //reload_dns_records(true);
                                        }
                                    }else
                                        console.log(result);
                                }
                            });
                        }

                        function addDnsRecord(el)
                        {
                            record_type         = $("#DnsRecord_type").val();
                            record_name         = $("#DnsRecord_name").val();
                            record_value        = $("#DnsRecord_value").val();
                            record_ttl          = $("#DnsRecord_ttl").val();
                            record_priority     = $("#DnsRecord_priority").val();

                            var request = MioAjax({
                                button_element:$(el),
                                waiting_text: "<?php echo addslashes(__("website/others/button1-pending")); ?>",
                                action: "<?php echo $links["controller"]; ?>",
                                method: "POST",
                                data:
                                    {
                                        operation: "domain_add_dns_record",
                                        type:record_type,
                                        name:record_name,
                                        value:record_value,
                                        ttl:record_ttl,
                                        priority:record_priority,
                                    }
                            },true,true);

                            request.done(function(result){
                                if(result !== ''){
                                    var solve = getJson(result);
                                    if(solve !== false){
                                        if(solve.status === "error")
                                            alert_error(solve.message,{timer:3000});
                                        else if(solve.status === "successful")
                                        {
                                            $("#DnsRecord_type").val('');
                                            $("#DnsRecord_name").val('');
                                            $("#DnsRecord_value").val('');


                                            alert_success("<?php echo __("website/account_products/domain-dns-records-7"); ?>",{timer:3000});
                                            reload_dns_records(true);
                                        }
                                    }else
                                        console.log(result);
                                }
                            });
                        }

                        function reload_dns_sec_records(add_loader)
                        {
                            if(add_loader) $("#getDnsSecRecords_tbody").html($("#template-loader").html());

                            $.get("<?php echo $links["controller"]; ?>?bring=dns-sec-records",function(result){
                                $("#getDnsSecRecords_tbody").html(result);
                            });
                        }

                        function addDnsSecRecord(el)
                        {
                            record_digest       = $("#DnsSecRecord_digest").val();
                            record_key_tag       = $("#DnsSecRecord_key_tag").val();
                            record_digest_type  = $("#DnsSecRecord_digest_type").val();
                            record_algorithm    = $("#DnsSecRecord_algorithm").val();

                            var request = MioAjax({
                                button_element:$(el),
                                waiting_text: "<?php echo addslashes(__("website/others/button1-pending")); ?>",
                                action: "<?php echo $links["controller"]; ?>",
                                method: "POST",
                                data:
                                    {
                                        operation: "domain_add_dns_sec_record",
                                        digest:record_digest,
                                        key_tag:record_key_tag,
                                        digest_type:record_digest_type,
                                        algorithm:record_algorithm,
                                    }
                            },true,true);

                            request.done(function(result){
                                if(result !== ''){
                                    var solve = getJson(result);
                                    if(solve !== false){
                                        if(solve.status === "error")
                                            alert_error(solve.message,{timer:3000});
                                        else if(solve.status === "successful")
                                        {
                                            $("#DnsSecRecord_digest").val('');
                                            $("#DnsSecRecord_key_tag").val('');
                                            $("#DnsSecRecord_digest_type").val('');
                                            $("#DnsSecRecord_algorithm").val('');


                                            alert_success("<?php echo __("website/account_products/domain-dns-records-7"); ?>",{timer:3000});
                                            reload_dns_sec_records(true);
                                        }
                                    }else
                                        console.log(result);
                                }
                            });
                        }

                        function removeDnsSecRecord(k,el)
                        {
                            if(!confirm("<?php echo ___("needs/delete-are-you-sure"); ?>")) return false;

                            record_identity         = $("#DnsSecRecord_"+k+" input[name=identity]").val();
                            record_digest           = $("#DnsSecRecord_"+k+" input[name=digest]").val();
                            record_key_tag           = $("#DnsSecRecord_"+k+" input[name=key_tag]").val();
                            record_digest_type      = $("#DnsSecRecord_"+k+" input[name=digest_type]").val();
                            record_algorithm        = $("#DnsSecRecord_"+k+" input[name=algorithm]").val();


                            var request = MioAjax({
                                button_element:$(el),
                                waiting_text: "<?php echo addslashes(__("website/others/button1-pending")); ?>",
                                action: "<?php echo $links["controller"]; ?>",
                                method: "POST",
                                data:
                                    {
                                        operation: "domain_delete_dns_sec_record",
                                        identity:record_identity,
                                        digest:record_digest,
                                        key_tag:record_key_tag,
                                        digest_type:record_digest_type,
                                        algorithm:record_algorithm
                                    }
                            },true,true);

                            request.done(function(result){
                                if(result !== ''){
                                    var solve = getJson(result);
                                    if(solve !== false){
                                        if(solve.status === "error")
                                            alert_error(solve.message,{timer:3000});
                                        else if(solve.status === "successful")
                                        {
                                            $("#DnsSecRecord_"+k).remove();

                                            alert_success("<?php echo __("website/account_products/domain-dns-records-9"); ?>",{timer:3000});
                                            //reload_dns_records(true);
                                        }
                                    }else
                                        console.log(result);
                                }
                            });
                        }

                        function ModifyDns_handler(result){
                            if(result != ''){
                                var solve = getJson(result);
                                if(solve !== false){
                                    if(solve.status == "error"){
                                        if(solve.for != undefined && solve.for != ''){
                                            $("#ModifyDns "+solve.for).focus();
                                            $("#ModifyDns "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                            $("#ModifyDns "+solve.for).change(function(){
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


                    <div class="adminpagecon">


                        <div id="tab-dns" class="subtab">

                            <ul class="tab">

                                <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'current','dns')" data-tab="current"><i class="fa fa-angle-right"></i> <?php echo __("website/account_products/domain-current-dns"); ?></a></li>

                                <?php if($allow_dns_cns): ?>
                                    <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'cns','dns')" data-tab="cns"><i class="fa fa-angle-right"></i> <?php echo __("admin/orders/domain-cns-management"); ?></a></li>
                                <?php endif; ?>

                                <?php if($allow_dns_records): ?>
                                    <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'records','dns')" data-tab="records"><i class="fa fa-angle-right"></i> <?php echo __("website/account_products/domain-dns-records-1"); ?></a></li>
                                <?php endif; ?>

                                <?php if($allow_dns_sec_records): ?>
                                    <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'sec-records','dns')" data-tab="sec-records"><i class="fa fa-angle-right"></i> <?php echo __("website/account_products/domain-dns-records-13"); ?></a></li>
                                <?php endif; ?>


                            </ul>

                            <div id="dns-current" class="tabcontent">
                                <div class="blue-info">
                                    <div class="padding15">
                                        <i class="fa fa-info-circle" aria-hidden="true"></i>
                                        <p><?php echo __("website/account_products/domain-dns-tx1"); ?></p>
                                    </div>
                                </div>

                                <div class="padding20">
                                    <form action="<?php echo $links["controller"]; ?>" method="post" id="ModifyDns">

                                        <input type="hidden" name="operation" value="domain_modify_dns">

                                        <div class="formcon">
                                            <div class="yuzde30" style="text-align:center;">#1</div>
                                            <div class="yuzde70"><input name="dns[]" value="<?php echo isset($options["ns1"]) ? $options["ns1"] : false; ?>" type="text" class="" placeholder="<?php echo"ns.".($order["options"]["domain"] ?? $order["name"]); ?>">
                                            </div>
                                        </div>

                                        <div class="formcon">
                                            <div class="yuzde30" style="text-align:center;">#2</div>
                                            <div class="yuzde70">
                                                <input name="dns[]" value="<?php echo isset($options["ns2"]) ? $options["ns2"] : false; ?>" type="text" class="" placeholder="<?php echo"ns.".($order["options"]["domain"] ?? $order["name"]); ?>">
                                            </div>
                                        </div>

                                        <div class="formcon">
                                            <div class="yuzde30" style="text-align:center;">#3</div>
                                            <div class="yuzde70">
                                                <input name="dns[]" value="<?php echo isset($options["ns3"]) ? $options["ns3"] : false; ?>" type="text" class="" placeholder="<?php echo"ns.".($order["options"]["domain"] ?? $order["name"]); ?>">
                                            </div>
                                        </div>

                                        <div class="formcon">
                                            <div class="yuzde30" style="text-align:center;">#4</div>
                                            <div class="yuzde70">
                                                <input name="dns[]" value="<?php echo isset($options["ns4"]) ? $options["ns4"] : false; ?>" type="text" class="" placeholder="<?php echo"ns.".($order["options"]["domain"] ?? $order["name"]); ?>">
                                            </div>
                                        </div>

                                        <div class="guncellebtn yuzde30" style="float: right;">
                                            <a href="javascript:void(0);" class="mavibtn gonderbtn" id="ModifyDns_submit"><?php echo __("website/account_products/domain-dns-update"); ?></a>
                                        </div>

                                    </form>
                                    <script type="text/javascript">
                                        $(document).ready(function(){
                                            $("#ModifyDns_submit").click(function(){
                                                MioAjaxElement($(this),{
                                                    waiting_text: "<?php echo addslashes(__("website/others/button1-pending")); ?>",
                                                    result: "ModifyDns_submit",
                                                });

                                            });
                                        });

                                        function ModifyDns_submit(result) {
                                            if(result != ''){
                                                var solve = getJson(result);
                                                if(solve !== false){
                                                    if(solve.status == "error"){
                                                        if(solve.for != undefined && solve.for != ''){
                                                            $("#ModifyDns "+solve.for).focus();
                                                            $("#ModifyDns "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                                            $("#ModifyDns "+solve.for).change(function(){
                                                                $(this).removeAttr("style");
                                                            });
                                                        }
                                                        if(solve.message != undefined && solve.message != '')
                                                            alert_error(solve.message,{timer:5000});
                                                    }else if(solve.status == "successful"){
                                                        alert_success(solve.message,{timer:3000});
                                                        if(solve.redirect != undefined && solve.redirect != ''){
                                                            setTimeout(function(){
                                                                window.location.href = solve.redirect;
                                                            },3000);
                                                        }
                                                    }
                                                }else
                                                    console.log(result);
                                            }
                                        }
                                    </script>
                                    <div class="clear"></div>
                                </div>
                            </div>

                            <?php if($allow_dns_cns): ?>
                                <div id="dns-cns" class="tabcontent" style="display: none">
                                    <div class="blue-info">
                                        <div class="padding15">
                                            <i class="fa fa-info-circle" aria-hidden="true"></i>
                                            <p><?php echo __("website/account_products/domain-dns-tx2"); ?></p>
                                        </div>
                                    </div>
                                    <div class="padding20">
                                        <form action="<?php echo $links["controller"]; ?>" method="post" id="addCNS">
                                            <input type="hidden" name="operation" value="domain_add_cns">

                                            <div class="yuzde33"><input name="ns" type="text" class="" placeholder="ns1.<?php echo $order["options"]["domain"] ?? $order["name"]; ?>"></div>
                                            <div class="yuzde33"><input name="ip" type="text" class="" placeholder="192.168.1.1"></div>
                                            <div class="yuzde33">
                                                <a style="width:240px;float:none;" href="javascript:void(0);" id="addCNS_submit" class="yesilbtn gonderbtn"><?php echo __("website/account_products/add-ns-button"); ?></a>
                                            </div>
                                        </form>
                                        <script type="text/javascript">
                                            $(document).ready(function(){
                                                $("#addCNS_submit").click(function(){
                                                    MioAjaxElement($(this),{
                                                        waiting_text: "<?php echo addslashes(__("website/others/button1-pending")); ?>",
                                                        result: "addCNS_handler",
                                                    });

                                                });
                                            });
                                            function addCNS_handler(result) {
                                                if(result != ''){
                                                    var solve = getJson(result);
                                                    if(solve !== false){
                                                        if(solve.status == "error"){
                                                            if(solve.for != undefined && solve.for != ''){
                                                                $("#addCNS "+solve.for).focus();
                                                                $("#addCNS "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                                                $("#addCNS "+solve.for).change(function(){
                                                                    $(this).removeAttr("style");
                                                                });
                                                            }
                                                            if(solve.message != undefined && solve.message != '')
                                                                alert_error(solve.message,{timer:5000});
                                                        }else if(solve.status == "successful"){
                                                            alert_success(solve.message,{timer:3000});
                                                            setTimeout(function(){
                                                                reload_dns_cns(true);
                                                            },3000);
                                                        }
                                                    }else
                                                        console.log(result);
                                                }
                                            }
                                            function editCnsBtn(el)
                                            {
                                                MioAjaxElement($(el),{
                                                    waiting_text: "<?php echo addslashes(__("website/others/button4-pending")); ?>",
                                                    result: "ModifyCNS",
                                                });
                                            }
                                            function deleteCnsBtn(el,id)
                                            {
                                                var request = MioAjax({
                                                    button_element: $(el),
                                                    action : "<?php echo $links["controller"];?>",
                                                    method : "POST",
                                                    data   :
                                                        {
                                                            operation: "domain_delete_cns",
                                                            id: id,
                                                        },
                                                    waiting_text: "<?php echo addslashes(__("website/others/button4-pending")); ?>",
                                                },true,true);
                                                request.done(DeleteCNS);
                                            }
                                            function delete_confirm() {
                                                var co = confirm("<?php echo __("website/account_products/delete-are-you-sure"); ?>");
                                                return co;
                                            }
                                            function DeleteCNS(result) {
                                                if(result != ''){
                                                    var solve = getJson(result);
                                                    if(solve !== false){
                                                        if(solve.status == "error"){
                                                            swal(
                                                                '<?php echo __("website/account_products/modal-error-title"); ?>',
                                                                solve.message,
                                                                'error'
                                                            )
                                                        }else if(solve.status == "successful"){
                                                            swal({
                                                                type: 'success',
                                                                title: '<?php echo __("website/account_products/modal-success-title"); ?>',
                                                                text: '<?php echo __("website/account_products/deleted-domain-cns"); ?>',
                                                                showConfirmButton: false,
                                                                timer: 1500
                                                            });
                                                            setTimeout(function(){
                                                                reload_dns_cns(true);
                                                            },1500);
                                                        }
                                                    }else
                                                        console.log(result);
                                                }
                                            }
                                            function ModifyCNS(result) {
                                                if(result != ''){
                                                    var solve = getJson(result);
                                                    if(solve !== false){
                                                        if(solve.status == "error"){
                                                            swal(
                                                                '<?php echo __("website/account_products/modal-error-title"); ?>',
                                                                solve.message,
                                                                'error'
                                                            )
                                                        }else if(solve.status == "successful"){
                                                            swal({
                                                                type: 'success',
                                                                title: '<?php echo __("website/account_products/modal-success-title"); ?>',
                                                                text: '<?php echo __("website/account_products/changed-domain-cns"); ?>',
                                                                showConfirmButton: false,
                                                                timer: 1500
                                                            });
                                                            setTimeout(function(){
                                                                reload_dns_cns(true);
                                                            },1500);
                                                        }
                                                    }else
                                                        console.log(result);
                                                }
                                            }
                                        </script>

                                        <div class="line"></div>

                                        <div id="cns-wrap"></div>

                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if($allow_dns_records): ?>
                                <div id="dns-records" class="tabcontent" style="display: none">
                                    <div class="blue-info">
                                        <div class="padding15">
                                            <i class="fa fa-info-circle" aria-hidden="true"></i>
                                            <p><?php echo __("website/account_products/domain-dns-tx3"); ?></p>
                                        </div>
                                    </div>
                                    <div class="DomainModuleChildPage">
                                        <table width="100%" id="getDnsRecords" border="0" cellpadding="0" cellspacing="0">
                                            <thead>
                                            <tr>
                                                <th data-orderable="false" align="left" width="15%"><?php echo __("website/account_products/domain-dns-records-2"); ?></th>
                                                <th data-orderable="false" align="left"><?php echo __("website/account_products/domain-dns-records-3"); ?></th>
                                                <th data-orderable="false" align="left"><?php echo __("website/account_products/domain-dns-records-4"); ?></th>
                                                <th data-orderable="false" align="center"><?php echo __("website/account_products/domain-dns-records-10"); ?></th>
                                                <th data-orderable="false" align="center"></th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr class="addDnsRecord_table">
                                                <td align="left">
                                                    <select id="DnsRecord_type">
                                                        <option value=""><?php echo __("website/account_products/domain-dns-records-2"); ?></option>
                                                        <?php
                                                            foreach($module_con->config["settings"]["dns-record-types"] AS $t)
                                                            {
                                                                ?>
                                                                <option><?php echo $t; ?></option>
                                                                <?php
                                                            }
                                                        ?>
                                                    </select>
                                                </td>
                                                <td align="left">
                                                    <input type="text" id="DnsRecord_name" placeholder="@">
                                                </td>
                                                <td align="left"><input type="text" id="DnsRecord_value" placeholder=""></td>
                                                <td align="center">
                                                    <select id="DnsRecord_ttl" style="width: 80px;">
                                                        <option value="">Auto</option>
                                                        <?php
                                                            foreach($ttl_times AS $ttl_k => $ttl_v)
                                                            {
                                                                ?>
                                                                <option value="<?php echo $ttl_k; ?>"><?php echo $ttl_v; ?></option>
                                                                <?php
                                                            }
                                                        ?>
                                                    </select>
                                                    <input type="number" style="width: 80px;display:none;" id="DnsRecord_priority" placeholder="Priority" value="">
                                                </td>
                                                <td align="center">
                                                    <a href="javascript:void 0;" class="lbtn green add-dns-record" onclick="addDnsRecord(this);"><i class="fa fa-plus"></i> <?php echo __("website/account_products/domain-dns-records-5"); ?></a>
                                                </td>
                                            </tr>
                                            </tbody>
                                            <tbody id="getDnsRecords_tbody"></tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if($allow_dns_sec_records): ?>
                                <div id="dns-sec-records" class="tabcontent" style="display: none">
                                    <div class="blue-info">
                                        <div class="padding15">
                                            <i class="fa fa-info-circle" aria-hidden="true"></i>
                                            <p><?php echo __("website/account_products/domain-dns-tx4"); ?></p>
                                        </div>
                                    </div>
                                    <div class="DomainModuleChildPage">
                                        <table width="100%" id="getDnsRecords" border="0" cellpadding="0" cellspacing="0">
                                            <thead>
                                            <tr>
                                                <th data-orderable="false" align="left">Digest</th>
                                                <th data-orderable="false" align="left">Key Tag</th>
                                                <th data-orderable="false" align="left">Digest Type</th>
                                                <th data-orderable="false" align="center">Algorithm</th>
                                                <th data-orderable="false" align="center"></th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr class="addDnsSecRecord_table">
                                                <td align="left">
                                                    <input type="text" id="DnsSecRecord_digest" placeholder="Digest">
                                                </td>
                                                <td align="left">
                                                    <input type="text" id="DnsSecRecord_key_tag" placeholder="Key Tag">
                                                </td>
                                                <td align="left">
                                                    <select id="DnsSecRecord_digest_type">
                                                        <option value=""><?php echo ___("needs/select-your"); ?></option>
                                                        <?php
                                                            foreach($module_con->config["settings"]["dns-digest-types"] AS $k => $t)
                                                            {
                                                                ?>
                                                                <option value="<?php echo $k; ?>"><?php echo $t; ?></option>
                                                                <?php
                                                            }
                                                        ?>
                                                    </select>
                                                </td>
                                                <td align="center">
                                                    <select id="DnsSecRecord_algorithm">
                                                        <option value=""><?php echo ___("needs/select-your"); ?></option>
                                                        <?php
                                                            foreach($module_con->config["settings"]["dns-algorithms"] AS $k => $t)
                                                            {
                                                                ?>
                                                                <option value="<?php echo $k; ?>"><?php echo $t; ?></option>
                                                                <?php
                                                            }
                                                        ?>
                                                    </select>
                                                </td>
                                                <td align="center">
                                                    <a href="javascript:void 0;" class="lbtn green add-dns-sec-record" onclick="addDnsSecRecord(this);"><i class="fa fa-plus"></i> <?php echo __("website/account_products/domain-dns-records-5"); ?></a>
                                                </td>
                                            </tr>
                                            </tbody>
                                            <tbody id="getDnsSecRecords_tbody"></tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>

                    </div>

                    <div class="clear"></div>
                </div>

                <?php if($allow_forwarding_dmn || $allow_forwarding_eml): ?>
                    <div id="content-forwarding" class="tabcontent">

                        <div class="tabcontentcon">
                            <script type="text/javascript">
                                $(document).ready(function(){
                                    var tab_forwarding = _GET("forwarding");
                                    if (tab_forwarding != '' && tab_forwarding != undefined) {
                                        $("#tab-forwarding .tablinks[data-tab='" + tab_forwarding + "']").click();
                                    }
                                    else
                                    {
                                        $("#tab-forwarding .tablinks:eq(0)").addClass("active");
                                        $("#tab-forwarding .tabcontent:eq(0)").css("display", "block");
                                    }

                                    $("#forwarding-domain-wrap").html($("#template-loaderx").html());
                                    reload_forwarding_domain();
                                });

                                function set_forward_domain(el)
                                {
                                    var request = MioAjax({
                                        button_element:el,
                                        waiting_text: "<?php echo addslashes(__("website/others/button1-pending")); ?>",
                                        action:"<?php echo $links["controller"]; ?>",
                                        method: "POST",
                                        data:{
                                            operation:"domain_set_forward_domain",
                                            protocol : $("#forward_protocol").val(),
                                            domain : $("#forward_domain").val(),
                                            method : $(".domainforwarding input[name=method]:checked").val(),
                                        },
                                    },true,true);

                                    request.done(function(result){
                                        if(result != ''){
                                            var solve = getJson(result);
                                            if(solve !== false){
                                                if(solve.status === "error")
                                                    alert_error(solve.message,{timer:3000});
                                                else if(solve.status === "successful"){
                                                    alert_success(solve.message,{timer:2000});
                                                    setTimeout(function(){
                                                        reload_forwarding_domain(true);
                                                    },2000);
                                                }
                                            }else
                                                console.log(result);
                                        }
                                    });
                                }
                                function cancel_forward_domain(el)
                                {
                                    var request = MioAjax({
                                        button_element:el,
                                        waiting_text: "<?php echo addslashes(__("website/others/button1-pending")); ?>",
                                        action:"<?php echo $links["controller"]; ?>",
                                        method: "POST",
                                        data:{
                                            operation:"domain_cancel_forward_domain"
                                        },
                                    },true,true);

                                    request.done(function(result){
                                        if(result != ''){
                                            var solve = getJson(result);
                                            if(solve !== false){
                                                if(solve.status === "error")
                                                    alert_error(solve.message,{timer:3000});
                                                else if(solve.status === "successful"){
                                                    alert_success(solve.message,{timer:2000});
                                                    setTimeout(function(){
                                                        reload_forwarding_domain(true);
                                                    },2000);
                                                }
                                            }else
                                                console.log(result);
                                        }
                                    });
                                }

                                function reload_forwarding_domain(add_loader)
                                {
                                    if(add_loader) $("#forwarding-domain-wrap").html($("#template-loaderx").html());

                                    $.get("<?php echo $links["controller"]; ?>?bring=forwarding-domain",function(result){
                                        $("#forwarding-domain-wrap").html(result);
                                    });
                                }
                            </script>

                            <div class="adminpagecon">
                                <div id="tab-forwarding" class="subtab">

                                    <ul class="tab">

                                        <?php if($allow_forwarding_dmn): ?>
                                            <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'domain','forwarding')" data-tab="domain"><i class="fa fa-angle-right"></i> <?php echo __("website/account_products/domain-forwarding-tx1"); ?></a></li>
                                        <?php endif; ?>

                                        <?php if($allow_forwarding_eml): ?>
                                            <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'email','forwarding')" data-tab="cns"><i class="fa fa-angle-right"></i> <?php echo __("website/account_products/domain-forwarding-tx2"); ?></a></li>
                                        <?php endif; ?>
                                    </ul>

                                    <?php if($allow_forwarding_dmn): ?>
                                        <div id="forwarding-domain" class="tabcontent">

                                            <div class="blue-info">
                                                <div class="padding15">
                                                    <i class="fa fa-info-circle" aria-hidden="true"></i>
                                                    <p><?php echo __("website/account_products/domain-forwarding-tx3"); ?></p>
                                                </div>
                                            </div>


                                            <div id="forwarding-domain-wrap"></div>


                                        </div>
                                    <?php endif; ?>

                                    <?php if($allow_forwarding_eml): ?>
                                        <script type="text/javascript">
                                            var email_forward_prefix,email_forward_target,email_forward_target_new,email_forward_identity;
                                            $(document).ready(function(){
                                                $("#getEmailForwards_tbody").html($("#template-loader2").html());
                                                setTimeout(function(){
                                                    reload_email_forwards();
                                                },500);
                                            });

                                            function reload_email_forwards(add_loader)
                                            {
                                                if(add_loader) $("#getEmailForwards_tbody").html($("#template-loader2").html());

                                                $.get("<?php echo $links["controller"]; ?>?bring=email-forwards",function(result){
                                                    $("#getEmailForwards_tbody").html(result);
                                                });
                                            }

                                            function editEmailForward(k)
                                            {
                                                email_forward_prefix        = $("#EmailForward_"+k+" input[name=prefix]").val();
                                                email_forward_target        = $("#EmailForward_"+k+" input[name=target]").val();
                                                email_forward_identity      = $("#EmailForward_"+k+" input[name=identity]").val();


                                                $("#getEmailForwards_tbody .edit-wrap").css("display","none");
                                                $("#getEmailForwards_tbody .edit-content").css("display","none");
                                                $("#getEmailForwards_tbody .show-wrap").css("display","block");
                                                $("#getEmailForwards_tbody .no-edit-content").css("display","block");
                                                $("#getEmailForwards_tbody tr").removeClass("editing-active");

                                                $("#EmailForward_"+k+" .email-forward-target input").val(email_forward_target);

                                                $("#EmailForward_"+k+" .edit-content").css("display","block");
                                                $("#EmailForward_"+k+" .edit-wrap").css("display","block");
                                                $("#EmailForward_"+k+" .no-edit-content").css("display","none");
                                                $("#EmailForward_"+k+" .show-wrap").css("display","none");
                                                $("#EmailForward_"+k).addClass('editing-active');
                                            }

                                            function cancelEmailForward(k)
                                            {
                                                $("#getEmailForwards_tbody .edit-wrap").css("display","none");
                                                $("#getEmailForwards_tbody .edit-content").css("display","none");
                                                $("#getEmailForwards_tbody .show-wrap").css("display","block");
                                                $("#getEmailForwards_tbody .no-edit-content").css("display","block");
                                                $("#getEmailForwards_tbody tr").removeClass("editing-active");
                                            }

                                            function saveEmailForward(k,el)
                                            {
                                                email_forward_prefix        = $("#EmailForward_"+k+" input[name=prefix]").val();
                                                email_forward_target        = $("#EmailForward_"+k+" input[name=target]").val();
                                                email_forward_target_new    = $("#EmailForward_"+k+" .email-forward-target input").val();
                                                email_forward_identity  = $("#EmailForward_"+k+" input[name=identity]").val();


                                                $("#EmailForward_"+k+" .email-forward-target .show-wrap").html(email_forward_target);

                                                var request = MioAjax({
                                                    button_element:$(el),
                                                    waiting_text: '<i style="-webkit-animation:fa-spin 2s infinite linear;animation: fa-spin 2s infinite linear;" class="fa fa-spinner" aria-hidden="true"></i>',
                                                    action: "<?php echo $links["controller"]; ?>",
                                                    method: "POST",
                                                    data:
                                                        {
                                                            operation: "domain_update_email_forward",
                                                            prefix:email_forward_prefix,
                                                            target:email_forward_target,
                                                            target_new:email_forward_target_new,
                                                            identity:email_forward_identity,
                                                        }
                                                },true,true);

                                                request.done(function(result){
                                                    if(result !== ''){
                                                        var solve = getJson(result);
                                                        if(solve !== false){
                                                            if(solve.status === "error")
                                                                alert_error(solve.message,{timer:3000});
                                                            else if(solve.status === "successful")
                                                            {
                                                                alert_success("<?php echo __("website/account_products/domain-dns-records-8"); ?>",{timer:3000});
                                                                reload_email_forwards(true);
                                                            }
                                                        }else
                                                            console.log(result);
                                                    }
                                                });

                                            }
                                            function removeEmailForward(k,el)
                                            {
                                                if(!confirm("<?php echo ___("needs/delete-are-you-sure"); ?>")) return false;

                                                email_forward_prefix    = $("#EmailForward_"+k+" input[name=prefix]").val();
                                                email_forward_target    = $("#EmailForward_"+k+" input[name=target]").val();
                                                email_forward_identity  = $("#EmailForward_"+k+" input[name=identity]").val();


                                                var request = MioAjax({
                                                    button_element:$(el),
                                                    waiting_text: '<i style="-webkit-animation:fa-spin 2s infinite linear;animation: fa-spin 2s infinite linear;" class="fa fa-spinner" aria-hidden="true"></i>',
                                                    action: "<?php echo $links["controller"]; ?>",
                                                    method: "POST",
                                                    data:
                                                        {
                                                            operation: "domain_delete_email_forward",
                                                            prefix:email_forward_prefix,
                                                            target:email_forward_target,
                                                            identity:email_forward_identity
                                                        }
                                                },true,true);

                                                request.done(function(result){
                                                    if(result !== ''){
                                                        var solve = getJson(result);
                                                        if(solve !== false){
                                                            if(solve.status === "error")
                                                                alert_error(solve.message,{timer:3000});
                                                            else if(solve.status === "successful")
                                                            {
                                                                $("#EmailForward_"+k).remove();

                                                                alert_success("<?php echo __("website/account_products/domain-forwarding-tx17"); ?>",{timer:3000});
                                                            }
                                                        }else
                                                            console.log(result);
                                                    }
                                                });
                                            }

                                            function addEmailForward(el)
                                            {
                                                email_forward_prefix      = $("#EmailForward_prefix").val();
                                                email_forward_target      = $("#EmailForward_target").val();

                                                var request = MioAjax({
                                                    button_element:$(el),
                                                    waiting_text: "<?php echo addslashes(__("website/others/button1-pending")); ?>",
                                                    action: "<?php echo $links["controller"]; ?>",
                                                    method: "POST",
                                                    data:
                                                        {
                                                            operation: "domain_add_email_forward",
                                                            prefix:email_forward_prefix,
                                                            target:email_forward_target,
                                                        }
                                                },true,true);

                                                request.done(function(result){
                                                    if(result !== ''){
                                                        var solve = getJson(result);
                                                        if(solve !== false){
                                                            if(solve.status === "error")
                                                                alert_error(solve.message,{timer:3000});
                                                            else if(solve.status === "successful")
                                                            {
                                                                $("#EmailForward_prefix").val('');
                                                                $("#EmailForward_target").val('');

                                                                alert_success("<?php echo __("website/account_products/domain-forwarding-tx16"); ?>",{timer:3000});
                                                                reload_email_forwards(true);
                                                            }
                                                        }else
                                                            console.log(result);
                                                    }
                                                });
                                            }
                                        </script>
                                        <div id="forwarding-email" class="tabcontent">
                                            <table width="100%" id="getEmailForwards" border="0" cellpadding="0" cellspacing="0">
                                                <thead>
                                                <tr>
                                                    <th data-orderable="false" align="left"><?php echo __("website/account_products/domain-forwarding-tx14"); ?></th>
                                                    <th></th>
                                                    <th data-orderable="false" align="left"><?php echo __("website/account_products/domain-forwarding-tx15"); ?></th>
                                                    <th data-orderable="false" align="center"></th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <tr class="addEmailForward_table">
                                                    <td align="left">
                                                        <input type="text" id="EmailForward_prefix" value="" placeholder="Prefix" style="width: 150px;float:left;"><strong style="float: left;">@<?php echo $options["domain"]; ?></strong>
                                                    </td>
                                                    <td align="left">
                                                        <i class="fas fa-long-arrow-alt-right" style="font-size: 30px;"></i>
                                                    </td>
                                                    <td align="left">
                                                        <input type="text" id="EmailForward_target" placeholder="email@example.com" style="width: 100%;">
                                                    </td>
                                                    <td align="center">
                                                        <a href="javascript:void 0;" class="lbtn green add-email-forward" onclick="addEmailForward(this);"><i class="fa fa-plus"></i> <?php echo ___("needs/button-create"); ?></a>
                                                    </td>
                                                </tr>
                                                </tbody>
                                                <tbody id="getEmailForwards_tbody"></tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>

                                </div>
                            </div>

                        </div>

                    </div>
                <?php endif; ?>

                <div id="content-history" class="tabcontent"><!-- history tab content start -->

                    <script type="text/javascript">
                        $(document).ready(function(){
                            $('#history-table').DataTable({
                                "columnDefs": [
                                    {
                                        "targets": [0],
                                        "visible":false,
                                        "searchable": false
                                    },
                                ],
                                "aaSorting" : [[0, 'asc']],
                                "lengthMenu": [
                                    [10, 25, 50, -1], [10, 25, 50, "<?php echo ___("needs/allOf"); ?>"]
                                ],
                                responsive: true,
                                "language":{"url":"<?php echo APP_URI; ?>/<?php echo ___("package/code"); ?>/datatable/lang.json"}
                            });
                        });
                    </script>
                    <table width="100%" id="history-table" class="table table-striped table-borderedx table-condensed nowrap">
                        <thead style="background:#ebebeb;">
                        <tr>
                            <th align="left">#</th>
                            <th data-orderable="false" align="left"><?php echo __("admin/orders/detail-history-th-by"); ?></th>
                            <th data-orderable="false" align="left"><?php echo __("admin/orders/detail-history-th-desc"); ?></th>
                            <th data-orderable="false" align="center"><?php echo __("admin/orders/detail-history-th-date"); ?></th>
                            <th data-orderable="false" align="center"><?php echo __("admin/users/detail-actions-th-ip"); ?></th>
                        </tr>
                        </thead>
                        <tbody align="center" style="border-top:none;">
                        <?php
                            $users = [];
                            $list = Events::getList('log','order',$order["id"],false,false,0,'id DESC');
                            if($list){
                                foreach($list AS $i => $row){
                                    $row['data'] = Utility::jdecode($row['data'],true);
                                    $user_detail    = ___("needs/system");
                                    $user           = [];
                                    if($row["user_id"] > 0)
                                    {
                                        if(isset($users[$row['user_id']]))
                                            $user = $users[$row['user_id']];
                                        else
                                        {
                                            $users[$row['user_id']] = User::getData($row['user_id'],'type,full_name','assoc');
                                            $user = $users[$row['user_id']];
                                        }
                                    }

                                    if($user){
                                        $user_detail = $user["full_name"];
                                        if($user["type"] == "admin")
                                            $user_detail = "<a href='".Controllers::$init->AdminCRLink("admins-dl",[$row["user_id"]])."' target='_blank' style='color:green;'>".$user_detail."</a>";
                                        elseif($user["type"] == "member")
                                            $user_detail = "<a href='".Controllers::$init->AdminCRLink("users-2",["detail",$row["user_id"]])."' target='_blank'>".$user_detail."</a>";
                                    }
                                    ?>
                                    <tr>
                                        <td align="left"><?php echo $i; ?></td>
                                        <td align="left">
                                            <?php
                                                echo $user_detail;
                                            ?>
                                        </td>
                                        <td align="left">
                                            <?php
                                                echo Events::order_log_description($row);
                                            ?>
                                        </td>
                                        <td align="center">
                                            <?php echo DateManager::format(Config::get("options/date-format")." H:i",$row["cdate"])?>
                                        </td>
                                        <td align="center">
                                            <?php echo isset($row['data']['ip']) ? $row['data']['ip'] : '-'; ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            }
                        ?>
                        </tbody>
                    </table>
                    <div class="clear"></div>

                </div><!-- history tab content end -->

                <div class="clear"></div>
            </div><!-- dns tab content end -->


        </div><!-- tab wrap content end -->


        <div class="clear"></div>
    </div>
</div>


</div>

<?php include __DIR__.DS."inc".DS."footer.php"; ?>

</body>
</html>