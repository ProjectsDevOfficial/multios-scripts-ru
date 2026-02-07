<?php
    Class SepaPaymentQRCode extends AddonModule {
        public $version = "1.0";
        function __construct(){
            $this->_name = __CLASS__;
            parent::__construct();
        }

        public function fields(){
            $settings = isset($this->config['settings']) ? $this->config['settings'] : [];

            $image_url = Utility::image_link_determiner("resources/uploads/category-icon/default.jpg");

            if(isset($settings["image"]) && $settings["image"])
            {
                $image_name = $settings["image"];
                if(file_exists(__DIR__.DS."uploads".DS.$image_name))
                    $image_url = $this->url."uploads/".$image_name;
            }


            $show_image = '<img src="'.$image_url.'" width="120" height="auto" style="margin-top:5px;"><br>';


            return [
                'image'          => [
                    'wrap_width'        => 100,
                    'name'              => $this->lang["image"] ?? "QR Code Image",
                    'type'              => "file",
                    'description'       => $show_image.$this->lang["image-desc"] ?? '',
                ],
                'title'          => [
                    'wrap_width'        => 100,
                    'name'              => $this->lang["text10"],
                    'description'       => '',
                    'type'              => "text",
                    'value'             => $settings["title"] ?? "",
                ],

                'description'          => [
                    'wrap_width'        => 100,
                    'name'              => $this->lang["text11"],
                    'description'       => '',
                    'type'              => "textarea",
                    'value'             => $settings["description"] ?? "",
                ],
            ];
        }

        public function save_fields($fields=[]){
            $image      = Filter::init("FILES/fields");


            if($image)
            {

                $image_data = [
                    'name'          => $image["name"]["image"] ?? '',
                    'full_path'     => $image["full_path"]["image"] ?? '',
                    'type'          => $image["type"]["image"] ?? '',
                    'tmp_name'      => $image["tmp_name"]["image"] ?? '',
                    'error'         => $image["error"]["image"] ?? '',
                    'size'          => $image["size"]["image"] ?? '',
                ];


                Helper::Load("Uploads");

                $folder    = __DIR__.DS."uploads".DS;

                $upload = Helper::get("Uploads");
                $upload->init($image_data,[
                    'image-upload' => true,
                    'folder' => $folder,
                    'allowed-ext' => "image/*",
                    'file-name' => "random",
                    'date'      => false,
                ]);
                if(!$upload->processed())
                {
                    $this->error = __("admin/settings/error1",['{error}' => $upload->error]);
                    return false;
                }
                $picture        = current($upload->operands);
                $fields["image"] = $picture["name"] ?? "";
            }
            elseif($this->config["settings"]["image"] ?? false)
                $fields["image"] = $this->config["image"];


            return $fields;
        }

        public function activate(){
            return true;
        }

        public function deactivate(){
            /*
             * Here, you can perform any intervention before the module is deactivate.
             * If you return boolean (true), the module will be deactivate.
            */
            return true;
        }

        public function add_qr_code_to_invoice_detail($invoice=[])
        {

            $image              = $this->url."uploads/".$this->config["settings"]["image"] ?? '';

            if(!$image)
                $image =  $this->url.'logo.png';

            return [
                'image'         => $image,
                'title'         => $this->config["settings"]["title"] ?? 'SEPA QR CODE',
                'description'   => $this->config["settings"]["description"] ?? '',
            ];
        }

    }

    Hook::add("AddQRCodetoInvoiceDetailinClientArea",1,[
        'class' => "SepaPaymentQRCode",
        'method' => "add_qr_code_to_invoice_detail",
    ]);