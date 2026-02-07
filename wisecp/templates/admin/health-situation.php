<!DOCTYPE html>
<html>
<head>
    <?php
        $plugins        = [];
        include __DIR__.DS."inc".DS."head.php";

        if($rate <= 33) $perc_id = '1to33';
        elseif($rate <= 66) $perc_id = '33to66';
        else $perc_id = '66to100';

        function show_item_list($type='',$items=[],$vars=[])
        {
            extract($vars);
            if($items)
            {
                foreach($items AS $key => $value)
                {
                    $replaces   = [];
                    if($key == 'session') $replaces['{directory}'] = $value;
                    elseif($key == 'db-version' && $value)
                        $replaces = [
                            '{version}'     => $value["type"].' '.$value["version"],
                            '{type}'        => $value["type"],
                            '{suggested1}'  => $value["suggested1"],
                            '{suggested2}'  => $value["suggested2"],
                        ];
                    elseif($key == 'sql-mode')
                        $replaces = [
                            '{modes}'   => $value["modes"],
                        ];
                    $title      = __("admin/help/health-".$key);
                    $info       = __("admin/help/health-".$type."-".$key,$replaces);
                    if(!$title) $title = $key;
                    if(!$info) $info = 'No information';
                    $ul_items   = [];
                    if($key == 'extensions' || $key == 'file-permission')
                        $ul_items = array_values($value);

                    ?>
                    <div class="systemhealth-item">
                        <h4><?php echo $title; ?></h4>
                        <p>
                            <?php echo $info; ?>
                            <?php
                                if($key == 'limits' && $type == 'errors')
                                {
                            ?>
                            <br><br>
                            <?php echo __("admin/help/health-must-be"); ?>
                        <ul>
                            <?php
                                if(isset($value["memory_limit"]))
                                {
                                    ?>
                                    <li><strong>memory_limit = <?php echo $suggested_memory_limit ?? '0'; ?>M</strong></li>
                                    <?php
                                }

                                if(isset($value["max_execution_time"]))
                                {
                                    ?>
                                    <li><strong>max_execution_time = <?php echo $suggested_execution_time ?? 0; ?></strong></li>
                                    <?php
                                }
                            ?>
                        </ul>

                        <br>
                        <?php echo __("admin/help/health-limits-server-values"); ?>
                        <ul>
                            <?php
                                if(isset($value["memory_limit"]))
                                {
                                    ?>
                                    <li><strong>memory_limit = <?php echo $value["memory_limit"]; ?></strong></li>
                                    <?php
                                }
                                if(isset($value["max_execution_time"]))
                                {
                                    ?>
                                    <li><strong>max_execution_time = <?php echo $value["max_execution_time"]; ?></strong></li>
                                    <?php
                                }
                            ?>
                        </ul>
                        <?php
                            }

                            if($ul_items)
                            {
                                ?>
                                <ul>
                                    <?php
                                        foreach($ul_items AS $ul_item)
                                        {
                                            ?>
                                            <li><strong><?php echo $ul_item; ?></strong></li>
                                            <?php
                                        }
                                    ?>
                                </ul>
                                <?php
                            }
                            ?>
                        </p>
                    </div>
                    <?php
                }
            }
            else
            {
                ?>
                <div class="systemhealth-no-item">
                    <p><?php echo __("admin/help/health-situation-no-".$type); ?></p>
                </div>
                <?php
            }
        }
        $vars = [
            'suggested_memory_limit'    => $suggested_memory_limit ?? 0,
            'suggested_execution_time'  => $suggested_execution_time ?? 0,
        ];



    ?>
</head>
<body>

<?php include __DIR__.DS."inc/header.php"; ?>

<div id="wrapper">

    <div class="icerik-container">
        <div class="icerik">

            <div class="icerikbaslik">
                <h1>
                    <strong><?php echo __("admin/help/page-health"); ?></strong>
                </h1>
                <?php include __DIR__.DS."inc".DS."breadcrumb.php"; ?>
            </div>

            <div class="systemhealth">

                <div class="systemhealth-con">

                    <div class="systemhealth-status">

                        <?php if($rate == 100): ?>
                            <div class="systemhealth-all-ok-line"></div>
                            <div class="systemhealth-all-ok">
                                <div class="systemhealth-all-ok-icon"><i class="fas fa-thumbs-up"></i></div>
                                <div class="systemhealth-all-ok-text"><strong><?php echo __("admin/help/health-text5"); ?></strong><br><?php echo __("admin/help/health-text6"); ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="clear"></div>

                        <?php

                        ?>

                        <div class="systemhealth-vertical-block" id="systemhealth-error">

                            <div class="systemhealth-vertical-block-title">
                                <div class="systemhealth-vertical-block-title-icon"><i class="fas fa-times"></i></div>
                                <div class="systemhealth-vertical-block-title-bg"><i class="fas fa-times"></i></div>
                                <h3><strong><?php echo $error_count ?? 0; ?></strong> <?php echo __("admin/help/health-text2"); ?></h3>
                            </div>


                            <div class="padding20">
                                <?php
                                    show_item_list('errors',$items['errors'] ?? [],$vars);
                                ?>
                            </div>

                        </div>

                        <div class="systemhealth-vertical-block" id="systemhealth-info">

                            <div class="systemhealth-vertical-block-title">
                                <div class="systemhealth-vertical-block-title-icon"><i class="fas fa-info-circle"></i></div>
                                <div class="systemhealth-vertical-block-title-bg"><i class="fas fa-info-circle"></i></div>
                                <h3><strong><?php echo $warning_count ?? 0; ?></strong> <?php echo __("admin/help/health-text3"); ?></h3>
                            </div>

                            <div class="padding20">
                                <?php
                                    show_item_list('warnings',$items['warnings'] ?? [],$vars);
                                ?>
                            </div>

                        </div>

                        <div class="systemhealth-vertical-block" id="systemhealth-ok">

                            <div class="systemhealth-vertical-block-title">
                                <div class="systemhealth-vertical-block-title-icon"><i class="fas fa-check-circle"></i></div>
                                <div class="systemhealth-vertical-block-title-bg"><i class="fas fa-check-circle"></i></div>
                                <h3><strong><?php echo $success_count ?? 0; ?></strong> <?php echo __("admin/help/health-text4"); ?></h3>
                            </div>

                            <div class="padding20">
                                <?php
                                    show_item_list('successes',$items['successes'] ?? [],$vars);
                                ?>
                            </div>

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