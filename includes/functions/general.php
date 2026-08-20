<?php
// Tools
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use util\arrays;
use util\tools;

// Devuelve del array de informacion las paginas segun su padre y su grupo
function showInformationPages($aArgumentos = array())
{
    // Variables
    global $aInformationPages;
    $nGrupo = array_key_exists('GRUPO', $aArgumentos) ? $aArgumentos['GRUPO'] : 1;
    $nPadre = array_key_exists('PADRE', $aArgumentos) ? $aArgumentos['PADRE'] : 0;
    $bHtml = array_key_exists('HTML', $aArgumentos) ? $aArgumentos['HTML'] : true;
    $bAll = array_key_exists('ALL', $aArgumentos) ? $aArgumentos['ALL'] : false;
    $bSubPages = array_key_exists('SUBPAGES', $aArgumentos) ? $aArgumentos['SUBPAGES'] : false;
    $aDenegados = array_key_exists('DENEGADOS', $aArgumentos) ? $aArgumentos['DENEGADOS'] : array();
    $aEach = array_key_exists('ARRAY', $aArgumentos) ? $aArgumentos['ARRAY'] : $aInformationPages[$nGrupo];
    $aReturn = array();
    $aReturnAll = array();
    $sHtml = '';
    $sHtmlAll = '';
    $nIndice = 0;

    // Si nos mandan un padre y no nos han mandado un array es que estamos buscando solo por el padre con lo cual buscamos el padre total
		if( $nPadre != 0 && !array_key_exists( 'ARRAY', $aArgumentos ) )
		{
			foreach( $aInformationPages[$nGrupo] as $key => $aPage )
			{
				if( preg_match( '/,' . $nPadre . ',|^' . $nPadre . '|' . $nPadre . '$|^' . $nPadre . '$/i', $aPage['indice'] ) )
				{
					$aEach = array( $aInformationPages[$nGrupo][$key] );
					$nPadre = $aPage['parent_id'];
					break;
				}
			}
		}

		if( count($aEach) > 0 )
		{
			// Recorremos en busca del grupo y del padre
			foreach( $aEach as $aPage )
			{
				// Si coincide el padre y no es denegada
				if( $aPage['parent_id'] == $nPadre && !in_array( $aPage['information_id'], $aDenegados ) )
				{
					// Html en lista
					$sHtml .= '<li><a title="' . $aPage['information_title'] . '" href="' . $aPage['href'] . '">' . $aPage['information_title'] . '</a>';
                $sHtmlAll .= '<a class="inftl col a02 t03 m05 fid-' . $aPage['information_id'] . '" title="' . $aPage['information_title'] . '" href="' . $aPage['href'] . '">' . $aPage['information_title'] . '</a>';

					// Clonamos el array aPage y eliminamos las subpages asi la vamos creando y no añadimos todas del tiron
					$aAux = $aPage;
					unset( $aAux['subpages'] );
					$aReturn[$nIndice] = $aAux;
					$aReturnAll[] = $aAux;

					// Si necesitamos las subpaginas y contiene
					if( $bSubPages && array_key_exists( 'subpages', $aPage ) )
					{
						// Creamos el array de subpages
						$aReturn[$nIndice]['subpages'] = array();

						// Cambios el padre y el array a buscar
						$aArgumentos['ARRAY'] = $aPage['subpages'];
						$aArgumentos['PADRE'] = $aPage['information_id'];

						// Obtenemos las subpages
						$aux = showInformationPages($aArgumentos);

						// Si hemos obtenido algo
						if( (is_array( $aux ) && count($aux) > 0) )
						{
							$aReturn[$nIndice]['subpages'][] = $aux;
							$aReturnAll = array_merge( $aReturnAll, $aux );
						}
						elseif ($aux != '')
						{
							$sHtml .= '<ul>' . $aux . '</ul>';
							$sHtmlAll .= $aux;
						}
					}

					$sHtml .= '</li>';

					// Aumentamos indice
					$nIndice++;
					}
				}
		}

    // Si devolvemos html o el array
    if ($bHtml) {
        return ($bAll ? $sHtmlAll : $sHtml);
    } else {
        return ($bAll ? $aReturnAll : $aReturn);
    }

}

