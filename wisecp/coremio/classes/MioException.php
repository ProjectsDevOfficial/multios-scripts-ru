<?php
    defined('CORE_FOLDER') OR exit('You can not get in here!');
    class MioException {
        protected $view;

        static $error_type       = [
            500                  => "Software Error",
            E_ERROR              => 'Error',
            E_WARNING            => 'Warning',
            E_PARSE              => 'Parsing Error',
            E_NOTICE             => 'Notice',
            E_CORE_ERROR         => 'Core Error',
            E_CORE_WARNING       => 'Core Warning',
            E_COMPILE_ERROR      => 'Compile Error',
            E_COMPILE_WARNING    => 'Compile Warning',
            E_USER_ERROR         => 'User Error',
            E_USER_WARNING       => 'User Warning',
            E_USER_NOTICE        => 'User Notice',
            E_STRICT             => 'Runtime Notice',
            E_RECOVERABLE_ERROR  => 'Catchable Fatal Error',
            E_DEPRECATED         => 'Deprecated',
            E_USER_DEPRECATED    => 'User Deprecated',
            E_ALL                => 'General',
        ];
        static $error_hide       = false;

        public function __construct()
        {
            $this->view = new View();

            @ini_set("display_errors",false);
            error_reporting(0);

            register_shutdown_function(array($this,"shutdown_error_handler"));
            set_error_handler(array($this,'error_handler'));
        }

        public function error_handler($errno,$errstr,$errfile,$errline){
            if(LOG_SAVE && !self::$error_hide)
            {
                if(
                    $errstr != "Trying to access array offset on value of type bool" &&
                    !stristr($errstr,'declared before required parameter') &&
                    !stristr($errstr,'Undefined array key') &&
                    !stristr($errstr,'Undefined global variable') &&
                    !stristr($errstr,'Passing null to parameter') &&
                    !stristr($errstr,'of type string is deprecated') &&
                    !stristr($errstr,'argument must be of type array|object') &&
                    !stristr($errstr,'Automatic conversion of false to array is deprecated') &&
                    !stristr($errstr,'Implicit conversion from') &&
                    !stristr($errstr,'Controller::$data is deprecated') &&
                    $errstr != "Trying to access array offset on value of type null"
                )
                {
                    if(defined("DEVELOPMENT") && DEVELOPMENT) $errstr .= print_r(debug_backtrace(),true);
                    LogManager::core_error_log($errno,$errstr,$errfile,$errline);
                    if(ERROR_DEBUG && !self::$error_hide) echo "[".(self::$error_type[$errno] ?? $errno)."]: ".$errstr." in ".$errfile." on line: ".$errline."\n ";
                }
            }
            //print_r(debug_backtrace());
        }

        public function shutdown_error_handler(){
            $error = error_get_last();
            if($error && in_array($error['type'],[E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR, E_CORE_WARNING, E_COMPILE_WARNING, E_PARSE])){
                $errno   = $error["type"];
                $errfile = $error["file"];
                $errline = $error["line"];
                $errstr  = $error["message"];
                header("HTTP/1.1 200 OK");
                die($this->error_handler($errno,$errstr,$errfile,$errline));
            }
        }

        public function errorDB($arg=null){
            echo $this->view->chose("system")->render("database-error",['exception' => $arg],true);
        }

    }