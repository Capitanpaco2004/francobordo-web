<?php
if (intval($_GET['id']) > 0) {
    $aDato = rmaGetOptionsReturn(intval($_GET['id']));
} else {
    $aDatos = rmaGetOptionsReturn();
}
 ?>
<div class="row ax">
    <div class="oeBox column a03 T06 row ax">
        <div class="oeWrpr">
            <div class="oeTitu">
                <i class="fa fa-user"></i> <?php if (intval($_GET['id']) == 0): ?>Nuevo<?php else: ?>Editar<?php endif; ?>
            </div>

            <form class="oeCntd rows sp10 ax" method="post" action="<?php echo tep_href_link('rma.php', 'action=save-options-return'); ?>">
                <label class="column a04 ">Plazo máximo:</label>
                <div class="column a08">
                    <select name="plazo_maximo" class="skip">
                        <?php $plazos = rmaGetPlazos($aDato['plazo_maximo']); ?>
                        <?php foreach ($plazos as $plazo): ?>
                            <option value="<?php echo intval($plazo['dias']); ?>"<?php echo ($plazo['selected'] == true ? ' selected': ''); ?>><?php echo $plazo['text']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <label class="column a04 ">Mostrar reembolso:</label>
                <div class="column a08">
                    <input class="skip" type="checkbox" name="muestra_reembolso" value="1" <?php echo (intval($aDato['muestra_reembolso']) == 1 ? ' checked="checked"': ''); ?> />
                </div>
                <hr class="column a12" />
                <?php $languages = tep_get_languages(); ?>
                <div class="rmaTabs xform">
                    <?php foreach ($languages as $language): ?>
                        <div class="rmaTabsContent">
                            <label class="column a12" for="<?php echo $language['directory']; ?>">
                                <img src="../includes/languages/<?php echo $language['directory']; ?>/images/<?php echo $language['image']; ?>" />
                                <strong><?php echo $language['name']; ?></strong>
                            </label>
                            <input type="radio" name="rmatab" id="<?php echo $language['directory']; ?>" class="rmaTabsInput" <?php echo ($language['id'] == 3 ? ' checked="checked" ' : ''); ?>/>
                            <div class="rmaTabsContentText">
                                <label class="column a04 ">Texto:</label><div class="column a08"><input autocomplete="off" required name="text[<?php echo $language['id']; ?>]" value="<?php echo $aDato['languages'][$language['id']]['text']; ?>" type="text"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>


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
                            <td>Plazo máximo</td>
                            <td>¿Mostrar reembolso?</td>
                            <td style="width: 20px;"></td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($aDatos as $key => $aDato): ?>
                            <tr class="oeTrProduct">
                                <td style="width: 20px; text-align: center;">
                                    <a href="<?php echo tep_href_link('rma.php', 'action='.$sAction.'&id=' . $aDato['id']); ?>" class="tnegro"><i class="fa fa-edit" style="cursor:  cursor: pointer;position: relative;top: 1px;"></i></a>
                                    <a href="<?php echo tep_href_link('rma.php', 'action=remove-options-return&id=' . $aDato['id']); ?>" class="tnegro rmaRemove"><i class="fa fa-trash tnegro oeDelete" style="cursor: pointer;"></i></a>
                                </td>
                                <td>
                                    <?php echo $aDato['id']; ?>
                                </td>
                                <td>
                                    <?php echo $aDato['text']; ?>
                                </td>
                                <td>
                                    <?php echo intval($aDato['plazo_maximo']); ?> días
                                </td>
                                <td>
                                    <?php echo (intval($aDato['muestra_reembolso']) == 1 ? 'Sí': 'No'); ?>
                                </td>
                                <td>
                                    <a href="<?php echo tep_href_link('rma.php', 'action=active-options-return&id=' . $aDato['id'].'&active='.(intval($aDato['active']) == 0 ? 1 : 0)); ?>" class="<?php echo intval($aDato['active']) == 0 ? 'rmaInactive' : 'rmaActive'; ?> rmaActiveLink">
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
