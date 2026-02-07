<?php defined('CORE_FOLDER') OR exit('You can not get in here!');
    if(!isset($hoptions)) $hoptions = [];

    if(isset($hoptions["page"]) && $hoptions["page"] != "index" && isset($meta["title"]))
    {
        $suffix         = __("website/index/meta/title-suffix");
        $home_title     = __("website/index/meta/title");
        if(strlen($suffix) > 1)
            $meta["title"] = str_replace(['{home_title}','{page_title}'],[$home_title,$meta["title"]],$suffix);
    }
?>
<!-- Meta Tags -->
<title><?php echo isset($meta["title"]) ? $meta["title"] : NULL; ?></title>
<meta name="keywords" content="<?php echo isset($meta["keywords"]) ? $meta["keywords"] : NULL;?>" />
<meta name="description" content="<?php echo isset($meta["description"]) ? $meta["description"] : NULL;?>" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<meta name="robots" content="<?php echo isset($meta["robots"]) ? $meta["robots"] : NULL; ?>" />
<?php View::main_meta(); ?>
<link rel="canonical" href="<?php echo $canonical_link;?>" />
<link rel="icon" type="image/x-icon" href="<?php echo $favicon_link;?>" />
<meta name="theme-color" content="<?php echo $meta_color; ?>">
<?php if(isset($page) && isset($page["mockup"]) && $page["mockup"] != ''): ?>
    <meta property="og:image" content="<?php echo $page["mockup"]; ?>">
<?php endif; ?>

<?php
    if(isset($lang_list) && $lang_list){
        foreach($lang_list AS $l_row){
            $l_link = $l_row["link"];
            $l_link = str_replace(["?chl=true","&chl=true"],"",$l_link);
            ?><link rel="alternate" hreflang="<?php echo $l_row["key"]; ?>" href="<?php echo $l_link; ?>" /><?php
            echo EOL;
        }
    }
?>

<!-- Meta Tags -->

<!-- Css -->
<?php
    View::main_style();
?>

<link rel="stylesheet" href="<?php echo $_theme->get_css_url(); ?>"/>
<!-- Icon Fonts -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/fontawesome.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/solid.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/brands.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/v4-shims.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/regular.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?php echo $tadress;?>css/ionicons.min.css"/>
<link rel="stylesheet" href="<?php echo $tadress;?>css/animate.css" media="none" onload="if(media!='all')media='all'">
<link rel="stylesheet" href="<?php echo $tadress;?>css/aos.css" />
<?php if(isset($hoptions) && in_array("aos",$hoptions)): ?><?php endif; ?>
<?php if(in_array("highlightjs",$hoptions)): ?><link rel="stylesheet" href="<?php echo $sadress; ?>assets/plugins/highlightjs/styles/zenburn.css">
<?php endif; ?>
<?php if(in_array("jquery-ui",$hoptions)): ?><link rel="stylesheet" href="<?php echo $sadress; ?>assets/plugins/css/jquery-ui.css">
<?php endif; ?>
<?php if(in_array("intlTelInput",$hoptions)): ?><link rel="stylesheet" href="<?php echo $sadress; ?>assets/plugins/phone-cc/css/intlTelInput.css">
<?php endif; ?>
<?php if(in_array("dataTables",$hoptions)): ?><link rel="stylesheet" href="<?php echo $sadress; ?>assets/plugins/dataTables/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="<?php echo $sadress; ?>assets/plugins/dataTables/css/dataTables.responsive.min.css">
<?php endif; ?>
<?php if(in_array("select2",$hoptions)): ?><link rel="stylesheet" href="<?php echo $sadress; ?>assets/plugins/select2/css/select2.min.css">
<?php endif; ?>
<?php if(in_array("jQtags",$hoptions)): ?><link rel="stylesheet" href="<?php echo $sadress; ?>assets/plugins/tags/jquery.tagsinput.min.css">
<?php endif; ?>
<?php if(in_array("ion.rangeSlider",$hoptions)): ?><link rel="stylesheet" href="<?php echo $sadress; ?>assets/plugins/ion.rangeSlider/css/ion.rangeSlider.min.css">
<?php endif; ?>
<?php if(___("package/rtl")): ?><link rel="stylesheet" href="<?php echo $sadress."assets/style/theme-rtl.css?v=".License::get_version();?>&lang=<?php echo Bootstrap::$lang->clang; ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?php echo $sadress; ?>assets/style/theme-default.css?v=<?php echo License::get_version(); ?>"  type="text/css">
<!-- Css -->

<!-- Js -->

<script>
    var template_address = "<?php echo $tadress;?>";
