<!DOCTYPE html>
<html>
<head>
    <?php
        $plugins        = [];
        include __DIR__.DS."inc".DS."head.php";
    ?>
</head>
<body>

<?php include __DIR__.DS."inc/header.php"; ?>

<div id="wrapper">

    <div class="icerik-container">
        <div class="icerik">

            <div class="icerikbaslik">
                <h1>
                    <strong><?php echo __("admin/help/page-license"); ?></strong>
                </h1>
                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>


            <div class="adminpagecon">

                <div class="licenseinformation">

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/help/license-col-version"); ?></div>
                        <div class="yuzde60">
                            <h4 style="float:left;margin-right:20px;"><STRONG><?php echo License::get_version(); ?></STRONG></h4>

                            <?php
                            if(Updates::check_new_version()){
                                ?>
                                <strong><a href="<?php echo Controllers::$init->AdminCRLink("help-1",["updates"]); ?>" class="red-info versiyoninfo"><?php echo __("admin/help/license-check-updates"); ?></a></strong>
                                <?php
                            }else{
                                ?>
                                <span class="green-info versiyoninfo"><?php echo __("admin/help/license-using-latest-version"); ?></span>
                                <?php
                            }
                            ?>
                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/help/license-col-owner"); ?></div>
                        <div class="yuzde60">
                            <?php echo $license_info["owner_name"]; ?>
                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/help/license-col-domain"); ?></div>
                        <div class="yuzde60">
                            <?php echo $license_info["product-name"] ?? 'Unknown'; ?><br>
                            <strong> <?php echo $license_info["domain"]; ?></strong>
                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/help/license-col-activation-date"); ?></div>
                        <div class="yuzde60"><?php echo DateManager::format(Config::get("options/date-format")." - H:i",$license_info["activation-date"]); ?></div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/help/license-col-due-date"); ?></div>
                        <div class="yuzde60">
                            <?php
                            if(substr($license_info["due-date"],0,4) == "0000" || substr($license_info["due-date"],0,4) == "1881")
                                echo ___("needs/none");
                            else
                                echo DateManager::format(Config::get("options/date-format")." - H:i",$license_info["due-date"])
                            ?>
                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/help/license-col-delimitation"); ?></div>
                        <div class="yuzde60">
                            <?php
                            if($license_info["delimitation"])
                                echo $license_info["branded_account_limit"];
                            else
                                echo ___("needs/none");
                            ?>
                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/help/license-col-copyright-obligation"); ?></div>
                        <div class="yuzde60"><?php echo $license_info["copyright"] ? ___("needs/yes") : ___("needs/none"); ?>
                        </div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30">Server IP</div>
                        <div class="yuzde60"><?php echo $license_info["ip"]; ?></div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("system/install/stage1-text4"); ?></div>
                        <div class="yuzde60"><?php echo PHP_VERSION; ?></div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("admin/help/license-col-installed-directory"); ?></div>
                        <div class="yuzde60"><?php echo ROOT_DIR; ?></div>
                    </div>

                    <div class="formcon">
                        <div class="yuzde30"><?php echo __("website/account_products/purchased-services"); ?></div>
                        <div class="yuzde60">
                            <?php
                                if(isset($license_info["additional"]) && $license_info["additional"])
                                {
                                    ?>
                                    <table style="width: 100%;">
                                        <thead>
                                        <tr>
                                            <th style="text-align: left;"><?php echo __("website/account_products/service-name"); ?></th>
                                            <th style="text-align: left;"><?php echo __("website/account_products/service-status"); ?></th>
                                            <th style="text-align: left;"><?php echo __("admin/financial/coupons-list-end-date"); ?></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                            foreach($license_info["additional"] as $k => $service)
                                            {
                                                ?>
                                                <tr>
                                                    <td><?php echo $service["name"] ?? $k; ?></td>
                                                    <td><?php echo __("admin/orders/status-".$service['status']); ?></td>
                                                    <td>
                                                        <?php
                                                            $duedate = $service["duedate"] ?? DateManager::ata();
                                                            if(substr($duedate,0,4) < 2023)
                                                                echo ___("needs/none");
                                                            else
                                                                echo DateManager::format($duedate,Config::get("options/date-format"));
                                                        ?>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                        ?>
                                        </tbody>
                                    </table>
                                    <?php
                                }
                                else
                                    echo __("admin//");
                            ?>

                        </div>
                    </div>



                </div>

            </div>


            <div class="clear"></div>

        </div>
    </div>


</div>

<?php include __DIR__.DS."inc".DS."footer.php"; ?>

</body>
</html>