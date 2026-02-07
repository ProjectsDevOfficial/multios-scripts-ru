<?php
    defined('CORE_FOLDER') OR exit('You can not get in here!');
    ob_start();

    session_set_cookie_params(["SameSite" => "None"]);
    session_set_cookie_params(["Secure" => "true"]);
    session_set_cookie_params(["HttpOnly" => "false"]);

    class Cookie {
        static function set($name="",$content,$expire=0,$encode=false){

            if(!($name == "ucid" || $name == "clang")) self::delete($name);

            if($name == "ucid" && Filter::GET("chc")) Session::set("ucid",$content);



            if($content && $encode) $content = Crypt::encode($content,Config::get("crypt/cookie"));
            $_COOKIE[$name] = $content;

            if(version_compare(PHP_VERSION, '7.3.0', '<'))
                return setcookie($name, $content, $expire, "path=/; samesite=None","",true);
            else
                return setcookie($name,$content,[
                    'expires' => $expire,
                    'path'    => "/",
                    'secure' => true,
                    'samesite' => 'None',
                ]);
        }

        static function get($name="",$decode=false){
            $data = isset($_COOKIE[$name]) ? $_COOKIE[$name] : false;
            if($data && $decode) $data = Crypt::decode($data,Config::get("crypt/cookie"));
            return $data;
        }

        static function delete($key){
            if(isset($_COOKIE[$key])){
                unset($_COOKIE[$key]);
                setcookie($key,"",time()-1000);
                setcookie($key,"",time()-1000,'/');
                return true;
            }else
                return false;
        }

        static function GetCookie(){
            return $_COOKIE;
        }

        static function clear(){
            if (isset($_SERVER['HTTP_COOKIE'])) {
                $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
                foreach($cookies as $cookie) {
                    $parts = explode('=', $cookie);
                    $name = trim($parts[0]);
                    setcookie($name, '', time()-1000);
                    setcookie($name, '', time()-1000, '/');
                }
            }
            $_COOKIE = array();
            return true;
        }



    }