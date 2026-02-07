<?php
    if(!defined("CORE_FOLDER")) die();
    $LANG       = $module->lang;
    $CONFIG     = $module->config;
    $callback_url   = Controllers::$init->CRLink("payment",['Stripe',$module->get_auth_token(),'callback'],"none");
    $success_url    = Controllers::$init->CRLink("pay-successful");
    $failed_url     = Controllers::$init->CRLink("pay-failed");

    $countries      = AddressManager::getCountries('t1.id,t1.a2_iso,t2.name');
?>
<form action="<?php echo Controllers::$init->getData("links")["controller"]; ?>" method="post" id="Stripe">
    <input type="hidden" name="operation" value="module_controller">
    <input type="hidden" name="module" value="Stripe">
    <input type="hidden" name="controller" value="settings">


    <div class="formcon">
        <div class="yuzde30">API Key</div>
        <div class="yuzde70">
            <input type="text" name="live_publishable_key" value="<?php echo $CONFIG["settings"]["live_publishable_key"]; ?>">
            <span class="kinfo">You can find API information <a target='_blank' href='https://dashboard.stripe.com/account/apikeys'>here</a>.</span>
        </div>
    </div>

    <div class="formcon live_content">
        <div class="yuzde30">Secret Key</div>
        <div class="yuzde70">
            <input type="text" name="live_secret_key" value="<?php echo $CONFIG["settings"]["live_secret_key"]; ?>">
            <span class="kinfo">You can find API information <a target='_blank' href='https://dashboard.stripe.com/account/apikeys'>here</a>.</span>
        </div>
    </div>

    <div class="formcon">
        <div class="yuzde30">Signing Secret</div>
        <div class="yuzde70">
            <input type="text" name="endpoint_secret" value="<?php echo $CONFIG["settings"]["endpoint_secret"] ?? ''; ?>">
        </div>
    </div>

    <div class="formcon">
        <div class="yuzde30">
            Webhook Event Types
        </div>
        <div class="yuzde70">

            <strong style="margin:5px; width: 25%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">payment_intent.succeeded</strong>
            <div class="clear"></div>
        </div>
    </div>


    <div class="formcon">
        <div class="yuzde30">
            Test Mode
        </div>
        <div class="yuzde70">

            <input<?php echo ($CONFIG["settings"]["test_mode"] ?? false) ? ' checked' : ''; ?> type="checkbox" name="test_mode" class="checkbox-custom" value="1" id="el-Stripe-item-sandbox">
            <label class="checkbox-custom-label" for="el-Stripe-item-sandbox"><span class="kinfo">Activate for test mode.</span></label>
        </div>
    </div>


    <div class="formcon">
        <div class="yuzde30"><?php echo $LANG["commission-rate"]; ?></div>
        <div class="yuzde70">
            <input type="text" name="commission_rate" value="<?php echo $CONFIG["settings"]["commission_rate"]; ?>" style="width: 80px;">
            <span class="kinfo"><?php echo $LANG["commission-rate-desc"]; ?></span>
        </div>
    </div>

    <div class="formcon">
        <div class="yuzde30"><?php echo __("admin/modules/convert-to"); ?></div>
        <div class="yuzde70">
            <select name="force_convert_to">
                <option value=""><?php echo ___("needs/none"); ?></option>
                <?php
                    foreach (Money::getCurrencies(isset($CONFIG["settings"]["force_convert_to"]) ? $CONFIG["settings"]["force_convert_to"] : 0) AS $c)
                    {
                        ?>
                        <option<?php echo isset($CONFIG["settings"]["force_convert_to"]) && $CONFIG["settings"]["force_convert_to"] == $c["id"] ? ' selected' : ''; ?> value="<?php echo $c["id"]; ?>"><?php echo $c["name"]." (".$c["code"].")"; ?></option>
                        <?php
                    }
                ?>
            </select>
            <span class="kinfo"><?php echo __("admin/modules/convert-to-desc"); ?></span>
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

    <div class="formcon">
        <div class="yuzde30">Callback URL</div>
        <div class="yuzde70">
            <span style="font-size:13px;font-weight:600;" class="selectalltext"><?php echo $callback_url; ?></span>
        </div>
    </div>

    <div class="formcon">
        <div class="yuzde30">Success URL</div>
        <div class="yuzde70">
            <span style="font-size:13px;font-weight:600;" class="selectalltext"><?php echo $success_url; ?></span>
        </div>
    </div>

    <div class="formcon">
        <div class="yuzde30">Failed URL</div>
        <div class="yuzde70">
            <span style="font-size:13px;font-weight:600;" class="selectalltext"><?php echo $failed_url; ?></span>
        </div>
    </div>




    <div style="float:right;" class="guncellebtn yuzde30"><a id="Stripe_submit" href="javascript:void(0);" class="yesilbtn gonderbtn"><?php echo $LANG["save-button"]; ?></a></div>

</form>


<script type="text/javascript">
    $(document).ready(function(){

        $("#Stripe_submit").click(function(){
            MioAjaxElement($(this),{
                waiting_text:waiting_text,
                progress_text:progress_text,
                result:"Stripe_handler",
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

    function Stripe_handler(result){
        if(result != ''){
            var solve = getJson(result);
            if(solve !== false){
                if(solve.status == "error"){
                    if(solve.for != undefined && solve.for != ''){
                        $("#Stripe "+solve.for).focus();
                        $("#Stripe "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                        $("#Stripe "+solve.for).change(function(){
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