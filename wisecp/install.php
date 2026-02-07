<?php
    @ob_start();
    @session_start();
    @set_time_limit(0);

    $mod_rewrite_test = isset($_GET["mod_rewrite_test"]) ? strip_tags($_GET["mod_rewrite_test"]) : false;
    if($mod_rewrite_test === "true") exit("Mod rewrite active");

    define("DS",DIRECTORY_SEPARATOR);
    define("CORE_FOLDER","coremio");
    define("ROOT_DIR",__DIR__.DS);
    define("CORE_DIR",ROOT_DIR.CORE_FOLDER.DS);
    define("TEMPLATE_DIR",ROOT_DIR."templates".DS."system".DS);
    define("CONFIG_DIR",ROOT_DIR.CORE_FOLDER.DS."configuration".DS);
    define("LOCALE_DIR",ROOT_DIR.CORE_FOLDER.DS."locale".DS);

    if(!file_exists(CONFIG_DIR."general.php")) die(CONFIG_DIR."general.php File Not Found.");

    $_SESSION["testing"]    = "success";
    $wcp_version = @file_get_contents(CORE_DIR."VERSION");
    define("WCP_VERSION",$wcp_version);
    $general_file = CONFIG_DIR."general.php";
    $general    = include $general_file;
    $general    = $general["general"];
    $clang      = $general["local"];

    date_default_timezone_set($general["timezone"]);

    $s_lang     = isset($_SESSION["lang"]) ? $_SESSION["lang"] : false;
    if($s_lang) $clang  = $s_lang;
    else $_SESSION["lang"] = $clang;

    $lfile      = LOCALE_DIR.$clang.DS."cm".DS."system".DS."install.php";

    if(!file_exists($lfile))
        die($lfile." Language File Not Found.");
    $lang       = include $lfile;

    function wcp_glob($pattern, $flags = 0,$recursive=false){
        if(!function_exists("glob")) return false;
        if ($recursive){
            if(!function_exists('glob_recursive')){
                // Does not support flag GLOB_BRACE
                function glob_recursive($pattern, $flags = 0){
                    $files = glob($pattern, $flags);
                    foreach (glob(dirname($pattern).DS.'*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir)
                        $files = array_merge($files, glob_recursive($dir.DS.basename($pattern), $flags));
                    return $files;
                }
            }
            return glob_recursive($pattern,$flags);
        }else
            return glob($pattern,$flags);
    }

    function isSSL(){
        if(isset($_SERVER['https']) && $_SERVER['https'] == 'on' || isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')
            return true;
        elseif(isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == '443')
            return true;
        elseif(!empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' )
            return true;

        return false;
    }
    function get_phpinfo_content()
    {
        ob_start();
        phpinfo();
        $aux = str_replace('&nbsp;', ' ', ob_get_clean());
        if($aux !== false){
            return $aux;
        }
        return false;
    }
    function GetIonCubeLoaderVersion($aux=''){
        if($aux !== false)
        {
            $pos = stripos($aux, 'ionCube PHP Loader');
            if($pos !== false)
            {
                $aux = substr($aux, $pos + 18);
                $aux = substr($aux, stripos($aux, ' v') + 2);

                $version = '';
                $c = 0;
                $char = substr($aux, $c++, 1);
                while(strpos('0123456789.', $char) !== false)
                {
                    $version .= $char;
                    $char = substr($aux, $c++, 1);
                }
                return $version;
            }
        }
        return false;
    }

    function mod_rewrite_testing()
    {
        $domain         = isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : (isset($_SERVER["SERVER_NAME"]) ? $_SERVER["SERVER_NAME"] : '');
        $uri            = $_SERVER["REQUEST_URI"];
        $file_name      = basename(__FILE__);
        if(stristr($uri,$file_name))
            $uri            = str_replace($file_name,"install-mod-rewrite-test",$uri);
        else
        {
            $query = stristr($uri,"?");
            if($query)
            {
                $uri_exp    = explode("?",$uri);
                $uri        = $uri_exp[0];
            }
            $uri  .= "install-mod-rewrite-test";
        }

        $url            = 'http'.(isSSL() ? "s" : "")."://".$domain.$uri;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);


        $data   = curl_exec($ch);
        $error  = curl_error($ch);

        curl_close($ch);

        if($error) return ['message' => $error];
        return stristr($data,'Mod rewrite active');
    }

    function requirement_tester(){
        global $address,$mod_rewrite_test;
        $php                = version_compare(PHP_VERSION, '7.2.0', '>=');
        $php_v              = phpversion();
        $phpinfo            = get_phpinfo_content();
        $ioncube            = $phpinfo ? true : false;
        $ioncube_v          = GetIonCubeLoaderVersion($phpinfo);
        $_session           = isset($_SESSION["testing"]) ? $_SESSION["testing"] : false;
        $curl               = false;
        $pdo                = false;
        $zip                = false;
        $mbstring           = false;
        $openssl            = false;
        $gd                 = false;
        $intl               = false;
        $file_get_put       = true;
        $xml                    = function_exists("simplexml_load_string");
        $glob                   = function_exists("glob");
        $json                   = function_exists("json_encode") && function_exists("json_decode");
        $finfo                  = class_exists("finfo");
        $idn_to_ascii           = function_exists("idn_to_ascii");
        $mysqli                 = function_exists("mysqli_connect");
        $memory_limit           = true;
        $max_execution_time     = true;
        $session                = $_session == "success";
        $suggested_memory       = 256;
        $suggested_exe          = 60;


        $search = '<tr><td(.*?)>memory_limit<\/td><td(.*?)>(.*?)<\/td><td(.*?)>(.*?)<\/td><\/tr>';
        preg_match('/'.$search.'/',$phpinfo,$matches);
        $memory_limit_1_raw = $matches[3];

        $search                 = '<tr><td(.*?)>max_execution_time<\/td><td(.*?)>(.*?)<\/td><td(.*?)>(.*?)<\/td><\/tr>';
        preg_match('/'.$search.'/',$phpinfo,$matches);
        $max_execution_time_1   = $matches[3];


        $memory_limit_1           = 0;
        if(preg_match('/^(\d+)(.)$/', $memory_limit_1_raw, $matches)){
            if ($matches[2] == 'G'){
                $memory_limit_1 = $matches[1] * 1024 * 1024 * 1024; // nnnG -> nnn GB
            }elseif ($matches[2] == 'M'){
                $memory_limit_1 = $matches[1] * 1024 * 1024; // nnnM -> nnn MB
            }elseif ($matches[2] == 'K'){
                $memory_limit_1 = $matches[1] * 1024; // nnnK -> nnn KB
            }elseif ($matches[2] == 'B'){
                $memory_limit_1 = $matches[1];
            }
        }


        if($memory_limit_1_raw == '' || (substr($memory_limit_1_raw,0,2) !== '-1' && $memory_limit_1 > 0 && $memory_limit_1 < ($suggested_memory * 1024 * 1024)))
        {
            $memory_limit = false;
            $memory_limit_used = $memory_limit_1_raw;
        }
        else
            $memory_limit_used = $memory_limit_1_raw;

        if($max_execution_time_1 == '' || ($max_execution_time_1 != '-1' && !($max_execution_time_1 >= $suggested_exe)))
        {
            $max_execution_time = false;
            $max_execution_time_used = $max_execution_time_1;
        }
        else
            $max_execution_time_used = $max_execution_time_1;

        $search                 = '<tr><td(.*?)>cgi.fix_pathinfo<\/td><td(.*?)>(.*?)<\/td><td(.*?)>(.*?)<\/td><\/tr>';
        preg_match('/'.$search.'/',$phpinfo,$matches);

        $cgi_fix_pathinfo = $matches[3] ?? '';


        foreach(get_loaded_extensions() as $name){
            $name   = strtolower($name);
            if($name == "ioncube loader") $ioncube = true;
            elseif($name == "zlib") $zip = true;
            elseif($name == "pdo_mysql") $pdo = true;
            elseif($name == "curl") $curl = true;
            elseif($name == "mbstring") $mbstring = true;
            elseif($name == "openssl") $openssl = true;
            elseif($name == "gd") $gd = true;
            elseif($name == "intl") $intl = true;
        }
        if(function_exists("glob")) $glob = true;
        if(!function_exists('curl_init') OR !function_exists('curl_exec') OR !function_exists('curl_setopt')) $curl = false;
        if(!function_exists("fopen") || !function_exists("fwrite") || !function_exists("file_get_contents") || !function_exists("file_put_contents")) $file_get_put = false;

        if($ioncube) $ioncube = $ioncube_v >= 12.0;

        $mod_rewrite_request       = mod_rewrite_testing();
        $mod_rewrite_error          = NULL;
        if($mod_rewrite_request && is_array($mod_rewrite_request)) $mod_rewrite_error = $mod_rewrite_request["message"];
        elseif($mod_rewrite_request) $mod_rewrite_test = true;

        $server = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');

        if(empty($cgi_fix_pathinfo) && !$mod_rewrite_test && !str_contains($server, 'nginx'))
        {
            $cgi_fix_pathinfo = true;
            $mod_rewrite_test = true;
        }

        if($mod_rewrite_test)
            $cgi_fix_pathinfo = true;
        elseif(strtoupper($cgi_fix_pathinfo) != "ON")
            $cgi_fix_pathinfo = false;

        $_SESSION["mod_rewrite_test"] = $mod_rewrite_test;


        $conformity     = true;

        if($conformity && !$php) $conformity = false;
        if($conformity && !$phpinfo) $conformity = false;
        if($conformity && !$ioncube) $conformity = false;
        if($conformity && !$zip) $conformity = false;
        if($conformity && !$pdo) $conformity = false;
        if($conformity && !$curl) $conformity = false;
        if($conformity && !$mbstring) $conformity = false;
        if($conformity && !$openssl) $conformity = false;
        if($conformity && !$gd) $conformity = false;
        if($conformity && !$intl) $conformity = false;
        if($conformity && !$file_get_put) $conformity = false;
        if($conformity && !$glob) $conformity = false;
        if($conformity && !$json) $conformity = false;
        //if($conformity && !$finfo) $conformity = false;
        if($conformity && !$idn_to_ascii) $conformity = false;
        if($conformity && !$mysqli) $conformity = false;
        //if($conformity && !$memory_limit) $conformity = false;
        //if($conformity && !$max_execution_time) $conformity = false;
        if($conformity && !$session) $conformity = false;
        if($conformity && !$cgi_fix_pathinfo) $conformity = false;
        //if($conformity && !$mod_rewrite_test) $conformity = false;

        return [
            'php'               => $php,
            'php_v'             => $php_v,
            'phpinfo'           => $phpinfo,
            'ioncube'           => $ioncube,
            'ioncube_v'         => $ioncube_v,
            'curl'              => $curl,
            'cgi_fix_pathinfo'  => $cgi_fix_pathinfo,
            'mod_rewrite_test'  => $mod_rewrite_test,
            'mod_rewrite_error' => $mod_rewrite_error,
            'pdo'               => $pdo,
            'zip'               => $zip,
            'mbstring'          => $mbstring,
            'openssl'           => $openssl,
            'gd'                => $gd,
            'intl'              => $intl,
            'file_get_put'      => $file_get_put,
            'file_permissions'  => [],
            'glob'              => $glob,
            'mysqli'            => $mysqli,
            'xml'               => $xml,
            'json'              => $json,
            'finfo'             => $finfo,
            'idn_to_ascii'      => $idn_to_ascii,
            'session'           => $session,
            'memory_limit'      => $memory_limit,
            'memory_limit_used' => $memory_limit_used,
            'max_execution_time'=> $max_execution_time,
            'max_execution_time_used' => $max_execution_time_used,
            'conformity'        => $conformity,
        ];
    }
    function isQuoted($offset, $text)
    {
        if ($offset > strlen($text))
            $offset = strlen($text);

        $isQuoted = false;
        for ($i = 0; $i < $offset; $i++) {
            if ($text[$i] == "'")
                $isQuoted = !$isQuoted;
            if ($text[$i] == "\\" && $isQuoted)
                $i++;
        }
        return $isQuoted;
    }
    function clearSQL($sql, &$isMultiComment)
    {
        if ($isMultiComment) {
            if (preg_match('#\*/#sUi', $sql)) {
                $sql = preg_replace('#^.*\*/\s*#sUi', '', $sql);
                $isMultiComment = false;
            } else {
                $sql = '';
            }
            if(trim($sql) == ''){
                return $sql;
            }
        }

        $offset = 0;
        while (preg_match('{--\s|#|/\*[^!]}sUi', $sql, $matched, PREG_OFFSET_CAPTURE, $offset)) {
            list($comment, $foundOn) = $matched[0];
            if (isQuoted($foundOn, $sql)) {
                $offset = $foundOn + strlen($comment);
            } else {
                if (substr($comment, 0, 2) == '/*') {
                    $closedOn = strpos($sql, '*/', $foundOn);
                    if ($closedOn !== false) {
                        $sql = substr($sql, 0, $foundOn) . substr($sql, $closedOn + 2);
                    } else {
                        $sql = substr($sql, 0, $foundOn);
                        $isMultiComment = true;
                    }
                } else {
                    $sql = substr($sql, 0, $foundOn);
                    break;
                }
            }
        }
        return $sql;
    }
    function query($sql){
        global $db;
        try{
            $db->query($sql);
            return true;
        }catch(PDOException $e){
            throw new PDOException($e->getMessage(),$e->getCode(),$e->getPrevious());
            return false;
        }
    }
    function sqlImport($file)
    {

        $delimiter = ';';
        $file = fopen($file, 'r');
        $isFirstRow = true;
        $isMultiLineComment = false;
        $sql = '';

        while (!feof($file)) {

            $row = fgets($file);

            // remove BOM for utf-8 encoded file
            if ($isFirstRow) {
                $row = preg_replace('/^\x{EF}\x{BB}\x{BF}/', '', $row);
                $isFirstRow = false;
            }

            // 1. ignore empty string and comment row
            if (trim($row) == '' || preg_match('/^\s*(#|--\s)/sUi', $row)) {
                continue;
            }

            // 2. clear comments
            $row = trim(clearSQL($row, $isMultiLineComment));

            // 3. parse delimiter row
            if (preg_match('/^DELIMITER\s+[^ ]+/sUi', $row)) {
                $delimiter = preg_replace('/^DELIMITER\s+([^ ]+)$/sUi', '$1', $row);
                continue;
            }

            // 4. separate sql queries by delimiter
            $offset = 0;
            while (strpos($row, $delimiter, $offset) !== false) {
                $delimiterOffset = strpos($row, $delimiter, $offset);
                if (isQuoted($delimiterOffset, $row)) {
                    $offset = $delimiterOffset + strlen($delimiter);
                } else {
                    $sql = trim($sql . ' ' . trim(substr($row, 0, $delimiterOffset)));
                    if(!query($sql))
                        return false;

                    $row = substr($row, $delimiterOffset + strlen($delimiter));
                    $offset = 0;
                    $sql = '';
                }
            }
            $sql = trim($sql . ' ' . $row);
        }
        if (strlen($sql) > 0) {
            if(!query($row))
                return false;
        }

        fclose($file);
    }

    function download_outer_file($url, $localPathname,$post=false){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        if($post)
        {
            curl_setopt($ch,CURLOPT_POST,1);
            curl_setopt($ch,CURLOPT_POSTFIELDS,$post);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        $data   = curl_exec($ch);
        $error  = curl_error($ch);

        curl_close($ch);

        if($error)
        {
            return [
                'status'    => "error",
                'code'   => "002",
                'message' => $error,
            ];
        }

        $size   = strlen($data);
        if($size <= 1000)
        {
            $data = json_decode($data,true);
            return [
                'status'    => "error",
                'code'   => $data["code"],
                'message' => $data["message"],
            ];
        }


        if(!$data)
            return [
                'status' => "error",
                'code'   => "003",
                'message' => "An empty data was received",
            ];

        $fp = fopen($localPathname, 'wb');

        if ($fp) {
            fwrite($fp, $data);
            fclose($fp);
        } else {
            fclose($fp);
            return [
                'status'    => "error",
                'code'   => "004",
                'message'   => "Failed to write target file: ".$localPathname,
            ];
        }

        return true;
    }

    function GetIP(){
        if(isset($_SERVER["HTTP_CLIENT_IP"]) && $_SERVER["HTTP_CLIENT_IP"]) {
            $ip = $_SERVER["HTTP_CLIENT_IP"];
        } elseif(isset($_SERVER["HTTP_X_FORWARDED_FOR"]) && $_SERVER["HTTP_X_FORWARDED_FOR"]) {
            $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
            if (strstr($ip, ',')) {
                $tmp = explode (',', $ip);
                $ip = trim($tmp[0]);
            }
            elseif (strstr($ip, ':')) {
                $tmp = explode (':', $ip);
                $ip_ = trim(end($tmp));
                if(strlen($ip_) >= 5) $ip = $ip_;
            }
        } else {
            $ip = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : false;
            if($ip == "::1") $ip = isset($_SERVER["SERVER_ADDR"]) ? $_SERVER["SERVER_ADDR"] : false;
        }

        if($ip && !filter_var($ip,FILTER_VALIDATE_IP) && isset($_SERVER["REMOTE_ADDR"]) && $_SERVER["REMOTE_ADDR"])  $ip = $_SERVER["REMOTE_ADDR"];

        if(!$ip) $ip = "UNKNOWN";
        $ip     = strip_tags($ip);
        $ip     = substr($ip,0,50);
        return $ip;
    }

    $way        = $_SERVER["REQUEST_URI"];
    if(stristr($way,"?")){
        $split  = explode("?",$way);
        $way    = $split[0];
    }
    $way        = str_replace("install.php","",$way);
    $address    = isSSL() ? "https" : "http";
    $address    .= "://".$_SERVER["HTTP_HOST"].$way;

    $taddress   = "https://my.wisecp.com/templates/system/";
    $saddress   = "https://my.wisecp.com/resources/";

    $ses_stage  = isset($_SESSION["stage"]) ? (int) $_SESSION["stage"] : false;
    $established = isset($_SESSION["established"]) ? $_SESSION["established"] : false;
    $stage      = isset($_GET["stage"]) ? $_GET["stage"] : false;

    if($general["established-date"] != "0000-00-00 00:00:00" && $ses_stage < 4 && !$established){
        echo <<<HTML
<iframe src="https://wisecp.com/{$clang}/warning-install-file" style="border: 0; position:fixed; top:0; left:0; right:0; bottom:0; width:100%; height:100%"></iframe>
HTML;
        exit();
    }

    $act        = isset($_GET["act"]) ? $_GET["act"] : false;

    if($act){

        if($act == "stage1"){
            $_SESSION["stage"] = 1;
            header("Location:".$address."install.php?stage=1");
        }
        elseif($act == "stage2"){
            $tester = requirement_tester();
            if(!$tester["conformity"]) exit();
            $_SESSION["stage"] = 2;
            header("Location:".$address."install.php?stage=2");
        }
        elseif($act == "stage3"){

            $db_host        = $_POST["db_host"];
            $db_name        = $_POST["db_name"];
            $db_username    = $_POST["db_username"];
            $db_password    = $_POST["db_password"];

            if(!$db_host) $db_host = "localhost";

            if(!$db_name)
                die(json_encode([
                    'status' => "error",
                    'for'    => "input[name=db_name]",
                    'message' => $lang["error1"],
                ]));

            if(!$db_username)
                die(json_encode([
                    'status' => "error",
                    'for'    => "input[name=db_username]",
                    'message' => $lang["error2"],
                ]));

            if(!$db_password)
                die(json_encode([
                    'status' => "error",
                    'for'    => "input[name=db_password]",
                    'message' => $lang["error3"],
                ]));


            try{
                $db = new PDO('mysql:dbname='.$db_name.';host='.$db_host.';charset=utf8',$db_username,$db_password,array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
                $db->exec("SET names utf8");
                $db->exec("SET character_set_connection = 'utf8' ");
            }catch(PDOException $e){
                die(json_encode([
                    'status' => "error",
                    'message' => $lang["error4"].$e->getMessage(),
                ]));
            }

            try{
                $statement = $db->query("SHOW TABLES");
            }catch(PDOException $e){
                die(json_encode([
                    'status' => "error",
                    'message' => $lang["error5"].$e->getMessage(),
                ]));
            }

            $is_import  = true;

            $tables = $statement->fetchAll(PDO::FETCH_NUM);
            $tables = array_map(function($a){ return $a[0] ?? ''; },$tables);
            if($tables && in_array("users_informations",$tables)) 
                $is_import = false;

            $result     = $db->query('SELECT @@version AS version');
            $row        = $result->fetch(PDO::FETCH_ASSOC);
            $version    = $row['version'];
            $vType      = 'MySQL';

            if(strpos($version, 'MariaDB') !== false)
            { // MariaDB
                $vType          = 'MariaDB';
                $versionParts   = explode('-', $version);
                $version        = $versionParts[0];
                if (version_compare($version, '10.3.0', '<'))
                    die(json_encode([
                        'status' => "error",
                        'message' => str_replace([
                            '{CURRENT_VERSION}',
                            '{MIN_VERSION}',
                        ],[
                            'MariaDB '.$version,
                            '10.3.0',
                        ],$lang["error16"]),
                    ]));
            }
            else
            { // MySQL
                if (version_compare($version, '8.0.0', '<'))
                    die(json_encode([
                        'status' => "error",
                        'message' => str_replace([
                            '{CURRENT_VERSION}',
                            '{MIN_VERSION}',
                        ],[
                            'MySQL '.$version,
                            '8.0',
                        ],$lang["error16"]),
                    ]));

            }

            /*
            $result       = $db->query("SELECT @@sql_mode AS value;");
            $row          = $result->fetch(PDO::FETCH_ASSOC);
            $sql_mode     = $row['value'];
            $sql_mode     = explode(",",str_replace(' ','',$sql_mode));

            $caught   = [
                'STRICT_TRANS_TABLES',
                'STRICT_ALL_TABLES',
                'ERROR_FOR_DIVISION_BY_ZERO',
                'NO_ZERO_DATE',
                'NO_ZERO_IN_DATE',
            ];

            $detect     = [];
            foreach($caught AS $c) if(in_array($c,$sql_mode)) $detect[] = $c;

            if($detect)
                die(json_encode([
                    'status' => "error",
                    'message' => $lang["error17"],
                ]));*/


            if($is_import){

                $sql_file   = __DIR__.DS."install.sql";

                if(!file_exists($sql_file))
                    die(json_encode([
                        'status' => "error",
                        'message' => $lang["error6"],
                    ]));

                try{
                    sqlImport($sql_file);
                }
                catch(PDOException $e){
                    die(json_encode([
                        'status' => "error",
                        'message' => $lang["error5"].$e->getMessage(),
                    ]));
                }

                if($db){
                    sleep(5);
                    $db->query("UPDATE currencies SET rate='0' WHERE local=1");
                    $db->query("UPDATE currencies SET local='0'");
                    $db->query("UPDATE currencies SET local='1',rate='1' WHERE id=".$general["currency"]);
                }

            }

            $db_file    = __DIR__.DS.CORE_FOLDER.DS."configuration".DS."database.php";

            if(!file_exists($db_file))
                die(json_encode([
                    'status' => "error",
                    'message' => $lang["error7"],
                ]));

            $database   = include $db_file;

            $database["database"]["host"]       = $db_host;
            $database["database"]["name"]       = $db_name;
            $database["database"]["username"]   = $db_username;
            $database["database"]["password"]   = $db_password;
            $export                             = var_export($database,true);
            $export                             = '<?php
    defined(\'CORE_FOLDER\') OR exit(\'You can not get in here!\');
    return '.$export.';';

            $write = function_exists("file_put_contents") && file_put_contents($db_file,$export);

            if(!$write)
                die(json_encode([
                    'status' => "error",
                    'message' => $lang["error18"],
                ]));

            sleep(3);

            $now                                = (new DateTime())->format("Y-m-d H:i:s");
            $_SESSION["established"]            = $now;
            $rich_url                           = $_SESSION["mod_rewrite_test"] ?? false;
            $defined_str                        = "<?php";
            $defined_str                        .= ' defined(\'CORE_FOLDER\') OR exit(\'You can not get in here!\');';
            $defined_str                        .= "\n";
            $defined_str                        .= "return ";

            $general["established-date"]        = $now;
            $general["rich-url"]                = $rich_url ? "on" : "off";
            $export_arr                         = ['general' => $general];
            $export                             = $defined_str . var_export($export_arr,true) . ";";
            $write = function_exists("file_put_contents") && file_put_contents($general_file,$export);

            if(!$write)
                die(json_encode([
                    'status' => "error",
                    'message' => "coremio/configuration/general.php file could not be saved.",
                ]));


            $_SESSION["stage"] = 3;

            echo json_encode([
                'status' => "successful",
                'redirect' => $address."install.php?stage=3",
            ]);
        }
        exit();
    }

    if($stage == 1 && $ses_stage >= 1){
        $tester   = requirement_tester();
        extract($tester);

        if($conformity) header("Location:".$address."install.php?act=stage2");
    }
    elseif($stage == 2 && $ses_stage >= 2){
        $tester   = requirement_tester();

        extract($tester);
    }
    elseif($stage == 3 && $ses_stage >= 3)
    {
        $database   = include __DIR__.DS.CORE_FOLDER.DS."configuration".DS."database.php";
        $database   = $database["database"];

        try{
            $db = new PDO('mysql:dbname='.$database["name"].';host='.$database["host"].';charset=utf8',$database["username"],$database["password"],array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            $db->exec("SET names utf8");
            $db->exec("SET character_set_connection = 'utf8' ");
        }catch(PDOException $e){
            $db = null;
        }

        if($db){
            $admin  = $db->query("SELECT id,email FROM users WHERE id=1");
            $admin  = $admin->fetch(PDO::FETCH_ASSOC);
            $admin_email = isset($admin["email"]) ? $admin["email"] : '';
        }

        unset($_SESSION["lang"]);
        unset($_SESSION["stage"]);
        unlink(__DIR__.DS."install.php");
        unlink(__DIR__.DS."install.sql");


    }
    else $stage = false;

    include TEMPLATE_DIR."install.php";