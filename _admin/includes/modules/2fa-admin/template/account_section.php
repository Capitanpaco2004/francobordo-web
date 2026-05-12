<?php
/**
 * Seccion 2FA para la vista principal de Mi Cuenta
 * Se ejecuta via event:: 'back_office_account_index_2fa'
 */

use Oscdenox\Core\Auth\TwoFactor\RecoveryCodeService;

global $account, $login_id, $sUrlPage;

$recoveryCount = 0;
if ($account['admin_2fa_enabled'] == 1) {
    $recoveryService = new RecoveryCodeService();
    $recoveryCount = $recoveryService->getRemainingCount((int)$login_id);
}

ob_start();
?>
<div class="oeWrpr" style="margin-top: 40px;">
    <div class="oeTitu"><i class="fas fa-shield-halved"></i> <?php echo TABLE_HEADING_2FA ?></div>
    <div class="oeCntd row ax xform xform-horizontal">
        <label class="column a02 tright inline"><strong><?php echo TEXT_2FA_STATUS ?></strong></label>
        <div class="column a10">
            <?php if ($account['admin_2fa_enabled'] == 1): ?>
                <span style="display: inline-block; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px; padding: 3px 10px; font-weight: bold;">
                    <i class="fas fa-check-circle"></i> <?php echo TEXT_2FA_ENABLED ?>
                </span>
            <?php else: ?>
                <span style="display: inline-block; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; padding: 3px 10px;">
                    <i class="fas fa-times-circle"></i> <?php echo TEXT_2FA_DISABLED ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($account['admin_2fa_enabled'] == 1): ?>
        <div class="xline xline-dashed"></div>
        <label class="column a02 tright inline"><strong><?php echo TEXT_2FA_RECOVERY_REMAINING ?></strong></label>
        <div class="column a10">
            <p><?php echo (int)$recoveryCount ?> / 10</p>
        </div>

        <div class="xline xline-dashed"></div>
        <label class="column a02 tright inline">&nbsp;</label>
        <div class="column a10">
            <a class="xbutton hv8 small" href="<?php echo tep_href_link($sUrlPage, 'action=account_2fa_recovery_regen') ?>"><i class="fa fa-rotate"></i> <?php echo TEXT_2FA_BUTTON_REGEN_CODES ?></a>
            <a class="xbutton hv8 small rojo" href="<?php echo tep_href_link($sUrlPage, 'action=account_2fa_disable') ?>"><i class="fa fa-shield-xmark"></i> <?php echo TEXT_2FA_BUTTON_DISABLE ?></a>
        </div>
        <?php else: ?>
        <div class="xline xline-dashed"></div>
        <label class="column a02 tright inline">&nbsp;</label>
        <div class="column a10">
            <a class="xbutton hv8 small verde" href="<?php echo tep_href_link($sUrlPage, 'action=account_2fa_setup') ?>"><i class="fa fa-shield-halved"></i> <?php echo TEXT_2FA_BUTTON_ENABLE ?></a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
return ob_get_clean();
