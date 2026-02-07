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

        public function get_header_background(){
            $this->db_start();
            $sth = $this->db->select("name")->from("pictures");
            $sth->where("owner_id","=",0,"&&");
            $sth->where("owner","=","contact","&&");
            $sth->where("reason","=","header-background");
            if($sth->build())
                return $sth->getObject()->name;
            else
                return false;
        }

        public function add($data=[]){
            $data['cdate'] = DateManager::Now();
            return $this->db->insert("contact_messages",$data);
        }

    }