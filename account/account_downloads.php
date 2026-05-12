<?php
	// Aplicacion
	include( 'includes/application.php' );
	
	// Function para crear csv
	function outputCSV($data)
	{
		ob_start();
		$output = fopen("php://output", "a+");
		foreach ($data as $row)
			fputcsv( $output, $row );
		fclose($output);

		$a = ob_get_contents();
		ob_end_clean();	

		return mb_convert_encoding($a, 'UTF-16LE', 'UTF-8');;
	}
	
	// Si contenemos algo por post
	if( $_SERVER['REQUEST_METHOD'] === 'POST' )
	{ 
		// Variables
		$aPostDownload = tep_db_prepare_input( $_POST['downloads'] );
		$aTypes = array(
			array(
				'filename' => getSlug( AD_TITU_1, '_' ),
				'tables' => array( 'customers', 'address_book' ),
				'rows' => array(
					array(
						'customers_firstname' => 'Firtname',
						'customers_lastname' => 'Lastname',
						'customers_dob' => 'Birthday_date',
						'customers_email_address' => 'E_mail',
						'customers_telephone' => 'Telephone'
					),
					array(
						'entry_company' => 'Company',
						'entry_NIF' => 'NIF',
						'entry_firstname' => 'Firtname',
						'entry_lastname' => 'Lastname',
						'entry_street_address' => 'Address',
						'entry_postcode' => 'Postcode',
						'cities.name' => 'City',
						'zones.zone_name' => 'Zone',
						'countries.countries_name' => 'countries',
					)
				)
			),
			array(
				'filename' => getSlug( AD_TITU_2, '_' ),
				'tables' => array( 'orders', 'orders_products'),
				'rows' => array(
					array(
						'orders_id' => 'Orders ID',
						'customers_name' => 'Name',
						'customers_company' => 'Company',
						'customers_street_address' => 'Address',
						'customers_city' => 'City',
						'customers_postcode' => 'Postcode',
						'customers_state' => 'State',
						'customers_country' => 'Country',
						'customers_telephone' => 'Telephone',
						'customers_email_address' => 'E_mail',
						'delivery_name' => 'Name',
						'delivery_company' => 'Company',
						'delivery_street_address' => 'Address',
						'delivery_city' => 'City',
						'delivery_postcode' => 'Postcode',
						'delivery_state' => 'State',
						'delivery_country' => 'Country',
						'billing_name' => 'Name',
						'billing_company' => 'Company',
						'billing_street_address' => 'Address',
						'billing_city' => 'City',
						'billing_postcode' => 'Postcode',
						'billing_state' => 'State',
						'billing_country' => 'country'					
					),
					array(
						'orders.orders_id' => 'Orders ID',
						'product_ean' => 'Ean',
						'products_name' => 'Name',
						'products_price' => 'Price',
						'products_cost' => 'Cost',
						'final_price' => 'Price',
						'products_tax' => 'Tax',
						'products_quantity' => 'Quantity'
					)
				)
			),
			array(
				'filename' => getSlug( AD_TITU_3, '_' ),
				'tables' => array( 'reviews' ),
				'rows' => array(
					array(
						'customers_name' => 'Name',
						'reviews_rating' => 'Rating',
						'date_added' => 'Date_add',
						'reviews_text' => 'Text',
						'products_name' => 'Products_name'
					)
				)
			),
			array(
				'filename' => getSlug( AD_TITU_4, '_' ),
				'tables' => array( 'opinion' ),
				'rows' => array(
					array(
						'orders_id' => 'Orders ID',
						'trato_personal' => 'Trato_personal',
						'h_trato_personal_malo' => 'Trato_personal_malo',
						'trato_transporte' => 'Trato_transporte',
						'h_trato_transporte_malo' => 'Trato_transporte_malo',
						'mercancia_tiempo_previsto' => 'Mercancia_tiempo_previsto',
						'embalaje_pedido' => 'Embalaje_pedido',
						'h_embalaje_pedido_malo' => 'Embalaje_pedido_malo',
						'a_producto_ampliar_catalogo' => 'Producto_ampliar_catalogo',
						'h_producto_ampliar_catalogo' => 'Producto_ampliar_catalogo',
						'como_conociste' => 'Como_conociste',
						'h_como_conociste' => 'Como_conociste'
					)
				)
			)			
		);

		// Si no tenemos configurado que la fecha de nacimiento sea por lightbox entonces importamos fechas
		if( RGPD_ACCOUNT_DELETE_DOB != '1' )
			$aTypes[0]['rows'][0]['customers_dob'] = 'Birthday_date';
		
		// Obtenemos que queremos descargar
		if( count( $aPostDownload ) > 0 )
		{
			// Creamos zip
			$zip = new ZipArchive();
			$zip_name = getcwd() . '/temp/' . $customer_id . '.zip';
			$zip->open( $zip_name,  ZipArchive::CREATE );
			
			foreach( $aPostDownload as $nTypeDownload )
			{
				if( array_key_exists( $nTypeDownload, $aTypes ) )
				{
					// Recorremos las tablas
					foreach( $aTypes[$nTypeDownload]['tables'] as $nIndex => $sTableName )
					{					
						// Csv
						$aCsv = array( array_values( $aTypes[$nTypeDownload]['rows'][$nIndex] ) );

						// From
						$sFrom = $sTableName;
						
						// Si es orders_products
						if( $sTableName == 'orders_products' )
							$sFrom .= ' inner join orders on(orders.orders_id = orders_products.orders_id) ';
						
						// Si es reviews
						if( $sTableName == 'reviews' )
						{
							$sFrom .= ' inner join reviews_description on(reviews.reviews_id = reviews_description.reviews_id) ';
							$sFrom .= ' inner join products_description on(reviews.products_id = products_description.products_id and products_description.language_id = "' . (int)$languages_id . '") ';
						}
						
						// Si es address_book
						if( $sTableName == 'address_book' )
						{
							$sFrom .= ' inner join cities on(cities.id = address_book.entry_city_id) ';
							$sFrom .= ' inner join countries on(countries.countries_id = address_book.entry_country_id) ';
							$sFrom .= ' inner join zones on(zones.zone_id = address_book.entry_zone_id) ';
						}
						
						// Obtenemos datos
						$aRows = tep_db_query( 'SELECT ' . implode( ',', array_keys( $aTypes[$nTypeDownload]['rows'][$nIndex] ) ) . ' FROM ' . $sFrom . ' WHERE customers_id = "' . (int)$customer_id . '"' );
						
						// Recorremos
						while( $aRow = tep_db_fetch_array( $aRows ) )
							$aCsv[] = array_values( $aRow );
						
						// Añadimos al zip
						$zip->addFromString( $aTypes[$nTypeDownload]['filename'] . '_' . $sTableName . '.csv',  outputCSV( $aCsv ) );  
					}
				}
			}
			
			// Cerramos zip
			$zip->close();
			
			// Descargamos zip y eliminamos del servidor
			header('Content-disposition: attachment; filename=' . getSlug( STORE_NAME ) . '_csv.zip');
			header('Content-type: application/zip');
			readfile($zip_name);
			unlink( $zip_name );
		}
		
		// Detenemos
		exit();
	}
	
	// Breadcrumb
	$breadcrumb->add(NAVBAR_TITLE_2, tep_href_link('account/account_downloads.php', '', 'SSL'));

	// Header
	include( 'account/includes/header.php' );
	
	// Titulo
	echo '<div class="ccTitle">' . NAVBAR_TITLE_2 . '</div>';
	
	// Mensaje
	echo '<div style="line-height: 28px; text-align: justify;">' . AD_MESSAGE . '</div>';;

	echo '<form class="xform" id="ccDelete" target="_blank" method="post" action="' . tep_href_link( 'account/account_downloads.php' ) . '">';
		echo '<div class="ccRow actv">';
			echo '<i class="ccLeft fa fa-user"></i>';
			echo '<div class="ccTitu">' . AD_TITU_1 . '</div>';
			echo '<div class="ccStitu">' . AD_STITU_1 . '</div>';
			echo '<div class="ccCheck"><input type="checkbox" name="downloads[]" value="0" checked="checked" id="downloads1" /><label for="downloads1"><span></span></label></div>';
		echo '</div>';
		
		echo '<div class="ccRow actv">';
			echo '<i class="ccLeft fas fa-file-alt"></i>';
			echo '<div class="ccTitu">' . AD_TITU_2 . '</div>';
			echo '<div class="ccStitu">' . AD_STITU_2 . '</div>';
			echo '<div class="ccCheck"><input type="checkbox" name="downloads[]" value="1" checked="checked" id="downloads2" /><label for="downloads2"><span></span></label></div>';
		echo '</div>';
		
		echo '<div class="ccRow actv">';
			echo '<i class="ccLeft fa fa-comments"></i>';
			echo '<div class="ccTitu">' . AD_TITU_3 . '</div>';
			echo '<div class="ccStitu">' . AD_STITU_3 . '</div>';
			echo '<div class="ccCheck"><input type="checkbox" name="downloads[]" value="2" checked="checked" id="downloads3" /><label for="downloads3"><span></span></label></div>';
		echo '</div>';
		
		echo '<div class="ccRow actv">';
			echo '<i class="ccLeft fas fa-comment-alt"></i>';
			echo '<div class="ccTitu">' . AD_TITU_4 . '</div>';
			echo '<div class="ccStitu">' . AD_STITU_4 . '</div>';
			echo '<div class="ccCheck"><input type="checkbox" name="downloads[]" value="3" checked="checked" id="downloads4" /><label for="downloads4"><span></span></label></div>';
		echo '</div>';
		
		echo '<div class="tright" style="margin-top: 15px;"><input class="button ccbutton verde" type="submit" value="' . AD_BUTTON_DOWNLOADS . '"/></div>';
	echo '</form>';
	
	// Footer
	include( 'account/includes/footer.php' );	
?>