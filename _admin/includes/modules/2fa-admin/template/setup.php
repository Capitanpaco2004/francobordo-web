<form method="post" action="<?php echo tep_href_link($sUrlPage, 'action=account_2fa_setup_confirm') ?>" id="mainForm">
<input type="hidden" name="totp_code" id="otp-hidden">
<div class="oeBox column a12 row ax">
    <div class="oeCntd row ax" style="display: flex; gap: 30px; align-items: flex-start; flex-wrap: wrap;">

        <!-- Columna izquierda: QR -->
        <div style="flex: 0 0 auto; text-align: center;">
            <img src="<?php echo $qrInline ?>" alt="QR TOTP" width="220" height="220" style="border: 1px solid #ddd; padding: 8px; background: #fff; border-radius: 8px;">
            <div style="margin-top: 10px;">
                <small style="color: #888;"><?php echo TEXT_2FA_SETUP_MANUAL_KEY ?></small><br>
                <code id="manual-key" style="font-size: 13px; letter-spacing: 1px; background: #f5f5f5; padding: 4px 8px; border-radius: 4px; user-select: all;"><?php echo $manualKey ?></code>
                <a href="javascript:void(0)" onclick="copyManualKey(this)" title="Copiar" style="margin-left: 6px; color: #888; font-size: 14px;"><i class="fas fa-copy"></i></a>
            </div>
        </div>

        <!-- Columna derecha: instrucciones + codigo -->
        <div style="flex: 1; min-width: 280px;">
            <div class="oeWrpr">
                <div class="oeTitu"><i class="fas fa-shield-halved"></i> <?php echo TEXT_2FA_SETUP_TITLE ?></div>
                <div class="oeCntd">
                    <p style="margin-bottom: 20px;"><?php echo TEXT_2FA_SETUP_INTRO ?></p>
                </div>
            </div>

            <div class="oeWrpr" style="margin-top: 20px;">
                <div class="oeTitu"><i class="fas fa-keyboard"></i> <?php echo TEXT_2FA_SETUP_CODE_LABEL ?></div>
                <div class="oeCntd" style="text-align: center; padding: 20px 0;">
                    <div class="otp-group">
                        <input type="text" class="otp-box" autocomplete="one-time-code" inputmode="numeric" data-i="0" autofocus>
                        <input type="text" class="otp-box" maxlength="1" inputmode="numeric" data-i="1">
                        <input type="text" class="otp-box" maxlength="1" inputmode="numeric" data-i="2">
                        <span class="otp-sep">–</span>
                        <input type="text" class="otp-box" maxlength="1" inputmode="numeric" data-i="3">
                        <input type="text" class="otp-box" maxlength="1" inputmode="numeric" data-i="4">
                        <input type="text" class="otp-box" maxlength="1" inputmode="numeric" data-i="5">
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
</form>

<script>
function copyManualKey(el) {
    var key = document.getElementById('manual-key').textContent.replace(/\s/g, '');
    navigator.clipboard.writeText(key).then(function() {
        var icon = el.querySelector('i');
        icon.className = 'fas fa-check';
        el.style.color = '#27ae60';
        setTimeout(function() {
            icon.className = 'fas fa-copy';
            el.style.color = '#888';
        }, 1500);
    });
}
</script>
