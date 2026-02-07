<?php
    /**
     * @author WISECP LLC
     * @since 2017
     * @copyright All rights reserved for WISECP LLC.
     * @contract https://my.wisecp.com/en/service-and-use-agreement
     * @warning Unlicensed can not be copied, distributed and can not be used.
     **/

    defined('CORE_FOLDER') or exit('You can not get in here!');

    class Controller extends Controllers
    {
        protected $params, $data = [];

        public function __construct($arg = [])
        {
            parent::__construct();
            $this->params = $arg['params'];

            header('Set-Cookie: ' . session_name() . '=' . session_id() . '; SameSite=None; Secure', false);

        }

        public function main()
        {
            $this->takeDatas(["language"]);

            $method = isset($this->params[0]) ? $this->params[0] : false;
            $token = isset($this->params[1]) ? $this->params[1] : false;
            $type = isset($this->params[2]) ? $this->params[2] : false;
            $type = Filter::route($type);
            $type = str_replace(['-', '.'], '_', $type);
            $type = substr($type, 0, 300);

            if ($method == "successful") return $this->successful_main();
            elseif ($method == "failed") return $this->failed_main();

            if (!$method || !$token || !$type) die("Access Denied");
            $methods = Config::get("modules/payment-methods");
            $methods[] = "Free";

            if ($methods && in_array($method, $methods)) {
                Modules::Load("Payment", $method);
                if (class_exists($method)) {
                    $module = new $method();
                    $mtoken = $module->get_auth_token();
                    if ($type == "callback" && $token != $mtoken) die("Invalid Security Token");

                    Helper::Load(["User", "Basket", "Invoices", "Products", "Orders", "Money", "Events"]);

                    if ($type == "callback") {
                        $result = $module->payment_result();
                        if ($result) {
                            $process = PaymentGatewayModule::processed_by_callback($result, $module);
                            if (isset($process['message']))
                                echo $process['message'];
                            elseif (isset($process['redirect']))
                                Utility::redirect($process['redirect']);
                        } else {
                            if ($module->error)
                                echo $module->error;
                            else
                                echo "No results found for payment.";
                        }
                    } elseif ($type != '' && isset($module->call_function) && isset($module->call_function[$type])) {
                        if (is_callable($module->call_function[$type]))
                            $module->call_function[$type]();
                        elseif (method_exists($module, $module->call_function[$type]))
                            call_user_func([$module, $module->call_function[$type]]);
                        else
                            echo "Undefined callable function";
                    }
                } else die("There is no such payment method plugin.");
            } else die("There is no such payment method.");

        }

        public function successful_main()
        {

            Helper::Load(["Basket"]);

            if (Basket::count() < 1) {
                Utility::redirect($this->CRLink("basket"));
                return;
            }

            Basket::clear();
            Session::delete("coupons");


            $this->takeDatas([
                "sign-all",
                "language",
                "lang_list",
                "newsletter",
                "contacts",
                "socials",
                "header_menus",
                "footer_menus",
                "home_link",
                "canonical_link",
                "favicon_link",
                "header_logo_link",
                "footer_logo_link",
                "header_type",
                "meta_color",
                "footer_logos",
            ]);


            $this->addData("meta", ['title' => __("website/payment/meta-title-successful")]);
            $this->addData("header_title", __("website/payment/header-title-successful"));

            $this->addData("links", [
                'products' => $this->CRLink("ac-ps-products"),
            ]);


            $this->view->chose("website")->render("payment-successful", $this->data);
        }

        public function failed_main()
        {

            if (Basket::count() < 1) {
                Utility::redirect($this->CRLink("basket"));
                return;
            }

            $this->takeDatas([
                "sign-all",
                "language",
                "lang_list",
                "newsletter",
                "contacts",
                "socials",
                "header_menus",
                "footer_menus",
                "home_link",
                "canonical_link",
                "favicon_link",
                "header_logo_link",
                "footer_logo_link",
                "header_type",
                "meta_color",
                "footer_logos",
            ]);

            $this->addData("meta", ['title' => __("website/payment/meta-title-failed")]);
            $this->addData("header_title", __("website/payment/header-title-failed"));

            $this->view->chose("website")->render("payment-failed", $this->data);
        }
    }