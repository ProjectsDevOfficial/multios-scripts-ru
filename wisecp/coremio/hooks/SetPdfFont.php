<?php
    Hook::add("SetPdfFont",1,function($html2pdf,$invoice=[]){
        $lang           = substr(Bootstrap::$lang->clang,0,2);

        $saved_lang     = substr($invoice["user_data"]["lang"] ?? '',0,2);
        if(defined("CRON") || defined("ADMINISTRATOR")) $lang = $saved_lang;

        $select         = Config::get("options/pdf-font");
        $default        = "freesans";


        $fonts = [
            'ar' => 'xzar',
        ];


        if($select) $font = $select;
        else $font = $fonts[$lang] ?? $default;

        $html2pdf->setDefaultFont($font);
    });