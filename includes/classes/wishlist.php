<?php
class wishlist
{
	/** Constructor
	 */
	public function __construct()
	{
		// Variables
		global $customer_id, $language, $aWishlist;

		// Inluimos el idioma
		require( $_SERVER['DOCUMENT_ROOT'] . '/' . DIR_WS_LANGUAGES . $language . '/wishlist.php' );

		// Declaramos el id de usuario como false
		$nIdCustomer = false;

		// Si nos encontramos logueados obtemos el id de usuario
		if( tep_session_is_registered( 'customer_id' ) )
			$this->nIdCustomer = $customer_id;

		// Comprobamos si necesitamos añadir a la session el array de wishlist, solo para usuarios no registrados
		if( !$this->nIdCustomer && !tep_session_is_registered( 'aWishlist' ) )
		{
			tep_session_register( 'aWishlist' );
			$aWishlist = array();
		}

		// Obtenemos un array con todos los productos que estan en el wishlist
		$this->aProductsWishlist = $this->getArrayProductsWishlist();
	}


	//////////////////////////
	// PROPIEDADES PÚBLICAS //
	//////////////////////////

	// Array con los los productos que contenemos en nuestro wishlist, pero sin información solo id de productos e ids de atributos
    public $aProductsWishlist;


	//////////////////////////
	// PROPIEDADES PRIVADAS //
	//////////////////////////

	// Id del usuario
	public $nIdCustomer;

