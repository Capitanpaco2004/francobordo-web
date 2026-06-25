<?php
use util\event;

// Funcion que se encarga de cambiar la consulta para añadir los precios por grupo de cliente y oferta, retornando ademas la consulta ya paginada
function changePriceCustomer($sSql, $aArgumentos = array())
{
    // Variables
    global $nProductosTotal;
    $aProductos = array();
    $sNumero = tep_db_prepare_input($_GET['numero'] ?? '');
    $sCountKey = array_key_exists('COUNT_KEY', $aArgumentos) ? $aArgumentos['COUNT_KEY'] : '*';
    $bPaginar = array_key_exists('PAGINAR', $aArgumentos) ? $aArgumentos['PAGINAR'] : true;
    $bAjax = array_key_exists('AJAX', $aArgumentos) ? $aArgumentos['AJAX'] : false;
    $bProductsArray = array_key_exists('PRODUCTS_ARRAY', $aArgumentos) ? $aArgumentos['PRODUCTS_ARRAY'] : false;
    $bAddSpecials = array_key_exists('ADD_SPECIALS', $aArgumentos) ? $aArgumentos['ADD_SPECIALS'] : true;
    $nCustomerGroupId = getCustomerGroupId();
    $sIds = '';

	// Si somos un cliente distinto al cliente final cambiamos el SQL para el precio por grupo de cliente
	if ($nCustomerGroupId != 0) {
		// Si no está la tabla por grupo de cliente la añadimos
		if ($bAddSpecials && !preg_match('/join\s+products_groups/i', $sSql)) {
			$sSql = preg_replace('/\bWHERE\b/i',
								 'LEFT JOIN products_groups pg ON (pg.customers_group_id = "' . $nCustomerGroupId . '" AND pg.products_id = p.products_id) WHERE',
								 $sSql, 1);
		}

		// Si no está la tabla de ofertas la añadimos
		if ($bAddSpecials && !preg_match('/join\s+specials\s+s/i', $sSql)) {
			$sSql = preg_replace('/\bWHERE\b/i',
								 'LEFT JOIN specials s ON (s.products_id = p.products_id AND s.status = 1 AND s.customers_group_id = "' . $nCustomerGroupId . '") WHERE',
								 $sSql, 1);

			// Cambiamos los precios
			if (!preg_match('/(options_values_price)/i', $sSql)) {
				$sSql = preg_replace('/p\.products_price/i',
									 'IF(s.specials_new_products_price IS NOT NULL AND (s.venta_flash = 0 OR (s.venta_flash = 1 AND NOW() >= s.start_date)),
                        s.specials_new_products_price,
                        IF(pg.customers_group_price IS NOT NULL, pg.customers_group_price, p.products_price)
                    ) as products_price,
                     IF(s.specials_new_products_price IS NOT NULL AND (s.venta_flash = 0 OR (s.venta_flash = 1 AND NOW() >= s.start_date)),
                        IF(pg.customers_group_price IS NOT NULL, pg.customers_group_price, p.products_price),
                        NULL
                     ) as products_price_anterior',
									 $sSql, 1);
			}
		}
	} else {
		// Si no está la tabla de ofertas la añadimos
		if ($bAddSpecials && !preg_match('/join\s+specials\s+s/i', $sSql)) {
			$sSql = preg_replace('/\bWHERE\b/i',
								 'LEFT JOIN specials s ON (s.products_id = p.products_id
                                           AND s.status = 1
                                           AND s.customers_group_id = "' . $nCustomerGroupId . '"
                                           AND s.start_date = (SELECT MAX(s2.start_date)
                                                               FROM specials s2
                                                               WHERE s2.products_id = s.products_id
                                                                 AND s2.status = 1
                                                                 AND DATE(NOW()) >= DATE(s2.start_date)))
                 WHERE',
								 $sSql, 1);

			// Cambiamos los precios
			if (!preg_match('/(options_values_price)/i', $sSql)) {
				$sSql = preg_replace('/p\.products_price/i',
									 'IF(s.specials_new_products_price IS NOT NULL AND (s.venta_flash = 0 OR (s.venta_flash = 1 AND NOW() >= s.start_date)),
                        s.specials_new_products_price,
                        p.products_price
                    ) as products_price,
                     IF(s.specials_new_products_price IS NOT NULL AND (s.venta_flash = 0 OR (s.venta_flash = 1 AND NOW() >= s.start_date)),
                        p.products_price,
                        NULL
                     ) as products_price_anterior',
									 $sSql, 1);
			}
		}
	}

	// Añadimos el campo de oferta express SOLO si está el join specials
	if ($bAddSpecials && preg_match('/join\s+specials\s+s/i', $sSql)) {
		$sSql = preg_replace('/from/i', ', s.venta_flash, s.start_date, s.expires_date FROM ', $sSql, 1);
	}


	// Permite al caller pasar un QUERY_COUNT pre-calculado (ej. autocomplete de buscador,
	// que no necesita un conteo exacto y se ahorra re-ejecutar el FULLTEXT completo).
	$sQueryCount = !empty($aArgumentos['QUERY_COUNT'])
		? $aArgumentos['QUERY_COUNT']
		: 'select count(*) as total FROM (' . $sSql . ') as cnt';

    // Si contenemos datos por post el filtro se ha realizado por ese metodo
    if (count($_POST) > 0) {
        $sNumero = tep_db_prepare_input($_POST['numero']);
    }

    if ($sNumero == '*' || $bAjax) {
        $sNumero = OSCDENOX_CANTIDAD_PRODUCTOS_LISTADO_AJAX;
    }


    // Comprobamos si vamos a paginar el resultado
    if ($bPaginar) {
        // Comprobamos si no tenemos numero maximo para mostrar
        if (!$sNumero || $sNumero == '-1' || !is_numeric($sNumero)) {
            $sNumero = OSCDENOX_CANTIDAD_PRODUCTOS_LISTADO;
        }

        // Paginamos la consulta
        $aDatosSplit = new splitPageResults($sSql, $sNumero, $sCountKey, 'page', $sQueryCount);

        // Modificamos el SQL
        $sSql = $aDatosSplit->sql_query;
    }

    // Productos, devolvemos la consulta o un array de productos
    if ($bProductsArray) {
        // Consultamos
        $aDatos = tep_db_query($sSql);

        // Total
        $nTotal = tep_db_num_rows($aDatos);

        // Obtenemos los productos
        while ($aDato = tep_db_fetch_array($aDatos)) {
            $aProductos[] = $aDato;
        }
    } else {
        $aProductos = tep_db_query($sSql);
        $nTotal = tep_db_num_rows($aProductos);
    }

    // Total de productos
    $nProductosTotal = $nTotal;

    return array(
        'PRODUCTOS' => $aProductos,
        'PAGE_PRODUCTOS' => $aDatosSplit ?? null,
        'TOTAL' => $nTotal,
    );
}

// Funcion que comprueba si el envio sera gratuito o no segun la zona
function getProductFreeShippingByGeoZone()
{
    global $order, $customerCore;
    // Si estamos logueados
    if ($customerCore->hasLogin() && FREE_SHIPPING_PENINSULA == 1) {
        // Se declara para donde queremos los Envios Gratis (Esta solo para Peninsula y Portugal)
        if ($_SESSION['customer_country_id'] != STORE_COUNTRY && $_SESSION['customer_country_id'] != 171) {
            return false;
        }

        // Creamos clase auxliar para la provincia de envío del cliente
        if (!class_exists('order')) {
            require DIR_WS_CLASSES . 'order.php';
            $order = new order;
        }

        if (($order->delivery['state'] == 'Las Palmas') || ($order->delivery['state'] == 'Ceuta') || ($order->delivery['state'] == 'Melilla') || ($order->delivery['state'] == 'Santa Cruz de Tenerife')) {
            return false;
        }
    }

    return true;
}

// Devuelve el stock máximo entre las variantes ACTIVAS de un producto, o null
// si el producto no tiene variantes. "Activas" = combinaciones cuyos pares
// option_id-options_values_id existen en products_attributes (filtramos así
// las entries huérfanas en products_stock — p.ej. atributos viejos como `1-934`
// que dejaron de aplicar al producto).
// Resultado memoizado por products_id durante la request.
function maxActiveVariantStock($products_id)
{
	static $cache = [];
	$products_id = (int)$products_id;
	if (array_key_exists($products_id, $cache)) return $cache[$products_id];

	$aActive = [];
	$q = tep_db_query("SELECT CONCAT(options_id,'-',options_values_id) AS k FROM products_attributes WHERE products_id='" . $products_id . "'");
	while ($r = tep_db_fetch_array($q)) $aActive[$r['k']] = true;

	if (empty($aActive)) return $cache[$products_id] = null;

	$q = tep_db_query("SELECT products_stock_attributes, products_stock_quantity FROM products_stock WHERE products_id='" . $products_id . "'");
	$nMax = null;
	while ($r = tep_db_fetch_array($q)) {
		$sKey = $r['products_stock_attributes'];
		if ($sKey === '') continue;
		$bAllActive = true;
		foreach (explode(',', $sKey) as $sPart) {
			if (!isset($aActive[trim($sPart)])) { $bAllActive = false; break; }
		}
		if (!$bAllActive) continue;
		$n = (int)$r['products_stock_quantity'];
		if ($nMax === null || $n > $nMax) $nMax = $n;
	}
	return $cache[$products_id] = $nMax;
}

