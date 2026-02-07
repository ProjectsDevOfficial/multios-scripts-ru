<?php
    class SonicPanel_Module extends ServerModule
    {
        public $api;
        private $panel_data = [];
        private bool $rand_username_already = false;
        function __construct($server,$options=[])
        {
            $this->force_setup = false;
            $this->_name = __CLASS__;

            $auth_endpoint  = NULL;
            $username_parse = explode(":",$server["username"]);
            if(isset($username_parse[1]) && $username_parse[1])
            {
                $auth_endpoint = $username_parse[1];
                $server["username"] = $username_parse[0];
            }

            parent::__construct($server,$options);

            if($auth_endpoint) $this->config["auth-endpoint"] = $auth_endpoint;
        }

        private function access_panel($params=[])
        {
            if(!(isset($params["user"]) && $params["user"] && isset($params['password']) && $params['password']))
            {
                $this->error = "User access information is not defined.";
                return false;
            }
            $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.104 Safari/537.36';

            $ch = curl_init();

            $cookie_file    = ROOT_DIR."temp".DS.md5(time()).".txt";

            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT,10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION,true);
            curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
            curl_setopt($ch, CURLOPT_COOKIEFILE,$cookie_file);

            $website = $this->api->GetHostname(false);

            // Login...

            $post_data  = [
                'username' => $params['user'],
                'password' => $params['password'],
            ];


            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: */*',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent: '.$user_agent,
                'X-Requested-With: XMLHttpRequest',
            ));
            curl_setopt($ch, CURLOPT_URL,$website.'/cp/inc/authsp.php');
            curl_setopt($ch, CURLOPT_POST,true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($post_data));
            $result = curl_exec($ch);
            if(curl_errno($ch)){
                $this->error = curl_error($ch);
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }

            if($result != "1")
            {
                $this->error = $result;
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }


            // Get Index

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: */*',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent: '.$user_agent,
                'X-Requested-With: XMLHttpRequest',
            ));
            curl_setopt($ch, CURLOPT_URL,$website.'/cp/index.php');

            $index_result = curl_exec($ch);
            if(curl_errno($ch)){
                $this->error = curl_error($ch);
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }


            // Get Summary

            preg_match('/{ listeners_limit: "(.*?)", rsys: "(.*?)", port: "(.*?)", radminpass: "(.*?)", rpass: "(.*?)", hosting_limit: "(.*?)", home: "(.*?)", bw_limit: "(.*?)", bitrate_limit: "(.*?)", NoCache: ts }/is',$index_result,$match_data);

            $post_data = [
                'listeners_limit'   => isset($match_data[1]) ? $match_data[1] : '150',
                'rsys'              => isset($match_data[2]) ? $match_data[2] : 'scv26',
                'port'              => isset($match_data[3]) ? $match_data[3] : '8002',
                'radminpass'        => isset($match_data[4]) ? $match_data[4] : '000',
                'rpass'             => isset($match_data[5]) ? $match_data[5] : '000',
                'hosting_limit'     => isset($match_data[6]) ? $match_data[6] : '1024',
                'home'              => isset($match_data[7]) ? $match_data[7] : 'home',
                'bw_limit'          => isset($match_data[8]) ? $match_data[8] : '2048',
                'bitrate_limit'     => isset($match_data[9]) ? $match_data[9] : '128',
                'NoCache'           => DateManager::strtotime(),
            ];

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: */*',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent: '.$user_agent,
                'X-Requested-With: XMLHttpRequest',
            ));
            curl_setopt($ch, CURLOPT_URL,$website.'/cp/accsum.php');
            curl_setopt($ch, CURLOPT_POST,true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($post_data));

            $result = curl_exec($ch);
            if(curl_errno($ch)){
                $this->error = curl_error($ch);
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }
            curl_close($ch);
            FileManager::file_delete($cookie_file);

            $listeners_used     = '';
            $listeners_limit    = '';
            $bitrate_used       = '';
            $bitrate_limit      = '';
            $disk_used          = '';
            $disk_limit         = '';
            $bandwidth_used     = '';
            $bandwidth_limit    = '';
            $status             = '';
            $ip                 = '';
            $port               = '';

            preg_match('/#mem-circ"\)\.circliful\({(.*?)}\);/is',$result,$match_data);
            if(isset($match_data[1]) && $match_data[1])
            {
                preg_match('/text: \'(.*?) (.*?) \/ (.*?)\',/is',$match_data[1],$match_data);
                $parse1     = explode(" ",$match_data[2]);
                $used       = array_pop($parse1);

                if(isset($match_data[2]) && $match_data[2]) $listeners_used = $used;
                if(isset($match_data[3]) && $match_data[3]) $listeners_limit = $match_data[3];
            }

            preg_match('/#disk-circ"\)\.circliful\({(.*?)}\);/is',$result,$match_data);
            if(isset($match_data[1]) && $match_data[1])
            {
                preg_match('/text: \'(.*?) (.*?) \/ (.*?)\',/is',$match_data[1],$match_data);
                $parse1 = explode(" ",$match_data[2]);
                $used_1   = array_pop($parse1);
                $used_0   = array_pop($parse1);
                $used     = $used_0." ".$used_1;
                if(isset($match_data[2]) && $match_data[2]) $disk_used = $used;
                if(isset($match_data[3]) && $match_data[3]) $disk_limit = $match_data[3];
            }

            preg_match('/#load-circ"\)\.circliful\({(.*?)}\);/is',$result,$match_data);
            if(isset($match_data[1]) && $match_data[1])
            {
                preg_match('/text: \'(.*?) (.*?) \/ (.*?)\',/is',$match_data[1],$match_data);

                $parse1 = explode(" ",$match_data[2]);
                $used_1   = array_pop($parse1);
                $used_0   = array_pop($parse1);
                $used     = $used_0." ".$used_1;

                if(isset($match_data[2]) && $match_data[2]) $bandwidth_used = $used;
                if(isset($match_data[3]) && $match_data[3]) $bandwidth_limit = $match_data[3];
            }

            preg_match('/#bitrate"\)\.circliful\({(.*?)}\);/is',$result,$match_data);
            if(isset($match_data[1]) && $match_data[1])
            {
                preg_match('/text: \'(.*?) (.*?) \/ (.*?)\',/is',$match_data[1],$match_data);
                if(isset($match_data[2]) && $match_data[2]) $bitrate_used = $match_data[2];
                if(isset($match_data[3]) && $match_data[3]) $bitrate_limit = $match_data[3];
            }


            if($index_result)
            {
                preg_match('/<span id=\'radio_status\'>(.*?)<\/span>/is',$index_result,$match_status);
                if(isset($match_status[1]) && $match_status[1])
                {
                    if(preg_match('/\W*((?i)Online(?-i))\W*/is',$match_status[1]))
                        $status = 'Online';
                    else
                        $status = 'Offline';
                }

                preg_match('/id="ip4" value="(.*?)"/is',$index_result,$match_data);
                if(isset($match_data[1]) && $match_data[1]) $ip = $match_data[1];

                preg_match('/id="ip5" value="(.*?)"/is',$index_result,$match_data);
                if(isset($match_data[1]) && $match_data[1]) $port = $match_data[1];
            }

            if(!$status)
            {
                $this->error = "Panel data cannot be received.";
                return false;
            }

            $return_data = [
                'status'            => $status,
                'ip'                => $ip,
                'port'              => $port,
                'listeners_used'    => $listeners_used,
                'listeners_limit'   => $listeners_limit,
                'disk_used'         => $disk_used,
                'disk_limit'        => $disk_limit,
                'bandwidth_used'    => $bandwidth_used,
                'bandwidth_limit'   => $bandwidth_limit,
                'bitrate_used'      => $bitrate_used,
                'bitrate_limit'     => $bitrate_limit,
            ];

            return $return_data;
        }
        private function access_admin_packages($params=[])
        {
            $data       = [];

            $this->error = "Package detail not found.";

            $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.104 Safari/537.36';

            $ch = curl_init();

            $cookie_file    = ROOT_DIR."temp".DS.md5(time()).".txt";
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT,10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION,true);
            curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
            curl_setopt($ch, CURLOPT_COOKIEFILE,$cookie_file);

            $website = $this->api->GetHostname();

            // Login...

            $post_data  = [
                'username' => $params['username'],
                'password' => $params['password'],
            ];


            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: */*',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent: '.$user_agent,
                'X-Requested-With: XMLHttpRequest',
            ));
            curl_setopt($ch, CURLOPT_URL,$website.$this->config["auth-endpoint"]);
            curl_setopt($ch, CURLOPT_POST,true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($post_data));
            $result = curl_exec($ch);
            if(curl_errno($ch)){
                $this->error = curl_error($ch);
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }

            if($result != "1")
            {
                $this->error = $result;
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }

            include CORE_DIR."helpers".DS."libraries".DS."html_dom.php";


            // Get Normal Packages

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: */*',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent: '.$user_agent,
                'X-Requested-With: XMLHttpRequest',
            ));
            curl_setopt($ch, CURLOPT_URL,$website.'/scr/edit_pack.php');

            $result = curl_exec($ch);
            if(curl_errno($ch)){
                $this->error = curl_error($ch);
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }

            if($result)
            {
                $html = str_get_html($result);
                if($html && method_exists($html,"find"))
                {
                    foreach($html->find('#helper .packlist') AS $row)
                    {
                        $id         = $row->find('div.card-body button[name=delete]', 0)->getAttribute('id');
                        $title      = $row->find('div.card-header h4', 0)->plaintext;
                        $features   = [];
                        foreach($row->find('div.card-body ul li') AS $li_row)
                        {
                            $li_row         = $li_row->plaintext;
                            $li_row         = explode(": ",$li_row);
                            $features[$li_row[0]] = $li_row[1];
                        }
                        $data["normal"][$title]['id']            = $id;
                        $data["normal"][$title]['features']      = $features;
                    }
                }
            }

            if($params["username"] == "root")
            {
                // Get Reseller Packages

                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Accept: */*',
                    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                    'User-Agent: '.$user_agent,
                    'X-Requested-With: XMLHttpRequest',
                ));
                curl_setopt($ch, CURLOPT_URL,$website.'/scr/edit_pack_reseller.php');

                $result = curl_exec($ch);
                if(curl_errno($ch)){
                    $this->error = curl_error($ch);
                    curl_close($ch);
                    FileManager::file_delete($cookie_file);
                    return false;
                }

                if($result)
                {
                    $html = str_get_html($result);
                    if($html && method_exists($html,"find"))
                    {
                        foreach($html->find('#helper .packlist') AS $row)
                        {
                            $id         = $row->find('div.card-body button[name=delete]', 0)->getAttribute('id');
                            $title      = $row->find('div.card-header h4', 0)->plaintext;
                            $features   = [];
                            foreach($row->find('div.card-body ul li') AS $li_row)
                            {
                                $li_row         = $li_row->plaintext;
                                $li_row         = explode(": ",$li_row);
                                $features[$li_row[0]] = $li_row[1];
                            }
                            $data["reseller"][$title]['id']            = $id;
                            $data["reseller"][$title]['features']      = $features;
                        }
                    }
                }
            }


            curl_close($ch);
            FileManager::file_delete($cookie_file);

            if($data) $this->error = NULL;

            return $data;
        }
        private function access_admin_accounts($params=[])
        {
            $data       = [];

            $this->error = "account list not found.";

            $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.104 Safari/537.36';

            $ch = curl_init();

            $cookie_file    = ROOT_DIR."temp".DS.md5(time()).".txt";

            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT,10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION,true);
            curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
            curl_setopt($ch, CURLOPT_COOKIEFILE,$cookie_file);

            $website = $this->api->GetHostname();

            // Login...

            $post_data  = [
                'username' => $params['username'],
                'password' => $params['password'],
            ];


            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: */*',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent: '.$user_agent,
                'X-Requested-With: XMLHttpRequest',
            ));
            // /pma/server_auth.php
            // /inc/authsp.php
            curl_setopt($ch, CURLOPT_URL,$website.$this->config["auth-endpoint"]);
            curl_setopt($ch, CURLOPT_POST,true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($post_data));
            $result = curl_exec($ch);
            if(curl_errno($ch)){
                $this->error = curl_error($ch);
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }



            if($result != "1")
            {
                $this->error = $result;
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }

            include CORE_DIR."helpers".DS."libraries".DS."html_dom.php";


            // Get Normal Packages

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: */*',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent: '.$user_agent,
                'X-Requested-With: XMLHttpRequest',
            ));
            curl_setopt($ch, CURLOPT_URL,$website.'/scr/list_acc.php');

            $result = curl_exec($ch);
            if(curl_errno($ch)){
                $this->error = curl_error($ch);
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }

            if($result)
            {
                $html = str_get_html($result);
                if($html && method_exists($html,"find"))
                {

                    foreach($html->find('tbody #listing tr') AS $row)
                    {
                        $srv_id             = isset($row->rid) ? $row->rid : false;

                        if($srv_id)
                        {
                            $username           = $row->find('td[name='.$srv_id.']', 0)->plaintext;
                            $radio_status       = true;
                            $ac_status          = $row->find('td[acc_status='.$srv_id.']', 0)->plaintext;
                            $owner              = $row->find('td[owner='.$srv_id.']', 0)->plaintext;
                            $package            = $row->find('td[pack_name='.$srv_id.']', 0)->plaintext;
                            $radio_system       = $row->find('td[sysname='.$srv_id.']', 0)->plaintext;
                            $port               = $row->find('td[port='.$srv_id.']', 0)->plaintext;
                        }
                        else
                        {
                            $username           = $row->find('td', 0)->plaintext;
                            $radio_status       = $row->find('td', 1)->plaintext;
                            $ac_status          = $row->find('td', 2)->plaintext;
                            $owner              = $row->find('td', 3)->plaintext;
                            $package            = $row->find('td', 4)->plaintext;
                            $radio_system       = $row->find('td', 5)->plaintext;
                            $port               = $row->find('td', 6)->plaintext;
                        }

                        $data[] = [
                            'username'      => $username,
                            'radio_status'  => $radio_status,
                            'ac_status'     => $ac_status,
                            'owner'         => $owner,
                            'package'       => $package,
                            'radio_system'  => $radio_system,
                            'port'          => $port,
                        ];
                    }
                }
            }

            $this->error = NULL;


            curl_close($ch);
            FileManager::file_delete($cookie_file);

            if($data) $this->error = NULL;

            return $data;
        }
        private function edit_reseller_post($params=[])
        {
            $access     = $this->server;
            $data       = [];

            $this->error = "account list not found.";

            $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.104 Safari/537.36';

            $ch = curl_init();

            $cookie_file    = ROOT_DIR."temp".DS.md5(time()).".txt";

            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT,10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION,true);
            curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
            curl_setopt($ch, CURLOPT_COOKIEFILE,$cookie_file);

            $website = $this->api->GetHostname();

            // Login...

            $post_data  = [
                'username' => $access['username'],
                'password' => $access['password'],
            ];


            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: */*',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent: '.$user_agent,
                'X-Requested-With: XMLHttpRequest',
            ));
            curl_setopt($ch, CURLOPT_URL,$website.$this->config["auth-endpoint"]);
            curl_setopt($ch, CURLOPT_POST,true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($post_data));
            $result = curl_exec($ch);
            if(curl_errno($ch)){
                $this->error = curl_error($ch);
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }

            if($result != "1")
            {
                $this->error = $result;
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }

            // Post edit reseller feature

            $post_data  = [
                'username'          => isset($params['username']) ? $params['username'] : '',
                'panel_pass'        => isset($params['password']) ? $params['password'] : '',
                'client_email'      => isset($params['email']) ? $params['email'] : '',
                'packsid'           => isset($params['packsid']) ? $params['packsid'] : '',
            ];


            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: */*',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent: '.$user_agent,
                'X-Requested-With: XMLHttpRequest',
            ));
            curl_setopt($ch, CURLOPT_URL,$website.'/scr/edit_res_save.php');
            curl_setopt($ch, CURLOPT_POST,true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($post_data));
            $result = curl_exec($ch);
            if(curl_errno($ch)){
                $this->error = curl_error($ch);
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }

            if(stristr($result,'!'))
            {
                $this->error = NULL;
                $data = ['result' => "complete"];
            }
            else
            {
                $this->api->error   = $result;
                $this->error        = $result;
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }


            curl_close($ch);
            FileManager::file_delete($cookie_file);

            if($data) $this->error = NULL;

            return $data;
        }
        private function control_post($params=[],$cmd='')
        {
            $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.104 Safari/537.36';

            $ch = curl_init();

            $cookie_file    = ROOT_DIR."temp".DS.md5(time()).".txt";

            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT,10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION,true);
            curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
            curl_setopt($ch, CURLOPT_COOKIEFILE,$cookie_file);

            $website = $this->api->GetHostname(false);

            // Login...

            $post_data  = [
                'username' => $params['user'],
                'password' => $params['password'],
            ];


            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: */*',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent: '.$user_agent,
                'X-Requested-With: XMLHttpRequest',
            ));
            curl_setopt($ch, CURLOPT_URL,$website.'/cp/inc/authsp.php');
            curl_setopt($ch, CURLOPT_POST,true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($post_data));
            $result = curl_exec($ch);
            if(curl_errno($ch)){
                $this->error = curl_error($ch);
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }

            if($result != "1")
            {
                $this->error = $result;
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }


            // Get Index

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: */*',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent: '.$user_agent,
                'X-Requested-With: XMLHttpRequest',
            ));
            curl_setopt($ch, CURLOPT_URL,$website.'/cp/index.php');

            $index_result = curl_exec($ch);
            if(curl_errno($ch)){
                $this->error = curl_error($ch);
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }

            // Post Control

            preg_match('/{
  NoCache: ts,
  cmnd: \'true\',
  rsys: \'(.*?)\',
  rid: \'(.*?)\',
  username: \'(.*?)\'
  }/is',$index_result,$match_data);

            $post_data = [
                'NoCache'           => DateManager::strtotime(),
                'cmnd'              => $cmd ? 'true' : 'false',
                'rsys'              => isset($match_data[1]) ? $match_data[1] : 'scv26',
                'rid'               => isset($match_data[2]) ? $match_data[2] : '2',
                'username'          => isset($match_data[3]) ? $match_data[3] : $params["user"],
            ];

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: */*',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent: '.$user_agent,
                'X-Requested-With: XMLHttpRequest',
            ));
            curl_setopt($ch, CURLOPT_URL,$website.'/cp/scr/control.php');
            curl_setopt($ch, CURLOPT_POST,true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($post_data));

            $result = curl_exec($ch);
            if(curl_errno($ch)){
                $this->error = curl_error($ch);
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }
            curl_close($ch);
            FileManager::file_delete($cookie_file);

            if($result != "1")
            {
                $this->error = $result;
                return false;
            }

            return true;
        }

        protected function define_server_info($server=[])
        {
            if(!class_exists("SonicPanelAPI")) include __DIR__.DS."api.class.php";
            $this->api = new SonicPanelAPI($server);
            $this->server = $server;
        }

        public function testConnect(){

            $packs      = $this->api->call("packs");

            if(!$packs && $this->api->error)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function config_options($data=[])
        {
            $is_root        = $this->server["username"] == "root";
            $packages       = $this->api->call("packs",false,true);
            $packages_r     = $is_root ? $this->api->call("reseller_packs",false,true) : [];
            if(!$packages) $packages = [];

            if($packages)
            {
                $packages_n     = [];
                $packages_s     = explode(",",$packages);
                foreach($packages_s AS $v) $packages_n[$v] = $v;
                $packages = $packages_n;
            }

            if($packages_r)
            {
                $packages_r_n     = [];
                $packages_r_s     = explode(",",$packages_r);
                foreach($packages_r_s AS $v) $packages_r_n[$v] = $v;
                $packages_r = $packages_r_n;
            }



            if(!$packages && isset($data["package"]) && $data["package"]) $packages[$data["package"]] = $data["package"];
            if(!$packages_r && isset($data["package_r"]) && $data["package_r"]) $packages_r[$data["package_r"]] = $data["package_r"];
            if($packages_r)
                $packages_r = array_merge(['0' => ___("needs/none")],$packages_r);

            $opts = [
                'package'          => [
                    'wrap_width'        => 100,
                    'name'              => "Package",
                    'type'              => "dropdown",
                    'options'           => $packages,
                    'value'             => isset($data["package"]) ? $data["package"] : "",
                ],
            ];

            if($is_root || (isset($data["reseller"]) && $data["reseller"]) || $data["package_r"])
            {
                $opts['reseller'] =  [
                    'wrap_width'        => 100,
                    'name'              => "Reseller",
                    'description'       => "",
                    'type'              => "approval",
                    'checked'           => isset($data["reseller"]) && $data["reseller"] ? true : false,
                ];

                $opts['package_r'] = [
                    'wrap_width'        => 100,
                    'name'              => "Reseller Package",
                    'type'              => "dropdown",
                    'options'           => $packages_r,
                    'value'             => isset($data["package_r"]) ? $data["package_r"] : "",
                ];
            }

            return $opts;
        }

        public function listAccounts(){
            $accounts       =  [];

            $data           = $this->access_admin_accounts([
                'username'      => $this->server["username"],
                'password'      => $this->server["password"],
            ]);
            if(!$data && $this->error) return false;

            if($data)
            {
                foreach($data AS $row)
                {
                    $item   = [];
                    $item["cdate"]          = '';
                    $item["domain"]         = '';
                    $item["username"]       = $row["username"];
                    $item["plan"]           = $row["package"];
                    $item["suspended"]      = $row["ac_status"] == "Suspended";
                    $accounts[]             = $item;
                }
            }


            return $accounts;

        }

        public function create($domain = '',$order_options=[])
        {
            if(!$domain && isset($order_options["domain"]) && $order_options["domain"])
                $domain = $order_options["domain"];
            if(!$domain && isset($this->order["options"]["domain"]) && $this->order["options"]["domain"])
                $domain = $this->order["options"]["domain"];

            $username = '';
            if($domain) {
                $split_domain   = explode(".",$domain);
                $username       = Utility::substr($split_domain[0] ?? '',0,100);
                if($this->rand_username_already) $username .= Utility::generate_hash(3,false,'d');
            }
            if(!$username) $username = Utility::generate_hash(10,false,'lu').$this->order["id"];

            $password       = Utility::generate_hash(20,false,'lud');



            if(isset($order_options["username"]) && $order_options["username"]) $username = $order_options["username"];
            if(isset($order_options["password"]) && $order_options["password"]) $password = $order_options["password"];

            if(isset($this->val_of_requirements["username"]) && $this->val_of_requirements["username"])
                $username = $this->val_of_requirements["username"];
            if(isset($this->val_of_requirements["password"]) && $this->val_of_requirements["password"])
                $password = $this->val_of_requirements["password"];


            $username       = str_replace("-","",$username);
            $creation_info  = isset($order_options["creation_info"]) ? $order_options["creation_info"] : [];
            $reseller       = isset($creation_info["reseller"]) && $creation_info["reseller"];
            $package        = isset($creation_info["package"]) ? $creation_info["package"] : '';
            $package_r      = isset($creation_info["package_r"]) ? $creation_info["package_r"] : '';

            $conf_opt       = [];

            if($this->val_of_requirements)
                $conf_opt = array_merge($conf_opt,$this->val_of_requirements);

            if($this->val_of_conf_opt)
                $conf_opt = array_merge($conf_opt,$this->val_of_conf_opt);



            if($reseller)
                $create                 = $this->api->call("create_reseller",array_merge([
                    'client_email'      => $this->user["email"],
                    'rad_username'      => $username,
                    'panel_pass'        => $password,
                    'owner'             => $this->server["username"],
                    'package'           => $package_r,
                    'send_email'        => "no",
                ],$conf_opt));
            else
                $create                 = $this->api->call("create",array_merge([
                    'client_email'      => $this->user["email"],
                    'rad_username'      => $username,
                    'panel_pass'        => $password,
                    'owner'             => $this->server["username"],
                    'package'           => $package,
                    'send_email'        => "no",
                ],$conf_opt));

            if(!$create)
            {
                $this->error = $this->lang["error1"].": ".$this->api->error;
                return false;
            }

            if(isset($create["result"]) && $create["result"] == "complete")
                $username = $create["username"];
            else
            {
                $this->error = $this->lang["error1"].": ".(is_array($create) ? print_r($create,true) : $create);
                if(str_contains($this->error,"account already")) {
                    $this->rand_username_already = true;
                    return $this->create($domain,$order_options);
                }
                return false;
            }


            return [
                'username' => $username,
                'password' => $password,
                'ftp_info' => [],
            ];
        }

        public function suspend()
        {
            if(isset($this->options["creation_info"]["reseller"]) && $this->options["creation_info"]["reseller"])
                $suspend = $this->api->call("suspend_reseller",['reseller_username' => $this->config["user"]]);
            else
                $suspend = $this->api->call("suspend",['rad_username' => $this->config["user"]]);

            if(!$suspend && $this->api->error)
            {
                $this->error = $this->lang["error1"].": ".$this->api->error;
                return false;
            }

            if(!is_array($suspend) || $suspend["result"] != "complete")
            {
                $this->error = $this->lang["error1"].": ".(is_array($suspend) ? print_r($suspend,true) : $suspend);
                return false;
            }

            return true;
        }

        public function unsuspend()
        {
            if(isset($this->options["creation_info"]["reseller"]) && $this->options["creation_info"]["reseller"])
                $suspend = $this->api->call("unsuspend_reseller",['reseller_username' => $this->config["user"]]);
            else
                $suspend = $this->api->call("unsuspend",['rad_username' => $this->config["user"]]);

            if(!$suspend && $this->api->error)
            {
                $this->error = $this->lang["error1"].": ".$this->api->error;
                return false;
            }

            if(!is_array($suspend) || $suspend["result"] != "complete")
            {
                $this->error = $this->lang["error1"].": ".(is_array($suspend) ? print_r($suspend,true) : $suspend);
                return false;
            }

            return true;
        }

        public function terminate()
        {
            if(isset($this->options["creation_info"]["reseller"]) && $this->options["creation_info"]["reseller"])
                $terminate = $this->api->call("terminate_reseller",['reseller_username' => $this->config["user"]]);
            else
                $terminate = $this->api->call("terminate",['rad_username' => $this->config["user"]]);

            if(!$terminate && $this->api->error)
            {
                $this->error = $this->lang["error1"].": ".$this->api->error;
                return false;
            }

            if(!is_array($terminate) || $terminate["result"] != "complete")
            {
                $this->error = $this->lang["error1"].": ".(is_array($terminate) ? print_r($terminate,true) : $terminate);
                return false;
            }

            return true;
        }

        public function change_password($password=''){
            if(isset($this->options["creation_info"]["reseller"]) && $this->options["creation_info"]["reseller"])
            {
                $packages       = $this->adminArea_packages();

                $send_params = [
                    'password' => $password,
                    'username' => $this->config["user"],
                    'email' => $this->user["email"],
                ];
                if($packages && isset($packages["reseller"][$this->options["creation_info"]["package_r"]]))
                    $send_params["packsid"] = $packages["reseller"][$this->options["creation_info"]["package_r"]]["id"];

                $change = $this->edit_reseller_post($send_params);
            }
            else
                $change = $this->api->call("changepass",['password' => $password,'rad_username' => $this->config["user"]]);

            if(!$change && $this->error) return false;

            if(!$change && $this->api->error)
            {
                $this->error = $this->lang["error1"].": ".$this->api->error;
                return false;
            }

            if(!is_array($change) || $change["result"] != "complete")
            {
                $this->error = $this->lang["error1"].": ".(is_array($change) ? print_r($change,true) : $change);
                return false;
            }

            return true;
        }

        public function apply_updowngrade($orderopt=[],$product=[]){
            $o_creation_info        = $orderopt["creation_info"];
            $p_creation_info        = $product["module_data"];

            if(isset($this->options["creation_info"]["reseller"]) && $this->options["creation_info"]["reseller"]) {
                $packages = $this->adminArea_packages();

                $send_params = [
                    'password' => '',
                    'username' => $this->config["user"],
                    'email'    => $this->user["email"],
                ];
                if($packages && isset($packages["reseller"][$p_creation_info["package_r"]]))
                {
                    $send_params["packsid"] = $packages["reseller"][$p_creation_info["package_r"]]["id"];
                    $change = $this->edit_reseller_post($send_params);
                }
                else
                {
                    $change = false;
                    $this->api->error = "Not found reseller package";
                }
            }
            else
                $change = $this->api->call("change",['pack' => $p_creation_info["package"],'rad_username' => $this->config["user"]]);

            if(!$change && $this->error) return false;
            if(!$change && $this->api->error)
            {
                $this->error = $this->lang["error1"].": ".$this->api->error;
                return false;
            }

            if(!is_array($change) || $change["result"] != "complete")
            {
                $this->error = $this->lang["error1"].": ".(is_array($change) ? print_r($change,true) : $change);
                return false;
            }

            return true;

        }

        public function clientArea()
        {
            $buttons    = $this->clientArea_buttons_output();
            $_page      = $this->page;
            $_data      = [];

            if(!$_page) $_page = 'home';
            if($_page == "home")
            {
                $reseller   = isset($this->options["creation_info"]["reseller"]) && $this->options["creation_info"]["reseller"];

                $_data["reseller"] = $reseller;
                if(!$reseller)
                {
                    if(!$this->panel_data)
                    {
                        $result             = $this->access_panel($this->config);
                        $this->panel_data   = $result;
                    }
                    else
                        $result = $this->panel_data;

                    $_data["panel_data"] = $result;
                    $_data["panel_data_err"] = $this->error;

                    $_data["panel_url"] = $this->api->getHostname(false)."/cp/";
                }

                $packages = $this->adminArea_packages();
                if($reseller)
                    $package        = isset($packages["reseller"][$this->options["creation_info"]["package_r"]]) ? $packages["reseller"][$this->options["creation_info"]["package_r"]] : [];
                else
                    $package        = isset($packages["normal"][$this->options["creation_info"]["package"]]) ? $packages["normal"][$this->options["creation_info"]["package"]] : [];

                $_data["package"]   = $package;
                $_data["config"]    = $this->config;
                $_data["options"]   = $this->options;
                $_data["buttons"]   = $buttons;
            }
            return $this->get_page('clientArea-'.$_page,$_data);
        }

        public function clientArea_buttons()
        {
            if(isset($this->options["creation_info"]["reseller"]) && $this->options["creation_info"]["reseller"])
                return [];

            if(!$this->panel_data)
            {
                $result             = $this->access_panel($this->config);
                $this->panel_data   = $result;
            }
            else
                $result             = $this->panel_data;
            if(!$result && $this->error) return [];

            $server_status = $result["status"];

            if($server_status == 'Online')
                $buttons['stop']     = [
                    'attributes'=> [
                        'id' => "vpsstop",
                        'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('stop',this);",
                    ],
                    'icon'      => 'fa fa-pause-circle',
                    'text'      => "Stop",
                    'type'      => 'transaction',
                ];
            else
                $buttons['start']     = [
                    'attributes' => [
                        'id' => 'vpsstart',
                        'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('start',this);",
                    ],
                    'icon'  => 'fa fa-play-circle',
                    'text'  => "Start",
                    'type'  => 'transaction',
                ];

            return $buttons;
        }

        public function use_clientArea_start()
        {
            if($this->control_post($this->config,true))
            {
                $u_data     = UserManager::LoginData('member');
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "start" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'server-order-start');

                echo Utility::jencode([
                    'status' => "successful",
                    'message' => $this->lang["successful"],
                    'timeRedirect' => ['url' => $this->area_link, 'duration' => 1000],
                ]);
                return true;
            }
            else
                echo Utility::jencode([
                    'status' => "error",
                    'message' => $this->error,
                ]);
            return false;
        }
        public function use_clientArea_stop()
        {
            if($this->control_post($this->config,false))
            {
                $u_data     = UserManager::LoginData('member');
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "stop" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'server-order-stop');
                echo Utility::jencode([
                    'status' => "successful",
                    'message' => $this->lang["successful"],
                    'timeRedirect' => ['url' => $this->area_link, 'duration' => 1000],
                ]);
                return true;
            }
            else
                echo Utility::jencode([
                    'status' => "error",
                    'message' => $this->error,
                ]);
            return false;
        }
        public function use_clientArea_SingleSignOn2()
        {
            $url = $this->server["secure"] ? "https://" : "http://";

            $url        .= Validation::NSCheck($this->server["name"]) ? $this->server["name"] : $this->server["ip"];

            if(isset($this->options["creation_info"]["reseller"]) && $this->options["creation_info"]["reseller"])
                $url .= $this->server["secure"] ? ":2087" : ":2086";
            else
                $url .= "/cp";

            Utility::redirect($url);

            echo "Redirecting...";
        }


        public function adminArea_buttons()
        {
            if(isset($this->options["creation_info"]["reseller"]) && $this->options["creation_info"]["reseller"])
                return [];

            if(isset($this->config["password"]) && $this->config["password"])
                $this->config["password"] = $this->decode_str($this->config["password"]);

            if(!$this->panel_data)
            {
                $result             = $this->access_panel($this->config);
                $this->panel_data   = $result;
            }
            else
                $result             = $this->panel_data;
            if(!$result && $this->error) return [];

            $server_status = $result["status"];

            if($server_status == 'Online')
                $buttons['stop']     = [
                    'attributes'=> [
                        'id' => "vpsstop",
                        'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('stop',this);",
                    ],
                    'icon'      => 'fa fa-pause-circle',
                    'text'      => "Stop",
                    'type'      => 'transaction',
                ];
            else
                $buttons['start']     = [
                    'attributes' => [
                        'id' => 'vpsstart',
                        'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('start',this);",
                    ],
                    'icon'  => 'fa fa-play-circle',
                    'text'  => "Start",
                    'type'  => 'transaction',
                ];

            return $buttons;
        }

        public function use_adminArea_SingleSignOn2()
        {
            $url = $this->server["secure"] ? "https://" : "http://";

            $url        .= Validation::NSCheck($this->server["name"]) ? $this->server["name"] : $this->server["ip"];

            if(isset($this->options["creation_info"]["reseller"]) && $this->options["creation_info"]["reseller"])
                $url .= $this->server["secure"] ? ":2087" : ":2086";
            else
                $url .= "/cp";

            Utility::redirect($url);

            echo "Redirecting...";
        }
        public function use_adminArea_start()
        {
            if(isset($this->config["password"]) && $this->config["password"])
                $this->config["password"] = $this->decode_str($this->config["password"]);

            if($this->control_post($this->config,true))
            {
                $u_data     = UserManager::LoginData('admin');
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "start" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'server-order-start');

                echo Utility::jencode([
                    'status' => "successful",
                    'message' => $this->lang["successful"],
                    'timeRedirect' => ['url' => $this->area_link."?content=hosting", 'duration' => 1000],
                ]);
                return true;
            }
            else
                echo Utility::jencode([
                    'status' => "error",
                    'message' => $this->error,
                ]);
            return false;
        }
        public function use_adminArea_stop()
        {
            if(isset($this->config["password"]) && $this->config["password"])
                $this->config["password"] = $this->decode_str($this->config["password"]);

            if($this->control_post($this->config,false))
            {
                $u_data     = UserManager::LoginData('admin');
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "stop" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'server-order-stop');
                echo Utility::jencode([
                    'status' => "successful",
                    'message' => $this->lang["successful"],
                    'timeRedirect' => ['url' => $this->area_link."?content=hosting", 'duration' => 1000],
                ]);
                return true;
            }
            else
                echo Utility::jencode([
                    'status' => "error",
                    'message' => $this->error,
                ]);
            return false;
        }

        public function adminArea_data()
        {
            if(isset($this->config["password"]) && $this->config["password"])
                $this->config["password"] = $this->decode_str($this->config["password"]);

            if(!$this->panel_data)
            {
                $result             = $this->access_panel($this->config);
                $this->panel_data   = $result;
            }
            else
                $result = $this->panel_data;
            return $result;
        }

        public function adminArea_packages()
        {
            return $this->access_admin_packages([
                'username'      => $this->server["username"],
                'password'      => $this->server["password"]
            ]);
        }

        public function save_adminArea_service_fields($data=[]){

            /* OLD DATA */
            $o_c_info           = $data['old']['creation_info'];
            $o_config           = $data['old']['config'];
            $o_ftp_info         = $data['old']['ftp_info'];
            $o_options          = $data['old']['options'];

            /* NEW DATA */

            $n_c_info           = $data['new']['creation_info'];
            $n_config           = $data['new']['config'];
            $n_ftp_info         = $data['new']['ftp_info'];
            $n_options          = $data['new']['options'];

            if(isset($o_c_info["reseller"]) && $o_c_info["reseller"])
            {
                if(isset($o_c_info["package_r"]) && $o_c_info["package_r"] != $n_c_info["package_r"])
                {
                    $packages = $this->adminArea_packages();

                    $send_params = [
                        'password' => '',
                        'username' => $this->config["user"],
                        'email'    => $this->user["email"],
                    ];
                    if($packages && isset($packages["reseller"][$n_c_info["package_r"]]))
                    {
                        $send_params["packsid"] = $packages["reseller"][$n_c_info["package_r"]]["id"];
                        $change = $this->edit_reseller_post($send_params);
                    }
                    else
                    {
                        $change = false;
                        $this->api->error = "Not found reseller package";
                    }

                    if(!$change && $this->error) return false;

                    if(!$change && $this->api->error)
                    {
                        $this->error = $this->api->error;
                        return false;
                    }

                    if(!is_array($change) || $change["result"] != "complete")
                    {
                        $this->error = $this->lang["error1"].": ".(is_array($change) ? print_r($change,true) : $change);
                        return false;
                    }
                }
            }
            else
            {
                if(isset($o_c_info["package"]) && $o_c_info["package"] != $n_c_info["package"])
                {
                    $change = $this->api->call("change",['pack' => $n_c_info["package"],'rad_username' => $this->config["user"]]);

                    if(!$change && $this->api->error)
                    {
                        $this->error = $this->api->error;
                        return false;
                    }
                    if(!is_array($change) || $change["result"] != "complete")
                    {
                        $this->error = $this->lang["error1"].": ".(is_array($change) ? print_r($change,true) : $change);
                        return false;
                    }
                }
            }


            return [
                'creation_info'     => $n_c_info,
                'config'            => $n_config,
                'ftp_info'          => [],
                'options'           => $n_options,
            ];
        }
    }

    Hook::add("changePropertyToAccountOrderDetails",1,function($params = [])
    {
        if($params["module"] == "SonicPanel" && !Filter::isPOST())
        {
            $options        = $params["options"];

            Helper::Load("Products");
            $server         = Products::get_server($options["server_id"]);
            if($server) $options["domain"] = $server["name"];

            if(isset($options["ftp_info"]) && $options["ftp_info"]) unset($options["ftp_info"]);
            $options["disable_showing_resource_limits"] = true;
            $params["options"] = $options;
            return $params;
        }
    });

    Hook::add("ClientAreaEndBody",1,function(){
        if(Controllers::$init->getData("module") != "SonicPanel") return false;
        $module = Controllers::$init->getData("module_con");
        return '
<div id="status_wrap" style="display: none;">
<div class="service-status-con" id="server_status_online"><span class="statusonline">Online</span></div>
                <div class="service-status-con" id="server_status_offline">Offline</div>
                <div class="service-status-con" id="server_status_loader" style="display: block;"><span class="statusloader">'.___("needs/processing").'...</span></div>
</div>
<script type="text/javascript">
// Generate a password string
function randString_x(options){

    if(typeof options == "object" && options.characters != undefined && options.characters != \'\') var characters = options.characters;
    else var characters = "A-Z,a-z,0-9";
    if(typeof options == "object" && options.size != undefined && options.size != \'\') var size = options.size;
    else var size = 16;

    var dataSet = characters.split(\',\');
    var possible = \'\';
    if($.inArray(\'a-z\', dataSet) >= 0){
        possible += \'abcdefghijklmnopqrstuvwxyz\';
    }
    if($.inArray(\'A-Z\', dataSet) >= 0){
        possible += \'ABCDEFGHIJKLMNOPQRSTUVWXYZ\';
    }
    if($.inArray(\'0-9\', dataSet) >= 0){
        possible += \'0123456789\';
    }
    if($.inArray(\'#\', dataSet) >= 0){
        possible += \'+_)-(*#!+_)-(*#!\';
    }
    var text = \'\';
    for(var i=0; i < size; i++) {
        text += possible.charAt(Math.floor(Math.random() * possible.length));
    }
    return text;
}

$(document).ready(function(){
    $("#sifredegistir .green-info p").html("'.$module->lang["change-password-warning"].'");
    $("#HostingChangePassword_success h4").html("'.$module->lang["change-password-successful"].'");
    $("#sifredegistir .green-info").attr("class","red-info");
    $("#order_image").html(\'<img height="100" src="'.$module->url.'order-image.jpg" style="height: 160px;width: auto;margin-bottom:15px;">\');
    $("#order_image").before($("#status_wrap").html());
    $("#status_wrap").remove();
    let onclick_content = $("#HostingChangePassword .incelebtn").attr("onclick");
    onclick_content = onclick_content.replace("randString","randString_x");
    $("#HostingChangePassword .incelebtn").attr("onclick",onclick_content);
   
});
</script>';

    });

    Hook::add("AdminAreaEndBody",1,function(){
        if(in_array(View::$init->template_file,['add-hosting.php','edit-hosting.php','hosting-order-detail.php']))
            return '
<script type="text/javascript">
$(document).ready(function(){
    if(document.getElementById("select-shared-server") && $("#select-shared-server option:selected").html().indexOf("SonicPanel") !== -1) toggle_features(0);
    if(document.getElementById("server_info") && $("#server_info").html().indexOf("SonicPanel") !== -1) toggle_features(0);
    $("#select-shared-server").change(function(){
        if($("#select-shared-server option:selected").html().indexOf("SonicPanel") === -1) toggle_features(1);
        else toggle_features(0);
    });
});

function toggle_features(a = 0)
{
    if(a === 1)
    {
        $("#SonicPanel_packages").remove();
        $("input[name=domain]").parent().parent().css("display","block");
    }
    else
    {
        $("input[name=domain]").parent().parent().css("display","none");
    }
    
    $("#disk_limit_container").parent().css("display",a === 1 ? "block" : \'none\');
    $("#bandwidth_limit_container").parent().css("display",a === 1 ? "block" : "none");
    $("#email_limit_container").parent().css("display",a === 1 ? "block" : "none");
    $("#database_limit_container").parent().css("display",a === 1 ? "block" : "none");
    $("#addons_limit_container").parent().css("display",a === 1 ? "block" : "none");
    $("#subdomain_limit_container").parent().css("display",a === 1 ? "block" : "none");
    $("#ftp_limit_container").parent().css("display",a === 1 ? "block" : "none");
    $("#park_limit_container").parent().css("display",a === 1 ? "block" : "none");
    $("#max_email_per_hour_container").parent().css("display",a === 1 ? "block" : "none");
    $("input[name=cpu_limit]").parent().parent().css("display",a === 1 ? "block" : "none");
    $("#feature-CloudLinux").parent().parent().css("display",a === 1 ? "block" : "none");
    $("#dns_content").css("display","none");
}
</script>
        ';
    });