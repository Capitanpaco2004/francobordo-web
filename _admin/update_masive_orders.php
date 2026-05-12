<?php
/*
  $Id: create_order.php,v 1 2003/08/17 23:21:34 frankl Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License
*/

require('includes/application_top.php');

//Definimos ruta para las clases
define('PATH_UPDATE_MASIVE_ORDERS', 'includes/modules/update_masive_orders/');

//Incluimos clases
require(PATH_UPDATE_MASIVE_ORDERS . 'update_masive_orders.php');
require(PATH_UPDATE_MASIVE_ORDERS . 'importador.php');

$importador = new importadorPedidos;
?>

<?php require(THEME . 'html/header.php'); ?>
            <div class="fluid grid">
                <div class="box-tbl grid6">
                    <div class="box-head">
                        <h6>Actualizar Estados Masivos</h6>
                        <div class="clear"></div>
                    </div>
                    <form action="<?php echo tep_href_link('update_masive_orders.php', 'action=import');?>" method="post" enctype="multipart/form-data">
                        <div class="formRow">
                            <div class="grid3">
                                <label>Transportista:</label>
                            </div>
                            <div class="grid6">
                                <select name="transportista">
                                    <option value="">Seleccione transportista</option>
                                    <?php foreach ($importador->modules as $transportista): ?>
                                        <option value="<?php echo $transportista['value']; ?>"><?php echo $transportista['text']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="clear"></div>
                        </div>
                        <div class="formRow">
                            <div class="grid3">
                                <label>Archivo:</label>
                            </div>
                            <div class="grid6">
                                <input type="file" name="archivo" />
                            </div>
                            <div class="grid1">
                                <div class="wButton grid6">
                                    <button type="submit" class="buttonL bGreen">Enviar</button>
                                </div>
                            </div>
                            <div class="clear"></div>
                        </div>

                    </form>
                </div>
            </div>

            <?php $logs = $importador->getLogs(); ?>
            <?php if (!empty($logs)): ?>
                <div class="fluid grid">
                    <div class="box-tbl grid6">
                        <div class="box-head">
                            <h6>Log</h6>
                            <div class="clear"></div>
                        </div>
                        <table style="width: 100%" class="tAlt wGeneral tDefault">
                            <tr>
                                <td>
                                    Fecha
                                </td>
                                <td>
                                    Log
                                </td>
                                <td width="50">
                                </td>
                                <td width="50">
                                </td>
                            </tr>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <?php echo $log['fecha']; ?>
                                </td>
                                <td>
                                    <?php echo $log['archivo']; ?>
                                </td>
                                <td>
                                    <a href="<?php echo tep_href_link('update_masive_orders.php', 'action=delete-log&log=' . base64_encode($log['archivo'])); ?>" class="buttonS bRed">Borrar</a>
                                </td>
                                <td>
                                    <a href="<?php echo tep_href_link('update_masive_orders.php', 'action=view-log&log=' . base64_encode($log['archivo'])); ?>#view-log" class="buttonS bGreen">Ver</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($importador->log != ''): ?>
                <div class="fluid grid" id="view-log">
                    <div class="box-tbl grid12">
                        <div class="box-head">
                            <h6>Log</h6>
                            <a href="<?php echo tep_href_link('update_masive_orders.php'); ?>" class="buttonS bGreen" style="margin-top: 3px; float: right; margin-right: 3px;">Cerrar</a>
                            <div class="clear"></div>
                        </div>
                        <pre class="log" style="padding: 20px;"><?php echo $importador->log; ?></pre>
                    </div>
                </div>
            <?php endif; ?>
<?php
    $sJavascript = '';

    require(THEME . 'html/footer.php');
?>
<?php
require(DIR_WS_INCLUDES . 'application_bottom.php');
