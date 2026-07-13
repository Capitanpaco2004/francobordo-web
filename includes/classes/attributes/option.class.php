<?php
	class option
	{
		public function __construct()
		{
			// Obtenemos los tipos que existen
			$this->getOptionsTypes();
		}

		//////////////////////////
		// PROPIEDADES PRIVADAS //
		//////////////////////////

		// Contiene un array con los diferentes tipos de opciones que existen
		private $aOptionsTypes;
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

				// Control de stock POR VARIANTE: mapa "oid-ovid" => 1 de variantes con flag propio
				// (products_attributes.check_stock). Lo usa cart.js para NO ofrecer el modal
				// "7-10 dias" en variantes controladas (paridad con check_stock de producto).
				$sHtml .= '<div id="array_option_checkstock" style="display: none;">' . json_encode( function_exists('fb_variant_check_map') ? fb_variant_check_map($nProductsId) : array() ) . '</div>';
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
	}
