<?php
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
        $plugins    = ['jquery-ui','dataTables','select2'];
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


    <style>
        .load-wrapp{width:150px;margin:55px auto;text-align:center;color:#607D8B}
        .load-7{display:inline-block;margin-left:-70px}
        .square{width:12px;height:12px;border-radius:4px;background-color:#607D8B}
        .spinner{position:relative;width:45px;height:45px;margin:0 auto}
        .l-1{animation-delay:.48s}
        .l-2{animation-delay:.6s}
        .l-3{animation-delay:.72s}
        .l-4{animation-delay:.84s}
        .l-5{animation-delay:.96s}
        .l-6{animation-delay:1.08s}
        .l-7{animation-delay:1.2s}
        .l-8{animation-delay:1.32s}
        .l-9{animation-delay:1.44s}
        .l-10{animation-delay:1.56s}

        .load-7 .square {animation: loadingG 1.5s cubic-bezier(.17,.37,.43,.67) infinite;}

        @keyframes loadingA {
            50%{height:15px 35px}
            100%{height:15px}
        }
        @keyframes loadingB {
            50%{width:15px 35px}
            100%{width:15px}
        }
        @keyframes loadingC {
            50%{transform:translate(0,0) translate(0,15px)}
            100%{transform:translate(0,0)}
        }
        @keyframes loadingD {
            50%{transform:rotate(0deg) rotate(180deg)}
            100%{transform:rotate(360deg)}
        }
        @keyframes loadingE {
            100%{transform:rotate(0deg) rotate(360deg)}
        }
        @keyframes loadingF {
            0%{opacity:0}
            100%{opacity:1}
        }
        @keyframes loadingG {
            0%{transform:translate(0,0) rotate(0deg)}
            50%{transform:translate(70px,0) rotate(360deg)}
            100%{transform:translate(0,0) rotate(0deg)}
        }
        @keyframes loadingH {
            0%{width:15px}
            50%{width:35px;padding:4px}
            100%{width:15px}
        }
        @keyframes loadingI {
            100%{transform:rotate(360deg)}
        }
        @keyframes bounce {
            0%,100%{transform:scale(0.0)}
            50%{transform:scale(1.0)}
        }
        @keyframes loadingJ {
            0%,100%{transform:translate(0,0)}
            50%{transform:translate(80px,0);background-color:#607D8B;width:25px}
        }
    </style>

</head>
<body>

<?php include __DIR__.DS."inc/header.php"; ?>

<div id="wrapper">

    <div class="icerik-container">
        <div class="icerik">

            <div class="icerikbaslik">
                <h1><?php echo __("admin/modules/registrars-page-name"); ?></h1>
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
                            $recommended_modules = Utility::HttpRequest('https://www.wisecp.com/remotedata/modules/recommended-registrar-modules.php?'.http_build_query([
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

                                            <div class="clear"></div>
                                            <a class="module-btn-close lbtn" href="javascript:moduleCloseTab('<?php echo $key; ?>');void 0;"> <i class="fa fa-angle-double-left"></i> <?php echo __("admin/tools/button-turn-back"); ?></a>


                                            <div class="clear"></div>
                                        </div><!-- tab item end -->
                                        <?php
                                    }
                                ?>

                                <div id="moduleList-wrap">

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