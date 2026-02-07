<?php
    $get_module_page = $functions["get_module_page"];
    if(Filter::REQUEST("module_page_settings")){
        $m_name     = Filter::init("POST/module_page_settings","route");

        if($m_name == "WFraud")
        {

            ?>
            <form action="" method="post" id="defaultModule">

                <div class="blue-info">
                    <div class="padding20">
                        <i class="fa fa-shield"></i>
                        <p> <?php echo __("admin/settings/WFraud-description"); ?> </p>
                    </div>
                </div>



                <input type="hidden" name="operation" value="update_WFraud">

                <div class="formcon">
                    <div class="yuzde30"><?php echo __("admin/settings/blacklist-status"); ?></div>
                    <div class="yuzde70">
                        <input<?php echo Config::get("options/blacklist/status") ? ' checked' : ''; ?> type="checkbox" name="status" value="1" id="blacklist-status" class="sitemio-checkbox">
                        <label for="blacklist-status" class="sitemio-checkbox-label"></label>
                        <span class="kinfo"><?php echo __("admin/settings/blacklist-status-info"); ?></span>
                    </div>
                </div>

                <div class="formcon">
                    <div class="yuzde30"><?php echo __("admin/settings/blacklist-use-producer-as-source"); ?></div>
                    <div class="yuzde70">
                        <input<?php echo Config::get("options/blacklist/use-producer-as-source") ? ' checked' : ''; ?> type="checkbox" name="use-producer-as-source" value="1" id="blacklist-use-producer-as-source" class="sitemio-checkbox">
                        <label for="blacklist-use-producer-as-source" class="sitemio-checkbox-label"></label>
                        <span class="kinfo"><?php echo __("admin/settings/blacklist-use-producer-as-source-info"); ?></span>
                        <div class="clear"></div>
                        <span class="kinfo"><?php echo __("admin/settings/blacklist-use-producer-as-source-warning"); ?></span>
                    </div>
                </div>

                <div class="formcon">
                    <div class="yuzde30"><?php echo __("admin/settings/blacklist-risk-score"); ?></div>
                    <div class="yuzde70">
                        <input type="text" name="risk-score" class="yuzde15" style="vertical-align: middle;" value="<?php echo Config::get("options/blacklist/risk-score"); ?>">
                        <span class="kinfo"><?php echo __("admin/settings/blacklist-risk-score-info"); ?></span>
                    </div>
                </div>

                <div class="formcon">
                    <div class="yuzde30"><?php echo __("admin/settings/blacklist-order-blocking"); ?></div>
                    <div class="yuzde70">
                        <input<?php echo Config::get("options/blacklist/order-blocking") ? ' checked' : ''; ?> type="checkbox" name="order-blocking" value="1" id="blacklist-order-blocking" class="sitemio-checkbox">
                        <label for="blacklist-order-blocking" class="sitemio-checkbox-label"></label>
                        <span class="kinfo"><?php echo __("admin/settings/blacklist-order-blocking-info"); ?></span>
                        <div class="clear"></div>
                    </div>
                </div>

                <div class="formcon">
                    <div class="yuzde30"><?php echo __("admin/settings/blacklist-ip-country-mismatch"); ?></div>
                    <div class="yuzde70">
                        <input<?php echo Config::get("options/blacklist/ip-country-mismatch") ? ' checked' : ''; ?> type="checkbox" name="ip-country-mismatch" value="1" id="blacklist-ip-country-mismatch" class="sitemio-checkbox">
                        <label for="blacklist-ip-country-mismatch" class="sitemio-checkbox-label"></label>
                        <span class="kinfo"><?php echo __("admin/settings/blacklist-ip-country-mismatch-info"); ?></span>
                        <div class="clear"></div>
                    </div>
                </div>
                <div class="formcon">
                    <div class="yuzde30"><?php echo __("admin/settings/proxy-block"); ?></div>
                    <div class="yuzde70">
                        <input <?php echo Config::get("options/proxy-block") ? 'checked ' : NULL; ?>type="checkbox" class="sitemio-checkbox" name="proxy-block" value="1" id="proxy-block" onchange="if($(this).prop('checked')) $('#proxy-block-wrap').css('display','block'); else $('#proxy-block-wrap').css('display','none');">
                        <label class="sitemio-checkbox-label" for="proxy-block"></label>
                        <span class="kinfo"><?php echo __("admin/settings/proxy-block-desc"); ?></span>

                        <div class="formcon" style="<?php echo Config::get("options/proxy-block") ? '' : 'display: none;'; ?>" id="proxy-block-wrap">
                            <div style="display: none;">
                                <input<?php echo Config::get("options/proxy-block-host") ? ' checked' : ''; ?> type="checkbox" id="i-have-premium-subscription" class="checkbox-custom" onchange="if($(this).prop('checked')) $('#proxy-block-host').css('display','block'); else $('#proxy-block-host').css('display','none').val('');">
                                <label class="checkbox-custom-label" for="i-have-premium-subscription"><span class="kinfo"><?php echo __("admin/settings/proxy-block-i-have-premium-subscription"); ?></span></label>

                            </div>

                            <div class="clear"></div>
                            <input type="text" id="proxy-block-host" name="proxy-block-host" value="<?php echo Config::get("options/proxy-block-host"); ?>" placeholder="<?php echo __("admin/settings/proxy-block-host"); ?>" style="<?php echo Config::get("options/proxy-block-host") ? '' : 'display:none;'; ?>">
                            <div class="clear"></div>
                            <textarea placeholder="Whitelist:
192.168.1.0/24
192.168.1.2
AS123452" name="proxy-block-whitelist" rows="5"><?php echo Config::get("options/proxy-block-whitelist"); ?></textarea>
                        </div>

                    </div>
                </div>

                <div class="clear"></div>

                <div style="float:right;" class="guncellebtn yuzde30"><a id="defaultModule_submit" href="javascript:void(0);" class="yesilbtn gonderbtn"><?php echo __("admin/modules/save-settings-button"); ?></a></div>



            </form>
            <script type="text/javascript">
                $(document).ready(function(){

                    $("#defaultModule_submit").click(function(){
                        MioAjaxElement($(this),{
                            waiting_text:waiting_text,
                            progress_text:progress_text,
                            result:"defaultModule_handler",
                        });
                    });
                });
                function defaultModule_handler(result){
                    if(result != ''){
                        var solve = getJson(result);
                        if(solve !== false){
                            if(solve.status == "error"){
                                if(solve.for != undefined && solve.for != ''){
                                    $("#defaultModule "+solve.for).focus();
                                    $("#defaultModule "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                                    $("#defaultModule "+solve.for).change(function(){
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
            <?php
            return true;
        }

        $key        = $m_name;
        $m_name2    = "Fraud_".$m_name;
        Modules::Load("Fraud",$m_name);

        $init = new $m_name2();
        $fields = $init->fields();

        ?>
        <div id="<?php echo $key; ?>_tab">
            <ul class="modules-tabs">
                <li><a href="javascript:<?php echo $key; ?>_open_tab(this,'configuration');" data-tab="configuration" class="modules-tab-item active"><?php echo __("admin/settings/fraud-protection-module-tab-configuration"); ?></a></li>
                <li><a href="javascript:<?php echo $key; ?>_open_tab(this,'records');" data-tab="records" class="modules-tab-item"><?php echo __("admin/settings/fraud-protection-module-tab-records"); ?></a></li>
            </ul>

            <div id="<?php echo $key; ?>_tab-configuration" class="modules-tabs-content" style="display: block">
                <form action="<?php echo $links["controller"]; ?>" method="post" id="<?php echo $key; ?>Form">
                    <input type="hidden" name="operation" value="update_fraud_protection">
                    <input type="hidden" name="module" value="<?php echo $key; ?>">

                    <div class="clear"></div>

                    <?php $init->fields_output($fields); ?>

                    <div class="clear"></div>


                    <div style="float:right;" class="guncellebtn yuzde30"><a id="<?php echo $key; ?>Form_submit" href="javascript:void(0);" class="yesilbtn gonderbtn"><?php echo __("admin/modules/save-settings-button"); ?></a></div>



                </form>
            </div>

            <div id="<?php echo $key; ?>_tab-records" class="modules-tabs-content" style="display: none;">
                <div class="clear"></div>

                <?php
                    if(method_exists($init,'records')){
                        ?>
                        <script type="text/javascript">
                            $(document).ready(function(){
                                $('#<?php echo $key; ?>_records').DataTable({
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
                            });
                        </script>

                        <table width="100%" id="<?php echo $key; ?>_records" class="table table-striped table-borderedx table-condensed nowrap">
                            <thead style="background:#ebebeb;">
                            <tr>
                                <th align="center" data-orderable="false">#</th>
                                <th align="left" data-orderable="false"><?php echo __("admin/settings/fraud-protection-module-th-user"); ?></th>
                                <th align="left" data-orderable="false"><?php echo __("admin/settings/fraud-protection-module-th-message"); ?></th>
                                <th align="left" data-orderable="false"><?php echo __("admin/settings/fraud-protection-module-th-date"); ?></th>
                            </tr>
                            </thead>
                            <tbody align="center" style="border-top:none;">
                            <?php
                                $list   = $init->records();
                                $i = 0;
                                if($list){
                                    foreach($list AS $row){
                                        $i++;
                                        ?>
                                        <tr>
                                            <td align="center"><?php echo $i; ?></td>
                                            <td align="left"><a href="<?php echo Controllers::$init->AdminCRLink("users-2",["detail",$row["user_id"]])?>" target="_blank"><?php echo $row["user_full_name"]; ?></a></td>
                                            <td align="left"><?php echo $row["message"]; ?></td>
                                            <td align="left">
                                                <?php echo DateManager::format(Config::get("options/date-format").' H:i',$row["created_at"]); ?>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                }
                            ?>
                            </tbody>
                        </table>

                        <?php



                    }else{
                        echo 'Not found records function';
                    }
                ?>


            </div>


        </div>
        <script type="text/javascript">
            $(document).ready(function(){
                $("#<?php echo $key; ?>Form_submit").click(function(){
                    MioAjaxElement($(this),{
                        waiting_text:waiting_text,
                        progress_text:progress_text,
                        result:"Form_handler",
                    });
                });
            });

            function <?php echo $key; ?>_open_tab(elem, tabName){
                var owner = "<?php echo $key; ?>_tab";
                $("#"+owner+" .modules-tabs-content").css("display","none");
                $("#"+owner+" .modules-tabs .modules-tab-item").removeClass("active");
                $("#"+owner+"-"+tabName).css("display","block");
                $("#"+owner+" .modules-tabs .modules-tab-item[data-tab='"+tabName+"']").addClass("active");
            }

            function Form_handler(result){
                if(result != ''){
                    var solve = getJson(result);
                    if(solve !== false){
                        if(solve.status == "error"){
                            if(solve.message != undefined && solve.message != '')
                                alert_error(solve.message,{timer:5000});
                        }else if(solve.status == "successful")
                            alert_success(solve.message,{timer:2500});
                    }else
                        console.log(result);
                }
            }
        </script>
        <?php


        return true;
    }
?>
<!DOCTYPE html>
<html>
<head>
    <?php
        $plugins    = ['jquery-ui','select2','dataTables'];
        include __DIR__.DS."inc".DS."head.php";
    ?>
    <script type="text/javascript">
        var
            waiting_text  = '<?php echo ___("needs/button-waiting"); ?>',
            progress_text = '<?php echo ___("needs/button-uploading"); ?>';
    </script>

    <script type="text/javascript">
        $(function(){

            var tab = _GET("mainTab");
            if(tab != '' && tab != undefined){
                $("#tab-mainTab .tablinks[data-tab='"+tab+"']").click();
            }
            else{
                $("#tab-mainTab .tablinks:eq(0)").addClass("active");
                $("#tab-mainTab .tabcontent:eq(0)").css("display","block");
            }

            var xtab = _GET("module");
            if (xtab != '' && xtab != undefined) {
                $("#tab-module .tablinks[data-tab='" + xtab + "']").click();
                moduleActiveTab(xtab);
            }

            $("#tab-module .tablinks").click(function(){
                var key = $(this).data("tab");
                moduleActiveTab(key);
            });

            $("#mainTabAllBtn").click(function(){
                var tab = _GET("module");
                if (tab != '' && tab != undefined) moduleCloseTab(tab);
            });

        });

        function moduleActiveTab(key){
            var xtab = _GET("mainTab");
            if(xtab !== "all") $("#tab-mainTab .tablinks[data-tab='all']").click();
            $("#module-"+key+" .module-page-loading").css("display","block");
            $("#module-"+key+" .module-page-content").css("display","none");
            $("#moduleList-wrap").css('display','none');

            var request     = MioAjax({
                action:window.location.href,
                method:"POST",
                data:{module_page_settings:key},
            },true,true);

            request.done(function(result){
                $("#module-"+key+" .module-page-loading").css("display","none");

                $("#module-"+key+" .module-page-content").css("display","block").html(result);

                <?php if(Config::get("options/accessibility")): ?>
                $('.sitemio-checkbox,.checkbox-custom,.radio-custom').each(function(){
                    $(this).attr('class','sitemio-checkbox-accessibility');
                });
                $('.sitemio-checkbox-label').each(function(){
                    $(this).css('display','none');
                });
                <?php endif; ?>

            });
        }
        function moduleCloseTab(key)
        {
            $("#module-"+key).css('display','none');
            $("#moduleList-wrap").css('display','block');
            var link = set_GET("module","");
            var title = $("title").html();
            window.history.pushState("object or string",title,link);
            $("#tab-module .tablinks").removeClass('active');
        }

        function searchModules() {
            // Declare variables
            var input, filter, ul, li, a, i, txtValue;
            input = document.getElementById('searchInput');
            filter = input.value.toUpperCase();
            ul = document.getElementById("moduleList");
            li = ul.getElementsByTagName('li');

            // Loop through all list items, and hide those who don't match the search query
            for (i = 0; i < li.length; i++) {
                a = li[i].getElementsByTagName("a")[0];
                txtValue = a.textContent || a.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    li[i].style.display = "";
                } else {
                    li[i].style.display = "none";
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
                <h1><?php echo __("admin/settings/page-fraud-protection"); ?></h1>
                <?php
                $ui_help_link = 'https://docs.wisecp.com/en/kb/wfraud';
                if($ui_lang == "tr") $ui_help_link = 'https://docs.wisecp.com/tr/kb/wfraud';
                ?>
                <a title="<?php echo __("admin/help/usage-guide"); ?>" target="_blank" class="pagedocslink" href="<?php echo $ui_help_link; ?>"><i class="fa fa-life-ring" aria-hidden="true"></i></a>
                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>


            <div class="clear"></div>

            <div id="tab-mainTab">
                <ul class="tab">
                    <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'settings','mainTab')" data-tab="settings"><i class="fa fa-cogs" aria-hidden="true"></i> <?php echo __("admin/modules/default-module"); ?></a></li>
                    <li><a href="javascript:void(0)" class="tablinks" id="mainTabAllBtn" onclick="open_tab(this, 'all','mainTab')" data-tab="all"><i class="fa fa-list" aria-hidden="true"></i> <?php echo __("admin/modules/modulestitle"); ?></a></li>
                </ul>

                <div id="mainTab-settings" class="tabcontent">

                    <div class="adminpagecon">
                        <?php
                            $recommended_modules = Utility::HttpRequest('https://www.wisecp.com/remotedata/modules/recommended-fraud-protection-modules.php?'.http_build_query([
                                    'lang'          => $ui_lang,
                                    'country'       => Config::get("general/country"),
                                    'used'          => [],
                                    'app_address'   => APP_URI,
                                ]),['timeout' => 3]);
                            if($recommended_modules && stristr($recommended_modules,'recommended-module'))
                            {
                                ?>
                                <div class="recommended-modules">

                                    <div class="verticaltabstitle">
                                        <h2><i class="fa fa-star" aria-hidden="true"></i> <?php echo __("admin/modules/recommended"); ?></h2>
                                    </div>

                                    <?php echo $recommended_modules; ?>


                                    <div class="allmoduleslink">
                                        <h5><?php echo __("admin/modules/more-recommended",['{link}' => "javascript:$('#mainTabAllBtn').click();void 0;"]); ?></h5>
                                        <p><?php echo __("admin/modules/more-modules"); ?></p>
                                    </div>

                                </div>
                                <?php
                            }
                        ?>

                        <div class="clear"></div>
                    </div>



                    <div class="clear"></div>


                </div>

                <div id="mainTab-all" class="tabcontent">

                    <div class="verticaltabs">
                        <div class="verticaltabscon">
                            <div id="tab-module"><!-- tab wrap content start -->


                                <div id="module-WFraud" class="tabcontent"><!-- tab item start -->

                                    <div class="verticaltabstitle">
                                        <h2><?php echo __("admin/settings/WFraud"); ?></h2>
                                    </div>

                                    <div class="module-page-loading">

                                        <div class="load-wrapp">
                                            <p style="margin-bottom:20px;font-size:17px;"><strong><?php echo ___("needs/processing"); ?>...</strong><br><?php echo ___("needs/please-wait"); ?></p>
                                            <div class="load-7">
                                                <div class="square-holder">
                                                    <div class="square"></div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                    <div class="module-page-content"></div>

                                    <a class="module-btn-close lbtn" href="javascript:moduleCloseTab('WFraud');void 0;"> <i class="fa fa-angle-double-left"></i> <?php echo __("admin/tools/button-turn-back"); ?></a>


                                    <div class="clear"></div>
                                </div><!-- tab item end -->


                                <?php
                                    foreach($modules AS $key=>$item){
                                        $name    = $item["config"]["meta"]["name"];
                                        if(isset($item["lang"]["name"])) $name = $item["lang"]["name"];
                                        $version = isset($item["config"]["meta"]["version"]) ? $item["config"]["meta"]["version"] : false;
                                        $logo = isset($item["config"]["meta"]["logo"]) ? $item["config"]["meta"]["logo"] : false;
                                        if($logo) $logo = Utility::image_link_determiner($logo,$module_url.$key.DS);
                                        $page   = $get_module_page($key,"settings");
                                        ?>
                                        <div id="module-<?php echo $key; ?>" class="tabcontent"><!-- tab item start -->

                                            <div class="verticaltabstitle">
                                                <h2><?php echo $name; ?>
                                                    <?php if($logo): ?>
                                                        <img style="float:right" src="<?php echo $logo; ?>" height="35"/>
                                                    <?php endif; ?>
                                                </h2>
                                            </div>

                                            <div class="module-page-loading">

                                                <div class="load-wrapp">
                                                    <p style="margin-bottom:20px;font-size:17px;"><strong><?php echo ___("needs/processing"); ?>...</strong><br><?php echo ___("needs/please-wait"); ?></p>
                                                    <div class="load-7">
                                                        <div class="square-holder">
                                                            <div class="square"></div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                            <div class="module-page-content"></div>

                                            <a class="module-btn-close lbtn" href="javascript:moduleCloseTab('<?php echo $key; ?>');void 0;"> <i class="fa fa-angle-double-left"></i> <?php echo __("admin/tools/button-turn-back"); ?></a>


                                            <div class="clear"></div>
                                        </div><!-- tab item end -->
                                        <?php
                                    }
                                ?>

                                <div id="moduleList-wrap">

                                    <ul class="tab" id="moduleList">

                                        <div class="module-search">
                                            <h4><strong><?php echo __("admin/modules/search-module-1"); ?></strong></h4>
                                            <input type="text" id="searchInput" onkeyup="searchModules();" value="" placeholder="<?php echo __("admin/modules/search-module-2"); ?>">
                                            <i class="fa fa-search" aria-hidden="true"></i>
                                            <div class="clear"></div>
                                        </div>
                                        <div class="clear"></div>


                                        <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'WFraud','module')" data-tab="WFraud"><span>WFraud</span></a></li>

                                        <?php
                                            foreach($modules AS $key=>$item){
                                                $name = isset($item["config"]["meta"]["name"]) ? $item["config"]["meta"]["name"] : $key;
                                                if(isset($item["lang"]["name"])) $name = $item["lang"]["name"];
                                                ?>
                                                <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, '<?php echo $key; ?>','module')" data-tab="<?php echo $key; ?>"><span><?php echo $name; ?></span></a></li>
                                                <?php
                                            }
                                        ?>

                                        <div class="allmoduleslink">
                                            <p><?php echo __("admin/modules/more-modules"); ?></p>
                                        </div>
                                    </ul>
                                </div>

                            </div><!-- tab wrap content end -->

                        </div>
                    </div>

                </div>



            </div>



        </div>
    </div>


</div>

<?php include __DIR__.DS."inc".DS."footer.php"; ?>

</body>
</html>