</script>
<script src="<?php echo $tadress;?>js/jquery-2.2.4.min.js"></script>

<?php if(isset($hoptions["page"]) && $hoptions["page"] == "index"): ?>

    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {setTimeout('document.getElementById("wisecp").style.display="none"', 100);});
    </script>
    <script>(function(){"use strict";var c=[],f={},a,e,d,b;if(!window.jQuery){a=function(g){c.push(g)};f.ready=function(g){a(g)};e=window.jQuery=window.$=function(g){if(typeof g=="function"){a(g)}return f};window.checkJQ=function(){if(!d()){b=setTimeout(checkJQ,100)}};b=setTimeout(checkJQ,100);d=function(){if(window.jQuery!==e){clearTimeout(b);var g=c.shift();while(g){jQuery(g);g=c.shift()}b=f=a=e=d=window.checkJQ=null;return true}return false}}})();</script>
    <style type="text/css">div.hbcne{position:fixed;z-index:4000;}div.hgchd{top:0px;left:0px;} div.hbcne{_position:absolute;}div.hgchd{_bottom:auto;_top:expression(ie6=(document.documentElement.scrollTop+document.documentElement.clientHeight – 52)+"px") );}</style>


    <script src="<?php echo $tadress; ?>js/jquery.carouFredSel-6.2.1-packed.js"></script>

    <script src="<?php echo $tadress; ?>js/index-setting.js"></script>

<?php endif; ?>

<?php if(isset($hoptions) && in_array("counterup",$hoptions)): ?>
    <script src="<?php echo $tadress;?>js/jquery.counterup.min.js"></script>
    <script src="<?php echo $tadress;?>js/waypoints.min.js"></script>
<?php endif; ?>
<?php if(in_array("highlightjs",$hoptions)): ?>
    <script src="<?php echo $sadress; ?>assets/plugins/highlightjs/highlight.pack.js"></script>
    <script type="text/javascript">
        $(document).ready(function() {
            $('pre code').each(function(i, block) {
                hljs.highlightBlock(block);
            });
        });
    </script>
<?php endif; ?>
<?php if(in_array("jquery-ui",$hoptions)): ?>
    <script src="<?php echo $sadress; ?>assets/plugins/js/jquery-ui.js"></script>
    <script type="text/javascript" src="<?php echo $sadress;?>assets/plugins/js/i18n/datepicker-<?php echo ___("package/code"); ?>.js"></script>
<?php endif; ?>
<?php if(in_array("intlTelInput",$hoptions)): ?>
    <script src="<?php echo $sadress; ?>assets/plugins/phone-cc/js/intlTelInput.js"></script>
<?php endif; ?>
<?php if(in_array("dataTables",$hoptions)): ?>
    <script src="<?php echo $sadress; ?>assets/plugins/dataTables/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo $sadress; ?>assets/plugins/dataTables/js/dataTables.responsive.min.js"></script>
<?php endif; ?>
<?php if(in_array("select2",$hoptions)): ?>
    <script src="<?php echo $sadress; ?>assets/plugins/select2/js/select2.min.js"></script>
<?php endif; ?>
<?php if(in_array("isotope",$hoptions)): ?>
    <script src="<?php echo $sadress; ?>assets/plugins/js/isotope.pkgd.min.js"></script>
<?php endif; ?>
<?php if(in_array("jQtags",$hoptions)): ?>
    <script src="<?php echo $sadress; ?>assets/plugins/tags/jquery.tagsinput.min.js"></script>
<?php endif; ?>
<?php if(in_array("voucher_codes",$hoptions)): ?>
    <script src="<?php echo $sadress; ?>assets/plugins/js/voucher_codes.js"></script>
<?php endif; ?>
<?php if(in_array("Sortable",$hoptions)): ?>
    <script src="<?php echo $sadress; ?>assets/plugins/js/Sortable.min.js"></script>
<?php endif; ?>
<?php if(in_array("jquery-nestable",$hoptions)): ?>
    <script src="<?php echo $sadress; ?>assets/plugins/js/jquery.nestable.js"></script>
<?php endif; ?>
<?php if(in_array("jquery.countdown",$hoptions)): ?>
    <script src="<?php echo $sadress; ?>assets/plugins/js/jquery.countdown.min.js"></script>
<?php endif; ?>
<?php if(in_array("ion.rangeSlider",$hoptions)): ?>
    <script src="<?php echo $sadress; ?>assets/plugins/ion.rangeSlider/js/ion.rangeSlider.min.js"></script>
<?php endif; ?>

<?php View::main_script(); ?>

<!-- Js -->