<?php
    $intstat         = Config::get("options/international-sms-service");
    $trsmsstat       = Config::get("options/turkey-sms-service");
    $smsapiser       = Config::get("options/sms-api-service");

    $get_module_page = $functions["get_module_page"];
    if(Filter::REQUEST("module_page_settings")){
        echo $get_module_page(Filter::init("POST/module_page_settings","route"),"settings");
        return true;
    }
?>
<!DOCTYPE html>
<html>
<head>
    <?php
        $plugins    = ['jquery-ui','select2'];
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
                <h1><?php echo __("admin/modules/sms-page-name"); ?></h1>
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

                        <div class="green-info" style="margin-bottom:20px;">
                            <div class="padding15">
                                <i class="fa fa-info-circle" aria-hidden="true"></i>
                                <p><?php echo __("admin/modules/sms-default-module-desc"); ?></p>
                            </div>
                        </div>



                        <form action="" method="post" id="defaultModule">
                            <input type="hidden" name="operation" value="save_settings">

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/modules/default-modules-select"); ?></div>
                                <div class="yuzde70">
                                    <select name="module">
                                        <option value=""><?php echo ___("needs/none"); ?></option>
                                        <?php
                                            foreach($modules AS $key=>$item) {
                                                $name       = $item["config"]["meta"]["name"];
                                                if(isset($item["lang"]["name"])) $name = $item["lang"]["name"];

                                                if(isset($item["config"]["meta"]["website"]))
                                                    $name .= " - ".$item["config"]["meta"]["website"];

                                                $selected = $key==$default_module;
                                                ?>
                                                <option value="<?php echo $key; ?>"<?php echo $selected ? ' selected' : NULL; ?>><?php echo $name; ?></option>
                                                <?php
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/modules/default-module-sms-intl-select"); ?></div>
                                <div class="yuzde70">
                                    <select name="module-intl" id="selectModule">
                                        <option value=""><?php echo ___("needs/none"); ?></option>
                                        <?php
                                            $showInternational = false;
                                            foreach($modules AS $key=>$item) {
                                                $module     = new $key();
                                                if($module->international){
                                                    $name       = $item["lang"]["name"];
                                                    $selected   = $key==$default_module_intl;
                                                    $module     = new $key();
                                                    if($selected) $showInternational = true;
                                                    if(isset($item["config"]["meta"]["website"]))
                                                        $name .= " - ".$item["config"]["meta"]["website"];
                                                    ?>
                                                    <option value="<?php echo $key; ?>"<?php echo $selected ? ' selected' : NULL; ?>><?php echo $name; ?></option>
                                                    <?php
                                                }
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>


                            <div id="international-content"<?php echo $showInternational ? '' : 'style="display:none;"'; ?>>
                                <div class="formcon">

                                    <div class="blue-info" style="margin-bottom:20px;">
                                        <div class="padding15">
                                            <p> <?php echo __("admin/modules/international-sms-service-description"); ?></p>
                                        </div>
                                    </div>

                                    <div id="informations" data-iziModal-title="<?php echo __("admin/modules/informationstitle"); ?>" style="display:none">
                                        <div class="padding20"><p><?php echo __("admin/modules/international-sms-service-description2"); ?></p></div>
                                    </div>

                                    <div class="clear"></div>
                                    <div class="yuzde30"><?php echo __("admin/modules/international-sms-service"); ?></div>
                                    <div class="yuzde70">
                                        <input type="checkbox" class="sitemio-checkbox" id="intsmsser" name="intsmsser" value="1"<?php echo $intstat ? ' checked' : NULL; ?>>
                                        <label class="sitemio-checkbox-label" for="intsmsser"></label>
                                        <span class="kinfo"><?php echo __("admin/modules/international-sms-service-desc"); ?></span>
                                    </div>
                                </div>
                            </div>

                            <?php if(Config::get("general/country") == "tr"): ?>
                                <div class="formcon">
                                    <div class="yuzde30"><?php echo __("admin/modules/turkey-sms-service"); ?></div>
                                    <div class="yuzde70">
                                        <input<?php echo $trsmsstat ? ' checked' : ''; ?> type="checkbox" id="turkey_sms_service" name="trsmsser" value="1" class="sitemio-checkbox">
                                        <label for="turkey_sms_service" class="sitemio-checkbox-label"></label>
                                        <span class="kinfo"><?php echo __("admin/modules/turkey-sms-service-desc"); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="formcon">
                                <div class="yuzde30"><?php echo __("admin/modules/sms-api-service"); ?></div>
                                <div class="yuzde70">
                                    <input<?php echo $smsapiser ? ' checked' : ''; ?> type="checkbox" name="sms-api-service" value="1" class="sitemio-checkbox" id="api_service">
                                    <label for="api_service" class="sitemio-checkbox-label"></label>
                                    <span class="kinfo"><?php echo __("admin/modules/sms-api-service-desc"); ?></span>
                                </div>
                            </div>


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
                            $recommended_modules = Utility::HttpRequest('https://www.wisecp.com/remotedata/modules/recommended-sms-modules.php?'.http_build_query([
                                    'lang'          => $ui_lang,
                                    'country'       => Config::get("general/country"),
                                    'used'          => $default_module,
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


                                <?php
                                    foreach($modules AS $key=>$item){
                                        $name    = $item["config"]["meta"]["name"];
                                        if(isset($item["lang"]["name"])) $name = $item["lang"]["name"];
                                        $version = isset($item["config"]["meta"]["version"]) ? $item["config"]["meta"]["version"] : false;
                                        $logo = isset($item["config"]["meta"]["logo"]) ? $item["config"]["meta"]["logo"] : false;
                                        if($logo) $logo = Utility::image_link_determiner($logo,$module_url.$key.DS);
                                        $page   = $get_module_page($key,"settings");

                                        if(isset($item["config"]["meta"]["website"]))
                                            $name .= " - ".$item["config"]["meta"]["website"];

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
                                    <div style="display: inline-block;margin-bottom:40px;">
                                        <div class="verticaltabstitle">
                                            <h2><?php echo __("admin/modules/activated-modules"); ?></h2>
                                        </div>

                                        <ul class="tab module-buttons" style="margin-bottom: 0px;">
                                            <?php
                                                if($default_module == "none") $default_module = [];
                                                if($default_module)
                                                {
                                                    $default_module = [$default_module];

                                                    foreach($default_module AS $key)
                                                    {
                                                        $item   = $modules[$key] ?? [];
                                                        $name   = isset($item["config"]["meta"]["name"]) ? $item["config"]["meta"]["name"] : $key;
                                                        if(isset($item["lang"]["name"])) $name = $item["lang"]["name"];
                                                        ?>
                                                        <li><a href="javascript:void(0)" class="tablinks activated-module" onclick="open_tab(this, '<?php echo $key; ?>','module')" data-tab="<?php echo $key; ?>"><span><?php echo $name; ?></span></a></li>
                                                        <?php
                                                    }
                                                }
                                            ?>
                                        </ul>

                                        <div class="clear"></div>
                                        <span class="kinfo"><i class="fa fa-info-circle" aria-hidden="true"></i> <?php echo __("admin/modules/activated-modules-desc"); ?></span>
                                    </div>


                                    <div class="clear"></div>

                                    <ul class="tab" id="moduleList">

                                        <div class="module-search">
                                            <h4><strong><?php echo __("admin/modules/search-module-1"); ?></strong></h4>
                                            <input type="text" id="searchInput" onkeyup="searchModules();" value="" placeholder="<?php echo __("admin/modules/search-module-2"); ?>">
                                            <i class="fa fa-search" aria-hidden="true"></i>
                                            <div class="clear"></div>
                                        </div>
                                        <div class="clear"></div>


                                        <?php
                                            if(!is_array($default_module)) $default_module = [];
                                            foreach($modules AS $key=>$item){
                                                if(in_array($key,$default_module)) continue;
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