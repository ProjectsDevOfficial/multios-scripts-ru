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

        public function set_address($id=0,$data=[]){
            return $this->db->update("users_addresses",$data)->where("id","=",$id)->save();
        }

        public function check_ActivOrder($uid=0){
            $stmt   = $this->db->select("id")->from("users_products");
            $stmt->where("status","=","active","&&");
            $stmt->where("owner_id","=",$uid);
            return $stmt->build() ? $stmt->getObject()->id : false;
        }

        public function check_lastActivOrder($uid=0){
            $stmt   = $this->db->select("id")->from("users_products");
            $stmt->where("status","=","active","&&");
            $stmt->where("owner_id","=",$uid,"&&");
            $stmt->where("DATE_FORMAT(CURDATE(), cdate) - INTERVAL 3 MONTH");
            return $stmt->build() ? $stmt->getObject()->id : false;
        }

        public function check_applyonce_coupon($cid=0,$uid=0){
            $stmt   = $this->db->select("id")->from("invoices");
            $stmt->where("user_id","=",$uid,"&&");
            $stmt->where("FIND_IN_SET(".$cid.",used_coupons)");
            return $stmt->build() ? $stmt->getObject()->id : false;
        }

        public function check_applyonce_promotion($pid=0,$uid=0){
            $stmt   = $this->db->select("id")->from("invoices");
            $stmt->where("user_id","=",$uid,"&&");
            $stmt->where("FIND_IN_SET(".$pid.",used_promotions)");
            return $stmt->build() ? $stmt->getObject()->id : false;
        }

        public function header_background(){
            $sth = $this->db->select("name")->from("pictures");
            $sth->where("owner","=","basket","&&");
            $sth->where("reason","=","header-background");
            $sth->order_by("id DESC");
            return $sth->build() ? $sth->getObject()->name : false;
        }

        public function coupon_check($code='',$id=0){
            $sth = $this->db->select()->from("coupons");
            if($code == NULL) $sth->where("id","=",$id,"&&");
            else $sth->where("code","=",$code,"&&");
            $sth->where("status","=","active","&&");
            $sth->where("(");
            $sth->where("maxuses","=","0","||");
            $sth->where("(");
            $sth->where("maxuses","!=","0","&&");
            $sth->where("uses","< maxuses");
            $sth->where(")");
            $sth->where(")","","","&&");

            $sth->where("(");
            $sth->where("(");
            $sth->where("duedate","!=",DateManager::ata(),"&&");
            $sth->where("duedate",">",DateManager::Now());
            $sth->where(")","","","||");
            $sth->where("duedate","=",DateManager::ata());
            $sth->where(")");
            return $sth->build() ? $sth->getAssoc() : false;
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