// Devuelve las paginas de informacion a modo de array
function getInformationPages()
{
    // Variables
    global $languages_id;
    $aPaginas = array();
    $aReturn = array();

    // Si no existe la funcion getInformationPagesRecursivo
    if (!function_exists('getInformationPagesRecursivo')) {
        function getInformationPagesRecursivo($aPaginas, $nParent, &$sIndice)
        {
            // Variables
            $aReturn = false;

            // Recorremos los elementos
            foreach ($aPaginas as $aPagina) {
                // Si el padre coincide
                if ($aPagina['parent_id'] == $nParent) {
                    // Enlace
                    $aPagina['href'] = tep_href_link('information.php', 'info_id=' . $aPagina['information_id']);

                    // Vamos creando el indice
                    $sIndice .= $aPagina['information_id'] . ',';

                    // Buscamos si tiene hijos
                    $aSubPaginas = getInformationPagesRecursivo($aPaginas, $aPagina['information_id'], $sIndice);

                    // Si tiene hijos
                    if ($aSubPaginas) {
                        $aPagina['subpages'] = $aSubPaginas;
                    }

                    $aReturn[] = $aPagina;
                }
            }

            // Retornamos
            return $aReturn;
        }
    }

    // Recogemos todas las paginas
    $aDatos = tep_db_query('select information_id, information_title, parent_id, information_group_id
								 from information
								 where visible = 1 and language_id = "' . (int) $languages_id . '"
								 order by sort_order');

    // Recorremos las paginas para guardarlas
    while ($aDato = tep_db_fetch_array($aDatos)) {
        $aPaginas[] = $aDato;
    }

    // Recorremos las paginas
    foreach ($aPaginas as $aPagina) {
        // Indice
        $sIndice = '';

        // Si es padre global
        if ($aPagina['parent_id'] == 0) {
            // Comprobamos si existe el grupo si no existe lo creamos
            if (!array_key_exists($aPagina['information_group_id'], $aReturn)) {
                $aReturn[$aPagina['information_group_id']] = array();
            }

            // Enlace
            $aPagina['href'] = tep_href_link('information.php', 'info_id=' . $aPagina['information_id']);

            // Vamos creando el indice
            $sIndice = $aPagina['information_id'] . ',';

            // Buscamos si tiene hijos
            $aSubPaginas = getInformationPagesRecursivo($aPaginas, $aPagina['information_id'], $sIndice);

            // Si tiene hijos
            if ($aSubPaginas) {
                $aPagina['subpages'] = $aSubPaginas;
            }

            // Guardamos el indice
            $aPagina['indice'] = substr($sIndice, 0, -1);

            $aReturn[$aPagina['information_group_id']][] = $aPagina;
        }
    }

    // Retornamos
    return $aReturn;
}

// Detener script php
// Cuando cambiamos pais o zona los seleccionables
	function ajaxCountryZoneCity()
	{
		// Variables
		$sAction = arrays::getValueByKey( $_POST, 'a', false );

		switch( $sAction )
		{
			case 'getZones':
				// Variables
				$nCountry = (int)arrays::getValueByKey( $_POST, 'country', 0 );
				$aReturn = array();

				// Zonas
				$aReturn['zones'] = getZonesByCountry( array( 'country' => $nCountry, 'name_zone_select' => arrays::getValueByKey( $_POST, 'name_zone_select', 'zone_id' )) );

				// Ciudad
				$aReturn['cities'] = getCitiesByCountryByZone();

				// Json
				echo json_encode( $aReturn );
			break;

			case 'getCities':
				// Variables
				$nCountry = (int)arrays::getValueByKey( $_POST, 'country', 0 );
				$nZone = (int)arrays::getValueByKey( $_POST, 'zone', 0 );

				echo getCitiesByCountryByZone( array( 'country' => $nCountry, 'zone' => $nZone ) );
			break;

			case 'getCp':
				// Variables (postcode-fix 2026-07-09: cp como STRING; el cast (int) perdia el cero inicial
				// y rompia el autocompletado de TODAS las provincias 01-09, p.ej. 03700 Denia)
				$sCp = trim( (string)arrays::getValueByKey( $_POST, 'cp', '' ) );
				$nCountry = (int)arrays::getValueByKey( $_POST, 'country', 0 );
				$aCity = getCityByCP( $sCp, $nCountry );
				$aReturn = array();

				// Pais
				$aReturn['country'] = is_array( $aCity ) && isset( $aCity['id_country'] ) ? $aCity['id_country'] : null;

				// Zonas
				$aReturn['zones'] = isset( $aCity['id_zone'] ) && isset( $aCity['id_country'] ) ? getZonesByCountry( array( 'country' => $aCity['id_country'], 'zone' => $aCity['id_zone'] ) ) : array();

				// Ciudad
				$aReturn['cities'] = isset( $aCity['id_zone'] ) && isset( $aCity['id_country'] ) ? getCitiesByCountryByZone( array( 'country' => $aCity['id_country'], 'zone' => $aCity['id_zone'], 'cp' => $sCp, 'city' => $aCity['id'] ) ) : array();

				// Json
				echo json_encode( $aReturn );
			break;
		}

		// Detenemos
		exit();
	}
function tep_exit()
{
    exit();
}

	// Devuelve el pais por ID
	function getCountrynameById($nCountry)
	{
		$aRow = pharaonix_queryOne( 'SELECT countries_name FROM countries WHERE countries_id = "' . (int)$nCountry . '"' );

		if( $aRow->num_rows > 0 )
			return $aRow->records['countries_name'];

		return '';
	}

	// Devuelve los paises
	function getCountries($aArguments = array())
    {
		// Argumentos
		$sParameters = arrays::getValueByKey( $aArguments, 'parameters', '' );
		$nCountryDefault = arrays::getValueByKey( $aArguments, 'country', '' );

		// Obtenemos las zonas del pais
		$sQuery = 'SELECT countries_id, countries_name FROM countries WHERE countries_status = 1 ORDER BY countries_name ASC';

		// Consulta para crear el array choices
		$aCountries = pharaonix_getArrayAssociativeSql( $sQuery, 'countries_id', 'countries_name', array( array( 'id' => '0', 'text' => PULL_DOWN_COUNTRY ) ) );

		// Retornamos
		return tep_draw_pull_down_menu( 'country', $aCountries, $nCountryDefault, 'data-ajax-country class="select2 not" ' . $sParameters );
	}

	// Devuelve las zonas de un pais
	function getZonesByCountry($aArguments = array())
	{
		// Argumentos
		$nCountry = arrays::getValueByKey( $aArguments, 'country', DEFAULT_COUNTRY );
		$nZoneDefault = arrays::getValueByKey( $aArguments, 'zone', '' );
		$sParameters = arrays::getValueByKey( $aArguments, 'parameters', '' );
        $name_zone_select = arrays::getValueByKey( $aArguments, 'name_zone_select', 'zone_id' );

		// Obtenemos las zonas del pais
		$sQuery = 'SELECT zone_id, zone_name FROM zones WHERE zone_country_id = "' . $nCountry . '" ORDER BY zone_name ASC';

		// Consulta para crear el array choices
		$aZones = pharaonix_getArrayAssociativeSql( $sQuery, 'zone_id', 'zone_name', array( array( 'id' => '0', 'text' => PULL_DOWN_STATE ) ) );

		// Si solo tenemos un registro mostramos un input
		if( count( $aZones ) == 1 )
			return tep_draw_input_field( 'state', '' );

		// Retornamos
		return tep_draw_pull_down_menu( $name_zone_select, $aZones, $nZoneDefault, 'data-ajax-zone class="select2 not"' . $sParameters );
	}

	// Devuelve las ciudades de una zona y un pais
	function getCitiesByCountryByZone($aArguments = array())
    {
		// Argumentos
		$nCountry = arrays::getValueByKey( $aArguments, 'country', false );
		$nZone = arrays::getValueByKey( $aArguments, 'zone', false );
		$nCityDefault = arrays::getValueByKey( $aArguments, 'city', '' );
		$sParameters = arrays::getValueByKey( $aArguments, 'parameters', '' );
		$sCp = arrays::getValueByKey( $aArguments, 'cp', '' );
		$inputMode = arrays::getValueByKey( $aArguments, 'input_mode', false );
		$returnInput = '<input type="text" placeholder="' . SELECT_COUNTRY_CITY_NOT_FOUND_PLACEHOLDER . '" name="city">';

		if ($inputMode !== false) {
			return str_replace('input', 'input name="city" value = "' . $inputMode . '"', $returnInput);
		}

		if ($sCp != ''){
				// postcode-fix 2026-07-09: normalizamos el CP (PT busca por CP4) y escapamos
			$sQuery = 'SELECT id, CONCAT( name, " [", cp, "]" ) AS name, cp, id_zone, id_country FROM cities WHERE cp = "' . tep_db_input( fb_cp_lookup_value( $sCp, (int)$nCountry ) ) . '" AND id_country = "' . (int)$nCountry . '" ORDER BY name';
		}
		else{
			$sQuery = 'SELECT id, CONCAT( name, " [", cp, "]" ) AS name, id_zone FROM cities WHERE id_zone = "' . (int)$nZone . '" AND id_country = "' . (int)$nCountry . '" ORDER BY name';
		}

		// Consulta para crear el array choices
		$aCities = pharaonix_getArrayAssociativeSql( $sQuery, 'id', 'name', array( array( 'id' => '0', 'text' => PULL_DOWN_CITY ), array( 'id' => '-1', 'text' => SELECT_COUNTRY_CITY_NOT_FOUND ) ) );

		// Si no nos envian zona o pais
		if( $nZone == false || $nCountry == false )
			return tep_draw_pull_down_menu( 'city_id', array( array( 'id' => 0, 'text' => SELECT_COUNTRY_ZONE_CITY ) ), '', 'data-ajax-city class="select2 not" ' . $sParameters ) . $returnInput;

		// Si solo tenemos un registro
		if( count( $aCities ) == 1 )
			return tep_draw_input_field( 'city', $nCityDefault ) . $returnInput;
		//Si el pais no tiene ciudades cargadas (hoy ES y PT) forzamos que nos escriban el nombre de la ciudad
		if( !in_array( (int)$nCountry, array( 195, 171 ), true ) )
			return tep_draw_input_field( 'city', $nCityDefault );
		// Retornamos
		return tep_draw_pull_down_menu( 'city_id', $aCities, $nCityDefault, 'data-ajax-city class="select2 not" ' . $sParameters ) . $returnInput;
    }

	// Normaliza un CP de formulario al valor de busqueda en cities.cp:
	// ES = 5 digitos (conservando el cero inicial); PT (171) = los 4 primeros digitos del CP7 (cities guarda CP4)
	if (!function_exists('fb_cp_lookup_value')) {
		function fb_cp_lookup_value($sCp, $nCountry = 0)
		{
			$sCp = trim( (string)$sCp );
			$sDigits = preg_replace( '/[^0-9]/', '', $sCp );
			if( (int)$nCountry === 171 || ( (int)$nCountry === 0 && preg_match( '/^[0-9]{4}\s*-\s*[0-9]{3}$/', $sCp ) ) )
				return substr( $sDigits, 0, 4 );
			return substr( $sDigits, 0, 5 );
		}
	}

	// Devuelve la ciudad mediante un CP
	function getCityByCP($nCp = false, $nCountry = 0)
    {
			// postcode-fix 2026-07-09: cp como string escapado; el (int) anterior perdia el cero inicial (provincias 01-09)
		$sCp = fb_cp_lookup_value( $nCp, (int)$nCountry );
		if( $sCp === '' || $sCp === false )
			return false;
		return pharaonix_queryOne( 'SELECT id, id_country, id_zone FROM cities WHERE cp = "' . tep_db_input( $sCp ) . '"' . ((int)$nCountry === 0 ? '' : ' AND id_country = "' . (int)$nCountry . '"') )->records;
    }

/**
 * Redirige a otra página o sitio con un código de redirección opcional.
 *
 * @param string $url          La URL o ruta a la que se debe redirigir.
 * @param int    $redirectCode El código de redirección HTTP (301 por defecto).
 */
function tep_redirect($url, $redirectCode = 301) {
	// Verifica si la URL es relativa y conviértela en una URL completa
	if (parse_url($url, PHP_URL_SCHEME) === NULL) {
		// Agrega el dominio y el esquema a la URL relativa
		$url = HTTP_SERVER . DIR_WS_HTTP_CATALOG . ltrim($url, '/');
	}

	// Codificar espacios en la URL antes de validar
	$url = str_replace(" ", "%20", $url);

	// Validación de la URL
	if (filter_var($url, FILTER_VALIDATE_URL) === false) {
		// Si la URL no es válida, redirigir a la página por defecto o mostrar un error
		trigger_error('Redirección a URL inválida: ' . htmlspecialchars($url), E_USER_WARNING);
		tep_redirect(tep_href_link(FILENAME_DEFAULT, '', 'NONSSL', false), 302);
		return;
	}

	// Prevenir inyecciones de encabezados
	if (preg_match("/[\n\r]/", $url)) {
		tep_redirect(tep_href_link(FILENAME_DEFAULT, '', 'NONSSL', false), 302);
		return;
	}

	// Manejar redirecciones SSL
	if (ENABLE_SSL && getenv('HTTPS') == 'on') { // Estamos cargando una página SSL
		if (strpos($url, HTTP_SERVER . DIR_WS_HTTP_CATALOG) === 0) { // URL NONSSL
			$url = HTTPS_SERVER . DIR_WS_HTTPS_CATALOG . substr($url, strlen(HTTP_SERVER . DIR_WS_HTTP_CATALOG)); // Cambiarla a SSL
		}
	}

	// Reemplazar &amp; por &
	$url = str_replace('&amp;', '&', $url);

	// Enviar encabezado de redirección con el código especificado
	header('Location: ' . $url, true, $redirectCode);
	exit();
}

////
// Redirect to another page or site
function tep_redirect2($url)
{
    if ((strstr($url, "\n") != false) || (strstr($url, "\r") != false)) {
        tep_redirect(tep_href_link(FILENAME_DEFAULT, '', 'NONSSL', false));
    }

    header("HTTP/1.1 302 Moved Temporarily");
    header("Location: mantenimiento.html");

    tep_exit();
}

////
// Parse the data used in the html tags to ensure the tags will not break
function tep_parse_input_field_data($data, $parse)
{
	return strtr(trim($data ?? ''), $parse);
}


function tep_output_string($string, $translate = false, $protected = false) {
	if (is_null($string)) {
		$string = '';
	}

	if ($protected == true) {
		return htmlspecialchars($string ?? '');
	} else {
		if ($translate == false) {
			return tep_parse_input_field_data($string, ['"' => '&quot;']);
		} else {
			return tep_parse_input_field_data($string, $translate);
		}
	}
}


function tep_output_string_protected($string) {
	return tep_output_string($string, false, true);
}

function tep_sanitize_string($string)
{
    $patterns = array('/ +/', '/[<>]/');
    $replace = array(' ', '_');
    return preg_replace($patterns, $replace, trim((string)($string ?? '')));
}

////
// Return a random row from a database query
function tep_random_select($query)
{
    $random_product = '';
    $random_query = tep_db_query($query);
    $num_rows = tep_db_num_rows($random_query);
    if ($num_rows > 0) {
        $random_row = tep_rand(0, ($num_rows - 1));
        $random_product = tep_db_fetch_array($random_query);
    }

    return $random_product;
}

////
// Return a product's name
// TABLES: products
function tep_get_products_name($product_id, $language = '') {
	global $languages_id;

	if (empty($language)) $language = $languages_id;

	$product_query = tep_db_query("select products_name from " . TABLE_PRODUCTS_DESCRIPTION . " where products_id = '" . (int)$product_id . "' and language_id = '" . (int)$language . "'");
	$product       = tep_db_fetch_array($product_query);

	return $product['products_name'];
}

////
// Return a product's special price (returns nothing if there is no offer)
// TABLES: products
function tep_get_products_special_price($product_id) {
	// BOF Separate Pricing Per Customer
	if (isset($_SESSION['sppc_customer_group_id']) && $_SESSION['sppc_customer_group_id'] != '0') {
		$customer_group_id = $_SESSION['sppc_customer_group_id'];
	} else {
		$customer_group_id = '0';
	}

	$product_query = tep_db_query("select specials_new_products_price from " . TABLE_SPECIALS . " where products_id = '" . (int)$product_id . "' and status = 1 and customers_group_id = '" . (int)$customer_group_id . "'");
	// EOF Separate Pricing Per Customer
	$product = tep_db_fetch_array($product_query);

	return $product ? $product['specials_new_products_price'] : false;
}

////
// Return a product's stock
// TABLES: products
//++++ QT Pro: Begin Changed code
function tep_get_products_stock($products_id, $attributes = array())
{
    // Sampedro: Editor de pedidos, si mandamos por GET dicha variable todos los productos tendran stock 999, asi aunque no tengamos stock podemos realizar checkout_confirmation
    if (array_key_exists('curl_oe', $_GET)) {
        return 999;
    }

    global $languages_id;
    $products_id = tep_get_prid($products_id);
    if (sizeof($attributes) > 0) {
        $all_nonstocked = true;
        $attr_list = '';
        $options_list = implode(",", array_keys($attributes));
        $track_stock_query = tep_db_query("select products_options_id, products_options_track_stock from " . TABLE_PRODUCTS_OPTIONS . " where products_options_id in ($options_list) and language_id= '" . (int) $languages_id . "order by products_options_id'");
        while ($track_stock_array = tep_db_fetch_array($track_stock_query)) {
            if ($track_stock_array['products_options_track_stock']) {
                $attr_list .= $track_stock_array['products_options_id'] . '-' . $attributes[$track_stock_array['products_options_id']] . ',';
                $all_nonstocked = false;
            }
        }
        $attr_list = substr($attr_list, 0, strlen($attr_list) - 1);
    }

    if ((sizeof($attributes) == 0) | ($all_nonstocked)) {
        $stock_query = tep_db_query("select products_quantity as quantity, products_bundle from " . TABLE_PRODUCTS . " where products_id = '" . (int) $products_id . "'");
    } else {
        $stock_query = tep_db_query("select products_stock_quantity as quantity from " . TABLE_PRODUCTS_STOCK . " where products_id='" . (int) $products_id . "' and products_stock_attributes='$attr_list'");
    }
    if (tep_db_num_rows($stock_query) > 0) {
        $stock = tep_db_fetch_array($stock_query);

        if ($stock['products_bundle'] == 'yes') {
            $bundle_query = tep_db_query("select subproduct_id, subproduct_qty from " . TABLE_PRODUCTS_BUNDLES . " where bundle_id = " . (int) $products_id);
            $bundle_stock = array();
            while ($bundle_data = tep_db_fetch_array($bundle_query)) {
                $bundle_stock[] = intval(tep_get_products_stock($bundle_data['subproduct_id']) / $bundle_data['subproduct_qty']);
            }
            $quantity = min($bundle_stock); // return quantity of least plentiful subproduct
        } else {
            $quantity = $stock['quantity'];
        }
    } else {
        $quantity = 0;
    }
    return $quantity;
//++++ QT Pro: End Changed Code
}

////
// Check if the required stock is available
// If insufficent stock is available return an out of stock message
function tep_check_stock($products_id, $products_quantity, $attributes = []) {

	$stock_left   = tep_get_products_stock($products_id, $attributes) - $products_quantity;
	$out_of_stock = false;

	if ($stock_left < 0) {
		$out_of_stock = '<span class="markProductOutOfStock" style="color: red;"><b>' . STOCK_MARK_PRODUCT_OUT_OF_STOCK . ' ' . OUT_OF_STOCK_TEXT . '</b></span>';
	}

	return $out_of_stock;
}

////
// Break a word in a string if it is longer than a specified length ($len)
function tep_break_string($string, $len, $break_char = '-') {
	$l      = 0;
	$output = '';
	for ($i = 0, $n = strlen($string); $i < $n; $i++) {
		$char = substr($string, $i, 1);
		if ($char != ' ') {
			$l++;
		} else {
			$l = 0;
		}
		if ($l > $len) {
			$l      = 1;
			$output .= $break_char;
		}
		$output .= $char;
	}

	return $output;
}

// Return all HTTP post variables, except those passed as a parameter
function tep_post_all_post_params($exclude_array = '') {
	global $_POST;

	if (!is_array($exclude_array)) $exclude_array = [];

	$get_url = '';
	if (is_array($_POST) && (count($_POST) > 0)) {
		foreach ($_POST as $key => $value) {
			if ((strlen($value) > 0) && ($key != tep_session_name()) && ($key != 'error') && (!in_array($key, $exclude_array)) && ($key != 'x') && ($key != 'y'))
				$get_url .= $key . '=' . rawurlencode(stripslashes($value)) . '&';
		}
	}

	return $get_url;
}

////
// Return all HTTP GET variables, except those passed as a parameter
function tep_get_all_get_params($exclude_array = '')
{
    global $_GET;

    if (!is_array($exclude_array)) {
        $exclude_array = array();
    }

    $get_url = '';
    if (is_array($_GET) && (sizeof($_GET) > 0)) {
      foreach($_GET as $key => $value) {
        if ( is_string($value) && (strlen($value) > 0) && ($key != tep_session_name()) && ($key != 'error') && (!in_array($key, $exclude_array)) && ($key != 'x') && ($key != 'y') ) {
          $get_url .= $key . '=' . rawurlencode(stripslashes($value)) . '&';
        }
      }
    }

    return $get_url;
}

// Devuelve todos los parametros de filtros
function tep_get_all_get_params_filter()
{
    $get_url = '';

		if( is_array( $_GET ) && ( sizeof( $_GET ) > 0 ) )
		{
			foreach( $_GET as $key => $value)
			{
				if( is_array( $value ) )
				{
					foreach( $value as $val )
						$get_url .=  '&' . $key . '[]=' . rawurlencode( stripslashes( $val ) );
				}
			}
		}

    return $get_url;
}

////
// Returns an array with countries
// TABLES: countries
function tep_get_countries($countries_id = '', $with_iso_codes = false)
{
    $countries_array = array();
    if (tep_not_null($countries_id)) {
        if ($with_iso_codes == true) {
            $countries = tep_db_query("select countries_name, countries_iso_code_2, countries_iso_code_3 from " . TABLE_COUNTRIES . " where countries_id = '" . (int) $countries_id . "' order by countries_name");
            $countries_values = tep_db_fetch_array($countries);
            $countries_array = array('countries_name' => $countries_values['countries_name'],
                'countries_iso_code_2' => $countries_values['countries_iso_code_2'],
                'countries_iso_code_3' => $countries_values['countries_iso_code_3']);
        } else {
            $countries = tep_db_query("select countries_name from " . TABLE_COUNTRIES . " where countries_id = '" . (int) $countries_id . "'");
            $countries_values = tep_db_fetch_array($countries);
            $countries_array = array('countries_name' => $countries_values['countries_name']);
        }
    } else {
        $countries = tep_db_query("select countries_id, countries_iso_code_2, countries_name from " . TABLE_COUNTRIES . " order by countries_name");
        while ($countries_values = tep_db_fetch_array($countries)) {
            $countries_array[] = array('countries_id' => $countries_values['countries_id'],
                'countries_iso_code_2' => $countries_values['countries_iso_code_2'],
                'countries_name' => $countries_values['countries_name']);
        }
    }

    return $countries_array;
}

////
// Alias function to tep_get_countries, which also returns the countries iso codes
function tep_get_countries_with_iso_codes($countries_id)
{
    return tep_get_countries($countries_id, true);
}

////
// Generate a path to categories
function tep_get_path($current_category_id = '')
{
    global $cPath_array;

    if (tep_not_null($current_category_id) && is_array($cPath_array)) {
        $cp_size = sizeof($cPath_array);
        if ($cp_size == 0) {
            $cPath_new = $current_category_id;
        } else {
            $cPath_new = '';
            $last_category_query = tep_db_query("select parent_id from " . TABLE_CATEGORIES . " where categories_id = '" . (int) $cPath_array[($cp_size - 1)] . "'");
            $last_category = tep_db_fetch_array($last_category_query);

            $current_category_query = tep_db_query("select parent_id from " . TABLE_CATEGORIES . " where categories_id = '" . (int) $current_category_id . "'");
            $current_category = tep_db_fetch_array($current_category_query);

            if ($last_category['parent_id'] == $current_category['parent_id']) {
                for ($i = 0; $i < ($cp_size - 1); $i++) {
                    $cPath_new .= '_' . $cPath_array[$i];
                }
            } else {
                for ($i = 0; $i < $cp_size; $i++) {
                    $cPath_new .= '_' . $cPath_array[$i];
                }
            }
            $cPath_new .= '_' . $current_category_id;

            if (substr($cPath_new, 0, 1) == '_') {
                $cPath_new = substr($cPath_new, 1);
            }
        }
    } else if(is_array($cPath_array)) {
        $cPath_new = implode('_', $cPath_array);
    }

    return 'cPath=' . $cPath_new;
}

////
// Returns the clients browser
function tep_browser_detect($component)
{
    global $HTTP_USER_AGENT;

    return stristr($HTTP_USER_AGENT, $component);
}

////
// Alias function to tep_get_countries()
function tep_get_country_name($country_id)
{
    $country_array = tep_get_countries($country_id);

    return $country_array['countries_name'];
}

////
// Returns the zone (State/Province) name
// TABLES: zones
function tep_get_zone_name($country_id, $zone_id, $default_zone)
{
    $zone_query = tep_db_query("select zone_name from " . TABLE_ZONES . " where zone_country_id = '" . (int) $country_id . "' and zone_id = '" . (int) $zone_id . "'");
    if (tep_db_num_rows($zone_query)) {
        $zone = tep_db_fetch_array($zone_query);
        return $zone['zone_name'];
    } else {
        return $default_zone;
    }
}

////
// Returns the zone (State/Province) code
// TABLES: zones
function tep_get_zone_code($country_id, $zone_id, $default_zone)
{
    $zone_query = tep_db_query("select zone_code from " . TABLE_ZONES . " where zone_country_id = '" . (int) $country_id . "' and zone_id = '" . (int) $zone_id . "'");
    if (tep_db_num_rows($zone_query)) {
        $zone = tep_db_fetch_array($zone_query);
        return $zone['zone_code'];
    } else {
        return $default_zone;
    }
}

////
// Wrapper function for round()
  function tep_round($number, $precision) {
	if( is_array( $number ) )
		  $number = $number[0];

    $number = (string)($number ?? '');
    if (strpos($number, '.') && (strlen(substr($number, strpos($number, '.')+1)) > $precision)) {
      $number = substr($number, 0, strpos($number, '.') + 1 + $precision + 1);

        if (substr($number, -1) >= 5) {
            if ($precision > 1) {
                $number = substr($number, 0, -1) + ('0.' . str_repeat(0, $precision - 1) . '1');
            } elseif ($precision == 1) {
                $number = substr($number, 0, -1) + 0.1;
            } else {
                $number = substr($number, 0, -1) + 1;
            }
        } else {
            $number = substr($number, 0, -1);
        }
    }

    return $number;
}

////
// #FB-IVA-RECOGIDA (2026-06-24): zona fiscal del punto de recogida en tienda.
// En "Recogida en tienda" el lugar de puesta a disposicion es la propia tienda,
// asi que el IVA debe seguir la ubicacion de la tienda y NO la del cliente.
// Tiendas en Canarias (CP 35/38), Ceuta (51) o Melilla (52) => zona exenta
// (IVA 0). Resto (Peninsula y Baleares) => zona peninsular => IVA normal
// (21/10/4 %), aunque el cliente sea de un territorio o pais sin IVA.
// Devuelve ['country_id'=>.., 'zone_id'=>..].
function fb_pickup_store_tax_zone($store_id)
{
    $exentas  = ['35' => 3459, '38' => 3472, '51' => 3447, '52' => 3465];
    $store_id = (int) $store_id;
    if ($store_id > 0) {
        $rs = tep_db_query("select store_address from store where id_store = '" . $store_id . "'");
        if ($rs && ($row = tep_db_fetch_array($rs)) && preg_match('/(\d{5})/', (string) $row['store_address'], $mm)) {
            $prefix = substr($mm[1], 0, 2);
            if (isset($exentas[$prefix])) {
                return ['country_id' => 195, 'zone_id' => $exentas[$prefix]];
            }
        }
    }
    // Por defecto / Peninsula / Baleares => zona peninsular (IVA normal)
    return ['country_id' => (int) STORE_COUNTRY, 'zone_id' => (int) STORE_ZONE];
}

// #FB-IVA-EXPORT (2026-07-02): destinos con IVA propio configurado, que NO deben tratarse como
// exportacion exenta. = UE-27 (por country_id) + Francia metropolitana (74) + Monaco (141, territorio
// IVA frances) + Reino Unido (222, conserva su zona OSS configurada -> mismo trato storefront/admin).
// Fuera de este set, y con un destino REAL conocido (country_id > 0), una entrega es EXPORTACION
// exenta -> IVA 0%. Usado por tep_get_tax_rate y tep_get_tax_description. Guardado con
// function_exists por si el fichero se incluye dos veces.
if (!function_exists('fb_is_eu_vat_country')) {
	function fb_is_eu_vat_country($country_id) {
		static $eu = array(
			14, 21, 33, 53, 55, 56, 57, 67, 72, 73, 74, 81, 84, 97, 103, 105,
			117, 123, 124, 132, 141, 150, 170, 171, 175, 189, 190, 195, 203, 222
		); // AT BE BG HR CY CZ DK EE FI FR FX DE GR HU IE IT LV LT LU MT MC NL PL PT RO SK SI ES SE GB
		return in_array((int) $country_id, $eu, true);
	}
}

// #FB-VIES (2026-07-02): reverse charge intracomunitario. true si el cliente es Profesional con VAT
// validado en VIES (flag de sesion sppc_vies_reverse_charge, fijado en login desde fb_vies_status) Y la
// entrega es a otro pais del area IVA-UE distinto de Espana (195) -> operacion exenta (inversion del
// sujeto pasivo, art. 25 Ley 37/1992) -> IVA 0%.
if (!function_exists('fb_vies_reverse_charge_applies')) {
	function fb_vies_reverse_charge_applies($country_id) {
		return isset($_SESSION['sppc_vies_reverse_charge'])
			&& $_SESSION['sppc_vies_reverse_charge'] === '1'
			&& (int) $country_id !== 195
			&& (int) $country_id !== 222   // UK: fuera del area IVA UE (Brexit) -> es export, no reverse charge
			&& function_exists('fb_is_eu_vat_country')
			&& fb_is_eu_vat_country($country_id);
	}
}

////
// Returns the tax rate for a zone / class
// TABLES: tax_rates, zones_to_geo_zones
function tep_get_tax_rate($class_id, $country_id = -1, $zone_id = -1)
{
    global $customer_zone_id, $customer_country_id, $aTaxCache, $customerCore;
	$taxes = array();

    // BOF Separate Pricing Per Customer, tax exempt modifications
    if (!isset($_SESSION['sppc_customer_group_tax_exempt'])) {
        $customer_group_tax_exempt = '0';
    } else {
        $customer_group_tax_exempt = $_SESSION['sppc_customer_group_tax_exempt'];
    }

	// Excluimos Canarias, Tenerife, Ceuta y Melilla
	if ($zone_id == 3459 || $zone_id == 3472 || $zone_id == 3447 || $zone_id == 3465) {
		$customer_group_tax_exempt = 1;
	}

    if ($customer_group_tax_exempt == '1') {
        return 0;
    }

    if (isset($_SESSION['sppc_customer_specific_taxes_exempt']) && tep_not_null($_SESSION['sppc_customer_specific_taxes_exempt'])) {
        $additional_for_specific_taxes = "AND tax_rates_id NOT IN ( " . $_SESSION['sppc_customer_specific_taxes_exempt'] . " )";
    } else {
        $additional_for_specific_taxes = '';
    }

    // EOF Separate Pricing Per Customer, tax exempt modifications

    if ($country_id == -1 && $zone_id == -1) {
        if (!$customerCore->hasLogin()) {
            $country_id = STORE_COUNTRY;
            $zone_id = STORE_ZONE;
        } else {
            $country_id = $customer_country_id;
            $zone_id = $customer_zone_id;
        }
    }

	if( !is_array( $aTaxCache ) )
		$aTaxCache = array();

	// Si esta en cache retornamos directamente
	if( array_key_exists( $class_id . '_' . $country_id . '_' . $zone_id, $aTaxCache ) )
		return $aTaxCache[$class_id . '_' . $country_id . '_' . $zone_id];

	// #FB-VIES: reverse charge intracomunitario (inversion del sujeto pasivo) -> 0%. Depende del pais de
	// entrega (country_id), por lo que la cache con clave class_country_zone es correcta.
	if (fb_vies_reverse_charge_applies($country_id)) {
		$aTaxCache[$class_id . '_' . $country_id . '_' . $zone_id] = 0;
		return 0;
	}

    // BOF Separate Pricing Per Customer, specific taxes exempt modification
    $tax_query = tep_db_query("select sum(tax_rate) as tax_rate
								from " . TABLE_TAX_RATES . " tr
								left join " . TABLE_ZONES_TO_GEO_ZONES . " za on (tr.tax_zone_id = za.geo_zone_id)
								inner join " . TABLE_GEO_ZONES . " tz on (tz.geo_zone_id = tr.tax_zone_id)
								where (za.zone_country_id is null or za.zone_country_id = '0' or za.zone_country_id = '" . (int) $country_id . "')
									and (za.zone_id is null or za.zone_id = '0' or za.zone_id = '" . (int) $zone_id . "')
									and tr.tax_class_id = '" . (int) $class_id . "' " . $additional_for_specific_taxes . "
								group by tr.tax_priority");
    // EOF Separate Pricing Per Customer, specific taxes exempt modification

	while ($tax = tep_db_fetch_array($tax_query)) {
		if ($tax['tax_rate'] != '') {
			$taxes[] = $tax['tax_rate'];
		}
	}

	if (count($taxes) == 0) {
		// #FB-IVA-EXPORT (2026-07-02): la 1a query no caso ninguna fila OSS. La query fallback de abajo
		// aplica la zona catch-all 31 (IVA 21/10/4% con zone_country_id NULL) a CUALQUIER pais. Correcto
		// para Espana y el resto del area IVA de la UE, pero para un destino REAL fuera de la UE es una
		// entrega de EXPORTACION exenta -> 0%. El "> 0" evita eximir por error cuando el country_id llega
		// 0/desconocido (p.ej. llamadas sin direccion, como el bug de $tax_address en discount_coupon):
		// en ese caso se mantiene el comportamiento anterior (cae al fallback -> tasa de la tienda).
		if ((int) $country_id > 0 && !fb_is_eu_vat_country($country_id)) {
			$aTaxCache[$class_id . '_' . $country_id . '_' . $zone_id] = 0;
			return 0;
		}

		$tax_query = tep_db_query("select sum(tax_rate) as tax_rate
									from " . TABLE_TAX_RATES . " tr
									left join " . TABLE_ZONES_TO_GEO_ZONES . " za on (tr.tax_zone_id = za.geo_zone_id)
									left join " . TABLE_GEO_ZONES . " tz on (tz.geo_zone_id = tr.tax_zone_id)
									where (za.zone_country_id is null or za.zone_country_id = '0' or za.zone_country_id = '" . (int) $country_id . "')
										and (za.zone_id is null or za.zone_id = '0' or za.zone_id = '" . (int) $zone_id . "')
										and tr.tax_class_id = '" . (int) $class_id . "' " . $additional_for_specific_taxes . "
									group by tr.tax_priority");

		while ($tax = tep_db_fetch_array($tax_query)) {
			$taxes[] = $tax['tax_rate'];
		}
	}

    // Guardamos el valor en cache
    $aTaxCache[$class_id . '_' . $country_id . '_' . $zone_id] = 0;

    if (tep_db_num_rows($tax_query) > 0) {
        $tax_multiplier = 1.0;
        foreach ($taxes as $tax) {
            $tax_multiplier *= 1.0 + ($tax / 100);
        }

        $sValor = ($tax_multiplier - 1.0) * 100;

        // Modificamos el valor en cache
        $aTaxCache[$class_id . '_' . $country_id . '_' . $zone_id] = $sValor;

        return $sValor;
    }
    // Si NO hemos obtenido resultados
    else {
		// @Victor.DENOX Debido al ticket #BJU-123-41792 he comentado este bloque para el editor de pedidos, ya que clientes de melilla obtenía IVA
        // Sampedro: Editor de pedidos, si mandamos por GET dicha variable el iva sera el class_id
        /*if (array_key_exists('curl_oe', $_GET)) {
            return $class_id;
        }*/

        return 0;
    }
}

////
// Return the tax description for a zone / class
// TABLES: tax_rates;
function tep_get_tax_description($class_id, $country_id, $zone_id)
{
	$taxes = array();

	// #FB-VIES: reverse charge intracomunitario -> sin descripcion de IVA (la nota legal de inversion del
	// sujeto pasivo se anade en la factura). Coherente con tep_get_tax_rate (0%).
	if (fb_vies_reverse_charge_applies($country_id)) {
		return '';
	}

	// #FB-IVA-EXPORT (2026-07-02): coherente con tep_get_tax_rate. Destino REAL fuera del area IVA-UE
	// = exportacion exenta -> sin descripcion de IVA (evita etiquetar "IVA 21%" en facturas de export).
	if ((int) $country_id > 0 && !fb_is_eu_vat_country($country_id)) {
		return '';
	}

// BOF Separate Pricing Per Customer, specific taxes exempt modification
    if (isset($_SESSION['sppc_customer_specific_taxes_exempt']) && tep_not_null($_SESSION['sppc_customer_specific_taxes_exempt'])) {
        $additional_for_specific_taxes = "AND tax_rates_id NOT IN ( " . $_SESSION['sppc_customer_specific_taxes_exempt'] . " )";
    } else {
        $additional_for_specific_taxes = '';
    }

    $tax_query = tep_db_query("select tax_description from " . TABLE_TAX_RATES . " tr left join " . TABLE_ZONES_TO_GEO_ZONES . " za on (tr.tax_zone_id = za.geo_zone_id) inner join " . TABLE_GEO_ZONES . " tz on (tz.geo_zone_id = tr.tax_zone_id) where (za.zone_country_id is null or za.zone_country_id = '0' or za.zone_country_id = '" . (int) $country_id . "') and (za.zone_id is null or za.zone_id = '0' or za.zone_id = '" . (int) $zone_id . "') and tr.tax_class_id = '" . (int) $class_id . "' " . $additional_for_specific_taxes . " order by tr.tax_priority");

	while ($tax = tep_db_fetch_array($tax_query)) {
		if ($tax['tax_description'] != '') {
			$taxes[] = $tax['tax_description'];
		}
	}

    if (count($taxes) == 0) {
		$tax_query = tep_db_query("select tax_description from " . TABLE_TAX_RATES . " tr left join " . TABLE_ZONES_TO_GEO_ZONES . " za on (tr.tax_zone_id = za.geo_zone_id) left join " . TABLE_GEO_ZONES . " tz on (tz.geo_zone_id = tr.tax_zone_id) where (za.zone_country_id is null or za.zone_country_id = '0' or za.zone_country_id = '" . (int) $country_id . "') and (za.zone_id is null or za.zone_id = '0' or za.zone_id = '" . (int) $zone_id . "') and tr.tax_class_id = '" . (int) $class_id . "' " . $additional_for_specific_taxes . " order by tr.tax_priority");

		while ($tax = tep_db_fetch_array($tax_query)) {
			$taxes[] = $tax['tax_description'];
		}
	}

// EOF Separate Pricing Per Customer, specific taxes exempt modification
    if (tep_db_num_rows($tax_query)) {
        $tax_description = '';
        foreach ($taxes as $tax) {
            $tax_description .= $tax . ' + ';
        }
        $tax_description = substr($tax_description, 0, -3);

        return $tax_description;
    } else {
        return TEXT_UNKNOWN_TAX_RATE;
    }
}

////
// Add tax to a products price
function tep_add_tax($price, $tax) {
	if (!isset($_SESSION['sppc_customer_group_show_tax'])) {
		$customer_group_show_tax = '1';
	} else {
		$customer_group_show_tax = $_SESSION['sppc_customer_group_show_tax'];
	}

	if ((DISPLAY_PRICE_WITH_TAX == 'true') && ($tax > 0) && ($customer_group_show_tax == '1')) {
		return $price + tep_calculate_tax($price, $tax);
	} else {
		return $price;
	}
}

// Calculates Tax rounding the result
function tep_calculate_tax($price, $tax) {
	if (is_numeric($price) && is_numeric($tax))
		return $price * $tax / 100;
	else
		return false;
}

////
// Return the number of products in a category
// TABLES: products, products_to_categories, categories
function tep_count_products_in_category($category_id, $include_inactive = false) {
	// BOF Separate Pricing Per Customer, hide products and categories for groups
	global $sppc_customer_group_id;
	if (!tep_session_is_registered('sppc_customer_group_id')) {
		$customer_group_id = '0';
	} else {
		$customer_group_id = $sppc_customer_group_id;
	}
	$products_count = 0;
	if ($include_inactive == true) {
		$products_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c left join " . TABLE_CATEGORIES . " c using(categories_id) where p.products_id = p2c.products_id and p2c.categories_id = '" . (int)$category_id . "' and find_in_set('" . $customer_group_id . "', products_hide_from_groups) = 0");
	} else {
		$products_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c left join " . TABLE_CATEGORIES . " c using(categories_id) where p.products_id = p2c.products_id and p.products_status = '1' and p2c.categories_id = '" . (int)$category_id . "' and find_in_set('" . $customer_group_id . "', products_hide_from_groups) = 0");
	}
	$products       = tep_db_fetch_array($products_query);
	$products_count += $products['total'];
	// no need to find child categories that are hidden from this customer or have a higher level category that is hidden
	$child_categories_query = tep_db_query("select categories_id from " . TABLE_CATEGORIES . " where parent_id = '" . (int)$category_id . "'");
	// EOF Separate Pricing Per Customer, hide products and categories for groups
	if (tep_db_num_rows($child_categories_query)) {
		while ($child_categories = tep_db_fetch_array($child_categories_query)) {
			$products_count += tep_count_products_in_category($child_categories['categories_id'], $include_inactive);
		}
	}

	return $products_count;
}

////
// Return true if the category has subcategories
// TABLES: categories
function tep_has_category_subcategories($category_id)
{
    $child_category_query = tep_db_query("select count(*) as count from " . TABLE_CATEGORIES . " where parent_id = '" . (int) $category_id . "'");
    $child_category = tep_db_fetch_array($child_category_query);

    if ($child_category['count'] > 0) {
        return true;
    } else {
        return false;
    }
}

function tep_has_specifications($nIdProducto)
{
    global $current_category_id, $languages_id;

    $specifications_query_raw = "select ps.specification,
										  s.filter_display,
										  s.enter_values,
										  sd.specification_name,
										  sd.specification_prefix,
										  sd.specification_suffix
								   from " . TABLE_PRODUCTS_SPECIFICATIONS . " ps,
										" . TABLE_SPECIFICATION . " s,
										" . TABLE_SPECIFICATION_DESCRIPTION . " sd,
										" . TABLE_SPECIFICATION_GROUPS . " sg,
										" . TABLE_SPECIFICATIONS_TO_CATEGORIES . " sg2c
								   where sg.show_products = 'True'
									 and s.show_products = 'True'
									 and s.specification_group_id = sg.specification_group_id
									 and sg.specification_group_id = sg2c.specification_group_id
									 and sd.specifications_id = s.specifications_id
									 and ps.specifications_id = sd.specifications_id
									 and sg2c.categories_id = '" . (int) $current_category_id . "'
									 and ps.products_id = '" . (int) $nIdProducto . "'
									 and sd.language_id = '" . (int) $languages_id . "'
									 and ps.language_id = '" . (int) $languages_id . "'
								   order by s.specification_sort_order,
											sd.specification_name
								 ";
    // print $specifications_query_raw . "<br>\n";
    $specifications_query = tep_db_query($specifications_query_raw);

    $count_specificatons = tep_db_num_rows($specifications_query);

    if ($count_specificatons == 0) {
        return false;
    }

    return true;
}

////
// Returns the address_format_id for the given country
// TABLES: countries;
function tep_get_address_format_id($country_id)
{
    $address_format_query = tep_db_query("select address_format_id as format_id from " . TABLE_COUNTRIES . " where countries_id = '" . (int) $country_id . "'");
    if (tep_db_num_rows($address_format_query)) {
        $address_format = tep_db_fetch_array($address_format_query);
        return $address_format['format_id'];
    } else {
        return '1';
    }
}

////
// Return a formatted address
// TABLES: address_format
function tep_address_format($address_format_id, $address, $html, $boln, $eoln, $bNif = true)
{
    $address_format_query = tep_db_query("select address_format as format from " . TABLE_ADDRESS_FORMAT . " where address_format_id = '" . (int) $address_format_id . "'");
    $address_format = tep_db_fetch_array($address_format_query);
    if (!$address_format) {
        $address_format = ['format' => '$firstname $lastname$cr$street$cr$city $postcode$cr$country'];
    }

    $company = tep_output_string_protected($address['company']);
    $nif = isset( $address['nif'] ) ? tep_output_string_protected($address['nif']) : '';
    if (isset($address['firstname']) && tep_not_null($address['firstname'])) {
        $firstname = tep_output_string_protected($address['firstname']);
        $lastname = tep_output_string_protected($address['lastname']);
    } elseif (isset($address['name']) && tep_not_null($address['name'])) {
        $firstname = tep_output_string_protected($address['name']);
        $lastname = '';
    } else {
        $firstname = '';
        $lastname = '';
    }
    $street = tep_output_string_protected($address['street_address']);
    $suburb = tep_output_string_protected($address['suburb']);
    $city = tep_output_string_protected($address['city']);
    $state = tep_output_string_protected($address['state']);
    $telephone = tep_output_string_protected($address['telephone']);
    if (isset($address['country_id']) && tep_not_null($address['country_id'])) {
        $country = tep_get_country_name($address['country_id']);

        if (isset($address['zone_id']) && tep_not_null($address['zone_id'])) {
            $state = tep_get_zone_code($address['country_id'], $address['zone_id'], $state);
        }
    } elseif (isset($address['country']) && tep_not_null($address['country'])) {
        $country = tep_output_string_protected($address['country']['title']);
    } else {
        $country = '';
    }
    $postcode = tep_output_string_protected($address['postcode']);
    $zip = $postcode;

    if ($html) {
// HTML Mode
        $HR = '<hr />';
        $hr = '<hr />';
        if (($boln == '') && ($eoln == "\n")) { // Values not specified, use rational defaults
            $CR = '<br />';
            $cr = '<br />';
            $eoln = $cr;
        } else { // Use values supplied
            $CR = $eoln . $boln;
            $cr = $CR;
        }
    } else {
// Text Mode
        $CR = $eoln;
        $cr = $CR;
        $HR = '----------------------------------------';
        $hr = '----------------------------------------';
    }

    $statecomma = '';
    $streets = $street;
    if ($suburb != '') {
        $streets = $street . $cr . $suburb;
    }

    if ($state != '') {
        $statecomma = $state . ', ';
    }

    $fmt = $address_format['format'];
    eval("\$address = \"$fmt\";");

    if ((ACCOUNT_COMPANY == 'true') && (tep_not_null($company))) {
        $address = $company . $cr . $address . ($telephone != '' ? $cr : '') . ($bNif ? "N.I.F. " . $nif : '');
    }

    return $address;
}

////
// Return a formatted address
// TABLES: customers, address_book
function tep_address_label($customers_id, $address_id = 1, $html = false, $boln = '', $eoln = "\n")
{
    if (is_array($address_id) && !empty($address_id)) {
        return tep_address_format($address_id['address_format_id'], $address_id, $html, $boln, $eoln);
    }

    $address_query = tep_db_query("select IF(c.name IS NOT NULL, c.name, entry_city) as city, entry_firstname as firstname, entry_lastname as lastname, entry_company as company, entry_street_address as street_address, entry_suburb as suburb, entry_postcode as postcode, entry_state as state, entry_zone_id as zone_id, entry_country_id as country_id, entry_nif as nif, entry_telephone as telephone from " . TABLE_ADDRESS_BOOK . " a LEFT JOIN cities c ON c.id = a.entry_city_id where customers_id = '" . (int) $customers_id . "' and address_book_id = '" . (int) $address_id . "'");

    $address = tep_db_fetch_array($address_query);

    $format_id = tep_get_address_format_id($address['country_id']);

    return tep_address_format($format_id, $address, $html, $boln, $eoln);
}

function tep_row_number_format($number)
{
    if (($number < 10) && (substr($number, 0, 1) != '0')) {
        $number = '0' . $number;
    }

    return $number;
}

function tep_get_categories($categories_array = '', $parent_id = '0', $indent = '')
{
    global $languages_id;

    if (!is_array($categories_array)) {
        $categories_array = array();
    }

    // BOF SPPC Hide categories for groups
    if (isset($_SESSION['sppc_customer_group_id']) && $_SESSION['sppc_customer_group_id'] != '0') {
        $customer_group_id = $_SESSION['sppc_customer_group_id'];
    } else {
        $customer_group_id = '0';
    }

    $categories_query = tep_db_query("select c.categories_id, cd.categories_name from " . TABLE_CATEGORIES . " c, " . TABLE_CATEGORIES_DESCRIPTION . " cd where parent_id = '" . (int) $parent_id . "' and c.categories_id = cd.categories_id and cd.language_id = '" . (int) $languages_id . "' order by sort_order, cd.categories_name");
    // EOF SPPC Hide categories for groups
    while ($categories = tep_db_fetch_array($categories_query)) {
        $categories_array[] = array('id' => $categories['categories_id'],
            'text' => $indent . $categories['categories_name']);

        if ($categories['categories_id'] != $parent_id) {
            $categories_array = tep_get_categories($categories_array, $categories['categories_id'], $indent . '&nbsp;&nbsp;');
        }
    }

    return $categories_array;
}

function tep_get_manufacturers($manufacturers_array = '')
{
    if (!is_array($manufacturers_array)) {
        $manufacturers_array = array();
    }

    $manufacturers_query = tep_db_query("select manufacturers_id, manufacturers_name from " . TABLE_MANUFACTURERS . " order by manufacturers_name");
    while ($manufacturers = tep_db_fetch_array($manufacturers_query)) {
        $manufacturers_array[] = array('id' => $manufacturers['manufacturers_id'], 'text' => $manufacturers['manufacturers_name']);
    }

    return $manufacturers_array;
}

////
// Return all subcategory IDs
// TABLES: categories
function tep_get_subcategories(&$subcategories_array, $parent_id = 0)
{
    $subcategories_query = tep_db_query("select categories_id from " . TABLE_CATEGORIES . " where parent_id = '" . (int) $parent_id . "'");
    while ($subcategories = tep_db_fetch_array($subcategories_query)) {
        $subcategories_array[sizeof($subcategories_array)] = $subcategories['categories_id'];
        if ($subcategories['categories_id'] != $parent_id) {
            tep_get_subcategories($subcategories_array, $subcategories['categories_id']);
        }
    }
}

// Output a raw date string in the selected locale date format
// $raw_date needs to be in this format: YYYY-MM-DD HH:MM:SS
function tep_date_long($raw_date)
{
    if (($raw_date == '0000-00-00 00:00:00') || ($raw_date == '0000-00-00') || ($raw_date == '')) {
        return false;
    }

    $year = (int) substr($raw_date, 0, 4);
    $month = (int) substr($raw_date, 5, 2);
    $day = (int) substr($raw_date, 8, 2);
    $hour = (int) substr($raw_date, 11, 2);
    $minute = (int) substr($raw_date, 14, 2);
    $second = (int) substr($raw_date, 17, 2);
    $aReplaceEn = array('MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY', 'JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER');
    $aReplaceEs = array('Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre');

    return str_ireplace($aReplaceEn, $aReplaceEs, date('d/m/Y', mktime($hour, $minute, $second, $month, $day, $year)));
}

////
// Output a raw date string in the selected locale date format
// $raw_date needs to be in this format: YYYY-MM-DD HH:MM:SS
// NOTE: Includes a workaround for dates before 01/01/1970 that fail on windows servers
function tep_date_short($raw_date)
{
    if (($raw_date == '0000-00-00 00:00:00') || ($raw_date == '0000-00-00') || empty($raw_date)) {
        return false;
    }

    $year = substr($raw_date, 0, 4);
    $month = (int) substr($raw_date, 5, 2);
    $day = (int) substr($raw_date, 8, 2);
    $hour = (int) substr($raw_date, 11, 2);
    $minute = (int) substr($raw_date, 14, 2);
    $second = (int) substr($raw_date, 17, 2);

    if (@date('Y', mktime($hour, $minute, $second, $month, $day, $year)) == $year) {
        return date(DATE_FORMAT, mktime($hour, $minute, $second, $month, $day, $year));
    } else {
        return preg_replace('/2037$/', $year, date(DATE_FORMAT, mktime($hour, $minute, $second, $month, $day, 2037)));
    }
}

////
// Parse search string into indivual objects
function tep_parse_search_string($search_str, &$objects)
{
    $search_str = trim(strtolower($search_str));

// Break up $search_str on whitespace; quoted string will be reconstructed later
    $pieces = preg_split('/[[:space:]]+/', $search_str);
    $objects = array();
    $tmpstring = '';
    $flag = '';

    for ($k = 0; $k < count($pieces); $k++) {
        while (substr($pieces[$k], 0, 1) == '(') {
            $objects[] = '(';
            if (strlen($pieces[$k]) > 1) {
                $pieces[$k] = substr($pieces[$k], 1);
            } else {
                $pieces[$k] = '';
            }
        }

        $post_objects = array();

        while (substr($pieces[$k], -1) == ')') {
            $post_objects[] = ')';
            if (strlen($pieces[$k]) > 1) {
                $pieces[$k] = substr($pieces[$k], 0, -1);
            } else {
                $pieces[$k] = '';
            }
        }

// Check individual words

        if ((substr($pieces[$k], -1) != '"') && (substr($pieces[$k], 0, 1) != '"')) {
            $objects[] = trim($pieces[$k]);

            for ($j = 0; $j < count($post_objects); $j++) {
                $objects[] = $post_objects[$j];
            }
        } else {
/* This means that the $piece is either the beginning or the end of a string.
So, we'll slurp up the $pieces and stick them together until we get to the
end of the string or run out of pieces.
 */

// Add this word to the $tmpstring, starting the $tmpstring
            $tmpstring = trim(preg_replace('/"/', ' ', $pieces[$k]));

// Check for one possible exception to the rule. That there is a single quoted word.
            if (substr($pieces[$k], -1) == '"') {
// Turn the flag off for future iterations
                $flag = 'off';

                $objects[] = trim(preg_replace('/"/', ' ', $pieces[$k]));

                for ($j = 0; $j < count($post_objects); $j++) {
                    $objects[] = $post_objects[$j];
                }

                unset($tmpstring);

// Stop looking for the end of the string and move onto the next word.
                continue;
            }

// Otherwise, turn on the flag to indicate no quotes have been found attached to this word in the string.
            $flag = 'on';

// Move on to the next word
            $k++;

// Keep reading until the end of the string as long as the $flag is on

            while (($flag == 'on') && ($k < count($pieces))) {
                while (substr($pieces[$k], -1) == ')') {
                    $post_objects[] = ')';
                    if (strlen($pieces[$k]) > 1) {
                        $pieces[$k] = substr($pieces[$k], 0, -1);
                    } else {
                        $pieces[$k] = '';
                    }
                }

// If the word doesn't end in double quotes, append it to the $tmpstring.
                if (substr($pieces[$k], -1) != '"') {
// Tack this word onto the current string entity
                    $tmpstring .= ' ' . $pieces[$k];

// Move on to the next word
                    $k++;
                    continue;
                } else {
/* If the $piece ends in double quotes, strip the double quotes, tack the
$piece onto the tail of the string, push the $tmpstring onto the $haves,
kill the $tmpstring, turn the $flag "off", and return.
 */
                    $tmpstring .= ' ' . trim(preg_replace('/"/', ' ', $pieces[$k]));

// Push the $tmpstring onto the array of stuff to search for
                    $objects[] = trim($tmpstring);

                    for ($j = 0; $j < count($post_objects); $j++) {
                        $objects[] = $post_objects[$j];
                    }

                    unset($tmpstring);

// Turn off the flag to exit the loop
                    $flag = 'off';
                }
            }
        }
    }

// add default logical operators if needed
    $temp = array();
    for ($i = 0; $i < (count($objects) - 1); $i++) {
        $temp[] = $objects[$i];
        if (($objects[$i] != 'and') &&
            ($objects[$i] != 'or') &&
            ($objects[$i] != '(') &&
            ($objects[$i + 1] != 'and') &&
            ($objects[$i + 1] != 'or') &&
            ($objects[$i + 1] != ')')) {
            $temp[] = ADVANCED_SEARCH_DEFAULT_OPERATOR;
        }
    }
    $temp[] = $objects[$i];
    $objects = $temp;

    $keyword_count = 0;
    $operator_count = 0;
    $balance = 0;
    for ($i = 0; $i < count($objects); $i++) {
        if ($objects[$i] == '(') {
            $balance--;
        }

        if ($objects[$i] == ')') {
            $balance++;
        }

        if (($objects[$i] == 'and') || ($objects[$i] == 'or')) {
            $operator_count++;
        } elseif (($objects[$i]) && ($objects[$i] != '(') && ($objects[$i] != ')')) {
            $keyword_count++;
        }
    }

    if (($operator_count < $keyword_count) && ($balance == 0)) {
        return true;
    } else {
        return false;
    }
}

////
// Check date
function tep_checkdate($date_to_check, $format_string, &$date_array) {
	$separator_idx = -1;

	$separators = ['-', ' ', '/', '.'];
	$month_abbr = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
	$no_of_days = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

	$format_string = strtolower($format_string);

	if (strlen($date_to_check) != strlen($format_string)) {
		return false;
	}

	$size = count($separators);
	for ($i = 0; $i < $size; $i++) {
		$pos_separator = strpos($date_to_check, $separators[$i]);
		if ($pos_separator != false) {
			$date_separator_idx = $i;
			break;
		}
	}

	for ($i = 0; $i < $size; $i++) {
		$pos_separator = strpos($format_string, $separators[$i]);
		if ($pos_separator != false) {
			$format_separator_idx = $i;
			break;
		}
	}

	if ($date_separator_idx != $format_separator_idx) {
		return false;
	}

	if ($date_separator_idx != -1) {
		$format_string_array = explode($separators[$date_separator_idx], $format_string);
		if (count($format_string_array) != 3) {
			return false;
		}

		$date_to_check_array = explode($separators[$date_separator_idx], $date_to_check);
		if (count($date_to_check_array) != 3) {
			return false;
		}

		$size = count($format_string_array);
		for ($i = 0; $i < $size; $i++) {
			if ($format_string_array[$i] == 'mm' || $format_string_array[$i] == 'mmm') $month = $date_to_check_array[$i];
			if ($format_string_array[$i] == 'dd') $day = $date_to_check_array[$i];
			if (($format_string_array[$i] == 'yyyy') || ($format_string_array[$i] == 'aaaa')) $year = $date_to_check_array[$i];
		}
	} else {
		if (strlen($format_string) == 8 || strlen($format_string) == 9) {
			$pos_month = strpos($format_string, 'mmm');
			if ($pos_month != false) {
				$month = substr($date_to_check, $pos_month, 3);
				$size = count($month_abbr);
				for ($i = 0; $i < $size; $i++) {
					if ($month == $month_abbr[$i]) {
						$month = $i;
						break;
					}
				}
			} else {
				$month = substr($date_to_check, strpos($format_string, 'mm'), 2);
			}
		} else {
			return false;
		}

		$day  = substr($date_to_check, strpos($format_string, 'dd'), 2);
		$year = substr($date_to_check, strpos($format_string, 'yyyy'), 4);
	}

	if (strlen($year) != 4) {
		return false;
	}

	if (!settype($year, 'integer') || !settype($month, 'integer') || !settype($day, 'integer')) {
		return false;
	}

	if ($month > 12 || $month < 1) {
		return false;
	}

	if ($day < 1) {
		return false;
	}

	if (tep_is_leap_year($year)) {
		$no_of_days[1] = 29;
	}

	if ($day > $no_of_days[$month - 1]) {
		return false;
	}

	$date_array = [$year, $month, $day];

	return true;
}

////
// Check if year is a leap year
function tep_is_leap_year($year) {
	if ($year % 100 == 0) {
		if ($year % 400 == 0) return true;
	} else {
		if (($year % 4) == 0) return true;
	}

	return false;
}

////
// Return table heading with sorting capabilities
function tep_create_sort_heading($sortby, $colnum, $heading) {
	global $PHP_SELF;

	$sort_prefix = '';
	$sort_suffix = '';

	if ($sortby) {
		$sort_prefix = '<a href="' . tep_href_link(basename($PHP_SELF), tep_get_all_get_params(['page', 'info', 'sort']) . 'page=1&sort=' . $colnum . ($sortby == $colnum . 'a' ? 'd' : 'a')) . '" title="' . tep_output_string(TEXT_SORT_PRODUCTS . ($sortby == $colnum . 'd' || substr($sortby, 0, 1) != $colnum ? TEXT_ASCENDINGLY : TEXT_DESCENDINGLY) . TEXT_BY . $heading) . '" class="productListing-heading">';
		$sort_suffix = (substr($sortby, 0, 1) == $colnum ? (substr($sortby, 1, 1) == 'a' ? '+' : '-') : '') . '</a>';
	}

	return $sort_prefix . $heading . $sort_suffix;
}

////
// Recursively go through the categories and retreive all parent categories IDs
// TABLES: categories
function tep_get_parent_categories(&$categories, $categories_id) {
	$parent_categories_query = tep_db_query("select parent_id from " . TABLE_CATEGORIES . " where categories_id = '" . (int)$categories_id . "'");
	while ($parent_categories = tep_db_fetch_array($parent_categories_query)) {
		if ($parent_categories['parent_id'] == 0) return true;
		$categories[count($categories)] = $parent_categories['parent_id'];
		if ($parent_categories['parent_id'] != $categories_id) {
			tep_get_parent_categories($categories, $parent_categories['parent_id']);
		}
	}
}

////
// Construct a category path to the product
// TABLES: products_to_categories
function tep_get_product_path($products_id)
{
    $cPath = '';

    $category_query = tep_db_query("select p2c.categories_id from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c where p.products_id = '" . (int) $products_id . "' and p.products_status = '1' and p.products_id = p2c.products_id limit 1");
    if (tep_db_num_rows($category_query)) {
        $category = tep_db_fetch_array($category_query);

        $categories = array();
        tep_get_parent_categories($categories, $category['categories_id']);

        $categories = array_reverse($categories);

        $cPath = implode('_', $categories);

        if (tep_not_null($cPath)) {
            $cPath .= '_';
        }

        $cPath .= $category['categories_id'];
    }

    return $cPath;
}

// Sampedro: Inicio, Atributos por tipo //
// Transforma una cadena tipo IDPRODUCTO{ATRIBUTOS}, en un array option => value de atributos
function tep_get_array_uprid($sText)
{
	global $aOptionsInsertUser;

	$aAux = explode( '{', $sText );
	$aReturn = array();

	foreach( $aAux as $sAux )
	{
		if( strstr($sAux, '}') )
		{
			$sAux = explode( '}', $sAux);

			// Si la opcion es tipo insercción de usuario debemos codificarla para que navege por url
			if( in_array( $sAux[0], $aOptionsInsertUser ) )
				$sAux[1] = urlencode($sAux[1]);

			$aReturn[$sAux[0]] = $sAux[1];
		}
	}

	return $aReturn;
}

////
// Return a product ID with attributes
function tep_get_uprid($prid, $params)
{

    if (is_numeric($prid)) {
        $uprid = $prid;
        if (is_array($params) && (sizeof($params) > 0)) {
            $attributes_check = true;
            $attributes_ids = '';

            foreach( $params as $option => $value ) {
                if (is_numeric($option) && is_numeric($value)) {
                    $attributes_ids .= '{' . (int) $option . '}' . (int) $value;
                } else {
                    $attributes_check = false;
                    break;
                }
            }

            if ($attributes_check == true) {
                $uprid .= $attributes_ids;
            }
        }
    } else {
        $uprid = tep_get_prid($prid);

        if (is_numeric($uprid)) {
            if (strpos($prid, '{') !== false) {
                $attributes_check = true;
                $attributes_ids = '';

// strpos()+1 to remove up to and including the first { which would create an empty array element in explode()
                $attributes = explode('{', substr($prid, strpos($prid, '{') + 1));

                for ($i = 0, $n = sizeof($attributes); $i < $n; $i++) {
                    $pair = explode('}', $attributes[$i]);

                    if (is_numeric($pair[0]) && is_numeric($pair[1])) {
                        $attributes_ids .= '{' . (int) $pair[0] . '}' . (int) $pair[1];
                    } else {
                        $attributes_check = false;
                        break;
                    }
                }

                if ($attributes_check == true) {
                    $uprid .= $attributes_ids;
                }
            }
        } else {
            return false;
        }
    }

    return $uprid;
}

////
// Return a product ID from a product ID with attributes
function tep_get_prid($uprid)
{
    $pieces = explode('{', (string)$uprid);

    if (is_numeric($pieces[0])) {
        return $pieces[0];
    } else {
        return false;
    }
}

////
// Return a customer greeting
function tep_customer_greeting()
{
    global $customer_id, $customer_first_name, $customerCore;

    if (tep_session_is_registered('customer_first_name') && $customerCore->hasLogin()) {
        $greeting_string = sprintf(TEXT_GREETING_PERSONAL, tep_output_string_protected($customer_first_name), tep_href_link(FILENAME_PRODUCTS_NEW));
    } else {
        $greeting_string = sprintf(TEXT_GREETING_GUEST, tep_href_link(FILENAME_LOGIN, '', 'SSL'), tep_href_link(FILENAME_CREATE_ACCOUNT, '', 'SSL'));
    }

    return $greeting_string;
}

/**
 * Function to Send Mail using PHP Mailer
 *
 * @param       $to_name
 * @param       $to_email_address
 * @param       $email_subject
 * @param       $email_text
 * @param       $from_email_name
 * @param       $from_email_address
 * @param false $attachFile
 *
 * @return bool
 * @throws Exception
 */
function tep_mail($to_name, $to_email_address, $email_subject, $email_text, $from_email_name, $from_email_address, $attachFile = false, $bcc = '') {
	// Mail
	$mail = new PHPMailer(true);

	// Comprobamos que sea un mail valido
	if (!$mail->validateAddress($to_email_address)) {
		return false;
	}
	// Decodificamos el json de emails
	$aEmails = defined('STORE_OWNER_EMAIL_ADDRESS_GROUP') ? json_decode(stripslashes(STORE_OWNER_EMAIL_ADDRESS_GROUP), true) : [];

	// Declaramos en false la variable semaforo para enviar el email por smtp o como siempre.
	$bSmtp = false;

	// Variables
	$sEmail    = '';
	$sHost     = '';
	$sPort     = '';
	$sPassword = '';

	if (is_array($aEmails) && count($aEmails) > 0) {
		// Recorremos el array de emails configurados
		foreach ($aEmails as $sUser => $aEmail) {
			$aSecciones = explode(',', $aEmail[2]);

			foreach ($aSecciones as $sSeccion) {
				// Si coincide la sección en la que estamos
				if (preg_match('/' . $sSeccion . '/i', $_SERVER['SCRIPT_NAME'])) {
					// Guardamos los datos del envío smtp
					$sEmail    = $sUser;
					$sHost     = $aEmail[0];
					$sPort     = $aEmail[1];
					$sPassword = tools::decrypt($aEmail[3]);

					// Marcamos para envío smtp
					if ($sEmail != '' && $sHost != '' && $sPort != '' && $sPassword != '')
						$bSmtp = true;

					break;
				}
			}

			if ($bSmtp)
				break;
		}
	}

	// Si enviamos por smtp autentificado
	if ($bSmtp || (SMTP_ACTIVE == 'smtp' && SMTP_HOST != '' && SMTP_PUERTO != '' && SMTP_PASS != '')) {
		$mail->IsSMTP();
		$mail->SMTPAuth = true;
		$mail->Host     = ($bSmtp ? $sHost : SMTP_HOST);
		$mail->Port     = ($bSmtp ? $sPort : SMTP_PUERTO);
		$mail->Username = ($bSmtp ? $sEmail : (defined('SMTP_USER') && SMTP_USER != '' ? SMTP_USER : STORE_OWNER_EMAIL_ADDRESS));
		$mail->Password = ($bSmtp ? $sPassword : tools::decrypt(SMTP_PASS));

		// Ajustar el tipo de cifrado basado en el puerto
		if ($mail->Port == 465) {
			$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
		} else if ($mail->Port == 587) {
			$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
		} else {
			// Si se usa otro puerto, puedes decidir si dejarlo sin cifrado o establecer algún valor por defecto.
			$mail->SMTPSecure = ''; // Sin cifrado (o puedes elegir PHPMailer::ENCRYPTION_STARTTLS como opción por defecto)
		}
		$mail->SMTPDebug = 0; // Nivel de debug (0 = off, 1 = client messages, 2 = client and server messages)
		$mail->setFrom(($bSmtp ? $sEmail : STORE_OWNER_EMAIL_ADDRESS), $from_email_name);
		// PHPMailer lanza Invalid address si el email está vacío (form sin email del usuario)
		if (filter_var((string)$from_email_address, FILTER_VALIDATE_EMAIL)) {
			$mail->AddReplyTo($from_email_address);
		}
		$mail->FromName = $from_email_name;
	} else {
		$mail->Host = "localhost";
		$mail->setFrom($from_email_address, $from_email_name);
	}

	$mail->CharSet = 'utf-8';
	$mail->IsHTML(true);
	$mail->Subject = $email_subject;
	$mail->AddAddress($to_email_address, $to_name);

	// === Override: para destinos internos @francobordo.com, enviar via REST API SendGrid ===
	// con bypass_list_management=true. Razón: el header X-SMTPAPI via SMTP no es respetado
	// por SendGrid SMTP, y el caché interno de bounces drop emails legítimos a internos.
	// La REST API SÍ respeta bypass, asegurando entrega.
	if (stripos($to_email_address, '@francobordo.com') !== false
		&& defined('SMTP_PASS') && defined('STORE_OWNER_EMAIL_ADDRESS')) {
		$_sgKey = \util\tools::decrypt(SMTP_PASS);
		if (strpos($_sgKey, 'SG.') === 0) {
			$_payload = json_encode([
				'personalizations' => [['to' => [['email' => $to_email_address, 'name' => $to_name]]]],
				'from'             => ['email' => STORE_OWNER_EMAIL_ADDRESS, 'name' => $from_email_name ?: STORE_OWNER_EMAIL_ADDRESS],
				'reply_to'         => ['email' => $from_email_address ?: STORE_OWNER_EMAIL_ADDRESS],
				'subject'          => $email_subject,
				'content'          => [['type' => 'text/html', 'value' => $email_text]],
				'mail_settings'    => ['bypass_list_management' => ['enable' => true]],
			]);
			$_ch = curl_init('https://api.sendgrid.com/v3/mail/send');
			curl_setopt_array($_ch, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $_payload,
				CURLOPT_HTTPHEADER     => [
					'Authorization: Bearer ' . $_sgKey,
					'Content-Type: application/json',
				],
				CURLOPT_TIMEOUT        => 15,
			]);
			$_resp = curl_exec($_ch);
			$_code = curl_getinfo($_ch, CURLINFO_HTTP_CODE);
			if ($_code >= 200 && $_code < 300) {
				return true;  // entregado a SendGrid via REST con bypass, listo
			}
			// si falla la REST API, cae al envío SMTP normal abajo (mejor algo que nada)
		}
	}


	if ($attachFile)
		$mail->AddAttachment($attachFile['tmp_name'], $attachFile['name']);

	$mail->Body    = $email_text;
	$mail->AltBody = htmlentities($mail->Body);

	// Trustpilot AFS: BCC opcional, solo si se pasa una direccion valida (lo usa checkout_process.php).
	if ($bcc !== '' && filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
		$mail->AddBCC($bcc);
	}

	try {
		$mail->send();
	} catch (Exception $e) {
		// En caso de error critico se envia un email al sistema de tickets cada 6 horas
		if ($e->getCode() == $mail::STOP_CRITICAL) {
			$smtpErrorDate             = pharaonix_queryOne('SELECT configuration_value FROM configuration WHERE configuration_key = "SMTP_ERROR_DATE"', true);
			$smtpErrorDateLastHour     = $smtpErrorDate->records['configuration_value'] ?? (new DateTime())->sub(new DateInterval("PT6H"))->format('Y-m-d H:i:s');
			$smtpErrorDateSubtractHour = (new DateTime($smtpErrorDateLastHour))->add(new DateInterval("PT6H"))->format('Y-m-d H:i:s');

			if (date('Y-m-d H:i:s') >= $smtpErrorDateSubtractHour) {
				if ($smtpErrorDate->num_rows == 0) {
					$configGroupId = checkConfigurationGroupIdFromTitle('Configurar Emails');

					tep_db_perform('configuration', ['configuration_value' => date('Y-m-d H:i:s'), 'configuration_key' => 'SMTP_ERROR_DATE', 'configuration_group_id' => $configGroupId]);
				} else {
					tep_db_perform('configuration', ['configuration_value' => date('Y-m-d H:i:s')], 'update', 'configuration_key = "SMTP_ERROR_DATE"');
				}

				$mail->Host    = "localhost";
				$mail->CharSet = 'utf-8';
				$mail->IsHTML(true);
				$mail->From     = STORE_OWNER_EMAIL_ADDRESS;
				$mail->FromName = STORE_OWNER;
				$mail->Subject  = "[" . STORE_NAME . "] - Error conexión SMTP";
				$mail->AddAddress('info@denox.es', 'Denox');
				$mail->Body    = STORE_NAME . ", error SMTP con mensaje: <br><br>" . $e->getMessage();
				$mail->AltBody = htmlentities($eMail->Body);
				@$mail->Send();
			}
		}
	}

	return true;
}

function checkConfigurationGroupIdFromTitle($tilte) {
	$query         = tep_db_query("SELECT configuration_group_id FROM configuration_group WHERE configuration_group_title = '.$tilte.'");
	$result        = tep_db_fetch_array($query);
	$configGroupId = $result['configuration_group_id'];

	return $configGroupId;
}

////
// Check if product has attributes
function tep_has_product_attributes($products_id)
{
// BOF Hide attributes from customer groups (SPPC 4.2 and higher)
    global $sppc_customer_group_id;

    if (!tep_session_is_registered('sppc_customer_group_id')) {
        $customer_group_id = '0';
    } else {
        $customer_group_id = $sppc_customer_group_id;
    }
    $attributes_query = tep_db_query("select count(*) as count from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int) $products_id . "' and find_in_set('" . $customer_group_id . "', attributes_hide_from_groups) = 0 ");
// EOF Hide attributes from customer groups (SPPC 4.2 and higher)
    $attributes = tep_db_fetch_array($attributes_query);

    if ($attributes['count'] > 0) {
        return true;
    } else {
        return false;
    }
}

////
// Get the number of times a word/character is present in a string
function tep_word_count($string, $needle) {
	$temp_array = preg_split('/' . $needle . '/', $string);

	return count($temp_array);
}

function tep_count_modules($modules = '') {
	$count = 0;

	if (empty($modules)) return $count;

	$modules_array = explode(';', $modules);

	for ($i = 0, $n = count($modules_array); $i < $n; $i++) {
		$class = substr($modules_array[$i], 0, strrpos($modules_array[$i], '.'));

		if (isset($GLOBALS[$class]) && is_object($GLOBALS[$class])) {
			if ($GLOBALS[$class]->enabled) {
				$count++;
			}
		}
	}

	return $count;
}

function tep_count_payment_modules() {
	return tep_count_modules(MODULE_PAYMENT_INSTALLED);
}

function tep_count_shipping_modules() {
	return tep_count_modules(MODULE_SHIPPING_INSTALLED);
}

function tep_create_random_value($length, $type = 'mixed') {
	if (($type != 'mixed') && ($type != 'chars') && ($type != 'digits')) $type = 'mixed';

	$chars  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$digits = '0123456789';

	$base = '';

	if (($type == 'mixed') || ($type == 'chars')) {
		$base .= $chars;
	}

	if (($type == 'mixed') || ($type == 'digits')) {
		$base .= $digits;
	}

	$value = '';

	if (!class_exists('PasswordHash')) {
		include(DIR_WS_CLASSES . 'passwordhash.php');
	}

	$hasher = new PasswordHash(10, true);

	do {
		$random = base64_encode($hasher->get_random_bytes($length));

		for ($i = 0, $n = strlen($random); $i < $n; $i++) {
			$char = substr($random, $i, 1);

			if (strpos($base, $char) !== false) {
				$value .= $char;
			}
		}
	} while (strlen($value) < $length);

	if (strlen($value) > $length) {
		$value = substr($value, 0, $length);
	}

	return $value;
}

function tep_array_to_string($array, $exclude = '', $equals = '=', $separator = '&')
{
    if (!is_array($exclude)) {
        $exclude = array();
    }

    $get_string = '';
    if (sizeof($array) > 0) {
      foreach($array as $key => $value) {
        if ( (!in_array($key, $exclude)) && ($key != 'x') && ($key != 'y') ) {
          $get_string .= $key . $equals . $value . $separator;
        }
      }
      $remove_chars = strlen($separator);
      $get_string = substr($get_string, 0, -$remove_chars);
    }

    return $get_string;
}

function tep_not_null($value)
{
    if (is_array($value)) {
        if (sizeof($value) > 0) {
            return true;
        } else {
            return false;
        }
    } else {
        if (($value != '') && (strtolower($value) != 'null') && (strlen(trim($value)) > 0)) {
            return true;
        } else {
            return false;
        }
    }
}

////
// Output the tax percentage with optional padded decimals
function tep_display_tax_value($value, $padding = TAX_DECIMAL_PLACES)
{
    if (strpos($value, '.')) {
        $loop = true;
        while ($loop) {
            if (substr($value, -1) == '0') {
                $value = substr($value, 0, -1);
            } else {
                $loop = false;
                if (substr($value, -1) == '.') {
                    $value = substr($value, 0, -1);
                }
            }
        }
    }

    if ($padding > 0) {
        if ($decimal_pos = strpos($value, '.')) {
            $decimals = strlen(substr($value, ($decimal_pos + 1)));
            for ($i = $decimals; $i < $padding; $i++) {
                $value .= '0';
            }
        } else {
            $value .= '.';
            for ($i = 0; $i < $padding; $i++) {
                $value .= '0';
            }
        }
    }

    return $value;
}

////
// Checks to see if the currency code exists as a currency
// TABLES: currencies
function tep_currency_exists($code)
{
    $code = tep_db_prepare_input($code);

    $currency_query = tep_db_query("select code from " . TABLE_CURRENCIES . " where code = '" . tep_db_input($code) . "' limit 1");
    if (tep_db_num_rows($currency_query)) {
        $currency = tep_db_fetch_array($currency_query);
        return $currency['code'];
    } else {
        return false;
    }
}

function tep_string_to_int($string)
{
    return (int) $string;
}

////
// Parse and secure the cPath parameter values
function tep_parse_category_path($cPath)
{
// make sure the category IDs are integers
    $cPath_array = array_map('tep_string_to_int', explode('_', $cPath));

// make sure no duplicate category IDs exist which could lock the server in a loop
    $tmp_array = array();
    $n = sizeof($cPath_array);
    for ($i = 0; $i < $n; $i++) {
        if (!in_array($cPath_array[$i], $tmp_array)) {
            $tmp_array[] = $cPath_array[$i];
        }
    }

    return $tmp_array;
}

////
// Return a random value
function tep_rand($min = null, $max = null)
{
    static $seeded;

    if (!isset($seeded)) {
        $seeded = true;
        if ((PHP_VERSION < '4.2.0')) {
            mt_srand((float) microtime() * 1000000);
        }
    }

    if (isset($min) && isset($max)) {
        if ($min >= $max) {
            return $min;
        } else {
            return mt_rand($min, $max);
        }
    } else {
        return mt_rand();
    }
}

function tep_setcookie($name, $value = '', $expire = 0, $path = '/', $domain = '', $secure = 0)
{
    setcookie($name, $value, $expire, $path, (tep_not_null($domain) ? $domain : ''), $secure);
}

function tep_validate_ip_address($ip_address)
{
    if (function_exists('filter_var') && defined('FILTER_VALIDATE_IP')) {
        return filter_var($ip_address, FILTER_VALIDATE_IP, array('flags' => FILTER_FLAG_IPV4));
    }

    if (preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $ip_address)) {
        $parts = explode('.', $ip_address);

        foreach ($parts as $ip_parts) {
            if ((intval($ip_parts) > 255) || (intval($ip_parts) < 0)) {
                return false; // number is not within 0-255
            }
        }

        return true;
    }

    return false;
}
function tep_get_ip_address()
{
    $ip_address = null;
    $ip_addresses = array();

    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (array_reverse(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])) as $x_ip) {
            $x_ip = trim($x_ip);

            if (tep_validate_ip_address($x_ip)) {
                $ip_addresses[] = $x_ip;
            }
        }
    }

    if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip_addresses[] = $_SERVER['HTTP_CLIENT_IP'];
    }

    if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && !empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
        $ip_addresses[] = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
    }

    if (isset($_SERVER['HTTP_PROXY_USER']) && !empty($_SERVER['HTTP_PROXY_USER'])) {
        $ip_addresses[] = $_SERVER['HTTP_PROXY_USER'];
    }

    $ip_addresses[] = $_SERVER['REMOTE_ADDR'];

    foreach ($ip_addresses as $ip) {
        if (!empty($ip) && tep_validate_ip_address($ip)) {
            $ip_address = $ip;
            break;
        }
    }

    return $ip_address;
}

