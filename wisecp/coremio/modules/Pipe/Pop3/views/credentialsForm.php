<div class="blue-info">
    <div class="padding20">
        <i class="fa fa-info-circle"></i>
        <?php echo ($mv["lang"]["description"] ?? ''); ?>
    </div>
</div>


<div class="formcon">
    <div class="yuzde30"><?php echo $mv["lang"]["protocol"]; ?></div>
    <div class="yuzde70">
        <select name="module[<?php echo $mk; ?>][<?php echo $did ?? 0; ?>][protocol]">
            <option<?php echo ($mv["init"]->config[$did]["protocol"] ?? 'pop3') == 'pop3' ? ' selected' : ''; ?> value="pop3">POP3</option>
            <option<?php echo ($mv["init"]->config[$did]["protocol"] ?? 'pop3') == 'imap' ? ' selected' : ''; ?> value="imap">IMAP</option>
        </select>
    </div>
</div>

<div class="formcon">
    <div class="yuzde30"><?php echo $mv["lang"]["hostname"]; ?> / <?php echo $mv["lang"]["port"]; ?></div>
    <div class="yuzde70">
        <div class="yuzde80">
            <input type="text" name="module[<?php echo $mk; ?>][<?php echo $did ?? 0; ?>][hostname]" value="<?php echo $mv["init"]->config[$did]["hostname"] ?? ''; ?>" placeholder="mail.example.com">
        </div>
        <div class="yuzde20">
            <input type="text" name="module[<?php echo $mk; ?>][<?php echo $did ?? 0; ?>][port]" value="<?php echo $mv["init"]->config[$did]["port"] ?? ''; ?>" onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode == 43" placeholder="995">
        </div>
    </div>
</div>

<div class="formcon">
    <div class="yuzde30"><?php echo $mv["lang"]["username"]; ?></div>
    <div class="yuzde70">
        <input type="text" name="module[<?php echo $mk; ?>][<?php echo $did ?? 0; ?>][username]" value="<?php echo $mv["init"]->config[$did]["username"] ?? ''; ?>" placeholder="me@example.com">
    </div>
</div>

<div class="formcon">
    <div class="yuzde30"><?php echo $mv["lang"]["password"]; ?></div>
    <div class="yuzde70">
        <input type="password" name="module[<?php echo $mk; ?>][<?php echo $did ?? 0; ?>][password]" value="<?php echo $mv["init"]->config[$did]["password"] ?? ''; ?>">
    </div>
</div>

<div class="formcon">
    <div class="yuzde30"><?php echo $mv["lang"]["ssl"]; ?></div>
    <div class="yuzde70">
        <input<?php echo ($mv["init"]->config[$did]["ssl"] ?? false) ? ' checked' : ''; ?> type="checkbox" name="module[<?php echo $mk; ?>][<?php echo $did ?? 0; ?>][ssl]" value="1" onchange="$(this).parent().find('input[type=hidden]').prop('disabled',$(this).prop('checked'));" class="checkbox-custom" id="<?php echo $mk."_".$did; ?>_ssl">
        <label class="checkbox-custom-label" for="<?php echo $mk."_".$did; ?>_ssl"></label>
        <input disabled type="hidden" name="module[<?php echo $mk; ?>][<?php echo $did ?? 0; ?>][ssl]" value="0">
    </div>
</div>


<div class="formcon">
    <a class="lbtn green" href="javascript:void 0;" onclick="buttonPipe(this,<?php echo $did ?? 0; ?>,'test_connection');" style="margin-top:5px;"><i class="fas fa-plug"></i> <?php echo __("admin/modules/test-button"); ?></a>
</div>