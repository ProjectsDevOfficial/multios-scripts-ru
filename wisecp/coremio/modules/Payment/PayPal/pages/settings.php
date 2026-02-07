<?php
    if(!defined("CORE_FOLDER")) die();
    $LANG       = $module->lang;
    $CONFIG     = $module->config;

    $notify_url = Controllers::$init->CRLink("payment",['PayPal',$module->get_auth_token(),'callback']);
    $success_url = Controllers::$init->CRLink("pay-successful");
    $failed_url  = Controllers::$init->CRLink("pay-failed");
    $countries      = AddressManager::getCountries('t1.id,t1.a2_iso,t2.name');
?>
<form action="<?php echo Controllers::$init->getData("links")["controller"]; ?>" method="post" id="PayPalForm">
    <input type="hidden" name="operation" value="module_controller">
    <input type="hidden" name="module" value="PayPal">
    <input type="hidden" name="controller" value="settings">


    <div class="blue-info">
        <div class="padding20">
            <i class="fa fa-info-circle"></i>
            <p><?php echo $LANG["description"]; ?></p>
        </div>
    </div>

    <div class="clear"></div>

    <script type="text/javascript">
        $(document).ready(function(){
            changed_type("basic");
        });
        function changed_type(value,el)
        {
            if(el !== undefined)
            {
                $("#module-PayPal .modules-tab-item").removeClass("active");
                $(el).addClass("active");
            }

            $(".type-contents").css("display","none");
            $(".type-is-"+value).css("display","block");
        }
    </script>
    <style type="text/css">
        #module-PayPal .tab {display: block;width: 100%;}
        #module-PayPal .tab li {display: block; float: left;width: 20%;}
    </style>

    <ul class="modules-tabs">
        <li><a class="modules-tab-item active" href="javascript:void 0;" onclick="changed_type('basic',this);"><?php echo $LANG["type-basic"]; ?></a></li>
        <li><a class="modules-tab-item" href="javascript:void 0;" onclick="changed_type('subscription',this);"><?php echo $LANG["type-subscription"]; ?></a></li>
    </ul>


    <div class="formcon type-contents type-is-subscription" style="margin-top:15px;">
        <div class="yuzde30"><?php echo $LANG["subscription-status"]; ?></div>
        <div class="yuzde70">
            <input<?php echo isset($CONFIG["settings"]["subscription_status"]) && $CONFIG["settings"]["subscription_status"] ? ' checked' : ''; ?> type="checkbox" name="subscription_status" value="1" id="subscription_status" class="sitemio-checkbox">
            <label class="sitemio-checkbox-label" for="subscription_status"></label>
            <span class="kinfo"><?php echo $LANG["subscription-status-desc"]; ?></span>
        </div>
    </div>


    <div class="formcon">
        <div class="yuzde30">Client ID</div>
        <div class="yuzde70">
            <input type="text" name="client_id" value="<?php echo isset($CONFIG["settings"]["client_id"]) ? $CONFIG["settings"]["client_id"] : ''; ?>">
            <span class="kinfo"><?php echo $LANG["api-credentials"] ?? ''; ?></span>
        </div>
    </div>

    <div class="formcon">
        <div class="yuzde30">Client Secret</div>
        <div class="yuzde70">
            <input type="text" name="secret_key" value="<?php echo isset($CONFIG["settings"]["secret_key"]) ? $CONFIG["settings"]["secret_key"] : ''; ?>">
            <span class="kinfo"><?php echo $LANG["api-credentials"] ?? ''; ?></span>
        </div>
    </div>


    <div class="formcon type-contents type-is-basic">
        <div class="yuzde30"><?php echo $LANG["email"]; ?></div>
        <div class="yuzde70">
            <input type="text" name="email" value="<?php echo $CONFIG["settings"]["email"]; ?>">
            <span class="kinfo"><?php echo $LANG["email-desc"]; ?></span>
        </div>
    </div>

    <div class="formcon type-contents type-is-basic">
        <div class="yuzde30"><?php echo $LANG["commission-rate"]; ?></div>
        <div class="yuzde70">
            <input type="text" name="commission_rate" value="<?php echo $CONFIG["settings"]["commission_rate"]; ?>" style="width: 80px;">
            <span class="kinfo"><?php echo $LANG["commission-rate-desc"]; ?></span>
        </div>
    </div>

    <div class="formcon type-contents type-is-basic">
        <div class="yuzde30"><?php echo $LANG["convert-to"]; ?></div>
        <div class="yuzde70">
            <select name="convert_to">
                <option value=""><?php echo ___("needs/none"); ?></option>
                <?php
                    foreach (Money::getCurrencies(isset($CONFIG["settings"]["convert_to"]) ? $CONFIG["settings"]["convert_to"] : 0) AS $c)
                    {
                        ?>
                        <option<?php echo isset($CONFIG["settings"]["convert_to"]) && $CONFIG["settings"]["convert_to"] == $c["id"] ? ' selected' : ''; ?> value="<?php echo $c["id"]; ?>"><?php echo $c["name"]." (".$c["code"].")"; ?></option>
                        <?php
                    }
                ?>
            </select>
            <span class="kinfo"><?php echo $LANG["convert-to-desc"]; ?></span>
        </div>
    </div>

    <div class="formcon">
        <div class="yuzde30"><?php echo __("admin/modules/accepted-countries"); ?></div>
        <div class="yuzde70">
            <select name="accepted_countries[]" id="accepted_countries" multiple>
                <?php
                    $values = $CONFIG["settings"]["accepted_countries"] ?? [];
                    foreach($countries AS $country){
                        ?>
                        <option<?php echo in_array($country['a2_iso'],$values) ? ' selected' : ''; ?> value="<?php echo $country["a2_iso"]; ?>" data-image="<?php echo View::$init->get_resources_url("assets/images/flags/".strtolower($country["a2_iso"]).".svg"); ?>" data-a2-iso="<?php echo $country["a2_iso"]; ?>"><?php echo $country["name"]; ?></option>
                        <?php
                    }
                ?>
            </select>
            <span class="kinfo"><?php echo __("admin/modules/accepted-countries-desc"); ?></span>
        </div>
    </div>

    <div class="formcon">
        <div class="yuzde30"><?php echo __("admin/modules/unaccepted-countries"); ?></div>
        <div class="yuzde70">
            <select name="unaccepted_countries[]" id="unaccepted_countries" multiple>
                <?php
                    $values = $CONFIG["settings"]["unaccepted_countries"] ?? [];
                    foreach($countries AS $country){
                        ?>
                        <option<?php echo in_array($country['a2_iso'],$values) ? ' selected' : ''; ?> value="<?php echo $country["a2_iso"]; ?>" data-image="<?php echo View::$init->get_resources_url("assets/images/flags/".strtolower($country["a2_iso"]).".svg"); ?>" data-a2-iso="<?php echo $country["a2_iso"]; ?>"><?php echo $country["name"]; ?></option>
                        <?php
                    }
                ?>
            </select>
            <span class="kinfo"><?php echo __("admin/modules/unaccepted-countries-desc"); ?></span>
        </div>
    </div>


    <div class="formcon type-contents type-is-subscription">
        <div class="yuzde30"><?php echo $LANG["force-subscription"]; ?></div>
        <div class="yuzde70">
            <input<?php echo isset($CONFIG["settings"]["force_subscription"]) && $CONFIG["settings"]["force_subscription"] ? ' checked' : ''; ?> type="checkbox" name="force_subscription" value="1" id="PayPal_force_subscription" class="checkbox-custom">
            <label class="checkbox-custom-label" for="PayPal_force_subscription"><span class="kinfo"><?php echo $LANG["force-subscription-desc"]; ?></span></label>
        </div>
    </div>

    <div class="formcon">
        <div class="yuzde30">Sandbox</div>
        <div class="yuzde70">
            <input<?php echo isset($CONFIG["settings"]["sandbox"]) && $CONFIG["settings"]["sandbox"] ? ' checked' : ''; ?> type="checkbox" name="sandbox" value="1" id="PayPal_sandbox" class="checkbox-custom">
            <label class="checkbox-custom-label" for="PayPal_sandbox"></label>
        </div>
    </div>

    <div class="formcon type-contents type-is-subscription">
        <div class="yuzde30"><?php echo __("admin/financial/automatic-subscription-fee-change"); ?></div>
        <div class="yuzde70">
            <input<?php echo isset($CONFIG["settings"]["change_subscription_fee"]) && $CONFIG["settings"]["change_subscription_fee"] ? ' checked' : ''; ?> type="checkbox" name="change_subscription_fee" value="1" id="PayPal_change_subscription_fee" class="checkbox-custom">
            <label class="checkbox-custom-label" for="PayPal_change_subscription_fee"><?php echo __("admin/financial/automatic-subscription-fee-change-desc"); ?></label>
        </div>
    </div>

    <div class="line"></div>

    <div class="yuzde30 guncellebtn" style="float: left;">
        <a class="lbtn" id="PayPal_test" href="javascript:void 0;"><i class="fa fa-plug"></i> <?php echo $LANG["test-connection"]; ?></a>
    </div>

    <div style="float:right;" class="guncellebtn yuzde30"><a id="PayPal_submit" href="javascript:void(0);" class="yesilbtn gonderbtn"><?php echo $LANG["save-button"]; ?></a></div>