function tep_count_customer_orders($id = '', $check_session = true, $bPublicFlag = true)
{
    global $customer_id, $languages_id, $customerCore;

    if (is_numeric($id) == false) {
        if ($customerCore->hasLogin()) {
            $id = $customer_id;
        } else {
            return 0;
        }
    }

    if ($check_session == true) {
        if (($customerCore->hasLogin() == false) || ($id != $customer_id)) {
            return 0;
        }
        $whValidate = "";
        if ($check_validate == true) {
            $whValidate = " AND orders_status = '3'";
        }
    }

    $orders_check_query = tep_db_query("select count(*) as total from " . TABLE_ORDERS . " o, " . TABLE_ORDERS_STATUS . " s where o.customers_id = '" . (int) $id . "' and o.orders_status = s.orders_status_id and s.language_id = '" . (int) $languages_id . "'" . ($bPublicFlag ? " and s.public_flag = '1'" : ''));
    $orders_check = tep_db_fetch_array($orders_check_query);

    return $orders_check['total'];
}

function tep_count_customer_address_book_entries($id = '', $check_session = true)
{
    global $customer_id, $customerCore;

    if (is_numeric($id) == false) {
        if ($customerCore->hasLogin()) {
            $id = $customer_id;
        } else {
            return 0;
        }
    }

    if ($check_session == true) {
        if (($customerCore->hasLogin() == false) || ($id != $customer_id)) {
            return 0;
        }
    }

    $addresses_query = tep_db_query("select count(*) as total from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int) $id . "'");
    $addresses = tep_db_fetch_array($addresses_query);

    return $addresses['total'];
}

// nl2br() prior PHP 4.2.0 did not convert linefeeds on all OSs (it only converted \n)
function tep_convert_linefeeds($from, $to, $string)
{
    if ((PHP_VERSION < "4.0.5") && is_array($from)) {
        return preg_replace('/(' . implode('|', $from) . ')/', $to, $string);
    } else {
        return str_replace($from, $to, $string);
    }
}

// BOF SPPC, hide products and categories from groups
function tep_get_hide_status_single($customer_group_id, $pid_for_hide)
{
    $hide_query = tep_db_query("select find_in_set('" . $customer_group_id . "', products_hide_from_groups) as hide_or_not from " . TABLE_PRODUCTS . " p left join " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c using(products_id) left join " . TABLE_CATEGORIES . " c using(categories_id) where p.products_id = '" . $pid_for_hide . "'");
// since a product can be in more than one category (linked products) we have
    // to check for the possibility of more than one row returned
    while ($_hide_product_array = tep_db_fetch_array($hide_query)) {
        $hide_product_array[] = $_hide_product_array;
    }
    if (is_array($hide_product_array)) { // if products_id exists
        foreach ($hide_product_array as $key => $hide_product_sub_array) {
            if ($hide_product_sub_array['hide_or_not'] != '0') {
                $hide_product = true;
            }
// if the product is also present in a category that is not hidden it should be
            // possible to buy it, delete it, get notifications etcetera
            elseif ($hide_product_sub_array['in_hidden_category'] == '0') {
                $hide_product = false;
// no need to continue with foreach
                break;
            } elseif ($hide_product_sub_array['in_hidden_category'] != '0') {
                $hide_product = true;
            }
        } // end  foreach ($hide_product_array as $key => $hide_product_sub_array)
    } else { // if a product_id doesn't exist
        $hide_product = true;
    }
    return $hide_product;
}

function tep_get_hide_status($hide_status_products, $customer_group_id, $temp_post_get_array)
{
    foreach ($temp_post_get_array as $key => $value) {
        $int_products_id = tep_get_prid($value);
// the November 13 updated MS2.2 function tep_get_prid
        // can return false with an invalid products_id
        if ($int_products_id != false) {
            $int_products_id_array[] = $int_products_id;
        }
        $list_of_products_ids = implode(',', $int_products_id_array);
    } // end foreach ($temp_post_get_array as $key => $value)

    $hide_query = tep_db_query("select p.products_id, find_in_set('" . $customer_group_id . "', products_hide_from_groups) as hide_or_not from " . TABLE_PRODUCTS . " p left join " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c using(products_id) left join " . TABLE_CATEGORIES . " c using(categories_id) where p.products_id in (" . $list_of_products_ids . ")");
// since a product can be in more than one category (linked products) we have to check for the
    // possibility of more than one row returned for each products_id where "hide_or_not"
    // is the same for every row, but "in_hidden_category" can be different
    unset($int_products_id_array); // start over
    $int_products_id_array = array();
    if (tep_not_null($hide_status_products)) {
        foreach ($hide_status_products as $key => $subarray) {
            $int_products_id_array[] = $hide_status_products['products_id'];
        }
    } // end if (tep_not_null($hide_status_products))
    while ($hide_products_array = tep_db_fetch_array($hide_query)) {
        $cat_hidden = '1';
        $prod_hidden = '0';
        if ($hide_products_array['hide_or_not'] != '0') {
            $prod_hidden = '1';
        } elseif ($hide_products_array['in_hidden_category'] == '0') {
            $cat_hidden = '0';
        }
        if ($prod_hidden == '0' && $cat_hidden == '0') {
            $hidden = '0';
        } else {
            $hidden = '1';
        }
        if (in_array($hide_products_array['products_id'], $int_products_id_array)) {
            foreach ($hide_status_products as $key => $subarray) {
                if ($subarray['products_id'] == $hide_products_array['products_id']) {
                    if ($subarray['hidden'] == '1' && $subarray['prod_hidden'] == '0' && $cat_hidden == '0') {
// product is not a hidden one and now found to be in a category that is not hidden
                        $hide_status_products[$key]['hidden'] = '0';
                    }
                } // end if ($subarray['products_id'] == $hide_products_array['products_id'])
            } // end foreach ($hide_status_products as $key => $subarray)
        } else {
            $hide_status_products[] = array('products_id' => $hide_products_array['products_id'], 'hidden' => $hidden, 'prod_hidden' => $prod_hidden);
        }
        $int_products_id_array[] = $hide_products_array['products_id'];
    } // end while
    return $hide_status_products;
}
// EOF SPPC, hide products and categories from groups
function tep_array_values_to_string($array, $separator = ',')
{
    $get_string = '';
    if (sizeof($array) > 0) {
	foreach($array as $key => $value) {
            $get_string .= $value . $separator;
        }
        $remove_chars = strlen($separator);
        $get_string = substr($get_string, 0, -$remove_chars);
    }
    return $get_string;
}

// starts canonical tag function
function CanonicalUrl()
{
    global $request_type;

    $domain = substr((($request_type == 'SSL') ? HTTPS_SERVER : HTTP_SERVER), 0); // gets the base URL minus the trailing slash
    $string = $_SERVER['REQUEST_URI']; // gets the url
    $search = '\&osCsid.*|\?osCsid.*'; // searches for the session id in the url
    $replace = ''; // replaces with nothing i.e. deletes
    $newstring = '';

    $str = $string;
    $chars = preg_split('/&/', $str, -1);

    if(isset($chars[1]))
	$newstring = "?".$chars[1];

    if(isset($chars[2]))
	$newstring = $newstring ."&".$chars[2];

    if ($newstring) {
        $string = $domain . preg_replace('/' . $search . '/i', $replace, $string) . $newstring;
    } else {
        $string = $domain . preg_replace('/' . $search . '/i', $replace, $string);
    }

    //Sustituyo cualquier parámetro GET para la Canonical
    $string = preg_replace('/\?.+$/', '', $string);

    return $string;
}

//
//SEO - Paginación de URls
//NO esta activo por un fallo que ocurre en Cosasdeboda
function CanonicalNextPrev()
{
    global $request_type, $aPaginador;

    $nPage = $_GET['page'];

    //Si existe cPath para las Urls Amigables
    if (isset($_GET['cPath'])) {
        $parameters = 'cPath=' . $_GET['cPath'] . '&';
    }

    //Si hay página o es la página de Oferta, Novedades y Opiniones
    if (isset($nPage) || basename($_SERVER['PHP_SELF']) == 'specials.php' || basename($_SERVER['PHP_SELF']) == 'products_new.php' || basename($_SERVER['PHP_SELF']) == 'opiniones.php' || basename($_SERVER['PHP_SELF']) == 'advanced_search_result.php') {

        if ($nPage > 1) {
            echo '<link rel="prev" href="' . tep_href_link(basename($_SERVER['PHP_SELF']), $parameters . $page . 'page=' . ($nPage - 1), $request_type) . '" />' . "\n";
        }

        //Control para el máximo de páginas
        if ($aPaginador->number_of_pages >= ($nPage + 1)) {
            echo '<link rel="next" href="' . tep_href_link(basename($_SERVER['PHP_SELF']), $parameters . $page . 'page=' . ($nPage + 1), $request_type) . '" />' . "\n";
        }

    }

}

function getImagenBannerDestacado($nId, $nIdIdioma, $sTipo)
{
    $aImagenes = scandir(DIR_WS_IMAGES . 'banners_destacados/');

    // Formato actual del admin: {id}_{lang}_{tipo}.{ext}  (p.ej. 151_3_web.png)
    $matches = preg_grep('/^' . $nId . '_' . $nIdIdioma . '_' . preg_quote($sTipo, '/') . '\./i', $aImagenes);

    // Fallback al formato legado del Media Manager para la imagen "web": {id}_{lang}_g_{slug}.{ext}
    if (count($matches) === 0 && $sTipo === 'web') {
        $matches = preg_grep('/^' . $nId . '_' . $nIdIdioma . '_g_/i', $aImagenes);
    }

    if (count($matches) > 0) {
        // Preferir webp (más ligero) y caer en png/jpg si no existe
        $matchesWebp = array_values(array_filter($matches, function($f) { return preg_match('/\.webp$/i', $f); }));
        $matches = count($matchesWebp) > 0 ? $matchesWebp : array_values($matches);
        return DIR_WS_IMAGES . 'banners_destacados/' . $matches[0];
    }

    return false;
}

// Devuelve la imagen de la categoria por idioma si no existe por defecto sera el español
function getImagenCategoria($aImagenes, $sType)
{
    global $languages_id;

    if ($aImagenes == '' || $aImagenes == null) {
        return false;
    }

    if (!is_json($aImagenes)) {
        return $aImagenes;
    }

    $aImagenes = json_decode($aImagenes, true);

    // Si existe la imagen
    if (array_key_exists($sType, $aImagenes) && array_key_exists($languages_id, $aImagenes[$sType]) && $aImagenes[$sType][$languages_id] != '' && file_exists(DIR_WS_IMAGES . 'categorias/' . $aImagenes[$sType][$languages_id])) {
        return $aImagenes[$sType][$languages_id];
    } elseif (array_key_exists($sType, $aImagenes) && array_key_exists(3, $aImagenes[$sType]) && $aImagenes[$sType][3] != '' && file_exists(DIR_WS_IMAGES . 'categorias/' . $aImagenes[$sType][3])) {
        return $aImagenes[$sType][3];
    }

    return false;
}

function cambiarFormatoFecha($fecha)
{
    list($anio, $mes, $dia) = explode("-", $fecha);
    return $dia . "-" . $mes . "-" . $anio;
}

function print_array($array, $exit = false)
{
    print "<pre>";
    print_r($array);
    print "</pre>";
    if ($exit) {
        exit();
    }

}

function tiene_recargo($id = 0)
{
    $recargo_query = tep_db_query("select recargo_equivalencia from " . TABLE_CUSTOMERS . " where customers_id='" . $id . "'");
    //echo "select recargo_equivalencia from ".TABLE_CUSTOMERS." where customers_id='".$id."'";
    $salida = null;
    while ($recargo = tep_db_fetch_array($recargo_query)) {
        $salida = $recargo['recargo_equivalencia'];
    }

    return $salida;
}

// Sampedro: Editor de pedidos, se encarga de modificar las variables necesarias para falsear el checkout
function changeVarsCheckout_oe()
{
    // Variables
    global $shipping, $pfs;

    // Si no contenemos valores del editor de pedidos no hacemos nada
    if (!array_key_exists('data_oe', $_POST)) {
        return false;
    }

    // Pasamos todos los post por tep_db_prepare_input
    array_walk($_POST, function ($value, $key) {global $_POST; $_POST['data_oe'][$key] = tep_db_prepare_input($_POST['data_oe'][$key]);});

    // Order editor
    $aOrderEditor = $_POST['data_oe'];

    // Modificacion del shipping
    $shipping = array('id' => $aOrderEditor['shipping'], 'title' => preg_replace('/( )?(:)?$/i', '', $aOrderEditor['ot_shipping_title']), 'cost' => $aOrderEditor['ot_shipping_price_change']);

    // #FB-IVA-RECOGIDA (2026-06-24): propagamos la tienda de recogida elegida en el
    // editor a la sesion del checkout-curl, para que el IVA siga la ubicacion de la
    // tienda (ver fb_pickup_store_tax_zone / order::cart).
    if (isset($aOrderEditor['store_id'])) {
        $_SESSION['store_id'] = (int) $aOrderEditor['store_id'];
    }

    // Modificacion productos
    foreach ($aOrderEditor['products_oe'] as $aProduct) {
        // Variables
        $nPrice = $aProduct['price'];

        // Si contenemos atributos
        if (is_array($aProduct['attributes'])) {
            foreach ($aProduct['attributes'] as $aAttr) {
                if ($aAttr['prefix'] == '-') {
                    $nPrice -= $aAttr['price'];
                } else {
                    $nPrice += $aAttr['price'];
                }

            }
        }

        // Obtenemos tax
        $aRecords = tep_db_query('SELECT tax_class_id FROM tax_rates WHERE tax_rate = "' . $aProduct['tax'] . '"');

        // Si contenemos datos
        if (tep_db_num_rows($aRecords) > 0) {
            $aRecords = tep_db_fetch_array($aRecords);
            $aProduct['tax'] = $aRecords['tax_class_id'];
        } else {
            // #FB-IVA-RECOGIDA (2026-06-24): si la tasa actual no casa con ninguna
            // (p.ej. 0 por una exencion previa de Canarias/Ceuta/Melilla), recuperamos
            // la clase fiscal real del producto del catalogo para que el recalculo
            // aplique el IVA correcto segun la zona/tienda (recogida en peninsula => 21%).
            $aClass = tep_db_query('SELECT products_tax_class_id FROM products WHERE products_id = "' . (int) $aProduct['products_id'] . '"');
            if (tep_db_num_rows($aClass) > 0) {
                $aClass = tep_db_fetch_array($aClass);
                $aProduct['tax'] = $aClass['products_tax_class_id'];
            }
        }

        // Datos
        $pfs->priceFormatterData[$aProduct['products_id']]['products_name'] = $aProduct['name'];
        $pfs->priceFormatterData[$aProduct['products_id']]['products_model'] = $aProduct['model'];
        $pfs->priceFormatterData[$aProduct['products_id']]['specials_new_products_price'] = false;
        $pfs->priceFormatterData[$aProduct['products_id']]['products_price'] = $nPrice;
        $pfs->priceFormatterData[$aProduct['products_id']]['products_tax_class_id'] = $aProduct['tax'];
    }
}

include $_SERVER['DOCUMENT_ROOT'] . '/' . DIR_WS_FUNCTIONS . 'metatags.php';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/functions/support_functions.php';

function tep_draw_button($title = null, $icon = null, $link = null, $priority = null, $params = null)
{
    static $button_counter = 1;

    $types = array('submit', 'button', 'reset');

    if (!isset($params['type'])) {
        $params['type'] = 'submit';
    }

    if (!in_array($params['type'], $types)) {
        $params['type'] = 'submit';
    }

    if (($params['type'] == 'submit') && isset($link)) {
        $params['type'] = 'button';
    }

    if (!isset($priority)) {
        $priority = 'secondary';
    }
    // modification : attribution d'un id dans le tableau $params
    if (!isset($params['id'])) {
        $idbutton = "tdb" . $button_counter . "";
    } else {
        $idbutton = $params['id'];
        $button_counter -= 1;
    }
    $button = '<span class="tdbLink">';
    if (($params['type'] == 'button') && isset($link)) {
        $button .= '<a id="' . $idbutton . '" href="' . $link . '"';
        if (isset($params['newwindow'])) {
            $button .= ' target="_blank"';
        }
    } else {
        $button .= '<button id="' . $idbutton . '" type="' . tep_output_string($params['type']) . '"';
        if (isset($params['params'])) {
            $button .= ' ' . $params['params'];
        }
    }
    $button .= '>' . $title;

    if (($params['type'] == 'button') && isset($link)) {
        $button .= '</a>';
    } else {
        $button .= '</button>';
    }
    $button .= '</span><script type="text/javascript">$("#' . $idbutton . '").button(';
    $args = array();

    if (isset($icon)) {
        if (!isset($params['iconpos'])) {
            $params['iconpos'] = 'left';
        }

        if ($params['iconpos'] == 'left') {
            $args[] = 'icons:{primary:"ui-icon-' . $icon . '"}';
        } else {
            $args[] = 'icons:{secondary:"ui-icon-' . $icon . '"}';
        }
    }

    if (empty($title)) {
        $args[] = 'text:false';
    }

    // modification : desactivation d'un bouton
    if (isset($params['disabled'])) {
        $args[] = 'disabled: true';
        $activate = 'false';
    }
    if (!empty($args)) {
        $button .= '{' . implode(',', $args) . '}';
    }
    $button .= ').addClass("ui-priority-' . $priority . '").click(function() {return ' . $activate . ';}).parent().removeClass("tdbLink");</script>';
    $button_counter++;

    return $button;
}

// función cálculo del stock por atributo.
function stock_en_atributos($opcion, $valor, $pID)
{
    $sql = "select products_stock_quantity from " . TABLE_PRODUCTS_STOCK . " where products_stock_attributes = '" . $opcion . "-" . $valor . "' and products_id = $pID";
    $act = tep_db_query($sql) or die($sql);
    $val = tep_db_fetch_array($act);
    $val = $val['products_stock_quantity'];
    return $val;
}


// -------------------------------------------------------------------------
// Control de stock POR VARIANTE (2026-07-08).
// products_attributes.check_stock (0/1) anade control de stock a UNA variante
// (se marca en _admin/stock.php, boton "Stock" de la ficha). Semantica OR:
// si products.check_stock (global, ficha de producto) esta activo se controlan
// TODAS las variantes, como siempre; si esta apagado, solo las variantes con
// flag propio a 1.
// -------------------------------------------------------------------------
function fb_variant_check_map($products_id)
{
    static $cache = array();
    $pid = (int)tep_get_prid($products_id);
    if (!isset($cache[$pid])) {
        $map = array();
        $q = tep_db_query("select options_id, options_values_id from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = " . $pid . " and check_stock = 1");
        while ($r = tep_db_fetch_array($q)) {
            $map[(int)$r['options_id'] . '-' . (int)$r['options_values_id']] = 1;
        }
        $cache[$pid] = $map;
    }
    return $cache[$pid];
}

// Flag de control de stock EFECTIVO para una variante concreta.
// $attr: array(oid => ovid), array de arrays con option_id/value_id (formato
//        $order->products[n]['attributes']) o string "oid-ovid[,oid-ovid]".
// $product_check_stock: el flag global si ya lo tienes leido (ahorra 1 query);
//        null = consultarlo aqui.
function fb_variant_check_stock($products_id, $attr, $product_check_stock = null)
{
    $pid = (int)tep_get_prid($products_id);
    if ($product_check_stock === null) {
        $q = tep_db_query("select check_stock from " . TABLE_PRODUCTS . " where products_id = " . $pid);
        $r = tep_db_fetch_array($q);
        $product_check_stock = (int)($r['check_stock'] ?? 0);
    }
    if ((int)$product_check_stock == 1) return 1;

    $map = fb_variant_check_map($pid);
    if (count($map) == 0) return 0;

    $pairs = array();
    if (is_array($attr)) {
        foreach ($attr as $o => $v) {
            if (is_array($v)) {
                if (isset($v['option_id']) && isset($v['value_id']))
                    $pairs[] = (int)$v['option_id'] . '-' . (int)$v['value_id'];
            } else {
                $pairs[] = (int)$o . '-' . (int)$v;
            }
        }
    } elseif (is_string($attr) && $attr !== '') {
        foreach (explode(',', $attr) as $par) {
            $x = explode('-', trim($par));
            if (count($x) == 2) $pairs[] = (int)$x[0] . '-' . (int)$x[1];
        }
    }

    foreach ($pairs as $k) {
        if (isset($map[$k])) return 1;
    }
    return 0;
}

// begin Bundled Products
// returns an array of all non-bundle products in the bundle with their quantities including products contained in nested bundles
function get_all_bundle_products($bundle_id)
{
    $bundle_query = $bundle_query = tep_db_query('select pb.subproduct_id, pb.subproduct_qty, p.products_bundle from ' . TABLE_PRODUCTS_BUNDLES . ' pb, ' . TABLE_PRODUCTS . ' p where p.products_id = pb.subproduct_id and bundle_id = ' . (int) $bundle_id);
    $product_list = array();
    while ($bundle = tep_db_fetch_array($bundle_query)) {
        if ($bundle['products_bundle'] == 'yes') {
            $bundle_list = get_all_bundle_products($bundle['subproduct_id']);
            foreach ($bundle_list as $id => $qty) {
                $product_list[$id] += $qty;
            }
        } else {
            $product_list[$bundle['subproduct_id']] += $bundle['subproduct_qty'];
        }
    }
    return $product_list;
}
// end Bundled Products

////
// Get the attributes info to use in a product listing table or on the products page
function tep_get_products_attributes($products_id, $languages_id, $tax_class_id)
{
    global $currencies;
    $products_attributes_query_raw = "
      select
        count(*) as total
      from
        " . TABLE_PRODUCTS_OPTIONS . " popt,
        " . TABLE_PRODUCTS_ATTRIBUTES . " patrib
      where
        patrib.products_id='" . (int) $products_id . "'
        and patrib.options_id = popt.products_options_id
        and popt.language_id = '" . (int) $languages_id . "'
    ";
    $products_attributes_query = tep_db_query($products_attributes_query_raw);
    $products_attributes = tep_db_fetch_array($products_attributes_query);
    if ($products_attributes['total'] > 0) {
        // There are options
        $output_array = array();
        // Options Types added
        $products_options_name_query_raw = "
        select distinct
          popt.products_options_id,
          popt.products_options_name,
          popt.products_options_type,
          popt.products_options_length,
          popt.products_options_comment
        from
          " . TABLE_PRODUCTS_OPTIONS . " popt
          join " . TABLE_PRODUCTS_ATTRIBUTES . " patrib
            on patrib.options_id = popt.products_options_id
        where
          patrib.products_id='" . (int) $products_id . "'
          and popt.language_id = '" . (int) $languages_id . "'
        order by
          popt.products_options_sort_order,
          popt.products_options_name
      ";
        $products_options_name_query = tep_db_query($products_options_name_query_raw);
        while ($products_options_name = tep_db_fetch_array($products_options_name_query)) {
            // Step through the options and build an array of values
            $products_options_array = array();
            $products_options_query_raw = "
          select
            pov.products_options_values_id,
            pov.products_options_values_name,
            pa.options_values_price,
            pa.price_prefix
          from
            " . TABLE_PRODUCTS_ATTRIBUTES . " pa
            join " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov
              on pov.products_options_values_id = pa.options_values_id
          where
            pa.products_id = '" . (int) $products_id . "'
            and pa.options_id = '" . (int) $products_options_name['products_options_id'] . "'
            and pov.language_id = '" . (int) $languages_id . "'
          order by
            pov.products_options_values_name
        ";
            $products_options_query = tep_db_query($products_options_query_raw);
            while ($products_options = tep_db_fetch_array($products_options_query)) {
                $options_text = $products_options['products_options_values_name'];
                // Add the price to the text if not zero
                if ($products_options['options_values_price'] != '0') {
                    $options_text .= ' (' . $products_options['price_prefix'] . $currencies->display_price($products_options['options_values_price'], tep_get_tax_rate($tax_class_id)) . ') ';
                } // if ($products_options
                // Build the array of values to display (Format is designed for a pulldown)
                $products_options_array[] = array(
                    'id' => $products_options['products_options_values_id'],
                    'text' => $options_text,
                );
            } // while ($products_options

            // Options Types added
            $output_array[] = array(
                'id' => $products_options_name['products_options_id'],
                'name' => $products_options_name['products_options_name'],
                'type' => $products_options_name['products_options_type'],
                'length' => $products_options_name['products_options_length'],
                'comment' => $products_options_name['products_options_comment'],
                'values' => $products_options_array,
            );
        } // while ($products_options_name

        return $output_array;
    } else {
        return false;
    } // if ($products_attributes ... else ....
} // function tep_get_products_attributes

function tep_select_attributes($products_id, $options_array, $languages_id = 1, $tax_class_id = 1, $type = 'pulldown')
{
    global $currencies, $cart;

    $output_string = '';
    switch ($options_array['type']) {
        case 'text':
            $products_attribs_query_raw = "
          select distinct
            options_values_price,
            price_prefix
          from " . TABLE_PRODUCTS_ATTRIBUTES . "
          where products_id='" . (int) $products_id . "'
            and options_id = '" . $options_array['id'] . "'
        ";
            $products_attribs_query = tep_db_query($products_attribs_query_raw);
            $products_attribs_array = tep_db_fetch_array($products_attribs_query);

            $tmp_html = '<input type="text" name ="id[' . $options_array['id'] . ']" style="width:' . (int) $options_array['length'] . 'em"' . ' value="' . $cart->contents[$products_id]['attributes_values'][$options_array['id']] . '"> ';
            if ($products_attribs_array['options_values_price'] != '0') {
                $tmp_html .= '(' . $products_attribs_array['price_prefix'] . $currencies->display_price($products_attribs_array['options_values_price'], $tax_class_id) . ')';
            }

            $output_string .= '          <tr>' . PHP_EOL;
            $output_string .= '            <td class="comparison" align="left">' . $options_array['name'] . ":</td>" . PHP_EOL;
            $output_string .= '            <td class="comparison" align="left">';
            $output_string .= $tmp_html;
            $output_string .= $options_array['comment'];
            $output_string .= '</td>' . PHP_EOL;
            $output_string .= '          </tr>' . PHP_EOL;
            break;

        case 'radio':
        case 'check':
            $products_options_query_raw = "
          select
            pov.products_options_values_id,
            pov.products_options_values_name,
            pa.options_values_price,
            pa.price_prefix
          from " . TABLE_PRODUCTS_ATTRIBUTES . " pa
            join " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov
              on pov.products_options_values_id = pa.options_values_id
          where pa.products_id = '" . (int) $products_id . "'
            and pa.options_id = '" . $options_array['id'] . "'
            and pov.language_id = '" . $languages_id . "'
        ";
            $products_options_query = tep_db_query($products_options_query_raw);

            $tmp_html = '        <table border="0" cellspacing="0" cellpadding="2">' . PHP_EOL;
            while ($products_options_array = tep_db_fetch_array($products_options_query)) {
                $tmp_html .= '          <tr>' . PHP_EOL;
                $tmp_html .= '            <td class="main">';
                if ($options_array['type'] == 'radio') {
                    $tmp_html .= tep_draw_radio_field('id[' . $options_array['id'] . ']', $products_options_array['products_options_values_id']);
                } else {
                    $tmp_html .= tep_draw_checkbox_field('id[' . $options_array['id'] . ']', $products_attribs_array['products_options_values_id']);
                }
                $tmp_html .= $products_options_array['products_options_values_name'];
                $tmp_html .= $options_array['comment'] . '&nbsp;&nbsp;&nbsp;';
                if ($products_options_array['options_values_price'] != '0') {
                    $tmp_html .= '(' . $products_options_array['price_prefix'] . $currencies->display_price($products_options_array['options_values_price'], $tax_class_id) . ')&nbsp';
                }
                $tmp_html .= '</td>' . PHP_EOL;
                $tmp_html .= '          </tr>' . PHP_EOL;
            }
            $tmp_html .= '        </table>' . PHP_EOL;

            $output_string .= '          <tr>' . PHP_EOL;
            $output_string .= '            <td class="comparison" align="left">' . $options_array['name'] . ":</td>" . PHP_EOL;
            $output_string .= '            <td class="comparison" align="left">' . $tmp_html . PHP_EOL;
            $output_string .= '          </tr>' . PHP_EOL;
            break;

        case 'select':
        default:
            $selected_attribute = false;
            if (isset($cart->contents[$products_id]['attributes'][$options_array['id']])) {
                $selected_attribute = $cart->contents[$products_id]['attributes'][$options_array['id']];
            }

            $output_string .= '            <div class="comparison" align="left">' . $options_array['name'] . ":</div>" . PHP_EOL;
            $output_string .= '            <div class="comparison" align="left">';
            $output_string .= tep_draw_pull_down_menu('id[' . $options_array['id'] . ']', $options_array['values'], $selected_attribute);
            $output_string .= $options_array['comment'];
            $output_string .= '</div>' . PHP_EOL;
            break;
    } // switch

    return $output_string;
} // function

function tep_decode_specialchars($string)
{
    $string = str_replace('&gt;', '>', $string);
    $string = str_replace('&lt;', '<', $string);
    $string = str_replace('\'', "'", $string);
    $string = str_replace('&quot;', "\"", $string);
    $string = str_replace('&amp;', '&', $string);

    return $string;
}

function getFabricanteName($id)
{

    $id_fabricante = tep_db_query("select manufacturers_id from products where products_id='" . $id . "'");
    $id_fabricante_value = tep_db_fetch_array($id_fabricante);
    $nombre_fabricante = tep_db_query("select manufacturers_name from manufacturers where manufacturers_id='" . $id_fabricante_value['manufacturers_id'] . "'");
    $nombre_fabricante_value = tep_db_fetch_array($nombre_fabricante);

    return $nombre_fabricante_value['manufacturers_name'];

}

function getFabricanteNameAmazon($id)
{
    $nombre_fabricante = tep_db_query("select manufacturers_name from manufacturers where manufacturers_id='" . $id . "'");
    $nombre_fabricante_value = tep_db_fetch_array($nombre_fabricante);

    return $nombre_fabricante_value['manufacturers_name'];

}

function getImageProduct($nId)
{
    $aId = explode('{', $nId);

    $sImageProduct = tep_db_query('SELECT products_image FROM products WHERE products_id = ' . $aId[0]);
    $aImageProduct = tep_db_fetch_array($sImageProduct);

    return $aImageProduct['products_image'];
}

include $_SERVER['DOCUMENT_ROOT'] . '/includes/functions/refund_functions.php';
