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

            var tab         = _GET("mainTab");
            var module      = _GET("module");

            if(module != '' && module != undefined) tab = "all";


            if(tab != '' && tab != undefined){
                $("#tab-mainTab .tablinks[data-tab='"+tab+"']").click();
            }
            else{
                $("#tab-mainTab .tablinks:eq(0)").addClass("active");
                $("#tab-mainTab .tabcontent:eq(0)").css("display","block");
            }

        });

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
                <h1><?php echo $page_name; ?></h1>
                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>


            <div class="clear"></div>


            <?php
                if($modules && sizeof($modules) > 0)
                {
                    ?>
                    <div id="tab-mainTab">
                        <ul class="tab">
                            <li><a href="javascript:void(0)" class="tablinks" onclick="open_tab(this, 'settings','mainTab')" data-tab="settings"><i class="fa fa-cogs" aria-hidden="true"></i> <?php echo __("admin/modules/default-module"); ?></a></li>
                            <li><a href="<?php echo $module ? $links["controller"]."?mainTab=all" : 'javascript:void(0);'; ?>" class="tablinks" id="mainTabAllBtn" onclick="open_tab(this, 'all','mainTab')" data-tab="all"><i class="fa fa-list" aria-hidden="true"></i> <?php echo __("admin/modules/modulestitle"); ?></a></li>
                        </ul>

                        <div id="mainTab-settings" class="tabcontent">

                            <div class="adminpagecon">
                                <?php
                                    $recommended_modules = Utility::HttpRequest('https://www.wisecp.com/remotedata/modules/recommended-product-api-modules.php?'.http_build_query([
                                            'group'         => $module_group,
                                            'lang'          => $ui_lang,
                                            'country'       => Config::get("general/country"),
                                            'used'          => '',
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
                                            if($module){
                                                ?>
                                                <div class="tabcontent" style="display: block">
                                                    <?php
                                                        $name    = isset($m_data["lang"]["name"]) ? $m_data["lang"]["name"] : $m_data["config"]["meta"]["name"];
                                                        $logo    = isset($m_data["config"]["meta"]["logo"]) ? $m_data["config"]["meta"]["logo"] : false;
                                                        if($logo) $logo = Utility::image_link_determiner($logo,$module_url.$m_name.DS);

                                                    ?>

                                                    <div class="verticaltabstitle">
                                                        <h2><?php echo $name; ?>
                                                            <?php if($logo): ?>
                                                                <img style="float:right" src="<?php echo $logo; ?>" height="35"/>
                                                            <?php endif; ?>
                                                        </h2>
                                                    </div>
                                                    <div class="module-page-content">
                                                        <?php echo $m_content; ?>
                                                    </div>

                                                    <div class="clear"></div>

                                                    <a class="module-btn-close lbtn" href="<?php echo $links["controller"]; ?>?mainTab=all"> <i class="fa fa-angle-double-left"></i> <?php echo __("admin/tools/button-turn-back"); ?></a>

                                                    <div class="clear"></div>
                                                </div>
                                                <?php
                                            }
                                        ?>

                                        <div id="moduleList-wrap" style="<?php echo $module ? 'display:none;' : ''; ?>">

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
                                                        <li><a href="<?php echo $links["controller"]; ?>?mainTab=all&module=<?php echo $key; ?>" class="tablinks" data-tab="<?php echo $key; ?>"><span><?php echo $name; ?></span></a></li>
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
                    <?php
                }
                else
                {
                    ?>
                    <div class="noapimodule">
                        <div class="padding20">
                            <i class="fa fa-info-circle"></i>
                            <p><?php echo __("admin/products/no-product-modules"); ?></p>
                        </div>
                    </div>
                    <?php
                }
            ?>



        </div>
    </div>


</div>

<?php include __DIR__.DS."inc".DS."footer.php"; ?>

</body>
</html>