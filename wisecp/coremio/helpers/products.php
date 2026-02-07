<?php
    defined('CORE_FOLDER') or exit('You can not get in here!');

    class Products
    {
        static $error;
        private static $storage;
        private static $model_init;
        private static $updowngrade_users = [];

        private static function model()
        {
            if (!self::$model_init) self::$model_init = new Products_Model();
            return self::$model_init;
        }


        static function special_groups($lang = '', $select = '')
        {
            if (!$lang) $lang = Bootstrap::$lang->clang;
            if (!$select) $select = "t1.id,t1.options,t2.title,t2.sub_title,t2.route,t2.options AS optionsl";
            $stmt = Models::$init->db->select($select)->from("categories AS t1");
            $stmt->join("LEFT", "categories_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
            $stmt->where("t2.id", "IS NOT NULL", '', "&&");
            $stmt->where("t1.type", "=", "products", "&&");
            $stmt->where("t1.kind", "=", "special", "&&");
            $stmt->where("t1.parent", "=", "0", "&&");
            $stmt->where("t1.status", "=", "active", "&&");
            $stmt->where("t1.visibility", "=", "visible");
            $stmt->order_by("t1.rank ASC");
            return $stmt->build() ? $stmt->fetch_assoc() : [];
        }


        static function getCategoryName($id = 0, $lang = '')
        {
            $stmt = Models::$init->db->select("title")->from("categories_lang");
            $stmt->where("owner_id", "=", $id, "&&");
            $stmt->where("lang", "=", $lang);
            return $stmt->build() ? $stmt->getObject()->title : false;
        }


        static function get_price($type, $owner, $owner_id, $lang = 'none')
        {
            return self::model()->get_price($type, $owner, $owner_id, $lang);
        }


        static function get_prices($type, $owner, $owner_id, $lang = 'none')
        {
            return self::model()->get_prices($type, $owner, $owner_id, $lang);
        }


        static function set_price($id = 0, $data = [])
        {
            return Models::$init->db->update("prices", $data)->where("id", "=", $id)->save();
        }


        static function getCategory($category, $lang = '', $select = '')
        {
            $lang = !$lang ? Bootstrap::$lang->clang : $lang;
            return self::model()->getCategory($category, $lang, $select);
        }


        static function get_info_by_fields($type = '', $id = 0, $fields = [], $lang = '')
        {
            if ($type == "software") {
                return false;
            } else {
                $select = implode(",", $fields);
                $sth = Models::$init->db->select($select)->from("products AS t1");
                $sth->join("LEFT", "products_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
                $sth->where("t2.id", "IS NOT NULL", "", "&&");
                $sth->where("t1.id", "=", $id);
                return $sth->build() ? $sth->getAssoc() : false;
            }
        }


        static function get($type = '', $id = 0, $lang = '', $status = '')
        {
            $lang = $lang == '' ? Bootstrap::$lang->clang : $lang;
            $type = Filter::init($type, "letters_numbers", "_");

            if ($type == "domain") $id = Filter::letters_numbers($id, ".");
            else $id = Filter::init($id, "numbers");

            if (isset(self::$storage["get"][$type][$id][$lang][$status]))
                return self::$storage["get"][$type][$id][$lang][$status];

            $data = false;
            if ($type && $id !== 0 && $id !== '') {

                if ($type == "software") {
                    $data = self::model()->get_software($id, $lang, $status);
                    if ($data) {
                        $data["type"] = "software";
                        $data["type_id"] = 0;
                        $data["options"] = $data["options"] == '' ? [] : Utility::jdecode($data["options"], true);
                        $data["optionsl"] = $data["optionsl"] == '' ? [] : Utility::jdecode($data["optionsl"], true);
                        $data["route"] = Controllers::$init->CRLink("software_detail", [$data["route"]]);
                        $data["category_id"] = $data["category"];
                        $data["category_route"] = Controllers::$init->CRLink("softwares_cat", [$data["category_route"]]);
                        $data["price"] = self::model()->get_prices("periodicals", "softwares", $data["id"], $lang);
                    }
                } elseif ($type == "domain") {
                    $data = self::model()->get_tld($id);
                    if ($data && !is_bool($status) && strlen($status) > 2 && $data["status"] != $status) $data = false;
                    if ($data) {
                        $data["type"] = "domain";
                        $data["type_id"] = 0;
                        $data["category"] = 0;
                        $data["category_id"] = 0;
                        $data["price"]["register"] = self::model()->get_price("register", "tld", $data["id"], $lang);
                        $data["price"]["renewal"] = self::model()->get_price("renewal", "tld", $data["id"], $lang);
                        $data["price"]["transfer"] = self::model()->get_price("transfer", "tld", $data["id"], $lang);
                    }
                } elseif ($type == "hosting" || $type == "server" || $type == "sms" || $type == "special") {
                    $data = self::model()->get($id, $lang, $type);
                    if ($data) {
                        $data["type"] = $type;
                        $data["module_data"] = $data["module_data"] == '' ? [] : Utility::jdecode($data["module_data"], true);
                        $data["options"] = $data["options"] == '' ? [] : Utility::jdecode($data["options"], true);
                        $data["optionsl"] = $data["optionsl"] == '' ? [] : Utility::jdecode($data["optionsl"], true);
                        if ($type == "sms") {
                            $data["category_id"] = 0;
                            $data["category_title"] = Bootstrap::$lang->get_cm("website/products/category-sms", false, $lang);
                            $data["category_route"] = Controllers::$init->CRLink("products", ["sms"]);
                            $data["price"] = self::model()->get_prices("sale", "products", $data["id"], $lang);
                        } else {
                            $Category = self::getCategory($data["category"], $lang);
                            if ($Category) {
                                $data["category_id"] = $Category["id"];
                                $data["category_title"] = $Category["title"];
                                $data["category_route"] = Controllers::$init->CRLink("products", [$Category["route"]]);
                            }
                            $data["price"] = self::model()->get_prices("periodicals", "products", $data["id"], $lang);
                        }
                    }
                }
            }
            self::$storage["get"][$type][$id][$lang][$status] = $data;
            return $data;
        }


        static function product_maps_info($string = '', $lang = '', $status = '')
        {
            $split = explode("/", $string);
            $type = $split[0];

            if ($type == "allOf") {
                $kind = $split[1];
                if ($kind == "special")
                    $data = [
                        'type' => "all",
                        'data' => self::getCategory($split[2], $lang),
                    ];
                else
                    $data = [
                        'type' => "all",
                        'data' => [
                            'title' => Bootstrap::$lang->get_cm("admin/financial/new-coupon-product-group-" . $kind, false, $lang),
                        ],
                    ];
            } elseif ($type == "category") {
                $kind = $split[1];

                if ($kind == "special")
                    $data = [
                        'type' => "category",
                        'data' => self::getCategory($split[3], $lang),
                    ];
                else
                    $data = [
                        'type' => "category",
                        'data' => self::getCategory($split[2], $lang),
                    ];
            } elseif ($type == "product")
                $data = [
                    'type'         => "product",
                    'product_type' => $split[1],
                    'product_id'   => $split[3],
                    'data'         => self::get($split[1], $split[3], $lang, $status),
                ];

            return $data;
        }


        static function apply_promotion($promotion = [], $price = 0, $cid = 0)
        {
            $promo_type = $promotion["type"];
            if ($promo_type == "free")
                $price = 0;
            elseif ($promo_type == "percentage") {
                $discount_amount = Money::get_discount_amount($price, $promotion["rate"]);
                if (($price - $discount_amount) <= 0)
                    $price = 0;
                else
                    $price -= $discount_amount;
            } elseif ($promo_type == "amount") {
                $exchange = Money::exChange($promotion["amount"], $promotion["currency"], $cid);
                if (($price - $exchange) <= 0)
                    $price = 0;
                else
                    $price -= $exchange;
            }
            return $price;
        }


        static function get_promotions_for_product($type = '', $id = 0, $period1 = false, $time1 = false)
        {
            $product = self::get($type, $id);
            if ($product) {
                $maps = self::get_product_maps($product);
                if (!$maps) return false;
                $maps_size = sizeof($maps) - 1;

                $stmt = self::model()->db->select()->from("promotions");
                $stmt->where("status", "=", "active", "&&");

                $stmt->where("(");
                foreach ($maps as $k => $v) $stmt->where("primary_product", "LIKE", "%" . $v . "%", $maps_size == $k ? '' : "||");
                $stmt->where(")", "", "", "&&");


                $stmt->where("(");
                $stmt->where("period1", "=", "", "||");
                $stmt->where("period1", "IS NULL", "", "||");
                $stmt->where("(");
                $stmt->where("period1", "=", $period1, "&&");
                $stmt->where("period_time1", "=", $time1);
                $stmt->where(")");
                $stmt->where(")", "", "", "&&");


                $stmt->where("(");
                $stmt->where("maxuses", "=", "0", "||");
                $stmt->where("(");
                $stmt->where("maxuses", "!=", "0", "&&");
                $stmt->where("uses", "< maxuses");
                $stmt->where(")");
                $stmt->where(")", "", "", "&&");

                $stmt->where("(");

                $stmt->where("(");
                $stmt->where("duedate", "!=", DateManager::ata(), "&&");
                $stmt->where("duedate", ">", DateManager::Now());
                $stmt->where(")", "", "", "||");

                $stmt->where("duedate", "=", DateManager::ata());
                $stmt->where(")");

                $data = $stmt->build() ? $stmt->fetch_assoc() : false;

                if ($data) {
                    $new_data = [];
                    foreach ($data as $datum) $new_data[$datum["id"]] = $datum;
                    $data = $new_data;
                }
                return $data;
            } else {
                return false;
            }
        }


        static function get_product_promotional($type = '', $id = '', $period2 = false, $period_time2 = false)
        {
            $product = self::get($type, $id);
            if ($product) {
                $maps = self::get_product_maps($product);
                if (!$maps) return false;
                $maps_size = sizeof($maps) - 1;

                $stmt = self::model()->db->select()->from("promotions");
                $stmt->where("status", "=", "active", "&&");

                if ($period2 && $period_time2) {
                    $stmt->where("period2", "=", $period2, "&&");
                    $stmt->where("period_time2", "=", $period_time2, "&&");
                }

                $stmt->where("(");
                foreach ($maps as $k => $v) $stmt->where("product", "=", $v, $maps_size == $k ? '' : "||");
                $stmt->where(")", "", "", "&&");

                $stmt->where("(");
                $stmt->where("maxuses", "=", "0", "||");
                $stmt->where("(");
                $stmt->where("maxuses", "!=", "0", "&&");
                $stmt->where("uses", "< maxuses");
                $stmt->where(")");
                $stmt->where(")", "", "", "&&");

                $stmt->where("(");

                $stmt->where("(");
                $stmt->where("duedate", "!=", DateManager::ata(), "&&");
                $stmt->where("duedate", ">", DateManager::Now());
                $stmt->where(")", "", "", "||");

                $stmt->where("duedate", "=", DateManager::ata());
                $stmt->where(")");

                $data = $stmt->build() ? $stmt->fetch_assoc() : false;

                if ($data) {
                    $new_data = [];
                    foreach ($data as $datum) $new_data[$datum["id"]] = $datum;
                    $data = $new_data;
                }

                return $data;
            }
        }


        static function find_products_in_coupon($maps = [])
        {
            if (!is_array($maps)) $maps = explode(",", $maps);
            $products = [];

            if ($maps) {
                foreach ($maps as $map) {
                    $split = explode("/", $map);
                    $type = isset($split[0]) ? $split[0] : false;
                    $group = isset($split[1]) ? $split[1] : false;
                    $id = isset($split[2]) ? $split[2] : false;
                    $id2 = isset($split[3]) ? $split[3] : false;

                    if ($type == "allOf" && $group == "special" && $id) {
                        $gps = Models::$init->db->select("id")->from("products");
                        $gps->where("type", "=", "special", "&&");
                        $gps->where("type_id", "=", $id);
                        $gps = $gps->build() ? $gps->fetch_assoc() : [];
                        if ($gps) foreach ($gps as $p) $products[$group . "-" . $id][$p["id"]] = true;
                    } elseif ($type == "allOf") {
                        if ($group == "domain") {
                            $gps = Models::$init->db->select("id")->from("tldlist");
                            $gps = $gps->build() ? $gps->fetch_assoc() : [];
                            if ($gps) foreach ($gps as $p) $products[$group][$p["id"]] = true;
                        } else {
                            $gps = Models::$init->db->select("id")->from($group == "software" ? "pages" : "products");
                            $gps->where("type", "=", $group);
                            $gps = $gps->build() ? $gps->fetch_assoc() : [];
                            if ($gps) foreach ($gps as $p) $products[$group][$p["id"]] = true;
                        }
                    } elseif ($type == "category" && $group == "special" && $id && $id2) {
                        $ids = self::get_sub_category_ids($id2, true);
                        $ids = $ids ? implode(",", $ids) : '';
                        if ($ids) {
                            $cps = Models::$init->db->select("id")->from("products");
                            $cps->where("type", "=", "special", "&&");
                            $cps->where("type_id", "=", $id, "&&");
                            $cps->where("FIND_IN_SET(category,'" . $ids . "')");
                            $cps = $cps->build() ? $cps->fetch_assoc() : [];
                            if ($cps) foreach ($cps as $p) $products[$group . "-" . $id][$p["id"]] = true;
                        }
                    } elseif ($type == "category") {
                        $ids = self::get_sub_category_ids($id, true);
                        $ids = $ids ? implode(",", $ids) . "," . $id : $id;
                        if ($ids) {
                            $cps = Models::$init->db->select("id")->from($group == "software" ? "pages" : "products");
                            $cps->where("type", "=", $group, "&&");
                            $cps->where("FIND_IN_SET(category,'" . $ids . "')");
                            $cps = $cps->build() ? $cps->fetch_assoc() : [];
                            if ($cps) foreach ($cps as $p) $products[$group][$p["id"]] = true;
                        }
                    } elseif ($type == "product" && $group == "special" && $id) $products[$group . "-" . $id][$id2] = true;
                    elseif ($type == "product" && $id && $id2) $products[$group][$id2] = true;
                    elseif ($type == "product" && $group == "domain") $products[$group][$id2] = true;
                    elseif ($type == "product") $products[$group][$id2] = true;
                }
            }
            return $products;
        }

        static function find_in_rates($product=[],$d_rates=[],$o_quantity=0,$lang='')
        {
            if(!$lang) $lang = Bootstrap::$lang->clang;

            $c_p_ids        = $product["category"] ? self::get_parent_categories_id($product["category"]) : [];
            $find           = [];
            $returnData     = [];

            if(isset($d_rates["default"]))
            {
                $find = $d_rates["default"];
                $returnData["k"] = "default";
                $returnData["name"] = Bootstrap::$lang->get_cm("website/basket/all-of-products",false,$lang);
            }

            if(isset($d_rates[$product["type"]])){
                $find = $d_rates[$product["type"]];
                $returnData["k"] = $product["type"];
                $returnData["name"] = Bootstrap::$lang->get_cm("website/account_products/product-type-names/".$product["type"],false,$lang);
            }

            if(isset($d_rates[$product["type"]."/".$product["type_id"]]))
            {
                $find = $d_rates[$product["type"]."/".$product["type_id"]];
                $returnData["k"] = $product["type"]."/".$product["type_id"];
                $returnData["name"] = self::getCategoryName($product["type_id"],$lang);
            }

            if($c_p_ids)
            {
                foreach($c_p_ids AS $c_p_id)
                {
                    if(isset($d_rates[$product["type"]."/".$c_p_id]))
                    {
                        $find = $d_rates[$product["type"]."/".$c_p_id];
                        $returnData["k"] = $product["type"]."/".$c_p_id;
                        $returnData["name"] = self::getCategoryName($c_p_id,$lang);
                    }
                }
            }

            if(isset($d_rates[$product["type"]."/".$product["category"]]))
            {
                $find = $d_rates[$product["type"]."/".$product["category"]];
                $returnData["k"] = $product["type"]."/".$product["category"];
                $returnData["name"] = self::getCategoryName($product["category"],$lang);
            }

            if(isset($d_rates[$product["type"]."-".$product["id"]]))
            {
                $find = $d_rates[$product["type"]."-".$product["id"]];
                $returnData["k"] = $product["type"]."-".$product["id"];
                $returnData["name"] = $product["title"];
            }
            if($find)
            {
                $first = current($find);
                $first_from = $first["from"] ?? 0;
                if($first_from > 0 && $o_quantity > $first_from) $o_quantity -= $first_from;

                foreach($find AS $i)
                    if($o_quantity >= $i["from"] && ($o_quantity <= $i["to"] || $i["to"] == 0))
                        $returnData["rate"] = $i["rate"];
                if(end($find)["to"] == 0 || (!isset($returnData["rate"]) && end($find)["to"] <= $o_quantity))
                    $returnData["rate"] = end($find)["rate"];
                if(!isset($returnData["rate"])) $returnData = [];
            }

            if($returnData) $returnData["o_quantity"] = $o_quantity;

            return $returnData;
        }

        static function get_product_maps($product = [])
        {
            $type = $product["type"];
            $id = $product["id"];
            $maps = [];
            if ($type == "special") $maps[] = "allOf/special/" . $product["type_id"];
            else $maps[] = "allOf/" . $type;

            if ($product["category"] != 0 && $product["category"] != $product["type_id"]) {
                $category_ids = self::get_parent_categories_id($product["category"]);
                if ($category_ids) {
                    foreach ($category_ids as $cat_id) {
                        if ($type == "special")
                            $maps[] = "category/" . $type . "/" . $product["type_id"] . "/" . $cat_id;
                        else
                            $maps[] = "category/" . $type . "/" . $cat_id;
                    }
                }
            }

            if ($type == "special")
                $maps[] = "product/" . $type . "/" . $product["type_id"] . "/" . $id;
            else
                $maps[] = "product/" . $type . "/" . $product["category"] . "/" . $id;

            return $maps;
        }


        static function get_parent_categories_id($id = 0, $cats = [])
        {
            if ($id) {
                $stmt = self::model()->db->select("parent,id")->from("categories");
                $stmt->where("id", "=", $id);
                if (!$stmt->build()) return false;
                $stmt = $stmt->getAssoc();
                $cats[] = $stmt["id"];
                if ($stmt["parent"]) return self::get_parent_categories_id($stmt["parent"], $cats);
                return $cats;
            }
        }

        static function get_sub_category_ids($id = 0, $first = false)
        {
            $cats = [];
            if ($id) {
                if ($first) $cats[] = $id;
                $stmt = self::model()->db->select("id,parent")->from("categories");
                $stmt->where("parent", "=", $id);
                if (!$stmt->build()) return false;
                $stmt->order_by("rank ASC");
                $stmt = $stmt->fetch_assoc();
                foreach ($stmt as $row) {
                    $cats[] = $row["id"];
                    $get_sub_cats = self::get_sub_category_ids($row["id"]);
                    if ($get_sub_cats) $cats = array_merge($cats, $get_sub_cats);
                }
            }
            return $cats;
        }


        static function get_parent_categories_breadcrumb($id = 0, $lang = '', $cats = [])
        {
            $model = self::model();
            $stmt = $model->db->select("t1.id,t1.parent,t2.title,t2.route")->from("categories AS t1");
            $stmt->join("LEFT", "categories_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
            $stmt->where("t2.id", "IS NOT NULL", "", "&&");
            $stmt->where("t1.id", "=", $id);
            if (!$stmt->build()) return false;
            $stmt = $stmt->getAssoc();
            $cats[] = [
                'title' => $stmt["title"],
                'link'  => Controllers::$init->CRLink("products", [$stmt["route"]], $lang),
            ];
            if ($stmt["parent"]) return self::get_parent_categories_breadcrumb($stmt["parent"], $lang, $cats);
            return $cats;
        }


        static function get_sub_categories_breadcrumb($type = '', $id = 0, $lang = '', $left_title = '')
        {
            $cats = [];
            $model = self::model();
            $stmt = $model->db->select("t1.id,t1.parent,t2.title,t2.route")->from("categories AS t1");
            $stmt->join("LEFT", "categories_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
            $stmt->where("t2.id", "IS NOT NULL", "", "&&");
            if ($type == "software")
                $stmt->where("t1.type", "=", $type, "&&");
            else {
                $stmt->where("t1.type", "=", "products", "&&");
                $stmt->where("t1.kind", "=", $type, "&&");
            }
            $stmt->where("t1.parent", "=", $id);
            $stmt->order_by("t1.rank ASC,t1.id DESC");
            if (!$stmt->build()) return false;
            $stmt = $stmt->fetch_assoc();
            if ($stmt) {
                foreach ($stmt as $row) {
                    $row["title"] = $left_title . " / " . $row["title"];
                    $cats[$row["id"]] = $row;
                    if ($new = self::get_sub_categories_breadcrumb($type, $row["id"], $lang, $row["title"])) $cats = array_merge($cats, $new);
                }
            }
            return $cats;
        }


        static function get_server($id = 0)
        {
            $model = self::model();
            $data = $model->get_server($id);
            if ($data) {
                $data["password"] = Crypt::decode($data["password"], Config::get("crypt/user"));
            }
            return $data;
        }


        static function get_server_group($id = 0)
        {
            return self::model()->get_server_group($id);
        }


        static function set($type = '', $id = 0, $set = [])
        {

            if ($type == "hosting" || $type == "server" || $type == "special" || $type == "sms") {
                return Models::$init->db->update("products", $set)->where("id", "=", $id)->save();
            } elseif ($type == "domain") {
                return Models::$init->db->update("tldlist", $set)->where("id", "=", $id)->save();
            } elseif ($type == "software") {
                return Models::$init->db->update("pages", $set)->where("id", "=", $id)->save();
            }

            return false;
        }


        static function set_category($id = 0, $data = [])
        {
            return Models::$init->db->update("categories", $data)->where("id", "=", $id)->save();
        }


        static function addons($ids = '', $lang = '')
        {
            $model = self::model();
            if (!Validation::isEmpty($ids)) {
                $lang = !$lang ? Bootstrap::$lang->clang : $lang;
                $result = $model->get_addons($lang, $ids);
                if ($result) {
                    $keys = array_keys($result);
                    $size = sizeof($keys) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $var = $result[$keys[$i]];
                        $result[$keys[$i]]["options"] = $var["options"] ? Utility::jdecode($var["options"], true) : [];
                        $result[$keys[$i]]["properties"] = $var["properties"] ? Utility::jdecode($var["properties"], true) : [];
                    }
                    return $result;
                }
            }
            return [];
        }


        static function addon($id = '', $lang = '')
        {
            $model = self::model();
            if ($id) {
                $lang = !$lang ? Bootstrap::$lang->clang : $lang;
                $result = $model->get_addon($lang, $id);
                if ($result) {
                    $result["options"] = $result["options"] ? Utility::jdecode($result["options"], true) : [];
                    $result["properties"] = $result["properties"] ? Utility::jdecode($result["properties"], true) : [];

                    if ($result["product_type_link"]) {
                        $product_link = self::get($result["product_type_link"], $result["product_id_link"], $lang);
                        if ($product_link && $product_link["price"]) {
                            $result["product_link"] = $product_link;
                            foreach ($product_link["price"] as $p_row) {
                                $result["options"][] = [
                                    'id'          => $p_row["id"],
                                    'name'        => Bootstrap::$lang->get("needs/iwwant", $lang),
                                    'period'      => $p_row["period"],
                                    'period_time' => $p_row["time"],
                                    'amount'      => $p_row["amount"],
                                    'cid'         => $p_row["cid"],
                                ];
                            }
                        }
                    }

                    return $result;
                }
            }
            return [];
        }


        static function requirement($id = '', $lang = '')
        {
            $model = self::model();
            if ($id) {
                $lang = !$lang ? Bootstrap::$lang->clang : $lang;
                $result = $model->get_requirement($lang, $id);
                if ($result) {
                    $result["options"] = $result["options"] ? Utility::jdecode($result["options"], true) : [];
                    $result["properties"] = $result["properties"] ? Utility::jdecode($result["properties"], true) : [];
                    return $result;
                }
            }
            return [];
        }


        static function get_modular_tlds()
        {
            $stmt = Models::$init->db->select()->from("tldlist");
            $stmt->where("module", "!=", "none", "&&");
            $stmt->where("status", "=", "active");
            return $stmt->build() ? $stmt->fetch_assoc() : false;
        }


        static function auto_define_domain_prices($profit_rate = -1)
        {
            $automation = defined("CRON");
            $tlds = self::get_modular_tlds();
            $profit_rate = $profit_rate == -1 ? Config::get("options/domain-profit-rate") : $profit_rate;
            $changes = 0;

            Helper::Load(["Money"]);

            if ($tlds) {
                $modules = [];
                foreach ($tlds as $tld)
                    if (!$tld["promo_status"] || DateManager::strtotime($tld["promo_duedate"] . " 23:59:59") < DateManager::strtotime()) $modules[$tld["module"]][$tld["id"]] = $tld["name"];

                if ($modules) {
                    $keys = array_keys($modules);
                    foreach ($keys as $key) {
                        if (Modules::Load("Registrars", $key)) {
                            if (class_exists($key) && method_exists($key, "cost_prices")) {

                                $module = new $key();
                                $module_c = Modules::Config("Registrars", $key);
                                $cost_cid = $module_c["settings"]["cost-currency"];


                                if (!isset($module->config["settings"]["adp"]) || !$module->config["settings"]["adp"])
                                    return 0;

                                $prices = $module->cost_prices("domain");

                                if (!$prices) {
                                    self::$error = $module->error;
                                    return false;
                                }

                                if ($prices) {
                                    foreach ($modules[$key] as $pid => $name) {
                                        if (isset($prices[$name])) {
                                            $price = $prices[$name];

                                            $reg_price = self::get_price("register", "tld", $pid);
                                            $ren_price = self::get_price("renewal", "tld", $pid);
                                            $tra_price = self::get_price("transfer", "tld", $pid);

                                            $tld_cid = $reg_price["cid"];


                                            $register_cost = Money::deformatter($price["register"]);
                                            $renewal_cost = Money::deformatter($price["renewal"]);
                                            $transfer_cost = Money::deformatter($price["transfer"]);

                                            // ExChanges
                                            $register_cost = Money::exChange($register_cost, $cost_cid, $tld_cid);
                                            $renewal_cost = Money::exChange($renewal_cost, $cost_cid, $tld_cid);
                                            $transfer_cost = Money::exChange($transfer_cost, $cost_cid, $tld_cid);


                                            $reg_profit = Money::get_discount_amount($register_cost, $profit_rate);
                                            $ren_profit = Money::get_discount_amount($renewal_cost, $profit_rate);
                                            $tra_profit = Money::get_discount_amount($transfer_cost, $profit_rate);

                                            $register_sale = $register_cost + $reg_profit;
                                            $renewal_sale = $renewal_cost + $ren_profit;
                                            $transfer_sale = $transfer_cost + $tra_profit;

                                            $promo_duedate = "1881-05-19";

                                            if ($register_cost < $renewal_cost)
                                                $promo_status = 1;
                                            elseif ($transfer_cost < $renewal_cost)
                                                $promo_status = 1;
                                            else
                                                $promo_status = 0;

                                            $set_domain = [];

                                            if ($promo_status) {
                                                $set_domain["promo_status"] = $promo_status;
                                                $set_domain["promo_duedate"] = $promo_duedate;

                                                if ($register_cost < $renewal_cost) {
                                                    $set_domain["promo_register_price"] = $register_sale;

                                                    if ($register_sale)
                                                        self::set_price($reg_price["id"], [
                                                            'amount' => $renewal_sale,
                                                            'cid'    => $tld_cid,
                                                        ]);
                                                } else {
                                                    $set_domain["promo_register_price"] = 0;
                                                    if ($register_sale)
                                                        self::set_price($reg_price["id"], [
                                                            'amount' => $register_sale,
                                                            'cid'    => $tld_cid,
                                                        ]);
                                                }

                                                if ($transfer_cost < $renewal_cost) {
                                                    $set_domain["promo_transfer_price"] = $transfer_sale;

                                                    if ($transfer_sale)
                                                        self::set_price($tra_price["id"], [
                                                            'amount' => $renewal_sale,
                                                            'cid'    => $tld_cid,
                                                        ]);
                                                } else {
                                                    $set_domain["promo_transfer_price"] = 0;
                                                    if ($transfer_sale)
                                                        self::set_price($tra_price["id"], [
                                                            'amount' => $transfer_sale,
                                                            'cid'    => $tld_cid,
                                                        ]);
                                                }

                                                $set_domain["register_cost"] = $register_cost;
                                                $set_domain["transfer_cost"] = $transfer_cost;

                                            } else {
                                                $set_domain["promo_status"] = $promo_status;
                                                $set_domain["promo_register_price"] = 0;
                                                $set_domain["promo_transfer_price"] = 0;
                                                $set_domain["promo_duedate"] = $promo_duedate;
                                                if ($register_cost) $set_domain["register_cost"] = $register_cost;
                                                if ($transfer_cost) $set_domain["transfer_cost"] = $transfer_cost;

                                                if ($register_sale)
                                                    self::set_price($reg_price["id"], [
                                                        'amount' => $register_sale,
                                                        'cid'    => $tld_cid,
                                                    ]);

                                                if ($transfer_sale)
                                                    self::set_price($tra_price["id"], [
                                                        'amount' => $transfer_sale,
                                                        'cid'    => $tld_cid,
                                                    ]);

                                            }

                                            if ($renewal_cost) $set_domain["renewal_cost"] = $renewal_cost;

                                            self::set("domain", $pid, $set_domain);

                                            if ($renewal_sale)
                                                self::set_price($ren_price["id"], [
                                                    'amount' => $renewal_sale,
                                                    'cid'    => $tld_cid,
                                                ]);


                                            $changes++;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            return $changes;
        }


        static function get_groups_products($lang = '')
        {
            $n_products = [];

            if (Config::get("options/pg-activation/hosting"))
                $n_products['hosting'] = [
                    'id'   => 'hosting',
                    'name' => __("website/account_products/product-type-names/hosting"),
                ];

            if (Config::get("options/pg-activation/server"))
                $n_products['server'] = [
                    'id'   => 'server',
                    'name' => __("website/account_products/product-type-names/server"),
                ];

            if (Config::get("options/pg-activation/software"))
                $n_products['software'] = [
                    'id'   => 'software',
                    'name' => __("website/account_products/product-type-names/software"),
                ];

            if (Config::get("options/pg-activation/sms") && Config::get("general/local") == "tr")
                $n_products['sms'] = [
                    'id'   => 'sms',
                    'name' => __("website/account_products/product-type-names/sms"),
                ];

            $special_groups = self::special_groups($lang, 't1.id,t2.title AS name');
            if ($special_groups)
                foreach ($special_groups as $group)
                    $n_products[$group["id"] . "-0"] = $group;

            if (Config::get("options/pg-activation/domain"))
                $n_products["domain"] = [
                    'id'   => "domain",
                    'name' => __("website/account_products/product-type-names/domain"),
                ];

            $r_products = [];

            if ($n_products) {
                foreach ($n_products as $k => $g) {
                    $c_id = 0;
                    $k_s = explode("-", $k);
                    if (Validation::isInt($k_s[0])) {
                        $t = "special";
                        $c_id = $k_s[0];
                    } else
                        $t = $k_s[0];

                    $products = self::get_products($t, $c_id);
                    $r_products[$k] = $g;
                    $r_products[$k]['products'] = $products;

                    $categories = self::get_sub_categories_breadcrumb($t, $c_id, $lang, $g["name"]);
                    if ($categories) {
                        foreach ($categories as $c) {
                            $r_products[$k_s[0] . "-" . $c["id"]] = [
                                'id'       => $c["id"],
                                'name'     => $c["title"],
                                'products' => self::get_products($t, $c["id"]),
                            ];
                        }
                    }

                }
            }

            return $r_products;
        }


        static function get_products($type = 'hosting', $cat_id = -1, $type_id = 0)
        {
            if ($type == 'domain') {
                $stmt = Models::$init->db->select("id,'0' AS category,'domain' AS type,name AS title")->from("tldlist");
                $stmt->order_by("rank ASC");
                return $stmt->build() ? $stmt->fetch_assoc() : [];
            } else {
                $_type = $type == 'software' ? "pages" : "products";
                $select = "t1.id,t1.category,t1.type,t2.title";
                if ($_type == "products") $select .= ",t1.type_id";
                $lang = Bootstrap::$lang->clang;
                $stmt = Models::$init->db->select($select)->from($_type . " AS t1");
                $stmt->join("LEFT", $_type . "_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
                if ($cat_id > -1) $stmt->where("t1.category", "=", $cat_id, "&&");
                if ($type_id > 0) $stmt->where("t1.type_id", "=", $type_id, "&&");
                $stmt->where("t1.type", "=", $type);
                $stmt->order_by("category ASC,id ASC");
                return $stmt->build() ? $stmt->fetch_assoc() : [];
            }
        }


        static function upgrade_products($order = [], $product = [], $remaining_amount = false)
        {
            $output = [
                'categories' => [],
                'products'   => [],
                'prices'     => [],
            ];

            if (!$product)
                $product = [
                    'id'                   => $order["product_id"],
                    'type'                 => $order["type"],
                    'type_id'              => $order["type_id"],
                    'title'                => $order["name"],
                    'upgradeable_products' => '',
                ];

            $upgradeable_products = isset($product['upgradeable_products']) && $product['upgradeable_products'] ? explode(',', $product['upgradeable_products']) : [];

            $onlyonsameserver = Config::get("options/product-upgrade/only-on-same-server");
            $onlyonsameperiods = Config::get("options/product-upgrade/only-on-same-periods");

            $type = $product["type"];

            $type_id = $product["type_id"];
            $categories = self::model()->get_select_categories($type, $type_id, '');

            if ($type == "special") {
                $mainCategory = Products::getCategory($type_id, false, 't1.id,t1.parent,t1.options,t2.title,t2.options AS optionsl');
                $categories = array_merge([$mainCategory], $categories);
            }


            $order_amount = (float)$order["amount"];

            if (!class_exists("Invoices")) Helper::Load(["Invoices"]);

            $country = 0;
            $city = 0;
            $tax_rate = 0;
            $taxation_type = Invoices::getTaxationType();

            if (!isset(self::$updowngrade_users[$order["owner_id"]])) {
                $udata = User::getData($order["owner_id"], "id,country", "array");
                $udata = array_merge($udata, User::getInfo($udata["id"], ["taxation"]));
                if ($getAddress = AddressManager::getAddress(0, $udata["id"])) $udata["address"] = $getAddress;
                self::$updowngrade_users[$udata["id"]] = $udata;
            }

            $udata = self::$updowngrade_users[$order["owner_id"]];
            $getAddress = isset($udata["address"]) ? $udata["address"] : [];

            $country = $udata["country"];
            if ($getAddress) {
                $country = $getAddress["country_id"];
                if (isset($getAddress["city_id"])) $city = $getAddress["city_id"];
                else
                    $city = $getAddress["city"];
            }

            $taxation = Invoices::getTaxation($country, $udata["taxation"]);
            $isLocal = Invoices::isLocal($country, $udata["id"]);
            if ($isLocal) $tax_rate = Invoices::getTaxRate($country, $city, $udata["id"]);

            $creation_info = isset($order["options"]["creation_info"]) ? $order["options"]["creation_info"] : [];


            if ($categories) {
                foreach ($categories as $category) {
                    $getps = self::model()->get_products_with_category($type, $category["id"]);
                    if ($getps) {
                        foreach ($getps as $p) {
                            if ($upgradeable_products && !in_array($p['id'], $upgradeable_products)) continue;

                            if ($onlyonsameserver && $type == "hosting" && $order["module"] != "none") {
                                $mdata = $p["module_data"] ? Utility::jdecode($p["module_data"], true) : [];
                                if (!isset($mdata["server_id"]) || $mdata["server_id"] != $order["options"]["server_id"]) continue;
                                $p_creation_info = isset($mdata['create_account']) ? $mdata['create_account'] : $mdata;
                                if (isset($creation_info["reseller"]) && $creation_info["reseller"])
                                    if (!isset($p_creation_info["reseller"]) || !$p_creation_info["reseller"]) continue;
                            }
                            if (!isset($products[$p["id"]])) {
                                $prices = self::get_prices("periodicals", "products", $p["id"]);
                                if ($prices) {
                                    $pprices = [];
                                    foreach ($prices as $price) {
                                        $price_ = $price["amount"];
                                        if ($price["setup"] > 0.00) $price_ += $price["setup"];

                                        if ($onlyonsameperiods)
                                            $same_periods = $price["period"] == $order["period"] && $price["time"] == $order["period_time"];
                                        else
                                            $same_periods = true;


                                        $exch = Money::exChange($price_, $price["cid"], $order["amount_cid"]);
                                        if ($price["period"] != "none" && $same_periods && $exch > $order_amount) {
                                            $price["amount"] = $exch;
                                            $price["cid"] = $order["amount_cid"];

                                            $price["payable"] = ($exch - $remaining_amount);

                                            if ($taxation_type == "inclusive") {
                                                $price["payable"] -= Money::get_inclusive_tax_amount($price["payable"], $tax_rate);
                                            }

                                            $price["tax"] = $taxation ? Money::get_tax_amount($price["payable"], $tax_rate) : 0;

                                            $price["taxed_payable"] = $price["payable"] + $price["tax"];

                                            $pprices[$price["id"]] = $price;
                                        }
                                    }
                                    if ($pprices) {
                                        $output["products"][$category["id"]][$p["id"]] = $p;
                                        $output["prices"][$p["id"]] = $pprices;
                                    }
                                }
                            }
                        }
                    }
                    if (!isset($output["products"][$category["id"]])) $category["non-product"] = true;
                    if (isset($output["products"][$category["id"]])) $output["categories"][$category["id"]] = $category;
                }
            }
            return $output;
        }


        static function catch_server_in_group($servers = '', $fill_type = 1)
        {
            if (!$fill_type) $fill_type = 1;
            $server_id = 0;

            $servers = explode(",", $servers);

            if ($servers) {
                if ($fill_type == 1) {
                    $remaining_list = [];

                    foreach ($servers as $s) {
                        $s = self::get_server($s);
                        if($s['status'] != 'active') continue;
                        $used = Orders::linked_server_count($s["id"]);
                        $remaining = $used > 0 ? $s["maxaccounts"] - $used : $s["maxaccounts"];

                        if ($remaining < 1) continue;

                        $remaining_list[$s["id"]] = $remaining;
                    }

                    if ($remaining_list) {
                        $remaining_list_f = array_flip($remaining_list);
                        $min = max($remaining_list);

                        $server_id = (int)$remaining_list_f[$min];
                    }
                } elseif ($fill_type == 2) {
                    foreach ($servers as $s) {
                        $s = self::get_server($s);
                        $used = Orders::linked_server_count($s["id"]);
                        $maxaccounts = $s["maxaccounts"] ?? 0;
                        $remaining = $used > 0 ? $maxaccounts - $used : $maxaccounts;
                        if ($remaining <= 0) continue;

                        $server_id = (int)$s["id"];
                        break;
                    }
                }
            }

            return $server_id;
        }


    }


    class Products_Model
    {
        public $db = false;


        function __construct()
        {
            $this->db = Models::$init->db;
        }


        public function get_products_with_category($type = '', $category = 0)
        {
            $ll_lang = Bootstrap::$lang->clang;
            $select = implode(",", [
                't1.id',
                't1.override_usrcurrency',
                't1.type',
                't1.options',
                't1.module',
                't1.module_data',
                't2.options AS options_lang',
                't2.title',
                't2.features',
                'CASE
                WHEN stock = "" THEN 1 
                WHEN stock IS NULL THEN 1 
                ELSE stock 
                END AS haveStock',
            ]);
            $stmt = $this->db->select($select)->from("products AS t1");
            $stmt->join("LEFT", "products_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $ll_lang . "'");
            $stmt->where("t2.id", "IS NOT NULL", "", "&&");
            $stmt->where("t1.type", "=", $type, "&&");
            $stmt->where("t1.status", "=", "active", "&&");
            $stmt->where("t1.visibility", "=", "visible", "&&");
            $stmt->where("t1.category", "=", $category);
            $stmt->order_by("rank ASC");
            return $stmt->build() ? $stmt->fetch_assoc() : false;
        }


        public function get_select_categories($type = '', $parent = 0, $line = '', $kind_id = 0)
        {
            $ll_lang = Bootstrap::$lang->clang;
            //$sd_lang    = Bootstrap::$lang->clang;

            $stmt = $this->db->select("c.id,c.parent,c.options,cl.title,cl.options AS optionsl")->from("categories AS c");
            $stmt->join("LEFT", "categories_lang AS cl", "cl.owner_id=c.id AND (cl.lang='" . $ll_lang . "')");
            $stmt->where("cl.id", "IS NOT NULL", "", "&&");
            $stmt->where("c.parent", "=", $parent, "&&");
            $stmt->where("c.status", "=", "active", "&&");
            $stmt->where("c.visibility", "=", "visible", "&&");
            if ($kind_id) $stmt->where("c.kind_id", "=", $kind_id, "&&");
            if ($type == "software") {
                $stmt->where("c.type", "=", $type);
            } elseif ($type == "addon") {
                $stmt->where("c.type", "=", $type);
            } elseif ($type == "requirement") {
                $stmt->where("c.type", "=", $type);
            } else {
                $stmt->where("c.kind", "=", $type, "&&");
                $stmt->where("c.type", "=", "products");
            }
            $stmt->order_by("c.rank ASC");
            $result = $stmt->build() ? $stmt->fetch_assoc() : [];
            $new_result = [];
            if ($result) {
                foreach ($result as $res) {
                    $new = $res;
                    $new["title"] = $line . " " . $res["title"];
                    $new_result[] = $new;
                    $sub_result = $this->get_select_categories($type, $res["id"], $line . "-", $kind_id);
                    if ($sub_result) {
                        $new_result = array_merge($new_result, $sub_result);
                    }
                }
            }
            return $new_result;
        }


        public function get_addons($lang = '', $ids = '')
        {
            $stmt = $this->db->select("t1.id,t1.override_usrcurrency,t1.requirements,t1.product_type_link,t1.product_id_link,t2.name,t2.description,t2.type,t2.properties,t2.options")->from("products_addons AS t1");
            $stmt->join("LEFT", "products_addons_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
            $stmt->where("t2.id", "IS NOT NULL", "", "&&");
            $stmt->where("t1.status", "=", "active", "&&");
            $stmt->where("FIND_IN_SET(t1.id,'" . $ids . "')");
            $stmt->order_by("t1.rank ASC,id ASC");
            return $stmt->build() ? $stmt->fetch_assoc() : false;
        }


        public function get_addon($lang = '', $id = 0)
        {
            $stmt = $this->db->select("t1.id,t1.override_usrcurrency,t1.rank,t1.product_type_link,t1.product_id_link,t2.name,t2.description,t2.type,t2.properties,t2.options")->from("products_addons AS t1");
            $stmt->join("LEFT", "products_addons_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
            $stmt->where("t2.id", "IS NOT NULL", "", "&&");
            $stmt->where("t1.status", "=", "active", "&&");
            $stmt->where("t1.id", "=", $id);
            return $stmt->build() ? $stmt->getAssoc() : false;
        }


        public function get_requirement($lang = '', $id = 0)
        {
            $stmt = $this->db->select("t1.id,t2.name,t2.description,t2.type,t2.properties,t2.options,t1.module_co_names")->from("products_requirements AS t1");
            $stmt->join("LEFT", "products_requirements_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
            $stmt->where("t2.id", "IS NOT NULL", "", "&&");
            $stmt->where("t1.status", "=", "active", "&&");
            $stmt->where("t1.id", "=", $id);
            return $stmt->build() ? $stmt->getAssoc() : false;
        }


        public function get_server($id = 0)
        {
            return $this->db->select()->from("servers")->where("id", "=", $id)->build() ? $this->db->getAssoc() : false;
        }


        public function get_server_group($id = 0)
        {
            return $this->db->select()->from("servers_groups")->where("id", "=", $id)->build() ? $this->db->getAssoc() : false;
        }


        public function get_software($id = 0, $lang = '', $status = '')
        {
            $select = implode(",", [
                't1.id',
                't1.status',
                't1.override_usrcurrency',
                't1.taxexempt',
                't1.addons',
                't1.requirements',
                't1.module',
                't1.module_data',
                't2.title',
                't2.route',
                't1.options',
                't2.options AS optionsl',
                't1.category',
                't1.subdomains',
                't1.affiliate_disable',
                't1.affiliate_rate',
                't3.title AS category_title',
                't3.route AS category_route',
            ]);
            $sth = $this->db->select($select)->from("pages AS t1");
            $sth->join("LEFT", "pages_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
            $sth->join("LEFT", "categories_lang AS t3", "t3.owner_id=t1.category AND t3.lang='" . $lang . "'");
            $sth->where("t2.id", "IS NOT NULL", "", "&&");
            $sth->where("t1.type", "=", "software", "&&");
            if ($status != ' ' && $status) $sth->where("t1.status", "=", $status, "&&");
            $sth->where("t1.id", "=", $id);
            return $sth->build() ? $sth->getAssoc() : false;
        }


        public function get($id = 0, $lang = '', $type = '')
        {
            $select = implode(",", [
                't1.id',
                't1.type',
                't1.status',
                't1.type_id',
                't1.override_usrcurrency',
                't1.taxexempt',
                't1.upgradeable_products',
                't1.addons',
                't1.requirements',
                't1.module',
                't1.module_data',
                't2.title',
                't2.features',
                't1.options',
                't2.options AS optionsl',
                't1.category',
                't1.module',
                't1.module_data',
                't1.stock',
                't1.subdomains',
                't1.affiliate_disable',
                't1.affiliate_rate',
                'CASE
                WHEN stock = "" THEN 1 
                WHEN stock IS NULL THEN 1 
                ELSE stock 
                END AS haveStock',
            ]);
            $sth = $this->db->select($select)->from("products AS t1");
            $sth->join("LEFT", "products_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
            $sth->where("t2.id", "IS NOT NULL", "", "&&");
            if ($type) $sth->where("t1.type", "=", $type, "&&");
            $sth->where("t1.id", "=", $id);
            return $sth->build() ? $sth->getAssoc() : false;
        }


        public function get_tld($id = 0)
        {
            $sth = $this->db->select()->from("tldlist");
            $sth->where("id", "=", $id, "||");
            $sth->where("name", "=", $id);
            return $sth->build() ? $sth->getAssoc() : false;
        }


        public function getCategory($cat_id = 0, $lang = false, $select = '')
        {
            if (!$select)
                $select = "t1.id,t2.id AS lid,t1.parent,t2.title,t2.route";
            $sth = $this->db->select($select)->from("categories AS t1");
            $sth->join("LEFT", "categories_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
            $sth->where("t2.id", "IS NOT NULL", "", "&&");
            $sth->where("t1.id", "=", $cat_id);
            $data = $sth->build() ? $sth->getAssoc() : false;
            if (isset($data["options"])) $data["options"] = $data["options"] ? Utility::jdecode($data["options"], true) : [];
            if (isset($data["optionsl"])) $data["optionsl"] = $data["optionsl"] ? Utility::jdecode($data["optionsl"], true) : [];
            return $data;
        }


        public function getTopCategory($cat_id = 0, $lang = false)
        {
            $sth = $this->db->select("t1.id,t1.parent,t2.title,t2.route")->from("categories AS t1");
            $sth->join("LEFT", "categories_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
            $sth->where("t2.id", "IS NOT NULL", "", "&&");
            $sth->where("t1.id", "=", $cat_id);
            if ($sth->build()) {
                $get = $sth->getAssoc();
                if ($get["parent"] != 0)
                    return self::getTopCategory($get["parent"], $lang);
                else
                    return $get;
            } else
                return false;
        }


        public function get_prices($type, $owner, $owner_id, $lang = 'none')
        {
            $sth = $this->db->select()->from("prices");
            $sth->where("type", "=", $type, "&&");
            $sth->where("owner", "=", $owner, "&&");
            $sth->where("owner_id", "=", $owner_id);
            //$sth->where("lang","=",$lang);
            $sth->order_by("rank ASC");
            if (!$sth->build()) return [];
            return $sth->fetch_assoc();
        }


        public function get_price($type, $owner, $owner_id, $lang = 'none')
        {
            $sth = $this->db->select()->from("prices");
            $sth->where("type", "=", $type, "&&");
            $sth->where("owner", "=", $owner, "&&");
            $sth->where("owner_id", "=", $owner_id);
            //$sth->where("lang","=",$lang);
            $sth->order_by("rank ASC");
            if (!$sth->build()) return [];
            return $sth->getAssoc();
        }
    }