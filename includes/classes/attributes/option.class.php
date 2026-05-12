<?php
	class option
	{
		public function __construct()
		{
			// Obtenemos los tipos que existen
			$this->getOptionsTypes();

			// Obtenemos las acciones que existen
			$this->getOptionsActions();
		}

		//////////////////////////
		// PROPIEDADES PRIVADAS //
		//////////////////////////

		// Contiene un array con los diferentes tipos de opciones que existen
		private $aOptionsTypes;
		// Contiene un array con las acciones que existen
		private $aActions;
		// Array con las plantillas
		private $aPlantillaHtml;
		// Mostrar o no prefijo y precio en el frontend
		private $bShowPrice;
		// Tax del producto que contiene las opciones
		private $nTaxId;
		// Si =true, en frontend filtra variantes sin stock real cuando el producto está en products_liquidacion=1
		private $bFilterLiquidacionNoStock = false;


		//////////////////////
		// MÉTODOS PÚBLICOS //
		//////////////////////

		// Activa el filtrado de variantes sin stock real para productos en liquidación.
		// Sólo lo llama product_info.php (frontend). Admin y carrito no lo activan.
		public function setFilterLiquidacionNoStock($b)
		{
			$this->bFilterLiquidacionNoStock = (bool)$b;
		}

		// Metodo que muestra el html de las opciones
		public function getOptionHtml($sGetProductsId, $aOptions = array())
		{
			// Variables
			global $messageStack, $languages_id;
			$nProductsId = preg_replace( '/\{.+$/i', '', $sGetProductsId );
			$sHtml = '';
			$aOpcionesSelected = array();
			$aStock = array();
			$aAcciones = array();

			// Opciones
			$aPlantillaHtml = array_key_exists( 'TEMPLATE', $aOptions ) ? $aOptions['TEMPLATE'] : array();
			$this->bShowPrice = array_key_exists( 'SHOW_PRICE', $aOptions ) ? $aOptions['SHOW_PRICE'] : false;
			$this->nTaxId = array_key_exists( 'TAX_ID', $aOptions ) ? $aOptions['TAX_ID'] : 1;

			// Plantilla html para mostrar opciones y valores
			$this->aPlantillaHtml = $aPlantillaHtml;

			// Obtenemos las opciones seleccionadas
			$aOpcionesSelected = explode( '{', str_replace( $nProductsId, '', $sGetProductsId ) );
			unset($aOpcionesSelected[0]);

			// Si tenemos alguna opcion seleccionada
			if( count($aOpcionesSelected) > 0 )
			{
				// Guardamos el array temportalmente para resetearlo
				$aAuxs = $aOpcionesSelected;
				$aOpcionesSelected = array();

				// Recorremos
				foreach( $aAuxs as $sAux )
				{
					// Dividimos opcion/valor
					$aAux = explode( '}', $sAux );

					// Si no existe sera por defecto 0
					if( $aAux[1] == "" )
						$aAux[1] = 0;

					// Guardamos
					$aOpcionesSelected[$aAux[0]] = $aAux[1];
				}
			}

			// Obtenemos las opciones que tiene asignado el producto
			$aDatos = tep_db_query( 'SELECT po.products_options_id, po.products_options_name, po.products_options_type, po.products_options_track_stock
									 FROM products_attributes pa
									 INNER JOIN products_options po ON(pa.options_id = po.products_options_id)
									 WHERE pa.products_id = "' . (int)$nProductsId . '" AND po.language_id = "' . (int)$languages_id . '"
									 GROUP BY pa.options_id
									 ORDER BY pa.products_options_sort_order asc' );

			// Recorremos las opciones para obtener su html
			while( $aDato = tep_db_fetch_array( $aDatos ) )
				$sHtml .= $this->getValueHtml( $nProductsId, $aDato['products_options_id'], array( 'OPTION' => $aDato, 'OPTION_SELECTED' => $aOpcionesSelected) );

			// Obtenemos el stock que tiene el producto
			$aDatos = tep_db_query( 'SELECT products_stock_attributes, products_stock_quantity
									 FROM products_stock
									 WHERE products_id = "' . (int)$nProductsId . '" ' );

			// Guardamos stock
			while( $aDato = tep_db_fetch_array( $aDatos ) )
				$aStock[$aDato['products_stock_attributes']] = $aDato['products_stock_quantity'];

			// Obtenemos las acciones que tiene el producto
			$aDatos = tep_db_query( 'SELECT products_attributes, value, action FROM products_attributes_actions WHERE products_id = "' . (int)$nProductsId . '"' );

			// Guardamos en un array
			while( $aDato = tep_db_fetch_array( $aDatos ) ) {
				$aAcciones[$aDato['products_attributes']] = ['value' => $aDato['value'], 'action' => $aDato['action']];
			}

			// Convertimos los arrays en json
			if( $sHtml != '' )
			{
				$sHtml .= '<div id="array_option_stock" style="display: none;">' . json_encode($aStock) . '</div>';
				$sHtml .= '<div id="array_option_action" style="display: none;">' . json_encode($aAcciones) . '</div>';
			}

			// Retornamos
			return $sHtml;
		}

		// Metodo que muestra el html de los valores
		public function getValueHtml($nProductsId, $nOptionId, $aOptions = array())
		{
			// Variables
			global $languages_id;
			$sHtml = '';
			$sHtmlValue = '';

			// Opciones
			$aOption = array_key_exists( 'OPTION', $aOptions ) ? $aOptions['OPTION'] : false;
			$aOpcionesSelected = array_key_exists( 'OPTION_SELECTED', $aOptions ) ? $aOptions['OPTION_SELECTED'] : array();

			// Si no tenemos option lo obtenemos
			if( $aOption == false )
			{
				$aDatos = tep_db_query( 'SELECT po.products_options_id, po.products_options_name, po.products_options_type, po.products_options_track_stock
										 FROM products_options po
										 WHERE po.language_id = "' . (int)$languages_id . '" AND po.products_options_id = "' . $nOptionId . '"' );

				$aOption = tep_db_fetch_array($aDatos);
			}

			// Comprobamos el tipo si no por defecto sera combobox
			if( $aOption['products_options_type'] == '' || !file_exists(DIR_WS_CLASSES . '/attributes/types/' . $aOption['products_options_type'] . '/' . $aOption['products_options_type'] . '.class.php') )
				$aOption['products_options_type'] = 'combobox';

			// Incluimos el tipo
			require_once( DIR_WS_CLASSES . '/attributes/types/' . $aOption['products_options_type'] . '/' . $aOption['products_options_type'] . '.class.php' );

			// Objeto option
			eval( '$obj = new option_' . $aOption['products_options_type'] . '();' );

			// Grupo de cliente
			$nCustomerGroupId = (isset($_SESSION['sppc_customer_group_id']) && $_SESSION['sppc_customer_group_id'] != '0') ? $_SESSION['sppc_customer_group_id'] : 0;

			// Liquidación: si el flag está activo Y el producto está en liquidación, filtramos variantes sin stock real
			// (stock real = products_stock_quantity > 0 AND != 2000). Sentinels -100/-800/-900/2000 cuentan como sin stock.
			$bApplyLiqFilter = false;
			if( $this->bFilterLiquidacionNoStock )
			{
				$rLiq = tep_db_query( 'SELECT products_liquidacion FROM products WHERE products_id = "' . (int)$nProductsId . '"' );
				if( $rLiq && ( $rowLiq = tep_db_fetch_array( $rLiq ) ) )
					$bApplyLiqFilter = ( (int)$rowLiq['products_liquidacion'] === 1 );
			}
			$sLiqJoin  = '';
			$sLiqWhere = '';
			if( $bApplyLiqFilter )
			{
				$sLiqJoin  = ' INNER JOIN products_stock ps_liq ON ps_liq.products_id = pa.products_id AND ps_liq.products_stock_attributes = CONCAT(pa.options_id, "-", pa.options_values_id) ';
				$sLiqWhere = ' AND ps_liq.products_stock_quantity > 0 AND ps_liq.products_stock_quantity != 2000 ';
			}

			// Obtenemos los valores
			$sql =  'SELECT pa.products_attributes_id, pov.products_options_values_id, pov.products_options_values_name, pa.options_values_price, pa.price_prefix, pa.reference, pa.reference_prov, pa.products_attributes_ean, pa.weight_prefix, pa.options_values_weight, pa.options_values_id, pa.options_id
			FROM products_attributes pa
			INNER JOIN products_options_values pov ON(pa.options_values_id = pov.products_options_values_id)
			' . $sLiqJoin . '
			WHERE pa.options_id = "' . (int)$nOptionId . '" and pa.products_id = "' . (int)$nProductsId . '" AND pov.language_id = "' . (int)$languages_id . '"
			' . $sLiqWhere . '
			ORDER BY pa.products_options_sort_order asc';

			if ($nCustomerGroupId != 0) {
				$sql = 'SELECT pa.products_attributes_id, pov.products_options_values_id, pov.products_options_values_name, pag.options_values_price, pa.price_prefix, pa.reference, pa.reference_prov, pa.products_attributes_ean, pa.weight_prefix, pa.options_values_weight, pa.options_values_id, pa.options_id
						FROM products_attributes pa
						INNER JOIN products_options_values pov ON(pa.options_values_id = pov.products_options_values_id)
						LEFT JOIN products_attributes_groups pag ON pag.products_attributes_id = pa.products_attributes_id
						' . $sLiqJoin . '
						WHERE pag.customers_group_id = '.$nCustomerGroupId.' AND pa.options_id = "' . (int)$nOptionId . '" and pa.products_id = "' . (int)$nProductsId . '" AND pov.language_id = "' . (int)$languages_id . '"
						' . $sLiqWhere . '
						ORDER BY pa.products_options_sort_order asc';
			}

			$aDatos = tep_db_query($sql);

			// Liquidación: si tras filtrar no queda ningún valor para esta option, no devolvemos HTML
			if( $bApplyLiqFilter && tep_db_num_rows( $aDatos ) == 0 )
				return '';

			// Plantilla
			$sPlantillaOption = $this->aPlantillaHtml['OPTION_HTML'][$aOption['products_options_type']];

			// Si existe plantilla personalizada
			if( array_key_exists( 'OPTION_HTML_EXTRA', $this->aPlantillaHtml ) && array_key_exists( $aOption['products_options_id'], $this->aPlantillaHtml['OPTION_HTML_EXTRA'] ) )
				$sPlantillaOption = $this->aPlantillaHtml['OPTION_HTML_EXTRA'][$aOption['products_options_id']];

			// Obtenemos el html
			$sHtmlValue .= $obj->frontendGetHtml( $aDatos, $aOption, $sPlantillaOption, $aOpcionesSelected, $this->bShowPrice, $this->nTaxId, $nProductsId );

			// Obtenemos la clase de la opcion
			$sClassOption = $aOption['products_options_type'];

			// Si existe una clase personalizada
			if( array_key_exists( 'OPTION_CLASS', $this->aPlantillaHtml ) && array_key_exists( $sClassOption, $this->aPlantillaHtml['OPTION_CLASS'] ) )
				$sClassOption = $this->aPlantillaHtml['OPTION_CLASS'][$sClassOption];

			// Obtenemos la plantilla por defecto
			$sPlantillaDefault = $this->aPlantillaHtml['OPTION_DEFAULT'];

			// Si contenemos una plantilla personalizada
			if( array_key_exists( 'OPTION_DEFAULT_EXTRA', $this->aPlantillaHtml ) && array_key_exists( $aOption['products_options_id'], $this->aPlantillaHtml['OPTION_DEFAULT_EXTRA'] ) )
				$sPlantillaDefault = $this->aPlantillaHtml['OPTION_DEFAULT_EXTRA'][$aOption['products_options_id']];

			// Html
			$sHtml .= str_replace( array(
				'%REPLACE_OPTION_TYPE_CLASS%',
				'%REPLACE_OPTION_NAME%',
				'%REPLACE_VALUE_HTML%'
			),
			array(
				$sClassOption,
				$aOption['products_options_name'],
				$sHtmlValue
			), $sPlantillaDefault );

			// Retornamos
			return $sHtml;
		}

		// Metodo que elimina un template
		public function attributeManagerDeleteTemplate($nIdTemplate)
		{
			tep_db_query( 'DELETE FROM am_attributes_to_templates WHERE template_id = "' . (int)$nIdTemplate . '"' );
			tep_db_query( 'DELETE FROM am_templates WHERE template_id = "' . (int)$nIdTemplate . '"' );

			return false;
		}

		// Metodo que remplaza el nombre de un template
		public function attributeManagerEditNameTemplate($nIdTemplate, $sNameTemplate)
		{
			tep_db_perform( 'am_templates', array('template_name' => $sNameTemplate), 'update', 'template_id = "' . (int)$nIdTemplate . '"' );

			return false;
		}

		// Metodo que crea/edita un nuevo template
		public function attributeManagerNewTemplate($nProductsId, $sNameTemplate, $nIdTemplate = false)
		{
			// Si nos pasan nombre del template
			if( $sNameTemplate != '' && $nProductsId != '' )
			{
				// Guardamos el nombre del template si no estamos editando
				if( $nIdTemplate === false )
				{
					tep_db_perform( 'am_templates', array('template_name' => $sNameTemplate) );
					$nIdTemplate = tep_db_insert_id();
				}
				else // Si estamos editando eliminamos todo
				{
					tep_db_query( 'DELETE FROM am_attributes_to_templates WHERE template_id = "' . (int)$nIdTemplate . '"' );
				}

				// Obtenemos las opciones del producto
				$aDatos = tep_db_query( 'SELECT * from products_attributes WHERE products_id = "' . (int)$nProductsId . '" ORDER BY products_options_sort_order ASC' );

				// Si existe
				if( tep_db_num_rows( $aDatos ) > 0 )
				{
					// Recorremos
					while( $aDato = tep_db_fetch_array( $aDatos ) )
					{
						// Quitamos el key "products_id"
						unset( $aDato['products_id'] );

						// Quitamos el key "products_attributes_id"
						unset( $aDato['products_attributes_id'] );

						// Quitamos el key "attributes_hide_from_groups"
						unset( $aDato['attributes_hide_from_groups'] );

						// Añadimos el key template_id
						$aDato['template_id'] = $nIdTemplate;

						// Cambiamos el campo options_values_id
						$aDato['option_values_id'] = $aDato['options_values_id'];
						unset( $aDato['options_values_id'] );

						// Guardamos
						tep_db_perform( 'am_attributes_to_templates', $aDato );
					}

					// Retornamos
					return $nIdTemplate;
				}
			}

			// Retornamos
			return false;
		}

		// Metodo que carga un template guardado
		public function attributeManagerLoadTemplate($nProductsId, $nIdTemplate)
		{
			// Obtenemos el template
			$aDatos = tep_db_query( 'SELECT * from am_attributes_to_templates WHERE template_id = "' . (int)$nIdTemplate . '" ORDER BY products_options_sort_order ASC' );

			// Si existe
			if( tep_db_num_rows( $aDatos ) > 0 )
			{
				// Eliminamos todas las opciones del producto
				tep_db_query( 'DELETE FROM products_attributes WHERE products_id = "' . (int)$nProductsId . '"' );

				// Recorremos
				while( $aDato = tep_db_fetch_array( $aDatos ) )
				{
					// Quitamos el key "template_id"
					unset( $aDato['template_id'] );

					// Añadimos el key products_id
					$aDato['products_id'] = $nProductsId;

					// Cambiamos el campo option_values_id
					$aDato['options_values_id'] = $aDato['option_values_id'];
					unset( $aDato['option_values_id'] );

					// Guardamos
					tep_db_perform( 'products_attributes', $aDato );
				}

				// Eliminamos las acciones y el stock
				tep_db_query( 'DELETE FROM products_attributes_actions WHERE products_id = "' . (int)$nProductsId . '"' );
				tep_db_query( 'DELETE FROM products_stock WHERE products_id = "' . (int)$nProductsId . '"' );
			}

			// Retornamos
			return $this->attributeManager($nProductsId, false);
		}

		// Metodo que se lanza cuando se elimina una acción
		public function attributeManagerActionDelete($nProductsId, $nCombinaciones, $sAction)
		{
			// Variables
			$nCantidadAccionesTotal = 0;
			$nCantidadAcciones = 0;

			// Incluimos la clase
			require_once( dirname( __FILE__ ) . '/actions/' . $sAction . '/' . $sAction . '.class.php' );

			// Creamos la clase
			eval( '$obj = new action_' . $sAction . '();' );

			// LLamamos al metodo de eliminar
			$obj->adminFormDelete($nProductsId, $nCombinaciones);

			// Eliminamos
			tep_db_query( 'DELETE FROM products_attributes_actions WHERE products_id = "' . (int)$nProductsId . '" AND products_attributes = "' . $nCombinaciones . '" AND action = "' . $sAction . '"' );

			// Obtenemos el total de acciones
			$aDatos = tep_db_query( 'SELECT count(distinct(products_attributes)) AS cantidad FROM products_attributes_actions WHERE products_id = "' . (int)$nProductsId . '"' );

			if( tep_db_num_rows( $aDatos ) > 0 )
			{
				$aDatos = tep_db_fetch_array($aDatos);
				$nCantidadAccionesTotal = $aDatos['cantidad'];
			}

			// Cantidad de acciones con la combinacion
			$aDatos = tep_db_query( 'SELECT count(id) AS cantidad FROM products_attributes_actions WHERE products_id = "' . (int)$nProductsId . '" AND products_attributes = "' . $nCombinaciones . '"' );

			if( tep_db_num_rows( $aDatos ) > 0 )
			{
				$aDatos = tep_db_fetch_array($aDatos);
				$nCantidadAcciones = $aDatos['cantidad'];
			}

			// Retornamos
			return json_encode( array( 'cantidad_total' => $nCantidadAccionesTotal, 'cantidad' => $nCantidadAcciones, 'html' => $this->attributeManagerActionForm($nProductsId, $nCombinaciones) ) );
		}

		// Metodo que se lanza cuando es enviado un formulario de accion
		public function attributeManagerActionFormSend($nProductsId, $sAction, $nCombinaciones)
		{
			// Comprobamos si existe el action
			if( in_array( $sAction, $this->aActions ) )
			{
				// Incluimos la clase
				include( dirname( __FILE__ ) . '/actions/' . $sAction . '/' . $sAction . '.class.php' );

				// Creamos la clase
				eval( '$obj = new action_' . $sAction . '();' );

				// Lanzamos el evento de guardar formulario
				$obj->adminFormSave($nProductsId);

				// Obtenemos el total de acciones
				$aDatos = tep_db_query( 'SELECT count(distinct(products_attributes)) AS cantidad FROM products_attributes_actions WHERE products_id = "' . (int)$nProductsId . '"' );

				if( tep_db_num_rows( $aDatos ) > 0 )
				{
					$aDatos = tep_db_fetch_array($aDatos);
					$nCantidadAccionesTotal = $aDatos['cantidad'];
				}

				// Cantidad de acciones con la combinacion
				$aDatos = tep_db_query( 'SELECT count(id) AS cantidad FROM products_attributes_actions WHERE products_id = "' . (int)$nProductsId . '" AND products_attributes = "' . $nCombinaciones . '"' );

				if( tep_db_num_rows( $aDatos ) > 0 )
				{
					$aDatos = tep_db_fetch_array($aDatos);
					$nCantidadAcciones = $aDatos['cantidad'];
				}

				// Retornamos
				return json_encode( array( 'cantidad_total' => $nCantidadAccionesTotal, 'cantidad' => $nCantidadAcciones, 'html' => $this->attributeManagerActionForm($nProductsId, $nCombinaciones) ) );
			}
		}

		// Metodo que muestra el formulario de acciones
		public function attributeManagerActionForm($nProductsId, $nCombinaciones)
		{
			// Variables
			$sHtml = '';
			$aAcciones = array();
			$sPostAction = tep_db_prepare_input( $_POST['action'] );

			// Obtenemos las acciones del producto y combinacion
			$aDatos = tep_db_query( 'SELECT action FROM products_attributes_actions WHERE products_id = "' . (int)$nProductsId . '" AND products_attributes = "' . $nCombinaciones . '"' );

			// Recorremos y guardamos las acciones
			while( $aDato = tep_db_fetch_array( $aDatos ) )
				$aAcciones[] = $aDato['action'];

			// Html
			$sHtml .= '<div class="fluid grid">';
				$sHtml .= '<div class="box-tbl grid12">';
					$sHtml .= '<div id="form-actn" class="formRow" style="padding: 0px;">';
						$sHtml .= '<div class="line"></div>';
						$sHtml .= '<div class="izqd">';
							$sHtml .= '<ul class="subNav">';
								// Recorremos las acciones
								foreach( $this->aActions as $key => $sAction )
								{
									// Incluimos la clase
									require_once( dirname( __FILE__ ) . '/actions/' . $sAction . '/' . $sAction . '.class.php' );

									// Creamos la clase
									eval( '$obj_' . $sAction . ' = new action_' . $sAction . '();' );
									eval( '$sNombre = $obj_' . $sAction . '->sNameAction;' );
									eval( '$bShow = $obj_' . $sAction . '->bShowAdmin;' );

									// Si no tenemos acción seleccionada, sera la primera la que estara seleccionado
									if( $sPostAction == '' && $bShow )
										$sPostAction = $sAction;

									// Pintamos
									if( $bShow )
										$sHtml .= '<li data-id="' . $key . '" class="' . ($sAction == $sPostAction ? ' activeli' : '') . '"><a ' . ($sAction == $sPostAction ? 'class="this"' : '') . ' title="" href="javascript:void(0);">' . (in_array($sAction,$aAcciones) ? '<span data-action="' . $sAction . '" class="icos-trash"></span>' : '') . $sNombre . '</a></li>';
								}
							$sHtml .= '</ul>';
						$sHtml .= '</div>';
						$sHtml .= '<div class="drch">';
							// Recorremos las acciones
							foreach( $this->aActions as $key => $sAction )
							{
								eval( '$bShow = $obj_' . $sAction . '->bShowAdmin;' );

								// Pintamos
								if( $bShow )
								{
									$sHtml .= '<div class="cntd" data-id="' . $key . '" style="display: ' . ($sAction == $sPostAction ? 'block' : 'none') . ';">';
										eval('$sHtml .= $obj_' . $sAction . '->adminForm($nProductsId, $nCombinaciones);');
									$sHtml .= '</div>';
								}
							}
						$sHtml .= '</div>';
						$sHtml .= '<div class="clear"></div>';

						$sHtml .= '<div class="clear"></div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';

			// Retornamos
			return $sHtml;
		}

		// Metodo que muestra las combinaciones entre propiedades
		public function attributeManagerAction($nProductsId, $nCombinaciones = 1)
		{
			// Variables
			global $sJavascript, $languages_id;
			$sHtml = '';
			$nIdsOpciones = '0,';
			$aOpciones = array();
			$aValores = array();
			$aValoresCombi = array();
			$aAcciones = array();

			// Obtenemos las opciones que tiene asignado el producto
			$aDatos = tep_db_query( 'SELECT pa.options_id, po.products_options_name
									 FROM products_attributes pa
									 INNER JOIN products_options po ON(pa.options_id = po.products_options_id)
									 WHERE pa.products_id = "' . (int)$nProductsId . '" AND po.language_id = "' . (int)$languages_id . '"
									 GROUP BY pa.options_id
									 ORDER BY pa.products_options_sort_order asc' );

			// Recorremos las opciones
			$nCont = 0;
			while( $aDato = tep_db_fetch_array( $aDatos ) )
			{
				// Guardamos las ids
				$nIdsOpciones .= $aDato['options_id'] . ',';

				// Guardamos
				$aOpciones[$nCont] = $aDato;
				$nCont++;
			}

			// Si contenemos opciones
			if( $nIdsOpciones != '0,' )
			{
				// Obtenemos las acciones que tiene el producto
				$aDatos = tep_db_query( 'SELECT distinct(products_attributes) FROM products_attributes_actions WHERE products_id = "' . (int)$nProductsId . '"' );

				// Guardamos en un array
				while( $aDato = tep_db_fetch_array( $aDatos ) )
					$aAcciones[] = $aDato['products_attributes'];

				// Combobox con las combinaciones
				$sHtml .= '<div class="grid12" style="text-align: right;">';
					$sHtml .= 'Número de combinaciones: &nbsp;&nbsp;';
					$sHtml .= '<select id="sltc-cmbi" style="margin: 0px 0px 10px;">';
						$nTotal = count( $aOpciones );

						for( $nCont = 1; $nCont <= $nTotal; $nCont++ )
							$sHtml .= '<option ' . ($nCont == $nCombinaciones ? 'selected="selected"' : '') . ' value="' . $nCont . '">' . $nCont . ' combinacies</option>';
					$sHtml .= '</select>';
				$sHtml .= '</div>';
				$sHtml .= '<div class="clear"></div>';

				// Obtenemos los valores
				$aDatos = tep_db_query( 'SELECT pov.products_options_values_name, pa.options_id, pa.options_values_id
										 FROM products_attributes pa
										 INNER JOIN products_options_values pov ON(pa.options_values_id = pov.products_options_values_id)
										 WHERE pa.options_id in (' . substr($nIdsOpciones, 0, -1) . ') and pa.products_id = "' . (int)$nProductsId . '" AND pov.language_id = "' . (int)$languages_id . '"
										 ORDER BY pa.products_options_sort_order asc' );

				// Guardamos los valores en un array para realizar las combinaciones
				$nCont = -1;
				$aAux = array();
				while( $aDato = tep_db_fetch_array( $aDatos ) )
				{
					// Si no existe la opcion la creamos
					if( !in_array( $aDato['options_id'], $aAux ) )
					{
						$aAux[] = $aDato['options_id'];
						$nCont++;
						$aValoresCombi[$nCont] = array();
					}

					// Guardamos el valor
					$aValoresCombi[$nCont][] = $aDato['options_values_id'];
					$aValores[$aDato['options_values_id']] = $aDato;
				}

				// Obtenemos las combinaciones
				$aCombinaciones = am_combinationsAttributes( $aValoresCombi, $nCombinaciones );

				// Recorremos
				foreach( $aCombinaciones as $aCombinacion )
				{
					// Variables
					$sTitular = 'Combinacion entre ';
					$aOpcionesAux = array();
					$sHtmlHeader = '';

					// Dividimos las opciones
					$aAux = explode( ',', $aCombinacion['opciones'] );

					// Recorremos las opciones
					foreach( $aAux as $key => $nAux )
					{
						$sTitular .= $aOpciones[$nAux]['products_options_name'] . ' => ';
						$aOpcionesAux[$key] = $aOpciones[$nAux]['options_id'];
						$sHtmlHeader .= '<td style="text-align: left;">' . $aOpciones[$nAux]['products_options_name'] . '</td>';
					}

					$sHtml .= '<div class="box-tbl" style="width: 100%; margin-bottom: 20px;">';
						$sHtml .= '<div class="box-head">';
							$sHtml .= '<h6>' . substr($sTitular, 0, -4) . '</h6>';
							$sHtml .= '<div class="clear"></div>';
						$sHtml .= '</div>';

						$sHtml .= '<table class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= $sHtmlHeader;
									$sHtml .= '<td style="width: 150px;">Acción</td>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';

								// Recorremos
								foreach( $aCombinacion['valores'] as $aAux )
								{
									$sHtml .= '<tr>';
									$sCombinacion = '';

									if( is_array( $aAux ) )
									{
										foreach( $aAux as $key => $sValor )
										{
											$sHtml .= '<td>' .  $aValores[$sValor]['products_options_values_name'] . '</td>';
											$sCombinacion .= $aOpciones[$key]['options_id'] . '-' . $aValores[$sValor]['options_values_id'] . ',';
										}
									}
									else
									{
										$sCombinacion = $aOpciones[0]['options_id'] . '-' . $aValores[$aAux]['options_values_id'] . ',';
										$sHtml .= '<td>' .  $aValores[$aAux]['products_options_values_name'] . '</td>';
									}

									$sCombinacion = substr($sCombinacion, 0, -1);

									$sHtml .= '<td style="text-align: center;"><a data-combi="' . $sCombinacion . '" class="buttonS ' . (in_array($sCombinacion, $aAcciones) ? 'bBlue' : 'bDefault' ) . ' new-actn" href="javascript: void(0);"><span class="icos-power"></span><span>Añadir acción</span></a></td>';

									$sHtml .= '</tr>';
								}

							$sHtml .= '</tbody>';
						$sHtml .= '</table>';
					$sHtml .= '</div>';
				}
			}

			// Retornamos
			return $sHtml;
		}


		// Metodo que cambia los datos de la tabla products_stock
		public function attributeManagerChangeStock($nProductsId, $sCombinacionAtributos, $sKey, $sValue)
		{
			// Variable
			$aAllowField = array();

			// Si el valor no es un numerico no hacemos nada
			if( !is_numeric($sValue) )
				return false;

			// Consulta para obtener los valores permitidos
			$aDatos = tep_db_query( 'DESCRIBE products_stock;' );

			// Recorremos y creamos los valores permitidos
			while( $aDato = tep_db_fetch_array( $aDatos ) )
				$aAllowField[] = $aDato['Field'];

			// Comprobamo si el key es permitido, de ser asi guardamos
			if( in_array( $sKey, $aAllowField ) )
			{
				// Consultamos si existe
				$aDatos = tep_db_query( 'SELECT products_stock_id FROM products_stock WHERE products_stock_attributes = "' . $sCombinacionAtributos . '" and products_id = "' . (int)$nProductsId . '"' );

				// Si existe actualizamos
				if( tep_db_num_rows( $aDatos ) > 0 )
					tep_db_perform( 'products_stock', array($sKey => $sValue), 'update', 'products_stock_attributes = "' . $sCombinacionAtributos . '" and products_id = "' . (int)$nProductsId . '"' );
				else // Si no existe insertamos
					tep_db_perform( 'products_stock', array($sKey => $sValue, 'products_stock_attributes' => $sCombinacionAtributos, 'products_id' => (int)$nProductsId) );
			}

			// Actualizamos stock
			$aDatos = tep_db_query( 'SELECT SUM(products_stock_quantity) AS summa FROM products_stock WHERE products_id = "' . (int)$nProductsId . '" AND products_stock_quantity > 0 ');
			$aDato = tep_db_fetch_array( $aDatos );
			tep_db_perform( 'products', array('products_quantity' => (empty($aDato['summa'])) ? '0' : $aDato['summa']), 'update', 'products_id = "' . (int)$nProductsId . '"' );

			// Actualizamos stock deseado
			$aDatos = tep_db_query( 'SELECT SUM(products_stock_quantity_deseada) AS summa FROM products_stock WHERE products_id = "' . (int)$nProductsId . '" AND products_stock_quantity_deseada > 0 ');
			$aDato = tep_db_fetch_array( $aDatos );
			tep_db_perform( 'products', array('products_quantity_deseada' => (empty($aDato['summa'])) ? '0' : $aDato['summa']), 'update', 'products_id = "' . (int)$nProductsId . '"' );

			// Devolvemos
			return $sValue;
		}

		// Metodo que muestra todas las combinaciones con stock
		public function attributeManagerStock($nProductsId)
		{
			// Variables
			global $sJavascript, $languages_id;
			$sHtml = '';
			$sHtmlOpciones = '';
			$nIdsOpciones = '0,';
			$aValoresCombi = array();
			$aStock = array();

			// Obtenemos las opciones que tiene asignado el producto y tengan control de stock
			$aDatos = tep_db_query( 'SELECT pa.options_id, po.products_options_name
									 FROM products_attributes pa
									 INNER JOIN products_options po ON(pa.options_id = po.products_options_id)
									 WHERE pa.products_id = "' . (int)$nProductsId . '" AND po.language_id = "' . (int)$languages_id . '" AND po.products_options_track_stock = 1
									 GROUP BY pa.options_id
									 ORDER BY pa.products_options_sort_order ASC' );

			// Recorremos las opciones
			while( $aDato = tep_db_fetch_array( $aDatos ) )
			{
				// Html
				$sHtmlOpciones .= '<td style="text-align: left;">' . $aDato['products_options_name'] . '</td>';

				// Guardamos las ids
				$nIdsOpciones .= $aDato['options_id'] . ',';
			}

			// Si contenemos opciones
			if( $nIdsOpciones != '0,' )
			{
				// Obtenemos los valores
				$aDatos = tep_db_query( 'SELECT pov.products_options_values_name, pa.options_id, pa.options_values_id
										 FROM products_attributes pa
										 INNER JOIN products_options_values pov ON(pa.options_values_id = pov.products_options_values_id)
										 WHERE pa.options_id in (' . substr($nIdsOpciones, 0, -1) . ') and pa.products_id = "' . (int)$nProductsId . '" AND pov.language_id = "' . (int)$languages_id . '"
										 ORDER BY pa.products_options_sort_order ASC' );

				// Guardamos los valores en un array para realizar las combinaciones
				$nCont = -1;
				$Aux = array();
				while( $aDato = tep_db_fetch_array( $aDatos ) )
				{
					// Si no existe la opcion la creamos
					if( !in_array( $aDato['options_id'], $Aux ) )
					{
						$Aux[] = $aDato['options_id'];
						$nCont++;
						$aValoresCombi[$nCont] = array();
					}

					// Guardamos el valor
					$aValoresCombi[$nCont][] = $aDato;
				}

				// Obtenemos el stock
				$aDatos = tep_db_query( 'SELECT products_stock_id, products_stock_attributes, products_stock_quantity, products_stock_quantity_deseada
										 FROM products_stock
										 WHERE products_id = "' . (int)$nProductsId . '"' );

				// Guardamos stock
				while( $aDato = tep_db_fetch_array( $aDatos ) )
					$aStock[$aDato['products_stock_attributes']] = $aDato;

				// Html
				$sHtml .= '<div class="box-tbl">';
					$sHtml .= '<table class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">';
						$sHtml .= '<thead>';
							$sHtml .= '<tr>';
								$sHtml .= $sHtmlOpciones;
								$sHtml .= '<td style="width: 90px;">Stock</td>';
								$sHtml .= '<td style="width: 90px;">Stock deseado</td>';
							$sHtml .= '</tr>';
						$sHtml .= '</thead>';
						$sHtml .= '<tbody>';
							// Variables
							$sCode = '';
							$nTotalOptions = count( $aValoresCombi );

							// Recoremos las combinaciones y creamos el codigo
							for( $nCont = 0; $nCont < $nTotalOptions; $nCont++ )
							{
								if( $nCont == $nTotalOptions - 1 )
								{
									$sCode .= '{';
									$sCode .= 'foreach( $aValoresCombi[' . $nCont . '] as $value' . $nCont . ' )';
									$sCode .= '{';
										$sCode .= '$sHtmlLista = \'<tr>\';';
										$sCode .= '$sCombinacion = \'\';';
										for( $nA = 0; $nA < $nTotalOptions; $nA++ )
										{
											$sCode .= '$sCombinacion .= $value' . $nA . '[\'options_id\'] . \'-\' . $value' . $nA . '[\'options_values_id\'] . \',\';';
											$sCode .= '$sHtmlLista .= \'<td>\' . $value' . $nA . '[\'products_options_values_name\'] . \'</td>\';';
										}

										// Combinacion
										$sCode .= '$sCombinacion = substr( $sCombinacion, 0, -1 );';

										// Stock y stock deseado
										$sCode .= '$sStock = 0;';
										$sCode .= '$sStockDeseado = 0;';

										// Comprobamos stock
										$sCode .= 'if( array_key_exists( $sCombinacion, $aStock ) )';
										$sCode .= '{';
											$sCode .= '$sStock = $aStock[$sCombinacion][\'products_stock_quantity\'];';
											$sCode .= '$sStockDeseado = $aStock[$sCombinacion][\'products_stock_quantity_deseada\'];';
										$sCode .= '}';

										$sCode .= '$sHtml .= $sHtmlLista . \'<td class="input100"><input name="products_stock_quantity" data-combi="\' . $sCombinacion . \'" value="\' . $sStock . \'" \' . ($sStock != 0 ?\' style="background: #f9f9df;" \' : \'\') . \'/></td><td class="input100"><input name="products_stock_quantity_deseada" data-combi="\' . $sCombinacion . \'" name="" value="\' . $sStockDeseado . \'" \' . ($sStockDeseado != 0 ?\' style="background: #f9f9df;" \' : \'\') . \'/></td></tr>\';';
									$sCode .= '}';
									$sCode .= '}';
								}
								else
								{
									$sCode .= 'foreach( $aValoresCombi[' . $nCont . '] as $value' . $nCont . ' )';
								}
							}

							// Ejecutamos el código
							eval ($sCode);
						$sHtml .= '</tbody>';
					$sHtml .= '</table>';
				$sHtml .= '</div>';
			}

			// Retornamos
			return $sHtml;
		}

		// Metodo que ataca al archivo products_attributes.php para mostrar fomularios etc
		public function attributeManagerProductsAttributes()
		{
			// Variables
			global $messageStack;
			$_GET = $_POST;
			$bLoadFileProductsAttributes = true;

			// Incluimos libreria
			include( 'products_attributes.php' );

			// Cargamos javascript
			echo '<script type="text/javascript">
				// Inicio, combobox idioma, para cambiar entre idiomas
				$("#dialog .change_idioma").change(function()
				{
					$("#dialog .tab-change-idma-" + $(this).data("id")).css("display", "none");
					$("#dialog #change-idma-" + $(this).data("id") + "-" + $(this).val()).css("display", "block");
				});

				if( $("#dialog .change_idioma").length > 0 )
					$("#dialog .change_idioma").trigger( "change" );
				// Fin, combobox idioma, para cambiar entre idiomas

				// Recargamos uniform
				$("#dialog select").uniform();
			</script>';

			echo $sJavascript;
			echo '<script type="text/javascript" src="../includes/classes/attributes/functions.js"></script>';
		}

		// Metodo que cambia datos de un valor
		public function attributeManagerChangeData($nIdAtributo, $sKey, $sValue)
		{
			// Variable
			$aAllowField = array();

			// Consulta para obtener los valores permitidos
			$aDatos = tep_db_query( 'DESCRIBE products_attributes;' );

			// Recorremos y creamos los valores permitidos
			while( $aDato = tep_db_fetch_array( $aDatos ) )
				$aAllowField[] = $aDato['Field'];

			// Comprobamo si el key es permitido, de ser asi guardamos
			if( in_array( $sKey, $aAllowField ) )
			{
				// Acciones
				switch($sKey)
				{
					case 'options_values_weight':
					case 'options_values_price':
						// Si no es un numero
						if( !is_numeric($sValue) )
							$sValue = 0;

						// Formateamos
						$sValue = number_format( $sValue, 4 );
					break;
				}

				// Actualizamos
				tep_db_perform( 'products_attributes', array($sKey => $sValue), 'update', 'products_attributes_id = "' . (int)$nIdAtributo . '"' );
			}

			// Devolvemos
			return $sValue;
		}

		// Metodo que inserta valores
		public function attributeManagerAdd($nProductsId, $nIdOption, $nIdValue, $nKey, $sJsonData)
		{
			// Comprobamos que no exista
			$aDatos = tep_db_query( 'SELECT products_attributes_id FROM products_attributes WHERE products_id = "' . (int)$nProductsId . '" AND options_id = "' . $nIdOption . '" AND options_values_id = "' . (int)$nIdValue . '"' );

			// Si no existe
			if( tep_db_num_rows($aDatos) <= 0 )
			{
				// Insertamos
				tep_db_perform( 'products_attributes', array( 'products_id' => (int)$nProductsId, 'options_id' => (int)$nIdOption, 'options_values_id' => (int)$nIdValue) );

				// Obtenemos el id insertado
				$nIdLast = tep_db_insert_id();

				// Json con array con los ids de todos los atributos
				$aDatos = json_decode($sJsonData);

				// Modificamos el último registro que es el que hemos insertado
				$aDatos[$nKey][] = $nIdLast;

				// Convertimos a json
				$sJsonData = json_encode( $aDatos );
			}

			// Actualizamos el orden y mostramos los valores de nuevo
			return json_encode( array( 'id' => $nIdLast, 'html' => $this->attributeManagerSort($nProductsId, $sJsonData, true, $nIdOption) ) );
		}

		// Método que elimina valores
		public function attributeManagerDelete($nProductsId, $nOptionId, $aDelete = array(), $sJsonData = "")
		{
			// Eliminamos
			tep_db_query( 'DELETE FROM products_attributes WHERE products_id = "' . (int)$nProductsId . '" and products_attributes_id IN (' . implode(',', $aDelete) . ')' );

			// Actualizamos el orden
			if( $sJsonData != '' )
				$this->attributeManagerSort($nProductsId, $sJsonData);

			// Return
			return $this->attributeManagerValuesOption($nProductsId, $nOptionId);
		}

		// Método que ordena los valores
		public function attributeManagerSort($nProductsId, $sJsonData, $bReturn = false, $nOptionId = 0, $nLastPosition = false, $nNowPosition = false)
		{
			// Variables
			global $languages_id;
			$sSql = '';
			$nCont = 0;
			$nIds = '';
			$bOrdenarOpcion = false;
			$bFoundOptionTrackStock = false;

			// Si nos envian posicion anterior y actual es que hemos ordenado una opción
			if( $nLastPosition !== false && $nNowPosition !== false && $nLastPosition != $nNowPosition )
				$bOrdenarOpcion = true;

			// Si tenemos que ordenar opcion
			if( $bOrdenarOpcion )
			{
				// Variables
				$nLastPositionOption = 0; // Usado para encontrar la posición que tiene la opcion

				// Obtenemos las opciones que tiene asignado el producto y tengan control de stock
				$aDatos = tep_db_query( 'SELECT pa.options_id
										 FROM products_attributes pa
										 INNER JOIN products_options po ON(pa.options_id = po.products_options_id)
										 WHERE pa.products_id = "' . (int)$nProductsId . '" AND po.language_id = "' . (int)$languages_id . '" AND po.products_options_track_stock = 1
										 GROUP BY pa.options_id
										 ORDER BY pa.products_options_sort_order ASC' );

				// Recorremos
				while( $aDato = tep_db_fetch_array( $aDatos ) )
				{
					// Si encontramos la id detenemos
					if($aDato['options_id'] == $nOptionId)
					{
						$bFoundOptionTrackStock = true;
						break;
					}

					$nLastPositionOption++;
				}
			}

			// Json con array con los ids de todos los atributos
			$aDatos = json_decode($sJsonData);

			// Recorremos para crear el SQL
			if( is_array($aDatos) )
			{
				foreach( $aDatos as $aDato )
				{
					if( is_array($aDato) )
					{
						foreach( $aDato as $nId )
						{
							if( is_numeric($nId) )
							{
								$sSql .= ' WHEN ' . $nId . ' THEN ' . $nCont . ' ';
								$nIds .= $nId . ',';
								$nCont++;
							}
						}
					}
				}
			}

			// Ejecutamos consulta
			if( $sSql != '' )
				tep_db_query( 'UPDATE products_attributes SET products_options_sort_order = CASE products_attributes_id ' . $sSql . ' ELSE products_options_sort_order END WHERE products_id = "' . (int)$nProductsId . '" AND products_attributes_id IN (' . substr($nIds, 0, -1) . ')' );

			// Si tenemos que ordenar opcion
			if( $bOrdenarOpcion )
			{
				// Variables
				$nNowPositionOption = 0; // Usado para encontrar la posición que tiene la opcion

				// Si hemos encontrado anteriormente la opción es que es una opcion con control de stock
				if( $bFoundOptionTrackStock )
				{
					// Obtenemos las opciones que tiene asignado el producto y tengan control de stock
					$aDatos = tep_db_query( 'SELECT pa.options_id
											 FROM products_attributes pa
											 INNER JOIN products_options po ON(pa.options_id = po.products_options_id)
											 WHERE pa.products_id = "' . (int)$nProductsId . '" AND po.language_id = "' . (int)$languages_id . '" AND po.products_options_track_stock = 1
											 GROUP BY pa.options_id
											 ORDER BY pa.products_options_sort_order ASC' );

					// Recorremos
					while( $aDato = tep_db_fetch_array( $aDatos ) )
					{
						// Si encontramos la id detenemos
						if($aDato['options_id'] == $nOptionId)
							break;

						$nNowPositionOption++;
					}

					// Si tenemos que cambiar la posicion
					if( $nLastPositionOption != $nNowPositionOption )
					{
						// Variables
						$sSql = '';
						$nIds = '';

						// Obtenemos todo el stock del producto
						$aDatos = tep_db_query( 'SELECT products_stock_id, products_stock_attributes FROM products_stock WHERE products_id = "' . (int)$nProductsId . '"' );

						// Recorremos
						while( $aDato = tep_db_fetch_array( $aDatos ) )
						{
							// Convertimos a array
							$aDato['products_stock_attributes'] = explode( ',', $aDato['products_stock_attributes'] );

							// Si la posicion anterior es menor que la posición nueva
							if( $nLastPositionOption < $nNowPositionOption )
							{
								$nTotal = $nNowPositionOption - $nLastPositionOption;
								$nAux = $nLastPositionOption;
								$bFunctionUse = 'down';
							}
							else
							{
								$nTotal = $nLastPositionOption - $nNowPositionOption;
								$nAux = $nLastPositionOption;
								$bFunctionUse = 'up';
							}

							// Recorremos para posicionar
							for($nCont = 0; $nCont < $nTotal; $nCont++ )
							{
								if( $bFunctionUse == 'down' )
								{
									$aDato['products_stock_attributes'] = am_arrayDown($aDato['products_stock_attributes'], $nAux);
									$nAux++;
								}
								else
								{
									$aDato['products_stock_attributes'] = am_arrayUp($aDato['products_stock_attributes'], $nAux);
									$nAux--;
								}
							}

							// Convertimos a string
							$aDato['products_stock_attributes'] = preg_replace( '/^\,/i', '', implode( ',', $aDato['products_stock_attributes'] ) );

							// Creamos SQL
							$sSql .= ' WHEN ' . $aDato['products_stock_id'] . ' THEN "' . $aDato['products_stock_attributes'] . '" ';
							$nIds .= $aDato['products_stock_id'] . ',';
						}

						// Ejecutamos consulta
						if( $sSql != '' )
							tep_db_query( 'UPDATE products_stock SET products_stock_attributes = CASE products_stock_id ' . $sSql . ' ELSE products_stock_attributes END WHERE products_id = "' . (int)$nProductsId . '" AND products_stock_id IN (' . substr($nIds, 0, -1) . ')' );
					}
				}

			/*
				// Variables
				$sSql = '';
				$nIds = '';

				// Obtenemos todo el stock del producto
				$aDatos = tep_db_query( 'SELECT products_stock_id, products_stock_attributes FROM products_stock WHERE products_id = "' . (int)$nProductsId . '"' );

				// Recorremos
				while( $aDato = tep_db_fetch_array( $aDatos ) )
				{
					// Convertimos a array
					$aDato['products_stock_attributes'] = explode( ',', $aDato['products_stock_attributes'] );

					// Ordenamos
					$sAux = $aDato['products_stock_attributes'][$nLastPosition];
					$aDato['products_stock_attributes'][$nLastPosition] = $aDato['products_stock_attributes'][$nNowPosition];
					$aDato['products_stock_attributes'][$nNowPosition] = $sAux;

					// Convertimos a string
					$aDato['products_stock_attributes'] = preg_replace( '/^\,/i', '', implode( ',', $aDato['products_stock_attributes'] ) );

					// Creamos SQL
					$sSql .= ' WHEN ' . $aDato['products_stock_id'] . ' THEN "' . $aDato['products_stock_attributes'] . '" ';
					$nIds .= $aDato['products_stock_id'] . ',';
				}

				// Ejecutamos consulta
				if( $sSql != '' )
					tep_db_query( 'UPDATE products_stock SET products_stock_attributes = CASE products_stock_id ' . $sSql . ' ELSE products_stock_attributes END WHERE products_id = "' . (int)$nProductsId . '" AND products_stock_id IN (' . substr($nIds, 0, -1) . ')' );*/
			}

			// Retornamos
			if( ($bReturn === true || $bReturn == 'true') && $nOptionId != 0 )
				return $this->attributeManagerValuesOption($nProductsId, $nOptionId );
		}

		// Metodo que devuelve los valores de una opcion
		public function attributeManagerValuesOption($nProductsId, $nOptionId)
		{
			// Variables
			global $messageStack, $languages_id;
			$sHtml = '';
			$nIdsValores = '';
			$aComboPrefix = array(
				array( 'id' => '+', 'text' => '+' ),
				array( 'id' => '-', 'text' => '-' )
			);

			// Comprobamos si no es un entero realizamos otro método
			if( !is_numeric($nOptionId) )
			{
				switch($nOptionId)
				{
					case 'accion': return $this->attributeManagerAction($nProductsId); break;
					case 'stock': return $this->attributeManagerStock($nProductsId); break;

					default:
						echo $messageStack->show( array( 'class' => 'wrng', 'text' => 'La acción que intentas realizar no existe' ) );
					break;
				}

				// Detenemos
				exit();
			}

			// Obtenemos los valores
			$aDatos = tep_db_query( 'SELECT pa.products_attributes_id, pov.products_options_values_id, pov.products_options_values_name, pa.options_values_price, pa.price_prefix, pa.reference, pa.reference_prov, pa.products_attributes_ean, pa.weight_prefix, pa.options_values_weight, pa.options_values_id
									 FROM products_attributes pa
									 INNER JOIN products_options_values pov ON(pa.options_values_id = pov.products_options_values_id)
									 WHERE pa.options_id = "' . (int)$nOptionId . '" and pa.products_id = "' . (int)$nProductsId . '" AND pov.language_id = "' . (int)$languages_id . '"
									 ORDER BY pa.products_options_sort_order asc' );

			$sHtml .= '<div class="box-tbl">';
				$sHtml .= '<table class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">';
					$sHtml .= '<thead>';
						$sHtml .= '<tr>';
							$sHtml .= '<td width="17" class="check"><input class="all-checked" type="checkbox"/></td>';
							$sHtml .= '<td style="text-align: left;">Valor</td>';
							$sHtml .= '<td style="text-align: left;">Precio</td>';
							$sHtml .= '<td style="text-align: left;">Peso</td>';
							$sHtml .= '<td style="text-align: left;">Referencia</td>';
							$sHtml .= '<td style="text-align: left;">EAN</td>';
							$sHtml .= '<td style="text-align: center;" width="125">Acciones</td>';
						$sHtml .= '</tr>';
					$sHtml .= '</thead>';
					$sHtml .= '<tbody>';
						// Recorremos los valores
						while( $aDato = tep_db_fetch_array( $aDatos ) )
						{
							$sHtml .= '<tr>';
								$sHtml .= '<td class="check negative" align="center"><input name="id[]" value="' . $aDato['products_attributes_id'] . '" data-option_value="' . $aDato['products_options_values_id'] . '" type="checkbox"/></td>';
								$sHtml .= '<td style="text-align: left;">' . $aDato['products_options_values_name'] . '</td>';
								$sHtml .= '<td class="prfj">';
									$sHtml .= tep_draw_pull_down_menu( 'price_prefix', $aComboPrefix, $aDato['price_prefix'], 'class="skip" data-id="' . $aDato['products_attributes_id'] . '"');
									$sHtml .= '<input name="options_values_price" data-id="' . $aDato['products_attributes_id'] . '" value="' . $aDato['options_values_price'] . '" />';
								$sHtml .= '</td>';
								$sHtml .= '<td class="prfj">';
									$sHtml .= tep_draw_pull_down_menu( 'weight_prefix', $aComboPrefix, $aDato['weight_prefix'], 'class="skip" data-id="' . $aDato['products_attributes_id'] . '"');
									$sHtml .= '<input name="options_values_weight" data-id="' . $aDato['products_attributes_id'] . '" value="' . $aDato['options_values_weight'] . '" />';
								$sHtml .= '</td>';
								$sHtml .= '<td class="rfrc">';
									$sHtml .= '<input name="reference" value="' . $aDato['reference'] . '" data-id="' . $aDato['products_attributes_id'] . '" />';
								$sHtml .= '</td>';
								$sHtml .= '<td class="rfrc">';
									$sHtml .= '<input name="reference_prov" value="' . $aDato['reference_prov'] . '" data-id="' . $aDato['products_attributes_id'] . '" />';
								$sHtml .= '</td>';
								$sHtml .= '<td class="ean">';
									$sHtml .= '<input name="products_attributes_ean" value="' . $aDato['products_attributes_ean'] . '" data-id="' . $aDato['products_attributes_id'] . '" />';
								$sHtml .= '</td>';
								$sHtml .= admin_setTableTdAction( array(
									array( 'HREF' => tep_href_link( 'products_attributes.php', 'ido=' . $nOptionId . '&a=value_edit_form&id=' . $aDato['products_options_values_id'] ), 'ATTR_HREF' => 'target="_blank"', 'CLASS' => 'icos-pencil', 'TEXT' => 'Editar valor' ),
									array( 'HREF' => 'javascript:void(0);', 'CLASS' => 'icos-trash', 'TEXT' => 'Eliminar', 'CLASS_HREF' => 'dlte', 'ATTR_HREF' => 'data-id="' . $aDato['products_attributes_id'] . '"' )
								));
							$sHtml .= '</tr>';

							// Guardamos ID
							$nIdsValores .= $aDato['options_values_id'] . ',';
						}
					$sHtml .= '</tbody>';
				$sHtml .= '</table>';
				$sHtml .= '<div class="tableFooter">';
					$sHtml .= '<div id="opciones_masivas" style="float: left;">
						<div class="btn-group">
							<a data-toggle="dropdown" class="buttonS bLightBlue">Seleccione opción masiva<span class="caret"></span></a>
							<ul class="dropdown-menu">
								<li><a class="dltes" href="javascript:void(0);"><span class="icos-trash"></span>Eliminar</a></li>
							</ul>
						</div>
					</div>';

					$sHtml .= '<div class="inpt-add">';
						$sHtml .= '<select id="inst-vlor" data-placeholder="Seleccionar valor" class="chosen skip" style="width: 100%;">';
							$sHtml .= '<option value=""></option>';

							// Obtenemos todas los valores que no esten ya asignadas al producto
							$aDatos = tep_db_query( 'SELECT pov.products_options_values_id, pov.products_options_values_name
													 FROM products_options_values pov
													 INNER JOIN products_options_values_to_products_options pov2po ON(pov.products_options_values_id = pov2po.products_options_values_id)
													 WHERE pov.language_id = "' . (int)$languages_id . '" AND pov2po.products_options_id = "' . (int)$nOptionId . '"' . ($nIdsValores != '' ? 'AND pov.products_options_values_id NOT IN(' . substr($nIdsValores, 0, -1) . ')' : '') );

							// Recorremos
							while( $aDato = tep_db_fetch_array( $aDatos ) )
								$sHtml .= '<option value="' . $aDato['products_options_values_id'] . '">' . $aDato['products_options_values_name'] . '</option>';

						$sHtml .= '</select>';
						$sHtml .= '<a id="crte-vlor" class="tablectrl_small bDefault tipS" href="javascript: void(0);"><span class="icos-add"></span></a>';
					$sHtml .= '</div>';
					$sHtml .= '<div class="clear"></div>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';

			// Retornamos
			return $sHtml;
		}

		// Metodo que muestra el attributeManager
		public function attributeManager($nProductsId, $bLib = true)
		{
			// Variables
			global $sJavascript, $messageStack, $languages_id;
			$sHtml = '';
			$sHtmlOpciones = '';
			$nIdsOpciones = '';
			$nCantidadAcciones = 0;

			// Si no nos envian products_id detenemos
			if( $nProductsId == '' )
				return $messageStack->show( array( 'class' => 'wrng', 'text' => 'Debes de guardar el producto antes de añadir opciones' ) );

			// Cargamos la libreria CSS y JS
			if( $bLib )
			{
				$sJavascript .= '<link rel="stylesheet" type="text/css" href="../includes/classes/attributes/style.css"/>';
				$sJavascript .= '<script type="text/javascript" src="../includes/classes/attributes/chosen.js"></script>';
				$sJavascript .= '<script type="text/javascript" src="../includes/classes/attributes/attributeManager.js"></script>';
			}

			// Obtenemos las opciones que tiene asignado el producto
			$aDatos = tep_db_query( 'SELECT pa.options_id, po.products_options_name, count(pa.options_id) AS cantidad
									 FROM products_attributes pa
									 INNER JOIN products_options po ON(pa.options_id = po.products_options_id)
									 WHERE pa.products_id = "' . (int)$nProductsId . '" AND po.language_id = "' . (int)$languages_id . '"
									 GROUP BY pa.options_id
									 ORDER BY pa.products_options_sort_order asc' );

			// Variable auxiliar para saber si es el primer registro y obtener su id
			$nAuxOptionId = 0;

			// Recorremos las opciones
			while( $aDato = tep_db_fetch_array( $aDatos ) )
			{
				$sHtmlOpciones .= '<li' . ($nAuxOptionId == 0 ? ' class="activeli"' : '') . '><a data-oid="' . $aDato['options_id'] . '"' . ($nAuxOptionId == 0 ? ' class="this"' : '') . ' href="javascript:void(0);"><span class="icos-trash"></span>' . $aDato['products_options_name'] . ' (<span class="cant">' . $aDato['cantidad'] . '</span>)' . '</a></li>';

				// Guardamos la id opcion
				if( $nAuxOptionId == 0 )
					$nAuxOptionId = $aDato['options_id'];

				// Guardamos las ids
				$nIdsOpciones .= $aDato['options_id'] . ',';
			}

			// Obtenemos cuantas acciones tenemos
			$aDatos = tep_db_query( 'SELECT count(distinct(products_attributes)) AS cantidad FROM products_attributes_actions WHERE products_id = "' . (int)$nProductsId . '"' );

			if( tep_db_num_rows( $aDatos ) > 0 )
			{
				$aDatos = tep_db_fetch_array($aDatos);
				$nCantidadAcciones = $aDatos['cantidad'];
			}

			// Html attributeManager
			$sHtml .= '<div class="box-head">';
				$sHtml .= '<h6>Opciones y valores del producto (Atributos)</h6>';

				$sHtml .= '<div id="am_template" style="float: right; position: relative; top: 1px;">';
					$sHtml .= '<select class="skip" style="float: left; margin-right: 8px;">';
						$sHtml .= '<option value="" selected="">-- Plantillas --</option>';
						$sHtml .= '<option value="-1">+ Nueva plantilla</option>';

						// Obtenemos las plantillas
						$aDatos = tep_db_query( 'SELECT template_name, template_id FROM am_templates ORDER BY template_name ASC' );

						// Recorremos las plantillas
						while( $aDato = tep_db_fetch_array( $aDatos ) )
							$sHtml .= '<option value="' . $aDato['template_id'] . '">' . $aDato['template_name'] . '</option>';
					$sHtml .= '</select>';
					$sHtml .= '<a class="load" style="position: relative; top: 7px; margin-right: 8px; display: block; float: left" href="javascript:void(0);" title="Carga la plantilla seleccionada" type="image" border="0"><img src="../includes/classes/attributes/images/icon_load.png" /></a>';
					$sHtml .= '<a class="save" style="position: relative; top: 7px; margin-right: 8px; display: block; float: left" href="javascript:void(0);" title="Guardar los atributos actuales" type="image" border="0"><img src="../includes/classes/attributes/images/icon_save.png" /></a>';
					$sHtml .= '<a class="edit" style="position: relative; top: 7px; margin-right: 8px; display: block; float: left" href="javascript:void(0);" title="Renombrar la plantilla seleccionada" type="image" border="0"><img src="../includes/classes/attributes/images/icon_rename.png" /></a>';
					$sHtml .= '<a class="delete" style="position: relative; top: 7px; margin-right: 11px; display: block; float: left" href="javascript:void(0);" title="Borrar la plantilla seleccionada" type="image" border="0"><img src="../includes/classes/attributes/images/icon_delete.png" /></a>';
				$sHtml .= '</div>';

				$sHtml .= '<div class="clear"></div>';
			$sHtml .= '</div>';
			$sHtml .= '<div class="formRow" id="atrb-mngr" data-pid="' . $nProductsId . '">';
				$sHtml .= '<div class="line"></div>';
				$sHtml .= '<div class="izqd">';
					$sHtml .= '<div class="inpt-add">';
						$sHtml .= '<select id="inst-optn" data-placeholder="Seleccionar opción" class="chosen skip" style="width: 100%;">';
							$sHtml .= '<option value=""></option>';

							// Obtenemos todas las opciones que no esten ya asignadas al producto
							$aDatos = tep_db_query( 'SELECT products_options_id, products_options_name
													 FROM products_options
													 WHERE language_id = "' . (int)$languages_id . '" ' . ($nIdsOpciones != '' ? 'AND products_options_id NOT IN(' . substr($nIdsOpciones, 0, -1) . ')' : '') );

							// Recorremos
							while( $aDato = tep_db_fetch_array( $aDatos ) )
								$sHtml .= '<option value="' . $aDato['products_options_id'] . '">' . $aDato['products_options_name'] . '</option>';

						$sHtml .= '</select>';
						$sHtml .= '<a id="crte-optn" class="tablectrl_small bDefault tipS" href="javascript:void(0);"><span class="icos-add"></span></a>';
					$sHtml .= '</div>';
					$sHtml .= '<ul class="subNav">';
						$sHtml .= $sHtmlOpciones;
						$sHtml .= '<li class="cncl"><a data-oid="accion" title="" href="javascript:void(0);"><span class="icos-power"></span>Acciones <strong>' . $nCantidadAcciones . '</strong></a></li>';
						$sHtml .= '<li class="cncl"><a data-oid="stock" title="" href="javascript:void(0);"><span class="icos-clipboard"></span>Stock</a></li>';
					$sHtml .= '</ul>';
				$sHtml .= '</div>';
				$sHtml .= '<div class="drch">';
					if( $nAuxOptionId != 0 )
						$sHtml .= $this->attributeManagerValuesOption($nProductsId, $nAuxOptionId);
				$sHtml .= '</div>';
				$sHtml .= '<div class="clear"></div>';

				// Array [key] => valores para poder ordenar
				$aAuxJson = array();
				$nKey = -1;
				$nLastOptionId = 0;

				// Obtenemos todos las opciones/valores para crear un array y así poder ordenarlos
				$aDatos = tep_db_query( 'SELECT options_id, products_attributes_id
										 FROM products_attributes
										 WHERE products_id = "' . (int)$nProductsId . '"
										 ORDER BY products_options_sort_order asc' );

				// Creamos el array
				while( $aDato = tep_db_fetch_array( $aDatos ) )
				{
					if( $nLastOptionId != $aDato['options_id'] )
					{
						$nKey++;
						$aAuxJson[$nKey] = array();
						$nLastOptionId = $aDato['options_id'];
					}

					$aAuxJson[$nKey][] = $aDato['products_attributes_id'];
				}

				// Pintamos el array en json para obtenerlo en JS
				$sHtml .= '<div class="array" style="display: none;">' . json_encode($aAuxJson) . '</div>';

				// Ventana dialog
				$sHtml .= '<div id="dialog"></div>';
			$sHtml .= '</div>';

			return $sHtml;
		}

		// Metodo que obtiene los tipos de opciones que existen
		public function getOptionsTypes()
		{
			// Si ya hemos obtenidos los addons instalados
			if( isset( $this->aOptionsTypes ) && is_array( $this->aOptionsTypes ) )
				return $this->aOptionsTypes;

			// Creamos array
			$this->aOptionsTypes = array();

			// Obtenemos los diferentes tipos que existen
			$aDirs = scandir( dirname( __FILE__ ) . '/types/' );

			// Recorremos el directorio
			foreach( $aDirs as $sType )
			{
				// Denegamos
				if( in_array( $sType, array( '.', '..' ) ) )
					continue;

				// Vamos guardando los diferentes tipos
				$this->aOptionsTypes[] = $sType;
			}

			// Retornamos
			return $this->aOptionsTypes;
		}

		// Metodo que obtiene las acciones que existen
		public function getOptionsActions()
		{
			// Si ya hemos obtenidos los addons instalados
			if( isset( $this->aActions ) && is_array( $this->aActions ) )
				return $this->aActions;

			// Creamos array
			$this->aActions = array();

			// Obtenemos los diferentes tipos que existen
			$aDirs = scandir( dirname( __FILE__ ) . '/actions/' );

			// Recorremos el directorio
			foreach( $aDirs as $sDir )
			{
				// Denegamos
				if( in_array( $sDir, array( '.', '..' ) ) )
					continue;

				// Vamos guardando los diferentes tipos
				$this->aActions[] = $sDir;
			}

			// Retornamos
			return $this->aActions;
		}
	}
?>