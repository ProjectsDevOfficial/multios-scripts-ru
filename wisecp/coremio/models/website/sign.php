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

        public function isNewsletter($type='',$content=''){
            $this->db_start();
            $sth = $this->db->select("id")->from("newsletters")->where("type","=",$type,"&&")->where("content","=",$content)->build();
            return $sth;
        }

        public function DeleteNewsletter($type='',$content=''){
            $this->db_start();
            $sth = $this->db->delete("newsletters")->where("type","=",$type,"&&")->where("content","=",$content)->run();
            return $sth;
        }

        public function register($data=[]){
            $this->db_start();
            $db = $this->db;
            if(sizeof($data)>0){
                $insert = $db->insert("users",$data);
                if(!$insert)
                    return false;
                else
                    return $db->lastID();
            }else
                return false;
        }

        public function get_user_info($email=''){
            $this->db_start();
            $db = $this->db;
            $sth = $db->select("id")->from("users");
            $sth->where("type","=","member","&&");
            $sth->where("email","=",$email);
            return ($sth->build()) ? $sth->getObject() : false;
        }

        public function get_custom_fields($lang=''){
            $stmt   = $this->db->select()->from("users_custom_fields");
            $stmt->where("status","=","active","&&");
            $stmt->where("signForm","=","1","&&");
            $stmt->where("lang","=",$lang);
            $stmt->order_by("rank ASC");
            return $stmt->build() ? $stmt->fetch_assoc() : false;
        }

        public function get_custom_field($id=0){
            $stmt   = $this->db->select()->from("users_custom_fields");
            $stmt->where("status","=","active","&&");
            $stmt->where("signForm","=","1","&&");
            $stmt->where("id","=",$id);
            return $stmt->build() ? $stmt->getAssoc() : false;
        }

    }