</form>


<script type="text/javascript">
    $(document).ready(function(){

        $("#PayPal_submit").click(function(){
            $("#PayPalForm input[name=controller]").val("settings");
            MioAjaxElement($(this),{
                waiting_text:waiting_text,
                progress_text:progress_text,
                result:"PayPal_handler",
            });
        });
        $("#PayPal_test").click(function(){
            $("#PayPalForm input[name=controller]").val("test_connection");
            MioAjaxElement($(this),{
                waiting_text:waiting_text,
                progress_text:progress_text,
                result:"PayPal_handler",
            });
        });


        $("#accepted_countries,#unaccepted_countries").select2({
            templateResult: format_select2_2,
            templateSelection: format_select2_1,
        });

    });

    function format_select2_1(state) {
        if (!state.id) { return state.text; }
        var originalOption = state.element;
        var image_url = $(originalOption).data('image');
        if(image_url == undefined) return state.text;
        return $("<span style='float: left;margin-right: 3px;'><img style='margin-top: 3px;' class='select2-flag' src='" + image_url + "' /> "+state.text+"</span>");
    }
    function format_select2_2(state) {
        if (!state.id) { return state.text; }
        var originalOption = state.element;
        var image_url = $(originalOption).data('image');
        if(image_url == undefined) return state.text;
        return $("<span><img class='select2-flag' src='" + image_url + "' /> "+state.text+"</span>");
    }

    function PayPal_handler(result){
        if(result != ''){
            var solve = getJson(result);
            if(solve !== false){
                if(solve.status == "error"){
                    if(solve.for != undefined && solve.for != ''){
                        $("#PayPalForm "+solve.for).focus();
                        $("#PayPalForm "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                        $("#PayPalForm "+solve.for).change(function(){
                            $(this).removeAttr("style");
                        });
                    }
                    if(solve.message != undefined && solve.message != '')
                        alert_error(solve.message,{timer:5000});
                }else if(solve.status == "successful"){
                    alert_success(solve.message,{timer:2500});
                }
            }else
                console.log(result);
        }
    }
</script>
<div class="clear"></div>