<script type="text/javascript">
    $(document).ready(function(){
        $("#ParasutIgnorePmethods").select2({width:'100%'});
        $("#Parasut_submit_button").click(function(){
            $("#Parasut_settingsForm input[name=test]").val('0');
            MioAjaxElement($(this),{
                waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                result:"Parasut_settingsForm_handler",
            });
        });

        $("#Parasut_test_button").click(function(){
            $("#Parasut_settingsForm input[name=test]").val('1');
            MioAjaxElement($(this),{
                waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                result:"Parasut_settingsForm_handler",
            });
        });

    });

    function Parasut_settingsForm_handler(result){
        var test = $("#Parasut_settingsForm input[name=test]").val();
        if(result != ''){
            var solve = getJson(result);
            if(solve !== false){
                if(solve.status == "error"){
                    if(solve.for != undefined && solve.for != ''){
                        $("#Parasut_settingsForm "+solve.for).focus();
                        $("#Parasut_settingsForm "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                        $("#Parasut_settingsForm "+solve.for).change(function(){
                            $(this).removeAttr("style");
                        });
                    }
                    if(solve.message != undefined && solve.message != '')
                        alert_error(solve.message,{timer:10000});
                }else if(solve.status == "successful"){
                    if(solve.message != undefined){
                        if(test==1){
                            swal({
                                type:"success",
                                title:solve.message,
                                timer:3000,
                            });
                        }else{
                            alert_success(solve.message,{timer:3000});
                        }
                    }

                    if(solve.redirect != undefined && solve.redirect != '') window.location.href = solve.redirect;
                }
            }else
                console.log(result);
        }
    }
</script>
<form action="<?php echo $request_uri; ?>?module=Parasut" method="post" id="Parasut_settingsForm">

    <div class="padding20">

    <input type="hidden" name="operation" value="get_addon_content">
    <input type="hidden" name="module_operation" value="save_config">
    <input type="hidden" name="test" value="0">

    <div class="formcon" style="display:none">
        <div class="yuzde30">Cronjob Son Çalışma Zamanı</div>
        <div class="yuzde70">
            <?php echo $cronjobs["last-run-time"] ? DateManager::timetostr(Config::get("options/date-format")." - H:i",$cronjobs["last-run-time"]) : __("admin/automation/never-worked"); ?>
        </div>
    </div>

    <?php
        $required = '<span class="kinfo" style="font-weight: bold; color: red;">*</span>';
    ?>
    <div class="formcon">
        <div class="yuzde30">Client ID <?php echo $required; ?></div>
        <div class="yuzde70">
            <input type="text" name="client_id" value="<?php echo $config["settings"]["client_id"]; ?>">
        </div>
    </div>

    <div class="formcon">
        <div class="yuzde30">Client Secret <?php echo $required; ?></div>
        <div class="yuzde70">
            <input type="password" name="client_secret" value="<?php echo $config["settings"]["client_secret"] ? "*****" : ''; ?>">
        </div>
    </div>

    <div class="formcon" style="display: none;">
        <div class="yuzde30">Callback urls <?php echo $required; ?></div>
        <div class="yuzde70">
            <input type="text" name="callback_urls" value="<?php echo $config["settings"]["callback_urls"]; ?>">
        </div>
    </div>

    <div class="formcon">
        <div class="yuzde30">Firma ID <?php echo $required; ?></div>
        <div class="yuzde70">
            <input type="text" name="company_id" value="<?php echo $config["settings"]["company_id"]; ?>">
        </div>
    </div>

    <div class="formcon">
        <div class="yuzde30">E-Posta <?php echo $required; ?></div>
        <div class="yuzde70">
            <input type="text" name="username" value="<?php echo $config["settings"]["username"]; ?>">
        </div>
    </div>

    <div class="formcon">
        <div class="yuzde30">Parola <?php echo $required; ?></div>
        <div class="yuzde70">
            <input type="password" name="password" value="<?php echo $config["settings"]["password"] ? "*****" : ''; ?>">
        </div>
    </div>



    <?php
        $r_gun = $cronjobs["tasks"]["invoices-to-be-formalized"]["settings"]["day"] ?? 1;
    ?>
    <script type="text/javascript">
        $(document).ready(function(){
            $("input[name=resmilestirme_turu]").change(function(){
                var section = $("input[name=resmilestirme_turu]:checked").val();
                if(section === 'manuel'){
                    $('input[name=formalize_day]').val('999');
                    $('#resmilestirme_wrap').css('display','none');
                    $('#resmilestirme_manuel').css('display','block');
                }
                else
                {
                    $('input[name=formalize_day]').val('1');
                    $('#resmilestirme_wrap').css('display','block');
                    $('#resmilestirme_manuel').css('display','none');
                }
            });
        });
    </script>
    <div class="formcon">
        <div class="yuzde30">Faturaları Resmileştir</div>
        <div class="yuzde70">
            <input<?php echo $r_gun >= 999 ? '' : ' checked'; ?> type="radio" name="resmilestirme_turu" value="auto" id="resmilestirme_turu_auto" class="radio-custom">
            <label class="radio-custom-label" for="resmilestirme_turu_auto" style="margin-right: 5px;">Otomatik</label>

            <input<?php echo $r_gun >= 999 ? ' checked' : ''; ?> type="radio" name="resmilestirme_turu" value="manuel" id="resmilestirme_turu_manuel" class="radio-custom">
            <label class="radio-custom-label" for="resmilestirme_turu_manuel" style="margin-right: 5px;">Manuel</label>

            <div<?php echo $r_gun >= 999 ? ' style="display:none;"' : ''; ?> id="resmilestirme_wrap">
                <span class="kinfo">Fatura ödenme tarihi itibari ile kaç gün sonra otomatik olarak resmileştirilsin?</span>
                <div class="clear"></div>
                <span style="width: 150px;">Otomatik Resmileştirme Günü</span>
                <input style="width: 50px;text-align: center;font-weight: 600;" type="text" name="formalize_day" value="<?php echo $r_gun; ?>">
            </div>
            <div id="resmilestirme_manuel"<?php echo !($r_gun >= 999) ? ' style="display:none;"' : ''; ?>>
                <span class="kinfo">Faturalar yönetici tarafından resmileştirilir.</span>
            </div>


        </div>
    </div>

    <div class="formcon"style="">
        <div class="yuzde30">Kdv Muafiyet Kodu <?php echo $required; ?></div>
        <div class="yuzde70">
            <input type="text" name="vat_exemption_reason_code" value="<?php echo $config["settings"]["vat_exemption_reason_code"]; ?>">
        </div>
    </div>

    <div class="formcon"style="">
        <div class="yuzde30">Kdv Muafiyet Açıklaması <?php echo $required; ?></div>
        <div class="yuzde70">
            <input type="text" name="vat_exemption_reason" value="<?php echo $config["settings"]["vat_exemption_reason"]; ?>">
        </div>
    </div>

        <div class="formcon">
            <div class="yuzde30">Kasa ID</div>
            <div class="yuzde70">
                <input type="text" name="account_id" value="<?php echo $config["settings"]["account_id"] ?? ''; ?>" placeholder="">
                <span class="kinfo">Kasa ID No değiştiği taktirde buradan yeni Kasa ID No tanımlayabilirsiniz.</span>
            </div>
        </div>

        <div class="formcon">
            <div class="yuzde30">Fatura ID Ön Ek</div>
            <div class="yuzde70">
                <input type="text" name="prefix" value="<?php echo $config["settings"]["prefix"] ?? ''; ?>" placeholder="">
                <span class="kinfo">Paraşüt panelinizde birden fazla sistem kullanıyorsanız ID çakışmasını önlemek için bir ön ek tanımlayınız.</span>
            </div>
        </div>

        <div class="formcon">
            <div class="yuzde30">Ödeme Yöntemlerini Yoksay</div>
            <div class="yuzde70">
                <select name="ignore_pmethods[]" id="ParasutIgnorePmethods" multiple>
                    <?php
                        $pmethods = Config::get("modules/payment-methods");
                        foreach($pmethods AS $p)
                        {
                            $selected = in_array($p, $config["settings"]["ignore_pmethods"] ?? []) ? ' selected="selected"' : '';
                            ?>
                            <option<?php echo $selected; ?> value="<?php echo $p; ?>"><?php echo $p; ?></option>
                            <?php
                        }
                    ?>
                </select>
                <span class="kinfo">Seçilen ödeme yöntemleri ile ödenen faturalar Paraşüt'e gönderilmez.</span>
            </div>
        </div>

    </div>

    <div class="clear"></div>

    <div style="float: left;" class="guncellebtn yuzde30">
        <a href="javascript:void 0;" class="lbtn" id="Parasut_test_button"><i class="fa fa-plug"></i> Bağlantıyı Test Et</a>
    </div>

    <div class="clear"></div>

    <div class="guncellebtn yuzde30" style="float: right;">
        <a href="javascript:void 0;" style="float:right;" class="gonderbtn yesilbtn" id="Parasut_submit_button"><?php echo ___("needs/button-save"); ?></a>
    </div>
    <div class="clear"></div>

</form>
<div class="clear"></div>