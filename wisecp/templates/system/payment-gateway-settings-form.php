<?php
    if(!defined("CORE_FOLDER")) die();
    $LANG           = $module->lang;
    $CONFIG         = $module->config;
    $callback_url   = $module->links["callback"];
    $success_url    = $module->links["successful"];
    $failed_url     = $module->links["failed"];
    $countries      = AddressManager::getCountries('t1.id,t1.a2_iso,t2.name');
?>
<form action="<?php echo Controllers::$init->getData("links")["controller"]; ?>" method="post" id="SettingForm<?php echo $module->name; ?>">
    <input type="hidden" name="operation" value="module_controller">
    <input type="hidden" name="module" value="<?php echo $module->name; ?>">
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
        $module->config_fields_output();
    ?>

    <?php
        if(method_exists($module,'change_subscription_fee'))
        {
            ?>
            <div class="formcon bordernone type-contents type-is-subscription">
                <div class="yuzde30"><?php echo __("admin/financial/automatic-subscription-fee-change"); ?></div>
                <div class="yuzde70">
                    <input<?php echo isset($CONFIG["settings"]["change_subscription_fee"]) && $CONFIG["settings"]["change_subscription_fee"] ? ' checked' : ''; ?> type="checkbox" name="change_subscription_fee" value="1" id="<?php echo $module->name; ?>_change_subscription_fee" class="checkbox-custom">
                    <label class="checkbox-custom-label" for="<?php echo $module->name; ?>_change_subscription_fee"><?php echo __("admin/financial/automatic-subscription-fee-change-desc"); ?></label>
                </div>
            </div>
            <?php
        }
    ?>

    <div class="formcon">
        <div class="yuzde30"><?php echo __("admin/modules/commission-rate"); ?></div>
        <div class="yuzde70">
            <input type="text" name="commission_rate" value="<?php echo $CONFIG["settings"]["commission_rate"] ?? ''; ?>" style="width: 80px;">
            <span class="kinfo"><?php echo __("admin/modules/commission-rate-desc"); ?></span>
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


    <div style="float:right;" class="guncellebtn yuzde30"><a id="SettingForm<?php echo $module->name; ?>_submit" href="javascript:void(0);" class="yesilbtn gonderbtn"><?php echo __("admin/modules/save-button"); ?></a></div>

</form>


<script type="text/javascript">
    $(document).ready(function(){

        $("#SettingForm<?php echo $module->name; ?>_submit").click(function(){
            MioAjaxElement($(this),{
                waiting_text:waiting_text,
                progress_text:progress_text,
                result:"SettingForm<?php echo $module->name; ?>_handler",
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

    function SettingForm<?php echo $module->name; ?>_handler(result){
        if(result != ''){
            var solve = getJson(result);
            if(solve !== false){
                if(solve.status == "error"){
                    if(solve.for != undefined && solve.for != ''){
                        $("#SettingForm<?php echo $module->name; ?> "+solve.for).focus();
                        $("#SettingForm<?php echo $module->name; ?> "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                        $("#SettingForm<?php echo $module->name; ?> "+solve.for).change(function(){
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
