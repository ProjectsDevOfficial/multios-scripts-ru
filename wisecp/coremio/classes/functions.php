<?php
    if(!defined("INTL_IDNA_VARIANT_UTS46")) define("INTL_IDNA_VARIANT_UTS46",1);
    if(!defined("INTL_IDNA_VARIANT_2003")) define("INTL_IDNA_VARIANT_2003",0);

    if(!function_exists("idn_to_ascii"))
    {
        function idn_to_ascii($arg1='',$arg2='',$arg3='',&$arg4='')
        {
            return $arg1;
        }
    }
    if(!function_exists("mime_content_type"))
    {
        function mime_content_type($filename)
        {
            if(!class_exists("finfo")) return false;
            $result = new finfo();

            if (is_resource($result) === true) {
                return $result->file($filename, FILEINFO_MIME_TYPE);
            }
            return false;
        }
    }
    if(!function_exists("mb_substr"))
    {
        function mb_substr($str, $start, $length = null)
        {
            return substr($str,$start,$length);
        }
    }
    if(!function_exists("mb_strlen"))
    {
        function mb_strlen ($str)
        {
            return strlen($str);
        }
    }

    if(!function_exists('glob_recursive')){
        function glob_recursive($pattern, $flags = 0){
            $files = glob($pattern, $flags);
            foreach (glob(dirname($pattern).DS.'*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir)
                $files = array_merge($files, glob_recursive($dir.DS.basename($pattern), $flags));
            return $files;
        }
    }