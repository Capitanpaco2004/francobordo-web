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
            <td class="pageHeading">Integración de productos Amazon</td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td>Haz click en "Actualizar" para iniciar la integración de productos en Amazon.
		<p><a href="https://www.francobordo.com/amazon/amazon.php" title="Integracion de productos Amazon"><img border="0" title="Actualizar" alt="Actualizar" src="includes/languages/espanol/images/buttons/button_update.png"></p></a></td>
	</tr>
</table>
<!-- body_eof //-->

<!-- footer //-->
<?php require(THEME . 'html/footer.php'); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>