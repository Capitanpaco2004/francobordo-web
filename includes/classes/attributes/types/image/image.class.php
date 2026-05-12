<?php
	class option_image
	{
		public function __construct()
		{
		}


		//////////////////////
		// MÉTODOS PÚBLICOS //
		//////////////////////
		
		// Metodo que muestra en el frontend el html
		public function frontendGetHtml($aDatos, $aDatoOption, $sPlantilla, $aOpcionesSelected, $bShowPrice, $nTaxId, $products_id = 0)
		{
			// Variables
			global $currencies;
			$sHtml = '';
			$nCont = 0;

			// Recorremos los datos			
			while( $aDato = tep_db_fetch_array( $aDatos ) )
			{
				// Variables
				$sImagen = '';
				$bSelected = false;
				$sPrice = 0;
				$sPriceData = 0;
				
				// Obtenemos precio
				if( $aDato['options_values_price'] != '' && $aDato['options_values_price'] > 0 )
				{
					$sPrice = $currencies->display_price($aDato['options_values_price'], tep_get_tax_rate($nTaxId));
					$sPriceData = str_replace( array(',', '€'), array('.', ''), $sPrice );
				}
			
				// Obtenemos la imagen
				if( file_exists( 'images/atributos/' . $aDato['products_options_values_id'] . '.jpg' ) )
					$sImagen = 'images/atributos/' . $aDato['products_options_values_id'] . '.jpg';
			
				// Obtenemos el valor que estara seleccionado
				if( array_key_exists( $aDatoOption['products_options_id'], $aOpcionesSelected ) && $aOpcionesSelected[$aDatoOption['products_options_id']] == $aDato['products_options_values_id'] )
					$bSelected = true;
			
				// Html
				$sHtml .= str_replace( array(
					'%REPLACE_VALUE_INPUT%',
					'%REPLACE_VALUE_IMAGE%',
					'%REPLACE_VALUE_NAME%',
					'%REPLACE_VALUE_PREFIX_PRICE%',
					'%REPLACE_VALUE_PRICE%',
					'%REPLACE_OPTION_NAME%'
				),
				array(
					'<input ' . ($nCont == 0 ? '%REPLACE_FIRST_ELEMENT%' : '') . ' data-price="' . $sPriceData . '" data-price-prefix="' . $aDato['price_prefix'] . '" ' . ($bSelected ? 'checked="checked"' : '') . ' data-oid="' . $aDatoOption['products_options_id'] . '" value="' . $aDato['products_options_values_id'] . '" name="id[' . $aDatoOption['products_options_id'] . ']" type="radio"' . ($aDatoOption['products_options_track_stock'] == 1 ? ' data-track="1" data-name="' . $aDatoOption['products_options_name'] . '" data-required="true"' : '') . '/>',
					($sImagen != '' ? '<img width="25" height="25" src="' . $sImagen . '"/>' : ' '),
					$aDato['products_options_values_name'],
					($bShowPrice ? ($aDato['options_values_price'] > 0 ? $aDato['price_prefix'] : ''): ''),
					($bShowPrice ? ($aDato['options_values_price'] > 0 ? $sPrice : ''): ''),
					$aDatoOption['products_options_name']
				), $sPlantilla );
				
				// Aumentamos
				$nCont++;
			}
			
			// Si no contenemos ningun valor seleccionado seleccionamos el primer valor por defecto
			$sHtml = str_replace( '%REPLACE_FIRST_ELEMENT%', (!preg_match( '/checked="checked"/i', $sHtml ) ? 'checked="checked"' : '' ), $sHtml );
			
			// Retornamos
			return $sHtml;
		}		
		
		// Método que verifica si se puede añadir valores a la opción
		public function getAllowValues()
		{
			return true;
		}
		
		// Cuando eliminamos un valor desde el admin
		public function adminValueDelete($sId)
		{
			// Si existe eliminamos
			if( file_exists( '../images/atributos/' . $sId . '.jpg' ) )
				unlink( getcwd() . '/../images/atributos/' . $sId . '.jpg' );
		}
		
		// Cuando editamos/insertamos el valor desde el admin
		public function adminValueAdd($sId)
		{
			// Variables
			$sPostImage = tep_db_prepare_input( $_POST['image'] );
		
			// Si contenemos imagen guardamos
			if( $sPostImage != '' && $sPostImage != 'no_change' )
			{
				$sDataBase64 = preg_replace( '/,.+$/i', '', $sPostImage ) . ',';
				$sPostImage = str_replace( $sDataBase64, '', $sPostImage );

				file_put_contents( getcwd() . '/../images/atributos/' . $sId . '.jpg', base64_decode( $sPostImage ) );
			}
			elseif($sPostImage == '') // Si esta vacia eliminamos
				unlink( getcwd() . '/../images/atributos/' . $sId . '.jpg' );
		}
		
		// Cargamos librerias javascript en el admin necesarias
		public function adminLoadJavascript()
		{
			return '<script type="text/javascript" src="../includes/classes/attributes/types/image/javascript.admin.js"></script>';
		}
		
		// Metodo que muestra en la la cabecera de la tabla de lista de valores su nombre
		public function adminValueListTableHead()
		{
			return '<td style="width: 1px; text-align: center;">Imagen</td>';
		}
		
		// Metodo que muestra en la la tabla de lista de valores la imagen
		public function adminValueListTableBody($aDato)
		{
			// Variables
			$sImagen = '';
			
			if( file_exists( '../images/atributos/' . $aDato['products_options_values_id'] . '.jpg' ) )
				$sImagen = '../images/atributos/' . $aDato['products_options_values_id'] . '.jpg';
			
			return '<td style="width: 1px; text-align: center;">' . ($sImagen != '' ? '<img width="35" height="35" src="' . $sImagen . '"/>' : ' ') . '</td>';
		}

		// Metodo que muestra el formulario para el admin al insertar/editar un valor
		public function adminValueShowForm($oInfo, $bEdit = false)
		{
			// Variables
			global $sUrlPage;
			$sImagen = '';
		
			// Si estamos editando intentamos localizar la imagen
			if( $bEdit )
			{
				if( file_exists( '../images/atributos/' . $oInfo->{3}['products_options_values_id'] . '.jpg' ) )
					$sImagen = '../images/atributos/' . $oInfo->{3}['products_options_values_id'] . '.jpg';
			}
		
			// Html
			$sHtmlModule .= '<div class="fluid grid">';
				$sHtmlModule .= '<div class="box-tbl grid12">';
					$sHtmlModule .= '<div class="box-head">';
						$sHtmlModule .= '<div class="grid12"><h6>Opciones de imagen</h6></div>';
						$sHtmlModule .= '<div class="clear"></div>';
					$sHtmlModule .= '</div>';
					
					// Input fake upload
					$sHtmlModule .= '<div style="visibility: hidden; width: 1px; height: 1px; display: none; opacity: 0;">';
						$sHtmlModule .= '<input id="attr-upld-imge-inpt" type="file"/>';
						$sHtmlModule .= '<input id="attr-upld-imge-base" name="image" value="no_change" type="text"/>';
					$sHtmlModule .= '</div>';
					
					// Tabla //
					$sHtmlModule .= '<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">';
						$sHtmlModule .= '<thead>';
							$sHtmlModule .= '<tr>';
								$sHtmlModule .= '<td style="text-align: center; width: 1px;">Imagen</td>';
								$sHtmlModule .= '<td style="text-align: left;">Archivo</td>';
								$sHtmlModule .= '<td style="text-align: center; width: 125px;">Acciones</td>';
							$sHtmlModule .= '</tr>';
						$sHtmlModule .= '</thead>';
						$sHtmlModule .= '<tbody>';
							$sHtmlModule .= '<tr>';
								$sHtmlModule .= '<td id="attr-upld-imge" style="text-align: center;">' . ( $sImagen != '' ? '<img width="35" height="35" src="' . $sImagen . '"/>' : '') . '</td>';
								$sHtmlModule .= '<td id="attr-upld-imge-name" style="text-align: left;">' . ( $sImagen != '' ? getcwd() . '/' . $sImagen : '') . '</td>';
								
								$sHtmlModule .= admin_setTableTdAction( array(
									array( 'HREF' => 'javascript: void(0);', 'CLASS' => 'icos-upload', 'TEXT' => 'Subir imagen', 'ATTR_HREF' => 'id="attr-upld-imge-bton"' ),
									array( 'HREF' => 'javascript: void(0);', 'CLASS' => 'icos-trash', 'TEXT' => 'Eliminar', 'ATTR_HREF' => 'id="attr-upld-imge-dlte"' )
								));
								
							$sHtmlModule .= '</tr>';							
						$sHtmlModule .= '</tbody>';
					$sHtmlModule .= '</table>';
				$sHtmlModule .= '</div>';
			$sHtmlModule .= '</div>';

			return $sHtmlModule;
		}
	}
?>