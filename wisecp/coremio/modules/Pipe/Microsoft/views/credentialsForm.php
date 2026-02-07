<div class="blue-info">
    <div class="padding20">
        <i class="fa fa-info-circle"></i>
        <?php echo ($mv["lang"]["description"] ?? ''); ?>
    </div>
</div>

<div class="formcon">
    <div class="yuzde30"><?php echo $mv["lang"]["client-id"]; ?></div>
    <div class="yuzde70">
        <input type="text" name="module[<?php echo $mk; ?>][<?php echo $did ?? 0; ?>][client_id]" value="<?php echo $mv["init"]->config[$did]["client_id"] ?? ''; ?>">
    </div>
</div>

<div class="formcon">
    <div class="yuzde30"><?php echo $mv["lang"]["client-secret"]; ?></div>
    <div class="yuzde70">
        <input type="password" name="module[<?php echo $mk; ?>][<?php echo $did ?? 0; ?>][client_secret]" value="<?php echo $mv["init"]->config[$did]["client_secret"] ?? ''; ?>">
    </div>
</div>

<div class="formcon">
    <div class="yuzde30"><?php echo $mv["lang"]["redirect-uri"]; ?></div>
    <div class="yuzde70">
        <input readonly type="text" value="<?php echo $mv["init"]->redirect_uri; ?>">
    </div>
</div>

<div class="formcon">
    <div class="yuzde30"><?php echo __("admin/tickets/pipe-text7"); ?></div>
    <div class="yuzde70">
        <?php
            $tokens = $mv["init"]->config[$did]["tokens"] ?? '';
        ?>
        <input readonly type="password" name="module[<?php echo $mk; ?>][<?php echo $did ?? 0; ?>][tokens]" value="<?php echo $tokens; ?>">

        <a class="lbtn" href="javascript:void 0;" onclick="buttonPipe(this,<?php echo $did ?? 0; ?>,'oAuth2');" style="margin-top:5px;<?php echo $tokens ? 'display:none;' : ''; ?>" id="<?php echo $mk."_".$did; ?>_authorize"><i class="fas fa-lock"></i> <?php echo __("admin/tickets/pipe-text8"); ?></a>

        <a class="lbtn red" href="javascript:void 0;" onclick="$(this).css('display','none'); $('input[name=\'module[<?php echo $mk; ?>][<?php echo $did ?? 0; ?>][tokens]\']').val(''); $('#<?php echo $mk."_".$did; ?>_authorize').css('display','inline-block');buttonPipe(this,<?php echo $did ?? 0; ?>,'clear_tokens');" style="margin-top:5px;<?php echo $tokens ? '' : 'display:none;'; ?>" id="<?php echo $mk."_".$did; ?>_unauthorize"><i class="fas fa-unlock"></i> <?php echo __("admin/tickets/pipe-text9"); ?></a>

    </div>
</div>

<?php if($tokens): ?>
    <div class="formcon">
        <a class="lbtn green" href="javascript:void 0;" onclick="buttonPipe(this,<?php echo $did ?? 0; ?>,'test_connection');" style="margin-top:5px;"><i class="fas fa-plug"></i> <?php echo __("admin/modules/test-button"); ?></a>
    </div>
<?php endif; ?>