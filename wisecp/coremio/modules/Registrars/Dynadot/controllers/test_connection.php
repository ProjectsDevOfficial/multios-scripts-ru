<?php
    if(!defined("CORE_FOLDER")) die();

    $lang           = $module->lang;
    $config         = $module->config;

    $key            = Filter::init("POST/key","hclear");
    $whidden_amount = (float) Filter::init("POST/whidden-amount","amount");
    $whidden_curr   = (int) Filter::init("POST/whidden-currency","numbers");
    $test_mode      = (int) Filter::init("POST/test-mode","numbers");
    $adp            = (bool) (int) Filter::init("POST/adp","numbers");
    $cost_cid       = (int) Filter::init("POST/cost-currency","numbers");



    $sets           = [];

    if($key != $config["settings"]["key"])
        $sets["settings"]["key"] = $key;

    if($whidden_amount != $config["settings"]["whidden-amount"])
        $sets["settings"]["whidden-amount"] = $whidden_amount;

    if($whidden_curr != $config["settings"]["whidden-currency"])
        $sets["settings"]["whidden-currency"] = $whidden_curr;

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
