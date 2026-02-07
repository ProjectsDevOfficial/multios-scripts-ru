<?php
if (!defined("CORE_FOLDER")) die();
$lang = $module->lang;
$data = $module->domains();
echo Utility::jencode($data);