<?php
/*
  $Id: backup.php,v 1.60 2003/06/29 22:50:51 hpdl Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/
  require('includes/application_top.php');

  if( isset( $_GET['m'] ) ) $messageStack->add_session('Se ha completado la importación de productos con éxito', 'success');
?>
<?php require(THEME . 'html/header.php'); ?>
<!-- header_eof //-->

<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
    <td width="<?php echo BOX_WIDTH; ?>" valign="top"><table border="0" width="<?php echo BOX_WIDTH; ?>" cellspacing="1" cellpadding="1" class="columnLeft">
<!-- left_navigation //-->
<?php require(DIR_WS_INCLUDES . 'column_left.php'); ?>
<!-- left_navigation_eof //-->
    </table></td>
<!-- body_text //-->
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
			<?php if( isset( $_GET['e'] ) ) echo '<tr><td><span style="color: red;">*Actualización realizada con éxito</span></td><td class="pageHeading" align="right">' . tep_draw_separator('pixel_trans.gif', 5, 5) .'</td></tr>'; ?>
            <td class="pageHeading">Actualizador de Pedidos Amazon</td>
          </tr>
        </table></td>
      </tr>
	  <tr>
        <td>Haz click en "Insertar" para insertar los pedidos de Amazon.
		<p><a href="https://francobordo.com/amazon/getorders.php" title="Insertar pedidos de Amazon"><img border="0" title="Insertar" alt="Insertar" src="includes/languages/espanol/images/buttons/button_insert.png"></p></a></td>
	 </tr>
	 <tr>
		<td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
     </tr>
	 <tr>
        <td>Haz click en "Actualizar" para iniciar la actualización de pedidos en Amazon.
		<p><a href="https://francobordo.com/amazon/orders_fulfillment.php" title="Actualizador de pedidos Amazon"><img border="0" title="Actualizar" alt="Actualizar" src="includes/languages/espanol/images/buttons/button_update.png"></p></a></td>
	 </tr>
</table>
<!-- body_eof //-->

<!-- footer //-->
<?php require(THEME . 'html/footer.php'); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>