<?php
	class action_change_image
	{
		public function __construct()
		{
			// Variables
			$this->sNameAction = 'Cambiar imagen';
			$this->sNameClase = 'change_image';
			$this->bShowAdmin = true;
		}

		//////////////////////////
		// PROPIEDADES PÚBLICAS //
		//////////////////////////

		// Contiene el nombre de la acción
		public $sNameAction;
		// Contiene el nombre de la clase
		public $sNameClase;
		// Mostrar o no en el admin
		public $bShowAdmin;


		//////////////////////
		// MÉTODOS PÚBLICOS //
		//////////////////////

		// Metodo que se llama cuando se elimina la accion
		public function adminFormDelete($nProductsId, $nCombinaciones)
		{
			// Comprobamos si existe
			$aDatos = tep_db_query( 'SELECT value FROM products_attributes_actions WHERE products_id = "' . (int)$nProductsId . '" AND products_attributes = "' . $nCombinaciones . '" AND action = "' . $this->sNameClase . '"' );

			// Si existe eliminamos las imagenes
			if( tep_db_num_rows( $aDatos ) > 0 )
			{
				// Obtenemos
				$aDato = tep_db_fetch_array( $aDatos );

				// Imagenes
				$aImagenes = explode( '[dxsepare]', $aDato['value'] );

				// Recorremos para eliminar
				foreach( $aImagenes as $sImagen )
				{
					if( $sImagen != '' && file_exists( getcwd() . '/../images/atributos/' . $sImagen ) )
						unlink( getcwd() . '/../images/atributos/' . $sImagen );
				}
			}
		}

		// Metodo que llama cuando se envia el formulario
		public function adminFormSave($nProductsId)
		{
			// Variables
			$nCombinaciones = tep_db_prepare_input( $_POST['combinaciones'] );
			$aInsertImages = array();
			$bExiste = false;

			// Comprobamos si existe
			$aDatos = tep_db_query( 'SELECT value FROM products_attributes_actions WHERE products_id = "' . (int)$nProductsId . '" AND products_attributes = "' . $nCombinaciones . '"  AND action = "' . $this->sNameClase . '"' );

			// Si existe
			if( tep_db_num_rows( $aDatos ) > 0 )
				$bExiste = true;

			// Si existe eliminamos las imagenes
			if( $bExiste )
			{
				// Obtenemos
				$aDato = tep_db_fetch_array( $aDatos );

				// Imagenes
				$aImagenes = explode( '[dxsepare]', $aDato['value'] );

				// Recorremos para eliminar
				foreach( $aImagenes as $sImagen )
				{
					if( $sImagen != '' && file_exists( getcwd() . '/../images/atributos/' . $sImagen ) )
						unlink( getcwd() . '/../images/atributos/' . $sImagen );
				}
			}

			// Recorremos las imagenes y las subimos
			foreach( tep_db_prepare_input( $_POST['images'] ) as $key => $sImagen )
			{
				if( $sImagen != '' )
				{
					$sExtension = '.' . preg_replace( '/\;base64\,.+$|data\:|image\//i', '', $sImagen );
					$sDataBase64 = preg_replace( '/,.+$/i', '', $sImagen ) . ',';
					$sImagen = str_replace( $sDataBase64, '', $sImagen );
					$sAux = 'ai_' . $nProductsId . '-' . $nCombinaciones . '-' . $key . $sExtension;

					file_put_contents( getcwd() . '/../images/atributos/' . $sAux, base64_decode( $sImagen ) );
					$aInsertImages[] = $sAux;
				}
			}

			// Array SQL
			$aSql = array(
				'products_id' => $nProductsId,
				'products_attributes' => $nCombinaciones,
				'value' => implode( '[dxsepare]', $aInsertImages ),
				'action' => $this->sNameClase
			);

			// Si existe modificamos si no insertamos
			if( $bExiste )
				tep_db_perform( 'products_attributes_actions', $aSql, 'update', 'products_id = "' . (int)$nProductsId . '" AND products_attributes = "' . $nCombinaciones . '"' );
			else
				tep_db_perform( 'products_attributes_actions', $aSql );
		}

		// Metodo que muestra el formulario en el admin
		public function adminForm($nProductsId, $nCombinaciones)
		{
			// Variables
			$sHtml = '';

			// Html
			$sHtml .= '<form action="categories.php?action=attributeManager&method=attributeManagerActionFormSend" method="post" id="dx-file-upload" class="fluid grid">';
				$sHtml .= '<div id="products_image_upload" class="box-tbl grid12">';
					$sHtml .= '<div class="box-head">';
						$sHtml .= '<h6>Imágenes del producto</h6>';
						$sHtml .= '<div class="clear"></div>';
					$sHtml .= '</div>';
					$sHtml .= '<input name="products_id" type="hidden" value="' . $nProductsId . '" />';
					$sHtml .= '<input name="action" type="hidden" value="' . $this->sNameClase . '" />';
					$sHtml .= '<input name="combinaciones" type="hidden" value="' . $nCombinaciones . '" />';
					$sHtml .= '<table class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">';
						$sHtml .= '<thead>';
							$sHtml .= '<tr>';
								$sHtml .= '<td  style="width: 80px;">Imagen</td>';
								$sHtml .= '<td>Archivo</td>';
								$sHtml .= '<td style="width: 50px;">Acción</td>';
							$sHtml .= '</tr>';
						$sHtml .= '</thead>';
						$sHtml .= '<tbody>';

							// Obtenemos las imagenes
							$aDatos = tep_db_query( 'SELECT value FROM products_attributes_actions WHERE products_id = "' . (int)$nProductsId . '" AND products_attributes = "' . $nCombinaciones . '"  AND action = "' . $this->sNameClase . '"' );

							// Si existe
							if( tep_db_num_rows( $aDatos ) > 0 )
							{
								// Obtenemos
								$aDato = tep_db_fetch_array( $aDatos );

								// Imagenes
								$aImagenes = explode( '[dxsepare]', $aDato['value'] );

								// Recorremos
								foreach( $aImagenes as $sImagen )
								{
									$sHtml .= '<tr>';
										$sHtml .= '<td>';
											$sHtml .= '<img width="80" height="80" src="../images/atributos/' . $sImagen . '?v=' . tep_rand() . '"/>';
										$sHtml .= '</td>';
										$sHtml .= '<td>';
											$type = pathinfo(getcwd() . '/../images/atributos/' . $sImagen, PATHINFO_EXTENSION);
											$data = file_get_contents(getcwd() . '/../images/atributos/' . $sImagen);
											$base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);

											$sHtml .= '<input type="hidden" value="' . $base64 . '" name="images[]">' . $sImagen;
										$sHtml .= '</td>';
										$sHtml .= '<td style="text-aling: center;"><a class="dlte"><span style="float: none; margin: 0px auto; display: block;" class="icos-trash"></span></a></td>';
									$sHtml .= '</tr>';
								}
							}

						$sHtml .= '</tbody>';
					$sHtml .= '</table>';
					$sHtml .= '<div class="tableFooter">';
						$sHtml .= '<a class="bton-fake-upload buttonS bGreen" href="javascript:void(0);" style="position: relative; z-index: 0;">Añadir archivos</a>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</form>';

			// Cargamos javascript
			$sHtml .= '<script type="text/javascript" src="../includes/classes/attributes/actions/change_image/javascript.js"></script>';

			// Retornamos
			return $sHtml;
		}
	}