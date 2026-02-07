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

        public function header_background(){
            $sth = $this->db->select("name")->from("pictures");
            $sth->where("owner","=","domain","&&");
            $sth->where("owner_id","=",0,"&&");
            $sth->where("reason","=","header-background");
            return $sth->build() ? $sth->getObject()->name : false;
        }

        public function TLDList(){
            $sth = $this->db->select()->from("tldlist");
            $sth->where("status","=","active");
            $sth->order_by("rank ASC");
            return $sth->build() ? $sth->fetch_assoc() : false;
        }

        public function getTLD($name='',$rank='0'){
            $sth = $this->db->select()->from("tldlist AS t1");
            if($name != '') $sth->where("name","=",$name,"&&");
            if(!is_string($rank) && $rank != 0) $sth->where("rank","=",$rank,"&&");
            $sth->where("status","=","active");
            if(!is_string($rank) && $rank == 0) $sth->order_by("t1.rank ASC");
            return $sth->build() ? $sth->getAssoc() : false;
        }


    }