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


        public function addPicture($name = '',$owner_id=0){
            if($name == '' || $owner_id == 0)
                return false;
            $this->db_start();
            $sth = $this->db->insert("pictures",[
                'owner_id' => $owner_id,
                'owner' => "customer_feedback",
                'reason' => "image",
                'name' => $name,
            ]);
            return $sth;
        }

        public function addFeedback($data=[]){
            $sth = $this->db->insert("customer_feedbacks",$data);
            return ($sth) ? $this->db->lastID() : false;
        }

        public function addFeedback_lang($data=[]){
            $sth = $this->db->insert("customer_feedbacks_lang",$data);
            return ($sth) ? $this->db->lastID() : false;
        }

    }