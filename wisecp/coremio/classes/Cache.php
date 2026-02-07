<?php
    defined('CORE_FOLDER') OR exit('You can not get in here!');
    if(isset($_SERVER["REQUEST_URI"]) && stristr($_SERVER["REQUEST_URI"],'/callback')) header('Set-Cookie: ' . session_name() . '=' . session_id() . '; SameSite=None; Secure', false);
    ob_start();
    class Cache {

        private $_storage=[];
        private $_cachepath = CACHE_DIR;
        private $_cachename = 'default';
        private $_extension = ".cache";


        public function __construct($config = null) {
            if (true === isset($config)) {
                if (is_string($config)) {
                    $this->setCache($config);
                } else if (is_array($config)) {
                    $this->setCache($config['name']);
                    $this->setCachePath($config['path']);
                    $this->setExtension($config['extension']);
                }
            }
        }


        public function isCached($key) {
            $license = FileManager::get_license_file_data();
            $domain         = str_replace("www.","",Utility::getDomain());
            $l_domain       = str_replace("www.","",$license["domain"] ?? '');
            if($domain != $l_domain) return  false;
            if (false != $this->_loadCache()) {
                $cachedData = $this->_loadCache();
                return isset($cachedData[$key]['data']);
            }
            return false;
        }


        public function store($key, $data, $expiration = 86400) {
            $license        = FileManager::get_license_file_data();
            $domain         = str_replace("www.","",Utility::getDomain());
            $l_domain       = str_replace("www.","",$license["domain"] ?? '');

            if($domain != $l_domain) return $this;


            $storeData = array(
                'time'   => time(),
                'expire' => $expiration,
                'data'   => serialize($data)
            );
            $dataArray = $this->_loadCache();
            if (true === is_array($dataArray)) {
                $dataArray[$key] = $storeData;
            } else {
                $dataArray = array($key => $storeData);
            }
            $cacheData = Utility::jencode($dataArray);
            $cacheData = (DEVELOPMENT) ? $cacheData : Crypt::Encode($cacheData,Config::get("crypt/cache"));
            FileManager::file_write($this->getCacheDir(), $cacheData);
            return $this;
        }


        public function retrieve($key, $timestamp = false) {
            $cachedData = $this->_loadCache();
            (false === $timestamp) ? $type = 'data' : $type = 'time';
            if (!isset($cachedData[$key][$type])) return null;
            return @unserialize($cachedData[$key][$type]);
        }


        public function retrieveAll($meta = false) {
            if ($meta === false) {
                $results = array();
                $cachedData = $this->_loadCache();
                if ($cachedData) {
                    foreach ($cachedData as $k => $v) {
                        $results[$k] = unserialize($v['data']);
                    }
                }
                return $results;
            } else {
                return $this->_loadCache();
            }
        }


        public function erase($key) {
            $cacheData = $this->_loadCache();
            if (true === is_array($cacheData)) {
                if (true === isset($cacheData[$key])) {
                    unset($cacheData[$key]);
                    $cacheData = Utility::jencode($cacheData);
                    $cacheData = (DEVELOPMENT) ? $cacheData : Crypt::Encode($cacheData,Config::get("crypt/cache"));
                    FileManager::file_write($this->getCacheDir(), $cacheData);
                } else {
                    throw new Exception("Error: erase() - Key '{$key}' not found.");
                }
            }
            return $this;
        }


        public function eraseExpired() {
            $cacheData = $this->_loadCache();
            if (true === is_array($cacheData)) {
                $counter = 0;
                foreach ($cacheData as $key => $entry) {
                    if (true === $this->_checkExpired($entry['time'], $entry['expire'])) {
                        unset($cacheData[$key]);
                        $counter++;
                    }
                }
                if ($counter > 0) {
                    $cacheData = Utility::jencode($cacheData);
                    $cacheData = (DEVELOPMENT) ? $cacheData : Crypt::Encode($cacheData,Config::get("crypt/cache"));
                    FileManager::file_write($this->getCacheDir(), $cacheData);
                }
                return $counter;
            }
        }


        public function eraseAll($delete=false) {
            $cacheDir = $this->getCacheDir();
            if (true === file_exists($cacheDir)) {
                if($delete){
                    unlink($cacheDir);
                }else{
                    $cacheFile = fopen($cacheDir, 'w');
                    fclose($cacheFile);
                }
            }
            return $this;
        }


        private function _loadCache() {
            if(isset($this->_storage["loaded"][$this->getCacheDir()]))
                return $this->_storage["loaded"][$this->getCacheDir()];

            if (true === file_exists($this->getCacheDir())) {
                $file = file_get_contents($this->getCacheDir());
                if($file == '')
                    return false;
                $file = (DEVELOPMENT) ? $file : Crypt::Decode($file,Config::get("crypt/cache"));
                $file = Utility::jdecode($file, true);
                $this->_storage["loaded"][$this->getCacheDir()] = $file;
            } else {
                return false;
            }
        }


        public function getCacheDir() {
            if (true === $this->_checkCacheDir()) {
                $filename = $this->getCache();
                $filename = preg_replace('/[^0-9a-z\.\_\-]/i', '', strtolower($filename));
                return $this->getCachePath() . $this->_getHash($filename) . $this->getExtension();
            }
        }


        private function _getHash($filename) {
            #return sha1($filename);
            return $filename;
        }


        private function _checkExpired($timestamp, $expiration){
            $result = false;
            if ($expiration !== 0) {
                $timeDiff = time() - $timestamp;
                ($timeDiff > $expiration) ? $result = true : $result = false;
            }
            return $result;
        }


        private function _checkCacheDir() {
            if (!is_dir($this->getCachePath()) && !mkdir($this->getCachePath(), 0775, true)) {
                throw new Exception('Unable to create cache directory ' . $this->getCachePath());
            } elseif (!is_readable($this->getCachePath()) || !is_writable($this->getCachePath())) {
                if (!chmod($this->getCachePath(), 0775)) {
                    throw new Exception($this->getCachePath() . ' must be readable and writeable');
                }
            }
            return true;
        }


        public function setCachePath($path) {
            $this->_cachepath = $path;
            return $this;
        }


        public function getCachePath() {
            return $this->_cachepath;
        }


        public function setCache($name) {
            $this->_cachename = $name;
            return $this;
        }


        public function getCache() {
            return $this->_cachename;
        }


        public function setExtension($ext) {
            $this->_extension = $ext;
            return $this;
        }


        public function getExtension() {
            return $this->_extension;
        }


        public function clear($keys=[]){
            if($keys && is_string($keys)) $keys = explode(",",$keys);
            if($keys){
                foreach($keys AS $key){
                    $this->setCache($key);
                    $this->eraseAll(true);
                }
            }else{
                $folder = $this->getCachePath()."*".$this->getExtension();
                $files  = FileManager::glob($folder,false,true);
                foreach($files AS $file){
                    $fileexp    = explode(DS,$file);
                    $filename   = end($fileexp);
                    $nexp       = explode($this->getExtension(),$filename);
                    $key        = $this->_getHash($nexp[0]);
                    $this->setCache($key);
                    $this->eraseAll(true);
                }
            }
        }
    }