<!DOCTYPE html>
<html>
<head>
    <?php
        Utility::sksort($lang_list,"local");
        $local_l    = Config::get("general/local");

        $privOperation  = Admin::isPrivilege("PRODUCTS_OPERATION");
        $plugins        = [
            'jquery-ui',
            'dataTables',
            'drag-sort',
            'tinymce-1'
        ];
        include __DIR__.DS."inc".DS."head.php";
    ?>

    <style type="text/css">
        .option-highlight {
            background: #FFF;
            height:50px;
        }
    </style>
    <script type="text/javascript">
        var before_open_accordion = false;
        var doc_last_id = <?php echo $doc_last_id ?? "-1"; ?>;
        var section,fn_el,otr_el,opts_el;

        $(document).ready(function() {

            $("#docs_wrapper").sortable({
                placeholder: "option-highlight",
                handle:".doc-bearer",
            }).disableSelection();


            $(".change-lang-buttons a").click(function(){
                var _wrap   = $(this).parent();
                var _type   = $(_wrap).data("type");
                var k       = $(this).data("key");

                if($(this).attr("id") === "lang-active") return false;
                window[_type+"_selected_lang"] = k;

                $("."+_type+"-values").css("display","none");
                $("."+_type+"-value-"+k).css("display","inline-block");

                $("a",_wrap).removeAttr("id");
                $(this).attr("id","lang-active");
            });

            $("#addNewForm_submit").on("click",function(){
                MioAjaxElement($(this),{
                    waiting_text: '<?php echo ___("needs/button-waiting"); ?>',
                    progress_text: '<?php echo ___("needs/button-uploading"); ?>',
                    result:"addNewForm_handler",
                });
            });

            add_doc();

        });

        function addNewForm_handler(result){
            if(result != ''){
                var solve = getJson(result);
                if(solve !== false){
                    if(solve.status == "error"){
                        if(solve.for != undefined && solve.for != ''){
                            $("#addNewForm "+solve.for).focus();
                            $("#addNewForm "+solve.for).attr("style","border-bottom:2px solid red; color:red;");
                            $("#addNewForm "+solve.for).change(function(){
                                $(this).removeAttr("style");
                            });
                        }
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


        function changeType(el)
        {
            el              = $(el);
            section         = el.val();
            fn_el           = $(el).parent().parent().parent();
            otr_el          = $("."+section+"-wrap",fn_el);
            opts_el         = $(".options-wrap",fn_el);

            $(".other-wrappers",fn_el).css("display","none");

            if(in_array(section,['select'])){
                opts_el.css("display","block");

                if($(".field-options-wrap .field-option-item",fn_el).length < 1)
                    add_option_in_doc($('.add-option-in-doc',opts_el));
                else
                    console.log($(".field-options-wrap .field-option-item",fn_el));
            }else{
                opts_el.css("display","none");
            }

            if(otr_el !== undefined){
                otr_el.css("display","block");
                $("select,input",otr_el).attr("disabled",false);
            }
        }

        function add_doc(){
            var template = $("#template-doc-item").html();

            doc_last_id++;

            template = template.replace(/docs\[x\]/g,"docs["+doc_last_id+"]");

            $("#docs_wrapper").append(template);
        }

        function add_option_in_doc(el)
        {
            var m_el            = $(el).parent().parent().parent().parent();
            var template        = $(".template-field-option",m_el).html();

            $(".field-options-wrap",m_el).append(template);
        }

    </script>
</head>
<body>


<div id="template-doc-item" style="display: none;">
    <div class="field-item">
        <input type="hidden" name="docs[x][id][]" value="true">
        <div class="template-field-option" style="display: none;">
            <div class="field-option-item">
                <div class="doc-option-child-item">
                    <?php
                        foreach($lang_list AS $row){
                            $l_k = $row["key"];
                            ?>
                            <input type="text" style="<?php echo $l_k == $local_l ? '' : 'display:none;';?>" name="docs[x][options][name][<?php echo $l_k; ?>][]" class="yuzde90 doc-values doc-value-<?php echo $l_k; ?>" placeholder="<?php echo __("admin/users/document-filter-f-option-name"); ?>">
                            <?php
                        }
                    ?>
                    <a class="sbtn red" href="javascript:void 0;" onclick="$(this).parent().parent().remove();"><i class="fa fa-remove"></i></a>
                </div>
            </div>
        </div>

        <div class="fieldcon">
            <div class="fieldcon2">
                <?php
                    foreach($lang_list AS $row){
                        $l_k = $row["key"];
                        ?>
                        <input type="text" style="<?php echo $l_k == $local_l ? '' : 'display:none;';?>" name="docs[x][name][<?php echo $l_k; ?>]" class="doc-values doc-value-<?php echo $l_k; ?>" placeholder="<?php echo __("admin/products/domain-docs-tx2"); ?>">
                        <?php
                    }
                ?>
            </div>
            <div class="fieldcon3">
                <select name="docs[x][type]" onchange="changeType(this);" style="">
                    <option value="text"><?php echo __("admin/products/domain-docs-tx5"); ?></option>
                    <option value="file"><?php echo __("admin/products/domain-docs-tx6"); ?></option>
                    <option value="select"><?php echo __("admin/products/domain-docs-tx9"); ?></option>
                </select>

                <div class="options-wrap other-wrappers formcon" style="display: none;">
                    <div class="field-options-wrap">

                    </div>
                    <div class="clear"></div>
                    <br>
                    <a class="lbtn blue add-option-in-doc" href="javascript:void 0;" onclick="add_option_in_doc(this);"><i class="fa fa-plus"></i> <?php echo __("admin/products/add-requirement-add-option"); ?></a>

                </div>
                <div class="file-wrap other-wrappers formcon" style="display: none;">

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/users/document-filter-f-allowed-ext"); ?></div>
                        <div class="yuzde70">
                            <input type="text" name="docs[x][allowed_ext]" placeholder="<?php echo __("admin/users/document-filter-f-allowed-ext-info"); ?>">
                        </div>
                    </div>
                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/users/document-filter-f-max-upload-fsz"); ?></div>
                        <div class="yuzde70">
                            <input type="text" name="docs[x][max_file_size]" value="3">
                        </div>
                    </div>

                </div>

            </div>
            <div class="fieldcon1">
                <a href="javascript:void(0);" class="sbtn doc-bearer"><i class="fa fa-arrows-alt"></i></a>

                <a href="javascript:void 0;" onclick="$(this).parent().parent().parent().remove(); $('#docs_wrapper').sortable('refresh');" class="sbtn red"><i class="fa fa-trash"></i></a>
            </div>
        </div>


        <div class="clear"></div>
    </div>
</div>


<?php include __DIR__.DS."inc/header.php"; ?>

<div id="wrapper">

    <div class="icerik-container">
        <div class="icerik">

            <div class="icerikbaslik">
                <h1>
                    <strong><?php echo __("admin/products/page-domain-add-doc"); ?></strong>
                </h1>
                <?php
                    $ui_help_link = 'https://docs.wisecp.com/en/kb/domain-name-service-management';
                    if($ui_lang == "tr") $ui_help_link = 'https://docs.wisecp.com/tr/kb/alan-adi-hizmet-yonetimi';
                ?>
                <a title="<?php echo __("admin/help/usage-guide"); ?>" target="_blank" class="pagedocslink" href="<?php echo $ui_help_link; ?>"><i class="fa fa-life-ring" aria-hidden="true"></i></a>
                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>


            <div class="clear"></div>

            <div class="adminpagecon domain-extension-doc">


                <div class="change-lang-buttons" data-type="doc">
                    <?php
                        foreach($lang_list AS $row){
                            ?>
                            <a class="lbtn"<?php echo $local_l == $row["key"] ? ' id="lang-active"' : ''; ?> href="javascript:void 0;" data-key="<?php echo $row["key"]; ?>"><?php echo strtoupper($row["key"]); ?></a>
                            <?php
                        }
                    ?>
                </div>


                <form action="<?php echo $links["controller"]; ?>" method="post" id="addNewForm">
                    <input type="hidden" name="operation" value="add_domain_doc">


                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/products/domain-docs-tx1"); ?></div>
                        <div class="yuzde70">

                            <input type="text" name="tld" value="" placeholder="<?php echo __("admin/products/domain-docs-tx4"); ?>">
                        </div>
                    </div>


                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/products/domain-docs-tx14"); ?></div>
                        <div class="yuzde70">
                            <div class="clear"></div>
                            <br>

                            <div class="fieldcon fieldconhead">
                                <div class="fieldcon2"><strong><?php echo __("admin/products/domain-docs-tx2"); ?></strong></div>
                                <div class="fieldcon3"><strong><?php echo __("admin/products/domain-docs-tx3"); ?></strong></div>
                            </div>


                            <div id="docs_wrapper"></div>

                            <div class="clear"></div>
                            <br>

                            <a href="javascript:void 0;" onclick="add_doc();" class="sbtn green"><i class="fa fa-plus"></i></a>
                        </div>
                    </div>


                    <div class="formcon">
                        <div class="yuzde30">
                            <?php echo __("admin/products/domain-docs-tx7"); ?>
                            <div class="clear"></div>
                            <span class="kinfo"><?php echo __("admin/products/domain-docs-tx15"); ?></span>
                        </div>
                        <div class="yuzde70">
                            <?php
                                foreach($lang_list AS $row){
                                    $l_k = $row["key"];
                                    ?>
                                    <div style="<?php echo $l_k == $local_l ? '' : 'display:none;';?>" class="doc-values doc-value-<?php echo $l_k; ?>">
                                        <textarea rows="5" name="description[<?php echo $l_k; ?>]" class="tinymce-1"></textarea>
                                    </div>
                                    <?php
                                }
                            ?>
                        </div>
                    </div>



                    <div style="float:right;margin-top:10px;" class="guncellebtn yuzde30">
                        <a class="yesilbtn gonderbtn" id="addNewForm_submit" href="javascript:void(0);"><?php echo ___("needs/button-create"); ?></a>
                    </div>
                    <div class="clear"></div>

                </form>


            </div>



            <div class="clear"></div>
        </div>


    </div>


</div>

<?php include __DIR__.DS."inc".DS."footer.php"; ?>

</body>
</html>