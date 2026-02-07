<?php
    if(!defined("CORE_FOLDER")) die();

    $lang           = $module->lang;
    $config         = $module->config;

    $username       = Filter::init("POST/username","hclear");
    $password       = Filter::init("POST/password","hclear");
    $api_client       = Filter::init("POST/api_client","hclear");
    $api_secret       = Filter::init("POST/api_secret","hclear");
    $test_mode      = (int) Filter::init("POST/test-mode","numbers");
    $adp            = (bool) (int) Filter::init("POST/adp","numbers");
    $cost_cid       = (int) Filter::init("POST/cost-currency","numbers");


    if($password && $password != "*****") $password = Crypt::encode($password,Config::get("crypt/system"));
    if($api_secret && $api_secret != "*****") $api_secret = Crypt::encode($api_secret,Config::get("crypt/system"));

    $sets           = [];

    if($username != $config["settings"]["username"])
        $sets["settings"]["username"] = $username;

    if($password != "*****" && $password != $config["settings"]["password"])
        $sets["settings"]["password"] = $password;


    if($api_client != $config["settings"]["api_client"])
        $sets["settings"]["api_client"] = $api_client;

    if($api_secret != "*****" && $api_secret != $config["settings"]["api_secret"])
        $sets["settings"]["api_secret"] = $password;

    if($test_mode != $config["settings"]["test-mode"])
        $sets["settings"]["test-mode"] = $test_mode;

    if($adp != $config["settings"]["adp"])
        $sets["settings"]["adp"] = $adp;

    if($cost_cid != $config["settings"]["cost-currency"])
        $sets["settings"]["cost-currency"] = $cost_cid;


    if(!$module->testConnection(array_replace_recursive($config,$sets)))
        die(Utility::jencode([
            'status' => "error",
            'message' => $module->error,
        ]));

    echo Utility::jencode(['status' => "successful",'message' => $lang["success2"]]);
