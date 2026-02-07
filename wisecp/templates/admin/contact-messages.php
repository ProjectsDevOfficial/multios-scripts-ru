<?php
    $inc    = Filter::init("GET/bring","route");
    if($inc){

        die();
    }
?>
<!DOCTYPE html>
<html>
<head>
    <?php
        $privOperation  = Admin::isPrivilege("CONTACT_FORM_OPERATION");
        $privDelete     = Admin::isPrivilege("CONTACT_FORM_DELETE");
        $plugins        = ['dataTables','select2'];
        include __DIR__.DS."inc".DS."head.php";
    ?>

    <style type="text/css">
        #datatable tbody tr td:nth-child(1),#datatable tbody tr td:nth-child(2) {
            text-align: left;
        }
    </style>
    <script type="text/javascript">
        var table,predefinedReply,lang,message,admin_message,name,email,phone,ip;
        $(document).ready(function() {

            $("#readMessage").iziModal(get_modal_options_generate({width:'800px'}));


            predefinedReply = $('#PredefinedReply');

            $("#datatable").on("click",".read-msg",function(){

                $("#readMessage").iziModal('open');

                let trElement   = $(this).parent().parent();

                message         = $(".message-msg",trElement).html();
                message         = nl2br(message);
                admin_message   = $(".message-admin-msg",trElement).html();

                name        = $(".message-name",trElement).html();
                lang        = $(".message-lang",trElement).html();
                email       = $(".message-email",trElement).html();
                phone       = $(".message-phone",trElement).html();
                ip          = $(".message-ip",trElement).html();
                name        =  strip_tags(name);

                $("input[name=id]").val($(this).data("id"));
                $("#message-msg").html(html_entity_decode(message));
                $("#message-name").html(name);
                $("#message-email").html('( '+email+' )');
                $("#message-ip").attr("href","https://check-host.net/ip-info?host="+ip).html(ip);
                $("#message-lang").html(lang);
                if(admin_message === '')
                {
                    $("#admin_message").val('').removeAttr("disabled");
                    $("#replyMessage_disable").css("display","none");
                    $("#replyMessage_submit").css("display","block");
                }
                else
                {
                    $("#admin_message").val(admin_message).attr("disabled",true);
                    $("#replyMessage_submit").css("display","none");
                    $("#replyMessage_disable").css("display","block");
                }

                predefinedReply.html($("#PredefinedReplyClone").html());
                $('optgroup[data-lang!='+lang+']',predefinedReply).remove();



                predefinedReply.select2({width:'100%'});

                if($('.select2-selection').length > 0) predefinedReply.select2('destroy');
                predefinedReply.select2({width:'100%'});

            });


            table = $('#datatable').DataTable({
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

        function deleteMessage(id){

            if(typeof id == "object" && id.length>1){
                $("#password_wrapper").css("display","block");
            }else
                $("#password_wrapper").css("display","none");

            $("#password1").val('');

            var content = "<?php echo __("admin/manage-website/delete-are-you-sure"); ?>";
            $("#confirmModal_text").html(content);

            open_modal("ConfirmModal",{
                title:"<?php echo __("admin/manage-website/delete-messages-title"); ?>"
            });

            $("#delete_ok").click(function(){
                var password = $('#password1').val();
                var request = MioAjax({
                    button_element:$(this),
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    action: "<?php echo $links["controller"]; ?>",
                    method: "POST",
                    data: {operation:"delete_message",id:id,password:password}
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
                                    $("#password1").val('');
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

        function readMessage(id){
            var request = MioAjax({
                action: "<?php echo $links["controller"]; ?>",
                method: "POST",
                data: {operation:"read_message",id:id}
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
                                table.ajax.reload();
                            }
                        }else
                            console.log(result);
                    }
                }else console.log(result);
            });

        }

        function applySelection(element){
            var selection = $(element).val();
            if(selection == ''){

            }else{
                $(element).val('');

                var values = [],value;
                $('.selected-item:checked').each(function(){
                    value       = $(this).val();
                    if(value) values.push(value);
                });
                if(values.length==0) return false;

                if(selection == "delete"){
                    deleteMessage(values);
                }
            }
        }

        function change_predefined_reply(elem) {
            let output   = $(elem).val();

            output = output.replace(/<br\s*[\/]?>/gi, '\n');
            output = output.replace(/<p\s*[\/]?>/gi, '');
            output = output.replace(/<\/p\s*[\/]?>/gi, '\n');
            output = output.replace(/<[^>]+>/g, '');
            output = output.trim();


            $("#admin_message").val(output);

        }

    </script>

</head>
<body>

<div id="ConfirmModal" style="display: none;">
    <div class="padding20">
        <p id="confirmModal_text"></p>

        <div align="center">

            <div id="password_wrapper" style="display: none;">
                <label><?php echo ___("needs/permission-delete-item-password-desc"); ?><br><br><strong><?php echo ___("needs/permission-delete-item-password"); ?></strong> <br><input type="password" id="password1" value="" placeholder="********"></label>
                <div class="clear"></div>
                <br>
            </div>
        </div>
    </div>
    <div class="modal-foot-btn">
        <a id="delete_ok" href="javascript:void(0);" class="red lbtn"><?php echo ___("needs/yes"); ?></a>
    </div>
</div>

<div id="readMessage" style="display: none;" data-izimodal-title="<?php echo __("admin/manage-website/modal-read-message"); ?>">
    <div class="padding20">

        <form action="<?php echo $links["controller"]; ?>" method="post" id="replyMessage">
            <input type="hidden" name="operation" value="reply_message">
            <input type="hidden" name="id" value="0">

            <div class="formcon">
                <div class="yuzde30"><?php echo __("admin/manage-website/messages-name"); ?></div>
                <div class="yuzde70">
                    <span id="message-name"></span>
                    <i id="message-email" style="font-size:14px;margin-left:5px;">( example@gmail.com )</i>
                    <i>( IP: <a style="text-decoration:underline;" referrerpolicy="no-referrer" href="https://check-host.net/ip-info?host=0.0.0.0" target="_blank" id="message-ip">0.0.0.0</a> )</i>
                </div>
            </div>

            <div class="formcon" id="user-message-wrap">
                <div class="yuzde30">
                    <?php echo __("admin/manage-website/messages-message"); ?> (<span id="message-lang"></span>)
                </div>
                <div class="yuzde70" id="message-msg"></div>
            </div>

            <?php if($privOperation): ?>
                <!--
                <div class="formcon" style="border:none">
                    <div class="yuzde30"><?php echo __("admin/manage-website/messages-reply-message"); ?></div>
                </div>
                -->

                <div class="formcon" id="predefined-replies-wrap">
                    <div class="yuzde30"><?php echo __("admin/tickets/request-detail-predefined-replies"); ?></div>
                    <div class="yuzde70">
                        <select name="predefined-reply" onchange="change_predefined_reply(this);" id="PredefinedReply"></select>
                        <select id="PredefinedReplyClone" style="display: none;">
                            <option value=""><?php echo ___("needs/select-your"); ?></option>
                            <?php
                                if($predefined_replies)
                                {
                                    foreach($predefined_replies AS $lk => $prs)
                                    {
                                        foreach($prs AS $category){
                                            if($category["items"]){
                                                ?>
                                                <optgroup label="<?php echo $category["title"]; ?>" data-lang="<?php echo strtoupper($lk); ?>">
                                                    <?php
                                                        foreach($category["items"] AS $item){
                                                            ?><option value="<?php echo htmlspecialchars($item["message"],ENT_QUOTES); ?>"><?php echo $item["name"]; ?></option><?php
                                                        }
                                                    ?>
                                                </optgroup>
                                                <?php
                                            }
                                        }
                                    }
                                }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="formcon" id="admin-message-wrap">
                    <textarea name="message" id="admin_message" rows="7"></textarea>
                    <span class="kinfo"><?php echo __("admin/manage-website/messages-reply-message-info"); ?></span>
                </div>

                <div class="clear"></div>
                <div style="float: right;" class="guncellebtn yuzde50">
                    <a id="replyMessage_submit" href="javascript:void(0);" class="gonderbtn yesilbtn"><?php echo __("admin/manage-website/button-reply-message"); ?></a>
                    <a id="replyMessage_disable" href="javascript:void(0);" style="display: none;" class="gonderbtn graybtn"><?php echo __("admin/manage-website/button-reply-message"); ?></a>
                </div>
            <?php endif; ?>

            <div class="clear"></div>

        </form>
        <script type="text/javascript">
            $(document).ready(function(){

                $("#replyMessage_submit").on("click",function(){
                    MioAjaxElement($(this),{
                        waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                        progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                        result:"replyMessage_handler",
                    });
                });

            });

            function replyMessage_handler(result){
                if(result != ''){
                    var solve = getJson(result);
                    if(solve !== false){
                        if(solve.status == "error"){
                            if(solve.for != undefined && solve.for != ''){
                                $("#replyMessage "+solve.for).focus();
                                $("#replyMessage "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                $("#replyMessage "+solve.for).change(function(){
                                    $(this).removeAttr("style");
                                });
                            }
                            if(solve.message != undefined && solve.message != '')
                                alert_error(solve.message,{timer:5000});
                        }else if(solve.status == "successful"){
                            alert_success(solve.message,{timer:2000});
                            table.ajax.reload();
                            $("#readMessage").iziModal('close');
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



    </div>
</div>

<?php include __DIR__.DS."inc/header.php"; ?>

<div id="wrapper">

    <div class="icerik-container">
        <div class="icerik">

            <div class="icerikbaslik">
                <h1>
                    <strong><?php echo __("admin/manage-website/page-messages-list");?></strong>
                </h1>

                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>


            <div class="clear"></div>

            <?php if($privDelete): ?>
                <select class="applyselect" id="selectApply" onchange="applySelection(this);">
                    <option value=""><?php echo __("admin/manage-website/apply-to-selected"); ?></option>
                    <?php if($privDelete): ?>
                        <option value="delete"><?php echo __("admin/manage-website/apply-to-selected-delete"); ?></option>
                    <?php endif; ?>
                </select>
                <div class="clear"></div>
            <?php endif; ?>

            <table width="100%" id="datatable" class="table table-striped table-borderedx table-condensed nowrap">
                <thead style="background:#ebebeb;">
                <tr>
                    <th align="left" data-orderable="false">#</th>
                    <?php
                        if($privDelete){
                            ?>
                            <th align="left" data-orderable="false">
                                <input type="checkbox" class="checkbox-custom" id="allSelect" onchange="$('.selected-item').prop('checked',$(this).prop('checked'));"><label for="allSelect" class="checkbox-custom-label"></label>
                            </th>
                            <?php
                        }
                    ?>
                    <th align="left" data-orderable="false"><?php echo __("admin/manage-website/messages-name"); ?></th>
                    <th align="center" data-orderable="false"><?php echo __("admin/manage-website/messages-email"); ?></th>
                    <th align="center" data-orderable="false"><?php echo __("admin/manage-website/messages-phone"); ?></th>
                    <th align="center" data-orderable="false"><?php echo __("admin/manage-website/messages-date"); ?></th>
                    <th align="center" data-orderable="false"><?php echo __("admin/manage-website/messages-ip"); ?></th>
                    <th align="center" data-orderable="false"></th>
                </tr>
                </thead>
                <tbody align="center" style="border-top:none;"></tbody>
            </table>

            <div class="clear"></div>

        </div>
    </div>


</div>

<?php include __DIR__.DS."inc".DS."footer.php"; ?>

</body>
</html>