<?php
	// Tools
	use util\tools as tools;
	use util\date as date;

	// Si nos mandan a instalar cambiamos el modulo por login para que forbidden no salte y podamos instalarlo
	if( array_key_exists( 'action', $_GET ) && in_array( $_GET['action'], array( 'install' ) ) )
	{
		// FIX bypass sin auth: PHP_SELF='index.php' (FILENAME_DEFAULT) hace que tep_admin_check_login
		// salte SOLO el ACL de pagina. NO tocar SCRIPT_FILENAME: asi el login SIGUE exigiendose.
		$_SERVER['PHP_SELF'] = 'index.php';
	}

	// Incluimos el application_top
	require_once( 'includes/application_top.php' );
	include( 'includes/modules/related_products/includes/functions/functions.php' );

	// Variables
	$sUrlPage =  'related_products.php';
	$sTitle = 'Productos relacionados';
	$sSubtitle = '';
	$aButtons = array();
	$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);
	$aPriorityByPullDown = array(
		array( 'id' => 1, 'text' => 'Productos - Categorías - Marcas' ),
		array( 'id' => 2, 'text' => 'Productos - Marcas - Categorías' ),
		array( 'id' => 3, 'text' => 'Categorías - Productos - Marcas' ),
		array( 'id' => 4, 'text' => 'Categorías - Marcas - Productos' ),
		array( 'id' => 5, 'text' => 'Marcas - Productos - Categorías' ),
		array( 'id' => 6, 'text' => 'Marcas - Categorías - Productos' )
	);
	$aOrderByPullDown = array(
		array( 'id' => 'products_name', 'text' => 'Título' ),
		array( 'id' => 'products_price', 'text' => 'Precio' )
	);
	$aTogetherPullDown = array(
		array( 'id' => 1, 'text' => 'Mostrar por prioridad' ),
		array( 'id' => 2, 'text' => 'Mezclar resultados' )
	);
	$sGetPage = (isset( $_GET['page'] ) ? tep_db_prepare_input( $_GET['page'] ) : 1);
	$sGetOrderby = (isset( $_GET['orderby'] ) ? tep_db_prepare_input( $_GET['orderby'] ) : 1);
	$sGetSort = (isset( $_GET['sort'] ) ? tep_db_prepare_input( $_GET['sort'] ) : 1);
	$sHtml = '';

	if( !function_exists( 'relatedProductsSafeExplode' ) )
	{
		function relatedProductsSafeExplode( $sDelimiter, $mValue )
		{
			if( !is_scalar( $mValue ) || $mValue === '' )
				return array();

			return array_values( array_filter( array_map( 'trim', explode( $sDelimiter, (string)$mValue ) ), 'strlen' ) );
		}
	}

	# Messagestack estilo
	$messageStack->style = 'solenopsis';

	// Acciones
	switch( $sPostAction )
	{
		case 'readme':
			// Variables
			$sSubtitle = 'Readme de instalación';
			$aButtons = array(
				array( 'title' => 'Ver módulo', 'icon' => 'fa-arrow-right', 'href' => $sUrlPage )
			);

			$sHtml = tools::parsedown( DIR_WS_MODULES . '/related_products/readme.txt' );
		break;

		case 'install':
			// Insertamos admin file
			tools::insertAdminFiles( $sUrlPage, 1 );

			// Insertamos el grupo de configuracion
			$aConfigGroup = tools::insertConfigurationGroup( 'Productos relacionados', 0 );

			// Insertamos la configuracion global
			tools::insertConfiguration( 'Activar productos relacionados', 'RELATED_PRODUCTS_ACTIVE', 'true', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Grupos reciprocos', 'RELATED_PRODUCTS_RECIPROCITY', 'true', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Limite productos a mostrar', 'RELATED_PRODUCTS_LIMIT_SHOW', '12', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Mínimo productos a mostrar', 'RELATED_PRODUCTS_MIN_SHOW', '4', '', $aConfigGroup->records['configuration_group_id'] );
			// tools::insertConfiguration( 'Mostrar slider al comprar', 'RELATED_PRODUCTS_SLIDER_CART', 'true', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Prioridad de relacionados', 'RELATED_PRODUCTS_PRIORITY', '1', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Orden productos relacionados', 'RELATED_PRODUCTS_ORDERBY', 'titulo', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Mezclar resultados o mostrar por prioridad', 'RELATED_PRODUCTS_TOGETHER', '1', '', $aConfigGroup->records['configuration_group_id'] );

			// Reset cache
			tools::createCacheFile();

			// Creamos la tabla de grupos
			tep_db_query( 'CREATE TABLE `related_products_groups` (
				`related_groups_id` int(11) NOT NULL AUTO_INCREMENT,
				`related_title` varchar(50) COLLATE utf8_bin NOT NULL,
				`related_status` tinyint(1) NOT NULL DEFAULT 1,
				`idproducts` varchar(32) DEFAULT NULL,
				`idcategories` varchar(32) DEFAULT NULL,
				`idbrands` varchar(32) DEFAULT NULL,
				PRIMARY KEY (`related_groups_id`),
				KEY `IDX_RELATED_TITLE` (`related_title`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;' );

			tep_db_query( 'CREATE TABLE `related_products_related` (
				`related_related_id` int(11) NOT NULL AUTO_INCREMENT,
				`idproducts` varchar(32) DEFAULT NULL,
				`idcategories` varchar(32) DEFAULT NULL,
				`idbrands` varchar(32) DEFAULT NULL,
				PRIMARY KEY (`related_related_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;' );

			tep_db_query( 'CREATE TABLE `related_groups_to_related` (
				`related_groups_id` int(11) NOT NULL DEFAULT 0,
				`related_related_id` int(11) NOT NULL DEFAULT 0,
				`related_status` tinyint(1) NOT NULL DEFAULT 1,
				PRIMARY KEY (`related_groups_id`,`related_related_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;' );

			// Mensajes
			$messageStack->addSession( 'success', 'El módulo <em>' . $sTitle . '</em> se ha instalado correctamente.', 'success' );

			// Redireccionamos
			tep_redirect( $sUrlPage . '?action=readme' );
		break;

		case 'update_options':
			// Pasamos todos los post por tep_db_prepare_input
			array_walk( $_POST, function( $value, $key){ global $_POST; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); } );

			// Recorremos post en busca de los campos SECURITY para actualizar
			foreach( $_POST as $key => $value )
			{
				// Si es campo RELATED_PRODUCTS_ actualizamos
				if( preg_match( '/^RELATED_PRODUCTS_/', $key ) )
					tep_db_query( 'UPDATE configuration SET configuration_value = "' . $value . '" WHERE configuration_key = "' . $key . '"' );
			}

			// Si nos encontramos en RELATED_PRODUCTS_ACTIVE y no existe en post es que hemos desactivado
			if( preg_match( '/^RELATED_PRODUCTS/', $key ) && !array_key_exists( 'RELATED_PRODUCTS_ACTIVE', $_POST ) )
				tep_db_query( 'UPDATE configuration SET configuration_value = "false" WHERE configuration_key = "RELATED_PRODUCTS_ACTIVE"' );

			// Si nos encontramos en RELATED_PRODUCTS_SLIDER_CART y no existe en post es que hemos desactivado
			// if( preg_match( '/^RELATED_PRODUCTS/', $key ) && !array_key_exists( 'RELATED_PRODUCTS_SLIDER_CART', $_POST ) )
				// tep_db_query( 'UPDATE configuration SET configuration_value = "false" WHERE configuration_key = "RELATED_PRODUCTS_SLIDER_CART"' );

			// Mensajes
			$messageStack->addSession( 'success', 'Los datos del módulo <em>' . $sTitle . '</em> se han actualizado correctamente.', 'success' );

			// Reset cache
			tools::createCacheFile();

			// Redireccionamos
			tep_redirect( $sUrlPage );
		break;

		case 'options':
			// Variables
			$aButtons = array(
				array( 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ),
				array( 'title' => 'Guardar', 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' )
			);
			$sSubtitle = 'Opciones';

			// Formulario
			$sHtml .= '<div class="oeBox column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-shield"></i> Configuración </div>';
					$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update_options' ) . '" class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= '<label for="RELATED_PRODUCTS_ACTIVE" class="column a02 tright inline">Activar productos relacionados:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="checkbox" name="RELATED_PRODUCTS_ACTIVE" id="RELATED_PRODUCTS_ACTIVE" ' . (defined( 'RELATED_PRODUCTS_ACTIVE' ) && RELATED_PRODUCTS_ACTIVE == 'true' ? 'checked="checked"' : '') . ' value="true"/><label for="RELATED_PRODUCTS_ACTIVE"><span></span></label>';
							$sHtml .= '<div class="DFhelp">Activar o desactiva para mostrar productos relacionados.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="RELATED_PRODUCTS_RECIPROCITY" class="column a02 tright inline">Grupos recíprocos:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="checkbox" name="RELATED_PRODUCTS_RECIPROCITY" id="RELATED_PRODUCTS_RECIPROCITY" ' . (defined( 'RELATED_PRODUCTS_RECIPROCITY' ) && RELATED_PRODUCTS_RECIPROCITY == 'true' ? 'checked="checked"' : '') . ' value="true"/><label for="RELATED_PRODUCTS_RECIPROCITY"><span></span></label>';
							$sHtml .= '<div class="DFhelp">Activar o desactiva para que los grupos sean recíprocos (se relacionan entre los propios productos del grupo, solo cuando no estén relacionados).</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="RELATED_PRODUCTS_LIMIT_SHOW" class="column a02 tright">Límite de productos a mostrar:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="RELATED_PRODUCTS_LIMIT_SHOW" id="RELATED_PRODUCTS_LIMIT_SHOW" value="' . (defined( 'RELATED_PRODUCTS_LIMIT_SHOW' ) &&  'RELATED_PRODUCTS_LIMIT_SHOW'  != '' ?  RELATED_PRODUCTS_LIMIT_SHOW  : '') . '"/>';
							$sHtml .= '<div class="DFhelp">Máximo de productos a mostrar (dejar vacío para mostrar todos).</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="RELATED_PRODUCTS_MIN_SHOW" class="column a02 tright">Mínimo de productos a mostrar:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="RELATED_PRODUCTS_MIN_SHOW" id="RELATED_PRODUCTS_MIN_SHOW" value="' . (defined( 'RELATED_PRODUCTS_MIN_SHOW' ) &&  'RELATED_PRODUCTS_MIN_SHOW'  != '' ?  RELATED_PRODUCTS_MIN_SHOW  : '') . '"/>';
							$sHtml .= '<div class="DFhelp">Mínimo de productos a mostrar (dejar vacío si solo se quiere mostrar los resultados obtenidos).</div>';
						$sHtml .= '</div>';

						// $sHtml .= '<div class="xline xline-dashed"></div>';

						// $sHtml .= '<label for="RELATED_PRODUCTS_SLIDER_CART" class="column a02 tright inline">Mostrar slider al comprar:</label>';
						// $sHtml .= '<div class="column a10">';
							// $sHtml .= '<input type="checkbox" name="RELATED_PRODUCTS_SLIDER_CART" id="RELATED_PRODUCTS_SLIDER_CART" ' . (defined( 'RELATED_PRODUCTS_SLIDER_CART' ) && RELATED_PRODUCTS_SLIDER_CART == 'true' ? 'checked="checked"' : '') . ' value="true"/><label for="RELATED_PRODUCTS_SLIDER_CART"><span></span></label>';
							// $sHtml .= '<div class="DFhelp">Posibilidad de mostrar un slider de productos relacionados al añadir un producto al carrito.</div>';
						// $sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="RELATED_PRODUCTS_PRIORITY" class="column a02 tright">Prioridad de los relacioandos:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= tep_draw_pull_down_menu( 'RELATED_PRODUCTS_PRIORITY', $aPriorityByPullDown, RELATED_PRODUCTS_PRIORITY );
							$sHtml .= '<div class="DFhelp">Selecciona la prioridad de obtener los productos relacionados.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="RELATED_PRODUCTS_ORDERBY" class="column a02 tright">Orden de los productos relacionados:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= tep_draw_pull_down_menu( 'RELATED_PRODUCTS_ORDERBY', $aOrderByPullDown, RELATED_PRODUCTS_ORDERBY );
							$sHtml .= '<div class="DFhelp">Selecciona el tipo de ordenamiento de productos relacionados.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="RELATED_PRODUCTS_TOGETHER" class="column a02 tright">Mezclar resultados o mostrar por prioridad:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= tep_draw_pull_down_menu( 'RELATED_PRODUCTS_TOGETHER', $aTogetherPullDown, RELATED_PRODUCTS_TOGETHER );
							$sHtml .= '<div class="DFhelp">Indica si quieres que vayan los relacionados por prioridad ordenando cada bloque indivualmente o si los mezclamos todos ordenandolos por el criterio seleccionado.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<input type="submit" style="display: none;" />';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		break;

		case 'autocomplete':
			// Variables
			$sPostType = tep_db_prepare_input( $_POST['type'] );
			$sPostIds = array_map( 'intval', relatedProductsSafeExplode( ',', $_POST['products'] ?? '' ) );
			$sPostIdsCt = array_map( 'intval', relatedProductsSafeExplode( ',', $_POST['categories'] ?? '' ) );
			$sPostIdsBr = array_map( 'intval', relatedProductsSafeExplode( ',', $_POST['brands'] ?? '' ) );
			$sHtml = '';

			// Buscamos por producto
			if( $sPostType == 'producto' )
				$sSql = getSqlSearchProducts_pi( strtolower( array_key_exists( 'term', $_POST ) ? tep_db_prepare_input( $_POST['term'] ) : '' ), $sPostIds );
			elseif( $sPostType == 'categoria' )
				$sSql = getSqlSearchCategories_pi( strtolower( array_key_exists( 'term', $_POST ) ? tep_db_prepare_input( $_POST['term'] ) : '' ), $sPostIdsCt );
			else
				$sSql = getSqlSearchBrands_pi( strtolower( array_key_exists( 'term', $_POST ) ? tep_db_prepare_input( $_POST['term'] ) : '' ), $sPostIdsBr );

			// Lanzamos la consulta
			$aDatos = tep_db_query( $sSql );

			// Si encontramos productos
			if( tep_db_num_rows( $aDatos ) > 0 )
			{
				while( $aDato = tep_db_fetch_array( $aDatos ) )
				{
					if( $sPostType == 'producto' )
						$sHtml .= '<li data-type="products" data-id="' . $aDato['products_id'] . '">' . $aDato['products_name'] . '</li>';
					elseif( $sPostType == 'categoria' )
					{
						$aAllCategories = getAllCategories();
						$aAux = getCategoriesByParent_pi( $aDato['parent_id'], $aDato['categories_name'], $aAllCategories );

						if( $aAux['text'] == ' => ' . $aDato['categories_name'] )
							$aAux['text'] = $aDato['categories_name'];

						$sHtml .= '<li data-type="categories" data-id="' . $aDato['categories_id'] . '">' . $aAux['text'] . '</li>';
					}
					else
						$sHtml .= '<li data-type="brands" data-id="' . $aDato['manufacturers_id'] . '">' . $aDato['manufacturers_name'] . '</li>';
				}
			}

			// Pintamos
			die( $sHtml != '' ? '<ul>' . $sHtml . '</ul>' : '' );
		break;

		case 'setflag':
            // Variables
			$nId = tep_db_prepare_input( $_GET['id'] );
			$nFlag = tep_db_prepare_input( $_GET['flag'] );

			tep_db_query('UPDATE related_products_groups set related_status = ' . (int)$nFlag . ' WHERE related_groups_id = ' . (int)$nId );

            tep_redirect( $_SERVER['HTTP_REFERER'] );
        break;

		case 'setflagrlt':
            // Variables
			$nIdGr = tep_db_prepare_input( $_GET['idg'] );
			$nIdRl = tep_db_prepare_input( $_GET['idr'] );
			$nFlag = tep_db_prepare_input( $_GET['flag'] );

			tep_db_query('UPDATE related_groups_to_related set related_status = ' . (int)$nFlag . ' WHERE related_groups_id = ' . (int)$nIdGr . ' AND related_related_id = ' . (int)$nIdRl );

            tep_redirect( $_SERVER['HTTP_REFERER'] );
        break;

		case 'delete':
			// Variables
			$aGetId = tep_db_prepare_input( $_GET['id'] );
			$aPostId = tep_db_prepare_input( $_POST['id'] );
			$sIds = '';

			// Si nos envian por get creamos el array
			if( $aGetId != '' )
				$aPostId = array( $aGetId );

			// Recorremos los id
			foreach( $aPostId as $sId )
				$sIds .= $sId . ',';

			// Si tenemos id eliminamos
			if( $sIds != '' )
			{
				// Eliminamos grupo
				tep_db_query( 'DELETE FROM related_products_groups WHERE related_groups_id IN(' . substr( $sIds, 0, -1 ) . ')' );

				// Eliminamos las relaciones que tenga
				tep_db_query( 'DELETE FROM related_groups_to_related WHERE related_groups_id IN(' . substr( $sIds, 0, -1 ) . ')' );
			}

			// Eliminamos los relacionados sin grupos
			tep_db_query( 'DELETE rpr.* FROM related_products_related rpr
						   LEFT JOIN related_groups_to_related rgr ON (rpr.related_related_id = rgr.related_related_id)
						   WHERE rgr.related_related_id IS NULL' );

			// Redireccionamos
			$messageStack->addSession( 'success', 'Los registros se han eliminado correctamente', 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

		case 'delete_related':
			// Variables
			$aGetId = tep_db_prepare_input( $_GET['id'] );
			$aPostId = tep_db_prepare_input( $_POST['id'] );
			$sIds = '';

			// Si nos envian por get creamos el array
			if( $aGetId != '' )
				$aPostId = array( $aGetId );

			// Recorremos los id
			foreach( $aPostId as $sId )
				$sIds .= $sId . ',';

			// Si tenemos id eliminamos
			if( $sIds != '' )
			{
				// Eliminamos relacionados
				tep_db_query( 'DELETE FROM related_products_related WHERE related_related_id IN(' . substr( $sIds, 0, -1 ) . ')' );

				// Eliminamos relacion de grupo con el relacionado borrado
				tep_db_query( 'DELETE FROM related_groups_to_related WHERE related_related_id IN(' . substr( $sIds, 0, -1 ) . ')' );
			}

			// Redireccionamos
			$messageStack->addSession( 'success', 'Los registros se han eliminado correctamente', 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

		case 'update':
		case 'add_form':
			// Javascript y css
			$aJs = array( 'includes/modules/related_products/js/index.js' );
			$aStyle = array( 'includes/modules/related_products/css/style.css' );

			// Variables
			$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
			$aMessageError = array();
			$sSubtitle = ($sGetId != '' ? 'Editar' : 'Crear') . ' Grupo';
			$aButtons = array(
				array( 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ),
				array( 'title' => 'Guardar', 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde saveform-products' )
			);
			$aRecord = array();

			// Si estamos editando
			if( $sGetId != false )
			{
				// Obtenemos el registro
				$aRecord = pharaonix_queryOne( 'SELECT related_title, idproducts, idcategories, idbrands FROM related_products_groups WHERE related_groups_id = "' . (int)$sGetId . '"' );

				// Si no existe
				if( $aRecord->num_rows == 0 )
				{
					$messageStack->addSession( 'success', 'El registro que intentas editar no existe', 'error' );
					tep_redirect( tep_href_link(  $sUrlPage ) );
				}

				// Registro
				$aRecord = $aRecord->records;
			}

			// Insertar o actualizar
			if( $sPostAction == 'update' )
			{
				// Pasamos todos los post por tep_db_prepare_input
				array_walk( $_POST, function( $value, $key){ global $_POST; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); } );

				// Comprobamos
				if( isset($_POST) && !is_array($_POST['ids']) ){
					$_POST['ids'] = array();
				}
				if( !isset($_POST['ids']['products']) || !is_array($_POST['ids']['products']) ){
					$_POST['ids']['products'] = array();
				}
				if( !isset($_POST['ids']['brands']) || !is_array($_POST['ids']['brands']) ){
					$_POST['ids']['brands'] = array();
				}
				if( !isset($_POST['ids']['categories']) || !is_array($_POST['ids']['categories']) ){
					$_POST['ids']['categories'] = array();
				}

				// Comprobamos que nos hayan enviado un título
				if( !array_key_exists( 'titulo', $_POST ) || (array_key_exists( 'titulo', $_POST ) && $_POST['titulo'] == '') )
					$aMessageError['titulo'] = $messageStack->show( array( 'text' => 'Debes especificar un título.', 'class' => 'error' ) );

				// Comprobamos que nos hayan seleccionado algún producto, categoría o marca
				if( !array_key_exists( 'ids', $_POST ) || (array_key_exists( 'ids', $_POST ) && count( $_POST['ids']['products'] ) <= 0 && count( $_POST['ids']['categories'] ) <= 0 && count( $_POST['ids']['brands'] ) <= 0)  )
					$aMessageError['ids'] = $messageStack->show( array( 'text' => 'Debes seleccionar algún producto, categoría o marca para el grupo.', 'class' => 'error' ) );

				// Si no existe errores actualizamos/insertamos
				if( count( $aMessageError ) == 0 )
				{
					$aSql = array(
						'related_title' => $_POST['titulo'],
						'idproducts' => tep_db_prepare_input( implode( ',', $_POST['ids']['products'] ) ),
						'idcategories' => tep_db_prepare_input( implode( ',', $_POST['ids']['categories'] ) ),
						'idbrands' => tep_db_prepare_input( implode( ',', $_POST['ids']['brands'] ) )
					);

					if( $sGetId != false )
						tep_db_perform( 'related_products_groups', $aSql, 'update', 'related_groups_id = "' . (int)$sGetId . '"' );
					else
					{
						tep_db_perform( 'related_products_groups', $aSql );
						$sGetId = tep_db_insert_id();
					}

					// Mensaje
					$messageStack->addSession( 'success', 'El grupo se ha ' . ($sGetId != false ? 'editado' : 'creado') . ' correctamente', 'success' );

					// Redireccionamos
					tep_redirect( tep_href_link(  $sUrlPage ) );
				}
			}

			// Formulario
			$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update' ) . '">';
				$sHtml .= '<div class="oeBox column a12 row ax" style="margin-bottom: 20px;">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-shield"></i> Configuración </div>';
						$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
							$sHtml .= $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : '';
							$sHtml .= '<input type="submit" style="display: none;" />';

							$sHtml .= '<label for="titulo" class="column a01 tright">Título:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="text" name="titulo" id="titulo" value="' . (array_key_exists( 'related_title', $aRecord ) ? $aRecord['related_title'] : (isset( $_POST['titulo'] ) ? $_POST['titulo'] : '')) . '"/>';
								$sHtml .= '<div class="DFhelp">Título del grupo.</div>';
								$sHtml .= array_key_exists( 'titulo', $aMessageError ) ? $aMessageError['titulo'] : '';
							$sHtml .= '</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				// Tabla
				$sHtml .= '<div id="table-products" class="oeBox oeTable xform column a12 row ax" style="display: block;">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> Contenido del grupo</div>';

						$sHtml .= '<div class="column a12 ax row aflex" style="padding: 20px; position: relative;">';
							$sHtml .= '<input name="term" type="text" placeholder="Buscar..." id="autocomplete" class="column" style="border-right: 0px;" />';
							$sHtml .= '<div id="autocomplete-target"></div>';
							$sHtml .= '<div class="column afixed">
								<div data-value-update="true" class="drop xfselect">
									<div id="autocomplete-type"><span data-type="producto">Por producto</span></div>
									<ul class="down">
										<li><a href="javascript:void(0);" class="hv"><span data-type="producto">Por producto</span></a></li>
										<li><a href="javascript:void(0);" class="hv"><span data-type="categoria">Por categoria</span></a></li>
										<li><a href="javascript:void(0);" class="hv"><span data-type="marca">Por marcas</span></a></li>
									</ul>
								</div></div>';
						$sHtml .= '</div>';

						$sHtml .= (array_key_exists( 'ids', $aMessageError ) ? $aMessageError['ids'] : '');

						$sHtml .= '<table class="xform">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
									$sHtml .= '<th>Nombre</th>';
									$sHtml .= '<th>Tipo</th>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';
								// Si tenemos productos en el grupo o si hemos enviado productos por el formulario
								if( (array_key_exists( 'idproducts', $aRecord ) && $aRecord['idproducts'] != '') || (isset( $_POST['ids']['products'] ) && count( $_POST['ids']['products'] ) > 0) )
								{
									$aAllProducts = getAllProducts();

									// Si el grupo tiene ya productos asignados
									if( array_key_exists( 'idproducts', $aRecord ) && $aRecord['idproducts'] != '' )
										$aIdsProduct = relatedProductsSafeExplode( ',', $aRecord['idproducts'] );
									// Si hemos enviado productos
									elseif( isset( $_POST['ids']['products'] ) )
										$aIdsProduct = tep_db_prepare_input($_POST['ids']['products']);

									foreach( $aIdsProduct as $nIdProduct )
									{
										$sHtml .= '<tr>';
											$sHtml .= '<td width="17" class="chck">';
												$sHtml .= '<input class="idsproducto" value="' . $nIdProduct . '" name="ids[products][]" type="checkbox" id="check_' . $nIdProduct . '"/>';
												$sHtml .= '<label for="check_' . $nIdProduct . '"><span></span></label>';
											$sHtml .= '</td>';
											$sHtml .= '<td>' . $aAllProducts[$nIdProduct]['products_name'] . '</td>';
											$sHtml .= '<td>producto</td>';
										$sHtml .= '</tr>';
									}
								}

								// Si tenemos categorias en el grupo o si hemos enviado categorias por el formulario
								if( (array_key_exists( 'idcategories', $aRecord ) && $aRecord['idcategories'] != '') || (isset( $_POST['ids']['categories'] ) && count( $_POST['ids']['categories'] ) > 0) )
								{
									$aAllCategories = getAllCategories();

									// Si el grupo tiene ya categorias asignados
									if( array_key_exists( 'idcategories', $aRecord ) && $aRecord['idcategories'] != '' )
										$aIdsCategories = relatedProductsSafeExplode( ',', $aRecord['idcategories'] );
									// Si hemos enviado categorias
									elseif( isset( $_POST['ids']['categories'] ) )
										$aIdsCategories = tep_db_prepare_input($_POST['ids']['categories']);

									foreach( $aIdsCategories as $nIdCategory )
									{
										$aAux = getCategoriesByParent_pi( $aAllCategories[$nIdCategory]['parent_id'], $aAllCategories[$nIdCategory]['categories_name'], $aAllCategories );

										if( $aAux['text'] == ' => ' . $aAllCategories[$nIdCategory]['categories_name'] )
											$aAux['text'] = $aAllCategories[$nIdCategory]['categories_name'];

										$sHtml .= '<tr>';
											$sHtml .= '<td width="17" class="chck">';
												$sHtml .= '<input class="idscategoria" value="' . $nIdCategory . '" name="ids[categories][]" type="checkbox" id="check_' . $nIdCategory . '"/>';
												$sHtml .= '<label for="check_' . $nIdCategory . '"><span></span></label>';
											$sHtml .= '</td>';
											$sHtml .= '<td>' . $aAux['text'] . '</td>';
											$sHtml .= '<td>categoria</td>';
										$sHtml .= '</tr>';
									}
								}

								// Si tenemos marcas en el grupo o si hemos enviado marcas por el formulario
								if( (array_key_exists( 'idbrands', $aRecord ) && $aRecord['idbrands'] != '') || (isset( $_POST['ids']['brands'] ) && count( $_POST['ids']['brands'] ) > 0) )
								{
									$aAllBrands = getAllBrands();

									// Si el grupo tiene ya productos asignados
									if( array_key_exists( 'idbrands', $aRecord ) && $aRecord['idbrands'] != '' )
										$aIdsBrands = relatedProductsSafeExplode( ',', $aRecord['idbrands'] );
									// Si hemos enviado productos
									elseif( isset( $_POST['ids']['brands'] ) )
										$aIdsBrands = tep_db_prepare_input($_POST['ids']['brands']);

									foreach( $aIdsBrands as $nIdBrand )
									{
										$sHtml .= '<tr>';
											$sHtml .= '<td width="17" class="chck">';
												$sHtml .= '<input class="idsmarca" value="' . $nIdBrand . '" name="ids[brands][]" type="checkbox" id="check_' . $nIdBrand . '"/>';
												$sHtml .= '<label for="check_' . $nIdBrand . '"><span></span></label>';
											$sHtml .= '</td>';
											$sHtml .= '<td>' . $aAllBrands[$nIdBrand]['manufacturers_name'] . '</td>';
											$sHtml .= '<td>marca</td>';
										$sHtml .= '</tr>';
									}
								}
							$sHtml .= '</tbody>';
						$sHtml .= '</table>';
						$sHtml .= '<div class="column a12 ax row xform oeTableBottom amiddle">';
							$sHtml .= '<div class="column a06 ax row aflex amiddle">';
								$sHtml .= '<label class="column afluid">Aplicar acción:&nbsp;&nbsp;</label>';
								$sHtml .= '<div class="column afluid">';
									$sHtml .= '<div class="drop xfselect">';
										$sHtml .= '<div>Acciones</div>';
										$sHtml .= '<ul class="down drch">';
											$sHtml .= '<li><a id="delete-products" href="javascript:void(0);" class="hv"><i class="fa fa-trash-o"></i>Eliminar registros</a></li>';
										$sHtml .= '</ul>';
									$sHtml .= '</div>';
								$sHtml .= '</div>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</form>';
		break;

		case 'update_rlt':
		case 'add_rlt':
			// Javascript y css
			$aJs = array( 'includes/modules/related_products/js/index.js', 'includes/modules/related_products/js/select2.js' );
			$aStyle = array( 'includes/modules/related_products/css/style.css', 'includes/modules/related_products/css/select2.css' );

			// Variables
			$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
			$aGroupsRelated = array();
			$aMessageError = array();
			$sSubtitle = ($sGetId != '' ? 'Editar' : 'Crear') . ' Relacionados';
			$aButtons = array(
				array( 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage, 'action=related' ), 'icon' => 'fa-arrow-left' ),
				array( 'title' => 'Guardar', 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde saveform-products' )
			);
			$aRecord = array();

			// Si estamos editando
			if( $sGetId != false )
			{
				// Obtenemos el registro
				$aRecord = pharaonix_queryOne( 'SELECT GROUP_CONCAT( CONCAT( rg.related_groups_id, ",") ORDER BY rg.related_title ASC SEPARATOR "") groups, rr.idproducts, rr.idcategories, rr.idbrands
												FROM related_products_related rr
												INNER JOIN related_groups_to_related rtr ON (rr.related_related_id = rtr.related_related_id)
												INNER JOIN related_products_groups rg ON (rtr.related_groups_id = rg.related_groups_id)
												WHERE rr.related_related_id = "' . (int)$sGetId . '"
												GROUP BY rr.related_related_id' );

				// Si no existe
				if( $aRecord->num_rows == 0 )
				{
					$messageStack->addSession( 'success', 'El registro que intentas editar no existe', 'error' );
					tep_redirect( tep_href_link(  $sUrlPage ) );
				}

				// Registro
				$aRecord = $aRecord->records;

				$aGroupsRelated = relatedProductsSafeExplode( ',', $aRecord['groups'] );
			}

			// Insertar o actualizar
			if( $sPostAction == 'update_rlt' )
			{
				// Pasamos todos los post por tep_db_prepare_input
				array_walk( $_POST, function( $value, $key){ global $_POST; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); } );

				// Comprobamos que hayan seleccionado algún producto, categoría o marca
				if( !array_key_exists( 'ids', $_POST ) || (array_key_exists( 'ids', $_POST ) && count( $_POST['ids']['products'] ) <= 0 && count( $_POST['ids']['categories'] ) <= 0 && count( $_POST['ids']['brands'] ) <= 0)  )
					$aMessageError['ids'] = $messageStack->show( array( 'text' => 'Debes seleccionar algún producto, categoría o marca para la relación.', 'class' => 'error' ) );

				// Comprobamos que hayan seleccionado algun grupo para relacionar
				if( !array_key_exists( 'groups', $_POST ) || (array_key_exists( 'groups', $_POST ) && count( $_POST['groups'] ) <= 0)  )
					$aMessageError['ids'] = $messageStack->show( array( 'text' => 'Debes seleccionar algún grupo para la relación.', 'class' => 'error' ) );

				// Comprobamos
				if( isset($_POST) && !is_array($_POST['ids']) ){
					$_POST['ids'] = array();
				}
				if( !isset($_POST['ids']['products']) || !is_array($_POST['ids']['products']) ){
					$_POST['ids']['products'] = array();
				}
				if( !isset($_POST['ids']['brands']) || !is_array($_POST['ids']['brands']) ){
					$_POST['ids']['brands'] = array();
				}
				if( !isset($_POST['ids']['categories']) || !is_array($_POST['ids']['categories']) ){
					$_POST['ids']['categories'] = array();
				}

				// Si no existe errores actualizamos/insertamos
				if( count( $aMessageError ) == 0 )
				{
					$aSql = array(
						'idproducts' => tep_db_prepare_input( implode( ',', $_POST['ids']['products'] ) ),
						'idcategories' => tep_db_prepare_input( implode( ',', $_POST['ids']['categories'] ) ),
						'idbrands' => tep_db_prepare_input( implode( ',', $_POST['ids']['brands'] ) )
					);

					if( $sGetId != false )
					{
						// Actualizamos relacionado
						tep_db_perform( 'related_products_related', $aSql, 'update', 'related_related_id = "' . (int)$sGetId . '"' );

						// Eliminamos las relaciones que ya no estén
						tep_db_query( 'DELETE FROM related_groups_to_related WHERE related_related_id = ' . (int)$sGetId . ' AND related_groups_id NOT IN (' . implode( ',', $_POST['groups'] ) . ')' );

						// Recorremos las nuevas relaciones para insertarlas
						foreach( $_POST['groups'] as $nIdGrupo )
						{
							$aAux = tep_db_query( 'SELECT * FROM related_groups_to_related WHERE related_related_id = ' . (int)$sGetId . ' AND related_groups_id = ' . $nIdGrupo );

							if( tep_db_num_rows( $aAux ) <= 0 )
								tep_db_query( 'INSERT INTO related_groups_to_related (related_related_id, related_groups_id) VALUES(' . (int)$sGetId . ', ' . $nIdGrupo . ')' );
						}
					}
					else
					{
						tep_db_perform( 'related_products_related', $aSql );
						$sGetId = tep_db_insert_id();

						// Recorremos las nuevas relaciones para insertarlas
						foreach( $_POST['groups'] as $nIdGrupo )
							tep_db_query( 'INSERT INTO related_groups_to_related (related_related_id, related_groups_id) VALUES(' . (int)$sGetId . ', ' . $nIdGrupo . ')' );
					}

					// Mensaje
					$messageStack->addSession( 'success', 'La relación se ha ' . ($sGetId != false ? 'editado' : 'creado') . ' correctamente', 'success' );

					// Redireccionamos
					tep_redirect( tep_href_link(  $sUrlPage, 'action=related' ) );
				}
			}

			// Formulario
			$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update_rlt' ) . '">';
				$sHtml .= '<div class="oeBox column a12 row ax" style="margin-bottom: 20px;">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-shield"></i> Relaciones - Grupos </div>';
						$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
							$sHtml .= $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : '';
							$sHtml .= '<input type="submit" style="display: none;" />';

							$sHtml .= '<label for="grupos" class="column a01 tright">Grupos:</label>';
							$sHtml .= '<div class="column a10">';
								// Obtenemos todos los grupos
								$aGrupos = tep_db_query( 'SELECT related_groups_id, related_title FROM related_products_groups ORDER BY related_title' );

								$sHtml .= '<select name="groups[]" multiple="multiple" class="select2 skip" style="width: 100%;">';
									while( $aGrupo = tep_db_fetch_array( $aGrupos ) )
										$sHtml .= '<option value="' . $aGrupo['related_groups_id'] . '"' . (in_array( $aGrupo['related_groups_id'], $aGroupsRelated ) ? ' selected="selected"' : '') . '>' . $aGrupo['related_title'] . '</option>';
								$sHtml .= '</select>';
								$sHtml .= '<div class="DFhelp">Grupos relacionados.</div>';
								$sHtml .= array_key_exists( 'grupos', $aMessageError ) ? $aMessageError['grupos'] : '';
							$sHtml .= '</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				// Tabla
				$sHtml .= '<div id="table-products" class="oeBox oeTable xform column a12 row ax" style="display: block;">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> Contenido del relacionado</div>';

						$sHtml .= '<div class="column a12 ax row aflex" style="padding: 20px; position: relative;">';
							$sHtml .= '<input name="term" type="text" placeholder="Buscar..." id="autocomplete" class="column" style="border-right: 0px;" />';
							$sHtml .= '<div id="autocomplete-target"></div>';
							$sHtml .= '<div class="column afixed">
								<div data-value-update="true" class="drop xfselect">
									<div id="autocomplete-type"><span data-type="producto">Por producto</span></div>
									<ul class="down">
										<li><a href="javascript:void(0);" class="hv"><span data-type="producto">Por producto</span></a></li>
										<li><a href="javascript:void(0);" class="hv"><span data-type="categoria">Por categoria</span></a></li>
										<li><a href="javascript:void(0);" class="hv"><span data-type="marca">Por marcas</span></a></li>
									</ul>
								</div></div>';
						$sHtml .= '</div>';

						$sHtml .= (array_key_exists( 'ids', $aMessageError ) ? $aMessageError['ids'] : '');

						$sHtml .= '<table class="xform">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
									$sHtml .= '<th>Nombre</th>';
									$sHtml .= '<th>Tipo</th>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';
								// Si tenemos productos en el grupo o si hemos enviado productos por el formulario
								if( (array_key_exists( 'idproducts', $aRecord ) && $aRecord['idproducts'] != '') || (isset( $_POST['ids']['products'] ) && count( $_POST['ids']['products'] ) > 0) )
								{
									$aAllProducts = getAllProducts();

									// Si el grupo tiene ya productos asignados
									if( array_key_exists( 'idproducts', $aRecord ) && $aRecord['idproducts'] != '' )
										$aIdsProduct = relatedProductsSafeExplode( ',', $aRecord['idproducts'] );
									// Si hemos enviado productos
									elseif( isset( $_POST['ids']['products'] ) )
										$aIdsProduct = tep_db_prepare_input($_POST['ids']['products']);

									foreach( $aIdsProduct as $nIdProduct )
									{
										$sHtml .= '<tr>';
											$sHtml .= '<td width="17" class="chck">';
												$sHtml .= '<input class="idsproducto" value="' . $nIdProduct . '" name="ids[products][]" type="checkbox" id="check_' . $nIdProduct . '"/>';
												$sHtml .= '<label for="check_' . $nIdProduct . '"><span></span></label>';
											$sHtml .= '</td>';
											$sHtml .= '<td>' . $aAllProducts[$nIdProduct]['products_name'] . '</td>';
											$sHtml .= '<td>producto</td>';
										$sHtml .= '</tr>';
									}
								}

								// Si tenemos categorias en el grupo o si hemos enviado categorias por el formulario
								if( (array_key_exists( 'idcategories', $aRecord ) && $aRecord['idcategories'] != '') || (isset( $_POST['ids']['categories'] ) && count( $_POST['ids']['categories'] ) > 0) )
								{
									$aAllCategories = getAllCategories();

									// Si el grupo tiene ya categorias asignados
									if( array_key_exists( 'idcategories', $aRecord ) && $aRecord['idcategories'] != '' )
										$aIdsCategories = relatedProductsSafeExplode( ',', $aRecord['idcategories'] );
									// Si hemos enviado categorias
									elseif( isset( $_POST['ids']['categories'] ) )
										$aIdsCategories = tep_db_prepare_input($_POST['ids']['categories']);

									foreach( $aIdsCategories as $nIdCategory )
									{
										$aAux = getCategoriesByParent_pi( $aAllCategories[$nIdCategory]['parent_id'], $aAllCategories[$nIdCategory]['categories_name'], $aAllCategories );

										if( $aAux['text'] == ' => ' . $aAllCategories[$nIdCategory]['categories_name'] )
											$aAux['text'] = $aAllCategories[$nIdCategory]['categories_name'];

										$sHtml .= '<tr>';
											$sHtml .= '<td width="17" class="chck">';
												$sHtml .= '<input class="idscategoria" value="' . $nIdCategory . '" name="ids[categories][]" type="checkbox" id="check_' . $nIdCategory . '"/>';
												$sHtml .= '<label for="check_' . $nIdCategory . '"><span></span></label>';
											$sHtml .= '</td>';
											$sHtml .= '<td>' . $aAux['text'] . '</td>';
											$sHtml .= '<td>categoria</td>';
										$sHtml .= '</tr>';
									}
								}

								// Si tenemos marcas en el grupo o si hemos enviado marcas por el formulario
								if( (array_key_exists( 'idbrands', $aRecord ) && $aRecord['idbrands'] != '') || (isset( $_POST['ids']['brands'] ) && count( $_POST['ids']['brands'] ) > 0) )
								{
									$aAllBrands = getAllBrands();

									// Si el grupo tiene ya productos asignados
									if( array_key_exists( 'idbrands', $aRecord ) && $aRecord['idbrands'] != '' )
										$aIdsBrands = relatedProductsSafeExplode( ',', $aRecord['idbrands'] );
									// Si hemos enviado productos
									elseif( isset( $_POST['ids']['brands'] ) )
										$aIdsBrands = tep_db_prepare_input($_POST['ids']['brands']);

									foreach( $aIdsBrands as $nIdBrand )
									{
										$sHtml .= '<tr>';
											$sHtml .= '<td width="17" class="chck">';
												$sHtml .= '<input class="idsmarca" value="' . $nIdBrand . '" name="ids[brands][]" type="checkbox" id="check_' . $nIdBrand . '"/>';
												$sHtml .= '<label for="check_' . $nIdBrand . '"><span></span></label>';
											$sHtml .= '</td>';
											$sHtml .= '<td>' . $aAllBrands[$nIdBrand]['manufacturers_name'] . '</td>';
											$sHtml .= '<td>marca</td>';
										$sHtml .= '</tr>';
									}
								}
							$sHtml .= '</tbody>';
						$sHtml .= '</table>';
						$sHtml .= '<div class="column a12 ax row xform oeTableBottom amiddle">';
							$sHtml .= '<div class="column a06 ax row aflex amiddle">';
								$sHtml .= '<label class="column afluid">Aplicar acción:&nbsp;&nbsp;</label>';
								$sHtml .= '<div class="column afluid">';
									$sHtml .= '<div class="drop xfselect">';
										$sHtml .= '<div>Acciones</div>';
										$sHtml .= '<ul class="down drch">';
											$sHtml .= '<li><a id="delete-products" href="javascript:void(0);" class="hv"><i class="fa fa-trash-o"></i>Eliminar registros</a></li>';
										$sHtml .= '</ul>';
									$sHtml .= '</div>';
								$sHtml .= '</div>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</form>';
		break;

		case 'related':
			// Variables
			$sSubtitle = '<font style="color: #cb4c4c;font-weight: bold;">Listado de relaciones</font>';
			$aButtons[] = array( 'title' => 'Opciones', 'href' => tep_href_link( $sUrlPage, 'action=options' ), 'icon' => 'fa-cog' );
			$aButtons[] = array( 'title' => 'Crear relacionados', 'href' => tep_href_link( $sUrlPage, 'action=add_rlt' ), 'icon' => 'fa-plus' );
			$aButtons[] = array( 'title' => 'Grupos', 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-object-ungroup' );

			// Html para el boton masivo
			$sHtmlActionMasivo = '<label class="column afluid">Aplicar acción:&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>Acciones</div>
				<ul class="down drch">
					<li><a data-question="¿Realmente deseas eliminar los registros?" data-action="' . tep_href_link( $sUrlPage, 'action=delete_related' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash-o"></i>Eliminar registros</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

			// Filtros
			$aFilter = array( 'search' => '', 'products' => '', 'categories' => '', 'brands' => '' );
			$aAuxFilter = array_key_exists( 'filter', $_GET ) && is_array($_GET['filter']) ? $_GET['filter'] : (array_key_exists( 'filter', $_POST ) && is_array($_POST['filter']) ? $_POST['filter'] : array());
			$sWhere = '';
			$sWhereChk = '';

			// Limpiamos variables get filter
			array_walk( $aFilter, function( $value, $key){ global $aFilter, $aAuxFilter; $aFilter[$key] = tep_db_prepare_input( array_key_exists( $key, $aAuxFilter ) ? $aAuxFilter[$key] : $aFilter[$key] ); } );

			// Where
			if( $aFilter['search'] != '' )
				$sWhere .= ' (LOWER(rg.related_title) LIKE "%' . strtolower( $aFilter['search'] ) . '%")';
			if( $aFilter['products'] == '1' )
				$sWhereChk .= ' rr.idproducts != "" OR';
			if( $aFilter['categories'] == 1 )
				$sWhereChk .= ' rr.idcategories != "" OR';
			if( $aFilter['brands'] == '1' )
				$sWhereChk .= ' rr.idbrands != "" OR';

			// Sql
			$sSql = 'SELECT rr.related_related_id, GROUP_CONCAT( CONCAT( rg.related_groups_id, "-", rg.related_title, "-", rtr.related_status, "<br>") ORDER BY rg.related_title ASC SEPARATOR " ") groups, rr.idproducts, rr.idcategories, rr.idbrands
					 FROM related_products_related rr
					 INNER JOIN related_groups_to_related rtr ON (rr.related_related_id = rtr.related_related_id)
					 INNER JOIN related_products_groups rg ON (rtr.related_groups_id = rg.related_groups_id)
					 ' . ($sWhere != '' || $sWhereChk != '' ? 'WHERE ' : '') . $sWhere . ($sWhereChk != '' ? ($sWhere != '' ? ' AND' : '') . ' (' . substr( $sWhereChk, 0, -3 ) . ')' : '') . '
					 GROUP BY rr.related_related_id
					 ORDER BY rr.related_related_id DESC';

			// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
			$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

			// Sql para el count
			$sSqlCount = 'SELECT COUNT(table_aux.related_related_id) as total FROM (' . $sSql . ') as table_aux';

			// Datos y paginacion
			$aDatoSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
			$aDatos = tep_db_query( $sSql );

			// Mensajes comprobamos si tenemos datos
			if( tep_db_num_rows( $aDatos ) <= 0 )
			{
				if( $sWhere != '' )
					$sHtml .= $messageStack->show( array( 'text' => 'El filtro establecido no contiene datos.', 'class' => 'warning' ) );
				else
					$sHtml .= $messageStack->show( array( 'text' => 'No existe ningun registro para mostrar.', 'class' => 'warning' ) );
			}
			else
			{
				$aAllProducts = getAllProducts();
				$aAllCategories = getAllCategories();
				$aAllBrands = getAllBrands();
			}

			// Tabla
			$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> Listado de relacionados</div>';
					$sHtml .= '<form method="post" action="' . tep_href_link( $sUrlPage, 'action=related' ) . '" class="oeCntd row ax">';
						$sHtml .= '<div class="oeBoxFltr column a12 ax row">';
							$sHtml .= '<div class="column a09 row ax amiddle input-search">';
								$sHtml .= '<label class="column">Buscar Grupo: </label> <div class="column"><input type="text" name="filter[search]" placeholder="Introduce título" value="' . $aFilter['search'] . '" autofocus/> <i class="fa fa-search"></i></div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="column a03 tright">';
								$sHtml .= ($sWhere != '' || $sWhereChk != '' ? '<a title="Quitar filtro" href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'filter' ) ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa fa-close"></i></a> ' : '');
								$sHtml .= '<a href="#fltr-lstd" title="Filtrar registros" class="xbutton small hv9 verde-turquesa mgp-inln"><i class="fa fa-filter"></i></a>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<table class="xform">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
									$sHtml .= '<th class="sort" width="160">Grupos relacionados</th>';
									$sHtml .= '<th>Productos</th>';
									$sHtml .= '<th>Categorías</th>';
									$sHtml .= '<th>Marcas</th>';
									$sHtml .= '<th width="125">Acciones</th>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';

								while( $aDato = tep_db_fetch_array( $aDatos ) )
								{
									// Separamos productos, categorias y marcas en array
									$aProductos = relatedProductsSafeExplode( ',', $aDato['idproducts'] );
									$aCategorias = relatedProductsSafeExplode( ',', $aDato['idcategories'] );
									$aMarcas = relatedProductsSafeExplode( ',', $aDato['idbrands'] );

									// Separamos los grupos de la relación
									$aGrupos = relatedProductsSafeExplode( '<br>', $aDato['groups'] );

									// Fila
									$sHtml .= '<tr data-dblclick="' . tep_href_link( $sUrlPage, 'action=add_rlt&id=' . $aDato['related_related_id'] ) . '">';
										$sHtml .= '<td class="chck" align="center"><input type="checkbox" id="id_' . $aDato['related_related_id'] . '" name="id[]" value="' . $aDato['related_related_id'] . '"/><label for="id_' . $aDato['related_related_id'] . '"><span></span></label></td>';

										// GRUPOS //
										$sHtml .= '<td>';
											$sHtml .= '<table>';
											// Recorremos los grupos, para pintarlos junto con la opción de cambiar estado
											foreach( $aGrupos as $aGrupo )
											{
												$aGrupo = explode( '-', $aGrupo );

												$sHtml .= '<tr>';

													$sHtml .= '<td style="padding: 0;"><a href="' . tep_href_link( $sUrlPage, 'action=add_form&id=' . $aGrupo[0] ) . '" title="Ver/editar grupo" target="_blank">' . $aGrupo[1] . '</a></td>';
													if( $aGrupo[count( $aGrupo )-1] == '1' )
														$sHtml .= '<td style="padding: 0;">' . tep_image(DIR_WS_IMAGES . 'icon_status_green.png', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link($sUrlPage, 'action=setflagrlt&flag=0&idg=' . $aGrupo[0] . '&idr=' . $aDato['related_related_id'], 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a></td>';
													else
														$sHtml .= '<td style="padding: 0;"><a href="' . tep_href_link($sUrlPage, 'action=setflagrlt&flag=1&idg=' . $aGrupo[0] . '&idr=' . $aDato['related_related_id'], 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.png', IMAGE_ICON_STATUS_RED, 10, 10) . '</td>';

												$sHtml .= '</tr>';
											}
											$sHtml .= '</table>';
										$sHtml .= '</td>';

										// Pintamos productos asignados
										$sHtml .= '<td>';
											$sProducts = '';
											foreach( $aProductos as $nIdProduct )
												$sProducts .= $aAllProducts[$nIdProduct]['products_name'] . ', ';
											$sHtml .= substr( $sProducts, 0, -2 );
										$sHtml .= '</td>';

										// Pintamos categorias asignados
										$sHtml .= '<td>';
											$sCategories = '';
											foreach( $aCategorias as $nIdCategory )
											{
												if( !isset($aAllCategories[$nIdCategory]) ) continue;
												$aAux = getCategoriesByParent_pi( $aAllCategories[$nIdCategory]['parent_id'], $aAllCategories[$nIdCategory]['categories_name'], $aAllCategories );

												if( $aAux['text'] == ' => ' . $aAllCategories[$nIdCategory]['categories_name'] )
													$aAux['text'] = $aAllCategories[$nIdCategory]['categories_name'];

												$sCategories .= $aAux['text'] . '<br>';
											}
											$sHtml .= substr( $sCategories, 0, -2 );
										$sHtml .= '</td>';

										// Pintamos marcas asignados
										$sHtml .= '<td>';
											$sBrands = '';
											foreach( $aMarcas as $nIdMarca )
												$sBrands .= $aAllBrands[$nIdMarca]['manufacturers_name'] . ', ';
											$sHtml .= substr( $sBrands, 0, -2 );
										$sHtml .= '</td>';

										$sHtml .= '<td>';
											$sHtml .= '<div class="drop xfselect">';
												$sHtml .= '<div>Acciones</div>';
												$sHtml .= '<ul class="down down-dngt">';
													$sHtml .= '<li><a href="' . tep_href_link( $sUrlPage, 'action=add_rlt&id=' . $aDato['related_related_id'] ) . '" class="hv"><i class="fa fa-pencil"></i>Editar registro</a></li>';
													$sHtml .= '<li><a data-confirm="¿Realmente deseas eliminar el registro?" href="' . tep_href_link( $sUrlPage, 'action=delete_related&id=' . $aDato['related_related_id'] ) . '" class="hv"><i class="fa fa-trash-o"></i>Eliminar registro</a></li>';
												$sHtml .= '</ul>';
											$sHtml .= '</div>';
										$sHtml .= '</td>';
									$sHtml .= '</tr>';
								}

							$sHtml .= '</tbody>';
						$sHtml .= '</table>';

						// Paginación
						$sHtml .= $aDatoSplit->showPaginateTable( tep_get_all_get_params( array('page') ), 'page', $sHtmlActionMasivo, 'solenopsis' );

						$sHtml .= '</div>';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';

			// Filtro
			$sHtml .= '<form action="' . tep_href_link( $sUrlPage ) . '" method="get" id="fltr-lstd" class="vntn-form mfp-hide zoom-anim-dialog oeBox mfp-white">';
				$sHtml .= '<input type="hidden" name="action" value="related" />';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> Búsqueda de relacionados</div>';
					$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= '<label for="search" class="column a02 tright">Buscar Grupo:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="filter[search]" placeholder="Introduce título" value="' . $aFilter['search'] . '"/> ';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="search" class="column a03 tright">Relación con productos: &nbsp;';
						$sHtml .= '<span class="chck"><input type="checkbox" id="id_prod" name="filter[products]" value="1"' . (isset( $aFilter['products'] ) && $aFilter['products'] == 1 ? ' checked="checked"' : '') . '"/><label for="id_prod"><span></span></label></span></label>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="search" class="column a03 tright">Relación con categorias: &nbsp;';
						$sHtml .= '<span class="chck"><input type="checkbox" id="id_cats" name="filter[categories]" value="1"' . (isset( $aFilter['categories'] ) && $aFilter['categories'] == 1 ? ' checked="checked"' : '') . '"/><label for="id_cats"><span></span></label></span></label>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="search" class="column a03 tright">Relación con marcas: &nbsp;';
						$sHtml .= '<span class="chck"><input type="checkbox" id="id_brnd" name="filter[brands]" value="1"' . (isset( $aFilter['brands'] ) && $aFilter['brands'] == 1 ? ' checked="checked"' : '') . '"/><label for="id_brnd"><span></span></label></span></label>';

						$sHtml .= '<div class="xline xline-none"></div>';
						$sHtml .= '<div class="column a12 tright">';
							$sHtml .= ($sWhere != '' ? '<a href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'filter' ) ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa fa-close"></i> Eliminar</a> ' : '');
							$sHtml .= '<div class="xbutton verde hv9 small"><input type="submit"/><span class="fa fa fa-filter"></span> Filtrar</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</form>';
		break;

		default:
			// Variables
			$sSubtitle = '<font style="color: #4c61cb;font-weight: bold;">Listado de grupos</font>';
			$aButtons[] = array( 'title' => 'Opciones', 'href' => tep_href_link( $sUrlPage, 'action=options' ), 'icon' => 'fa-cog' );
			$aButtons[] = array( 'title' => 'Crear Grupo', 'href' => tep_href_link( $sUrlPage, 'action=add_form' ), 'icon' => 'fa-plus' );
			$aButtons[] = array( 'title' => 'Relacionados', 'href' => tep_href_link( $sUrlPage, 'action=related' ), 'icon' => 'fa-share' );

			// Html para el boton masivo
			$sHtmlActionMasivo = '<label class="column afluid">Aplicar acción:&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>Acciones</div>
				<ul class="down drch">
					<li><a data-question="¿Realmente deseas eliminar los registros?" data-action="' . tep_href_link( $sUrlPage, 'action=delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash-o"></i>Eliminar registros</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

			// Filtros
			$aFilter = (array_key_exists( 'filter', $_POST ) && is_array($_POST['filter']) ? $_POST['filter'] : array());
			$sWhere = '';

			// Limpiamos variables get filter
			array_walk( $aFilter, function( $value, $key){ global $aFilter; $aFilter[$key] = tep_db_prepare_input( $aFilter[$key] ); } );

			// Where
			if( ($aFilter['search'] ?? '') != '' )
				$sWhere .= 'where (LOWER(related_title) LIKE "%' . strtolower( $aFilter['search'] ) . '%")';

			// Order by
			if( $sGetOrderby == 'related_title' )
				$sOrderby = 'related_title ' . $sGetSort;
			else
				$sOrderby = 'related_title asc';

			// Sql
			$sSql = 'SELECT related_groups_id, related_title, related_status, idproducts, idcategories, idbrands
					 FROM related_products_groups
					 ' . $sWhere . ' ORDER BY ' . $sOrderby;

			// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
			$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

			// Sql para el count
			$sSqlCount = 'SELECT COUNT(table_aux.related_groups_id) as total FROM (' . $sSql . ') as table_aux';

			// Datos y paginacion
			$aDatoSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
			$aDatos = tep_db_query( $sSql );

			// Mensajes comprobamos si tenemos datos
			if( tep_db_num_rows( $aDatos ) <= 0 )
			{
				if( $sWhere != '' )
					$sHtml .= $messageStack->show( array( 'text' => 'El filtro establecido no contiene datos.', 'class' => 'warning' ) );
				else
					$sHtml .= $messageStack->show( array( 'text' => 'No existe ningun registro para mostrar.', 'class' => 'warning' ) );
			}
			else
			{
				$aAllProducts = getAllProducts();
				$aAllCategories = getAllCategories();
				$aAllBrands = getAllBrands();
			}

			// Tabla
			$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> Listado de grupos y sus contenidos</div>';
					$sHtml .= '<form method="post" action="' . tep_href_link( $sUrlPage ) . '" class="oeCntd row ax">';
						$sHtml .= '<div class="oeBoxFltr column a12 ax row">';
							$sHtml .= '<div class="column a09 row ax amiddle input-search">';
								$sHtml .= '<label class="column">Buscar: </label> <div class="column"><input type="text" name="filter[search]" placeholder="Introduce título" value="' . ($aFilter['search'] ?? '') . '" autofocus/> <i class="fa fa-search"></i></div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="column a03 tright">';
								$sHtml .= ($sWhere != '' ? '<a title="Quitar filtro" href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'filter' ) ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa fa-close"></i></a> ' : '');
							$sHtml .= '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<table class="xform">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
									$sHtml .= '<th class="sort" width="150">' . tableSetSort( 'related_title', 'Título' ) . '</th>';
									$sHtml .= '<th>Productos</th>';
									$sHtml .= '<th>Categorías</th>';
									$sHtml .= '<th>Marcas</th>';
									$sHtml .= '<th style="text-align: center;">Estado</th>';
									$sHtml .= '<th width="125">Acciones</th>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';

								while( $aDato = tep_db_fetch_array( $aDatos ) )
								{
									$aProductos = relatedProductsSafeExplode( ',', $aDato['idproducts'] );
									$aCategorias = relatedProductsSafeExplode( ',', $aDato['idcategories'] );
									$aMarcas = relatedProductsSafeExplode( ',', $aDato['idbrands'] );

									// Fila
									$sHtml .= '<tr data-dblclick="' . tep_href_link( $sUrlPage, 'action=add_form&id=' . $aDato['related_groups_id'] ) . '">';
										$sHtml .= '<td class="chck" align="center"><input type="checkbox" id="id_' . $aDato['related_groups_id'] . '" name="id[]" value="' . $aDato['related_groups_id'] . '"/><label for="id_' . $aDato['related_groups_id'] . '"><span></span></label></td>';
										$sHtml .= '<td>' . $aDato['related_title'] . '</td>';

										// Pintamos productos asignados
										$sHtml .= '<td>';
											$sProducts = '';
											foreach( $aProductos as $nIdProduct )
												$sProducts .= $aAllProducts[$nIdProduct]['products_name'] . ', ';
											$sHtml .= substr( $sProducts, 0, -2 );
										$sHtml .= '</td>';

										// Pintamos categorias asignados
										$sHtml .= '<td>';
											$sCategories = '';
											foreach( $aCategorias as $nIdCategory )
											{
												if( !isset($aAllCategories[$nIdCategory]) ) continue;
												$aAux = getCategoriesByParent_pi( $aAllCategories[$nIdCategory]['parent_id'], $aAllCategories[$nIdCategory]['categories_name'], $aAllCategories );

												if( $aAux['text'] == ' => ' . $aAllCategories[$nIdCategory]['categories_name'] )
													$aAux['text'] = $aAllCategories[$nIdCategory]['categories_name'];

												$sCategories .= $aAux['text'] . '<br>';
											}
											$sHtml .= substr( $sCategories, 0, -4 );
										$sHtml .= '</td>';

										// Pintamos marcas asignados
										$sHtml .= '<td>';
											$sBrands = '';
											foreach( $aMarcas as $nIdMarca )
												$sBrands .= $aAllBrands[$nIdMarca]['manufacturers_name'] . ', ';
											$sHtml .= substr( $sBrands, 0, -2 );
										$sHtml .= '</td>';

										$sHtml .= '<td align="center">';
											if( $aDato['related_status'] == '1' )
												$sHtml .= tep_image(DIR_WS_IMAGES . 'icon_status_green.png', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link($sUrlPage, 'action=setflag&flag=0&id=' . $aDato['related_groups_id'], 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
											else
												$sHtml .= '<a href="' . tep_href_link($sUrlPage, 'action=setflag&flag=1&id=' . $aDato['related_groups_id'], 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.png', IMAGE_ICON_STATUS_RED, 10, 10);
										$sHtml .= '</td>';
										$sHtml .= '<td>';
											$sHtml .= '<div class="drop xfselect">';
												$sHtml .= '<div>Acciones</div>';
												$sHtml .= '<ul class="down down-dngt">';
													$sHtml .= '<li><a href="' . tep_href_link( $sUrlPage, 'action=add_form&id=' . $aDato['related_groups_id'] ) . '" class="hv"><i class="fa fa-pencil"></i>Editar registro</a></li>';
													$sHtml .= '<li><a data-confirm="¿Realmente deseas eliminar el registro?" href="' . tep_href_link( $sUrlPage, 'action=delete&id=' . $aDato['related_groups_id'] ) . '" class="hv"><i class="fa fa-trash-o"></i>Eliminar registro</a></li>';
												$sHtml .= '</ul>';
											$sHtml .= '</div>';
										$sHtml .= '</td>';
									$sHtml .= '</tr>';
								}

							$sHtml .= '</tbody>';
						$sHtml .= '</table>';

						// Paginación
						$sHtml .= $aDatoSplit->showPaginateTable( tep_get_all_get_params( array('page') ), 'page', $sHtmlActionMasivo, 'solenopsis' );

						$sHtml .= '</div>';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		break;
	}

	// Reemplazamos variable
	$sHtmlModuleOe = $sHtml;

	// MessageStack
	$sMessageStack = $messageStack->output(false);
	$messageStack->reset();

	// Header
	include( 'theme/solenopsis/html/header.php' );

	// Cabecera
	echo '<div class="oeHead column a12 row ax amiddle aflex">';
		echo '<div class="oeTitu column logo afixed" style="padding-left: 59px;"><b><i class="fa fa-clone"></i> ' . $sTitle . '</b>' . ($sSubtitle ? '<small>' . $sSubtitle . '</small>' : '') . '</div>';
		echo '<div class="oeButton column dtright">';
			foreach( $aButtons as $aButton )
				echo '<a class="xbutton hv8 small' . (array_key_exists( 'anchor_class', $aButton ) ? ' ' . $aButton['anchor_class'] : '') . '" ' . (array_key_exists( 'extra', $aButton ) ? $aButton['extra'] : '') . ' ' . (array_key_exists( 'title', $aButton ) ? 'title="' . $aButton['title'] . '"' : '') . ' href="' . (array_key_exists( 'href', $aButton ) ? $aButton['href'] : 'javascript:void(0);') . '"><i class="fa ' . $aButton['icon'] . '"></i>' . $aButton['title'] . '</a> ';
		echo '</div>';
	echo '</div>';

	// Mensajes
	echo $sMessageStack;

	// Pintamos
	echo $sHtmlModuleOe;

	// Footer
	include( 'theme/solenopsis/html/footer.php' );
?>
