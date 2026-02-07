<?php
    if(!defined("CORE_FOLDER")) die();

    $lang           = $module->lang;
    $config         = $module->config;

    Helper::Load(["Money"]);

    $merchantid                 = Filter::POST("merchantid");
    $sub_account_id             = Filter::POST("sub_account_id");
    $accesscode                 = Filter::POST("accesscode");
    $workingkey                 = Filter::POST("workingkey");
    $infomsg                    = Filter::POST("infomsg");
    $commission_rate            = Filter::init("POST/commission_rate","amount");
    $commission_rate            = str_replace(",",".",$commission_rate);


    $convert_to           = (int) Filter::init("POST/force_convert_to","numbers");
    $accepted_cs          = Filter::init("POST/accepted_countries");
    $unaccepted_cs        = Filter::init("POST/unaccepted_countries");

    if(!$accepted_cs) $accepted_cs      = [];
    if(!$unaccepted_cs) $unaccepted_cs  = [];

    $sets           = $config;
    $sets2          = [];



    if($merchantid != $config["settings"]["merchantid"])
        $sets["settings"]["merchantid"] = $merchantid;

    if($sub_account_id != $config["settings"]["sub_account_id"])
        $sets["settings"]["sub_account_id"] = $sub_account_id;

    if($accesscode != $config["settings"]["accesscode"])
        $sets["settings"]["accesscode"] = $accesscode;

    if($workingkey != $config["settings"]["workingkey"])
        $sets["settings"]["workingkey"] = $workingkey;

    if($infomsg != $config["settings"]["infomsg"])
        $sets["settings"]["infomsg"] = $infomsg;

    if($commission_rate != $config["settings"]["commission_rate"])
        $sets["settings"]["commission_rate"] = $commission_rate;

    if(!isset($config["settings"]["force_convert_to"]) || $convert_to != $config["settings"]["force_convert_to"])
        $sets["settings"]["force_convert_to"] = $convert_to;

    if(!isset($config["settings"]["accepted_countries"]) || $accepted_cs != $config["settings"]["accepted_countries"])
    {
        $sets["settings"]["accepted_countries"]   = NULL;
        $sets2["settings"]["accepted_countries"]  = $accepted_cs;

    }

    if(!isset($config["settings"]["unaccepted_countries"]) || $unaccepted_cs != $config["settings"]["unaccepted_countries"])
    {
        $sets["settings"]["unaccepted_countries"]   = NULL;
        $sets2["settings"]["unaccepted_countries"]  = $unaccepted_cs;
    }


    if($sets){
        $config_result  = array_replace_recursive($config,$sets);

        if($sets2) $config_result  = array_replace_recursive($config_result,$sets2);

        $array_export   = Utility::array_export($config_result,['pwith' => true]);

        $file           = dirname(__DIR__).DS."config.php";
        $write          = FileManager::file_write($file,$array_export);

        $adata          = UserManager::LoginData("admin");
        User::addAction($adata["id"],"alteration","changed-payment-module-settings",[
            'module' => $config["meta"]["name"],
            'name'   => $lang["name"],
        ]);
    }

    echo Utility::jencode([
        'status' => "successful",
        'message' => $lang["success1"],
    ]);
