<div class="stage2">
    <div class="logo"><img src="https://www.wisecp.com/images/logo.svg" /></div>
    <div class="title"><?php echo $lang["stage1"]; ?></div>
    <div style="padding:25px;">

        <div class="notice">
            <i class="fa fa-exclamation-circle"></i>
            <div class="noticeinfo">
                <?php echo $lang["stage1-text1"]; ?>
            </div>
        </div>

        <?php
            $good = $lang["stage1-text28"];

            $requires   = [
                [
                    'name'   =>  $lang["stage1-text4"],
                    'status' => $php,
                    'value'  => $php_v,
                    'message' => $php  ? $good : $lang["stage1-text5"],
                ],
                [
                    'name'   => 'PHP Info',
                    'status' => $phpinfo,
                    'value'  => "-",
                    'message' => $phpinfo  ? $good : $lang["stage1-text29"],
                ],
                [
                    'name'   => $lang["stage1-text30"],
                    'status' => $ioncube,
                    'value'  => $ioncube_v,
                    'message' => $ioncube  ? $good : $lang["stage1-text3"],
                ],
                [
                    'name'   => 'cURL',
                    'status' => $curl,
                    'value'  => '-',
                    'message' => $curl  ? $good : $lang["stage1-text7"],
                ],
                [
                    'name'   => 'PDO & MySQL',
                    'status' => $pdo,
                    'value'  => '-',
                    'message' => $pdo  ? $good : $lang["stage1-text9"],
                ],
                [
                    'name'   => 'MySQLi',
                    'status' => $mysqli,
                    'value'  => '-',
                    'message' => $mysqli  ? $good : $lang["stage1-text31"],
                ],
                [
                    'name'   => 'ZipArchive',
                    'status' => $zip,
                    'value'  => '-',
                    'message' => $zip  ? $good : $lang["stage1-text8"],
                ],
                [
                    'name'   => 'MultiByte String',
                    'status' => $mbstring,
                    'value'  => '-',
                    'message' => $mbstring  ? $good : $lang["stage1-text10"],
                ],
                [
                    'name'   => 'OpenSSL',
                    'status' => $openssl,
                    'value'  => '-',
                    'message' => $openssl  ? $good : $lang["stage1-text11"],
                ],
                [
                    'name'   => 'GD',
                    'status' => $gd,
                    'value'  => '-',
                    'message' => $gd  ? $good : $lang["stage1-text12"],
                ],
                [
                    'name'   => 'INTL',
                    'status' => $intl,
                    'value'  => '-',
                    'message' => $intl  ? $good : $lang["stage1-text13"],
                ],
                [
                    'name'   => 'GLOB',
                    'status' => $glob,
                    'value'  => '-',
                    'message' => $glob  ? $good : $lang["stage1-text21"],
                ],
                /*
                [
                    'name'   => 'XML',
                    'status' => $xml,
                    'value'  => '-',
                    'message' => $xml  ? $good : $lang["stage1-text32"],
                ],
                */
                [
                    'name'   => 'JSON',
                    'status' => $json,
                    'value'  => '-',
                    'message' => $json  ? $good : $lang["stage1-text33"],
                ],
                /*
                [
                    'name'   => 'FINFO',
                    'status' => $finfo,
                    'value'  => '-',
                    'message' => $finfo  ? $good : $lang["stage1-text34"],
                ],
                */
                [
                    'name'   => 'IDN',
                    'status' => $idn_to_ascii,
                    'value'  => '-',
                    'message' => $idn_to_ascii  ? $good : $lang["stage1-text35"],
                ],
                [
                    'name'   => 'SESSION',
                    'status' => $session,
                    'value'  => '-',
                    'message' => $session  ? $good : $lang["stage1-text36"],
                ],
                [
                    'name'   => "PATH_INFO",
                    'status' => $cgi_fix_pathinfo,
                    'value'  => '-',
                    'message' => $cgi_fix_pathinfo  ? $good : $lang["stage1-text37"],
                ],
                [
                    'name'   => $lang["stage1-text14"],
                    'status' => $file_get_put,
                    'value'  => '-',
                    'message' => $file_get_put  ? $good : $lang["stage1-text15"],
                ],
            ];
            $suitables  = [];
            $suitablesn = [];

            foreach($requires AS $r) if($r["status"]) $suitables[] = $r; else $suitablesn[] = $r;

            $requires = array_merge($suitablesn,$suitables);
        ?>

        <div class="requirements-table">
            <table width="100%" border="0" cellpadding="7" cellspacing="0">
                <thead>
                <tr>
                    <th width="20%" align="right"><?php echo $lang["stage1-text25"]; ?></th>
                    <th width="20%" align="center"><?php echo $lang["stage1-text26"]; ?></th>
                    <th width="10%" align="center"><?php echo $lang["stage1-text27"]; ?></th>
                    <th width="50%" align="left"></th>
                </tr>
                </thead>
                <tbody>
                <?php
                    foreach($requires AS $req)
                    {
                        $status_class = $req["status"] ? "suitable" : "not-suitable";
                        $status_icon  = $req["status"] ? '<i class="fa fa-check-circle"></i>' : '<i class="fa fa-times"></i>';
                        ?>
                        <tr class="<?php echo $status_class; ?>">
                            <td width="20%" align="right"><?php echo $req["name"]; ?></td>
                            <td width="20%" align="center"><strong><?php echo $req["value"]; ?></strong></td>
                            <td width="10%" align="center"><?php echo $status_icon; ?></td>
                            <td width="50%" align="left"><?php echo $req["message"]; ?></td>
                        </tr>
                        <?php
                    }
                ?>
                </tbody>
            </table>
        </div>
        <div class="clear"></div>


        <div class="requirementnotice">
            <?php
                if(isset($conformity) && $conformity){
                    ?>
                    <h3 style="color:#4caf50"><strong><?php echo $lang["stage1-text24"]; ?></strong></h3>
                    <?php
                }else{
                    ?>
                    <h3 style="color:#f44336"><strong><?php echo $lang["stage1-text23"]; ?></strong></h3>
                    <?php
                }
            ?>
        </div>

        <div class="clear"></div>

        <div align="center">
            <?php
                if(isset($conformity) && $conformity){
                    ?>
                    <a class="button" href="?act=stage2"><?php echo $lang["next-stage"]; ?></a>
                    <?php
                }else{
                    ?>
                    <a class="button" href=""><?php echo $lang["stage1-text18"]; ?></a>
                    <?php
                }
            ?>
        </div>

    </div>

</div>