// Funcion que se encarga de recorrer una consulta de productos y añade campos o cosas que se necesiten
function eachProducts($aDatos = false, $aArgumentos = array())
{

    // Variables
    global $aProductos, $currency, $currencies, $languages_id, $nAuxIndexEachProducts, $nProductosTotal;
    $aReturn = array();
    $nCustomerGroupId = getCustomerGroupId();

	$bRating = array_key_exists( 'RANTING', $aArgumentos ) ? $aArgumentos['RANTING'] : false;
    // Contador de vueltas, si no esta definido lo creamos
    if (!isset($nAuxIndexEachProducts)) {
        $nAuxIndexEachProducts = 0;
    }

    // Si no nos envian ninguna consulta sera $aProductos por defecto
    if (!$aDatos) {
        $aDatos = $aProductos;
    }

    // Obtenemos el registro
    if (is_array($aDatos)) {
        $aReturn = $aDatos[$nAuxIndexEachProducts];

        if ($nAuxIndexEachProducts >= count($aDatos)) {
            $nAuxIndexEachProducts = 0;
            return false;
        }
    } else {
        $aReturn = tep_db_fetch_array($aDatos);
    }

    // Si no existen datos detenemos
    if (!$aReturn) {
        $nAuxIndexEachProducts = 0;
        return false;
    }

    // Valores
    $aReturn = array_merge($aReturn, array(
        'CLASS_STOCK' => '',
        'CLASS_ENVIO' => '',
        'CLASS_OFERTA' => '',
        'OFERTA_FECHA' => '',
        'PRECIO' => '',
        'PRECIO_ANTERIOR' => '',
        'TITLE' => tep_output_string($aReturn['products_name'], array('"' => '&quot;', '\'' => '&#039;', '<' => '&lt;', '>' => '&gt;', '&' => '&amp;')),
        'HREF' => tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $aReturn['products_id']),
        'INDEX' => $nAuxIndexEachProducts,
    ));
	// Si necesitamos informacion del rating
	if( $bRating )
	{
		// Variables
		$nPuntosTotal = 0;
		$nCantidadComentarios = 0;

		// Sumamos todos los puntos de los comentarios
		$aData = tep_db_query( 'SELECT SUM(r.reviews_rating) AS TOTAL
									FROM ' . TABLE_REVIEWS . ' r
									INNER JOIN ' . TABLE_PRODUCTS . ' p ON (r.products_id = p.products_id)
									INNER JOIN ' . TABLE_REVIEWS_DESCRIPTION . ' rd ON (r.reviews_id = rd.reviews_id)
									WHERE r.approved = 1 AND rd.languages_id = ' . $languages_id . ' AND p.products_id = ' . $aReturn['products_id'] );
			// NOTA 2026-06-24: quitado el filtro p.products_status = 1 de esta SUMA para que el rating se calcule sobre las MISMAS resenas que el COUNT de abajo (que no filtra status). Antes, productos con resenas pero status<>1 (importados=2/legacy=0) daban rating 0 -> JSON-LD ratingValue:0 -> error "valoracion fuera de intervalo" en Search Console y perdida de estrellas.

		if( tep_db_num_rows( $aData ) > 0 )
		{
			$aData = tep_db_fetch_array( $aData );
			$nPuntosTotal = $aData['TOTAL'];
		}


		// Obtenemos el numero de comentarios que tiene el producto
		$aData = tep_db_query( 'SELECT count(*) AS COUNT
									FROM ' . TABLE_REVIEWS . ' r
									INNER JOIN ' . TABLE_REVIEWS_DESCRIPTION . ' rd ON (r.reviews_id = rd.reviews_id)
									WHERE r.products_id = ' . $aReturn['products_id'] . ' AND approved = 1 AND rd.languages_id = ' . $languages_id );

		if( tep_db_num_rows( $aData ) > 0 )
		{
			$aData = tep_db_fetch_array( $aData );
			$nCantidadComentarios = $aData['COUNT'];
		}

		// Obtenemos el numero maximo que podemos obtener
		$nMaxRating = 5 * $nCantidadComentarios;

		// Obtenemos la puntuacion (petición redondeo bajo)
		$nPuntuacion = ($nMaxRating ? ($nPuntosTotal * 5) / $nMaxRating : 0);
		$nPuntuacionInt = (int)$nPuntuacion;
		if( $nPuntuacion - $nPuntuacionInt >= 0.5 ) {
			$nPuntuacionDec = 0.5;
		}
		else
			$nPuntuacionDec = 0;
		$nPuntuacion = $nPuntuacionInt + $nPuntuacionDec;


		// Añadimos la informacion
		$aReturn = array_merge( $aReturn, array(
			'NUMERO_COMENTARIO' => $nCantidadComentarios,
			'RATING' => (int)$nPuntuacion,
			'PUNTUACION' => $nPuntuacion
		));
	}

    // Damos valor si tiene envio gratuito
    if ($nCustomerGroupId == 0) {
        if ((array_key_exists('products_ship_free', $aReturn) && $aReturn['products_ship_free'] && getProductFreeShippingByGeoZone()) || (MODULE_SHIPPING_FREEAMOUNT_DISPLAY=='True' && $aReturn['products_price'] >= (MODULE_SHIPPING_FREEAMOUNT_AMOUNT / 1.21) && $aReturn['products_weight'] < MODULE_SHIPPING_FREEAMOUNT_WEIGHT_MAX && getProductFreeShippingByGeoZone())) {
            $aReturn['CLASS_ENVIO'] = 'prdct-envo';
        }
    } else {
        if (MODULE_SHIPPING_FREEAMOUNT_DISPLAY =='True' && $aReturn['products_price'] >= (MODULE_SHIPPING_FREEAMOUNT_AMOUNT_DISTRI) &&  $aReturn['products_weight'] < MODULE_SHIPPING_FREEAMOUNT_WEIGHT_MAX && getProductFreeShippingByGeoZone()) {
            $aReturn['CLASS_ENVIO'] = 'prdct-envo';
        }
    }

    // Damos valor si esta en oferta
    if (array_key_exists('products_price_anterior', $aReturn) && $aReturn['products_price_anterior']) {
        $aReturn['OFERTA_PORCENTAJE'] = ($aReturn['products_price_anterior'] != '' && $aReturn['products_price_anterior'] != 0 ? round(100 - (($aReturn['products_price'] * 100) / $aReturn['products_price_anterior'])) : '') . '%';

        $aReturn['CLASS_OFERTA'] = 'prdt-ofrt';

        // Obtenemos el precio en oferta y la fecha
        $product_query = tep_db_query("select specials_new_products_price, expires_date from " . TABLE_SPECIALS . " where products_id = '" . (int) $aReturn['products_id'] . "' and status");
        $product = tep_db_fetch_array($product_query);
        $nPrecio = $product['specials_new_products_price'];
        $dateExpire = $product['expires_date'];
        $aReturn['OFERTA_FECHA'] = $dateExpire;
    }

    // Formateamos los precios
    if ($aReturn['CLASS_OFERTA'] != '') { // Si tiene oferta
        $aReturn['PRECIO'] = $currencies->display_price($aReturn['products_price'], tep_get_tax_rate($aReturn['products_tax_class_id']));
        $aReturn['PRECIO_ANTERIOR'] = $currencies->display_price($aReturn['products_price_anterior'], tep_get_tax_rate($aReturn['products_tax_class_id']));
    } else {
        $aReturn['PRECIO'] = $currencies->display_price($aReturn['products_price'], tep_get_tax_rate($aReturn['products_tax_class_id']));
    }

    // Explotamos los precios
    if(isset($currencies->currencies[$currency]['decimal_point']))
    {
    	$aReturn['ARRAY_PRECIO'] = explode($currencies->currencies[$currency]['decimal_point'], $aReturn['PRECIO']);
		$aReturn['ARRAY_PRECIO_ANTERIOR'] = explode($currencies->currencies[$currency]['decimal_point'], $aReturn['PRECIO_ANTERIOR']);
    }

    //Precio Richsnippet
    $aReturn['PRECIO_RICHSNIPPET'] = number_format(tep_add_tax($aReturn['products_price'], tep_get_tax_rate($aReturn['products_tax_class_id'])), 2);

    // Aumentamos indice
    if ((is_array($aDatos) && count($aReturn) > 1) || tep_db_num_rows($aDatos) > 1) {
        $nAuxIndexEachProducts++;
    }

    // Juntamos todas las clases
    $aReturn['CLASS'] = $aReturn['CLASS_OFERTA'] . ' ' . $aReturn['CLASS_ENVIO'] . claseBotonComprar($aReturn['products_quantity'], $aReturn['check_stock'] ?? '');

    // Si el producto sale como prdt-agtd pero tiene variantes con stock real,
    // recomputamos sin el agotado. claseBotonComprar usa products_quantity de
    // la tabla products, que es 0 para productos con variantes — el stock real
    // vive en products_stock por combinación.
    if (strpos($aReturn['CLASS'], 'prdt-agtd') !== false) {
        $nVarStock = maxActiveVariantStock($aReturn['products_id']);
        if ($nVarStock !== null) {
            $sNewClass = trim(claseBotonComprar($nVarStock, $aReturn['check_stock'] ?? ''));
            if ($sNewClass !== 'prdt-agtd') {
                $aReturn['CLASS'] = preg_replace('/\s*\bprdt-agtd\b\s*/', ' ', $aReturn['CLASS']);
                if ($sNewClass !== '' && strpos($aReturn['CLASS'], $sNewClass) === false) {
                    $aReturn['CLASS'] .= ' ' . $sNewClass;
                }
                $aReturn['CLASS'] = preg_replace('/\s+/', ' ', trim($aReturn['CLASS']));
            }
        }
    }

	// Retornamos
    return $aReturn;
}

function claseBotonComprar($nQuantity, $bCheckStock)
{
    // Obtenemos la cantidad
    $nQuantity = (int) $nQuantity;

    /**
    * Si está activado en la configuración, devolvemos producto agotado.
    * #JDI-925-64407
     */
    if (DISABLE_SHIPPING_5_DAYS == 'true' && $nQuantity <= 0) {
        return ' prdt-agtd ';
    }

    // Si tenemos activo el check stock y la cantidad es menor de cero, el producto está agotado
    if ($bCheckStock && $nQuantity <= 0) {
        return ' prdt-agtd ';
    }

    // Clases 5 dias y bajo pedido
    switch (true) {
        case $nQuantity <= -100 and $nQuantity >= -150: // 5 dias
            return ' prdt-4dias ';
            break;

        case $nQuantity <= 0 and $nQuantity >= -799: // 5 dias
            return ' prdt-5dias ';
            break;

        case $nQuantity <= -800 and $nQuantity >= -899: // bajo pedido
            return ' prdt-bjpdd ';
            break;

        case ($nQuantity <= -900 and $nQuantity >= -901): // agotado
            return ' prdt-agtd ';
            break;
    }

    return '';
}

/*****************************\
|* INICIO FUNCIONES CLIENTES *|
\*****************************/

/**
 * Funcion que devuelve el ID del cliente con el nos hemos identificado, por defecto 0 cliente normal
 **/
function getCustomerGroupId()
{
    if (isset($_SESSION['sppc_customer_group_id']) && $_SESSION['sppc_customer_group_id'] != '0') {
        return $_SESSION['sppc_customer_group_id'];
    } else {
        return '0';
    }
}

/**************************\
|* FIN FUNCIONES CLIENTES *|
\**************************/

/*******************************\
|* INICIO FUNCIONES CATEGORIAS *|
\*******************************/
function getAllCategoryArray($bHref = false)
{
    // Variables
    global $languages_id;
    $aReturn = array();
    $aCategories = array();

    $aDatos = tep_db_query('select c.categories_id, cd.categories_name, c.parent_id, c.categories_image
								 from categories c
								 inner join categories_description cd on(c.categories_id = cd.categories_id)
								 where cd.language_id = "' . (int) $languages_id . '" and c.categories_status = 1
								 order by sort_order, cd.categories_name');

    while ($aDato = tep_db_fetch_array($aDatos)) {
        if ($bHref) {
            $aDato['href'] = tep_href_link(FILENAME_CATEGORIES, tep_get_path($aDato['categories_id']));
        }

        $aCategories[$aDato['categories_id']] = $aDato;
        $aReturn[$aDato['parent_id']][] = $aDato;
    }

    return array('parnt' => $aReturn, 'cat' => $aCategories);
}

// Obtenemos recursivamente las id de las categorias
function getRecursiveIdCategories($aCategoria, $nIdSearch)
{
    $sReturn = '';

   if (array_key_exists($nIdSearch ?? '', $aCategoria)) {
        foreach ($aCategoria[$nIdSearch] as $aAux) {
            $sIds = getRecursiveIdCategories($aCategoria, $aAux['categories_id']);
            $sReturn .= $aAux['categories_id'] . ',' . ($sIds != '' ? $sIds : '');
        }
    }

    return $sReturn;
}

// Obtenemos recursivamente las categorias padres
function getRecursiveParentsCategories($aCategoria, $nIdSearch)
{
    $aReturn = array();

    if (array_key_exists($nIdSearch, $aCategoria)) {
        if ($aCategoria[$nIdSearch]['parent_id'] >= 0) {
            $aReturn = getRecursiveParentsCategories($aCategoria, $aCategoria[$nIdSearch]['parent_id']);
            $aReturn[$aCategoria[$nIdSearch]['categories_id']] = array( 'id' => $aCategoria[$nIdSearch]['categories_id'], 'image' => $aCategoria[$nIdSearch]['categories_image'], 'name' => $aCategoria[$nIdSearch]['categories_name'] );
        }
    }

    return $aReturn;
}
/**
 * Obtenemos todas las categorias padres a partir de una categoría
 * Argumentos:
 *     - @param int $nCategory, ID de la categoria
 *
 **/
function getCategoriesParent($nCategory)
{
    // Función recursiva
    if (!function_exists('getCategoriesParentRecursive')) {
        function getCategoriesParentRecursive($nCategory, &$aReturn)
        {
            global $languages_id;

            // Obtenemos la información de la categoria
            $aCategory = tep_db_query('SELECT * FROM categories c INNER JOIN categories_description cd ON (c.categories_id = cd.categories_id) WHERE cd.language_id = ' . $languages_id . ' and c.categories_id = ' . $nCategory . ';');

            // Si tenemos registro
            if (tep_db_num_rows($aCategory) > 0) {
                // Registro
                $aCategory = tep_db_fetch_array($aCategory);

                // Metemos en el array de retorno la categoria
                $aReturn[] = $aCategory;

					// Llamamos recursivamente (parent_id > 0 significa que tiene padre)
					if( !empty($aCategory['parent_id']) )
						getCategoriesParentRecursive( $aCategory['parent_id'], $aReturn );
            }
        }
    }

    // Variables
    $aReturn = array();

    // Llamamos a la funcion recursiva
    getCategoriesParentRecursive($nCategory, $aReturn);

    // Retornamos
    return $aReturn;
}

/**
 * Busca la primera categoria padre desde la categoria pasada por argumento
 * Argumentos:
 *     - @param int $nId, Categoria desde la cual busca hacia arriba la categoria padre
 **/
function getParentFirstCategoria($nId)
{
    // Variables
    global $languages_id;
    $aCategorias = null;

    if (!isset($nId)) {
        return;
    }

    $aCategorias = tep_db_query('SELECT ctgr.categories_id, ctgr.categories_image, desp.categories_name, ctgr.categories_image, ctgr.parent_id
									  FROM ' . TABLE_CATEGORIES . ' ctgr
									  INNER JOIN ' . TABLE_CATEGORIES_DESCRIPTION . ' desp ON(ctgr.categories_id = desp.categories_id)
									  WHERE desp.language_id = ' . (int) $languages_id . ' AND ctgr.categories_id = ' . $nId);

    $aCategorias = (tep_db_num_rows($aCategorias) ? tep_db_fetch_array($aCategorias) : false);

    // Si la categoria tiene un parent_id desigual a NULL volvemos a buscar el padre
    if ($aCategorias['parent_id'] != 0) {
        $aCategorias = getParentFirstCategoria($aCategorias['parent_id']);
    }

    return $aCategorias;
}

/**
 * Devuelve todas las id hijas recursivamente desde una categoria padre
 * Argumentos:
 *     - @param int $nIdParentPrincipal, Categoria desde donde quieres obtener las demas categorias recursivamente
 **/
function getIdCategoriasHijasRecursivoByIdCategoriaPadre($nIdParentPrincipal)
{
    // Funcion recursiva
    if (!function_exists('_getIdCategoriasHijasRecursivoByIdCategoriaPadre')) {
        function _getIdCategoriasHijasRecursivoByIdCategoriaPadre($aCategorias, $nIdParent)
        {
            $sIds = '';

            foreach ($aCategorias as $key => $value) {
                if ($value == $nIdParent) {
                    $sIds .= $key . ', ';
                    $sIds .= _getIdCategoriasHijasRecursivoByIdCategoriaPadre($aCategorias, $key);
                }
            }

            return $sIds;
        }
    }

    // Obtenemos todas las categorias para no realizar muchas consultas
    $aDatos = tep_db_query('select categories_id, parent_id from categories where categories_status = 1 order by parent_id desc');
    $aCategorias = array();

    while ($aDato = tep_db_fetch_array($aDatos)) {
        $aCategorias[$aDato['categories_id']] = $aDato['parent_id'];
    }

    return substr(_getIdCategoriasHijasRecursivoByIdCategoriaPadre($aCategorias, $nIdParentPrincipal), 0, -2);
}

/**
 * Obtenemos la categoria pasada como argumento
 * Argumentos:
 *     - @param int $nId, Id categoria que deseamos
 */
function getCategoriaByIdCategoria($nId)
{
    // Variables
    global $languages_id;
    $aCategorias = null;

    $aCategorias = tep_db_query('SELECT ctgr.categories_id, ctgr.categories_image, desp.categories_name, ctgr.categories_image, ctgr.parent_id
									  FROM ' . TABLE_CATEGORIES . ' ctgr
									  INNER JOIN ' . TABLE_CATEGORIES_DESCRIPTION . ' desp ON(ctgr.categories_id = desp.categories_id)
									  WHERE desp.language_id = ' . (int) $languages_id . ' AND ctgr.categories_id = ' . $nId);

    return (tep_db_num_rows($aCategorias) ? tep_db_fetch_array($aCategorias) : false);
}

/**
 * Pasas como parametro un id de un producto y obtenemos el ID y el nombre de su categoria
 *
 * @global int $languages_id
 * @param int $nId
 * @return array $aSalida
 */
function getCategoriaProducto($nId)
{
    // Variables
    global $languages_id;
    $nId = tep_get_product_path($nId);

    $aCategorias = tep_db_query('SELECT c.categories_id, cd.categories_name
									  FROM ' . TABLE_CATEGORIES . ' c, ' . TABLE_CATEGORIES_DESCRIPTION . ' cd
									  WHERE c.categories_id = ' . (int) $nId . ' and c.categories_id = cd.categories_id and cd.language_id = ' . (int) $languages_id);

    while ($aCategoria = tep_db_fetch_array($aCategorias)) {
        $aSalida['nombre'] = $aCategoria['categories_name'];
        $aSalida['id'] = $aCategoria['categories_id'];
    }

    return $aSalida;
}

/****************************\
|* FIN FUNCIONES CATEGORIAS *|
\****************************/

/****************************\
|* INICIO FUNCIONES PRECIOS *|
\****************************/

function getMaxPriceProduct($bRedondear)
{
    // Obtenemos el maximo precio que existe en la web con IVA
    if (DISPLAY_PRICE_WITH_TAX == 'true') {
        if (!tep_session_is_registered('customer_country_id')) {
            $customer_country_id = STORE_COUNTRY;
            $customer_zone_id = STORE_ZONE;
        }

        $sSql = 'select max( round(((IF(s.status, s.specials_new_products_price, p.products_price) * tr.tax_rate) / 100) + IF(s.status, s.specials_new_products_price, p.products_price), 2) ) as maximo from products p left outer join specials s on (p.products_id = s.products_id and s.status = 1) left outer join ' . TABLE_TAX_RATES . ' tr on (p.products_tax_class_id = tr.tax_class_id) left outer join ' . TABLE_ZONES_TO_GEO_ZONES . ' gz on (tr.tax_zone_id = gz.geo_zone_id and (gz.zone_country_id is null or gz.zone_country_id = "0" or gz.zone_country_id = "' . (int) $customer_country_id . '") and (gz.zone_id is null or gz.zone_id = "0" or gz.zone_id = "' . (int) $customer_zone_id . '")) where p.products_status = 1;';
    }
    // Obtenemos el maximo precio que existe en la web sin IVA
    else {
        $sSql = 'select max( round( IF(s.status, s.specials_new_products_price, p.products_price), 2) ) as maximo from products p left outer join specials s on (p.products_id = s.products_id and s.status = 1) where p.products_status = 1;';
    }

    // Lanzamos la consulta para obtener el máximo
    $aMax = tep_db_query($sSql);

    // Si no hay productos retornamos 0
    if (tep_db_num_rows($aMax) == 0) {
        return 0;
    }

    // Obtenemos el máximo
    $aMax = tep_db_fetch_array($aMax);

    // Comprobamos si deseamos redondear
    if ($bRedondear) {
        return ceil($aMax['maximo']);
    }

    // Retornamos el máximo
    return $aMax['maximo'];
}

/*************************\
|* FIN FUNCIONES PRECIOS *|
\*************************/

/******************************\
|* INICIO FUNCIONES PRODUCTOS *|
\******************************/

/**
 * Obtenemos si el producto pasado como argumento contiene envio gratuito, devuelve 0 o 1
 * Argumentos:
 *     - @param int $nId, Id del producto a comprobar
 */
function getProductFreeShipping($nId)
{
    $aProductos = tep_db_query('SELECT products_ship_free FROM ' . TABLE_PRODUCTS . ' WHERE products_id = ' . (int) $nId);
    $aProducto = tep_db_fetch_array($aProductos);

    // Si el producto tiene envío gratuito, comprobamos si el envío también es gratuito según su zona
    if ($aProducto['products_ship_free']) {
        $aProducto['products_ship_free'] = getProductFreeShippingByGeoZone();
    }

    return $aProducto['products_ship_free'];
}

/**
 * Devuelve la tabla de rappels de un producto
 */
function getTableRappels($nId, $sPriceProduct)
{
    // Variables
    global $currencies, $customer_group_id;
    $aDatos = null;
    $sHtml = '';
    $sPriceProduct = floatval(str_replace(array('&euro;', ','), array('', '.'), $sPriceProduct));

    // Realizamos la consulta para obtener los rappels
    $aDatos = tep_db_query('select pb.products_price, pb.products_qty, p.products_tax_class_id
								 from products_price_break pb
								 inner join products p ON (pb.products_id = p.products_id)
								 where pb.customers_group_id = "' . (int) $customer_group_id . '" and p.products_id = "' . (int) $nId . '"
								 order by pb.products_qty asc');

    // Si hemos obtenido rappels
    if (tep_db_num_rows($aDatos) > 0) {
        while ($aDato = tep_db_fetch_array($aDatos)) {
            $aAux[] = array('CANTIDAD' => $aDato['products_qty'], 'PRECIO' => $currencies->display_price($aDato['products_price'], tep_get_tax_rate($aDato['products_tax_class_id'])));
        }

        // Cantidad de rapels
        $nCantidad = count($aAux);

		$sHtml .= '<div class="fich-bg">';
			$sHtml .= '<div class="web-cntd fich-wrpr-rows">';
				$sHtml .= '<div class="wrpr-titu">' . TEXT_DESCUENTO_CANTIDADES . '</div>';
				$sHtml .= '<table class="tble1">';
					$sHtml .= '<thead>';
						$sHtml .= '<tr>';
							$sHtml .= '<th>' . TEXT_CANTIDAD . '</th>';
							$sHtml .= '<th>' . TEXT_PRECIO_UNIDAD . '</th>';
							$sHtml .= '<th class="ustd-ahrr">' . TEXT_IT_SAVE . '</th>';
						$sHtml .= '</tr>';
					$sHtml .= '</thead>';
					$sHtml .= '<tbody>';

					// Recorremos los descuentos
					foreach ($aAux as $key => $aDato) {
						$sCantidad = ($key == $nCantidad - 1 ? '+ ' : '') . (string) ($aDato['CANTIDAD']) . ($key == $nCantidad - 1 ? '' : ' - ' . (string) ($aAux[$key + 1]['CANTIDAD'] - 1));
						$sPrecioAux = floatval(str_replace(array('&euro;', ','), array('', '.'), $aDato['PRECIO']));

						$sHtml .= '<tr>';
						$sHtml .= '<td>' . $sCantidad . '</td>';
						$sHtml .= '<td>' . $aDato['PRECIO'] . '</td>';
						$sHtml .= '<td class="ustd-ahrr">' . (100 - (int) (($sPrecioAux * 100) / $sPriceProduct)) . '%</td>';
						$sHtml .= '</tr>';
					}
					$sHtml .= '</tbody>';
				$sHtml .= '</table>';
			$sHtml .= '</div>';
		$sHtml .= '</div>';
    }

    return $sHtml;
}

/***************************\
|* FIN FUNCIONES PRODUCTOS *|
\***************************/

/***************************\
|* INICIO FUNCIONES VARIAS *|
\***************************/

/**
 * Obtenemos si la peticion es AJAX
 */
function isAjax()
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Obtenemos los productos de la consulta SQL filtrados
 * Argumentos:
 *       - @param string $sSql, SQL con los productos
 */
function changeFilter(&$sSql, $aArgumentos = array())
{
    // Variables
    global $aFiltro, $aFiltroOrdenar;
	$sFiltro = isset( $_GET['filtro'] ) ? tep_db_prepare_input( $_GET['filtro'] ) : '';
	$sOrden = isset( $_GET['orden'] ) ? tep_db_prepare_input( $_GET['orden'] ) : '';

    // Si contenemos datos por post el filtro se ha realizado por ese metodo
    if (count($_POST) > 0) {
        $sFiltro = tep_db_prepare_input($_POST['filtro']);
        $sOrden = tep_db_prepare_input($_POST['orden']);
    }

    // Comprobamos si existe el filtro
    if (isset($sFiltro) && $sFiltro != '-1' && is_array($aFiltro) && key_exists($sFiltro, $aFiltro)) {
        // Modificamos el where
        $sSql = str_replace('where ', 'where ' . $aFiltro[$sFiltro]['ACTION'] . ' and ', $sSql);
    }

    // Comprobamos si existe el ordenar
    if (is_array($aFiltroOrdenar) && isset($sOrden) && $sOrden != '-1' && key_exists($sOrden, $aFiltroOrdenar)) {
        // Modificamos el order by
        $sSql = preg_replace('/order by (.+)$/i', 'order by ' . $aFiltroOrdenar[$sOrden]['ACTION'], $sSql);
    }
}

 /**
     * Obtenemos los productos de la consulta SQL filtrados
     * Argumentos:
     *       - @param string $sSql, SQL con los productos
     */
    function changeFilterCategorie(&$listing_sql, $aArgumentos = array())
{
        // Variables
        global $current_category_id, $sSqlRecordar, $languages_id;

        $aManufacturers = tep_db_prepare_input($_GET['manufacturer']);

        if (array_key_exists('FILTRO_MANUFACTURER', $aArgumentos)) {
            if (is_array($aManufacturers) && !in_array($aArgumentos['FILTRO_MANUFACTURER'][0], $aManufacturers)) {
                $aManufacturers = array_merge($aManufacturers, $aArgumentos['FILTRO_MANUFACTURER']);
            } else {
                $aManufacturers = $aArgumentos['FILTRO_MANUFACTURER'];
            }
        }

        $sManufacturer = (is_array($aManufacturers) && count($aManufacturers) > 0 ? implode(', ', $aManufacturers) : ($aManufacturers != -1 ? $aManufacturers : ''));

        $aTipos = tep_db_prepare_input($_GET['tipo']);
        $aFiltroPrecios = tep_db_prepare_input($_GET['precio']);
        $aFiltros = tep_db_prepare_input($_GET['filter']);

        if (array_key_exists('FILTRO_EXTRA', $aArgumentos)) {
            if (is_array($aFiltros) && !in_array($aArgumentos['FILTRO_EXTRA'][0], $aFiltros)) {
                $aFiltros = array_merge($aFiltros, $aArgumentos['FILTRO_EXTRA']);
            } else {
                $aFiltros = $aArgumentos['FILTRO_EXTRA'];
            }
        }

        if ($sManufacturer == '') {
            $sManufacturer = tep_db_prepare_input($_GET['manufacturers_id']);
        }

        $manufacturers_id = tep_db_prepare_input($_GET['manufacturers_id']);
        $bRecordarSql = array_key_exists('RECORDAR_SQL', $aArgumentos) ? $aArgumentos['RECORDAR_SQL'] : false;
        $sPrecioMin = tep_db_prepare_input($_GET['precio_min']);
        $sPrecioMax = tep_db_prepare_input($_GET['precio_max']);

        // Filtro numérico //
        // Variables proceso filtro numérico
        $nCont = 0;
        $sBetween = '';

        // Recorremos los datos por GET
        foreach ($_GET as $sKey => $sValue) {
            // Guardamos los get que corresponda con min y max
            preg_match('/(min|max)(?<id>\w+)/', $sKey, $aMatch);

            // Si existen
            if (key_exists('id', $aMatch)) {
                // Guardamos en una variable el min y max
                $aBetween[$aMatch[1]][$nCont] = $_GET[$aMatch[0]];

                // Si tenemos min y max
                if ($nCont != 0 && $nCont == 1) {
                    // Guardamos consulta
                    $sBetween .= '(SELECT f.title FROM filters_to_products ftp INNER JOIN filters f on(f.filters_id = ftp.filters_id) WHERE f.language_id = ' . (int) $languages_id . ' AND ftp.products_id = p.products_id and f.parent_id = ' . $aMatch['id'] . ' and f.title BETWEEN ' . (isset($aBetween['min'][0]) ? $aBetween['min'][0] : $aBetween['min'][1]) . ' AND ' . (isset($aBetween['max'][0]) ? $aBetween['max'][0] : $aBetween['max'][1]) . ' limit 1) OR ';
                    $nCont = 0;

                    $aBetween = array();
                    continue;
                }

                ++$nCont;
            }
        }

        // Si tenemos consulta numerica
        if ($sBetween != '') {
            $sBetween = substr($sBetween, 0, -4);

            // Reemplazamos en consulta
            $listing_sql = preg_replace('/where/i', 'where (' . $sBetween . ') AND ', $listing_sql);
        }

        // /Filtro numérico //

        // Si filtramos por filtros de categoria
        if (!empty($aFiltros)) {
            $aFiltroFinal = array();

            // Recorremos los filtros recogidos
            foreach ($aFiltros as $aFiltro) {
                // Obtenemos el filtro y su padre
                $sFilterParent = tep_db_query('SELECT filters_id, parent_id FROM filters WHERE language_id = ' . (int) $languages_id . ' AND filters_id = ' . $aFiltro);

                // Guardamos el filtro indicando su padre como ID
                if (tep_db_num_rows($sFilterParent) > 0) {
                    $aFilterParent = tep_db_fetch_array($sFilterParent);
                    $aFiltroFinal[$aFilterParent['parent_id']][] = $aFilterParent['filters_id'];
                }
            }
        }

        // Filtros Categoria
        if (!empty($aFiltroFinal)) {
            // Recorrremos los filtros indexado por ID del filtro padre
            foreach ($aFiltroFinal as $nIdParent => $aFiltros) {
                // Comprobamos que exista el filtro
                $sFilters = tep_db_query('SElECT * FROM filters f ' . ($sManufacturer != '' || $manufacturers_id != '' || $current_category_id == 0 ? '' : 'INNER JOIN filters_to_categories ftc on(f.filters_id = ftc.filters_id) ') . 'WHERE f.language_id = ' . (int) $languages_id . ' AND f.filters_id in (' . implode(', ', $aFiltros) . ')' . ($sManufacturer != '' || $manufacturers_id != '' || $current_category_id == 0 ? '' : ' AND ftc.categories_id = ' . $current_category_id) . ' AND f.parent_id = ' . $nIdParent . ' ORDER BY ' . ($sManufacturer != '' || $manufacturers_id != '' || $current_category_id == 0 ? 'f.title' : 'ftc.sort_order'));

                // Si existe
                if (tep_db_num_rows($sFilters) > 0) {
                    // Iniciamos contador a 0
                    $nCont = 0;

                    // Recorremos los filtros que pertenen al ID padre en el que estamos
                    foreach ($aFiltros as $aFiltro) {
                        // Reemplazamos en consulta
                        $listing_sql = str_replace('where ' . ($nCont == 0 ? '' : '( ') . '', 'where ( ' . $aFiltro . ' IN (SELECT ftp.filters_id FROM filters_to_products ftp WHERE ftp.products_id = p.products_id) ' . ($nCont + 1 != count($aFiltro) ? (FILTERS_CONFIGURATION == 'OR' ? 'OR ' : 'AND ') : ') AND '), $listing_sql);

                        // Sumamos contador (sirve para el reemplazo de la consulta)
                        ++$nCont;
                    }
                }
            }
        }

        // Filtro fabricante
        if ($sManufacturer != '') {
            // Modificamos el where
            $listing_sql = preg_replace('/where /i', 'where p.manufacturers_id IN (' . $sManufacturer . ') and ', $listing_sql);
        }

        // Filtro disponibilidad
        if (!empty($aTipos)) {
            $sReplace = '';
            // Recorremos disponible
            foreach ($aTipos as $aTipo) {
                if ($aTipo == 1) {
                    $sReplace .= '(to_days(now()) - to_days(p.products_date_added) <= 30) or ';
                } elseif ($aTipo == 2) {
                $sReplace .= '(p.products_outlet = 1) or ';
            }
        }
        $sReplace = preg_replace('/(or )$/i', ') and ', $sReplace, 1);
        $listing_sql = str_replace('where ', 'where (' . $sReplace, $listing_sql);
    }

    // Comprobamos si existe el filtro de precio
    if ($sPrecioMax >= 0 && $sPrecioMin >= 0 && is_numeric($sPrecioMin) && is_numeric($sPrecioMax)) {
        // Modificamos el where
        $listing_sql = preg_replace('/order by/i', 'having products_price >= ' . (($sPrecioMin / 1.21) - 0.01) . ' and products_price <= ' . ceil($sPrecioMax / 1.21) . ' ORDER BY', $listing_sql);
    }

    // Guardamos los datos de la consulta, para obtener de ella el precio minimo y máximo
    if ($bRecordarSql) {
        $sSqlRecordar = $listing_sql;
    }
}

/**
 * Funcion que devuelve el numero aleatorio
 **/
function random()
{
    list($usec, $sec) = explode(' ', microtime());
    srand((float) $sec + ((float) $usec * 100000));
    return rand();
}

/**
 * Devuelve una cadena convertida a slug, convirtiendo los espacios al caracter deseado
 *
 * @param string $sTexto
 * @param string $sSeparator
 * @return string
 */
function getSlug($sTexto, $sSeparator = '-')
{
    // Convertimos los caracteres especiales
    $sTexto = htmlentities($sTexto);
    $sTexto = preg_replace('/&([a-zA-Z])(uml|acute|grave|circ|tilde);/', '$1', $sTexto);
    $sTexto = html_entity_decode($sTexto);

    // Pasamos a minusculas
    $sTexto = strtolower($sTexto);

    // Convertimos los caracteres no permitidos a espacios
    $sTexto = preg_replace('/\W/', ' ', $sTexto);

    // Reemplazamos los espacios por el separador
    $sTexto = preg_replace('/\ +/', $sSeparator, $sTexto);

    // Hacemos un trim para quitar espacios sobrantes
    $sTexto = trim($sTexto, $sSeparator);

    return $sTexto;
}

/*
 * Muestra el precio del producto en imagenes
 * @param array $aProductoInformacion
 */
function getPrecioImagen($aProductoInformacion)
{
    // Variables
    $sPrecio = str_replace('&euro;', '', $aProductoInformacion['PRECIO']);
    $sHtml = '';
    $aPrecios = explode(',', $sPrecio);
    $sClass = array('entr', 'dcml');
    $nTotal = 0;

    // Si no hemos obtenido bien el precio retornamos
    if (count($aPrecios) < 2) {
        return $aProductoInformacion['PRECIO'];
    }

    // Recorremos los enteros y los decimales
    foreach ($aPrecios as $nCont => $aPrecio) {
        // Obtenemos cuantos numeros tiene
        $nTotal = strlen($aPrecio) - 1;

        // Recorremos los numeros enteros y los numeros decimales
        for ($nPos = 0; $nPos <= $nTotal; $nPos++) {
            $sHtml .= '<span class="' . $sClass[$nCont] . $aPrecio[$nPos] . '">' . $aPrecio[$nPos] . '</span>';
        }

        // Si nos encontramos en los enteros ($nCont == 0) y tenemos decimales (count( $aPrecios ) > 0) añadimos la cama
        if ($nCont == 0 && count($aPrecios) > 0) {
            $sHtml .= '<span class="coma">,</span>';
        }
    }

    return $sHtml . '<span class="euro">€</span>';
}

/**
 * Acorta un string segun un tamaño considerando que no corte las palabras finales al añadir los puntos suspensivos
 * Argumentos:
 *       - @param string $text, Texto a cortar
 * Opciones:
 *     - SIZE: Cantidad de caracteres antes de cortar [100|int]
 *     - END: Cuando corte mostrar al final [...|string]
 *     - EXACT: No cortar palabras [true|false]
 *     - HTML: No cortar html [false|true]
 **/
function truncate($text, $aArgumentos = array())
{
    $length = (empty($aArgumentos['SIZE']) ? 100 : $aArgumentos['SIZE']);
    $ending = (empty($aArgumentos['END']) ? '...' : $aArgumentos['END']);
    $exact = (empty($aArgumentos['EXACT']) ? true : $aArgumentos['EXACT']);
    $considerHtml = (empty($aArgumentos['HTML']) ? false : $aArgumentos['HTML']);
    $bClear = (empty($aArgumentos['CLEAR']) ? false : $aArgumentos['CLEAR']);
	$text = preg_replace( '/\s\s+/', ' ', $text ?? '' );

    // UTF-8
    mb_internal_encoding("UTF-8");

    // Comprobamos si debemos limpiar la cadena
    if ($bClear) {
        $caracters_no_permitidos = array('"', "'");
        # paso los caracteres entities tipo &aacute; $gt;etc a sus respectivos html
        $s = html_entity_decode($text, ENT_COMPAT, 'UTF-8');
        # quito todas las etiquetas html y php
        $s = strip_tags($s);
        $s = str_replace(array(chr(13), chr(10)), array('', '', ''), $s);
        # elimino los caracters como comillas dobles y simples
        $text = str_replace($caracters_no_permitidos, "", $s);
    }

    if (is_array($ending)) {
        extract($ending);
    }
    if ($considerHtml) {
        if (mb_strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
            return $text;
        }
        $totalLength = mb_strlen($ending);
        $openTags = array();
        $truncate = '';
        preg_match_all('/(<\/?([\w+]+)[^>]*>)?([^<>]*)/', $text, $tags, PREG_SET_ORDER);
        foreach ($tags as $tag) {
            if (!preg_match('/img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param/s', $tag[2])) {
                if (preg_match('/<[\w]+[^>]*>/s', $tag[0])) {
                    array_unshift($openTags, $tag[2]);
                } elseif (preg_match('/<\/([\w]+)[^>]*>/s', $tag[0], $closeTag)) {
                    $pos = array_search($closeTag[1], $openTags);
                    if ($pos !== false) {
                        array_splice($openTags, $pos, 1);
                    }
                }
            }
            $truncate .= $tag[1];

            $contentLength = mb_strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $tag[3]));
            if ($contentLength + $totalLength > $length) {
                $left = $length - $totalLength;
                $entitiesLength = 0;
                if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', $tag[3], $entities, PREG_OFFSET_CAPTURE)) {
                    foreach ($entities[0] as $entity) {
                        if ($entity[1] + 1 - $entitiesLength <= $left) {
                            $left--;
                            $entitiesLength += mb_strlen($entity[0]);
                        } else {
                            break;
                        }
                    }
                }

                $truncate .= mb_substr($tag[3], 0, $left + $entitiesLength);
                break;
            } else {
                $truncate .= $tag[3];
                $totalLength += $contentLength;
            }
            if ($totalLength >= $length) {
                break;
            }
        }
    } else {
        $text = strip_tags($text);
        if (mb_strlen($text) <= $length) {
            return $text;
        } else {
            $truncate = mb_substr($text, 0, $length - strlen($ending));
        }
    }
    if (!$exact) {
        $spacepos = mb_strrpos($truncate, ' ');
        if (isset($spacepos)) {
            if ($considerHtml) {
                $bits = mb_substr($truncate, $spacepos);
                preg_match_all('/<\/([a-z]+)>/', $bits, $droppedTags, PREG_SET_ORDER);
                if (!empty($droppedTags)) {
                    foreach ($droppedTags as $closingTag) {
                        if (!in_array($closingTag[1], $openTags)) {
                            array_unshift($openTags, $closingTag[1]);
                        }
                    }
                }
            }
            $truncate = mb_substr($truncate, 0, $spacepos);
        }
    }

    $truncate .= $ending;

    if ($considerHtml) {
        foreach ($openTags as $tag) {
            $truncate .= '';
        }
    }

    return $truncate;
}

/**
 * Pinta los metatags necesarios en la cabecera
 */
function getHeader()
{
    global $lng, $request_type, $aPaginador;


	echo join('', event::getInstance()->execute('front_office_header_getheader_before'));

    echo '<meta http-equiv="Content-Type" content="text/html; charset=' . CHARSET . '" />' . "\n";
    echo '<meta name="language" content="es" />' . "\n";
    metatags();
    echo '<link rel="canonical" href="' . CanonicalUrl() . '" />' . "\n";
    echo '<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=eEYAkbbbek">' . "\n";
    echo '<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png?v=eEYAkbbbek">' . "\n";
    echo '<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png?v=eEYAkbbbek">' . "\n";
    echo '<link rel="manifest" href="/site.webmanifest?v=eEYAkbbbek">' . "\n";
    echo '<link rel="mask-icon" href="/safari-pinned-tab.svg?v=eEYAkbbbek" color="#5bbad5">' . "\n";
    echo '<link rel="shortcut icon" href="/favicon.ico?v=eEYAkbbbek">' . "\n";
    echo '<meta name="apple-mobile-web-app-title" content="Francobordo">' . "\n";
    echo '<meta name="application-name" content="Francobordo">' . "\n";
    echo '<meta name="msapplication-TileColor" content="#ffffff">' . "\n";
    echo '<meta name="theme-color" content="#ffffff">' . "\n";

    echo '<base href="' . (($request_type == 'SSL') ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_CATALOG . '" />' . "\n";


    include DIR_THEME . 'scripts/scripts.php';

    echo '<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />' . "\n";

    /**
     * @author Daniel Lucia <daniel.lucia@denox.es>
     * #ZTR-773-62408
     * Modificado a petición de Francisco. Posibilidad de hacer zoom en móvil.
     * Dejo comentado el anterior.
     */
    //echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=3.0" />' . "\n";

	if ($aPaginador)
	{
        if ($aPaginador->number_of_pages > 1)
		{
            $nPage = intval($_GET['page'] ?? 0) > 0 ? intval($_GET['page'] ?? 0) : 1;
            $sUrlNext = tep_href_link(basename($_SERVER['SCRIPT_FILENAME']), http_build_query(array_merge($_GET, array("page" => $nPage + 1))));
            $sUrlPrevious = tep_href_link(basename($_SERVER['SCRIPT_FILENAME']), http_build_query(array_merge($_GET, array("page" => $nPage - 1))));

			if ($nPage > 1 && $nPage <= $aPaginador->number_of_pages)
                echo '<link rel="prev" href="' . $sUrlPrevious . '" />' . "\n";

			if ($nPage < $aPaginador->number_of_pages)
                echo '<link rel="next" href="' . $sUrlNext . '" />' . "\n";
        }
    }
}

/**
 * Obtenemos la url actual donde nos encontramos
 *
 * @return string $sUrl
 */
function getCurrentUrl()
{
	// Variables
	$sUrl = 'http';

	if( $_SERVER["HTTPS"] == "on" )
		$sUrl .= "s";

	$sUrl .= "://";

	if( $_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443" )
		$sUrl .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
	else
		$sUrl .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];

	return $sUrl;
}

/**
 * Comprueba si existe el id en la tabla
 *
 * - @param string $sTable, Tabla a comprobar
 * - @param string $sCampo, Campo a comprobar
 * - @param int $nId, Valor del campo a comprobar
 * - @param int $sWhere, Consulta estra para la sentencia where
 *
 * @return bool
 */
function checkId($sTable, $sCampo, $nId, $sWhere = '')
{
    // Comprobamos que sea un ID valido
    if (!is_int((int) $nId)) {
        return false;
    }

    // Comprobamos si existe
    $aDatos = tep_db_query('select ' . $sCampo . ' from ' . $sTable . ' where ' . $sCampo . ' = ' . $nId . ' ' . $sWhere);

    // Si existe
    if (tep_db_num_rows($aDatos)) {
        return true;
    }

    return false;
}

/**
 * Función que obtiene todas las combinaciones posibles de una frase dividida en palabras
 *
 * - @param string Cadena separada por espacios en la que se obtendrán las combinaciones
 *
 * @return array
 */
function combinations($sString)
{
    // Variables
    $aStrings = null;
    $aArray = array();
    $aReturn = array();

    // Dividimos las cadenas por el espacio
    $aStrings = explode(' ', $sString);

    // Eliminamos elementos repetidos
    $aStrings = array_unique($aStrings);

    // Función showCombo
    if (!function_exists('showCombo')) {
        // Función showCombo
        function showCombo($aExcludes, $aStrings, &$aArray)
        {
            // Array que retornaremos
            $aReturn = array();

            // Recorremos las cadenas
            foreach ($aStrings as $aString) {
                // Si no está en el array excluido
                if (!in_array($aString, $aExcludes)) {
                    // Obtenemos la cadena actual
                    $aTemp = $aExcludes;
                    $aTemp[] = $aString;

                    // Guardamos la combinación
                    $aArray[] = $aTemp;

                    // Buscamos más combinaciones
                    $aComb = showCombo($aTemp, $aStrings, $aArray);

                    // Si hemos obtenido una combinación
                    if (count($aComb) > 0) {
                        $aReturn[] = $aComb;
                    }
                }
            }

            return $aReturn;
        }
    }

    // Obtenemos el combo de cadenas
    showCombo(array(), $aStrings, $aArray);

    // Quitamos las menores de x caracteres
    foreach ($aArray as $aAux) {
        if (count($aAux) == count($aStrings)) {
            $aReturn[] = $aAux;
        }
    }

    return $aReturn;
}

/**
 * Obtenemos una lista con las paginas de información
 *
 * @return string
 */
function getInformacion()
{
    // Preparamos el menu estatico
    $aInformacion = array();
    // Introducimos las ID de información³n que no queremos mostrar
    $aInformacionDenegadas = array();

    // Obtenemos las pagins de informacion
    $aQuery = tep_db_query('SELECT information_id, information_title, parent_id
								 FROM information
								 WHERE visible = 1 and language_id = 3 and information_group_id = 1
								 ' . (count($aInformacionDenegadas) > 0 ? ' and information_id not in(' . implode(',', $aInformacionDenegadas) . ')' : '') . '
								 ORDER BY sort_order');

    // Creamos el menu de información
    while ($aInfo = tep_db_fetch_array($aQuery)) {
        $aInformacion[] = array('FILE' => tep_href_link('information.php', 'info_id=' . $aInfo['information_id']), 'TITLE' => $aInfo['information_title']);
    }

    // Recorremos el submenu
    foreach ($aInformacion as $aSubMenu) {
        // Comprobamos si la url empieza por http para no llamar a la funcion tep_href_link
        if (preg_match('/^http/i', $aSubMenu['FILE'])) {
            $sUrl = $aSubMenu['FILE'];
        } else {
            $sUrl = tep_href_link($aSubMenu['FILE']);
        }

        $sHtml .= '<li' . ($sFile == $aSubMenu['FILE'] ? ' class="actv"' : '') . '><a class="' . $aSubMenu['CLASS'] . '" href="' . $sUrl . '" title="' . $aSubMenu['TITLE'] . '">' . $aSubMenu['TITLE'] . '</a></li>';
    }

    return $sHtml;
}

/*
 * Funcion que devuelve un array con los textos de un archivo del idioma
 *
 * @return array
 */
function getLangugeFile($sFile)
{
    global $language, $sDirNameScriptName;
    $aDenegado = array('<?', '<?php', '?>', ''); // Lineas denegadas cuando leemos un archivo
    $aReturn = array();
    $sFile = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $language . '/' . $sFile;

    // Comprobamos si el archivo existe
    if (file_exists($sFile)) {
        $aDatos = getDefineKeysValuesByFile($sFile, $aDenegado);

        foreach ($aDatos as $key => $value) {
            $aReturn[tep_db_prepare_input($key)] = html_entity_decode($value, ENT_QUOTES, "UTF-8");
        }
    }

    echo json_encode($aReturn);
}

/*
 * Funcion que lee un archivo y devuelve un array linea a linea en utf8
 *
 * @return array
 */
function getLinesFileUtf8($sFile, $sCharset = 'UTF-8')
{
    $sData = '';

    if (!file_exists($sFile)) {
        return false;
    }

    if (floatval(phpversion()) >= 4.3) {
        $sData = file_get_contents($sFile);
    } else {
        $flFile = fopen($sFile, 'r');

        if (!$flFile) {
            return false;
        }

        while (!feof($flFile)) {
            $sData .= fread($flFile, filesize($sFile));
        }

        fclose($flFile);
    }

    if (!isset($sFile)) {
        return false;
    }

    if ($sData && $sEncoding = mb_detect_encoding($sData, 'auto', true) != $sCharset) {
        $sData = @mb_convert_encoding($sData, $sCharset, $sEncoding);
    }

    return preg_split('/\R/', $sData);
}

/*
 * Funcion que lee un archivo lleno de defines y devuelve un array con KEY, VALUE
 *
 * @return array
 */
function getDefineKeysValuesByFile($sRutaCompleta, $aDenegado)
{
    // Array de retorno
    $aReturn = array();

    // Abrimos el archivo
    $flFile = getLinesFileUtf8($sRutaCompleta);

    // Si hemos obtenido el archivo
    if ($flFile) {
        // Recorremos las lineas
        foreach ($flFile as $sLine) {
            // Inicio, Limpiamos la linea \\

            // Quitamos tabuladores
            $sLine = str_replace("\t", '', $sLine);

            // Quitamos los alt+255
            $sLine = str_replace(" ", '', $sLine);

            // Quitamos espacios
            $sLine = trim($sLine);

            // Fin, Limpiamos la linea \\

            // Comprobamos que la linea obtenida no sea algo que no queremos
            if (in_array($sLine, $aDenegado)) {
                continue;
            }

            // Comprobamos que sea un define
            if (!preg_match('/^(define)(\s?)(\()/i', $sLine)) {
                continue;
            }

            // Obtenemos los define de la linea, normalmente sera uno por cada linea, pero puede existir el caso que haya mas de un define en una linea
            preg_match_all("/(define)(\s?)*(\()(.*)(\);)/Ui", $sLine, $aDefines, PREG_PATTERN_ORDER);

            // Si no hemos obtenido nada es que hemos encontrado algun define sin ; al final
            if (count($aDefines[0]) == 0) {
                preg_match_all("/(define)(\s?)*(\()(.*)(\))/Ui", $sLine, $aDefines, PREG_PATTERN_ORDER);
            }

            // Recorremos los define obtenidos
            foreach ($aDefines[0] as $sLine) {
                // echo htmlentities($sLine) . '<br/><br/>-----------------------------------<br/><br/>';

                // Inicio, descomponer el define obtenido \\
                // Descomponemos el define obtenido en KEY y VALUE
                //preg_match('/(define)(\s*)(\()((\'|\")*)(?<KEY>[^,]+)((\'|\")*)(\s*)(\,)(\s*)((\'|\")*)(?<VALUE>.+)((\'|\")*)(\s*)(\))(\;?)$/i', $sLine, $aAux);
                preg_match('/(define)(\s*)(\()(?<KEY>[^,]+)(\s*)(\,)(\s*)(?<VALUE>.+)(\s*)(\))(\;?)$/i', $sLine, $aAux);

                // Comprobamos que el key sea una llamada a funcion y se ha quedado rota, de ser asi utilizamos otro preg_match para obtener el KEY y VALUE
                if (preg_match('/\(/i', $aAux['KEY']) && !preg_match('/\)$/i', $aAux['KEY'])) {
                    preg_match('/(define)(\s*)(\()(?<KEY>.+)(\s*)(\,)(\s*)(?<VALUE>.+)(\s*)(\))(\;?)$/i', $sLine, $aAux);
                }

                // Fin, descomponer el define obtenido \\

                // Inicio, limpiamos el key \\
                // Quitamos espacios
                $aAux['KEY'] = trim($aAux['KEY']);

                // Si contiene una concatenación la cadena dejamos las comas por el contrario eliminariamos
                if (!preg_match('/(\'|")(\s*)\.|\.(\s*)(\'|")/i', $aAux['KEY'])) {
                    $aAux['KEY'] = preg_replace('/^(\'|")|(\'|")$/i', '', $aAux['KEY']);
                }

                // Fin, limpiamos el key \\

                // Inicio, limpiamos el value \\
                // Quitamos espacios
                $aAux['VALUE'] = trim($aAux['VALUE']);

                eval('$sAux = ' . $aAux['VALUE'] . ';');
                $aAux['VALUE'] = $sAux;

                // Si contiene una concatenación la cadena dejamos las comas por el contrario eliminariamos
                if (!preg_match('/(\'|")(\s*)\.(.+)|(\s*)\.(\s*)(\'|")(.+)/i', $aAux['VALUE'])) {
                    $aAux['VALUE'] = preg_replace('/^(\'|")|(\'|")$/i', '', $aAux['VALUE']);
                }

                // Mostramos html como texto para que no afecte cuando se muestre en el input
                $aAux['VALUE'] = htmlentities($aAux['VALUE'], ENT_QUOTES, "UTF-8");
                // Fin, limpiamos el value \\

                // Añadimos la linea al array
                $aReturn[$aAux['KEY']] = $aAux['VALUE'];
            }

            // die();
        }

        return $aReturn;
    }

    return false;
}

// Devuelve true o false indicando si el precio en la web lleva o no lleva IVA
function mostrarIva()
{
    global $sppc_customer_group_show_tax;

    /**
     * Modificación ya que el código
     * original solo aplica a los grupos
     * y no a las zonas.
     * @author Daniel Lucia <daniel.lucia@denox.es>
     * @ticket ONJ-397-59618
     */

    if (isset($_SESSION['sppc_customer_group_show_tax']) && $_SESSION['sppc_customer_group_show_tax'] == 0) {
        return false;
    }

    return tep_get_tax_rate(1) > 0;

    //$bMostrar = ($sppc_customer_group_show_tax == 1 || isset($sppc_customer_group_show_tax) === false ? true : false);

    //return $bMostrar;
}

// Devuelve true o false si la cadena pasada es un json
function is_json($string)
{
    if ($string == '' || $string == null || $string == null || $string == 'null' || $string == 'NULL' || is_numeric($string)) {
        return false;
    }

    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

// Obtenemos una página de información a través de un ID
function getInformationByID($nID)
{
    global $languages_id;

    // Obtenemos la información
    $aInfo = tep_db_query('SELECT * FROM ' . TABLE_INFORMATION . ' WHERE information_id = ' . $nID . ' AND language_id = ' . $languages_id . ';');

    // Si no tenemos registros retornamos false
    if (tep_db_num_rows($aInfo) == 0) {
        return false;
    }

    // Devolvemos el registro
    $aInfo = tep_db_fetch_array($aInfo);
    return $aInfo['information_description'];
}

function existsTableDb($sTableName)
{
    $aDatos = tep_db_query('SHOW TABLES LIKE "' . $sTableName . '"');
    if (tep_db_num_rows($aDatos) > 0) {
        return true;
    }

    return false;
}
/************************\
|* FIN FUNCIONES VARIAS *|
\************************/

/*****************************\
|* INICIO FUNCIONES TEMPLATE *|
\*****************************/
// Funcion para mostrar el menu de login
function _getMenuLoginUser($aArgumentos = array())
{
	global $customerCore;

	// Si no estamos logueados
	if( !$customerCore->hasLogin() )
		return false;

	// Variables
	global $sCustomersEmailAddress;

	echo '<div id="my-account" class="wind-mdal wind-mdal-anchor zoom-anim-dialog mfp-hide">';
		echo '<div class="titu ax row aflex amiddle">';
			echo '<span class="col"><div>' . tep_customer_greeting() . '</div><small>' . $sCustomersEmailAddress . '</small></span>';
		echo '</div>';
		echo '<div class="ax row aflex">';
			echo '<a href="' . tep_href_link( 'account.php' ) . '" title="' . MY_ACCOUNT . '" class="col a04 m06">';
				echo '<div class="imge fa fa-user"></div>';
				echo '<span class="titl">' . MY_ACCOUNT . '</span>';
			echo '</a>';
			echo '<a href="' . tep_href_link( FILENAME_ACCOUNT_HISTORY ) . '" title="' . BOX_HEADING_CUSTOMER_ORDERS . '" class="col a04 m06">';
				echo '<div class="imge fas fa-file-alt"></div>';
				echo '<span class="titl">' . BOX_HEADING_CUSTOMER_ORDERS . '</span>';
			echo '</a>';
			echo '<a href="' . tep_href_link( 'favoritos.php' ) . '" title="' . MY_WISH . '" class="col a04 m06">';
				echo '<div class="imge fa fa-star"></div>';
				echo '<span class="titl">' . MY_WISH . '</span>';
			echo '</a>';
			echo '<a href="' . tep_href_link( FILENAME_LOGOFF ) . '" title="' . LOGIN_LOGOFF . '" class="col a04 m06">';
				echo '<div class="imge fa fa-power-off"></div>';
				echo '<span class="titl">' . LOGIN_LOGOFF . '</span>';
			echo '</a>';
		echo '</div>';
	echo '</div>';
}

// Function para mostrar el formulario de busqueda comunmente usado en la cabecera
function _getSearchForm($aArgumentos = array())
{
	$sHtml = '';
	$bShow = !empty($aArgumentos['SHOW']);
	$sValueSubmit = $aArgumentos['VALUE_SUBMIT'] ?? TEXT_SEARCH;

	$sHtml .= tep_draw_form(
		'search',
		tep_href_link(FILENAME_ADVANCED_SEARCH_RESULT, '', 'NONSSL', false),
		'get',
		'id="form-srch" class="srch-wrpr d-flex ml-auto"'
	);

	$sHtml .= tep_draw_hidden_field('description', '1');
	$sHtml .= tep_draw_hidden_field('auto', '1');
	$sHtml .= tep_draw_input_field(
		'buscar',
		'',
		(SEARCH_AUTOCOMPLETE_DOOFINDER_DENOX == 'Denox' ? 'denox="true"' : (SEARCH_AUTOCOMPLETE_DOOFINDER_DENOX == 'Francobordo' ? 'data-fb-search="true"' : '')) .
		' placeholder="' . TEXT_PLACE_SEARCH . '..."'
	);

	$sHtml .= '<button type="submit" class="tt tt-24" title="' . TEXT_SEARCH . '"></button>';

	$sHtml .= tep_hide_session_id();
	$sHtml .= '</form>';

	if (!$bShow) return $sHtml;
	echo $sHtml;
}


// Funcion para mostrar las banderas de los idiomas disponibles
function _getBanderasIdioma($aArgumentos = array())
{
    // Variables
    global $lng, $PHP_SELF, $request_type, $language;
    $bLista = (empty($aArgumentos['LISTA']) ? true : false);
    $bShow = (empty($aArgumentos['SHOW']) ? false : true);
    $sHtml = '';

    // Comprobamos que $lng sea un objeto ya definido si no lo creamos
    if (!isset($lng) || isset($lng) && !is_object($lng)) {
        include DIR_WS_CLASSES . 'language.php';
        $lng = new language;
    }


    // Recorremos los lenguajes
    foreach( $lng->catalog_languages as $key => $value ) {
        $sHtml .= ($bLista ? '<li>' : '') . '<a id="' . getSlug($value['name']) . ($value['directory'] == $language ? '-actv' : '') . '" href="' . tep_href_link(basename($PHP_SELF), tep_get_all_get_params(array('language', 'currency')) . 'language=' . $key, $request_type) . '">' . $value['name'] . '</a>' . ($bLista ? '</li>' : '');
    }

    // Retornamos o mostramos el html resultante
    if (!$bShow) {
        return $sHtml;
    }

    echo $sHtml;
}

// Function para mostrar una lista de noticias
function _getListaNoticia($aArgumentos = array())
{
    // Variables
    $nMax = (empty($aArgumentos['MAX']) ? 5 : $aArgumentos['MAX']);
    $sOrder = (empty($aArgumentos['ORDER']) ? 'desc' : $aArgumentos['ORDER']);
    $nSizeTitulo = (empty($aArgumentos['SIZE_TITULO']) ? false : $aArgumentos['SIZE_TITULO']);
    $nSizeNoticia = (empty($aArgumentos['SIZE_NOTICIA']) ? false : $aArgumentos['SIZE_NOTICIA']);
    $bShowTitle = (key_exists('SHOW_TITLE', $aArgumentos) ? $aArgumentos['SHOW_TITLE'] : true);
    $bShowDate = (key_exists('SHOW_DATE', $aArgumentos) ? $aArgumentos['SHOW_DATE'] : true);
    $nSizeMas = (key_exists('SIZE_MAS', $aArgumentos) ? $aArgumentos['SIZE_MAS'] : 0);
    $bShow = (empty($aArgumentos['SHOW']) ? false : true);
    $sHtml = '';

    // Consultamos las noticias
    $aDatos = tep_db_query('select id, date_format(date,"%d/%m/%Y") as date, noticia, titulo
								 from noticias
								 order by UNIX_TIMESTAMP( date )  ' . $sOrder . ' limit ' . $nMax);

    // Si hemos obtenido noticias
    if (tep_db_num_rows($aDatos) > 0) {
        $sHtml .= '<div id="box-ntcs">';
        $sHtml .= '<a id="box-ntcs-mas" href="' . tep_href_link('noticias.php') . '" title="Ver todas las noticias"></a>';

        // Mostramos enlaces de ver mas noticias
        for ($nCont = 1; $nCont <= $nSizeMas; $nCont++) {
            $sHtml .= '<a id="box-ntcs-mas' . $nCont . '" href="' . tep_href_link('noticias.php') . '" title="Ver todas las noticias"></a>';
        }

        $sHtml .= '<ul>';

        while ($aDato = tep_db_fetch_array($aDatos)) {
            $sHtml .= '<li>
					<div>' . ($bShowDate ? $aDato['date'] . ' - ' : '') . ($bShowTitle ? ($nSizeTitulo ? truncate($aDato['titulo'], array('SIZE' => $nSizeTitulo)) : $aDato['titulo']) : '') . '</div>
					<span>' . ($nSizeTitulo ? truncate($aDato['noticia'], array('SIZE' => $nSizeNoticia, 'CLEAR' => true)) : $aDato['noticia']) . '
					<a href="' . getSlug(truncate($aDato['titulo'], array('SIZE' => 50))) . '-n-' . $aDato['id'] . '.html" title="Leer noticia completa">leer+</a></span>
				</li>';
        }

        $sHtml .= '</ul></div>';
    }

    // Retornamos o mostramos el html resultante
    if (!$bShow) {
        return $sHtml;
    }

    echo $sHtml;
}

// Funcion para mostrar formulario de filtro
function _getFiltro($aArgumentos = array())
{
    // Variables
    global $aFiltro, $aFiltroOrdenar, $aFiltroNumero, $current_category_id, $cPath, $aPaginador, $nProductosTotal;
    $sFiltro = (empty($aArgumentos['FILTRO']) ? FILTRO_FILTRO : $aArgumentos['FILTRO']);
    $sOrdenar = (empty($aArgumentos['ORDENAR']) ? FILTRO_ORDENAR : $aArgumentos['ORDENAR']);
    $nNumero = (empty($aArgumentos['NUMERO']) ? FILTRO_NUMERO : $aArgumentos['NUMERO']);
    $bVista = (empty($aArgumentos['VISTA']) ? true : $aArgumentos['VISTA']);
    $aExtra = (empty($aArgumentos['EXTRA']) ? false : $aArgumentos['EXTRA']);
    $bShow = (empty($aArgumentos['SHOW']) ? false : true);
    $sPostFiltro = tep_db_prepare_input($_GET['filtro'] ?? '');
    $sPostOrden = tep_db_prepare_input($_GET['orden'] ?? '');
    $sPostNumero = tep_db_prepare_input($_GET['numero'] ?? '');
    $sHtml = '';
    $sMethod = 'get';

    // Comprobamos si existe el formulario de filtros
    if (isset($aFiltro) || isset($aFiltroOrdenar) || $aFiltroNumero) {
        // Si existen campos por post el filtro sera post
        if (count($_POST) > 0) {
            $sMethod = 'post';
            $sPostFiltro = tep_db_prepare_input($_POST['filtro']);
            $sPostOrden = tep_db_prepare_input($_POST['orden']);
            $sPostNumero = tep_db_prepare_input($_POST['numero']);
        }

        $sHtml .= '<form id="fltr" action="' . $_SERVER['PHP_SELF'] . '" method="' . $sMethod . '">';
			$sHtml .= '<div class="d-flex web-cntd">';
				if( $aPaginador )
				{
					$sHtml .= '<div class="mstr d-flex-mx">';
						$sHtml .= sprintf( TEXT_MOSTRAR_PAG, $nProductosTotal, $aPaginador->number_of_rows );
					$sHtml .= '</div>';
				}

				// Boton comparar
				$sHtml .= $sHtmlComparar;

				// Si tenemos contenido extra que deseamos mostrar arriba
				if ($aExtra && $aExtra['POSITION'] == 'top') {
					$sHtml .= $aExtra['HTML'];
				}

				$sHtml .= '<div class="xform m-auto d-flex">';
					// Comprobamos si contiene filtro
					if (isset($aFiltro) && count( $aFiltro ) > 0) {
						$sHtml .= '<select onchange="this.form.submit();" name="filtro" id="filtro">';
						// Recorremos los filtros para mostrarlo
						foreach ($aFiltro as $key => $value) {
							$sHtml .= '<option ' . ($sPostFiltro == $key ? 'selected="selected"' : '') . ' value="' . $key . '">' . $value['TEXT'] . '</option>';
						}

						$sHtml .= '</select>';
					}

					// Comprobamos si contiene orden
					if (isset($aFiltroOrdenar)) {
						$sHtml .= '<select onchange="this.form.submit();" name="orden" id="orden">';
						// Recorremos los order para mostrarlo
						foreach ($aFiltroOrdenar as $key => $value) {
							$sHtml .= '<option ' . ($sPostOrden == $key ? 'selected="selected"' : '') . ' value="' . $key . '">' . $value['TEXT'] . '</option>';
						}

						$sHtml .= '</select>';
					}

					// Comprobamos si contiene numero
					if (isset($aFiltroNumero)) {
						$sHtml .= '<select name="numero" id="numero" style="display: none;">';
						// Recorremos los numeros para mostrarlo
						foreach ($aFiltroNumero as $key => $value) {
							$sHtml .= '<option ' . ($sPostNumero == $key ? 'selected="selected"' : '') . ' value="' . $key . '">' . $value['TEXT'] . '</option>';
						}

						$sHtml .= '</select>';
					}
				$sHtml .= '</div>';

				// Comprobamos si debemos añadir el icono de cambiar vista
				if ($bVista)
				{
					$sHtml .= '<div class="' . (!empty($_SESSION['vista']) && $_SESSION['vista'] == 'chng-vsta-hrzt' ? 'chng-vsta-hrzt' : 'chng-vsta-vrtl') . ' mhide" href="javascript:void(0);" id="chng-vsta">';
						$sHtml .= '<i class="tt tt-4"></i> ';
						$sHtml .= '<i class="tt tt-9 mhide"></i>';
					$sHtml .= '</div>';
				}

				// Recorremos todos los campos get
				foreach ($_GET as $key => $value) {
					if (!in_array($key, array('filtro', 'orden', 'numero', 'dxfilter'))) {
						$sHtml .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
					}
				}

				// Recorremos todos los campos post
				foreach ($_POST as $key => $value) {
					if (!in_array($key, array('filtro', 'orden', 'numero', 'dxfilter'))) {
						$sHtml .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
					}
				}

				$sHtml .= '<input type="hidden" name="dxfilter" value="1" />';

				// Si tenemos contenido extra que deseamos mostrar abajo
				if ($aExtra && $aExtra['POSITION'] == 'bottom') {
					$sHtml .= $aExtra['HTML'];
				}
			$sHtml .= '</div>';
        $sHtml .= '</form>';

        // Retornamos o mostramos el html resultante
        if (!$bShow) {
            return $sHtml;
        }
    }

    echo $sHtml;
}
/**************************\
|* FIN FUNCIONES TEMPLATE *|
\**************************/

function dateTimeDiff($dInicio, $dFin, $bSumarDias = false)
{
    // Variables
    $aFecha['inicio'] = strtotime((string)$dInicio);
    $aFecha['fin'] = strtotime((string)$dFin);

    if ($aFecha['inicio'] !== -1 && $aFecha['fin'] !== -1) {
        if ($aFecha['fin'] >= $aFecha['inicio']) {
            $dDiff = $aFecha['fin'] - $aFecha['inicio'];

            if ($dDia = intval((floor($dDiff / 86400)))) {
                $dDiff = $dDiff % 86400;
            }

            if ($dHora = intval((floor($dDiff / 3600)))) {
                $dDiff = $dDiff % 3600;
            }

            if ($dMinuto = intval((floor($dDiff / 60)))) {
                $dDiff = $dDiff % 60;
            }

            $dDiff = intval($dDiff);

            if ($bSumarDias && $dDia > 0) {
                $dHora += ($dDia * 24);
            }

            return (array('dia' => $dDia, 'hora' => $dHora, 'minuto' => $dMinuto, 'segundo' => $dDiff));
        }
    }

    return (false);
}

function getShippingEstimate($bDetailed = false, $bText = true, $nHoursToReception = false, $sModule = false)
{
    // Variables
    $aLocation = false;
    $sCity = false;
    $sCP = false;
    $nDay = date('d');
    $nMonth = date('m');
    $nYear = date('Y');
    $nDays = false;
    $dDelivery = false;
    $bPrediction = true;
	global $customerCore;

    // Global
    global $customer_id;

    // Si el cliente NO está registrado / NO ha hecho login
    if (!$customerCore->hasLogin()) {
        // Ciudad y CP por defecto
        $sCity = 'Madrid';
        $sCP = '28070';
    }
    // Si el cliente SI está registrado / SI ha hecho login
    else {
        // Si NO tenemos su CP en sesión
        if (!array_key_exists('customer_cp', $_SESSION)) {
            // Obtenemos su dirección
            $aAddress = tep_db_query('SELECT ab.entry_postcode, z.zone_name FROM address_book ab INNER JOIN zones z ON (ab.entry_zone_id = z.zone_id) WHERE ab.address_book_id = "' . $_SESSION['customer_default_address_id'] . '";');

            // Registro
            $aAddress = tep_db_fetch_array($aAddress);

            // Obtenemos su CP
            $sCP = $aAddress['entry_postcode'];
            $sCity = $aAddress['zone_name'];
        }
        // Si SI tenemos su CP en sesión
        else {
            $sCP = $_SESSION['customer_cp'];
        }
    }

    // Si la ciudad es una ciudad especial, Baleares
    if ($sCity == 'Baleares') {
        $nDays = 3;
    }

    // Si la ciudad es una ciudad especial, Las Palmas, Ceuta o Melilla
    elseif ($sCity == 'Las Palmas' || $sCity == 'Ceuta' || $sCity == 'Melilla') {
        $bPrediction = false;
    }

    // Si no, obtenemos la predicción por el CP
    else {
        // A través del CP comprobamos su predicción de tiempo de envío
        $aPrediction = tep_db_query('SELECT prediction FROM shipping_prediction_cp WHERE cp = "' . $sCP . '";');

        // Si hemos obtenido una predicción
        if (tep_db_num_rows($aPrediction) > 0) {
            // Registro
            $aPrediction = tep_db_fetch_array($aPrediction);

            // Obtenemos la predicción de días
            switch ($aPrediction['prediction']) {
                // Un día
                case 'diary':
                    $nDays = 1;
                    break;
                // Más de 24 horas
                case '+24':
                    $nDays = 2;
                    break;
                // CP sin predicción
                case 'no-predict':
                case 'consult':
                    $nDays = 1;
                    break;
            }
        }
        // Si No hemos obtenido una predicción
        else {
            // Insertamos el CP para consultar
            if ($sCP != '') {
                tep_db_query('INSERT INTO shipping_prediction_cp VALUES ("' . $sCP . '", "consult");');
            }

            // Por defecto 1 día
            $nDays = 1;
        }
    }

    // Fecha de envío //

    // Si la hora del pedido es DESPUÉS de la fecha límite
    if (date('H') >= MODULE_ESTIMATED_SHIP_TIMES && date('H') <= 23) {
        // La fecha del envío es mañana
        $dDelivery = addHoursToDate($nYear . '-' . $nMonth . '-' . $nDay, 24);
    }
    // Si la hora del pedido es ANTES de la fecha límite
    else {
        // La fecha del envío es hoy
        $dDelivery = addHoursToDate($nYear . '-' . $nMonth . '-' . $nDay, 0);
    }

    // Si el día del envío es sábado, sumamos 2 días
    if (date('N', mktime(0, 0, 0, $dDelivery['month'], $dDelivery['day'], $dDelivery['year'])) == 6 && $sModule != 'seurnacional' && (SHIPPING_ESTIMATE_SATURDAYS == 'No' || (SHIPPING_ESTIMATE_SATURDAYS == 'Si' && $sModule != 'tipsa' && date('H') <= 12))) {
        $dDelivery = addHoursToDate($dDelivery['year'] . '-' . $dDelivery['month'] . '-' . $dDelivery['day'], 48);
    }

    // Si el día del envío es domingo, sumamos 1 día
    elseif (date('N', mktime(0, 0, 0, $dDelivery['month'], $dDelivery['day'], $dDelivery['year'])) == 7) {
        $dDelivery = addHoursToDate($dDelivery['year'] . '-' . $dDelivery['month'] . '-' . $dDelivery['day'], 24);
    }

    // Comprobamos festivos y días sin envíos
    while (true) {
        // Consultamos si hay festivo
        $aAux = tep_db_query('SELECT * FROM shipping_prediction_calendar WHERE calendar_day = "' . $dDelivery['day'] . '" AND (calendar_month = "' . $dDelivery['month'] . '" OR calendar_month IS NULL OR calendar_month = "") AND (calendar_year = "' . $dDelivery['year'] . '" OR calendar_year IS NULL OR calendar_year = "") AND (calendar_type = "national" OR calendar_type = "personal" OR (calendar_type = "autonomic" AND calendar_province = "Madrid") OR (calendar_type = "local" AND calendar_province = "Madrid"));');

        // Si tenemos un día festivo o un día sin envío, sumamos 24 horas
        if (tep_db_num_rows($aAux) > 0) {
            // Sumamos 24 horas
            $dDelivery = addHoursToDate($dDelivery['year'] . '-' . $dDelivery['month'] . '-' . $dDelivery['day'], 24);
            continue;
        }
        // Si el día SI tiene envíos
        else {
            // Si el día del envío es sábado, sumamos 2 días
            if (date('N', mktime(0, 0, 0, $dDelivery['month'], $dDelivery['day'], $dDelivery['year'])) == 6 && $sModule != 'seurnacional' && (SHIPPING_ESTIMATE_SATURDAYS == 'No' || (SHIPPING_ESTIMATE_SATURDAYS == 'Si' && $sModule != 'tipsa' && date('H') <= 12))) {
                $dDelivery = addHoursToDate($dDelivery['year'] . '-' . $dDelivery['month'] . '-' . $dDelivery['day'], 48);
            }

            // Si el día del envío es domingo, sumamos 1 día
            elseif (date('N', mktime(0, 0, 0, $dDelivery['month'], $dDelivery['day'], $dDelivery['year'])) == 7) {
                $dDelivery = addHoursToDate($dDelivery['year'] . '-' . $dDelivery['month'] . '-' . $dDelivery['day'], 24);
            }

            // Si es un día laboral con envíos rompemos
            else {
                break;
            }
        }
    }

    // Fecha de recepción //

    // Si tenemos sumatorio de horas a la recepción
    if ($nHoursToReception !== false) {
        $nDays += ($nHoursToReception / 24);
    }

    // Sumamos a la fecha de envío los días de la predicción
    $dReception = addHoursToDate($dDelivery['year'] . '-' . $dDelivery['month'] . '-' . $dDelivery['day'], (24 * $nDays));

    // Si el día del recepción es sábado, sumamos 2 días
    if (date('N', mktime(0, 0, 0, $dReception['month'], $dReception['day'], $dReception['year'])) == 6 && $sModule != 'seurnacional' && $sModule != 'tipsawednesday') {
        // Si estamos logueados y no somos de España
        if ((!isset($_SESSION['customer_country_id'])) || (isset($_SESSION['customer_country_id']) && $_SESSION['customer_country_id'] != 195)) {
            $dReception = addHoursToDate($dReception['year'] . '-' . $dReception['month'] . '-' . $dReception['day'], 48);
        }
    }
    // Si el día del recepción es domingo, sumamos 1 día
    elseif (date('N', mktime(0, 0, 0, $dReception['month'], $dReception['day'], $dReception['year'])) == 7) {
        $dReception = addHoursToDate($dReception['year'] . '-' . $dReception['month'] . '-' . $dReception['day'], 24);
    }

    // Comprobamos festivos
    while (true) {
        // Consultamos si hay festivo
        $aAux = tep_db_query('SELECT * FROM shipping_prediction_calendar WHERE calendar_day = "' . $dReception['day'] . '" AND (calendar_month = "' . $dReception['month'] . '" OR calendar_month IS NULL OR calendar_month = "") AND (calendar_year = "' . $dReception['year'] . '" OR calendar_year IS NULL OR calendar_year = "");');

        // Si tenemos un día festivo, sumamos 24 horas
        if (tep_db_num_rows($aAux) > 0) {
            // Sumamos 24 horas
            $dReception = addHoursToDate($dReception['year'] . '-' . $dReception['month'] . '-' . $dReception['day'], 24);
            continue;
        }
        // Si el día NO tiene festivos
        else {
            // Si el día del recepción es sábado, sumamos 2 días
            if (date('N', mktime(0, 0, 0, $dReception['month'], $dReception['day'], $dReception['year'])) == 6 && $sModule != 'seurnacional' && $sModule != 'tipsawednesday') {
                $dReception = addHoursToDate($dReception['year'] . '-' . $dReception['month'] . '-' . $dReception['day'], 48);
            }

            // Si el día del recepción es domingo, sumamos 1 día
            elseif (date('N', mktime(0, 0, 0, $dReception['month'], $dReception['day'], $dReception['year'])) == 7) {
                $dReception = addHoursToDate($dReception['year'] . '-' . $dReception['month'] . '-' . $dReception['day'], 24);
            }

            // Si es un día laboral con recepciones rompemos
            else {
                break;
            }
        }
    }

    // Predicción en modo texto
    if ($bText) {
        // Si tenemos predicción
        if ($bPrediction) {
            return '<p>Compra antes de las <span>' . MODULE_ESTIMATED_SHIP_TIMES . ':00h</span> de ' . (date('H') >= MODULE_ESTIMATED_SHIP_TIMES && date('H') <= 23 ? 'mañana' : 'hoy') . ' y recibirás tu pedido el <span>' . dateToSpanish(date('l j \d\e F', strtotime($dReception['year'] . '-' . $dReception['month'] . '-' . $dReception['day']))) . '</span>.</p>';
        }

        // Si NO tenemos predicción
        else {
            return '<p>No se ha podido realizar una predicción de envío.</p>';
        }
    }
    // Predicción en fecha
    else {
        // Si tenemos predicción
        if ($bPrediction) {
            return $dReception;
        }

        // Si NO tenemos predicción
        else {
            return false;
        }
    }
}

function addHoursToDate($dDate, $nHours)
{
    // Sumamos las horas a la fecha
    $dDate = strtotime('+' . $nHours . ' hour', strtotime($dDate));

    // Retornamos la fecha con diferentes formatos
    return array('date' => date('d-m-Y', $dDate), 'day' => date('d', $dDate), 'month' => date('m', $dDate), 'year' => date('Y', $dDate));
}

function dateToSpanish($sDate)
{
    // Globales
    global $languages_id;

    // Solo traducimos al Español
    if ($languages_id == 3) {
        // Traducimos el día de la semana
        $sDate = str_replace(
            array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
            array('Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'),
            $sDate
        );

        // Traducimos el mes
        $sDate = str_replace(
            array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'),
            array("Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"),
            $sDate
        );
    }

    return $sDate;
}

/**
 * Función que devuelve las clases que debe tener el body
 *
 * @author Daniel Lucia <daniel.lucia@denox.es>
 * @return string
 */
function getBodyClasses(bool $returnArray = false)
{
    $excluded = ['login'];

    $classes = [];
    $classes[] = 'preload';
    $classes[] = 'home';

    $page = str_replace('.php', '', basename($_SERVER['PHP_SELF']));
    if (!in_array($page, $excluded)) {
        $classes[] = $page;
    }

    $theme = getClassTheme();
    if ($theme != '') {
        $classes[] = $theme;
    }

    if (!$returnArray) {
        return implode(' ', $classes);
    }

    return $classes;
}

function getClassTheme() : string {

    $theme = '';

    if (!defined('THEME_STATUS') || constant('THEME_STATUS') == 'Inactivo') {
        return $theme;
    }

    if (constant('THEME_IPS') != '') {
        $ips = array_map('trim', explode(',', constant('THEME_IPS')));

        if (!in_array($_SERVER['REMOTE_ADDR'], $ips)) {
            return $theme;
        }
    }

    if (constant('THEME_ACTIVE') != 'Ninguno') {
        $theme = constant('THEME_ACTIVE');
    }

    return $theme;
}


function sanitizeDb($input)
{
    // Si es un array
    if (is_array($input)) {
        $output = array();

        foreach ($input as $sKey => $sValue) {
            $output[$sKey] = sanitizeDb($sValue);
        }

    } else {

        $aSearch = array(
            '@<script[^>]*?>.*?</script>@si', // Limpiar javascript
            '@<[\/\!]*?[^<>]*?>@si', // Limpiar html
            '@<style[^>]*?>.*?</style>@siU', // Limpiar tags
            '@<![\s\S]*?--[ \t\n\r]*>@', // Limpiar saltos de linea
        );

        $input = preg_replace($aSearch, '', $input);

        // Realizamos un mysql_real_escape
        $output = str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $input);
    }

    // Retornamos
    return $output;
}

/**
 * Función apra mostrar elementos o funcionalidades por IP
 *
 * @author Daniel Lucia <daniel.lucia@denox.es>
 * @return boolean
 */
function isSandBox()
{
    return in_array($_SERVER['REMOTE_ADDR'], array('188.78.211.125', '217.127.199.171', '90.94.246.20', '90.94.245.163', '91.187.64.253'));
}

/**
 * Función para obtener un array con las categorias TOP
 *
 * @author Daniel Lucia <daniel.lucia@denox.es>
 * @param array $sIdCategoriasPadres
 * @return array
 */
function getCategoriesTop($sIdCategoriasPadres, $bShowSubmenu = true)
{
    global $languages_id;
    $aCategorias = array();

    if (!empty($sIdCategoriasPadres)) {
        $sSql = 'select cd.categories_name, cd.categories_id
		from categories c
		inner join categories_description cd on (cd.categories_id = c.categories_id)
		where c.categories_id in( ' . implode(', ', $sIdCategoriasPadres) . ' ) and cd.language_id = ' . $languages_id . '
		order by c.sort_order asc';

        $aDatos = tep_db_query($sSql);

        while ($aDato = tep_db_fetch_array($aDatos)) {
            if (!$bShowSubmenu) {
                $aCategorias[] = array(
                    'categories_id' => $aDato['categories_id'],
                    'categories_name' => $aDato['categories_name'],
                );
            } else {
                $aCategorias[] = array(
                    'categories_id' => $aDato['categories_id'],
                    'categories_name' => $aDato['categories_name'],
                    'subcategorias' => tep_db_query('select cd.categories_name, cd.categories_id
														  from categories c
														  inner join categories_description cd on (cd.categories_id = c.categories_id)
														  where c.parent_id = ' . $aDato['categories_id'] . ' and cd.language_id = ' . $languages_id),
                );
            }
        }
    }

    return $aCategorias;
}

function getPluralSingular($sText, $sReturn = 'text')
{
        // Variables
        $aSearchPlural = array();
        $aSearchSingular = array();
        $aWords = null;

        // Separamos las palabras
        $aWords = preg_split("/[\s]|[,]|[.]|[-]/", $sText, -1, PREG_SPLIT_NO_EMPTY);

        // Formateamos las palabras de la búsqueda
        foreach ($aWords as $aWord) {
            // Eliminamos las palabras demasiado cortas (pronombres, etc)
            if (strlen($aWord) > 1) {
                // Si es una preposición, artículo o nexo continuamos
                if (in_array($aWord, array('a', 'ante', 'bajo', 'cabe', 'con', 'contra', 'de', 'desde', 'en', 'entre', 'hacia', 'hasta', 'para', 'por', 'según', 'segun', 'sin', 'so', 'sobre', 'tras', 'del', 'al', 'el', 'la', 'las', 'los', 'un', 'unos', 'un', 'una', 'unas', 'y', 'u', 'o', 'e'))) {
                    continue;
                }

                // Si es un número
                if (is_numeric($aWord)) {
                    // Añadimos el número en singular y plural
                    $aSearchPlural[] = $aWord;
                    $aSearchSingular[] = $aWord;
                    continue;
                }

                // Comprobamos si la palabra está en plural (si termina en -s, -es, -ces), para obtener su singular
                if (preg_match('/s$/', $aWord) || preg_match('/es$/', $aWord) || preg_match('/ces$/', $aWord)) {
                    // Añadimos al array plural
                    $aSearchPlural[] = $aWord;

                    // Añadimos al array singular
                    if (preg_match('/ces$/', $aWord)) {
                        $aSearchSingular[] = preg_replace('/ces$/', 'z', $aWord);
                    } else if (preg_match('/es$/', $aWord)) {
                    $aSearchSingular[] = preg_replace('/es$/', '', $aWord);
                } else if (preg_match('/s$/', $aWord)) {
                    $aSearchSingular[] = preg_replace('/s$/', '', $aWord);
                }

            }
            // Si la palabra está en singular
            else {
                    // Añadimos al array singular
                    $aSearchSingular[] = $aWord;

                    // Obtenemos su plural
                    if (preg_match('/[a]$|[e]$|[o]$/', $aWord)) {
                        $aSearchPlural[] = $aWord . 's';
                    } else if (preg_match('/[z]$/', $aWord)) {
                    $aSearchPlural[] = preg_replace('/z$/', 'ces', $aWord);
                } else {
                    $aSearchPlural[] = $aWord . 'es';
                }

            }
        }
    }

    // Retornamos
    if ($sReturn == 'text') {
        $sReturn = '';

        foreach ($aSearchSingular as $nWord => $aWord) {
            $sReturn .= sanitizeDb($aSearchSingular[$nWord]) . ' ' . sanitizeDb($aSearchPlural[$nWord]) . ' ';
        }

        return substr($sReturn, 0, -1);
    } else {
        return array('singular' => $aSearchSingular, 'plural' => $aSearchPlural);
    }

}


function maybeYouWantedToSay()
{
    // Globales
    global $languages_id;

    // Variables
    $sSearch = strtolower(tep_db_prepare_input($_GET['search']));
    $sMaybe = $sSearch;
    $aDictionary = array();
    $bChange = false;

    // Generamos diccionario de palabras //

    // Obtenemos los nombres de los productos
    $aProducts = tep_db_query('SELECT products_name FROM ' . TABLE_PRODUCTS_DESCRIPTION . ' WHERE language_id = "' . (int) $languages_id . '";');

    // Recorremos los nombres y generamos el diccionario
    while ($aProduct = tep_db_fetch_array($aProducts)) {
        // Separamos las palabras
        $aWords = preg_split("/[\s]|[,]|[.]|[-]|[(]|[)]|[\/]|[+]/", $aProduct['products_name'], -1, PREG_SPLIT_NO_EMPTY);

        // Recorremos las palabras
        foreach ($aWords as $aWord) {
            // Si la palabra es mayor de 3 caracteres
            if (strlen($aWord) > 3) {
                // Formateamos la palabra
                $aWord = strtolower($aWord);

                // Añadimos la palabra
                $aDictionary[] = $aWord;
            }
        }
    }

    // Eliminamos elementos repetidos
    $aDictionary = array_unique($aDictionary);

    // Comprobamos la búsqueda //

    // Separamos las palabras
    $aWords = preg_split("/[\s]|[,]|[.]|[-]/", $sSearch, -1, PREG_SPLIT_NO_EMPTY);

    // Formateamos las palabras de la búsqueda
    foreach ($aWords as $aWord) {
        // Recorremos las palabras y comprobamos si hay que corregir
        foreach ($aDictionary as $aDic) {
            // Calculamos la proximidad
            $nProximity = (levenshtein($aWord, $aDic) * 100) / strlen($aWord);

            // Si está entre el 0 % y el 25 %, lo cambiamos
            if ($nProximity <= 25) {
                // Cambiamos la palabra
                $sMaybe = str_replace($aWord, $aDic, $sMaybe);
                $bChange = true;
                break;
            }
        }
    }

    // Si hemos encontrado cambio
    if ($bChange) {
        return '<p style="font-size: 20px; margin-top: 20px;"><a href="' . tep_href_link(FILENAME_SEARCH) . '?search=' . str_replace(' ', '+', $sMaybe) . '" title="' . MAYBE_YOU_WANTED_TO_SAY . ' ' . $sMaybe . '..." style="color: #3a3e2f;">' . MAYBE_YOU_WANTED_TO_SAY . ' <b style="color: #f60;">' . $sMaybe . '</b>...</a></p>';
    }

    return false;
}

// Función usada en header.php para montar arbol de categorías de forma recursiva
function printMenuCategories( $aAllCategorias, $nIdParent )
{
	global $_aAllDatos;
	$sReturn = '';

	foreach( $aAllCategorias[$nIdParent] as $aCategoryParent )
	{
		if( $aCategoryParent['parent_id'] == 0 )
			$sImagenCategoria = getImagenCategoria( $aCategoryParent['categories_image'], 'menu', '', false );

		$sReturn .= '<li>';
			// Comprobamos si tiene subcategoria
			if( isset( $aAllCategorias[$aCategoryParent['categories_id']] ) && $nIdParent != 1 )
			{
				$sReturn .= '<a class="link2 idc-' . $aCategoryParent['categories_id'] . '" href="javascript:void(0);">';
					if( $aCategoryParent['parent_id'] == 0 )
					{
						if( $sImagenCategoria && file_exists( DIR_WS_IMAGES . 'categorias/' . $sImagenCategoria ) )
							$sReturn .= tep_image( DIR_WS_IMAGES . 'categorias/' . $sImagenCategoria , $aCategoria['categories_name'], 21, 21, '', false, false );
						else
							$sReturn .= '<i class="tt tt-45"></i>';
					}
					$sReturn .= truncate( $aCategoryParent['categories_name'], array('SIZE' => 24) );
				$sReturn .= '</a>';

				$sReturn .= '<ul class="sbmn">';
					$sReturn .= '<li class="mp-back"><i class="fas fa-chevron-left"></i> ' . TEXT_VOLVER . ' ' . ($aCategoryParent['parent_id'] > 0 ? TEXT_SECCION_ANT : TEXT_SECCION_TODAS) . '</li>';

					$sReturn .= '<li class="lnkfrs"><span class="link4"><span><</span>' . $aCategoryParent['categories_name'] . '</span></li>';

					$sReturn .= printMenuCategories( $aAllCategorias, $aCategoryParent['categories_id'] );

					$sReturn .= '<li><a href="' . tep_href_link( 'categories.php', 'cPath=' . $aCategoryParent['categories_id'] ) . '" class="link6" title="' . TEXT_SHOW_ALL . ' ' .$aCategoryParent['categories_name'] . '"><span>></span> ' . TEXT_SHOW_ALL . '</a></li>';
				$sReturn .= '</ul>';
			}
			else
			{
				$sReturn .= '<a class="link' . ($aCategoryParent['parent_id'] == 0 ? '2' : '5') . '" href="' . tep_href_link( 'categories.php', 'cPath=' . $aCategoryParent['categories_id'] ) . '" title="' . $aCategoryParent['categories_name'] . '" alt="' . $aCategoryParent['categories_name'] . '">';

					if( $aCategoryParent['parent_id'] == 0 )
					{
						if( $sImagenCategoria && file_exists( DIR_WS_IMAGES . 'categorias/' . $sImagenCategoria ) )
							$sReturn .= tep_image( DIR_WS_IMAGES . 'categorias/' . $sImagenCategoria , $aCategoria['categories_name'], 21, 21, '', false, false );
						else
							$sReturn .= '<i class="tt tt-45"></i>';
					}

					$sReturn .= $aCategoryParent['categories_name'];
				$sReturn .= '</a>';
			}
		$sReturn .= '</li>';
	}

	return $sReturn;
}

/**
 * Modifica la url de una imagen
 * para que muestre la que necesita la plantilla
 *
 * @param string $image
 * @return string
 */
function modifyImageForTheme(string $image) : string {

    if (in_array('BlackFriday', getBodyClasses(true))) {
        $image = str_replace('.', '-blackfriday.', $image);
    }

    if (in_array('Christmas', getBodyClasses(true))) {
        $image = str_replace('.', '-christmas.', $image);
    }

    return $image;
}

/**
 * Retorna un select con los atributos de un producto
 *
 * #EXE-972-18979
 * @param integer $products_id
 * @param array $aDato
 * @param array $attributes
 * @return string
 * @author Daniel Lucia <daniel.lucia@denox.es>
 */
function getAttributesSelectHtml(int $products_id, array $aDato, $attributes = []): string
{

    global $languages_id, $currencies, $nCustomerGroupId;

    $html = '';

    if (!$aDato['price']) {
        $aDato['price'] = $aDato['products_price'];
    }

    // Datos
    $nTaxRate = ($nCustomerGroupId != 0 ? 0 : tep_get_tax_rate($aDato['products_tax_class_id']));
    $sPrecioRelacionado = tep_add_tax($aDato['price'], $nTaxRate);
    $sPrecio = $currencies->display_price($aDato['products_price'], $nTaxRate);
    $sOferta = '';

    // Si tiene oferta
    if ($aDato['products_price_anterior'] != '') {
        $sPrecio = $currencies->display_price($aDato['products_price'], $nTaxRate);
        $sPrecio = str_replace(array('&euro;'), array('€'), $sPrecio);
        $sOferta = $currencies->display_price($aDato['products_price_anterior'], $nTaxRate);
    }

    // Formateamos
    $sPrecioLastFormat = tep_add_tax($aDato['products_price'], $nTaxRate);
    $sPrecioFormat = $sPrecioRelacionado;

    $nPorcentaje = floatval($sPrecioFormat * 100 / $sPrecioLastFormat);

    $sql = 'SELECT po.products_options_id, po.products_options_name, po.products_options_type, po.products_options_track_stock
    FROM products_attributes pa
    INNER JOIN products_options po ON(pa.options_id = po.products_options_id)
    WHERE pa.products_id = "' . (int) $products_id . '" AND po.language_id = "' . (int) $languages_id . '"
    GROUP BY pa.options_id ORDER BY pa.products_options_sort_order asc';

    $aDatosAttb = tep_db_query($sql);
    while ($aDatoAttb = tep_db_fetch_array($aDatosAttb)) {
        $aDatosOption = tep_db_query('SELECT pa.products_attributes_id, pov.products_options_values_id, pov.products_options_values_name, pa.options_values_price, pa.price_prefix, pa.reference, pa.products_attributes_ean, pa.weight_prefix, pa.options_values_weight, pa.options_values_id, pa.options_id
        FROM products_attributes pa
        INNER JOIN products_options_values pov ON(pa.options_values_id = pov.products_options_values_id)
        WHERE pa.options_id = "' . (int) $aDatoAttb['products_options_id'] . '" and pa.products_id = "' . (int) $products_id . '" AND pov.language_id = "' . (int) $languages_id . '"
        ORDER BY pa.products_options_sort_order asc');

        $html .= '<div class="xform">';
        $html .= '<label>' . $aDatoAttb['products_options_name'] . ': <div class="color"></div></label>';
        $html .= '<select' . ($aDatoAttb['products_options_track_stock'] == 1 ? ' data-track="1" data-name="' . $aDatoAttb['products_options_name'] . '" data-required="true"' : '') . ' name="id[' . $aDatoAttb['products_options_id'] . ']" data-oid="' . $aDatoAttb['products_options_id'] . '">';
        $html .= '<option data-price="" data-price-last="" value="" disabled selected>' . TEXT_SELECCIONE . '</option>';

        while ($aDatoOption = tep_db_fetch_array($aDatosOption)) {
            $sPrice = 0;
            $sPriceData = 0;

            if ($aDatoOption['options_values_price'] != '' && $aDatoOption['options_values_price'] > 0) {
                $sPriceData = tep_add_tax($aDatoOption['options_values_price'], $nTaxRate);
            }

            $id = $aDatoOption['options_id'].'-'.$aDatoOption['options_values_id'];
            if (!empty($attributes)) {
                if (!in_array($id, $attributes)) {
                    continue;
                }
            }

            $nPrecio = $nPorcentaje * ($sPrecioLastFormat + $sPriceData) / 100;
            $nStock = stock_en_atributos($aDatoOption['options_id'], $aDatoOption['options_values_id'], $products_id);
            if ($nStock == -900) {
                $aDatoOption['products_options_values_name'] .= ' (Sin stock)';
            }

            $html .= '<option data-price="' . $currencies->display_price($nPrecio, 0) . '" data-price-last="' . $currencies->display_price($sPrecioLastFormat + $sPriceData, 0) . '" value="' . $aDatoOption['products_options_values_id'] . '">' . $aDatoOption['products_options_values_name'] . '</option>';
        }

        $html .= '</select>';
        $html .= '<div class="text ship"></div>';
        $html .= '</div>';
    }

    return $html;
}

/**
 * Obtiene el precio de un producto
 *
 * @param integer $products_id
 * @return float
 */
function getPriceFromProductsId(int $products_id): float
{
    global $nCustomerGroupId;

    $sql = 'SELECT IF(s.status, s.specials_new_products_price, p.products_price) as final_price
				 from products p
				 left join specials s on (s.products_id = p.products_id and s.status = 1 and s.customers_group_id = "' . $nCustomerGroupId . '")
				 WHERE p.products_id = ' . $products_id;

    if ($nCustomerGroupId != 0) {
        $sql = 'SELECT IF(s.status, s.specials_new_products_price, pg.customers_group_price) as final_price
                    from products p
                    left join specials s on (s.products_id = p.products_id and s.status = 1 and s.customers_group_id = "' . $nCustomerGroupId . '")
                    left join products_groups pg on (pg.customers_group_id = "' . $nCustomerGroupId . '" and pg.products_id = p.products_id)
                    WHERE p.products_id = ' . $products_id;
    }

    $datos = tep_db_query($sql);

    if (tep_db_num_rows($datos)) {
        $dato = tep_db_fetch_array($datos);
        return (float)$dato['final_price'];
    } else {
        return 0.00;
    }
}

/**
 * Obtiene el precio COMPLETO (sin oferta) de un producto, para el grupo de cliente actual.
 * Igual que getPriceFromProductsId() pero ignorando specials: se usa para calcular el ratio
 * de oferta (eff/full) con el que se escala el modificador de las variantes.
 *
 * @param integer $products_id
 * @return float
 */
function getFullPriceFromProductsId(int $products_id): float
{
    global $nCustomerGroupId;

    $sql = 'SELECT p.products_price as full_price
                 from products p
                 WHERE p.products_id = ' . $products_id;

    if ($nCustomerGroupId != 0) {
        $sql = 'SELECT COALESCE(pg.customers_group_price, p.products_price) as full_price
                    from products p
                    left join products_groups pg on (pg.customers_group_id = "' . $nCustomerGroupId . '" and pg.products_id = p.products_id)
                    WHERE p.products_id = ' . $products_id;
    }

    $datos = tep_db_query($sql);

    if (tep_db_num_rows($datos)) {
        $dato = tep_db_fetch_array($datos);
        return (float)$dato['full_price'];
    } else {
        return 0.00;
    }
}

/**
 *  * UCD-874-74497
 * @return void
 * @author Daniel Lúcia <daniel.lucia@denox.es>
 */
function saveShippingEstimator() {

	global $shipping, $order;

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {

		if (tep_not_null($_POST['sid'])) {

			require_once DIR_WS_CLASSES . 'order.php';
			$order = new order;

			$country_info = tep_get_countries($_POST['country_id'],true);
			$cache_state_prov_values = tep_db_fetch_array(tep_db_query("select zone_code from " . TABLE_ZONES . " where zone_country_id = '" . (int)$_POST['country_id'] . "' and zone_id = '" . (int)$_POST['state'] . "'"));
			$cache_state_prov_code = $cache_state_prov_values['zone_code'];


            if (!tep_session_is_registered('customer_id')) {
                @$order->delivery = array(
                    'postcode' => $_POST['zip_code'],
                    'state' => $cache_state_prov_code,
                    'country' => array('id' => $_POST['country_id'], 'title' => $country_info['countries_name'], 'iso_code_2' => $country_info['countries_iso_code_2'], 'iso_code_3' => $country_info['countries_iso_code_3']),
                    'country_id' => $_POST['country_id'],
                    'zone_id' => $_POST['state'],
                    'format_id' => tep_get_address_format_id($_POST['country_id'])
                );

                $cart_country_id = $_POST['country_id'];
                $cart_zone = $_POST['zone_id'];
                $cart_zip_code = $_POST['zip_code'];

                tep_session_register('cart_country_id');
                tep_session_register('cart_zone');
                tep_session_register('cart_zip_code');
            }

			list($module, $method) = explode('_', $_POST['sid']);

			require_once(DIR_WS_CLASSES . 'shipping.php');
			$shipping_modules = new shipping;
			$quotes = $shipping_modules->quote();

			global $$module;

			if (is_object($$module)) {
				if (!tep_session_is_registered('shipping')) {
					tep_session_register('shipping');
				}

				if (isset($shipping_modules->quote($method, $module)[0]['methods'][0])) {
					$shipping = $_SESSION['shipping'] = $shipping_modules->quote($method, $module)[0]['methods'][0];
				}
			}
		}
	}

	return $shipping;
}