	//////////////////////
	// MÉTODOS PÚBLICOS //
	//////////////////////
	/**
	 *  Devuelve la cantidad de productos que tenemos en el wishlist
	 *  @return int
	 */
	public function getCantidadWishlist()
	{
		global $aWishlist;
	
		// Si no estamos logueados
		if( $this->nIdCustomer == false )
			return count($aWishlist);
		
		$aProductos = $this->getProductsWishlist();
		return $aProductos['TOTAL'];
	}

	
	/**
	 *  Devuelve un array con todos los productos que contiene nuestro wishlist
	 *  @return array
	 */
	public function getProductsWishlist()
	{
		// Variables
		global $languages_id, $aWishlist;
	
		// Si estamos logeados
		if( $this->nIdCustomer )
		{
			$sSql = 'select w.atributo, p.products_id, p.products_image, pd.products_name, p.products_model, p.products_quantity, p.products_price, p.products_tax_class_id
					 from wishlist w
					 inner join products p on(p.products_id = w.products_id)
					 inner join products_description pd on(pd.products_id = p.products_id)
					 where p.products_status = 1 and w.customers_id = ' . $this->nIdCustomer . ' and pd.language_id = ' . $languages_id;
		}
		elseif( count( $aWishlist ) > 0 )
		{
			// Obtenemos los id
			$sIds = '';
			foreach( $aWishlist as $value )
				$sIds .= $value['products_id'] . ',';
		
			$sSql = 'select p.products_id, p.products_image, pd.products_name, p.products_model, p.products_quantity, p.products_price, p.products_tax_class_id
					 from products p
					 inner join products_description pd on(pd.products_id = p.products_id)
					 where p.products_status = 1 and p.products_id in(' . substr( $sIds, 0, -1 ) . ') and pd.language_id = ' . $languages_id;
		}
		
		// Si no hemos podido hacer consulta es que no tenemos datos
		if( $sSql == '' )
			return array( 'PRODUCTOS' => array(), 'TOTAL' => 0 );
	
		// Obtenemos el paginador y los productos
		$aAux = changePriceCustomer( $sSql, array( 'PRODUCTS_ARRAY' => true, 'PAGINAR' => false ) );
		$aProductos = $aAux['PRODUCTOS'];
		
		// Si no estamos logeados recorremos los productos del wishlist y le asignamos los valores del producto
		if( $this->nIdCustomer == false )
		{
			// Guardamos los productos en un array auxiliar
			$aAuxProductos = $aProductos;
			
			// Reiniciamos los productos
			$aProductos = array();

			// Recorremos el array de wishlist
			foreach( $aWishlist as $aProductoWishlist )
			{
				// Buscamos el producto
				foreach( $aAuxProductos as $aAux )
				{
					// Si encontramos el producto
					if( $aProductoWishlist['products_id'] == $aAux['products_id'] )
					{
						// Añadimos al array el key atributo
						$aAux['atributo'] = '';

						// Si contiene atributo se lo añadimos
						if( $aProductoWishlist['atributo'] != '' )
							$aAux['atributo'] = $aProductoWishlist['atributo'];

						// Agremos producto
						$aProductos[] = $aAux;
							
						break;
					}
				}
			}
		}
	
		// Total de productos
		$nProductosTotal = count( $aProductos );

		// Recorremos los productos para asignarle los atributos
		for( $nCont = 0; $nCont < $nProductosTotal; $nCont++ )
		{
			// Creamos un array para guardar las id de los atributos, si tuviese
			$aProductos[$nCont]['id_atributo'] = array("id" => array());
		
			// Si contiene atributos			
			if( $aProductos[$nCont]['atributo'] != '' )
			{
				// Descomponemos las distintas opciones
				$aOpciones = explode( ',', $aProductos[$nCont]['atributo'] );

				// Creamos un array para añadir los atributos
				$aProductos[$nCont]['atributo'] = array();
				
				// Recorremos las opciones
				foreach( $aOpciones as $opcion )
				{
					// Descomponemos los atributos
					$aAtributos = explode( '-', $opcion );
					
					// Consulta para obtener el atributo y su valor
					$aDatos = tep_db_query( 'select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix
											 from products_options popt
											 inner join products_attributes pa on (pa.options_id = popt.products_options_id)
											 inner join products_options_values poval on (pa.options_values_id = poval.products_options_values_id)
											 where pa.products_id = ' . preg_replace( '/\{.+$/i', '', $aProductos[$nCont]['products_id'] ) . '
											 and pa.options_id = ' . $aAtributos[0] . '
											 and pa.options_values_id = ' . $aAtributos[1] . '
											 and popt.language_id = ' . $languages_id . '
											 and poval.language_id = ' . $languages_id );

					$aDatos = tep_db_fetch_array( $aDatos );
											 
					$aProductos[$nCont]['atributo'][] = array( 'key' => $aDatos['products_options_name'], 'value' => $aDatos['products_options_values_name']  );
					$aProductos[$nCont]['id_atributo']["id"][$aAtributos[0]] = $aAtributos[1];
				}
			}
		}

		return array( 'PRODUCTOS' => $aProductos, 'TOTAL' => $nProductosTotal );
	}
	
	/**
	 *  Devuelve un array con todos los productos que contiene nuestro wishlist, pero sin información solo id de productos e ids de atributos
	 *  @return array
	 */
	public function getArrayProductsWishlist()
	{
		global $aWishlist;
	
		// Si nos encontramos logueados
		if( $this->nIdCustomer )
		{
			$aReturn = array();

			$aDatos = tep_db_query( 'select products_id, atributo from wishlist where customers_id = ' . $this->nIdCustomer );
			
			while( $aDato = tep_db_fetch_array( $aDatos ) )
				$aReturn[] = $aDato;

			return $aReturn;
		}
		// Si no estamos logueados retornamos el array de wishlist
		else
			return $aWishlist;
			
	}
	
	/**
	 *  Añade los productos que tenemos en el array a la cuenta de usuario
	 *  @return bool
	 */
	public function addArrayWishlistToAccount()
	{
		global $aWishlist, $customer_id;

		// Obtenemos el ID de usuario
		$this->nIdCustomer = $customer_id;

		// Obtenemos un array con todos los productos que estan en el wishlist
		$this->aProductsWishlist = $this->getArrayProductsWishlist();

		// Añadiamos los wishlist a tu cuenta
		foreach( $aWishlist as $value )
		{
			$_POST = array();
			$_POST['products_id'] = $value['products_id'];
			$_POST['id'] = null;
			
			// Descomponemos las opciones
			if( $value['atributo'] != '' )
			{
				$_POST['id'] = array();
				$aOpciones = explode( ',', $value['atributo'] );

				foreach( $aOpciones as $opcion )
				{
					// Descomponemos los atributos
					$aAtributos = explode( '-', $opcion );

					// Cargamos en el array
					$_POST['id'][$aAtributos[0]] = $aAtributos[1];
				}
			}

			// Añadimos
			$this->add();
		}
	}
	

	/**
	 *  Añade un producto al wishlist
	 *  @return bool
	 */
	public function add()
	{
		// Variables
		global $aWishlist, $_POST;
		$aPostAtributos = tep_db_prepare_input( $_POST['id'] );
		$nProductsId = tep_db_prepare_input( $_POST['products_id'] );
		$bEncontrado = false;
		$bInsertado = false;
		$sAtributo = '';
	
		// Si contiene atributos
		if( is_array( $aPostAtributos ) )
		{
			foreach( $aPostAtributos as $key => $value )
				$sAtributo .= $key . '-' . $value . ',';

			$sAtributo = substr( $sAtributo, 0, -1 );
		}
		
		// Comprobamos si ya existe
		foreach( $this->aProductsWishlist as $value )
		{
			// Si lo hemos encontrado
			if( $value['products_id'] == $nProductsId && $value['atributo'] == $sAtributo )
			{
				$bEncontrado = true;
				break;
			}
		}

		// Si estamos registrados y no esta vacio y el products_id existe y no tenemos insertado aun
		if( $this->nIdCustomer && $nProductsId != '' && checkId( 'products', 'products_id', $nProductsId ) && !$bEncontrado )
		{
			// Datos sql
			$aDatosSql = array( 'customers_id' => $this->nIdCustomer, 'products_id' => $nProductsId, 'atributo' => $sAtributo );

			// Insertamos
			tep_db_perform( 'wishlist', $aDatosSql );
			
			$bInsertado = true;
		}
		else
		{			
			// Si no lo hemos encontrado lo insertamos
			if( !$bEncontrado )
			{
				$aWishlist[] = array( 'products_id' => $nProductsId, 'atributo' => $sAtributo );
				$bInsertado = true;
			}
		}
		
		echo $this->getCantidadWishlist();
		//return $bInsertado;
	}
	
	
	/**
	 *  Elimina un producto al wishlist
	 *  @return bool
	 */
	public function remove()
	{
		// Variables
		global $aWishlist;
		$aPostAtributos = tep_db_prepare_input( $_POST['id'] );
		$nProductsId = tep_db_prepare_input( $_POST['products_id'] );
		$sAtributo = '';
		$bEliminado = false;
		$bEncontrado = false;
		$nPosicion = 0;

		// Si contiene atributos
		if( is_array( $aPostAtributos ) )
		{
			foreach( $aPostAtributos as $key => $value )
				$sAtributo .= $key . '-' . $value . ',';

			$sAtributo = substr( $sAtributo, 0, -1 );
		}
		
		// Comprobamos si ya existe
		foreach( $this->aProductsWishlist as $key => $value )
		{
			// Si lo hemos encontrado
			if( $value['products_id'] == $nProductsId && $value['atributo'] == $sAtributo )
			{
				$bEncontrado = true;
				$nPosicion = $key;
				break;
			}
		}
		
		// Si estamos registrados, y existe, eliminamos
		if( $this->nIdCustomer && $bEncontrado )
		{
			// Eliminamos
			tep_db_query( 'delete from wishlist where products_id = ' . $nProductsId . ' and atributo = "' . $sAtributo . '"' );

			$bEliminado = true;
		}
		else
		{
			unset( $aWishlist[$nPosicion] );
			$bEliminado = true;
		}

		echo $this->getCantidadWishlist();
	}
	
	
	/**
	 *  Devuelve el html del icono para añadir el producto al wishlist
	 *  @param int $nProductId
	 *  @param string $sProductName
	 *  @return string
	 */
	public function getHtmlIconAdd($nProductId, $sProductName, $bProductInfo = false, $nAtributo = 0, $sAtributo = '')
	{
		// Variables
		$bActivo = false;
	
		// Comprobamos si existe el producto en nuestro wishlist para añadir el icono a activo. Si el producto tiene atributos no podremos ponerlo como activo
		if (is_array($this->aProductsWishlist) || is_object($this->aProductsWishlist))
		{
			foreach( $this->aProductsWishlist as $value )
			{
				// Si lo hemos encontrado
				if( $value['products_id'] == $nProductId )
				{
					// Si no estamos en la ficha y no tiene tiene atributos, podemos activar
					if( !$bProductInfo && $value['atributo'] == "" )
					{
						$bActivo = true;
						break;
					}
					// Si estamos en la ficha y es un atributo
					elseif( $bProductInfo && $value['atributo'] != "" && $sAtributo == $value['atributo'] )
					{
						$bActivo = true;
						break;
					}
				}
			}
		}

		return '<a href="javascript: void(0);" ' . ($bProductInfo ? ' data-info="true"': '') . ' ' . ($nAtributo == 1 ? 'data-attr="1"' . (!$bProductInfo ? 'data-href="' . tep_href_link('product_info.php', 'products_id=' . $nProductId) . '"' : '') : '') . ' data-pid="' . $nProductId . '" class="fvrt tt tt-11' . ($bActivo ? ' actv' : '') . '"></a>';
	}

	/**
	 *  Elimina la sesion del wishlist
	 *  @return null
	 */
	public function deleteSession()
	{
		unset($aWishlist);
		tep_session_unregister('aWishlist');
	}
	
	/**
	 *  Idioma para el JS
	 *  @return null
	 */
	public function getLngScript()
	{
		$aLng = array(
			'FAVORITOS_ADD' => FAVORITOS_ADD,
			'WISHLIST_BOTON_ELIMINADO' => WISHLIST_BOTON_ELIMINADO,
			'WISHLIST_BOTON_AÑADIDO' => WISHLIST_BOTON_AÑADIDO,
			'FAVORITOS_LISTA' => FAVORITOS_LISTA,
			'FAVORITOS_ELIMINAR' => FAVORITOS_ELIMINAR,
			'FAVORITOS_CANTIDAD_COMPRAR_2' => FAVORITOS_CANTIDAD_COMPRAR_2,
			'FAVORITOS_CANTIDAD_COMPRAR' => FAVORITOS_CANTIDAD_COMPRAR
		);
		
		echo '<script type="text/javascript">var aLanguageWishlist = \'' . json_encode( $aLng ) . '\';</script>';
	}
}	
?>