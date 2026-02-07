<!DOCTYPE html>
<html>
<head>
    <?php
        $plugins    = [];
        include __DIR__.DS."inc".DS."head.php";
    ?>
    <script type="text/javascript">

    </script>
</head>
<body>

<?php include __DIR__.DS."inc/header.php"; ?>

<div id="wrapper">

    <div class="icerik-container">
        <div class="icerik">

            <div class="icerikbaslik">
                <h1><strong><?php echo $module_data["lang"]["page-title"]; ?></strong></h1>
                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>

            <div class="clear"></div>
            <?php
                if(method_exists($module,'area'))
                    $module->area();
                else
                {
                    ?>
                    <div class="red-info">
                        <div class="padding20">
                            <i class="fa fa-exclamation-triangle"></i>
                            <h2>Error</h2>
                            <p>The ‘area’ method is missing in the module class file!</p>
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