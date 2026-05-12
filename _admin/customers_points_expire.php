<?php
/*
  $Id: customers_points_expire.php, V2.1rc2a 2008/SEP/29 11:05:12 dsa_ Exp $
  created by Ben Zukrel, Deep Silver Accessories
  http://www.deep-silver.com

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2005 osCommerce

  Released under the GNU General Public License
*/

  include_once('includes/application_top.php');

      tep_mail('Cron Jobs', 'f.rodriguez@francobordo.com', 'Cron Job de archivo customers_point_expire.php', 'Se acaba de ejecutar el cron', 'Cron Francobordo', 'cron@francobordo.com');

  //Insertar precios que no existian en Grupo
  tep_db_query("insert into " . TABLE_PRODUCTS_GROUPS . "(customers_group_id, customers_group_price, products_id, products_qty_blocks, products_min_order_qty) select 1, products_price*0.9, products_id, products_qty_blocks, products_min_order_qty from " . TABLE_PRODUCTS ." where products_id not in (select products_id from ". TABLE_PRODUCTS_GROUPS . " where customers_group_id=1) ");
  echo 'Insertados Precios que no existian en distribución<br>';
  tep_db_query("insert into " . TABLE_PRODUCTS_GROUPS . "(customers_group_id, customers_group_price, products_id, products_qty_blocks, products_min_order_qty) select 2, products_price*1.1, products_id, products_qty_blocks, products_min_order_qty from " . TABLE_PRODUCTS ." where products_id not in (select products_id from ". TABLE_PRODUCTS_GROUPS . " where customers_group_id=2) ");
    echo 'Insertados Precios que no existian en Amazon<br>';
  tep_db_query("insert into " . TABLE_PRODUCTS_GROUPS . "(customers_group_id, customers_group_price, products_id, products_qty_blocks, products_min_order_qty) select 3, products_price*1.1, products_id, products_qty_blocks, products_min_order_qty from " . TABLE_PRODUCTS ." where products_id not in (select products_id from ". TABLE_PRODUCTS_GROUPS . " where customers_group_id=3) ");
    echo 'Insertados Precios que no existian en Ebay<br>';

  tep_db_query("delete from " . TABLE_PRODUCTS_GROUPS . " where products_id not in (select products_id from ". TABLE_PRODUCTS . ")");


  	//Insertar Atributos que no existen en Distribución
	tep_db_query("insert into " . TABLE_PRODUCTS_ATTRIBUTES_GROUPS . "(products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id) select products_attributes_id, 1, options_values_price*0.9, price_prefix, products_id from
". TABLE_PRODUCTS_ATTRIBUTES. " where products_attributes_id not in (select products_attributes_id from ". TABLE_PRODUCTS_ATTRIBUTES_GROUPS . " where customers_group_id=1)");
	 echo 'Insertados Precios Atributos que no existian en distribucion<br>';

	tep_db_query("insert into " . TABLE_PRODUCTS_ATTRIBUTES_GROUPS . "(products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id) select products_attributes_id, 2, options_values_price*1.1, price_prefix, products_id from
". TABLE_PRODUCTS_ATTRIBUTES. " where products_attributes_id not in (select products_attributes_id from ". TABLE_PRODUCTS_ATTRIBUTES_GROUPS . " where customers_group_id=2)");

	echo 'Insertados Precios Atributos que no existian en Amazon<br>';
		tep_db_query("insert into " . TABLE_PRODUCTS_ATTRIBUTES_GROUPS . "(products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id) select products_attributes_id, 3, options_values_price*1.1, price_prefix, products_id from
". TABLE_PRODUCTS_ATTRIBUTES. " where products_attributes_id not in (select products_attributes_id from ". TABLE_PRODUCTS_ATTRIBUTES_GROUPS . " where customers_group_id=3)");

	echo 'Insertados Precios Atributos que no existian en Ebay<br>';

     tep_db_query("delete from " . TABLE_PRODUCTS_ATTRIBUTES_GROUPS . " where products_attributes_id not in (select products_attributes_id from ". TABLE_PRODUCTS_ATTRIBUTES . ")");

  	//Borrar productos destacados caducados
	tep_db_query("update " . TABLE_PRODUCTS . " set products_featured =  '0',products_featured_until = '0000-00-00' where  products_featured = 1 and (products_featured_until is null or products_featured_until= '0000-00-00')") ;
 echo 'Actualizados productos destacados<br>';
	//Borrar ofertas caducadas y de productos descatalogados
	tep_db_query ("UPDATE " . TABLE_PRODUCTS . " SET products_last_modified = NOW() WHERE products_id in (SELECT products_id from " . TABLE_SPECIALS . " WHERE expires_date < CURDATE() AND expires_date != '0000-00-00 00:00:00' )");
	tep_db_query("DELETE FROM " . TABLE_SPECIALS . " WHERE expires_date < CURDATE() AND expires_date != '0000-00-00 00:00:00' ");
 echo 'Borradas ofertas caducadas<br>';
	tep_db_query("delete from " . TABLE_SPECIALS . " WHERE products_id in (SELECT products_id FROM products WHERE products_status=0)");
 echo 'Borradas ofertas productos descatalogados<br>';


//Comienza Sistema de Puntos
if ((USE_POINTS_SYSTEM == 'true') && tep_not_null(POINTS_AUTO_EXPIRES)){
  tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = null, customers_points_expires = null where customers_points_expires < CURDATE()");
 echo 'Borrados puntos expirados!<br>';

  if (tep_not_null(POINTS_EXPIRES_REMIND)){

    include_once(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CUSTOMERS_POINTS_PENDING);

 //   $customer_query = tep_db_query("select customers_gender, customers_lastname, customers_firstname, customers_email_address, customers_shopping_points, customers_points_expires from " . TABLE_CUSTOMERS . " where (CURDATE() + '". (int)POINTS_EXPIRES_REMIND ."') = customers_points_expires and createaccount ='Y'");
    $sSql = "SELECT c.customers_gender, c.customers_lastname, c.customers_firstname, c.customers_email_address, c.customers_shopping_points, c.customers_points_expires FROM customers c INNER JOIN rgpd_account_term rgat on(rgat.customers_id = c.customers_id) WHERE rgat.id_term_pivacy_trade = 4 AND c.customers_points_expires = ADDDATE( CURDATE(), INTERVAL ". (int)POINTS_EXPIRES_REMIND ." DAY)";
    $customer_query = tep_db_query($sSql);
    while($customer = tep_db_fetch_array($customer_query)){
    $customers_email_address = $customer['customers_email_address'];
    $gender = $customer['customers_gender'];
    $first_name = $customer['customers_firstname'];
    $last_name = $customer['customers_lastname'];
    $name = $first_name . ' ' . $last_name;

    if (ACCOUNT_GENDER == 'true') {
      if ($gender == 'f') {
        $greet = sprintf(EMAIL_GREET_MS, $first_name .' '.$last_name);
      } else {
        $greet = sprintf(EMAIL_GREET_MR, $first_name .' '.$last_name);
      }
    } else {

    $greet = sprintf(EMAIL_GREET_NONE, $first_name);
    }
	$email = '<span style="padding: 0 30px; font-size: 18px; font-weight: bold; line-height: 14px; color: #1fa1d0;">' . $greet . '</span><br><br>
			  <span style="padding: 0px 30px; display: block; line-height: 14px;">' . EMAIL_EXPIRE_INTRO . '</span><br><br>
			  <br><br>
			  <span style="padding: 0 0 0 30px; display: block; line-height: 16px;">' .
				  sprintf(EMAIL_EXPIRE_DET, number_format($customer['customers_shopping_points'],POINTS_DECIMAL_PLACES)) .
				  '<br>' .
				  sprintf(EMAIL_EXPIRE_DET2, tep_date_short($customer['customers_points_expires'])) .
				  '<br><br>' .
				  sprintf(EMAIL_TEXT_POINTS_URL, tep_catalog_href_link(FILENAME_CATALOG_MY_POINTS, '', 'SSL'), tep_catalog_href_link(FILENAME_CATALOG_MY_POINTS, '', 'SSL')) .
				  '<br><br>' .
				  sprintf(EMAIL_TEXT_POINTS_URL_HELP, tep_catalog_href_link(FILENAME_CATALOG_MY_POINTS_HELP, '', 'NONSSL'), tep_catalog_href_link(FILENAME_CATALOG_MY_POINTS_HELP, '', 'NONSSL')) .
				  '<br><br>' .
				  EMAIL_TEXT_SUCCESS_POINTS .
				  '<br>' .
				  EMAIL_CONTACT .
				  '<br>' .
				  EMAIL_SEPARATOR .
				  '<br><br>' .
				  EMAIL_AUTO .
			  '</span>';

	include( '../includes/languages/espanol.php' );
	include( '../' . DIR_WS_MODULES . 'UHtmlEmails/'. ULTIMATE_HTML_EMAIL_LAYOUT .'/varios.php' );
	$email = $sHtmlEmail;
    tep_mail($name, $customer['customers_email_address'], EMAIL_EXPIRE_SUBJECT, $email, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
    }
    echo 'Avisado Clientes de que expiran sus puntos!';
    // Email informativo
mail( 'f.rodriguez@francobordo.com', '[CORRECTO] Customers_points_expire Francobordo', 'Se ha actualizado con exito la tienda Francobordo.', 'MIME-Version: 1.0' . "\r\n" . 'Content-type: text/plain; charset=UTF-8' . "\r\n" . 'From:info@francobordo.com' . "\r\n" );

  }
}
?>
