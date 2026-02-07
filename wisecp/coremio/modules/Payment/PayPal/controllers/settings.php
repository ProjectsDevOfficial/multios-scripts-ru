<?php
    if(!defined("CORE_FOLDER")) die();

    $lang           = $module->lang;
    $config         = $module->config;

    Helper::Load(["Money"]);

    $email               = Filter::init("POST/email","email");
    $subscription_s      = Filter::init("POST/subscription_status");
    $client_id           = Filter::init("POST/client_id");
    $secret_key          = Filter::init("POST/secret_key");
    $commission_rate     = Filter::init("POST/commission_rate","amount");
    $commission_rate     = str_replace(",",".",$commission_rate);
    $convert_to          = (int) Filter::init("POST/convert_to","numbers");
    $sandbox             = (bool) (int) Filter::init("POST/sandbox","numbers");
    $force_subscription  = (bool) (int) Filter::init("POST/force_subscription","numbers");
    $change_subscription_fee  = (bool) (int) Filter::init("POST/change_subscription_fee","numbers");

    $accepted_cs          = Filter::init("POST/accepted_countries");
    $unaccepted_cs        = Filter::init("POST/unaccepted_countries");

    if(!$accepted_cs) $accepted_cs      = [];
    if(!$unaccepted_cs) $unaccepted_cs  = [];



    $sets           = $config;
    $sets2          = [];

    $remove_auth    = false;
    

    if($email != $config["settings"]["email"])
        $sets["settings"]["email"] = $email;

    if($subscription_s != $config["settings"]["subscription_status"])
        $sets["settings"]["subscription_status"] = $subscription_s;

    if($client_id != $config["settings"]["client_id"])
    {
        $sets["settings"]["client_id"] = $client_id;
        $remove_auth = true;
    }

    if($secret_key != $config["settings"]["secret_key"])
    {
        $sets["settings"]["secret_key"] = $secret_key;
        $remove_auth = true;
    }
    

    if($convert_to != $config["settings"]["convert_to"])
        $sets["settings"]["convert_to"] = $convert_to;

    if($force_subscription != $config["settings"]["force_subscription"])
        $sets["settings"]["force_subscription"] = $force_subscription;

    if($change_subscription_fee != $config["settings"]["change_subscription_fee"])
        $sets["settings"]["change_subscription_fee"] = $change_subscription_fee;
    
    if($sandbox != $config["settings"]["sandbox"])
        $sets["settings"]["sandbox"] = $sandbox;

    if($commission_rate != $config["settings"]["commission_rate"])
        $sets["settings"]["commission_rate"] = $commission_rate;

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

        if($subscription_s && ($remove_auth || !isset($config["settings"]["subscription_status"]) || $subscription_s != $config["settings"]["subscription_status"]))
        {
            $test       = $module->testConnection($config_result);

            if(!$test)
            {
                die(Utility::jencode([
                    'status' => "error",
                    'message' => $module->error,
                ]));
            }
        }

        $array_export   = Utility::array_export($config_result,['pwith' => true]);

        $file           = dirname(__DIR__).DS."config.php";
        $write          = FileManager::file_write($file,$array_export);
        if($remove_auth && file_exists(dirname(__DIR__).DS."auth.php"))
            FileManager::file_delete(dirname(__DIR__).DS."auth.php");

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