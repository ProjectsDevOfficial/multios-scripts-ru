<!DOCTYPE html>
<html>
<head>
    <?php
        Utility::sksort($lang_list,"local");
        $local_l    = Config::get("general/local");

        $plugins    = ['dataTables','select2'];
        include __DIR__.DS."inc".DS."head.php";
    ?>
    <script type="text/javascript">
        var list_table;

        $(document).ready(function(){

            $('.select2').select2({width:'100%'});
            list_table = $('#list_table').DataTable({
                "columnDefs": [
                    {
                        "targets": [0],
                        "visible":false,
                        "searchable": false
                    }
                ],
                paging: true,
                info: true,
                searching: true,
                responsive: true,
                "language":{"url":"<?php echo APP_URI; ?>/<?php echo ___("package/code"); ?>/datatable/lang.json"}
            });

            $("#settings_submit").on("click",function(){
                MioAjaxElement($(this),{
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                    result:"submit_handler",
                });
            });

            $("#advancedForm_submit").on("click",function(){
                MioAjaxElement($(this),{
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                    result:"submit_handler",
                });
            });

            $("#taxRatesForm_submit").on("click",function(){
                MioAjaxElement($(this),{
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                    result:"submit_handler",
                });
            });

            $("#taxation").change(function(){
                if(!$(this).prop("checked")){
                    $("#sebilltad_status").prop("checked",false);
                    $("input[name=rate]").val('0');
                }
            });

            var tab = _GET("tab");
            if(tab != '' && tab != undefined){
                $("#tab-tab .tablinks[data-tab='"+tab+"']").click();
            }else{
                $("#tab-tab .tablinks:eq(0)").addClass("active");
                $("#tab-tab .tabcontent:eq(0)").css("display","block");
            }

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

        });

        function submit_handler(result){
            if(result !== ''){
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

        function add_tax_rate(wrap)
        {
            var template = $("#template-tax-rate").html();
            if(wrap === 'tax-rates-1')
                template = template.replace('edit_tax_rule_modal','new_tax_rule_modal');
            else if(wrap === 'tax-rates-2')
                template = template.replace('new_tax_rule_modal','edit_tax_rule_modal');

            $("#"+wrap).append(template);
        }

        function new_tax_rule(){
            open_modal('new_tax_rule_modal');
            $("#DefineNewCity_con").css("display","none");
            $("#DefineNewCity_con input").val('');
            $("#selectCity").css("display","none");
            $("#cityCon").css("display","none");
            add_tax_rate('tax-rates-1');
        }

        function delete_tax_rule(el,country_id,city_id){
            if(!confirm('<?php echo htmlentities(___("needs/delete-are-you-sure")); ?>')) return false;
            var bfr_el_val = $(el).html();

            $(el).html('<i class="fa fa-spinner" style="-webkit-animation:fa-spin 2s infinite linear;animation: fa-spin 2s infinite linear;"></i>');

            var request = MioAjax({
                action:"<?php echo $links["controller"]; ?>",
                method:"POST",
                data:{
                    operation: "delete_tax_rule",
                    country_id: country_id,
                    city_id: city_id
                }
            },true,true);

            request.done(function(result){
                $(el).html(bfr_el_val);
                if(result !== ''){
                    var solve = getJson(result);
                    if(solve !== false){
                        if(solve.status == "error"){
                            if(solve.message != undefined && solve.message != '')
                                alert_error(solve.message,{timer:5000});
                        }else if(solve.status == "successful"){
                            alert_success(solve.message,{timer:2000});
                            list_table.row($(el).parent().parent()).remove().draw();
                        }
                    }else
                        console.log(result);
                }
            });

        }

        function edit_tax_rule(country,city)
        {
            open_modal('edit_tax_rule_modal');
            $("#get_country_name").html($('#country-'+country+"-name").html());
            $("#edit_tax_rule_modal input[name=country_id]").val(country);
            if(city)
            {
                $("#edit_tax_rule_modal input[name=city_id]").val(city);
                $("#get_city_name").html($('#city-'+city+"-name").html());
                $("#tax-rates-2").html($("#tax-rate-names-"+country+"-"+city).html());
            }
            else
            {
                $("#edit_tax_rule_modal input[name=city_id]").val('0');
                $("#get_city_name").html('-');
                $("#tax-rates-2").html($("#tax-rate-names-"+country).html());
            }
        }

    </script>
</head>
<body>

<div id="new_tax_rule_modal" style="display: none;" data-izimodal-title="<?php echo __("admin/financial/button-add-new-tax-rate-rule"); ?>">
    <div class="padding20">

        <script type="text/javascript">
            $(document).ready(function(){

                $("#addNewTaxRuleForm_submit").on("click",function(){
                    MioAjaxElement($(this),{
                        waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                        progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                        result:"submit_handler",
                    });
                });

                $("#selectCountry").on("change",function(){
                    var cities;

                    if($(this).val() === '')
                    {
                        $("#cityCon").css("display","none");
                        cities = {};
                    }else{
                        $("#cityCon").css("display","block");
                        var cities  = MioAjax({
                            action:"<?php echo Controllers::$init->AdminCRLink("users"); ?>",
                            method: "POST",
                            data:{
                                operation:"getCities",
                                country: parseInt($(this).val()),
                            }
                        },true,true);
                    }
                    cities.done(function(result){
                        result = getJson(result);
                        if(result.status === "successful" && $(result.data).length > 0)
                        {
                            $("#DefineNewCity_con").css("display","none");
                            $("#DefineNewCity_con input").val('');
                            $("#selectCity").css("display","block").html('');
                            $("#selectCity").append('<option value=""><?php echo ___("needs/select-your"); ?></option>');
                            $(result.data).each(function(){
                                $("#selectCity").append('<option value="'+this.id+'">'+this.name+'</option>');
                            });
                            $("#selectCity").append('<option value="add-new-city"><?php echo __("admin/financial/add-new-city"); ?></option>');
                        }
                        else
                        {
                            $("#selectCity").css("display","none").html('');
                            $("#DefineNewCity_con").css("display","block");
                        }
                    });
                });

                $("#selectCity").on("change",function(){
                    var selection = $("option:selected",$(this)).val();

                    if(selection === "add-new-city"){
                        $("#DefineNewCity_con").css("display","block");
                    }else{
                        $("#DefineNewCity_con").css("display","none");
                        $("#DefineNewCity_con input").val('');
                    }

                });

            });
        </script>
        <form action="<?php echo $links["controller"]; ?>" method="post" id="addNewTaxRuleForm">
            <input type="hidden" name="operation" value="add_new_tax_rule">

            <div class="formcon">
                <div class="yuzde30"><?php echo __("admin/financial/tax-rates-th-country"); ?></div>
                <div class="yuzde70">
                    <select name="country" id="selectCountry">
                        <option value=""><?php echo ___("needs/select-your"); ?></option>
                        <?php
                            if(isset($countries) && $countries){
                                foreach($countries AS $v){
                                    ?>
                                    <option value="<?php echo $v["id"]; ?>"><?php echo $v["name"]; ?></option>
                                    <?php
                                }
                            }
                        ?>
                    </select>
                </div>
            </div>
            <div class="formcon" id="cityCon">
                <div class="yuzde30"><?php echo __("admin/financial/tax-rates-th-city"); ?></div>
                <div class="yuzde70">
                    <select name="city_id" id="selectCity" style="display: none;">
                        <option value=""><?php echo ___("needs/select-your"); ?></option>
                        <option value="add-new-city"><?php echo __("admin/financial/add-new-city"); ?></option>
                    </select>
                    <div class="clear"></div>
                    <div id="DefineNewCity_con" style="display: none;">
                        <input type="text" name="city_name" value="" id="define_city" placeholder="<?php echo __("admin/financial/add-new-city-placeholder"); ?>">
                    </div>

                </div>
            </div>

            <div class="formcon">
                <div class="yuzde30"><?php echo __("admin/financial/tax"); ?></div>
                <div class="yuzde70">

                    <div id="tax-rates-1"></div>

                    <div class="clear"></div>
                    <a class="lbtn green" style="margin-top: 5px;" href="javascript:add_tax_rate('tax-rates-1');"><i class="fa fa-plus"></i> <?php echo ___("needs/button-add"); ?></a>

                </div>
            </div>

            <div class="clear"></div>

            <div style="float:right;margin-top:10px;" class="guncellebtn yuzde30">
                <a class="yesilbtn gonderbtn" id="addNewTaxRuleForm_submit" href="javascript:void(0);"><?php echo ___("needs/button-create"); ?></a>
            </div>

        </form>

        <div class="clear"></div>
    </div>
</div>

<div id="edit_tax_rule_modal" style="display: none;" data-izimodal-title="<?php echo __("admin/financial/button-edit-tax-rate-rule"); ?>">
    <div class="padding20">

        <script type="text/javascript">
            $(document).ready(function(){

                $("#editTaxRuleForm_submit").on("click",function(){
                    MioAjaxElement($(this),{
                        waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                        progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                        result:"submit_handler",
                    });
                });
            });
        </script>
        <form action="<?php echo $links["controller"]; ?>" method="post" id="editTaxRuleForm">
            <input type="hidden" name="operation" value="edit_tax_rule">
            <input type="hidden" name="country_id" value="0">
            <input type="hidden" name="city_id" value="0">

            <div class="formcon">
                <div class="yuzde30"><?php echo __("admin/financial/tax-rates-th-country"); ?></div>
                <div class="yuzde70" id="get_country_name">-</div>
            </div>
            <div class="formcon">
                <div class="yuzde30"><?php echo __("admin/financial/tax-rates-th-city"); ?></div>
                <div class="yuzde70" id="get_city_name">-</div>
            </div>

            <div class="formcon">
                <div class="yuzde30"><?php echo __("admin/financial/tax"); ?></div>
                <div class="yuzde70">

                    <div id="tax-rates-2"></div>

                    <div class="clear"></div>
                    <a class="lbtn green" style="margin-top: 5px;" href="javascript:add_tax_rate('tax-rates-2');"><i class="fa fa-plus"></i> <?php echo ___("needs/button-add"); ?></a>

                </div>
            </div>

            <div class="clear"></div>

            <div style="float:right;margin-top:10px;" class="guncellebtn yuzde30">
                <a class="yesilbtn gonderbtn" id="editTaxRuleForm_submit" href="javascript:void(0);"><?php echo ___("needs/button-update"); ?></a>
            </div>

        </form>

        <div class="clear"></div>
    </div>
</div>

<div id="DefineAllTaxRatesModal" style="display: none;" data-izimodal-title="<?php echo __("admin/financial/button-define-all-tax-rates"); ?>">
    <div class="padding20">
        <div style="text-align:center">
            <p>
                <?php echo __("admin/financial/define-all-tax-rates-note"); ?>
            </p>
        </div>
    </div>
    <div class="modal-foot-btn">
        <a href="<?php echo $links["controller"]; ?>?operation=define_all_tax_rates" class="blue lbtn"><?php echo ___("needs/ok"); ?></a>
    </div>
</div>


<div id="template-tax-rate" style="display: none;">
    <div class="formcon tax-item">
        <input type="text" style="width: 100px;" name="rates[name][]" class="tax-item-name" value="" placeholder="<?php echo __("admin/financial/tax-name"); ?>">
        <input type="text" name="rates[value][]" class="tax-item-value" placeholder="<?php echo __("admin/financial/tax-rate"); ?>" value="" onkeypress='return event.charCode==44 || event.charCode==46 || event.charCode>= 48 &&event.charCode<= 57' style="width: 100px;">
        <a class="sbtn red" onclick="if($('#new_tax_rule_modal .tax-item').length > 1){ $(this).parent().remove(); }" href="javascript:void 0;"><i class="fa fa-remove"></i></a>
    </div>
</div>

<?php include __DIR__.DS."inc/header.php"; ?>

<div id="wrapper">

    <div class="icerik-container">
        <div class="icerik">

            <div class="icerikbaslik">
                <h1><strong><?php echo __("admin/financial/page-taxation"); ?></strong></h1>
                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>

            <div id="tab-tab">
                <ul class="tab">

                    <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'detail','tab')" data-tab="detail"> <?php echo __("admin/financial/tab-detail"); ?> </a></li>
                    <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'tax-rates','tab')" data-tab="tax-rates"> <?php echo __("admin/financial/tab-tax-rates"); ?></a></li>
                    <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'advanced','tab')" data-tab="advanced"> <?php echo __("admin/financial/tab-advanced"); ?></a></li>
                </ul>

                <div id="tab-detail" class="tabcontent">

                    <div class="adminpagecon">


                        <div class="green-info" style="margin-bottom:20px;">
                            <div class="padding15">
                                <i class="fa fa-info-circle" aria-hidden="true"></i>
                                <p><?php echo __("admin/financial/taxation-desc"); ?></p>
                            </div>
                        </div>

                        <form action="<?php echo $links["controller"]; ?>" method="post" id="settingsForm">
                            <input type="hidden" name="operation" value="taxation_settings">

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/financial/taxation-status"); ?></div>
                                <div class="yuzde70">
                                    <input type="checkbox" class="sitemio-checkbox" id="taxation" name="status" value="1"<?php echo $settings["status"] ? ' checked' : NULL; ?>>
                                    <label class="sitemio-checkbox-label" for="taxation"></label>
                                    <span class="kinfo"><?php echo __("admin/financial/taxation-status-desc"); ?></span>
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/financial/taxation-type"); ?></div>
                                <div class="yuzde70">
                                    <input<?php echo $settings["taxation-type"] == "exclusive" ? ' checked' : ''; ?> type="radio" name="taxation_type" value="exclusive" class="radio-custom" id="taxation_type_exclusive">
                                    <label class="radio-custom-label" for="taxation_type_exclusive" style="margin-bottom:10px;"><?php echo __("admin/financial/taxation-type-exclusive"); ?> <span class="kinfo"><?php echo __("admin/financial/taxation-type-exclusive-info"); ?></span></label>
                                    <div class="clear"></div>

                                    <input<?php echo $settings["taxation-type"] == "inclusive" ? ' checked' : ''; ?> type="radio" name="taxation_type" value="inclusive" class="radio-custom" id="taxation_type_inclusive">
                                    <label class="radio-custom-label" for="taxation_type_inclusive" style="margin-bottom:10px;"><?php echo __("admin/financial/taxation-type-inclusive"); ?> <span class="kinfo"><?php echo __("admin/financial/taxation-type-inclusive-info"); ?></span></label>
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/financial/taxation-rate"); ?></div>
                                <div class="yuzde70">
                                    <input type="text" style="width:35px;" name="rate" value="<?php echo $settings["rate"]; ?>">
                                    <span class="kinfo"><?php echo __("admin/financial/taxation-rate-desc"); ?></span>
                                </div>
                            </div>

                            <div style="float:right;margin-top:10px;" class="guncellebtn yuzde30">
                                <a class="yesilbtn gonderbtn" id="settings_submit" href="javascript:void(0);"><?php echo __("admin/financial/save-settings"); ?></a>
                            </div>
                            <div class="clear"></div>


                        </form>
                    </div>

                </div>

                <div id="tab-tax-rates" class="tabcontent">

                    <div class="adminpagecon">
                        <div class="green-info" style="margin-bottom:20px;">
                            <div class="padding15">
                                <i class="fa fa-info-circle" aria-hidden="true"></i>
                                <p><?php echo __("admin/financial/tax-rates-desc"); ?></p>
                            </div>
                        </div>

                        <div class="clear"></div>

                        <div style="float: left;">
                            <a onclick="new_tax_rule();" class="lbtn green" href="javascript:void 0;" style="margin-bottom:5px;">+ <?php echo __("admin/financial/button-add-new-tax-rate-rule"); ?></a>
                        </div>
                        <div style="float: right;">
                            <a onclick="open_modal('DefineAllTaxRatesModal');" class="lbtn blue" href="javascript:void 0;" style="margin-bottom:5px;"><?php echo __("admin/financial/button-define-all-tax-rates"); ?></a>
                        </div>
                        <div class="clear"></div>

                        <form action="<?php echo $links["controller"]; ?>" method="post" id="taxRatesForm">
                            <input type="hidden" name="operation" value="update_tax_rates">

                            <table width="100%" id="list_table" class="table table-striped table-borderedx table-condensed nowrap">
                                <thead style="background:#ebebeb;">
                                <tr>
                                    <th align="center" style="opacity: 0;">#</th>
                                    <th align="left"><?php echo __("admin/financial/tax-rates-th-country"); ?></th>
                                    <th align="left"><?php echo __("admin/financial/tax-rates-th-city"); ?></th>
                                    <th data-orderable="false" align="center"><?php echo __("admin/financial/tax-rate"); ?></th>
                                    <th data-orderable="false" align="center">&nbsp;</th>
                                </tr>
                                </thead>
                                <tbody align="Center" style="border-top:none;">
                                <?php
                                    if(isset($countries) && $countries){
                                        $i=0;
                                        $rates_names = Config::get("options/tax-rates-names");
                                        foreach($countries AS $k=>$row){
                                            $i+1;
                                            $cities = $get_primary_cities($row["id"]);

                                            if(isset($rates_names[$row["id"]][0]))
                                                $rns = $rates_names[$row["id"]][0];
                                            else
                                                $rns = [
                                                    [
                                                        'name'      => '',
                                                        'value'     => $row["tax_rate"],
                                                    ]
                                                ];
                                            ?>
                                            <tr>
                                                <td align="center"><?php echo $i; ?></td>
                                                <td align="left"><?php echo $row["name"]; ?></td>
                                                <td align="left"> - </td>
                                                <td align="center">
                                                    <!--
                                                    <input type="text" name="country_rates[<?php echo $row["id"]; ?>]" value="<?php echo $row["tax_rate"]; ?>" onkeypress='return event.charCode==44 || event.charCode==46 || event.charCode>= 48 &&event.charCode<= 57' style="width: 50px;">
                                                    -->
                                                    <?php echo $row["tax_rate"]; ?>
                                                </td>
                                                <td align="center">
                                                    <div id="tax-rate-names-<?php echo $row["id"]; ?>" style="display: none">
                                                        <?php
                                                            foreach($rns AS $rn)
                                                            {
                                                                ?>
                                                                <div class="formcon tax-item">
                                                                    <input type="text" style="width: 100px;" name="rates[name][]" class="tax-item-name" value="<?php echo $rn["name"]; ?>" placeholder="<?php echo __("admin/financial/tax-name"); ?>">
                                                                    <input type="text" name="rates[value][]" class="tax-item-value" placeholder="<?php echo __("admin/financial/tax-rate"); ?>" value="<?php echo $rn["value"]; ?>" onkeypress='return event.charCode==44 || event.charCode==46 || event.charCode>= 48 &&event.charCode<= 57' style="width: 100px;">
                                                                    <a class="sbtn red" onclick="if($('#edit_tax_rule_modal .tax-item').length > 1){ $(this).parent().remove(); }" href="javascript:void 0;"><i class="fa fa-remove"></i></a>
                                                                </div>
                                                                <?php
                                                            }
                                                        ?>
                                                    </div>
                                                    <div id="country-<?php echo $row["id"]; ?>-name" style="display: none;"><?php echo $row["name"]; ?></div>

                                                    <a class="sbtn" data-tooltip="<?php echo __("admin/financial/button-edit-tax-rate-rule"); ?>" href="javascript:edit_tax_rule(<?php echo $row["id"]; ?>,0);void 0;"><i class="fa fa-pencil"></i></a>
                                                </td>
                                            </tr>
                                            <?php

                                            if($cities && is_array($cities)){
                                                foreach($cities AS $k2=>$row2){

                                                    if(isset($rates_names[$row["id"]][$row2["id"]]))
                                                        $rns = $rates_names[$row["id"]][$row2["id"]];
                                                    else
                                                        $rns = [
                                                            [
                                                                'name'      => '',
                                                                'value'     => $row2["tax_rate"],
                                                            ]
                                                        ];

                                                    ?>
                                                    <tr>
                                                        <td align="center"><?php echo $i; ?></td>
                                                        <td align="left"><?php echo $row["name"]; ?></td>
                                                        <td align="left"><?php echo $row2["name"]; ?></td>
                                                        <td align="center">
                                                            <!--
                                                            <input type="text" name="city_rates[<?php echo $row["id"]; ?>][<?php echo $row2["id"]; ?>]" value="<?php echo $row2["tax_rate"]; ?>" onkeypress='return event.charCode==44 || event.charCode==46 || event.charCode>= 48 &&event.charCode<= 57' style="width: 50px;">
                                                            -->
                                                            <?php echo $row2["tax_rate"]; ?>
                                                        </td>
                                                        <td align="center">

                                                            <div id="tax-rate-names-<?php echo $row["id"]; ?>-<?php echo $row2["id"]; ?>" style="display: none">
                                                                <?php
                                                                    foreach($rns AS $rn)
                                                                    {
                                                                        ?>
                                                                        <div class="formcon tax-item">
                                                                            <input type="text" style="width: 100px;" name="rates[name][]" class="tax-item-name" value="<?php echo $rn["name"]; ?>" placeholder="<?php echo __("admin/financial/tax-name"); ?>">
                                                                            <input type="text" name="rates[value][]" class="tax-item-value" placeholder="<?php echo __("admin/financial/tax-rate"); ?>" value="<?php echo $rn["value"]; ?>" onkeypress='return event.charCode==44 || event.charCode==46 || event.charCode>= 48 &&event.charCode<= 57' style="width: 100px;">
                                                                            <a class="sbtn red" onclick="if($('#edit_tax_rule_modal .tax-item').length > 1){ $(this).parent().remove(); }" href="javascript:void 0;"><i class="fa fa-remove"></i></a>
                                                                        </div>
                                                                        <?php
                                                                    }
                                                                ?>
                                                            </div>
                                                            <div id="city-<?php echo $row2["id"]; ?>-name" style="display: none;"><?php echo $row2["name"]; ?></div>

                                                            <a class="sbtn" data-tooltip="<?php echo __("admin/financial/button-edit-tax-rate-rule"); ?>" href="javascript:edit_tax_rule(<?php echo $row["id"]; ?>,<?php echo $row2["id"]; ?>);void 0;"><i class="fa fa-pencil"></i></a>

                                                            <a class="sbtn red" href="javascript:void 0;" data-tooltip="<?php echo ___("needs/button-delete"); ?>" onclick="delete_tax_rule(this,<?php echo $row["id"]; ?>,<?php echo $row2["id"]; ?>);"><i class="fa fa-trash" aria-hidden="true"></i></a>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                            }

                                        }
                                    }
                                ?>
                                </tbody>
                            </table>

                            <div class="clear"></div>

                            <!--
                            <div style="float:right;margin-top:10px;" class="guncellebtn yuzde30">
                                <a class="yesilbtn gonderbtn" id="taxRatesForm_submit" href="javascript:void(0);"><?php echo __("admin/financial/save-settings"); ?></a>
                            </div>
                            -->

                        </form>

                    </div>

                </div>

                <div id="tab-advanced" class="tabcontent">

                    <div class="adminpagecon">
                        <form action="<?php echo $links["controller"]; ?>" method="post" id="advancedForm">
                            <input type="hidden" name="operation" value="taxation_advanced">

                            <?php
                                $formalization_s = Config::get("options/invoice-formalization-status");
                            ?>
                            <div class="formcon">
                                <div class="yuzde30">
                                    <?php echo __("admin/financial/invoice-formalization-status"); ?>
                                </div>
                                <div class="yuzde70">
                                    <input<?php echo $formalization_s ? ' checked' : ''; ?> type="checkbox" name="invoice-formalization-status" value="1" class="sitemio-checkbox" id="invoice-formalization-status">
                                    <label for="invoice-formalization-status" class="sitemio-checkbox-label"></label>
                                    <span class="kinfo"><?php echo __("admin/financial/invoice-formalization-status-desc"); ?></span>
                                </div>
                            </div>


                            <?php
                                $invoice_num_format_status = Config::get("options/invoice-num-format-status");
                            ?>
                            <div class="formcon">
                                <div class="yuzde30">
                                    <?php echo __("admin/financial/proforma-format"); ?>
                                    <div class="clear"></div>
                                    <span class="kinfo"><?php echo __("admin/financial/proforma-format-desc"); ?></span>
                                </div>
                                <div class="yuzde70">

                                    <div class="formcon">
                                        <div class="yuzde30"><?php echo __("admin/financial/format-status"); ?></div>
                                        <div class="yuzde70">
                                            <input<?php echo $invoice_num_format_status ? ' checked' : ''; ?> type="checkbox" name="invoice-num-format-status" value="1" class="sitemio-checkbox" id="invoice-num-format-status" onchange="if($(this).prop('checked')){ $('input[name=invoice-num-format],input[name=invoices_auto_increment]').attr('readonly',false); } else { $('input[name=invoice-num-format],input[name=invoices_auto_increment]').attr('readonly',true); }">
                                            <label for="invoice-num-format-status" class="sitemio-checkbox-label"></label>
                                            <span class="kinfo"><?php echo __("admin/financial/format-status-desc-proforma"); ?></span>
                                        </div>
                                    </div>

                                    <div class="formcon">
                                        <div class="yuzde30"><?php echo __("admin/financial/numbering-format"); ?></div>
                                        <div class="yuzde70">
                                            <input<?php echo $invoice_num_format_status ? '' : ' readonly'; ?> type="text" name="invoice-num-format" value="<?php echo Config::get("options/invoice-num-format"); ?>" style="width: 200px;">
                                            <span class="kinfo"><?php echo __("admin/financial/invoice-num-format-desc"); ?></span>
                                            <div class="clear"></div>
                                            <span class="kinfo"><?php echo __("admin/financial/invoice-num-format-vars"); ?></span>
                                        </div>
                                    </div>

                                    <div class="formcon">
                                        <div class="yuzde30"><?php echo __("admin/financial/next-sequence-number"); ?></div>
                                        <div class="yuzde70">
                                            <input<?php echo $invoice_num_format_status ? '' : ' readonly'; ?> type="text" name="invoices_auto_increment" maxlength="5" value="<?php echo isset($invoices_auto_increment) ? $invoices_auto_increment : 1; ?>" style="width: 100px;" onkeypress='return event.charCode>= 48 &&event.charCode<= 57'>
                                            <span class="kinfo"><?php echo __("admin/financial/next-sequence-number-desc-proforma"); ?></span>
                                        </div>
                                    </div>

                                </div>
                            </div>


                            <?php
                                $paid_invoice_num_format_status = Config::get("options/paid-invoice-num-format-status");
                                $paid_invoice_num_format        = Config::get("options/paid-invoice-num-format");
                            ?>
                            <div class="formcon">
                                <div class="yuzde30">
                                    <?php echo __("admin/financial/paid-invoice-format"); ?>
                                    <div class="clear"></div>
                                    <span class="kinfo"><?php echo __("admin/financial/paid-invoice-format-desc"); ?></span>
                                </div>
                                <div class="yuzde70">

                                    <div class="formcon">
                                        <div class="yuzde30"><?php echo __("admin/financial/format-status"); ?></div>
                                        <div class="yuzde70">
                                            <input<?php echo $paid_invoice_num_format_status ? ' checked' : ''; ?> type="checkbox" name="paid-invoice-num-format-status" value="1" class="sitemio-checkbox" id="paid-invoice-num-format-status" onchange="if($(this).prop('checked')){ $('input[name=paid-invoice-num-format],input[name=paid_invoices_auto_increment]').attr('readonly',false); } else { $('input[name=paid-invoice-num-format],input[name=paid_invoices_auto_increment]').attr('readonly',true); }">
                                            <label for="paid-invoice-num-format-status" class="sitemio-checkbox-label"></label>
                                            <span class="kinfo"><?php echo __("admin/financial/format-status-desc-paid"); ?></span>
                                        </div>
                                    </div>

                                    <div class="formcon">
                                        <div class="yuzde30"><?php echo __("admin/financial/numbering-format"); ?></div>
                                        <div class="yuzde70">
                                            <input<?php echo $paid_invoice_num_format_status ? '' : ' readonly'; ?> type="text" name="paid-invoice-num-format" value="<?php echo $paid_invoice_num_format; ?>" style="width: 200px;">
                                            <span class="kinfo"><?php echo __("admin/financial/invoice-num-format-desc"); ?></span>
                                            <div class="clear"></div>
                                            <span class="kinfo"><?php echo __("admin/financial/invoice-num-format-vars"); ?></span>
                                        </div>
                                    </div>

                                    <div class="formcon">
                                        <div class="yuzde30"><?php echo __("admin/financial/next-sequence-number"); ?></div>
                                        <div class="yuzde70">
                                            <input<?php echo $paid_invoice_num_format_status ? '' : ' readonly'; ?> type="text" name="paid_invoices_auto_increment" maxlength="5" value="<?php echo isset($paid_invoices_auto_increment) ? $paid_invoices_auto_increment : 1; ?>" style="width: 100px;" onkeypress='return event.charCode>= 48 &&event.charCode<= 57'>
                                            <span class="kinfo"><?php echo __("admin/financial/next-sequence-number-desc-paid"); ?></span>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30">
                                    <?php echo __("admin/financial/client-tax-information"); ?>
                                </div>
                                <div class="yuzde70">

                                    <div class="formcon">
                                        <div class="yuzde30"><?php echo __("admin/settings/corporate-tax-number"); ?></div>
                                        <div class="yuzde60">

                                            <input type="checkbox" class="checkbox-custom" name="company_tax_number_status" value="1" id="company_tax_number_status" <?php echo Config::get("options/sign/up/kind/corporate/company_tax_number") ? ' checked' : ''; ?>>
                                            <label for="company_tax_number_status" class="checkbox-custom-label"><span class="kinfo"><?php echo __("admin/settings/corporate-tax-number-status"); ?></span></label>
                                            <br>
                                            <input type="checkbox" class="checkbox-custom" name="company_tax_number_required" value="1" id="company_tax_number_required" <?php echo Config::get("options/sign/up/kind/corporate/company_tax_number/required") ? ' checked' : ''; ?>>
                                            <label class="checkbox-custom-label" for="company_tax_number_required"><span class="kinfo"><?php echo __("admin/settings/corporate-tax-number-required"); ?></span></label>
                                            <!--
                                            <br>
                                            <input type="checkbox" class="checkbox-custom" name="company_tax_number_check" value="1" id="company_tax_number_check" <?php echo Config::get("options/sign/up/kind/corporate/company_tax_number/check") ? ' checked' : ''; ?>>
                                            <label class="checkbox-custom-label" for="company_tax_number_check"><span class="kinfo"><?php echo __("admin/financial/client-tax-number-validation-desc"); ?></span></label>
                                            -->
                                        </div>
                                    </div>

                                    <div class="formcon">
                                        <div class="yuzde30"><?php echo __("admin/settings/corporate-tax-office"); ?></div>
                                        <div class="yuzde60">

                                            <input type="checkbox" class="checkbox-custom" name="company_tax_office_status" value="1" id="company_tax_office_status" <?php echo Config::get("options/sign/up/kind/corporate/company_tax_office") ? ' checked' : ''; ?>>
                                            <label class="checkbox-custom-label" for="company_tax_office_status"><span class="kinfo"><?php echo __("admin/settings/corporate-tax-office-status"); ?></span></label>
                                            <br>

                                            <input type="checkbox" class="checkbox-custom" name="company_tax_office_required" value="1" id="company_tax_office_required" <?php echo Config::get("options/sign/up/kind/corporate/company_tax_office/required") ? ' checked' : ''; ?>>
                                            <label class="checkbox-custom-label" for="company_tax_office_required"><span class="kinfo"><?php echo __("admin/settings/corporate-tax-office-required"); ?></span></label>

                                        </div>
                                    </div>

                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/settings/balance-taxation"); ?></div>
                                <div class="yuzde70">
                                    <?php
                                        $balance_taxation = Config::get("options/balance-taxation");
                                    ?>
                                    <input<?php echo !$balance_taxation || $balance_taxation == "y" ? ' checked' : ''; ?> type="radio" name="balance-taxation" value="y" class="radio-custom" id="balance-taxation-y">
                                    <label class="radio-custom-label" for="balance-taxation-y" style="margin-bottom: 5px;"><span class="kinfo"><?php echo __("admin/settings/balance-taxation-y"); ?></span></label>
                                    <div class="clear"></div>

                                    <input<?php echo $balance_taxation == "n" ? ' checked' : ''; ?> type="radio" name="balance-taxation" value="n" class="radio-custom" id="balance-taxation-n">
                                    <label class="radio-custom-label" for="balance-taxation-n"><span class="kinfo"><?php echo __("admin/settings/balance-taxation-n"); ?></span></label>


                                </div>
                            </div>


                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/settings/invoice-show-requires-login"); ?></div>
                                <div class="yuzde70">
                                    <input<?php echo Config::get("options/invoice-show-requires-login") ? ' checked' : ''; ?> type="checkbox" class="sitemio-checkbox" name="invoice-show-requires-login" value="1" id="invoice-show-requires-login">
                                    <label class="sitemio-checkbox-label" for="invoice-show-requires-login"></label>
                                    <span class="kinfo"><?php echo __("admin/settings/invoice-show-requires-login-desc"); ?></span>
                                </div>
                            </div>



                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/settings/delete-invoice-item-aoc"); ?></div>
                                <div class="yuzde70">
                                    <input<?php echo Config::get("options/delete-invoice-item-aoc") ? ' checked' : ''; ?> type="checkbox" class="sitemio-checkbox" name="delete-invoice-item-aoc" value="1" id="delete-invoice-item-aoc">
                                    <label class="sitemio-checkbox-label" for="delete-invoice-item-aoc"></label>
                                    <span class="kinfo"><?php echo __("admin/settings/delete-invoice-item-aoc-desc"); ?></span>
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/settings/detect-auto-price-on-invoice"); ?></div>
                                <div class="yuzde70">
                                    <input<?php echo Config::get("options/detect-auto-price-on-invoice") ? ' checked' : ''; ?> type="checkbox" class="sitemio-checkbox" name="detect-auto-price-on-invoice" value="1" id="detect-auto-price-on-invoice">
                                    <label class="sitemio-checkbox-label" for="detect-auto-price-on-invoice"></label>
                                    <span class="kinfo"><?php echo __("admin/settings/detect-auto-price-on-invoice-desc"); ?></span>
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/settings/firstly-create-invoice"); ?></div>
                                <div class="yuzde70">
                                    <input<?php echo Config::get("options/firstly-create-invoice") ? ' checked' : ''; ?> type="checkbox" class="sitemio-checkbox" name="firstly-create-invoice" value="1" id="firstly-create-invoice">
                                    <label class="sitemio-checkbox-label" for="firstly-create-invoice"></label>
                                    <span class="kinfo"><?php echo __("admin/settings/firstly-create-invoice-desc"); ?></span>
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/settings/pdf-font-1"); ?></div>
                                <div class="yuzde70">
                                    <select name="pdf-font" class="select2">
                                        <option value=""><?php echo __("admin/users/dealership-activation-auto"); ?> (<?php echo __("admin/settings/pdf-font-2"); ?>)</option>
                                        <?php
                                            $dir = CORE_DIR."vendor".DS."tecnickcom".DS."tcpdf".DS."fonts".DS;
                                            if(file_exists($dir))
                                            {
                                                foreach(FileManager::glob($dir.DS."*.php") AS $file)
                                                {
                                                    $f_name     = str_replace(".php","",basename($file));
                                                    ?>
                                                    <option<?php echo Config::get("options/pdf-font") == $f_name ? ' selected' : ''; ?> value="<?php echo $f_name;?>"><?php echo $f_name;?></option>
                                                    <?php
                                                }
                                            }
                                        ?>
                                    </select>
                                    <div class="clear"></div>
                                    <span class="kinfo"><?php echo __("admin/settings/pdf-font-3"); ?></span>
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/financial/invoice-special-note"); ?></div>
                                <div class="yuzde70">

                                    <div class="change-lang-buttons" data-type="invoice_special_note">
                                        <?php
                                            if(isset($lang_list) && $lang_list)
                                            {
                                                foreach($lang_list AS $row){
                                                    ?>
                                                    <a class="lbtn"<?php echo $local_l == $row["key"] ? ' id="lang-active"' : ''; ?> href="javascript:void 0;" data-key="<?php echo $row["key"]; ?>"><?php echo strtoupper($row["key"]); ?></a>
                                                    <?php
                                                }
                                            }
                                        ?>
                                    </div>


                                    <?php
                                        if(isset($lang_list) && $lang_list)
                                        {
                                            foreach($lang_list AS $row){
                                                $l_k = $row["key"];
                                                ?>
                                                <textarea class="invoice_special_note-values invoice_special_note-value-<?php echo $l_k; ?>" style="<?php echo $l_k == $local_l ? '' : 'display:none;';?>" name="invoice_special_note[<?php echo $l_k; ?>]" rows="3" placeholder="<?php echo __("admin/financial/invoice-special-note-desc"); ?>"><?php echo Config::get("options/invoice_special_note/".$l_k); ?></textarea>
                                                <?php
                                            }
                                        }
                                    ?>
                                    <span class="kinfo"><?php echo __("admin/financial/invoice-special-note-vars"); ?></span>
                                </div>
                            </div>
                            

                            <?php
                                if(Config::get("general/country") == "tr"){
                                    ?>
                                    <div class="formcon">
                                        <div class="yuzde30"><?php echo __("admin/financial/send-bill-to-address-status"); ?></div>
                                        <div class="yuzde70">
                                            <input type="checkbox" onclick="if($(this).prop('checked')) $('#sebilltad_con').slideDown(); else $('#sebilltad_con').slideUp();" class="sitemio-checkbox" id="sebilltad_status" name="sebilltad_status" value="1"<?php echo $settings["sebilltad"]["status"] ? ' checked' : NULL; ?>>
                                            <label class="sitemio-checkbox-label" for="sebilltad_status"></label>
                                            <span class="kinfo"><?php echo __("admin/financial/send-bill-to-address-status-desc"); ?></span>

                                            <div class="formcon" id="sebilltad_con" style="<?php echo $settings["sebilltad"]["status"] ? ''  : 'display:none;'; ?>">
                                                <div class="yuzde30"><?php echo __("admin/financial/send-bill-to-address-amount"); ?></div>

                                                <input type="text" style="width:50px;" name="sebilltad_amount" value="<?php echo Money::formatter($settings["sebilltad"]["amount"],$settings["sebilltad"]["cid"]); ?>" onkeypress='return event.charCode==44 || event.charCode==46 || event.charCode>= 48 &&event.charCode<= 57'>


                                                <select style="width:150px;" name="sebilltad_cid">
                                                    <?php
                                                        if($currencies = Money::getCurrencies($settings["sebilltad"]["cid"])){
                                                            foreach($currencies AS $currency){
                                                                ?>
                                                                <option value="<?php echo $currency["id"]; ?>"<?php echo $currency["id"] == $settings["sebilltad"]["cid"] ? ' selected' : NULL; ?>><?php echo $currency["name"]." (".$currency["code"].")"; ?></option>
                                                                <?php
                                                            }
                                                        }
                                                    ?>
                                                </select>
                                                <span class="kinfo"><?php echo __("admin/financial/send-bill-to-address-desc"); ?></span>
                                            </div>

                                        </div>
                                    </div>
                                    <?php
                                }
                            ?>


                            <div style="float:right;margin-top:10px;" class="guncellebtn yuzde30">
                                <a class="yesilbtn gonderbtn" id="advancedForm_submit" href="javascript:void(0);"><?php echo __("admin/financial/save-settings"); ?></a>
                            </div>
                            <div class="clear"></div>


                        </form>
                    </div>

                </div>

            </div>



        </div>
    </div>


</div>

<?php include __DIR__.DS."inc".DS."footer.php"; ?>

</body>
</html>