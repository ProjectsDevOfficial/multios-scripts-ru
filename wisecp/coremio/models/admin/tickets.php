<?php
    defined('CORE_FOLDER') OR exit('You can not get in here!');
    class Model extends Models
    {
        function __construct()
        {
            parent::__construct();
        }

        public function get_reply($id=0,$lang=''){
            $stmt   = $this->db->select("t1.*,t2.id AS lid,t2.name,t2.message")->from("tickets_predefined_replies AS t1");
            $stmt->join("LEFT","tickets_predefined_replies_lang AS t2","t2.owner_id=t1.id AND t2.lang='".$lang."'");
            $stmt->where("t2.id","IS NOT NULL","","&&");
            $stmt->where("t1.id","=",$id);
            return $stmt->build() ? $stmt->getAssoc() : false;
        }

        public function get_c_field($id=0){
            $ll_lang    = Config::get("general/local");
            //$sd_lang  = Bootstrap::$lang->clang;
            $stmt       = $this->db->select("c.*,cl.name")->from("tickets_custom_fields AS c");
            $stmt->join("LEFT","tickets_custom_fields_lang AS cl","cl.owner_id=c.id AND (cl.lang='".$ll_lang."')");
            $stmt->where("cl.id","IS NOT NULL","","&&");
            $stmt->where("c.id","=",$id);
            return $stmt->build() ? $stmt->getAssoc() : false;
        }

        public function get_c_field_wlang($id=0,$lang=''){
            $stmt       = $this->db->select()->from("tickets_custom_fields_lang");
            $stmt->where("lang","=",$lang,"&&");
            $stmt->where("owner_id","=",$id);
            return $stmt->build() ? $stmt->getAssoc() : false;
        }

        public function delete_category($type='',$id=0){
            if($type == "predefined_replies" && $id){
                $this->db->delete("cy,cyl","categories cy")
                    ->join("INNER","categories_lang cyl","cyl.owner_id=cy.id")
                    ->where("cy.id","=",$id)->run();

                $this->db->delete("prers,prersl","tickets_predefined_replies prers")
                    ->join("INNER","tickets_predefined_replies_lang prersl","prersl.owner_id=prers.id")
                    ->where("prers.category","=",$id)->run();
                return true;
            }
            return false;
        }

        public function delete_predefined_reply($id=0){
            return $this->db->delete("prers,prersl","tickets_predefined_replies prers")
                ->join("INNER","tickets_predefined_replies_lang prersl","prersl.owner_id=prers.id")
                ->where("prers.id","=",$id)->run();
        }

        public function insert_category($data){
            return $this->db->insert("categories",$data) ? $this->db->lastID() : false;
        }

        public function set_category($id,$data){
            return $this->db->update("categories",$data)->where("id","=",$id)->save();
        }

        public function insert_category_lang($data){
            return $this->db->insert("categories_lang",$data) ? $this->db->lastID() : false;
        }

        public function set_category_lang($id,$data){
            return $this->db->update("categories_lang",$data)->where("id","=",$id)->save();
        }

        public function insert_predefined_reply($data){
            return $this->db->insert("tickets_predefined_replies",$data) ? $this->db->lastID() : false;
        }

        public function insert_predefined_reply_lang($data){
            return $this->db->insert("tickets_predefined_replies_lang",$data) ? $this->db->lastID() : false;
        }

        public function set_predefined_reply($id,$data){
            return $this->db->update("tickets_predefined_replies",$data)->where("id","=",$id)->save();
        }

        public function set_predefined_reply_lang($id,$data){
            return $this->db->update("tickets_predefined_replies_lang",$data)->where("id","=",$id)->save();
        }

        public function get_predefined_reply_categories(){
            $lang = Config::get("general/local");
            $data = $this->db->select("t1.id,t1.parent,t2.title,t3.title AS parent_title")->from("categories AS t1");
            $data->join("LEFT","categories_lang AS t2","t2.owner_id=t1.id AND t2.lang='".$lang."'");
            $data->join("LEFT","categories_lang AS t3","t3.owner_id=t1.parent AND t3.lang='".$lang."'");
            $data->where("t2.title","IS NOT NULL","","&&");
            $data->where("t1.type","=","predefined_replies");
            $data->order_by("t1.id DESC");
            $data = $data->build() ? $data->fetch_assoc() : false;
            if($data){
                $keys   = array_keys($data);
                $size   = sizeof($keys)-1;
                for($i=0;$i<=$size;$i++){
                    $var = $data[$keys[$i]];
                    $data[$keys[$i]]["edit_link"] = Controllers::$init->AdminCRLink("tickets-2",[
                        "predefined-replies","edit"
                    ])."?id=".$var["id"];
                }
            }
            return $data;
        }

        public function get_requests($searches='',$orders=[],$start=0,$end=1){

            $show_first = Config::get("options/ticket-show-first");
            if(!$show_first) $show_first = 2;

            $member_group               = Config::get("options/ticket-member-group");
            $assigned_tickets_only      = Config::get("options/ticket-assigned-tickets-only");

            $root_admin                 = Admin::isPrivilege(["ADMIN_PRIVILEGES"]);
            $admin_data                 = UserManager::LoginData("admin");
            $admin_id                   = $admin_data["id"];



            $case = "CASE ";
            if($member_group > 0)
                $case .= "WHEN t1.status = 'waiting' AND t2.group_id = ".$member_group." THEN 0 ";
            $case .= "WHEN t1.status = 'waiting' THEN 1 ";
            $case .= "WHEN t1.status = 'process' THEN 2 ";
            $case .= "WHEN t1.status = 'replied' THEN 3 ";
            $case .= "WHEN t1.status = 'solved' THEN 4 ";
            $case .= "END AS rank";
            $select = implode(",",[
                't1.*',
                'IF(t1.name !=\'\',t1.name,t2.full_name) AS user_name',
                't2.company_name AS user_company_name',
                $case
            ]);
            $sth = $this->db->select($select)->from("tickets AS t1");

            $sth->join("LEFT","users AS t2","t2.id=t1.user_id");

            if($searches){
                if(isset($searches["user_id"]) && $searches["user_id"])
                    $sth->where("t1.user_id","=",$searches["user_id"],"&&");
                if(isset($searches["client"]) && $searches["client"])
                    $sth->where("t1.user_id","=",$searches["client"],"&&");
                if(isset($searches["department"]) && $searches["department"])
                    $sth->where("t1.did","=",$searches["department"],"&&");
                if(isset($searches["status"]) && $searches["status"])
                    $sth->where("FIND_IN_SET(t1.status,'".$searches["status"]."')","","","&&");
                if(isset($searches["cstatus"]) && $searches["cstatus"])
                    $sth->where("FIND_IN_SET(CONCAT(t1.status,'-',t1.cstatus),'".$searches["cstatus"]."')","","","&&");
                if(isset($searches["priority"]) && $searches["priority"])
                    $sth->where("t1.priority","=",$searches["priority"],"&&");
                if(isset($searches["ticket_id"]) && $searches["ticket_id"])
                    $sth->where("t1.id","=",$searches["ticket_id"],"&&");
                if(isset($searches["assigned_to"]) && $searches["assigned_to"])
                    $sth->where("t1.assigned","=",$searches["assigned_to"],"&&");


                if(isset($searches["word"])){
                    $word       = $searches["word"];
                    $date       = DateManager::datetime_format_ifin($word);
                    $rid        = (int) ltrim($word,"#");

                    $sth->where("(");

                    if ($rid) $sth->where("t1.id","=",$rid,"||");
                    $sth->where("t1.title","LIKE","%".$word."%","||");
                    $sth->where("t1.ctime","LIKE","%".$date."%","||");
                    $sth->where("t2.full_name","LIKE","%".$word."%","||");
                    $sth->where("t2.company_name","LIKE","%".$word."%","||");
                    $sth->where("t2.email","LIKE","%".$word."%","||");

                    $sth->where("t1.lastreply","LIKE","%".$date."%","");

                    $sth->where(")","","","&&");
                }
            }

            if($assigned_tickets_only && !$root_admin)
                $sth->where("t1.assigned","=",$admin_id,"&&");

            $sth->where("t1.status","!=","delete");

            $timesort = 't1.lastreply DESC';
            if($show_first == 1) $timesort = 't1.lastreply ASC';

            $sth->order_by("rank ASC,".$timesort);
            $sth->limit($start,$end);
            return $sth->build() ? $sth->fetch_assoc() : false;
        }

        public function get_requests_total($searches=[]){

            $assigned_tickets_only      = Config::get("options/ticket-assigned-tickets-only");

            $root_admin                 = Admin::isPrivilege(["ADMIN_PRIVILEGES"]);
            $admin_data                 = UserManager::LoginData("admin");
            $admin_id                   = $admin_data["id"];


            $sth = $this->db->select("t1.id")->from("tickets AS t1");
            $sth->join("LEFT","users AS t2","t2.id=t1.user_id");

            if($searches){
                if(isset($searches["user_id"]) && $searches["user_id"])
                    $sth->where("t1.user_id","=",$searches["user_id"],"&&");
                if(isset($searches["client"]) && $searches["client"])
                    $sth->where("t1.user_id","=",$searches["client"],"&&");
                if(isset($searches["department"]) && $searches["department"])
                    $sth->where("t1.did","=",$searches["department"],"&&");
                if(isset($searches["status"]) && $searches["status"])
                    $sth->where("FIND_IN_SET(t1.status,'".$searches["status"]."')","","","&&");
                if(isset($searches["cstatus"]) && $searches["cstatus"])
                    $sth->where("FIND_IN_SET(CONCAT(t1.status,'-',t1.cstatus),'".$searches["cstatus"]."')","","","&&");
                if(isset($searches["priority"]) && $searches["priority"])
                    $sth->where("t1.priority","=",$searches["priority"],"&&");
                if(isset($searches["ticket_id"]) && $searches["ticket_id"])
                    $sth->where("t1.id","=",$searches["ticket_id"],"&&");
                if(isset($searches["assigned_to"]) && $searches["assigned_to"])
                    $sth->where("t1.assigned","=",$searches["assigned_to"],"&&");

                if(isset($searches["word"])){
                    $word       = $searches["word"];
                    $date       = DateManager::datetime_format_ifin($word);
                    $rid        = (int) ltrim($word,"#");

                    $sth->where("(");

                    if ($rid) $sth->where("t1.id","=",$rid,"||");
                    $sth->where("t1.title","LIKE","%".$word."%","||");
                    $sth->where("t1.ctime","LIKE","%".$date."%","||");

                    $sth->where("t2.full_name","LIKE","%".$word."%","||");
                    $sth->where("t2.company_name","LIKE","%".$word."%","||");
                    $sth->where("t2.email","LIKE","%".$word."%","||");

                    $sth->where("t1.lastreply","LIKE","%".$date."%","");

                    $sth->where(")","","","&&");
                }
            }

            if($assigned_tickets_only && !$root_admin)
                $sth->where("t1.assigned","=",$admin_id,"&&");

            $sth->where("t1.status","!=","delete");
            return $sth->build() ? $sth->rowCounter() : 0;
        }

        public function set_ticket($id=0,$data=[]){
            return $this->db->update("tickets",$data)->where("id","=",$id)->save();
        }

        public function insert_custom_field($data=[]){
            return $this->db->insert("tickets_custom_fields",$data) ? $this->db->lastID() : false;
        }

        public function insert_custom_field_lang($data=[]){
            return $this->db->insert("tickets_custom_fields_lang",$data) ? $this->db->lastID() : false;
        }

        public function set_custom_field($id=0,$data=[]){
            return $this->db->update("tickets_custom_fields",$data)->where("id","=",$id)->save();
        }

        public function set_custom_field_lang($id=0,$data=[]){
            return $this->db->update("tickets_custom_fields_lang",$data)->where("id","=",$id)->save();
        }

        public function delete_custom_field($id=0){
            $this->db->delete("tickets_custom_fields")->where("id","=",$id)->run();
            $this->db->delete("tickets_custom_fields_lang")->where("owner_id","=",$id)->run();
            return true;
        }

        public function user_groups(){
            return $this->db->select()->from("users_groups")->build() ? $this->db->fetch_assoc() : false;
        }

        public function log_list($id,$searches=[],$orders=[],$start=0,$end=1)
        {
            $format_convert = str_replace(['d','m','Y',],['%d','%m','%Y'],Config::get("options/date-format"));
            $sth = $this->db->select("t1.id,t1.owner_id,t1.data,t1.detail,t1.locale_detail,t1.ip,DATE_FORMAT(t1.ctime,'".$format_convert." %H:%i') AS date,t2.full_name AS user_name,t2.type AS user_type")->from("users_actions AS t1");
            $sth->join("LEFT","users AS t2","t1.owner_id!=0 AND t1.owner_id=t2.id");

            if($searches){
                if(isset($searches["word"])){
                    $word       = $searches["word"];
                    $date       = DateManager::datetime_format_ifin($word);
                    $sth->where("(");
                    $sth->where("t1.detail","LIKE","%".$word."%","||");
                    $sth->where("t1.locale_detail","LIKE","%".$word."%","||");
                    $sth->where("t1.ip","LIKE","%".$word."%","||");
                    $sth->where("t1.ctime","LIKE","%".$date."%","||");


                    $sth->where("(");
                    $sth->where("t2.full_name","LIKE","%".$word."%","||");
                    $sth->where("t2.email","LIKE","%".$word."%");
                    $sth->where(")","","");

                    $sth->where(")","","","&&");
                }
            }


            $sth->where("(");
            $sth->where("JSON_UNQUOTE(JSON_EXTRACT(t1.data,'$.id'))","LIKE",$id,"||");
            $sth->where("JSON_UNQUOTE(JSON_EXTRACT(t1.data,'$.ticket_id'))","LIKE",$id);
            $sth->where(")","","","&&");

            $sth->where("(");
            $sth->where("t1.detail","=","ticket-has-been-resolved","||");
            $sth->where("t1.detail","=","has-been-created-ticket","||");
            $sth->where("t1.detail","=","replied-to-ticket","||");
            $sth->where("t1.detail","=","assign-ticket-request","||");
            $sth->where("t1.detail","=","reply-ticket-request","||");
            $sth->where("t1.detail","=","changed-ticket-request","||");
            $sth->where("t1.detail","=","added-new-ticket-custom-field");
            $sth->where(")");

            $sth->order_by("t1.id DESC");
            $sth->limit($start,$end);
            return $sth->build() ? $sth->fetch_assoc() : false;
        }

        public function log_list_total($id,$searches=[])
        {
            $sth = $this->db->select("t1.id")->from("users_actions AS t1");
            $sth->join("LEFT","users AS t2","t1.owner_id!=0 AND t1.owner_id=t2.id");
            if($searches){
                if(isset($searches["word"])){
                    $word       = $searches["word"];
                    $date       = DateManager::datetime_format_ifin($word);
                    $sth->where("(");
                    $sth->where("t1.detail","LIKE","%".$word."%","||");
                    $sth->where("t1.locale_detail","LIKE","%".$word."%","||");
                    $sth->where("t1.ip","LIKE","%".$word."%","||");
                    $sth->where("t1.ctime","LIKE","%".$date."%","||");


                    $sth->where("(");
                    $sth->where("t2.full_name","LIKE","%".$word."%","||");
                    $sth->where("t2.email","LIKE","%".$word."%");
                    $sth->where(")","","");

                    $sth->where(")","","","&&");
                }
            }

            $sth->where("(");
            $sth->where("JSON_UNQUOTE(JSON_EXTRACT(t1.data,'$.id'))","LIKE",$id,"||");
            $sth->where("JSON_UNQUOTE(JSON_EXTRACT(t1.data,'$.ticket_id'))","LIKE",$id);
            $sth->where(")","","","&&");

            $sth->where("(");
            $sth->where("t1.detail","=","ticket-has-been-resolved","||");
            $sth->where("t1.detail","=","has-been-created-ticket","||");
            $sth->where("t1.detail","=","replied-to-ticket","||");
            $sth->where("t1.detail","=","assign-ticket-request","||");
            $sth->where("t1.detail","=","reply-ticket-request","||");
            $sth->where("t1.detail","=","changed-ticket-request","||");
            $sth->where("t1.detail","=","added-new-ticket-custom-field");
            $sth->where(")");

            return $sth->build() ? $sth->rowCounter() : 0;
        }

        public function statuses()
        {
            Helper::Load("Tickets");
            return Tickets::custom_statuses();
        }

        public function insert_status($data=[])
        {
            return $this->db->insert("tickets_statuses",$data) ? $this->db->lastID() : 0;
        }

        public function save_status($id=0,$data=[])
        {
            return $this->db->update("tickets_statuses",$data)->where("id","=",$id)->save();
        }

        public function save_status_lang($id=0,$lang='',$name='')
        {
            $get = $this->db->select()->from("tickets_statuses_lang")->where("owner_id","=",$id,"&&")->where("lang","=",$lang);
            if($get->build())
            {
                $data_id = $get->getObject()->id;
                return $this->db->update("tickets_statuses_lang",['name' => $name])->where("id","=",$data_id)->save();
            }
            else
                return $this->db->insert("tickets_statuses_lang",[
                    'owner_id'  => $id,
                    'lang'      => $lang,
                    'name'      => $name,
                ]);
        }

        public function remove_status($id=0)
        {
            $t1 = $this->db->delete("tickets_statuses")->where("id","=",$id)->run();
            $t2 = $this->db->delete("tickets_statuses_lang")->where("owner_id","=",$id)->run();
            return $t1 && $t2;
        }

        public function get_tasks($searches='',$orders=[],$start=0,$end=1){
            $stmt       = $this->db->select()->from("tickets_tasks");

            if($searches){
                if(isset($searches["word"]) && $searches["word"]){
                    $stmt->where("(");
                    $stmt->where("name","LIKE","%".$searches["word"]."%");
                    $stmt->where(")");
                }
            }

            $stmt->order_by("id DESC");
            $stmt->limit($start,$end);
            return $stmt->build() ? $stmt->fetch_assoc() : false;
        }
        public function get_tasks_total($searches='',$nosearch=false){
            $select     = "COUNT(id) AS total";
            $stmt       = $this->db->select($select)->from("tickets_tasks");

            if($searches){
                if(isset($searches["word"]) && $searches["word"]){
                    $stmt->where("(");
                    $stmt->where("name","LIKE","%".$searches["word"]."%");
                    $stmt->where(")");
                }
            }

            return $stmt->build() ? $stmt->getObject()->total : 0;
        }



    }