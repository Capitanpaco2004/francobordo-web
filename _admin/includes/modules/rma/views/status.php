<?php
if (intval($_GET['id']) > 0) {
    $aDato = rmaGetStatus(intval($_GET['id']));
} else {
    $aDatos = rmaGetStatus();
}
?>

<div class="row ax">
    <div class="oeBox column a03 T06 row ax">
        <div class="oeWrpr">
            <div class="oeTitu">
                <i class="fa fa-user"></i> <?php if (intval($_GET['id']) == 0): ?>Nuevo<?php else: ?>Editar<?php endif; ?>
            </div>

            <form class="oeCntd rows sp10 ax xform" method="post" action="<?php echo tep_href_link('rma.php', 'action=save-status'); ?>">
                <label class="column a03 ">Color:</label><div class="column a09"><input autocomplete="off" name="color" type="color" class="colorpicker" value="<?php echo $aDato['color']; ?>"></div>
                <hr class="column a12" />
                <?php $languages = tep_get_languages(); ?>
                <div class="rmaTabs">
                    <?php foreach ($languages as $language): ?>
                        <div class="rmaTabsContent">
                            <label class="column a12" for="<?php echo $language['directory']; ?>">
                                <img src="../includes/languages/<?php echo $language['directory']; ?>/images/<?php echo $language['image']; ?>" />
                                <strong><?php echo $language['name']; ?></strong>
                            </label>
                            <input type="radio" name="rmatab" id="<?php echo $language['directory']; ?>" class="rmaTabsInput" <?php echo ($language['id'] == 3 ? ' checked="checked" ' : ''); ?>/>
                            <div class="rmaTabsContentText">
                                <label class="column a03 ">Texto:</label><div class="column a09"><input autocomplete="off" required name="text[<?php echo $language['id']; ?>]" value="<?php echo $aDato['languages'][$language['id']]['text']; ?>" type="text"></div>
                                <label class="column a03 ">Texto para e-mail:</label><div class="column a09"><textarea name="email_text[<?php echo $language['id']; ?>]"><?php echo $aDato['languages'][$language['id']]['email_text']; ?></textarea></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>


                <?php if (intval($_GET['id']) > 0): ?>
                    <input type="hidden" name="id" value="<?php echo intval($_GET['id']); ?>" />
                <?php endif; ?>
                <button class="column a12 xbutton verde" type="submit">Guardar</button>
                <?php if (intval($_GET['id']) > 0): ?>
                    <input type="hidden" name="id" value="<?php echo intval($_GET['id']); ?>" />
                    <a href="<?php echo tep_href_link('rma.php', 'action='.$sAction); ?>" class="column a12 xbutton rojo">Volver</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php if (intval($_GET['id']) == 0): ?>
    <div class="oeBox column a09 T06 row ax">
        <div class="oeWrpr">
            <div class="oeTitu">
                <i class="fa fa-cog"></i> <?php echo $sTitle; ?>
            </div>

            <div class="oeCntd xform">
                <table>
                    <thead>
                        <tr>
                            <td style="width: 20px; text-align: center;">Acciones</td>
                            <td style="width: 20px; text-align: center;">ID</td>
                            <td>Texto</td>
                            <td>Color</td>
                            <td style="width: 20px;"></td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($aDatos as $key => $aDato): ?>
                            <tr class="oeTrProduct">
                                <td style="width: 20px; text-align: center;">
                                    <a href="<?php echo tep_href_link('rma.php', 'action='.$sAction.'&id=' . $aDato['id']); ?>" class="tnegro"><i class="fa fa-edit" style="cursor:  cursor: pointer;position: relative;top: 1px;"></i></a>
                                    <a href="<?php echo tep_href_link('rma.php', 'action=remove-status&id=' . $aDato['id']); ?>" class="tnegro rmaRemove"><i class="fa fa-trash tnegro oeDelete" style="cursor: pointer;"></i></a>
                                </td>
                                <td>
                                    <?php echo $aDato['id']; ?>
                                </td>
                                <td>
                                    <?php echo $aDato['text']; ?>
                                </td>
                                <td>
                                    <div style="background-color: <?php echo $aDato['color']; ?>; height: 15px; width: 60px;"></div>
                                </td>
                                <td>
                                    <a href="<?php echo tep_href_link('rma.php', 'action=active-status&id=' . $aDato['id'].'&active='.(intval($aDato['active']) == 0 ? 1 : 0)); ?>" class="<?php echo intval($aDato['active']) == 0 ? 'rmaInactive' : 'rmaActive'; ?> rmaActiveLink">
                                        <i class="fas <?php echo intval($aDato['active']) == 1 ? 'fa-times-square' : 'fa-check-square'; ?>" style="cursor:  cursor: pointer;position: relative;top: 1px;"></i> <span><?php echo intval($aDato['active']) == 0 ? 'Activar' : 'Desactivar'; ?></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
