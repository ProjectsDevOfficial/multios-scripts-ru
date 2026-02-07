<?php
    defined('CORE_FOLDER') OR exit('You can not get in here!');
    class Coupon
    {
        public static string $message;
        private static array $temp;

        /**
         * Retrieves a coupon based on the provided code or id.
         *
         * @param string|null $code The coupon code. Default is NULL.
         * @param int|null $id The coupon id. Default is NULL.
         * @return array|false Returns the coupon data as an associative array if found, otherwise false.
         */
        public static function get($code=NULL, $id=NULL)
        {
            $coupon = WDB::select()->from("coupons");
            if($id != NULL)
                $coupon->where("id","=",$id);
            else
                $coupon->where("code","=",$code);

            return $coupon->build() ? $coupon->getAssoc() : false;
        }

        /**
         * Validates the given coupon.
         *
         * @param array|null $coupon The coupon data that needs to be validated.
         * @param int $user_id
         * @return bool Returns false if the coupon is invalid and sets an error message, otherwise true.
         */
        public static function validate(array|null $coupon=[], int $user_id=0): bool
        {
            try {
                $duedate_f4         = substr($coupon['duedate'],0,4);
                $duedate_onetime    = $duedate_f4 == "0000" || $duedate_f4 == "1970" && $duedate_f4 == "1881";
                $duedate_mt         = $duedate_onetime ? 0 : DateManager::strtotime($coupon["duedate"]);
                $now_mt             = DateManager::strtotime();

                if($coupon["status"] != "active")
                    throw new Exception("Coupon status is not active");

                if($coupon["maxuses"] != 0 && $coupon["uses"] >= $coupon["maxuses"])
                    throw new Exception("Coupon has reached maximum uses");

                if(!$duedate_onetime && $duedate_mt <= $now_mt)
                    throw new Exception(Bootstrap::$lang->get_cm("website/basket/error3"));

                if(!$user_id && ($coupon["applyonce"] || $coupon["newsignups"] || $coupon["existingcustomer"]))
                    throw new Exception(Bootstrap::$lang->get_cm("website/basket/error11"));

                if($coupon["applyonce"] && $user_id && self::check_apply_once($coupon))
                    throw new Exception(Bootstrap::$lang->get_cm("website/basket/error12"));

                if($coupon["newsignups"] && $user_id && self::get_number_of_active_orders_for_user($user_id) > 0)
                    throw new Exception(Bootstrap::$lang->get_cm("website/basket/error13"));

                if($coupon["existingcustomer"] && $user_id && !self::get_last_active_order_for_user($user_id))
                    throw new Exception(Bootstrap::$lang->get_cm("website/basket/error14"));


                return true;
            }
            catch(Exception $e)
            {
                self::$message = $e->getMessage();
                return false;
            }
        }

        /**
         * Checks if a coupon has been applied by a user only once.
         *
         * @param array|int $coupon The coupon details as an associative array, or the coupon id. Default is an empty array.
         * @param int $user_id The id of the user. Default is 0.
         * @return int|false Returns the id of the invoice if the coupon has been used, otherwise zero.
         */
        public static function check_apply_once($coupon=[], $user_id=0)
        {
            if(is_int($coupon)) $coupon = self::get(NULL,$coupon);
            if(!$coupon) return false;

            $stmt   = WDB::select("id")->from("invoices");
            $stmt->where("user_id","=",$user_id,"&&");
            $stmt->where("FIND_IN_SET(".$coupon["id"].",used_coupons)");
            return $stmt->build() ? $stmt->getObject()->id : 0;
        }

        /**
         * Retrieves the number of active orders for a given user.
         *
         * @param int $user_id The ID of the user. Default is 0.
         * @return int Returns the count of active orders for the specified user.
         */
        public static function get_number_of_active_orders_for_user($user_id=0)
        {
            $stmt   = WDB::select("id")->from("users_products");
            $stmt->where("status","=","active","&&");
            $stmt->where("owner_id","=",$user_id);
            return $stmt->build() ? $stmt->getCount() : 0;
        }

        /**
         * Retrieves the last active order for a specified user.
         *
         * @param int $user_id The ID of the user. Default is 0.
         * @return int|false Returns the ID of the last active order if found, otherwise false.
         */
        public static function get_last_active_order_for_user($user_id=0){
            $stmt   = WDB::select("id")->from("users_products");
            $stmt->where("status","=","active","&&");
            $stmt->where("owner_id","=",$user_id,"&&");
            $stmt->where("DATE_FORMAT(CURDATE(), cdate) - INTERVAL 3 MONTH");
            return $stmt->build() ? $stmt->getObject()->id : false;
        }

        /**
         * Counts the number of times a specific coupon has been used in orders.
         *
         * @param array $coupon An associative array containing coupon details. For example, it should have an 'id' key.
         * @param int $order_id The ID of the order to check the coupon usage for. Default is 0.
         * @return int Returns the total number of times the coupon has been used.
         */
        public static function number_of_uses(array $coupon=[], int $order_id=0): int
        {
            $stmt = WDB::select("COUNT(i.id) AS total")->from("invoices_items ii");
            $stmt->join("LEFT","invoices i","ii.owner_id=i.id");
            if($order_id > 0) $stmt->where("ii.user_pid","=",$order_id,"&&");
            $stmt->where("FIND_IN_SET(".$coupon["id"].",i.used_coupons)","","","&&");
            $stmt->where("FIND_IN_SET(i.status,'waiting,paid')");
            return $stmt->build() ? $stmt->getObject()->total : 0;
        }

        /**
         * Selects a valid renewal coupon for the specified order based on the user's invoice history.
         *
         * @param int $id The order ID for whom the coupon selection is being made. Default is 0.
         * @return array An associative array containing the selected coupon details (id, type, useCount, code, rate, amount, and currency)
         * if a valid coupon is found, otherwise an empty array.
         */
        public static function select_renewal_coupon_for_order(int $id):array
        {
            Helper::Load(["Invoices"]);
            $pfx                = Models::$init->pfx;
            // Find the coupon on invoices
            $ftCoi = WDB::query(<<<SQL
SELECT 
    JSON_UNQUOTE(JSON_EXTRACT(i.discounts, CONCAT('$.items.coupon."', ii.id, '".id'))) AS coupon_id,
    JSON_UNQUOTE(JSON_EXTRACT(i.discounts, CONCAT('$.items.coupon."', ii.id, '".name'))) AS coupon_name,
    COUNT(*) AS usage_count
FROM 
    {$pfx}invoices i
JOIN 
    {$pfx}invoices_items ii ON i.id = ii.owner_id
WHERE 
    ii.user_pid = {$id}
    AND i.discounts IS NOT NULL 
    AND JSON_CONTAINS_PATH(i.discounts, 'one', CONCAT('$.items.coupon."', ii.id, '"'))
GROUP BY 
    coupon_id, coupon_name
HAVING 
    coupon_id IS NOT NULL
ORDER BY 
    usage_count DESC, coupon_id;
SQL,true);
            $result = $ftCoi->fetch_object();
            if($result) {
                foreach($result AS $r) {
                    $c = self::get(NULL,$r->coupon_id) ?: self::get($r->coupon_name);
                    if($c && $c["status"] == "active")
                    {
                        $recurring      = $c["recurring"] ?? false;
                        $recurring_num  = $c["recurring_num"] ?? 0;
                        if($recurring && ($recurring_num == 0 || $r->usage_count < $recurring_num)) {
                            $cType      = $c["type"] ?? "percentage";
                            $cRate      = (float) $c["rate"] ?? 0;
                            $cAmount    = (float) $c["amount"] ?? 0;
                            $cCurrency  = $c["currency"] ?? 4;

                            return [
                                'id'            => $r->coupon_id,
                                'type'          => $cType,
                                'code'          => $c["code"] ?? "",
                                'useCount'      => $r->usage_count,
                                'rate'          => $cRate,
                                'amount'        => $cAmount,
                                'currency'      => $cCurrency,
                            ];
                        }
                    }
                }
            }
            return [];
        }

        /**
         * Retrieves the static message.
         *
         * @return string|null Returns the message if set, otherwise null.
         */
        public static function get_message()
        {
            return self::$message;
        }


    }