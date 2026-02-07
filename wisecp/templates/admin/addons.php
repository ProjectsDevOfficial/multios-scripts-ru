<!DOCTYPE html>
<html>
<head>
    <?php
        $enabled_list   = [];
        $disabled_list  = [];

        if(isset($modules) && $modules)
        {
            foreach($modules AS $k => $m)
            {
                if($m["config"]["status"])
                    $enabled_list[$k] = $m;
                else
                    $disabled_list[$k] = $m;
            }
        }

        $plugins    = [
            'select2',
            'jquery-ui',
            'dataTables',
            'tinymce-1',
        ];
        include __DIR__.DS."inc".DS."head.php";
    ?>

    <script type="text/javascript">
        var result,get_open,get_open_type;
        var addons_indexes = {};
        var waiting_text =  '<?php echo htmlentities(strip_tags(___("needs/button-waiting")),ENT_QUOTES); ?>';
        var progress_text = '<?php echo htmlentities(___("needs/button-uploading"),ENT_QUOTES); ?>';

        $(document).ready(function(){
            get_open        = _GET("open");
            get_open_type   = _GET("type");

            if(get_open !== null)
            {
                if(get_open_type === null)  get_open_type = "normal";
                open_addon(get_open,get_open_type,true);
            }

            $(".addonlist").each(function(){
                let key = $(this).data('key');
                addons_indexes[key] = $(this).index();
            });
        });

        function open_addon(key,type,direct_open){
            if(type === "modal") type = "normal";
            if(direct_open === undefined)
            {
                if(type !== "modal")
                {
                    let link = set_GET("open",key);
                    window.history.pushState({path:link},'',link);
                }

            }

            if(type === "modal") type = "normal";
            if(type === "modal")
            {
                open_modal("open_addon_modal",{
                    title:$("#addon_"+key+" .addon-name").html(),
                    width:'700px',
                });
                $("#open_addon_modal_loader").css("display","block");
                $("#open_addon_modal_content").css("display","none").html('');

                var request = MioAjax({
                    action:"<?php echo Controllers::$init->ControllerURI(); ?>",
                    method:"GET",
                    data:{
                        operation:"get_addon_content",
                        module:key,
                    },
                },true,true);
                request.done(function(result){
                    $("#open_addon_modal_loader").css("display","none");
                    $("#open_addon_modal_content").html(result).fadeIn(1);
                });
            }
            else if(type === "normal")
            {
                $(".close-addon-btn").css("display","none");
                $(".addon-settings-inline-loader").css("display","block");
                $(".addon-settings-inline-content").css("display","none").html('');

                $("#addons_list").css("display","none");

                $("#addon_"+key+" .open-addon-btn").css("display","none");
                $("#addon_"+key+" .short-desc").css("display","none");
                $("#addon_"+key+" .long-desc").css("display","block");

                $(".addon-settings-inline").css("display","block");
                setTimeout(function(){
                    var request = MioAjax({
                        action:"<?php echo Controllers::$init->ControllerURI(); ?>",
                        method:"GET",
                        data:{
                            operation:"get_addon_content",
                            module:key,
                        },
                    },true,true);
                    request.done(function(res){
                        result = res;
                        $(".addon-settings-inline-loader").fadeOut(100,function(){
                            $("#addon_"+key).appendTo("#settings_addon_detail");
                            $(".close-addon-btn").css("display","inline-block");
                            $(".addon-settings-inline-content").html(result).css("display","block");
                        });
                    });
                },250);

            }
        }

        function close_addon(key, type){
            if(type === undefined) type = "normal";
            if(type === "modal") type = "normal";

            if(_GET("open") !== null){
                if(key === undefined) key = _GET("open");

                let link = unset_GET("open");
                window.history.pushState({path:link},'',link);
            }
            if(_GET("type") !== null)
            {
                let link = unset_GET("type");
                window.history.pushState({path:link},'',link);
            }

            if($(".addon-settings-inline").css("display") === "block" && type === "modal") type = "normal";

            if(type === "modal") close_modal("open_addon_modal");
            else if(type === "normal")
            {
                let current_status = "enabled";

                if($("#addon_"+key+" .enable-addon-btn").css("display") === "none")
                    current_status = "enabled";
                else
                    current_status = "disabled";

                $("#addon_"+key).appendTo("#addons_"+current_status+"_list");

                let originalIndex = addons_indexes[key];
                $('#addon_'+key).parent().children().eq(originalIndex).before($('#addon_'+key));

                $("#addon_"+key+" .open-addon-btn").css("display","inline-block");
                $("#addon_"+key+" .short-desc").css("display","block");
                $("#addon_"+key+" .long-desc").css("display","none");

                $(".addon-settings-inline").css("display","none");
                $("#addons_list").css("display","block");
            }

        }

        function change_addon_status(btn,key,status)
        {
            var request = MioAjax({
                button_element:btn,
                waiting_text:'<i class="fas fa-spinner" style="-webkit-animation:fa-spin 2s infinite linear;animation:fa-spin 2s infinite linear;padding:0;border:none;margin-right:7px;"></i>' + waiting_text,
                action:"<?php echo Controllers::$init->ControllerURI(); ?>",
                method:"POST",
                data:{
                    operation:"set_addon_status",
                    module:key,
                    status:status === "enable" ? 1 : 0,
                },
            },true,true);
            request.done(function(result){
                if(result != ''){
                    var solve = getJson(result);
                    if(solve !== false){
                        if(solve.status == "error"){
                            if(solve.message != undefined && solve.message != '')
                                alert_error(solve.message,{timer:5000});
                        }else if(solve.status === "successful"){
                            if(status === "enable")
                            {
                                if(_GET("open") === null)
                                {
                                    $('#addon_'+key).appendTo('#addons_enabled_list');
                                    let originalIndex = addons_indexes[key];
                                    $('#addon_'+key).parent().children().eq(originalIndex).before($('#addon_'+key));
                                }

                                $('#addon_'+key+" .enable-addon-btn").css("display","none");
                                $('#addon_'+key+" .disable-addon-btn").css("display","inline-block");
                                $("#addon_"+key).addClass("addonlist-active");
                                $("#addons_enabled_list .there_no_message").css("display","none");
                                if($("#addons_disabled_list .addonlist").length === 0)
                                    $("#addons_disabled_list .there_no_message").css("display","block");
                            }
                            else if(status === "disable")
                            {
                                if(_GET("open") === null)
                                {
                                    $('#addon_'+key).appendTo('#addons_disabled_list');
                                    let originalIndex = addons_indexes[key];
                                    $('#addon_'+key).parent().children().eq(originalIndex).before($('#addon_'+key));
                                }
                                $('#addon_'+key+" .enable-addon-btn").css("display","inline-block");
                                $('#addon_'+key+" .disable-addon-btn").css("display","none");
                                $("#addon_"+key).removeClass("addonlist-active");
                                $("#addons_disabled_list .there_no_message").css("display","none");
                                if($("#addons_enabled_list .addonlist").length === 0)
                                    $("#addons_enabled_list .there_no_message").css("display","block");
                            }

                        }
                    }else
                        console.log(result);
                }
            });
        }
        function delete_addon(btn,key)
        {
            if(_GET("open") !== null) close_addon();
            if(!confirm("<?php echo ___("needs/delete-are-you-sure"); ?>")) return false;
            var request = MioAjax({
                button_element:btn,
                waiting_text:'<i class="fas fa-spinner" style="-webkit-animation:fa-spin 2s infinite linear;animation:fa-spin 2s infinite linear;padding:0;border:none;margin-right:7px;"></i> '+waiting_text,
                action:"<?php echo Controllers::$init->ControllerURI(); ?>",
                method:"POST",
                data:{
                    operation:"delete_addon",
                    module:key
                },
            },true,true);
            request.done(function(result){
                if(result != ''){
                    var solve = getJson(result);
                    if(solve !== false){
                        if(solve.status == "error"){
                            if(solve.message != undefined && solve.message != '')
                                alert_error(solve.message,{timer:5000});
                        }
                        else if(solve.status === "successful")
                        {
                            $("#addon_"+key).remove();
                            if($("#addons_disabled_list .addonlist").length === 0)
                                $("#addons_disabled_list .there_no_message").css("display","block");
                            if($("#addons_enabled_list .addonlist").length === 0)
                                $("#addons_enabled_list .there_no_message").css("display","block");
                            alert_success(solve.message,{timer:3000});

                        }
                    }else
                        console.log(result);
                }
            });
        }
    </script>
