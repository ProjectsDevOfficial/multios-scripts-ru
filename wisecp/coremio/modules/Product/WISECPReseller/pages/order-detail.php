<?php
    $LANG           = $module->lang;
    $options        = $order["options"];
    $creation_info  = isset($options["creation_info"]) ? $options["creation_info"] : [];
    $config         = isset($options["config"]) ? $options["config"] : [];
    $buttons        =  $module->adminArea_buttons_output();

?>

<?php
    if($buttons){
        ?>
        <div class="formcon">
            <?php echo $buttons; ?>
        </div>
        <div class="clear"></div>
        <?php
    }
?>
    <div class="clear"></div>

    <div class="formcon">
        <div class="yuzde30">WISECP Order ID</div>
        <div class="yuzde70">
            <input type="text" name="config[order_id]" value="<?php echo isset($config["order_id"]) ? $config["order_id"]  : '' ?>">
        </div>
    </div>

<?php
    if(method_exists($module,"adminArea_service_fields") && $config_options = $module->adminArea_service_fields())
        $module->config_options_output($config_options,'creation_info');
?>

<?php
    if(method_exists($module,"config_options") && $config_options = $module->config_options($creation_info))
        $module->config_options_output($config_options,'creation_info');
?>