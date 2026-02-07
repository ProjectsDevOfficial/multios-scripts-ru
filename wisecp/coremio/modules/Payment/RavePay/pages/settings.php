<?php
    if(!defined("CORE_FOLDER")) die();
    $LANG           = $module->lang;
    $CONFIG         = $module->config;
    $countries      = AddressManager::getCountries('t1.id,t1.a2_iso,t2.name');

?>
<form action="<?php echo Controllers::$init->getData("links")["controller"]; ?>" method="post" id="RavePay">
    <input type="hidden" name="operation" value="module_controller">
    <input type="hidden" name="module" value="RavePay">
    <input type="hidden" name="controller" value="settings">

    <div class="formcon">
        <div class="yuzde30">Public Key</div>
        <div class="yuzde70">
            <input type="text" name="public_key" value="<?php echo $CONFIG["settings"]["public_key"]; ?>">
            <span class="kinfo"></span>
        </div>
    </div>

    <div class="formcon">
        <div class="yuzde30">Secret Key</div>
        <div class="yuzde70">
            <input type="text" name="secret_key" value="<?php echo $CONFIG["settings"]["secret_key"]; ?>">
            <span class="kinfo"></span>
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


    <div style="float:right;" class="guncellebtn yuzde30"><a id="RavePay_submit" href="javascript:void(0);" class="yesilbtn gonderbtn"><?php echo $LANG["save-button"]; ?></a></div>

</form>


<script type="text/javascript">
    $(document).ready(function(){

        $("#RavePay_submit").click(function(){
            MioAjaxElement($(this),{
                waiting_text:waiting_text,
                progress_text:progress_text,
                result:"RavePay_handler",
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

    function RavePay_handler(result){
        if(result != ''){
            var solve = getJson(result);
            if(solve !== false){
                if(solve.status == "error"){
                    if(solve.for != undefined && solve.for != ''){
                        $("#RavePay "+solve.for).focus();
                        $("#RavePay "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                        $("#RavePay "+solve.for).change(function(){
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