</head>
<body>

<div id="open_addon_modal" style="display: none;">
    <div id="open_addon_modal_loader" align="center">
        <img src="<?php echo $sadress; ?>assets/images/loading.gif">
    </div>
    <div id="open_addon_modal_content" style="display: none;"></div>
</div>

<?php include __DIR__.DS."inc/header.php"; ?>

<div id="wrapper">

    <div class="icerik-container">
        <div class="icerik addons-con">

            <div class="icerikbaslik">
                <h1><strong><?php echo $module ? $page_title : __("admin/tools/page-addons"); ?></strong></h1>
                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>

            <div class="clear"></div>


            <?php
                if(isset($module_content) && strlen($module_content) > 0)
                {
                    echo $module_content;
                }
                else
                {
                    ?>
                    <div class="addon-settings-inline adminpagecon" style="display: none;">

                        <div class="addon-settings-inline-loader" style="text-align:center;margin-top:140px;display:none;width: 100%;">
                            <div class="lds-ring"><div></div><div></div><div></div><div></div></div>
                        </div>
                        <div id="settings_addon_detail"></div>
                        <div class="addon-settings-inline-content" style="display: none;width: 100%;"></div>
                        <a style="display: none;" href="javascript:void 0;" onclick="close_addon();" class="lbtn red close-addon-btn"><i class="fa fa-angle-left"></i> <?php echo __("admin/tools/button-turn-back"); ?></a>

                    </div>

                    <div id="addons_list">

                        <div class="verticaltabstitle">
                            <h2><?php echo __("admin/tools/enabled-addons"); ?></h2>
                        </div>

                        <div id="addons_enabled_list">
                            <div class="there_no_message" style="<?php echo $enabled_list ? 'display:none;' : ''; ?>">
                                <?php echo __("admin/tools/there-are-no-addon-enable"); ?>
                            </div>

                            <?php
                                if(isset($enabled_list) && $enabled_list){
                                    foreach($enabled_list AS $key=>$module){
                                        $config     = $module["config"];
                                        $lang       = $module["lang"];
                                        $ms_folder  = CORE_FOLDER.DS.MODULES_FOLDER.DS."Addons".DS;
                                        $folder     = $ms_folder.$key.DS;
                                        $logo       = isset($config["meta"]["logo"]) ? $config["meta"]["logo"] : NULL;
                                        $logo       = Utility::image_link_determiner($logo,$folder);
                                        if($logo == '') $logo   = Utility::image_link_determiner("default-logo.svg",$ms_folder);
                                        $version    = isset($config["meta"]["version"]) ? $config["meta"]["version"] : "1.0";
                                        $op_type    = isset($config["meta"]["opening-type"]) ? $config["meta"]["opening-type"] : "none";
                                        $description = isset($lang["meta"]["descriptions"]) ? $lang["meta"]["descriptions"] : '';

                                        ?>
                                        <div class="addonlist addonlist-active" id="addon_<?php echo $key; ?>" data-key="<?php echo $key; ?>">
                                            <div class="addonimage">
                                                <img height="75" src="<?php echo $logo; ?>"/>
                                                <h5>V<?php echo $version; ?></h5>
                                            </div>
                                            <div class="addoninfo">
                                                <h4 class="addon-name"><?php echo isset($lang["meta"]["name"]) ? $lang["meta"]["name"] : $config["meta"]["name"]; ?></h4>
                                                <span><?php echo __("admin/tools/author"); ?>: <?php echo $config["meta"]["author"] ?? "N/A"; ?></span>
                                                <p class="short-desc">
                                                    <?php echo Utility::short_text(Filter::html_clear($description),0,140,true); ?>
                                                </p>
                                                <p class="long-desc" style="display: none;"><?php echo $description; ?></p>

                                            </div>
                                            <div class="addoncontrol">

                                                <?php if($op_type != "none"): ?>
                                                    <a href="javascript:void 0;" onclick="change_addon_status(this,'<?php echo $key; ?>','disable');" class="lbtn blue disable-addon-btn"><i class="far fa-times-circle"></i> <?php echo __("admin/tools/addon-disable"); ?></a>

                                                    <a style="display: none;" href="javascript:void 0;" onclick="change_addon_status(this,'<?php echo $key; ?>','enable');" class="lbtn green enable-addon-btn"><i class="far fa-check-circle"></i> <?php echo __("admin/tools/addon-enable"); ?></a>
                                                    <a href="javascript:open_addon('<?php echo $key; ?>','<?php echo $op_type; ?>');void 0;" class="lbtn open-addon-btn"><i class="fa fa-cog" aria-hidden="true"></i> <?php echo __("admin/tools/addons-settings-button"); ?></a>
                                                    <a href="javascript:void 0;" onclick="delete_addon(this,'<?php echo $key; ?>');" class="lbtn red delete-addon-btn"><i class="fas fa-trash-alt"></i> <?php echo __("admin/tools/delete-addon"); ?></a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php
                                    }

                                    ?>
                                    <?php
                                }
                            ?>
                        </div>

                        <div class="clear"></div>

                        <div class="verticaltabstitle">
                            <h2><?php echo __("admin/tools/disabled-addons"); ?></h2>
                        </div>

                        <div id="addons_disabled_list">
                            <div class="there_no_message" style="<?php echo $disabled_list ? 'display:none;' : ''; ?>">
                                <?php echo __("admin/tools/there-are-no-addon-disable"); ?>
                            </div>

                            <?php
                                if(isset($disabled_list) && $disabled_list){
                                    foreach($disabled_list AS $key=>$module){
                                        $config     = $module["config"];
                                        $lang       = $module["lang"];
                                        $ms_folder  = CORE_FOLDER.DS.MODULES_FOLDER.DS."Addons".DS;
                                        $folder     = $ms_folder.$key.DS;
                                        $logo       = isset($config["meta"]["logo"]) ? $config["meta"]["logo"] : NULL;
                                        $logo       = Utility::image_link_determiner($logo,$folder);
                                        if($logo == '') $logo   = Utility::image_link_determiner("default-logo.svg",$ms_folder);
                                        $version    = isset($config["meta"]["version"]) ? $config["meta"]["version"] : "1.0";
                                        $op_type    = isset($config["meta"]["opening-type"]) ? $config["meta"]["opening-type"] : "none";
                                        $description = isset($lang["meta"]["descriptions"]) ? $lang["meta"]["descriptions"] : '';

                                        ?>

                                        <div class="addonlist" id="addon_<?php echo $key; ?>" data-key="<?php echo $key; ?>">
                                            <div class="addonimage">
                                                <img height="75" src="<?php echo $logo; ?>"/>
                                                <h5>V<?php echo $version; ?></h5>
                                            </div>
                                            <div class="addoninfo">
                                                <h4 class="addon-name"><?php echo isset($lang["meta"]["name"]) ? $lang["meta"]["name"] : $config["meta"]["name"]; ?></h4>
                                                <span><?php echo __("admin/tools/author"); ?>: <?php echo $config["meta"]["author"] ?? "N/A"; ?></span>
                                                <p class="short-desc">
                                                    <?php echo Utility::short_text(Filter::html_clear($description),0,140,true); ?>
                                                </p>
                                                <p class="long-desc" style="display: none;"><?php echo $description; ?></p>
                                            </div>
                                            <div class="addoncontrol">

                                                <?php if($op_type != "none"): ?>
                                                    <a style="display: none;" href="javascript:void 0;" onclick="change_addon_status(this,'<?php echo $key; ?>','disable');" class="lbtn blue disable-addon-btn"><i class="far fa-times-circle"></i> <?php echo __("admin/tools/addon-disable"); ?></a>

                                                    <a href="javascript:void 0;" onclick="change_addon_status(this,'<?php echo $key; ?>','enable');" class="lbtn green enable-addon-btn"><i class="far fa-check-circle"></i> <?php echo __("admin/tools/addon-enable"); ?></a>
                                                    <a href="javascript:open_addon('<?php echo $key; ?>','<?php echo $op_type; ?>');void 0;" class="lbtn open-addon-btn"><i class="fa fa-cog" aria-hidden="true"></i> <?php echo __("admin/tools/addons-settings-button"); ?></a>
                                                    <a style="" href="javascript:void 0;" onclick="delete_addon(this,'<?php echo $key; ?>');" class="lbtn red delete-addon-btn"><i class="fas fa-trash-alt"></i> <?php echo __("admin/tools/delete-addon"); ?></a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php
                                    }

                                    ?>
                                    <?php
                                }
                            ?>
                        </div>

                    </div>
                    <?php
                }
            ?>

            <div class="clear"></div>
        </div>
    </div>


</div>

<?php include __DIR__.DS."inc".DS."footer.php"; ?>

</body>
</html>