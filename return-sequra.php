<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
*/

require('includes/application_top.php');
if ($cart->count_contents() < 1) {
	tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS));
}
tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT,"error_message=No se ha podido realizar el pago."));
require(DIR_WS_INCLUDES . 'application_bottom.php');