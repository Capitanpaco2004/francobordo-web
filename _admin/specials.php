<?php
/*
  $Id: specials.php 1739 2007-12-20 00:52:16Z hpdl $
  adapted for Separate Pricing Per Customer v4.0 2007/08/05

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');

	function specials_search($sGetTern,$sGetDescp)
	{
		// Variables
		global $all_groups, $currencies;
		$aKeywords = array();
		$aReturn = array();
		$exclude = array();

		$aDatos = tep_db_query( 'select products_id from products where products_id = "' . (int)$sGetTern . '"' );

		if( tep_db_num_rows($aDatos) > 0 )
		{
			$sWhere = ' and p.products_id = "' . (int)$sGetTern . '"';
		}
		else
		{		
			// Creamos el array de keywords segun lo buscado
			tep_parse_search_string( $sGetTern, $aKeywords );

			if( isset($aKeywords) && (sizeof($aKeywords) > 0) )
			{
				$sWhere .= " and (";

				for( $i = 0, $n = sizeof($aKeywords); $i < $n; $i++ )
				{
					switch ($aKeywords[$i])
					{
						case '(':
						case ')':
						case 'and':
						case 'or':
							$sWhere .= " " . strtolower($aKeywords[$i]) . " ";
						break;

						default:
							$keyword = tep_db_prepare_input($aKeywords[$i]);
							$sWhere .= "(LOWER(pd.products_name) like '%" . strtolower(tep_db_input($keyword)) . "%'";

							if( $sGetDescp != '' )
								$sWhere .= " or LOWER(pd.products_description) like '%" . strtolower(tep_db_input($keyword)) . "%'";
							
							$sWhere .= ')';
						break;
					}
				}

				$sWhere .= " )";
			}
		}

		// Consultamos
		$aDatos = tep_db_query( 'SELECT p.products_id, pd.products_name, p.products_price, p.products_tax_class_id 
								 FROM products p
								 INNER JOIN products_description pd ON(p.products_id = pd.products_id)
								 where pd.language_id = 3 ' . $sWhere . ' order by pd.products_name LIMIT 30' ); 

		while ($products = tep_db_fetch_array($aDatos)) 
		{
			// BOF Separate Price Per Customer
			$price_query = tep_db_query( "select customers_group_price, customers_group_id from " . TABLE_PRODUCTS_GROUPS . " where products_id = " . $products['products_id']);
			$product_prices=array();
			
			while( $prices_array=tep_db_fetch_array($price_query) )
				$product_prices[$prices_array['customers_group_id']] = $prices_array['customers_group_price'];

			$price_string = "";
			$sRel = "";
			$sde=0;
			$sTax = $products['products_tax_class_id'];

			foreach($all_groups as $sdek => $sdev)
			{
				if( !in_array((int)$products['products_id'].":".(int)$sdek, $exclude) )
				{
					if( $sde )
						$price_string .= ", ";
					
					$price_string .= $sdev . ": " . $currencies->format(isset($product_prices[$sdek]) ? $product_prices[$sdek]:$products['products_price']);
					$sRel .= (isset($product_prices[$sdek]) ? $product_prices[$sdek]:$products['products_price']).',';
					$sde=1;
				}
			}

			$price_string = str_replace( '&euro;', '€', $price_string );
			
			$aReturn[] = array( 'tax' => $sTax, 'rel' => substr($sRel, 0, -1), 'id' => $products['products_id'], 'label' => '#' . $products['products_id'] . '# '  . $products['products_name'] . ' (' . $price_string . ')', 'value' => '#' . $products['products_id'] . '# '  . $products['products_name'] . ' (' . $price_string . ')' );
			// EOF 	Separate Pricing Per Customer
		} 

		return $aReturn;
	}
  
  require(DIR_WS_CLASSES . 'currencies.php');
  $currencies = new currencies();
// BOF Separate Pricing Per Customer
      $customers_groups_query = tep_db_query("select customers_group_name, customers_group_id from " . TABLE_CUSTOMERS_GROUPS . " order by customers_group_id ");
    while ($existing_groups = tep_db_fetch_array($customers_groups_query)) {
         $input_groups[] = array("id" => $existing_groups['customers_group_id'], "text" => $existing_groups['customers_group_name']);
        $all_groups[$existing_groups['customers_group_id']] = $existing_groups['customers_group_name'];
    }
// EOF Separate Pricing Per Customer

  $action = (isset($_GET['action']) ? $_GET['action'] : '');

  if (tep_not_null($action)) {
    switch ($action) {
		case 'search':
			$sGetTern = tep_db_prepare_input( $_GET['term'] );
			$sGetDescp = tep_db_prepare_input( $_GET['descp'] );
		
			$aReturn = specials_search( $sGetTern, $sGetDescp );
			
			echo json_encode( $aReturn );
			exit();
		break;
      case 'setflag':
        tep_set_specials_status($_GET['id'], $_GET['flag']);

        tep_redirect(tep_href_link(FILENAME_SPECIALS, (isset($_GET['page']) ? 'page=' . $_GET['page'] . '&' : '') . 'sID=' . $_GET['id'], 'NONSSL'));
        break;
      case 'insert':
        $products_id = tep_db_prepare_input($_POST['products_id']);
        $products_price = tep_db_prepare_input($_POST['products_price']);
        $specials_price = tep_db_prepare_input($_POST['specials_price']);
		$expires_date = tep_db_prepare_input($_POST['expires_date']);
		$customers_group = tep_db_prepare_input($_POST['customers_group']);
		
		// Comprobamos que no este insertado ya el producto
		$aDatos = tep_db_query( 'select specials_id from specials where products_id = "' . (int)$products_id . '" and customers_group_id = "' . (int)$customers_group . '"' );
		if( tep_db_num_rows( $aDatos ) > 0 )
		{
			$messageStack->addSession( 'mensaje', 'Ya existe una oferta para este producto por grupo de cliente', 'eror' );			
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		}
		
		// venta flash
		$venta_flash = tep_db_prepare_input($_POST['venta_flash']);
        $portada_flash = tep_db_prepare_input($_POST['portada_flash']);
        $fecha_inicio = tep_db_prepare_input($_POST['start_date']);
        $hora_inicio = tep_db_prepare_input($_POST['hora_inicio']);
        $hora_fin = tep_db_prepare_input($_POST['hora_fin']);
        $expires_repeat = (isset($_POST['expires_repeat']) && $_POST['expires_repeat'] == '1') ? 1 : 0;

		// venta flash
        $start_date = fecha_mysql($fecha_inicio).' '.$hora_inicio;
        $expires_date = fecha_mysql($expires_date).' '.$hora_fin;
		
		// BOF Separate Pricing Per Customer
        $price_query = tep_db_query("select customers_group_price from " . TABLE_PRODUCTS_GROUPS. " WHERE products_id = ".(int)$products_id . " AND customers_group_id  = ".(int)$customers_group);
        while ($gprices = tep_db_fetch_array($price_query)) {
            $products_price = $gprices['customers_group_price'];
        }
       if (substr($specials_price, -1) == '%' && $customers_group == '0') {
          $new_special_insert_query = tep_db_query("select products_id, products_price from " . TABLE_PRODUCTS . " where products_id = '" . (int)$products_id . "'");
          $new_special_insert = tep_db_fetch_array($new_special_insert_query);

          $products_price = $new_special_insert['products_price'];
          $specials_price = ($products_price - (($specials_price / 100) * $products_price));
       } elseif (substr($specials_price, -1) == '%' && $customers_group != '0') {
 				$specials_price = ($products_price - (($specials_price / 100) * $products_price));
        }
		// EOF Separate Pricing Per Customer

        if (substr($specials_price, -1) == '%') {
          $new_special_insert_query = tep_db_query("select products_id, products_price from " . TABLE_PRODUCTS . " where products_id = '" . (int)$products_id . "'");
          $new_special_insert = tep_db_fetch_array($new_special_insert_query);

          $products_price = $new_special_insert['products_price'];
          $specials_price = ($products_price - (($specials_price / 100) * $products_price));
        }

        // if( $expires_date != '' )
		// {
			// $aAux = explode( '/', $expires_date );
			// $expires_date = $aAux[2] . '-' . $aAux[1] . '-' . $aAux[0] . ' 00:00:00';
        // }

// BOF Separate Pricing Per Customer
    tep_db_query("insert into " . TABLE_SPECIALS . " (products_id, specials_new_products_price, specials_date_added, expires_date, status, customers_group_id) values ('" . (int)$products_id . "', '" . tep_db_input($specials_price) . "', now(), '" . tep_db_input($expires_date) . "', '1', ".(int)$customers_group.")");
	$sId = tep_db_insert_id();
	
	// venta flash
	tep_db_query("update " . TABLE_SPECIALS . " set expires_date = '" . tep_db_input($expires_date) . "',  start_date = '" . tep_db_input($start_date) . "', venta_flash = '" . tep_db_input($venta_flash) . "', portada_flash = '" . tep_db_input($portada_flash) . "', expires_repeat = '" . (int)$expires_repeat . "' where specials_id = '" . (int)$sId . "'");
// EOF Separate Pricing Per Customer

        if( $_GET['pID'] )
            tep_redirect( tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $_GET['cPath'] . '&pID=' . $_GET['pID']) );
        else
            tep_redirect(tep_href_link(FILENAME_SPECIALS, 'page=' . $_GET['page']));
        break;
      case 'update':
        $specials_id = tep_db_prepare_input($_POST['specials_id']);
        $products_price = tep_db_prepare_input($_POST['products_price']);
        $specials_price = tep_db_prepare_input($_POST['specials_price']);
		$expires_date = tep_db_prepare_input($_POST['expires_date']);

		// Venta flash
        $venta_flash = tep_db_prepare_input($_POST['venta_flash']);
        $portada_flash = tep_db_prepare_input($_POST['portada_flash']);
        $fecha_inicio = tep_db_prepare_input($_POST['start_date']);
        $hora_inicio = tep_db_prepare_input($_POST['hora_inicio']);
        $hora_fin = tep_db_prepare_input($_POST['hora_fin']);
        $expires_repeat = (isset($_POST['expires_repeat']) && $_POST['expires_repeat'] == '1') ? 1 : 0;

		// venta flash
        $start_date = fecha_mysql($fecha_inicio).' '.$hora_inicio;
        $expires_date = fecha_mysql($expires_date).' '.$hora_fin;

		if (substr($specials_price, -1) == '%') $specials_price = ($products_price - (($specials_price / 100) * $products_price));

		// venta flash
        tep_db_query("update " . TABLE_SPECIALS . " set specials_new_products_price = '" . tep_db_input($specials_price) . "', specials_last_modified = now(), status = '1', expires_date = '" . tep_db_input($expires_date) . "',  start_date = '" . tep_db_input($start_date) . "', venta_flash = '" . tep_db_input($venta_flash) . "', portada_flash = '" . tep_db_input($portada_flash) . "', expires_repeat = '" . (int)$expires_repeat . "' where specials_id = '" . (int)$specials_id . "'");
		
        
        // if( $expires_date != '' )
		// {
			// $aAux = explode( '/', $expires_date );
			// $expires_date = $aAux[2] . '-' . $aAux[1] . '-' . $aAux[0] . ' 00:00:00';
        // }

        // tep_db_query("update " . TABLE_SPECIALS . " set specials_new_products_price = '" . tep_db_input($specials_price) . "', specials_last_modified = now(), expires_date = '" . tep_db_input($expires_date) . "' where specials_id = '" . (int)$specials_id . "'");


        if( $_GET['pID'] )
            tep_redirect( tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $_GET['cPath'] . '&pID=' . $_GET['pID']) );
        else
            tep_redirect(tep_href_link(FILENAME_SPECIALS, 'page=' . $_GET['page'] . '&sID=' . $specials_id));

        break;
      case 'deleteconfirm':
        $specials_id = tep_db_prepare_input($_GET['sID']);

        tep_db_query("delete from " . TABLE_SPECIALS . " where specials_id = '" . (int)$specials_id . "'");

        tep_redirect(tep_href_link(FILENAME_SPECIALS, 'page=' . $_GET['page']));
        break;
    }
  }
?>

<?php
  if ( ($action == 'new') || ($action == 'edit') ) {
?>
	<link rel="stylesheet" type="text/css" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/smoothness/jquery-ui.min.css">
	<script language="javascript" src="includes/general.js"></script>
<?php
  }
?>


<?php require(THEME . 'html/header.php'); ?>




<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>

    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
          </tr>
        </table></td>
      </tr>
<?php
  if ( ($action == 'new') || ($action == 'edit') ) {
    $form_action = 'insert';
    if ( ($action == 'edit') && isset($_GET['sID']) ) {
      $form_action = 'update';

// BOF Separate Pricing Per Customer
      $product_query = tep_db_query("select p.products_id, p.products_tax_class_id, venta_flash, portada_flash, DATE_FORMAT( s.start_date, '%d/%m/%Y %H:%i:%s') as start_date, pd.products_name, p.products_price, s.specials_new_products_price, DATE_FORMAT( s.expires_date, '%d/%m/%Y %H:%i:%s') as expires_date, s.expires_repeat, s.customers_group_id from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd, " . TABLE_SPECIALS . " s where p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "' and p.products_id = s.products_id and s.specials_id = '" . (int)$_GET['sID'] . "'");

      $product = tep_db_fetch_array($product_query);

      $customer_group_price_query = tep_db_query("select customers_group_price from " . TABLE_PRODUCTS_GROUPS . " where products_id = '" . $product['products_id']. "' and customers_group_id =  '" . $product['customers_group_id'] . "'");
         if ($customer_group_price = tep_db_fetch_array($customer_group_price_query)) {
            $product['products_price']= $customer_group_price['customers_group_price'];
         }
// EOF Separate Pricing Per Customer

      $sInfo = new objectInfo($product);
    } else {
      $sInfo = new objectInfo(array());


    $specials_array = array();
    $specials_query = tep_db_query("select p.products_id, s.customers_group_id from " .  TABLE_PRODUCTS . " p, " . TABLE_SPECIALS . " s where s.products_id = p.products_id limit 1");
    while ($specials = tep_db_fetch_array($specials_query)) {
       $specials_array[] = (int)$specials['products_id'].":".(int)$specials['customers_group_id'];
    }

    if(isset($_GET['sID']) && $sInfo->customers_group_id!= '0') {
        $customer_group_price_query = tep_db_query("select customers_group_price from " . TABLE_PRODUCTS_GROUPS . " where products_id = '" . $sInfo->products_id . "' and customers_group_id =  '" . $sInfo->customers_group_id . "'");
          if ($customer_group_price = tep_db_fetch_array($customer_group_price_query)) {
            $sInfo->products_price = $customer_group_price['customers_group_price'];
          }
       }
    }
?>

      <tr>
	  
        <td>
			<form name="new_special" <?php echo 'action="' . tep_href_link(FILENAME_SPECIALS, tep_get_all_get_params(array('action', 'info', 'sID')) . 'action=' . $form_action, 'NONSSL') . '"'; ?> method="post"><?php if ($form_action == 'update') echo tep_draw_hidden_field('specials_id', $_GET['sID']); ?>
				<br>
				<table border="0" cellspacing="0" cellpadding="2">
					<tr>
						<td class="main">Producto&nbsp;</td>
						<td class="main">
							<?php
								if( array_key_exists( 'pID', $_GET ) && $_GET['pID'] != '' )
								{
									$aReturn = specials_search( $_GET['pID'], false );
																			
									$sInfo->products_id = $_GET['pID'];
									$sInfo->products_name = $aReturn[0]['value'];
								}
							
								if( isset($sInfo->products_name) )
									echo $sInfo->products_name . ' <small>(' . $currencies->format($sInfo->products_price) . ', ' . $currencies->display_price($sInfo->products_price,tep_get_tax_rate($sInfo->products_tax_class_id)) .')</small>';
								else
									echo '<input type="text" id="autocomplete" style="width: 600px;" />';

								echo '<input type="text" id="products_id" name="products_id" style="display: none;" value="' . $sInfo->products_id . '" />';
								echo tep_draw_hidden_field('products_price', (isset($sInfo->products_price) ? $sInfo->products_price : '')); 
								
								if( isset($sInfo->products_name) )
									echo tep_draw_products_pull_down( 'products_id', 'style="display:none;" id="select_products" name="products_id"', array(), $sInfo->products_id, ' and p.products_id = ' . $sInfo->products_id );
							?>
						</td>
					</tr>
					<tr>
						<td class="main"><?php echo TEXT_SPECIALS_2_GROUPS; ?>&nbsp;</td>
						<td class="main">
							<?php
								if (isset($sInfo->customers_group_id)) 
								{
									for ($x=0; $x<count($input_groups); $x++) 
									{
										if ($input_groups[$x]['id'] == $sInfo->customers_group_id) 
										{
											echo $input_groups[$x]['text'];
											echo '<select style="display:none;" id="customers_group"><option value="' . $sInfo->customers_group_id . '">'.$sInfo->customers_group_id.'</option</select>';
										}
									} // end for loop
								}
								else 
								{
									echo tep_draw_pull_down_menu('customers_group', $input_groups, (isset($sInfo->customers_group_id)? $sInfo->customers_group_id:''), 'id="customers_group" onchange="resetMarkdown();updateNetFromMarkdown();updateGross();"' );
								} 
							?>
						</td>
					</tr>
         <tr>
            <td class="main"><?php echo TEXT_SPECIALS_2_SPECIAL_PRICE; ?>&nbsp;</td>
            <td class="main"><?php echo tep_draw_input_field('specials_price', (isset($sInfo->specials_new_products_price) ? $sInfo->specials_new_products_price : ''), 'onKeyUp="updateGross();updateMarkdown()"'); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo TEXT_SPECIALS_2_SPECIAL_PRICE . ' (Gross)'; ?>&nbsp;</td>
            <td class="main"><?php echo tep_draw_input_field('specials_price_gross', (isset($sInfo->specials_new_products_price) ? $sInfo->specials_new_products_price : ''), 'OnKeyUp="updateNet();updateMarkdown()"'); ?></td>
          </tr>
          <tr>
            <td class="main">Porcentaje:</td>
            <td class="main"><?php echo tep_draw_input_field('specials_markdown', (isset($sInfo->specials_new_products_price) ? $sInfo->specials_new_products_price : ''), 'OnKeyUp="updateNetFromMarkdown();updateGross();"'); ?></td>
          </tr>
		  
		  <tr><td height="30" colspan="2" style="vertical-align: middle;" align="center"><b>---- Oferta Express ----</b></td></tr>
	
          <tr>
            <td class="main">Oferta Express:</td>
            <td class="main"><?php echo tep_draw_pull_down_menu('venta_flash', array( array( 'id' => 0, 'text' => 'No' ), array( 'id' => 1, 'text' => 'Si' ) ), $sInfo->venta_flash); ?></td>
          </tr>	

          <tr>
            <td class="main">Oferta Express portada:</td>
            <td class="main"><?php echo tep_draw_pull_down_menu('portada_flash', array( array( 'id' => 0, 'text' => 'No' ), array( 'id' => 1, 'text' => 'Si' ) ), $sInfo->portada_flash); ?></td>
          </tr>	
		  
		 <tr>
            <td class="main">Fecha inicio</td>
            <td class="main">
				<?php 
					$aAux = explode( ' ', (string) $sInfo->start_date );
					echo tep_draw_input_field( 'start_date', (isset($sInfo->start_date) ? $aAux[0] : ''), 'size="2" style="width: 80px !important" maxlength="2" class="dxdatepicker cal-TextBox"' );
				?>
				<input id="hora_inicio" name="hora_inicio" value="<?php echo (isset($sInfo->start_date) ? $aAux[1] : '00:00'); ?>" />
			</td>
          </tr>
		  
         <tr>
            <td class="main"><?php echo TEXT_SPECIALS_2_EXPIRES_DATE; ?>&nbsp;</td>
            <td class="main">
				<?php
					$aAux = explode( ' ', (string) $sInfo->expires_date );
					echo tep_draw_input_field( 'expires_date', (isset($sInfo->expires_date) ? $aAux[0] : ''), 'size="2" style="width: 80px !important" maxlength="2" class="dxdatepicker cal-TextBox"' );
				?>
				<input id="hora_fin" name="hora_fin" value="<?php echo (isset($sInfo->expires_date) ? $aAux[1] : '00:00'); ?>" />
			</td>
          </tr>

          <tr>
            <td class="main">Repetir oferta (cada 14 d&iacute;as):</td>
            <td class="main"><?php echo tep_draw_pull_down_menu('expires_repeat', array( array( 'id' => 0, 'text' => 'No' ), array( 'id' => 1, 'text' => 'Si' ) ), (isset($sInfo->expires_repeat) ? $sInfo->expires_repeat : 0)); ?></td>
          </tr>

			<tr>
				<td colspan="2" class="main" align="right" valign="top"><br><?php echo (($form_action == 'insert') ? tep_image_submit('button_insert.png', IMAGE_INSERT) : tep_image_submit('button_update.png', IMAGE_UPDATE)). '&nbsp;&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_SPECIALS, 'page=' . $_GET['page'] . (isset($_GET['sID']) ? '&sID=' . $_GET['sID'] : '')) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>'; ?></td>
			</tr>
		  
          <tr>
			<td colspan="2" class="main"><br><?php echo TEXT_SPECIALS_2_PRICE_TIP; ?></td>
          </tr>
        </table></form></td>
      </tr>
<?php
  } else {
?>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCTS; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_PRODUCTS_PRICE; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_STATUS; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ACTION; ?>&nbsp;</td>
              </tr>
<?php
// BOF Separate Pricing Per Customer
    $all_groups = array();
    $customers_groups_query = tep_db_query("select customers_group_name, customers_group_id from " . TABLE_CUSTOMERS_GROUPS . " order by customers_group_id ");
    while ($existing_groups =  tep_db_fetch_array($customers_groups_query)) {
      $all_groups[$existing_groups['customers_group_id']] = $existing_groups['customers_group_name'];
    }

   $specials_query_raw = "select p.products_id, pd.products_name, p.products_price, p.products_tax_class_id, s.specials_id, s.customers_group_id, s.specials_new_products_price, s.specials_date_added, s.specials_last_modified, s.expires_date, s.date_status_change, s.status from " . TABLE_PRODUCTS . " p, " . TABLE_SPECIALS . " s, " . TABLE_PRODUCTS_DESCRIPTION . " pd where p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "' and p.products_id = s.products_id order by pd.products_name";

   $customers_group_prices_query = tep_db_query("select s.products_id, s.customers_group_id, pg.customers_group_price from " . TABLE_SPECIALS . " s LEFT JOIN " . TABLE_PRODUCTS_GROUPS . " pg using (products_id, customers_group_id) ");

 while ($_customers_group_prices = tep_db_fetch_array($customers_group_prices_query)) {
 $customers_group_prices[] = $_customers_group_prices;
 }
    $specials_split = new splitPageResults($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS, $specials_query_raw, $specials_query_numrows);
    $specials_query = tep_db_query($specials_query_raw);
// BOF Separate Pricing Per Customer
    $no_of_rows_in_specials = tep_db_num_rows($specials_query);
    while ($specials = tep_db_fetch_array($specials_query)) {
    for ($y = 0; $y < $no_of_rows_in_specials; $y++) {
    if ( tep_not_null($customers_group_prices[$y]['customers_group_price']) && $customers_group_prices[$y]['products_id'] == $specials['products_id'] && $customers_group_prices[$y]['customers_group_id'] == $specials['customers_group_id']) {
    $specials['products_price'] = $customers_group_prices[$y]['customers_group_price'] ;
    } // end if (tep_not_null($customers_group_prices[$y]['customers_group_price'] etcetera
    } // end for loop
// EOF Separate Pricing Per Customer
      if ((!isset($_GET['sID']) || (isset($_GET['sID']) && ($_GET['sID'] == $specials['specials_id']))) && !isset($sInfo)) {
        $products_query = tep_db_query("select products_image from " . TABLE_PRODUCTS . " where products_id = '" . (int)$specials['products_id'] . "'");
        $products = tep_db_fetch_array($products_query);
        $sInfo_array = array_merge($specials, $products);
        $sInfo = new objectInfo($sInfo_array);
      }

      if (isset($sInfo) && is_object($sInfo) && ($specials['specials_id'] == $sInfo->specials_id)) {
        echo '                  <tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_SPECIALS, 'page=' . $_GET['page'] . '&sID=' . $sInfo->specials_id . '&action=edit') . '\'">' . "\n";
      } else {
        echo '                  <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_SPECIALS, 'page=' . $_GET['page'] . '&sID=' . $specials['specials_id']) . '\'">' . "\n";
      }
?>
                <td  class="dataTableContent"><?php echo $specials['products_name']; ?></td>
<!-- BOF Separate Pricing Per Customer -->
                <td  class="dataTableContent" align="right"><span class="oldPrice"><?php echo $currencies->format($specials['products_price']); ?></span> <span class="specialPrice"><?php echo $currencies->format($specials['specials_new_products_price']) . " (".  $all_groups[$specials['customers_group_id']] . ")"; ?></span></td>
<!-- EOF Separate Pricing per Customer -->
                <td  class="dataTableContent" align="right">
<?php
      if ($specials['status'] == '1') {
        echo tep_image(DIR_WS_IMAGES . 'icon_status_green.png', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_SPECIALS, 'action=setflag&flag=0&id=' . $specials['specials_id'] . (isset($_GET['page']) ? '&page=' . $_GET['page'] : ''), 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
      } else {
        echo '<a href="' . tep_href_link(FILENAME_SPECIALS, 'action=setflag&flag=1&id=' . $specials['specials_id'] . (isset($_GET['page']) ? '&page=' . $_GET['page'] : ''), 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.png', IMAGE_ICON_STATUS_RED, 10, 10);
      }
?></td>
                <td class="dataTableContent" align="right"><?php if (isset($sInfo) && is_object($sInfo) && ($specials['specials_id'] == $sInfo->specials_id)) { echo tep_image(DIR_WS_IMAGES . 'icon_arrow_right.png', ''); } else { echo '<a href="' . tep_href_link(FILENAME_SPECIALS, 'page=' . $_GET['page'] . '&sID=' . $specials['specials_id']) . '">' . tep_image(DIR_WS_IMAGES . 'icon_info.png', IMAGE_ICON_INFO) . '</a>'; } ?>&nbsp;</td>
      </tr>
<?php
    }
?>
              <tr>
                <td colspan="4"><table class="table-page" border="0" width="100%" cellpadding="0"cellspacing="2">
                  <tr>
                    <td class="smallText" valign="top"><?php echo $specials_split->display_count($specials_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $_GET['page'],  TEXT_DISPLAY_NUMBER_OF_SPECIALS); ?></td>
                    <td class="smallText" align="right"><?php echo $specials_split->display_links($specials_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['page']); ?></td>
                  </tr>
<?php
  if (empty($action)) {
?>
                  <tr>
                    <td colspan="2" align="right"><?php echo '<a href="' . tep_href_link(FILENAME_SPECIALS, 'page=' . $_GET['page'] . '&action=new') . '">' . tep_image_button('button_new_product.png', IMAGE_NEW_PRODUCT) . '</a>'; ?></td>
                  </tr>
<?php
  }
?>
                </table></td>
              </tr>
            </table></td>
<?php
  $heading = array();
  $contents = array();

  switch ($action) {
    case 'delete':
      $heading[] = array('text' => '<b>' . TEXT_INFO_HEADING_DELETE_SPECIALS_2 . '</b>');

      $contents = array('form' => tep_draw_form('specials', FILENAME_SPECIALS, 'page=' . $_GET['page'] . '&sID=' . $sInfo->specials_id . '&action=deleteconfirm'));
      $contents[] = array('text' => TEXT_INFO_DELETE_INTRO);
      $contents[] = array('text' => '<br><b>' . $sInfo->products_name . '</b>');
      $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_delete.png', IMAGE_DELETE) . '&nbsp;<a href="' . tep_href_link(FILENAME_SPECIALS, 'page=' . $_GET['page'] . '&sID=' . $sInfo->specials_id) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>');
      break;
    default:
      if (is_object($sInfo)) {
        $heading[] = array('text' => '<b>' . $sInfo->products_name . '</b>');

        $contents[] = array('align' => 'center', 'text' => '<a href="' . tep_href_link(FILENAME_SPECIALS, 'page=' . $_GET['page'] . '&sID=' . $sInfo->specials_id . '&action=edit') . '">' . tep_image_button('button_edit.png', IMAGE_EDIT) . '</a> <a href="' . tep_href_link(FILENAME_SPECIALS, 'page=' . $_GET['page'] . '&sID=' . $sInfo->specials_id . '&action=delete') . '">' . tep_image_button('button_delete.png', IMAGE_DELETE) . '</a>');
        $contents[] = array('text' => '<br>' . TEXT_INFO_DATE_ADDED . ' ' . tep_date_short($sInfo->specials_date_added));
        $contents[] = array('text' => '' . TEXT_INFO_LAST_MODIFIED . ' ' . tep_date_short($sInfo->specials_last_modified));
        $contents[] = array('align' => 'center', 'text' => '<br>' . tep_info_image('/productos/'.$sInfo->products_image, $sInfo->products_name, SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT));
        $contents[] = array('text' => '<br>' . TEXT_INFO_ORIGINAL_PRICE . ' ' . $currencies->format($sInfo->products_price));
        $contents[] = array('text' => '' . TEXT_INFO_NEW_PRICE . ' ' . $currencies->format($sInfo->specials_new_products_price));
// BOF edited for SPPC where a product price can be 0 if there is no group price, gives a warning divison by zero
        $contents[] = array('text' => '' . TEXT_INFO_PERCENTAGE . ' ' . number_format(100 - (($sInfo->specials_new_products_price / ($sInfo->products_price == 0 ? $sInfo->specials_new_products_price : $sInfo->products_price)) * 100)) . '%');
// EOF edited for SPPC

        $contents[] = array('text' => '<br>' . TEXT_INFO_EXPIRES_DATE . ' <b>' . tep_date_short($sInfo->expires_date) . '</b>');
        $contents[] = array('text' => '' . TEXT_INFO_STATUS_CHANGE . ' ' . tep_date_short($sInfo->date_status_change));
      }
      break;
  }
  if ( (tep_not_null($heading)) && (tep_not_null($contents)) ) {
    echo '            <td width="25%" valign="top">' . "\n";

    $box = new box;
    echo $box->infoBox($heading, $contents);

    echo '            </td>' . "\n";
  }
}
?>
          </tr>
        </table></td>
      </tr>
    </table></td>
<!-- body_text_eof //-->
  </tr>
</table>
<!-- body_eof //-->

<!-- footer //-->
<?php require(THEME . 'html/footer.php'); ?>

<script type="text/javascript">
	$.extend({
		error: function( msg ) { throw msg; },
		parseJSON: function( data ) {
			if ( typeof data !== "string" || !data ) {
				return null;
			}    
			data = jQuery.trim( data );    
			if ( /^[\],:{}\s]*$/.test(data.replace(/\\(?:["\\\/bfnrt]|u[0-9a-fA-F]{4})/g, "@")
				.replace(/"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g, "]")
				.replace(/(?:^|:|,)(?:\s*\[)+/g, "")) ) {    
				return window.JSON && window.JSON.parse ?
					window.JSON.parse( data ) :
					(new Function("return " + data))();    
			} else {
				jQuery.error( "Invalid JSON: " + data );
			}
		}
	});


	<?php
		$tax_class_query = tep_db_query("select tax_class_id, tax_class_title from " . TABLE_TAX_CLASS . " order by tax_class_title");
		while ($tax_class = tep_db_fetch_array($tax_class_query)){
			$tax_class_array[] = array('id' => $tax_class['tax_class_id'], 'text' => $tax_class['tax_class_title']);
		}
	?>

	var tax_rates = new Array();
	var products_tax_class = new Array();
	var products_price = new Array();
	var customers_group = jQuery.parseJSON('<?php echo json_encode( $input_groups ); ?>');
	
	<?php
		for ($i=0, $n=sizeof($tax_class_array); $i<$n; $i++)
		{
			if ($tax_class_array[$i]['id'] > 0)
				echo 'tax_rates["' . $tax_class_array[$i]['id'] . '"] = ' . tep_get_tax_rate_value($tax_class_array[$i]['id']) . ';';
		}
	?>


	$("document").ready(function()
	{
		Globalize.culture( "de-DE" );

		// Timer
		$.widget( "ui.timespinner", $.ui.spinner, 
		{
			options: 
			{
				// seconds
				step: 60 * 1000,
				// hours
				page: 60
			},
			_parse: function( value ) 
			{
				if ( typeof value === "string" ) 
				{
					// already a timestamp
					if ( Number( value ) == value ) 
						return Number( value );
					
					return +Globalize.parseDate( value );
				}

				return value;
			},
			_format: function( value ) 
			{
				return Globalize.format( new Date(value), "t" );
			}
		});
		
		$( "#hora_inicio" ).timespinner();
		$( "#hora_fin" ).timespinner();
	
		// Autocomplete products
		$("#autocomplete").autocomplete(
		{
			source: "specials.php?action=search",
			minLength: 4,
			select: function( event, ui ) 
			{			
				var sID = ui.item.id;
				var aPrices = ui.item.rel.split(",");

				products_tax_class[sID] = ui.item.tax;
				products_price[sID] = new Array();

				$(aPrices).each(function(nCont,sValor)
				{
					products_price[sID][customers_group[nCont]["id"]] = sValor
				});
				
				$('input[name="products_id"]').val(sID);
				$('select[name="customers_group"]').trigger("change");
				
				// updateGross();
				// updateMarkdown();
			}
		});	

		<?php
			if( array_key_exists( 'pID', $_GET ) && $_GET['pID'] != '' )
			{
				$aReturn = specials_search( $_GET['pID'], false );
				
				echo '
					var sID = ' . $_GET['pID'] . ';
					var aPrices = [' . $aReturn[0]['rel'] . '];

					products_tax_class[sID] = ' . $aReturn[0]['tax'] . ';
					products_price[sID] = new Array();

					$(aPrices).each(function(nCont,sValor)
					{
						products_price[sID][customers_group[nCont]["id"]] = sValor
					});

					$(\'input[name="products_id"]\').val(sID);
					$(\'select[name="customers_group"]\').trigger("change");
				';
			}
		?>		
	
		// Creamos el array con los precios
		var dmOptions = $("#select_products option");
		
		if( dmOptions.length > 0 )
		{
			dmOptions.each(function(index, elmt)
			{
				var sID = $(elmt).val();
				var aPrices = $(elmt).attr("rel").split(",");
			
				products_tax_class[sID] = $(elmt).attr("data-tax");
				products_price[sID] = new Array();

				$(aPrices).each(function(nCont,sValor)
				{
					products_price[sID][customers_group[nCont]["id"]] = sValor
				});
			});
			
			updateGross();
			updateMarkdown();
		}
	});

	function doRound(x, places) {
	  return Math.round(x * Math.pow(10, places)) / Math.pow(10, places);
	}

	function getTaxRate() {
	  var productsID = $('input[name="products_id"]').val();
	  var parameterVal = products_tax_class[productsID];

	  if ( (parameterVal > 0) && (tax_rates[parameterVal] > 0) ) {
		return tax_rates[parameterVal];
	  } else {
		return 0;
	  }
	}


	function updateGross() {
	  var taxRate = getTaxRate();
	  var grossValue = document.forms["new_special"].specials_price.value;

	  if (taxRate > 0) {
		grossValue = grossValue * ((taxRate / 100) + 1);
	  }

	  document.forms["new_special"].specials_price_gross.value = doRound(grossValue, 4);
	}


	function updateNet() {
	  var taxRate = getTaxRate();
	  var netValue = document.forms["new_special"].specials_price_gross.value;

	  if (taxRate > 0) {
		netValue = netValue / ((taxRate / 100) + 1);
	  }

	  document.forms["new_special"].specials_price.value = doRound(netValue, 4);
	}

	function updateNetFromMarkdown() {
	  var productsID = $('input[name="products_id"]').val();	  
	  var markdown = document.forms["new_special"].specials_markdown.value;
	  var nIdGroup = $("#customers_group").val();
	  
	  if( products_price[productsID][nIdGroup] )
		var productsPrice = products_price[productsID][nIdGroup];
	  else
		var productsPrice = products_price[productsID][0];

	  if ( (markdown >= 0) && (markdown <= 100) ) {
		netValue = productsPrice * (1 - markdown/100);
	  }
	  document.forms["new_special"].specials_price.value = doRound(netValue, 4);
	}

	function updateMarkdown() {
	  var productsID = $('input[name="products_id"]').val();
	  var netValue = document.forms["new_special"].specials_price.value;

	  var nIdGroup = $("#customers_group").val();
	  if( products_price[productsID][nIdGroup] )
		var productsPrice = products_price[productsID][nIdGroup];
	  else
		var productsPrice = products_price[productsID][0];  
	  
	  markdown = (1 - netValue/productsPrice) * 100;
	  
	  document.forms["new_special"].specials_markdown.value = doRound(markdown, 2);

	}

	function resetMarkdown() {
	  
	  document.forms["new_special"].specials_markdown.value = 0;

	}
</script>

</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>