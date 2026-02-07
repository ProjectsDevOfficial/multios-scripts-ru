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

        public function get_total_messages($id=0,$searches=[]){
            $sth = $this->db->select("COUNT(id) AS total")->from("mail_logs");
            $sth->where("user_id","=",$id,"&&");
            if($searches){
                if(isset($searches["word"])){
                    $word       = $searches["word"];
                    $date       = DateManager::datetime_format_ifin($word);
                    $sth->where("(");
                    $sth->where("subject","LIKE","%".$word."%","||");
                    $sth->where("content","LIKE","%".$word."%","||");
                    $sth->where("ctime","LIKE","%".$date."%");
                    $sth->where(")","","","&&");
                }
            }
            $sth->where("private","=",0);
            return $sth->build() ? $sth->getObject()->total : false;
        }

        public function get_message($id=0,$uid=0){
            $format_convert = str_replace(['d','m','Y',],['%d','%m','%Y'],Config::get("options/date-format"));
            $sth = $this->db->select("id,subject,content,addresses,DATE_FORMAT(ctime,'".$format_convert." %H:%i') AS date")->from("mail_logs");
            $sth->where("user_id","=",$uid,"&&");
            $sth->where("id","=",$id,"&&");
            $sth->where("private","=",0);
            return $sth->build() ? $sth->getAssoc() : false;
        }

        public function get_messages($id=0,$searches=[],$orders=[],$start=0,$end=1){
            $format_convert = str_replace(['d','m','Y',],['%d','%m','%Y'],Config::get("options/date-format"));
            $sth = $this->db->select("id,subject,content,DATE_FORMAT(ctime,'".$format_convert." %H:%i') AS date")->from("mail_logs");
            $sth->where("user_id","=",$id,"&&");
            if($searches){
                if(isset($searches["word"])){
                    $word       = $searches["word"];
                    $date       = DateManager::datetime_format_ifin($word);
                    $sth->where("(");
                    $sth->where("subject","LIKE","%".$word."%","||");
                    $sth->where("content","LIKE","%".$word."%","||");
                    $sth->where("ctime","LIKE","%".$date."%");
                    $sth->where(")","","","&&");
                }
            }
            $sth->where("private","=",0);
            if($orders) $sth->order_by(implode(",",$orders));
            else $sth->order_by("id DESC");
            $sth->limit($start,$end);
            return $sth->build() ? $sth->fetch_assoc() : false;
        }

    }