<?php
    /**
     * @author WISECP LLC
     * @since 2017
     * @copyright All rights reserved for WISECP LLC.
     * @contract https://my.wisecp.com/en/service-and-use-agreement
     * @warning Unlicensed can not be copied, distributed and can not be used.
     **/

    defined('CORE_FOLDER') OR exit('You can not get in here!');
    class Model extends Models
    {
        function __construct()
        {
            parent::__construct();
        }

        public function SimilarityCheck($type='',$content=''){
            $this->db_start();
            $sth = $this->db->select("id")->from("newsletters")->where("type","=",$type,"&&")->where("content","=",$content);
            return $sth->build() ? $sth->getObject()->id : false;
        }

        public function addNewsletter($type = '',$content = '',$lang=''){
            $this->db_start();
            $sth = $this->db->insert("newsletters",[
                'lang' => $lang,
                'type' => $type,
                'content' => $content,
                'ip' => UserManager::GetIP(),
                'ctime' => DateManager::Now(),
            ]);
            return $sth;
        }
        public function removeNewsletter($id=0){
            $this->db_start();
            $sth = $this->db->delete("newsletters")->where("id","=",$id);
            return $sth->run();
        }

    }