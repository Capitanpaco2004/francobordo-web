<?php
	include( 'includes/application_top.php' );

//++++ QT Pro: Begin Added code
	//Create the product investigation for this product that are used in this page.
	$product_investigation = qtpro_doctor_investigate_product($_GET['pID'] ?? 0);
//++++ QT Pro: End Added code
    include( DIR_WS_CLASSES . 'currencies.php' );

	// Variables Imagenes Categorias
	$aTipoImagesCategoria = array(
		'categoria' => array( 'Imagen principal', 'Imagen que se muestra cuando estas navegando por categorías' ),
		'menu' => array( 'Imagen Menú', 'Imagen que se muestra en menú desplegable y acompañando al título de la categoría' )
	);

    $currencies = new currencies();

    // Rapels de descuentos
    include( DIR_WS_CLASSES . 'PriceFormatterAdmin.php' );
    $pf = new PriceFormatter;

	// Products Specifications
	require_once ( DIR_WS_FUNCTIONS . 'products_specifications.php' );

    $action = (isset( $_GET['action'] ) ? $_GET['action'] : '');
	$page = (isset($_GET['page']) ? $_GET['page'] : '1');
	//Se comprueban accesos y permisos del administrador
    $admin_access_query = tep_db_query( "select admin_groups_id, admin_cat_access, admin_right_access from " . TABLE_ADMIN . " where admin_id=" . $login_id );
    $admin_access_array = tep_db_fetch_array( $admin_access_query );
    $admin_groups_id = $admin_access_array['admin_groups_id'];
    $admin_cat_access = $admin_access_array['admin_cat_access'];
    $admin_cat_access_array_cats = explode( ",",$admin_cat_access );
    $admin_right_access = $admin_access_array['admin_right_access'];

	// begin bundled products
	function bundle_avoid($bundle_id)
	{
		// returns an array of bundle_ids containing the specified bundle
		$avoid_list = array();
		$check_query = tep_db_query('select bundle_id from ' . TABLE_PRODUCTS_BUNDLES . ' where subproduct_id = ' . (int)$bundle_id);
		while ($check = tep_db_fetch_array($check_query))
		{
			$avoid_list[] = $check['bundle_id'];
			$tmp = bundle_avoid($check['bundle_id']);
			$avoid_list = array_merge($avoid_list, $tmp);
		}
		return $avoid_list;
	}
	// end bundled products

	// Peticiones AJAX
	switch ($action)
	{
		case 'subproduct_selector':

			$where_str = '';
			if (isset($_GET['pID'])) {
				$bundle_check = bundle_avoid($_GET['pID']);
				if (!empty($bundle_check)) {
					$where_str = ' and (not (p.products_id in (' . implode(',', $bundle_check) . ')))';
				}
			}
			$products = tep_db_query("select pd.products_name, p.products_id, p.products_model from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where pd.products_id = p.products_id and pd.language_id = '" . (int) $languages_id . "' and p.products_id <> " . (int) $_GET['pID'] . $where_str . " AND pd.products_name LIKE '%".tep_db_prepare_input($_GET['term'])."%' order by FIELD(p.products_status, 1, 2, 0), p.products_model");

			$items = [];
			while ($products_values = tep_db_fetch_array($products)) {
				$items[] = [
					'id' => $products_values['products_id'],
					'text' => $products_values['products_name'] . ($products_values['products_model'] != '' ?  ' (' . $products_values['products_model'] . ')' : ''),
				];
			}

			echo json_encode(['results' => $items]);
			die();

			break;
		case 'remove-alternative':
			if ((int)$_GET['pid'] > 0)
			{
				tep_db_query('DELETE FROM products_descat_alternativos WHERE ID = '. (int)$_GET['pid']);
				tep_redirect( $_SERVER['HTTP_REFERER'] );
			}
		break;

		case 'autocomplete':
			// Variables
			$sGetTern = tep_db_prepare_input( $_GET['term'] );
			$sGetDescp = tep_db_prepare_input( $_GET['descp'] );
			$aKeywords = array();
			$aReturn = array();

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

			// Consultamos
			$aDatos = tep_db_query( 'SELECT p.products_id, pd.products_name, p.products_image
									 FROM products p
									 INNER JOIN products_description pd ON(p.products_id = pd.products_id)
									 where pd.language_id = 3 ' . $sWhere . ' order by pd.products_name LIMIT 30' );

			while ($products = tep_db_fetch_array($aDatos))
				$aReturn[] = array( 'id' => $products['products_id'], 'label' => '#' . $products['products_id'] . '# '  . $products['products_name'], 'value' => '#' . $products['products_id'] . '# '  . $products['products_name'], 'image' => tep_image( DIR_WS_CATALOG_IMAGES . 'productos/' . $products['products_image'], '', 50, 50 ) );

			echo json_encode( $aReturn );
			exit();
		break;

		// Ordenar imagenes del producto
		case 'image-order-product':
			// Variables
			$sId = tep_db_prepare_input( $_POST['id'] );
			$sImages = tep_db_prepare_input( $_POST['images'] );
			$sImagenPrincipal = '';

			// Explotamos el string en array
			$sImages = explode( ',', $sImages );

			// Obtenemos la imagen principal
			if( is_array( $sImages ) && count( $sImages ) > 0 )
			{
				$sImagenPrincipal = tep_db_prepare_input( $sImages[0] );
				array_shift( $sImages );

				// Reordenamos el array para insertar
				$sImages = array_values($sImages);

				// Eliminamos el ultimo ya que ha quedado vacio
				unset( $sImages[count($sImages) - 1] );
			}

			// Actualizamos
			tep_db_query( 'update products set products_image = "' . $sImagenPrincipal . '", products_subimages = "' . tep_db_input( json_encode( $sImages ) ) . '"  where products_id = "' . (int)$sId . '"' );

			exit(1);
		break;

		// Eliminar una imagen de categoria o producto
		case 'delete_image':
			// Variables
			$sId = tep_db_prepare_input( $_POST['id'] );
			$sImagenEliminar = tep_db_prepare_input( $_POST['image'] );
			$sType = tep_db_prepare_input( $_POST['type'] );
			$languages = tep_get_languages();
			$sRowImage = ($sType == 'categories' ? 'categories_image' : 'products_subimages');
			$sRowId = ($sType == 'categories' ? 'categories_id' : 'products_id');
			$sDirImagen = ($sType == 'categories' ? 'categorias' : 'productos');
			$sRowExtra = ($sType == 'categories' ? '' : 'products_image,');

			// Consultamos en db
			$aDato = tep_db_query( 'select ' . $sRowExtra . $sRowImage . ' from ' . $sType . ' where ' . $sRowId . ' = "' . (int)$sId . '"' );

			// Si existe el registro
			if( tep_db_num_rows( $aDato ) > 0 )
			{
				// Obtenemos los registros
				$aDato = tep_db_fetch_array( $aDato );
				$aImagesActuales = json_decode( (string) $aDato[$sRowImage], true ); if (!is_array($aImagesActuales)) $aImagesActuales = [];

				// Eliminamos
				deleteImagenAndThumb( $sImagenEliminar, $sDirImagen );

				// Buscamos en el array para eliminarlo
				// Si el tipo es categoria
				if( $sType == 'categories' )
				{
					foreach( $aTipoImagesCategoria as $key => $aTipo )
					{
						// Recorremos los idiomas para hacer las imagenes por tipo de idioma
						foreach( $languages as $aLanguge )
						{
							// Si existe y es el archivo lo eliminamos
							if( array_key_exists( $key, $aImagesActuales) && array_key_exists( $aLanguge['id'], $aImagesActuales[$key] ) && $aImagesActuales[$key][$aLanguge['id']] == $sImagenEliminar )
								$aImagesActuales[$key][$aLanguge['id']] = '';
						}
					}

					// Actualizamos
					tep_db_query( 'update categories set categories_image = "' . tep_db_input( json_encode( $aImagesActuales ) ) . '" where categories_id = "' . (int)$sId . '"' );
				}
				else // Si es productos
				{
					// Imagen principal del producto
					$sImagenPrincipal = $aDato['products_image'];

					// Si la imagen a borrar coincide con la principal, le añadimos la primera del array y la eliminamos de la subimages
					if( $sImagenPrincipal == $sImagenEliminar )
					{
						$sImagenPrincipal = $aImagesActuales[0];
						unset( $aImagesActuales[0] );
					}
					else // Subimages
					{
						// Recorremos las imagenes en busca de la que acabamos de eliminar para quitarla del array
						foreach( $aImagesActuales as $key => $sImagen )
							if( $sImagen == $sImagenEliminar )
								unset( $aImagesActuales[$key] );
					}

					// Reordenamos el array para insertar
					$aImagesActuales = array_values($aImagesActuales);

					// Actualizamos
					tep_db_query( 'update products set products_image = "' . $sImagenPrincipal . '", products_subimages = "' . tep_db_input( json_encode( $aImagesActuales ) ) . '"  where products_id = "' . (int)$sId . '"' );
				}

				exit(1);
			}
		break;
	}

    //Subir fichero adjunto
    if( !empty($_FILES['products_fileupload']['tmp_name']) && is_uploaded_file( $_FILES['products_fileupload']['tmp_name'] ) )
    {
        $nombre_products_fileupload = $_FILES['products_fileupload']['name'];
		copy( $_FILES['products_fileupload']['tmp_name'], '../images/upload/'.$nombre_products_fileupload );
    }
    else
        $nombre_products_fileupload=$_POST['products_fileupload_anterior'] ?? '';

    if( !empty($_FILES['products_pdfupload']['tmp_name']) && is_uploaded_file( $_FILES['products_pdfupload']['tmp_name'] ) )
    {
        $nombre_products_pdfupload = $_FILES['products_pdfupload']['name'];
		copy( $_FILES['products_pdfupload']['tmp_name'], DIR_FS_CATALOG_MANUALS . $nombre_products_pdfupload );
    }
    else
        $nombre_products_pdfupload=$_POST['products_pdfupload_anterior'] ?? '';
    // final fichero adjunto

	//Formas de pago/envio por productos
    if( !empty( $_POST ) )
    {
        if( !empty( $_POST['payment_methods'] ) )
            $payment_methods = implode( ';', $_POST['payment_methods'] );
		else
				$payment_methods = NULL;

		if( !empty( $_POST['shipping_methods'] ) )
				$shipping_methods = implode( ';',$_POST['shipping_methods'] );
		else
				$shipping_methods = NULL;
    }
    // PSM END

    // BOF Ultimate SEO URLs EDITABLE
    // If the action will affect the cache entries
    if( preg_match( "/(insert|update|setflag|setflagAmazon|setflag_featured|setflag_import_exclude)/i", $action) )
        include_once( 'includes/reset_seo_cache.php' );
    // EOF Ultimate SEO URLs EDITABLE

	// Variables de error
	$bErrorName = false;
	$bErrorNameLang = false;

    if( tep_not_null( $action ) )
    {
        switch( $action )
        {
            case 'setflag':
                if( $_GET['flag'] == '0' || $_GET['flag'] == '1'  || $_GET['flag'] == '2' )
                {
                    if( isset($_GET['pID'] ) )
                        tep_set_product_status( (int)$_GET['pID'], (int)$_GET['flag'] );

                    if( USE_CACHE == 'true' )
                    {
                        tep_reset_cache_block( 'categories' );
                        tep_reset_cache_block( 'also_purchased' );
                        tep_reset_cache_block('xsell_products');
                    }
                }

                if( isset($_GET['ajax']) && $_GET['ajax'] == '1' )
                {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'ok'     => true,
                        'pID'    => (int)$_GET['pID'],
                        'status' => (int)$_GET['flag'],
                    ]);
                    exit;
                }

                tep_redirect( tep_href_link( FILENAME_CATEGORIES, 'cPath=' . $_GET['cPath'] . '&pID=' . $_GET['pID'] ) );
            break;

			case 'setflagAmazon':
                if( $_GET['flag'] == '0' || $_GET['flag'] == '1' )
                {
                    if( isset($_GET['pID'] ) )
                        tep_set_product_amazon_status( (int)$_GET['pID'], (int)$_GET['flag'] );

                    if( USE_CACHE == 'true' )
                    {
                        tep_reset_cache_block( 'categories' );
                        tep_reset_cache_block( 'also_purchased' );
                        tep_reset_cache_block('xsell_products');
                    }
                }

                tep_redirect( tep_href_link( FILENAME_CATEGORIES, 'cPath=' . $_GET['cPath'] . '&pID=' . $_GET['pID'] ) );
            break;

			case 'setflag_featured':
				if( $_GET['flag'] == '0' || $_GET['flag'] == '1' )
				{
					if( isset($_GET['pID']) )
						tep_set_product_featured((int)$_GET['pID'], (int)$_GET['flag']);

					if( USE_CACHE == 'true' )
					{
						tep_reset_cache_block('categories');
						tep_reset_cache_block('also_purchased');
						tep_reset_cache_block('xsell_products');
					}
				}

				tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $_GET['cPath'] . '&pID=' . $_GET['pID']));
			break;

			case 'setflag_import_exclude':
				if( $_GET['flag'] == '0' || $_GET['flag'] == '1' )
				{
					if( isset($_GET['pID']) )
						tep_set_product_import_exclude((int)$_GET['pID'], (int)$_GET['flag']);

					if( USE_CACHE == 'true' )
					{
						tep_reset_cache_block('categories');
						tep_reset_cache_block('also_purchased');
						tep_reset_cache_block('xsell_products');
					}
				}

				tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $_GET['cPath'] . '&pID=' . $_GET['pID']));
			break;



			case 'setsortorder':
				for ($i=0, $n=sizeof($_POST['products_id']); $i<$n; $i++)
				{
					tep_set_product_sort_order((int)$_POST['products_id'][$i], (int)$_POST['sortorder'][$i]);
				}

				exit();
			break;

            // RSS quick set button
            // catching and setting the rss status
            case 'setrss':
                if( $_GET['rss'] == '0' || $_GET['rss'] == '1' )
                {
                    if( isset( $_GET['pID'] ) )
                        tep_set_product_rss_status((int)$_GET['pID'], (int)$_GET['rss']);
                }
            break;

            case 'setcats':
                if( $_GET['flag'] == '0' || $_GET['flag'] == '1' || $_GET['flag'] == '2' )
                {
                    if( isset( $_GET['cID'] ) )
                        tep_set_category_status_recursive( (int)$_GET['cID'], (int)$_GET['flag'] );

                    if( USE_CACHE == 'true' )
                    {
                        tep_reset_cache_block( 'categories' );
                        tep_reset_cache_block( 'also_purchased' );
                    }
                }

                tep_redirect( tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $_GET['cPath'] . '&cID=' . $_GET['cID'] ) );
            break;

			case 'update_seo_category':
				$aIdiomas = tep_get_languages();

				foreach( $aIdiomas as $aIdioma )
				{
					$aUpdate = array(
						'categories_seo_title' => tep_db_prepare_input( $_POST['categories_seo_title'][$aIdioma['id']] ),
						'categories_seo_keywords' => tep_db_prepare_input( $_POST['categories_seo_keywords'][$aIdioma['id']] ),
						'categories_seo_description' => tep_db_prepare_input( $_POST['categories_seo_description'][$aIdioma['id']] ),
						'categories_seo_text_landing_page' => tep_db_prepare_input( $_POST['categories_seo_text_landing_page'][$aIdioma['id']] )
					);

					// Actualizamos
					tep_db_perform( 'categories_description', $aUpdate, 'update', 'categories_id = ' . $_POST['categories_id'] . ' and language_id = ' . $aIdioma['id'] );
				}

				// Redireccionamos
				tep_redirect( tep_href_link( FILENAME_CATEGORIES, 'cPath=' . $_GET['cPath'] . '&cID=' . $_POST['categories_id'] ) );
			break;

            case 'insert_category':
            case 'update_category':
                if( isset( $_POST['categories_id'] ) )
                    $categories_id = tep_db_prepare_input( $_POST['categories_id'] );


                $sort_order = tep_db_prepare_input( $_POST['sort_order'] );
                $sql_data_array = array( 'sort_order' => (int)$sort_order );

				// Añadimos el grandparent ID
				if( isset( $_GET['cPath'] ) )
				{
					// Obtenemos el ID principal
					$cID = explode( '_', $_GET['cPath'] );
					$cID = $cID[0];

					// Establecemos el grandparent_id
					$sql_data_array['grandparent_id'] = $cID;
				}

                if( $action == 'insert_category' )
                {
                    $insert_sql_data = array( 'parent_id' => $current_category_id, 'date_added' => 'now()' );
                    $sql_data_array = array_merge( $sql_data_array, $insert_sql_data );

                    tep_db_perform( TABLE_CATEGORIES, $sql_data_array );

                    $categories_id = tep_db_insert_id();

                    if( in_array( "ALL", $admin_cat_access_array_cats )== false )
                    {
                        array_push( $admin_cat_access_array_cats, $categories_id );
                        $admin_cat_access = implode( ",", $admin_cat_access_array_cats );
                        $sql_data_array = array( 'admin_cat_access' => tep_db_prepare_input( $admin_cat_access ) );
                        tep_db_perform( TABLE_ADMIN, $sql_data_array, 'update', 'admin_id = \'' . $login_id . '\'' );
                    }
                }
                elseif( $action == 'update_category' )
                {
                    $update_sql_data = array( 'last_modified' => 'now()' );
                    $sql_data_array = array_merge( $sql_data_array, $update_sql_data );

					// Actualizamos la categoria
                    tep_db_perform( TABLE_CATEGORIES, $sql_data_array, 'update', "categories_id = '" . (int)$categories_id . "'" );
                }


				/**
				 * XCC-313-91043
				 * @author Daniel Lucia <daniel.lucia@denox.es>
				 */
				Affiliates::adminSaveCategory(
					intval($categories_id),
					floatval($_POST['comission']),
					floatval($_POST['comission_eu'])
				);

                $cxstat = ($_POST['cxstat'] <> 9 ? $_POST['cxstat'] : '');
                $products_query = tep_db_query( "select p.products_id, cd.categories_name  from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c, " . TABLE_CATEGORIES_DESCRIPTION . " cd  where  p.products_id = p2c.products_id and p2c.categories_id = '" . (int)$categories_id . "' and cd.categories_id = p2c.categories_id" );
                $cnt=0;

                if( $cxstat <> '' )
                {
                    while( $products = tep_db_fetch_array( $products_query ) )
                    {
                        $cnt++;
                        $categories_name = $products['categories_name'];
                        tep_set_product_status( (int)$products['products_id'], (int)$cxstat );
                    }

                    $message = ( $cxstat ? IMAGE_ICON_STATUS_GREEN : IMAGE_ICON_STATUS_RED );
                    $messageStack->add_session( '', 'none' );
                    $messageStack->add_session( 'Set ' . $cnt . ' Products In ' . $categories_name . ' To ' . $message, 'success' );

                    if( USE_CACHE == 'true' )
                    {
                        tep_reset_cache_block( 'categories' );
                        tep_reset_cache_block( 'also_purchased' );
                    }
                }

                $languages = tep_get_languages();

                for( $i=0, $n = sizeof( $languages ); $i < $n; $i++ )
                {
                    $categories_name_array = $_POST['categories_name'];
                    $categories_seo_url_array = $_POST['categories_seo_url'];

                    $language_id = $languages[$i]['id'];

                    $sql_data_array = array( 'categories_name' => tep_db_prepare_input( $categories_name_array[$language_id] ),
                                             'categories_seo_url' => tep_db_prepare_input( $categories_seo_url_array[$language_id] ) );

                    if( $action == 'insert_category' )
                    {
                        $insert_sql_data = array( 'categories_id' => $categories_id, 'language_id' => $languages[$i]['id'] );

                        $sql_data_array = array_merge( $sql_data_array, $insert_sql_data );

                        tep_db_perform( TABLE_CATEGORIES_DESCRIPTION, $sql_data_array );
                    }
                    elseif( $action == 'update_category' )
                        tep_db_perform( TABLE_CATEGORIES_DESCRIPTION, $sql_data_array, 'update', "categories_id = '" . (int)$categories_id . "' and language_id = '" . (int)$languages[$i]['id'] . "'" );
                }

				// Array con las imagenes
				$aImagenesInsert = array();
				// Array con las imagenes actuales
				$aImagesActuales = array();

				// Si estamos actualizando creamos el array de archivos que tenemos
                if( $action == 'update_category' )
                {
					// Obtenemos el nombre de las imagenes
					$aDato = tep_db_query( 'select categories_image from categories where categories_id = ' . $categories_id );
					$aDato = tep_db_fetch_array( $aDato );
					$aImagesActuales = json_decode( $aDato['categories_image'] ?? '', true );
				}

				// Recorremos los dintintos tipos de imagenes para ver si hemos subido alguno
				foreach( $aTipoImagesCategoria as $key => $aTipo )
				{
					// Creamos el indice
					$aImagenesInsert[$key] = array();

					// Recorremos los idiomas para hacer las imagenes por tipo de idioma
					foreach( $languages as $aLanguge )
					{
						// Creamos el indice
						$aImagenesInsert[$key][$aLanguge['id']] = '';

						// Si existe una subida continuamos
						if( array_key_exists( 'categories_image_' . $key, $_FILES) && $_FILES['categories_image_' . $key]['size'][$aLanguge['id']] != 0 )
						{
							$objUpload = new upload( 'categories_image_' . $key );
							$objUpload->set_destination( DIR_FS_CATALOG_IMAGES . 'categorias/' );
							$objUpload->set_language( $aLanguge['id'] );
							$bInsert = false;

							// Si se ha podido guardar
							if( $objUpload->parse() && $objUpload->save() )
							{
								$sName = $objUpload->filename;
								$sName2 = getSlug( $_POST['categories_name'][3] ) . '-' . $key . '-' . $aLanguge['id'] . '-' . $categories_id . '.' . getFileExtension( $objUpload->filename );
								rename( DIR_FS_CATALOG_IMAGES . "categorias/" . $sName, DIR_FS_CATALOG_IMAGES  . "categorias/" . $sName2 );

								$aImagenesInsert[$key][$aLanguge['id']] = $sName2;
								$bInsert = true;
							}

							// Si existe lo eliminamos
							if( array_key_exists( $key, $aImagenesInsert ) && array_key_exists( $aLanguge['id'], $aImagenesInsert[$key] ) )
							{
								// Si se ha subido lo eliminamos
								if( $bInsert )
									deleteImagenAndThumb( $aImagesActuales[$key][$aLanguge['id']], 'categorias', true );
								else // Si no existe pues guardamos el que teniamos anteriormente ya que no ha pasado nada
									$aImagenesInsert[$key][$aLanguge['id']] = $aImagesActuales[$key][$aLanguge['id']];
							}
						}
						else // Por el contrario guardamos el anterior
							$aImagenesInsert[$key][$aLanguge['id']] = $aImagesActuales[$key][$aLanguge['id']];
					}
				}

				// Actualizamos las imagenes
				tep_db_query( 'update categories set categories_image = "' . tep_db_input( json_encode( $aImagenesInsert ) ) . '" where categories_id = "' . (int)$categories_id . '"' );

                if( USE_CACHE == 'true' )
                {
                    tep_reset_cache_block( 'categories' );
                    tep_reset_cache_block( 'also_purchased' );
                }

                tep_redirect( tep_href_link( FILENAME_CATEGORIES, 'page='.$page.'&cPath=' . $cPath . '&cID=' . $categories_id ) );
            break;

            case 'delete_category_confirm':
                if( isset( $_POST['categories_id'] ) )
                {
                    $categories_id = tep_db_prepare_input( $_POST['categories_id'] );

                    $categories = tep_get_category_tree( $categories_id, '', '0', '',true );
                    $products = array();
                    $products_delete = array();

                    for( $i=0, $n = sizeof( $categories ); $i < $n; $i++ )
                    {
                        $product_ids_query = tep_db_query( "select products_id from " . TABLE_PRODUCTS_TO_CATEGORIES . " where categories_id = '" . (int)$categories[$i]['id'] . "'" );

                        while( $product_ids = tep_db_fetch_array( $product_ids_query) )
                            $products[$product_ids['products_id']]['categories'][] = $categories[$i]['id'];
                    }

                    foreach ($products as $key => $value)
                    {
                        $category_ids = '';

                        for( $i = 0, $n = sizeof( $value['categories'] ); $i < $n; $i++ )
                            $category_ids .= "'" . (int)$value['categories'][$i] . "', ";

                        $category_ids = substr( $category_ids, 0, -2 );

                        $check_query = tep_db_query( "select count(*) as total from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int)$key . "' and categories_id not in (" . $category_ids . ")" );
                        $check = tep_db_fetch_array( $check_query );

                        if( $check['total'] < '1' )
                            $products_delete[$key] = $key;
                    }

                    // removing categories can be a lengthy process
                    tep_set_time_limit(0);

                    for( $i = 0, $n = sizeof( $categories ); $i < $n; $i++ )
                        tep_remove_category($categories[$i]['id']);

                    foreach (array_keys($products_delete) as $key)
                        tep_remove_product($key);
                }

                if( USE_CACHE == 'true' )
                {
                    tep_reset_cache_block( 'categories' );
                    tep_reset_cache_block( 'also_purchased' );
                }

                tep_redirect( tep_href_link( FILENAME_CATEGORIES, 'page='.$page.'&cPath=' . $cPath ) );
            break;

	        //Eliminar Producto
            case 'delete_product_confirm':
                if( isset( $_POST['products_id'] ) && isset( $_POST['product_categories'] ) && is_array( $_POST['product_categories'] ) )
                {
                    $product_id = tep_db_prepare_input( $_POST['products_id'] );
                    $product_categories = $_POST['product_categories'];

                    for( $i = 0, $n = sizeof( $product_categories ); $i < $n; $i++ )
                    {
                        tep_db_query( "delete from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int)$product_id . "' and categories_id = '" . (int)$product_categories[$i] . "'" );

                        // BOF Separate Pricing Per Customer
                        tep_db_query( "delete from " . TABLE_PRODUCTS_GROUPS . " where products_id = '" . tep_db_input($product_id) . "' " );
                        // EOF Separate Pricing Per Customer
                    }

                    tep_db_query( "delete from " . TABLE_PRODUCTS_PRICE_BREAK . " where products_id = '" . (int)$product_id . "'" );

                    $product_categories_query = tep_db_query( "select count(*) as total from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int)$product_id . "'" );
                    $product_categories = tep_db_fetch_array( $product_categories_query );

                    if( $product_categories['total'] == '0' )
                        tep_remove_product($product_id);

                    /* Optional Related Products (ORP) */
                    tep_db_query( "delete from " . TABLE_PRODUCTS_RELATED_PRODUCTS . " where pop_products_id_master = '" . (int)$product_id . "'" );
                    tep_db_query( "delete from " . TABLE_PRODUCTS_RELATED_PRODUCTS . " where pop_products_id_slave = '" . (int)$product_id . "'" );
                    //ORP: end
                }

				// Start Products Specifications
				tep_db_query ("delete from " . TABLE_PRODUCTS_SPECIFICATIONS . " where products_id = '" . (int) $product_id . "'");
				// End Products Specifications

                if( USE_CACHE == 'true' )
                {
                    tep_reset_cache_block( 'categories' );
                    tep_reset_cache_block( 'also_purchased' );
                }

                tep_redirect( tep_href_link( FILENAME_CATEGORIES, 'cPath=' . $cPath ) );
            break;

            case 'move_category_confirm':
                if( isset( $_POST['categories_id'] ) && $_POST['categories_id'] != $_POST['move_to_category_id'] )
                {
                    $categories_id = tep_db_prepare_input( $_POST['categories_id'] );
                    $new_parent_id = tep_db_prepare_input( $_POST['move_to_category_id'] );

                    $path = explode( '_', tep_get_generated_category_path_ids( $new_parent_id ) );

                    if( in_array( $categories_id, $path ) )
                    {
                        $messageStack->add_session( ERROR_CANNOT_MOVE_CATEGORY_TO_PARENT, 'error' );
                        tep_redirect( tep_href_link( FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $categories_id ) );
                    }
                    else
                    {
                        tep_db_query( "update " . TABLE_CATEGORIES . " set parent_id = '" . (int)$new_parent_id . "', last_modified = now() where categories_id = '" . (int)$categories_id . "'" );

                        if( USE_CACHE == 'true' )
                        {
                            tep_reset_cache_block( 'categories' );
                            tep_reset_cache_block( 'also_purchased' );
                        }

                        tep_redirect( tep_href_link( FILENAME_CATEGORIES, 'cPath=' . $new_parent_id . '&cID=' . $categories_id ) );
                    }
                }
            break;

			case 'select_category_amazon_confirm':
				if (isset($_POST['categories_id']) && ($_POST['categories_id'] != $_POST['move_to_category_id']))
				{
					$categories_id = tep_db_prepare_input($_POST['categories_id']);
					$new_amazon_id = tep_db_prepare_input($_POST['move_to_category_id']);

					tep_db_query("update " . TABLE_CATEGORIES . " set categories_amazon_id = '" . (int)$new_amazon_id . "' where categories_id = '" . (int)$categories_id . "'");

					tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $categories_id));
				}

			break;

            case 'move_product_confirm':
                $products_id = tep_db_prepare_input( $_POST['products_id'] );
                $new_parent_id = tep_db_prepare_input( $_POST['move_to_category_id'] );

                $duplicate_check_query = tep_db_query( "select count(*) as total from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int)$products_id . "' and categories_id = '" . (int)$new_parent_id . "'" );
                $duplicate_check = tep_db_fetch_array( $duplicate_check_query );

                if( $duplicate_check['total'] < 1 )
                    tep_db_query( "update " . TABLE_PRODUCTS_TO_CATEGORIES . " set categories_id = '" . (int)$new_parent_id . "' where products_id = '" . (int)$products_id . "' and categories_id = '" . (int)$current_category_id . "'" );

                if( USE_CACHE == 'true' )
                {
                    tep_reset_cache_block( 'categories' );
                    tep_reset_cache_block( 'also_purchased' );
                }

                tep_redirect( tep_href_link( FILENAME_CATEGORIES, 'cPath=' . $new_parent_id . '&pID=' . $products_id ) );
            break;

            case 'insert_product':
            case 'update_product':
				// Variables
				$bError = false;
				$languages = tep_get_languages();

				// Comprobamos nombre
                for( $i=0, $n = sizeof( $languages ); $i < $n; $i++ )
				{
					// Si no tenemos nombre
					if( $_POST['products_name'][$languages[$i]['id']] == '' )
					{
						$bError = true;

						if( $i > 0 )
						{
							$bErrorNameLang = true;
							$bErrorName = false;
						}
						else
							$bErrorName = true;
					}
				}

				// Si tenemos error cambiamos la accion
				if( $bError )
					$action = 'new_product';

				// Si no tenemos ningn error
				if( ! $bError )
				{
					if( isset( $_POST['edit_x'] ) || isset( $_POST['edit_y'] ) )
						$action = 'new_product';
					else
					{
						if( isset( $_GET['pID'] ) )
							$products_id = tep_db_prepare_input( $_GET['pID'] );

						$products_date_available = tep_db_prepare_input( $_POST['products_date_available'] );

						if( $products_date_available != '' )
						{
							$aAux = explode( '/', $products_date_available );
							$products_date_available = $aAux[2] . '-' . $aAux[1] . '-' . $aAux[0];
						}

						$products_date_available = (date('Y-m-d') < $products_date_available) ? $products_date_available : 'null';

						$products_featured_until = tep_db_prepare_input( $_POST['products_featured_until'] );

						if( $products_featured_until != '' )
						{
							$aAux = explode( '/', $products_featured_until );
							$products_featured_until = $aAux[2] . '-' . $aAux[1] . '-' . $aAux[0];
						}

						// Si hemos activado producto destacado y no tenemos fecha asignamos la de por defecto
						if( tep_db_prepare_input($_POST['products_featured']) &&  $products_featured_until == '')
							$products_featured_until = date('Y-m-d', strtotime('+' . DAYS_UNTIL_FEATURED_PRODUCTS . ' days') );

						$products_featured_until = (date('Y-m-d') < $products_featured_until) ? $products_featured_until : 'null';



						$sql_data_array = array( 'products_quantity' => (int)tep_db_prepare_input($_POST['products_quantity']),
												 'products_bundle' => ($_POST['products_bundle'] == 'yes' ? 'yes' : 'no'),
												 'sold_in_bundle_only' => ($_POST['sold_in_bundle_only'] == 'yes' ? 'yes' : 'no'),
												 'products_quantity_deseada' => tep_db_prepare_input($_POST['products_quantity_deseada']),
												 'check_stock' => tep_db_prepare_input($_POST['check_stock']),
												 'exclude_feedmachine' => tep_db_prepare_input($_POST['exclude_feedmachine']),
												 'products_model' => tep_db_prepare_input($_POST['products_model']),
												 'products_youtube' => tep_db_prepare_input(extraer_id_video($_POST['products_youtube'])),
												 'products_price' => tep_db_prepare_input($_POST['products_price']),
												 'products_cost' => tep_db_prepare_input($_POST['products_cost']),
												 'products_fileupload' => tep_db_prepare_input($nombre_products_fileupload),
												 'products_pdfupload' => tep_db_prepare_input($nombre_products_pdfupload),
												 'products_qty_blocks' => (($i = (int)tep_db_prepare_input($_POST['products_qty_blocks'][0])) < 1) ? 1 : $i,
												 'products_min_order_qty' => (($min_i = (int)tep_db_prepare_input($_POST['products_min_order_qty'][0])) < 1) ? 1 : $min_i,
												 'products_date_available' => $products_date_available,
												 'products_featured_until' => $products_featured_until,
												 'products_weight' => (float)tep_db_prepare_input($_POST['products_weight']),
												 'ISBN' => tep_db_prepare_input($_POST['ISBN']),
												 'products_status' => tep_db_prepare_input($_POST['products_status']),
												 'products_featured' => tep_db_prepare_input($_POST['products_featured']),
												 'products_tax_class_id' => tep_db_prepare_input($_POST['products_tax_class_id']),
												 'product_ean' => tep_db_prepare_input($_POST['product_ean']),
												 'reference_prov' => tep_db_prepare_input($_POST['reference_prov']),
												 'shipping_methods' => tep_db_prepare_input($shipping_methods),
												 'payment_methods' => tep_db_prepare_input($payment_methods),
												 'manufacturers_id' => tep_db_prepare_input($_POST['manufacturers_id']),
												 'products_ship_free' => tep_db_prepare_input($_POST['products_ship_free']),
												 'products_to_rss' => tep_db_prepare_input($_POST['products_to_rss']),
												 'amazon_status' => tep_db_prepare_input($_POST['amazon_status']),
												 'products_liquidacion' => ($_POST['products_liquidacion'] == 1 && tep_db_prepare_input($_POST['specials_min_price']) > 0 ? 1 : 0));

		//++++ QT Pro: Begin Added code
			if($product_investigation['has_tracked_options'] or $product_investigation['stock_entries_count'] > 0){
				//Do not modify the stock from this page if the product has database entries or has tracked options
				unset($sql_data_array['products_quantity']);
			}
		//++++ QT Pro: End Added code

						if( $_POST['delete_products_fileupload'] == 'yes' )
						{
							unlink( '../images/upload/' . $_POST['products_fileupload_anterior'] );
							$sql_data_array['products_fileupload'] = tep_db_prepare_input( $_POST['none'] );
						}

						if( $_POST['delete_products_pdfupload'] == 'yes' )
						{
							unlink( '../manuals/' . $_POST['products_pdfupload_anterior'] );
							$sql_data_array['products_pdfupload'] = tep_db_prepare_input( $_POST['none'] );
						}

						if( $action == 'insert_product' )
						{
							$insert_sql_data = array( 'products_date_added' => 'now()' );

							$sql_data_array = array_merge( $sql_data_array, $insert_sql_data );

							tep_db_perform( TABLE_PRODUCTS, $sql_data_array );
							$products_id = tep_db_insert_id();
							tep_db_query( "INSERT INTO " . TABLE_PRODUCTS_YEARLY_SALES . " (products_id) VALUES ($products_id)" );
							tep_db_query( "insert into " . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) values ('" . (int)$products_id . "', '" . (int)$current_category_id . "')" );
						}
						elseif( $action == 'update_product' )
						{
							// Inicio products_notifications \\
							// Obtenemos el stock actual
							$aDato = tep_db_query( 'select products_quantity
													from products
													where products_id = ' . (int)$_GET['pID'] );
							$aDato = tep_db_fetch_array( $aDato );

							// Si la cantidad actual es menor que 1 y la nueva cantidad es mayor que 0 notificamos
							if( $aDato['products_quantity'] < 1 && $_POST['products_quantity'] > 0 )
							{
								// Obtenemos los clientes para el producto
								$aClientes = array();
								$aClientes = tep_db_query( 'select customers_firstname, customers_email_address, idioma
															from products_notifications
															where products_id = ' . $products_id );

								// Datos del producto
								$aProducto = tep_db_query( 'select p.products_id, pd.language_id, pd.products_name, pd.products_description, pd.products_url, p.products_quantity, p.exclude_feedmachine, p.check_stock, p.products_model, p.products_image, p.products_price, p.products_weight, p.ISBN, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_featured_until, p.products_status, p.products_featured, p.manufacturers_id
															from products  p
															inner join products_description pd on(p.products_id = pd.products_id)
															where p.products_id = ' . (int)$_GET['pID'] );
								$aProducto = tep_db_fetch_array( $aProducto );

								// Incluimos el theme del email
								require( '../' . DIR_WS_MODULES . 'UHtmlEmails/' . ULTIMATE_HTML_EMAIL_LAYOUT . '/avisador_productos.php' );

								while( $aCliente = tep_db_fetch_array( $aClientes ) )
								{
									// Cambiamos el texto del email
									if( $aCliente['idioma'] == 3 )
									{
										$sEmail = $html_email_sp;
										$sAsunto = 'Aviso de reposición de producto - ' . STORE_NAME;
									}
									else
									{
										$sEmail = $html_email_en;
										$sAsunto = 'Notice of replacement product - ' . STORE_NAME;
									}

									$sEmail = str_replace( array(
										'{NOMBRE_CLIENTE}',
										'{NOMBRE_PRODUCTO}',
										'{ATRIBUTOS_PRODUCTO}',
										'{ENLACE_PRODUCTO}'
									),array(
										$aCliente['customers_firstname'],
										$aProducto['products_name'],
										'',
										tep_catalog_href_link( 'product_info.php', 'products_id=' . $aProducto['products_id'] )
									), $sEmail );

									// Enviamos el email
									tep_mail( $aCliente['customers_firstname'], $aCliente['customers_email_address'], $sAsunto, $sEmail, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS );

									// Eliminamos al cliente de products_notifications para no avisar mas
									tep_db_query( 'delete from products_notifications where products_id = ' . $products_id );
								}
							}
							// Fin products_notifications \\

							$update_sql_data = array( 'products_last_modified' => 'now()' );
							$sql_data_array = array_merge( $sql_data_array, $update_sql_data );

							tep_db_perform( TABLE_PRODUCTS, $sql_data_array, 'update', "products_id = '" . (int)$products_id . "'" );

							// Mover producto a otra categoría desde el form de edición (campo opcional 'move_to_category_id')
							if (isset($_POST['move_to_category_id']) && (int)$_POST['move_to_category_id'] > 0 && (int)$_POST['move_to_category_id'] != (int)$current_category_id) {
								$new_cat_id = (int)$_POST['move_to_category_id'];
								$dup = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int)$products_id . "' and categories_id = '$new_cat_id'");
								$dupRow = tep_db_fetch_array($dup);
								if ($dupRow['total'] < 1) {
									tep_db_query("update " . TABLE_PRODUCTS_TO_CATEGORIES . " set categories_id = '$new_cat_id' where products_id = '" . (int)$products_id . "' and categories_id = '" . (int)$current_category_id . "'");
									$cPath = $new_cat_id;
									$current_category_id = $new_cat_id;
								}
							}
						}

						/**
						 * Guardamos caracterisiticas de ebay si las tuviera.
						 * @author Daniel Lucia <daniel.lucia@denox.es>
						 */

						 if (!empty($_POST['ebay_features'])) {
							tep_db_query('DELETE FROM ebay_features_products WHERE id_product = '. $products_id);
							foreach ($_POST['ebay_features'] as $nFeatureID => $sFeatureValue) {
								tep_db_query('INSERT INTO ebay_features_products SET id_product = '. $products_id.', id_feature = ' . $nFeatureID.', value = "'.$sFeatureValue.'", fecha = NOW()');
							}
							require ('includes/modules/ebay/includes/functions.php');
							ebayQueueAdd($products_id, 8, 'ebayReviseProduct');
						 }

						/**
						 * Guardamos productos alternativos.
						 * @author Daniel Lucia <daniel.lucia@denox.es>
						 */
						if (!empty($_POST['products_alt_id'])) {
							tep_db_query('DELETE FROM products_descat_alternativos WHERE products_id = '. $products_id);
							foreach ($_POST['products_alt_id'] as $products_alt_id) {
								tep_db_query('INSERT INTO products_descat_alternativos SET products_id = '. $products_id.', products_id_alt = ' . $products_alt_id.', date_add = NOW()');
							}
						}

						// Liquidacion productos //

						$nLiquidacion = (int)tep_db_prepare_input( $_POST['products_liquidacion'] );
						$nMinDiscount = tep_db_prepare_input( $_POST['specials_min_price'] );

						// Si hemos marcados la rebaja gradual, hemos indicado un mínimo de precio descuento y éste mínimo es más pequeño que el precio actual del producto
						if( $nLiquidacion == 1 && $nMinDiscount > 0 && $_POST['products_price'] > $nMinDiscount )
						{
							// Comprobamos si esta en oferta el producto
							$aProductoOferta = tep_db_query( 'select s.specials_id, s.specials_new_products_price, s.specials_min_price, p.products_tax_class_id, p.products_price
															  from specials s
															  inner join products p on (p.products_id = s.products_id)
															  where customers_group_id = 0 AND p.products_id = ' . (int)$products_id );

							// Si el producto está en oferta para clientes finales
							if( tep_db_num_rows( $aProductoOferta ) > 0 )
							{
								$aProductoOferta = tep_db_fetch_array( $aProductoOferta );

								// Guardamos en array el precio minimo de descuento
								$aUpdateSpecial = array( 'specials_min_price' => $nMinDiscount );

								// Si la oferta no tiene aun un mínimo
								if( $aProductoOferta['specials_min_price'] == '' )
								{
									// Rebajamos el porcentaje configurado al precio de la oferta
									$nPrecioActual = $aProductoOferta['specials_new_products_price'];
									$nSpecialPrice = $nPrecioActual - (($nPrecioActual * PERCENT_DISCOUNT_LIQUIDACION) / 100);

									// Si el precio de oferta es mas pequeño que el mínimo de precio, el precio en oferta será el mínimo indicado
									if( $nSpecialPrice < $nMinDiscount )
										$nSpecialPrice = $nMinDiscount;

									$aUpdatePriceSpecial = array( 'specials_new_products_price' => $nSpecialPrice );

									$aUpdateSpecial = array_merge( $aUpdateSpecial, $aUpdatePriceSpecial );
								}

								// Guardamos en la oferta el mínimo
								tep_db_perform( 'specials', $aUpdateSpecial, 'update', "specials_id = '" . (int)$aProductoOferta['specials_id'] . "'" );
							}
							// Si no está en oferta
							else
							{
								// Rebajamos el porcentaje configurado al precio del producto
								$nPrecioActual = tep_db_prepare_input( $_POST['products_price'] );
								$nSpecialPrice = $nPrecioActual - (($nPrecioActual * PERCENT_DISCOUNT_LIQUIDACION) / 100);

								// Si el precio de oferta es mas pequeño que el mínimo de precio, el precio en oferta será el mínimo indicado
								if( $nSpecialPrice < $nMinDiscount )
									$nSpecialPrice = $nMinDiscount;

								// Creamos oferta
								tep_db_query( 'INSERT INTO specials (products_id, specials_new_products_price, specials_min_price, specials_date_added, status, customers_group_id)
											   VALUES (' . (int)$products_id . ', "' . $nSpecialPrice . '", "' . $nMinDiscount . '", now(), 1, 0);');
							}
						}
						// FIN; Liquidacion productos //

						// BOF Bundled Products
						if ($_POST['products_bundle'] == "yes")
						{
							$to_avoid = bundle_avoid($products_id);
							$subprods = array();
							$subprodqty = array();
							tep_db_query("DELETE FROM " . TABLE_PRODUCTS_BUNDLES . " WHERE bundle_id = '" . (int)$products_id . "'");

							for ($i=0, $n=100; $i<$n; $i++)
							{
								if( isset($_POST['subproduct_' . $i . '_qty'] ) && ((int)$_POST['subproduct_' . $i . '_qty'] > 0) && !in_array($_POST['subproduct_' . $i . '_id'], $to_avoid) )
								{
									$attributes = '';
									if (isset($_POST['packs_attributes'][$i])) {
										$attributes = addslashes(json_encode($_POST['packs_attributes'][$i]));
									}

									if( in_array($_POST['subproduct_' . $i . '_id'], $subprods) )
									{
										$subprodqty[$_POST['subproduct_' . $i . '_id']] += (int)$_POST['subproduct_' . $i . '_qty'];
										$sql = 'UPDATE ' . TABLE_PRODUCTS_BUNDLES . ' set subproduct_qty = ' . (int)$subprodqty[$_POST['subproduct_' . $i . '_id']] . ', attributes = "'.$attributes.'" where bundle_id = ' . (int)$products_id . ' and subproduct_id = ' . (int)$_POST['subproduct_' . $i . '_id'];

										tep_db_query($sql);
									}
									else
									{
										$subprods[] = $_POST['subproduct_' . $i . '_id'];
										$subprodqty[$_POST['subproduct_' . $i . '_id']] = (int)$_POST['subproduct_' . $i . '_qty'];
										$sql = 'INSERT INTO ' . TABLE_PRODUCTS_BUNDLES . ' (bundle_id, subproduct_id, subproduct_qty, attributes) VALUES ("' . (int)$products_id . '", "' . (int)$_POST['subproduct_' . $i . '_id'] . '", "' . (int)$_POST['subproduct_' . $i . '_qty'] . '", "'.$attributes.'")';

										tep_db_query($sql);
									}
								}
							}

							// not a bundle if no subproducts set
							if( empty($subprods) )
							{
								tep_db_query('update ' . TABLE_PRODUCTS . ' set products_bundle = "no" where products_id = ' . (int)$products_id);
							}
							else
							{
								// calculate total weight from subproducts
								$weight = 0;
								foreach ($subprodqty as $id => $qty)
								{
									$subprod_query = tep_db_query('select products_weight from ' . TABLE_PRODUCTS . ' where products_id = ' . (int)$id);
									$subprod = tep_db_fetch_array($subprod_query);
									$weight += ($subprod['products_weight'] * $qty);
								} // quantity is set to one only so bundle can be sold, actual quantity is determined by other means

								tep_db_query('update ' . TABLE_PRODUCTS . ' set products_quantity = 1, products_weight = "' . tep_db_input($weight) . '" where products_id = ' . (int)$products_id);
							}
						}
						// EOF Bundled Products

						// Inicio, imagenes
						if( array_key_exists( 'products_subimages', $_POST ) )
						{
							$aInsertImages = array();
							$aImagenesSelect = array();
							$sTituloProducto = getSlug( $_POST["products_name"][3] );
							$nContImages = 0;

							// Borramos thumbs si nos han enviado imagen
							$aFiles = glob( getcwd() . '/../images/productos/thumbnails/' . $sTituloProducto . '-' . $products_id . '_thumb*' );

							// Recorremos para eliminar
							if( is_array( $aFiles ) )
								foreach( $aFiles as $sFile )
									@unlink( $sFile );

							// Array con los numeros consecutivos para controlar el numero de subida del fichero
							$aNumeros = range( 1, 50 );

							// Numeros libres para subir
							$aNumerosLibres = $aNumeros;

							// Si estamos editando seleccionamos que números libres tenemos
							if( $action == 'update_product' )
							{
								// Array auxiliar para añadir las imagenes
								$aAux = array();
								// Array para añadir los numeros que tenemos cogidos
								$aAuxNumero = array();

								// Obtenemos las imagenes
								$aDatos = tep_db_query( 'select products_image, products_subimages from products where products_id = ' . $products_id );
								$aDato = tep_db_fetch_array( $aDatos );

								// Imagen principal
								$aAux[] = $aDato['products_image'];

								// Si es un JSON valido
								if( is_string( $aDato['products_subimages'] ) && is_json( $aDato['products_subimages'] ) )
								{
									// Añadimos las imagenes al array auxiliar para recorrer las imagenes
									$aImagenesSelect = json_decode( $aDato['products_subimages'] );
									$aAux = array_merge( $aAux, $aImagenesSelect );
								}

								// Recorremos para seleccionar los numeros que tenemos ya cogidos
								foreach( $aAux as $sFile )
								{
									if( $sFile === null ) continue;
									// Limpiamos y asignamos numero
									$sFile = preg_replace( '/-' . $products_id . '\..+/i', '', $sFile );
									$sFile = preg_replace( '/^.+-/i', '', $sFile );
									if( is_numeric( $sFile ) )
										$aAuxNumero[] = $sFile;
								}

								// Recorremos para eliminar los numeros ocupados
								for( $nCont = 0, $nControl = 0; $nCont < count( $aAuxNumero ); $nCont++ )
									unset( $aNumerosLibres[($aAuxNumero[$nCont] - 1)] );

								// Reindexamos el array para empezar por 0
								$aNumerosLibres = array_values( array_filter( $aNumerosLibres ) );
							}

							// Recorremos las imagenes y las subimos
							foreach( $_POST['products_subimages'] as $sImagen )
							{
								$sExtension = '.' . preg_replace( '/\;base64\,.+$|data\:|image\//i', '', $sImagen );
								$sImagen = preg_replace( '/^.+\,/i', '', $sImagen );
								$sAux = $sTituloProducto . '-' . $aNumerosLibres[$nContImages] . '-' . $products_id . $sExtension;

								file_put_contents( getcwd() . '/../images/productos/' . $sAux, base64_decode( $sImagen ) );

								$aInsertImages[] = $sAux;

								$nContImages++;
							}

							// Obtenemos la primera imagen, si estamos insertando y existen imagenes O estamos editando y no tenemos ninguna imagen en database y si subida
							if( ($action == 'insert_product' && count( $aInsertImages ) > 0) || ($action == 'update_product' && count( $aInsertImages ) > 0 && ($aDato['products_image'] == '' || $aDato['products_image'] == null || $aDato['products_image'] == NULL)) )
							{
								$sql_data_array['products_image'] = tep_db_prepare_input( $aInsertImages[0] );
								array_shift( $aInsertImages );
							}

							// Unimos los dos array para añadirlo a base de datos
							$aAux = array_merge( $aImagenesSelect, $aInsertImages );
							$sql_data_array['products_subimages'] = tep_db_prepare_input( json_encode( $aAux ) );

							// Actualizamos
							tep_db_perform( 'products', $sql_data_array, 'update', "products_id = '" . (int)$products_id . "'" );
						}
						// Fin, imagenes

						// BOF Separate Pricing Per Customer
						$customers_group_query = tep_db_query( "select customers_group_id, customers_group_name from " . TABLE_CUSTOMERS_GROUPS . " where customers_group_id != '0' order by customers_group_id" );

						while( $customers_group = tep_db_fetch_array( $customers_group_query ) ) // Gets all of the customers groups
						{
							$attributes_query = tep_db_query("select customers_group_id, customers_group_price, products_qty_blocks, products_min_order_qty from " . TABLE_PRODUCTS_GROUPS . " where ((products_id = '" . $products_id . "') && (customers_group_id = " . $customers_group['customers_group_id'] . ")) order by customers_group_id");
							$attributes = tep_db_fetch_array($attributes_query);

							// set default values for quantity blocks and min order quantity
							$pg_products_qty_blocks = 1;
							$pg_products_min_order_qty = 1;
							$delete_row_from_pg = false;

							if( isset( $_POST['products_qty_blocks'][$customers_group['customers_group_id']] ) && (int)$_POST['products_qty_blocks'][$customers_group['customers_group_id']] > 1 )
								$pg_products_qty_blocks = (int)$_POST['products_qty_blocks'][$customers_group['customers_group_id']];

							if( isset( $_POST['products_min_order_qty'][$customers_group['customers_group_id']] ) && (int)$_POST['products_min_order_qty'][$customers_group['customers_group_id']] > 1 )
								$pg_products_min_order_qty = (int)$_POST['products_min_order_qty'][$customers_group['customers_group_id']];

							if( $_POST['sppcprice' . $customers_group['customers_group_id']] == '' && $pg_products_qty_blocks == 1 && $pg_products_min_order_qty == 1 )
								$delete_row_from_pg = true; // no need to have default values for qty blocks and min order qty in the table

							if( $_POST['sppcprice' . $customers_group['customers_group_id']] == '' )
								$pg_cg_group_price = 'null';
							else
								$pg_cg_group_price = "'" . (float)$_POST['sppcprice' . $customers_group['customers_group_id']] . "'";

							if( tep_db_num_rows($attributes_query) > 0 && $delete_row_from_pg == false )
							{
								// there is already a row inserted in products_groups, update instead of insert
								if( $_POST['sppcoption'][$customers_group['customers_group_id']] )
								{ // this is checking if the check box is checked
									tep_db_query( "update " . TABLE_PRODUCTS_GROUPS . " set customers_group_price = " . $pg_cg_group_price . ", products_qty_blocks = " . $pg_products_qty_blocks . ", products_min_order_qty = " . $pg_products_min_order_qty . " where customers_group_id = '" . $attributes['customers_group_id'] . "' and products_id = '" . $products_id . "'" );
								}
								else
									tep_db_query("delete from " . TABLE_PRODUCTS_GROUPS . " where customers_group_id = '" . $customers_group['customers_group_id'] . "' and products_id = '" . $products_id . "'");
							}
							elseif( tep_db_num_rows( $attributes_query ) > 0 && $delete_row_from_pg == true )
								tep_db_query( "delete from " . TABLE_PRODUCTS_GROUPS . " where customers_group_id = '" . $customers_group['customers_group_id'] . "' and products_id = '" . $products_id . "'" );
							elseif( $_POST['sppcoption'][$customers_group['customers_group_id']] && $delete_row_from_pg == false )
								tep_db_query( "insert into " . TABLE_PRODUCTS_GROUPS . " (products_id, customers_group_id, customers_group_price, products_qty_blocks, products_min_order_qty) values ('" . $products_id . "', '" . $customers_group['customers_group_id'] . "', " . $pg_cg_group_price . ", " . $pg_products_qty_blocks . ", " . $pg_products_min_order_qty . ")" );

						} // end while ($customers_group = tep_db_fetch_array($customers_group_query))
						// EOF entries in products_groups



						// Rapels de descuentos por cantidades
						foreach( $_POST['products_price_break'] as $pbb_cg_id => $price_break_array )
						{
							foreach( $price_break_array as $key1 => $products_price )
							{
								$pb_action = 'insert'; // re-set default to insert
								$where_clause = '';

								if( isset($_POST['products_delete'][$pbb_cg_id][$key1]) && $_POST['products_delete'][$pbb_cg_id][$key1] == 'y' && isset($_POST['products_price_break_id'][$pbb_cg_id][$key1]) )
								{
									$delete_from_ppb_array[] = (int)$_POST['products_price_break_id'][$pbb_cg_id][$key1];
									continue;
								}

								if( !tep_not_null( $products_price ) )
									continue; // if price is empty this price break is unused
								elseif( ! tep_not_null( $_POST['products_qty'][$pbb_cg_id][$key1] ) )
									continue; // if qty is not entered we will not update or insert this in the table
								else
								{
									$sql_price_break_data_array = array(
										'products_id' => (int)$products_id,
										'products_price' => (float)$products_price,
										'products_qty' => (int)$_POST['products_qty'][$pbb_cg_id][$key1],
										'customers_group_id' => $pbb_cg_id
									);

									if( isset($_POST['products_price_break_id'][$pbb_cg_id][$key1]) && (int)$_POST['products_price_break_id'][$pbb_cg_id][$key1] > 0 )
									{
										$pb_action = 'update';
										$where_clause = " products_price_break_id = '" . (int)$_POST['products_price_break_id'][$pbb_cg_id][$key1] . "'";
									}

									tep_db_perform(TABLE_PRODUCTS_PRICE_BREAK, $sql_price_break_data_array, $pb_action, $where_clause);
								} // end if/else (!tep_not_null($products_price))
							} // end foreach ($price_break_array as $key1 => $products_price)
						} // end foreach ($_POST['products_price_break'] as $pbb_cg_id => $price_break_array)

						// delete the unwanted price breaks using their products_price_break_id's
						if( isset($delete_from_ppb_array) && sizeof($delete_from_ppb_array > 0) && tep_not_null($delete_from_ppb_array[0]) )
							tep_db_query("delete from " . TABLE_PRODUCTS_PRICE_BREAK . " where products_price_break_id in (" . implode(',', $delete_from_ppb_array) . ")");
						// EOF entries in products_price_break
						// EOF QPBPP for SPPC

						/** AJAX Attribute Manager  **/
						require_once( 'attributeManager/includes/attributeManagerUpdateAtomic.inc.php' );
						/** AJAX Attribute Manager  end **/

						$languages = tep_get_languages();

						for( $i = 0, $n = sizeof($languages); $i < $n; $i++)
						{
							$language_id = $languages[$i]['id'];

							$sql_data_array = array( 'products_name' => tep_db_prepare_input($_POST['products_name'][$language_id]),
													 // Start Products Specifications
													 'products_tab_1' => tep_db_prepare_input ($_POST['products_tab_1'][$language_id]),
													 'products_tab_2' => tep_db_prepare_input ($_POST['products_tab_2'][$language_id]),
													 'products_tab_3' => tep_db_prepare_input ($_POST['products_tab_3'][$language_id]),
													 'products_tab_4' => tep_db_prepare_input ($_POST['products_tab_4'][$language_id]),
													 'products_tab_5' => tep_db_prepare_input ($_POST['products_tab_5'][$language_id]),
													 'products_tab_6' => tep_db_prepare_input ($_POST['products_tab_6'][$language_id]),
													 // End Products Specifications
													 'products_seo_title' => tep_db_prepare_input($_POST['products_seo_title'][$language_id]),
													 'products_seo_keywords' => tep_db_prepare_input($_POST['products_seo_keywords'][$language_id]),
													 'products_seo_description' => tep_db_prepare_input($_POST['products_seo_description'][$language_id]),
													 'products_description' => tep_db_prepare_input($_POST['products_description'][$language_id]),
													 'products_url' => tep_db_prepare_input($_POST['products_url'][$language_id]),
													 'products_seo_url' => tep_db_prepare_input($_POST['products_seo_url'][$language_id]) );

							if( $action == 'insert_product' )
							{
								$insert_sql_data = array( 'products_id' => $products_id,
														  'language_id' => $language_id );

								$sql_data_array = array_merge( $sql_data_array, $insert_sql_data );

								tep_db_perform( TABLE_PRODUCTS_DESCRIPTION, $sql_data_array, 'insert', '', 'db_link', true );
							}
							elseif( $action == 'update_product' )
								tep_db_perform( TABLE_PRODUCTS_DESCRIPTION, $sql_data_array, 'update', "products_id = '" . (int)$products_id . "' and language_id = '" . (int)$language_id . "'", 'db_link', true );
						}

						// Start Products Specifications
						for($i=0, $n=sizeof($languages); $i<$n; $i++)
						{
							// print 'Current Category ID: ' . $current_category_id; die;
							$language_id = $languages[$i]['id'];
							$specifications_query_raw = "select s.specifications_id
														 from " . TABLE_SPECIFICATION . " s
														 join " . TABLE_SPECIFICATIONS_TO_CATEGORIES . " sg2c on (sg2c.specification_group_id = s.specification_group_id and sg2c.categories_id = '" . (int) $current_category_id . "')";
							$specifications_query = tep_db_query ($specifications_query_raw);

							$count_specificatons = tep_db_num_rows ($specifications_query);

							if ($count_specificatons > 0)
							{
								// print 'Specifications Exist!'; die;
								while( $specifications = tep_db_fetch_array ($specifications_query) )
								{
									$specifications_id = (int) $specifications['specifications_id'];

									tep_db_query ("delete from " . TABLE_PRODUCTS_SPECIFICATIONS . " where products_id = '" . (int) $products_id . "' and specifications_id = '" . $specifications_id . "' and language_id = '" . $language_id . "'");

									$specification = $_POST['products_specification'][$specifications_id][$language_id];
									if( is_array ($specification) )
									{
										foreach($specification as $each_specification)
										{
											$each_specification = tep_db_prepare_input ($each_specification);
											if ($each_specification != '')
											{
												$sql_data_array = array ('specification' => $each_specification,
																		 'products_id' => $products_id,
																		 'specifications_id' => $specifications_id,
																		 'language_id' => $language_id
												);

												tep_db_perform (TABLE_PRODUCTS_SPECIFICATIONS, $sql_data_array);
											} // if ($each_specification
										} // foreach ($specification
									}
									else
									{
										$specification = tep_db_prepare_input ($specification);
										if( $specification != '' )
										{
											$sql_data_array = array ('specification' => $specification,
																	 'products_id' => $products_id,
																	 'specifications_id' => $specifications_id,
																	 'language_id' => $language_id );

											tep_db_perform (TABLE_PRODUCTS_SPECIFICATIONS, $sql_data_array);
										} // if ($specification
									} //  if (is_array ... else ...
								} // while ($specifications
							} // if ($count_specificatons
						} // for ($i=0
						// End Products Specifications


						// Inicio, repuestos
						$sPostRpImage = tep_db_prepare_input( $_POST['rp_image'] );

						// Si contenemos imagen subimos
						if( $sPostRpImage != '' )
						{
							// Comprobamos si existe imagen si es asi eliminamos antes
							$aImagenes = glob( getcwd() . '/../images/repuestos/' . $products_id . '-*' );

							// Si existe eliminamos
							if( count( $aImagenes ) > 0 )
							{
								@unlink( $aImagenes[0] );
								@unlink( $aImagenes[1] );
							}

							// Subimos la imagen
							if( $sPostRpImage != 'eliminar' )
							{
								$sExtension = '.' . preg_replace( '/\;base64\,.+$|data\:|image\//i', '', $sPostRpImage );
								$sPostRpImage = preg_replace( '/^.+\,/i', '', $sPostRpImage );
								$sAux = $products_id . '-imagen' . $sExtension;
								file_put_contents( getcwd() . '/../images/repuestos/' . $sAux, base64_decode( $sPostRpImage ) );
							}
						}

						// Eliminamos todos los repuestos del producto
						tep_db_query( 'delete from repuesto where products_id = ' . $products_id );

						// Si existen repuestos
						if( array_key_exists( 'rp_alias', $_POST ) )
						{

							// Recorremos los repuestos para insertarlos
							foreach( $_POST['rp_alias'] as $key => $value )
							{
								$attributes = '';

								if (isset($_POST['respuestos_attributes'][$key])) {
									$attributes = addslashes(json_encode($_POST['respuestos_attributes'][$key]));
								}

								$aSql = array(
									'products_id' => $products_id,
									'products_id_repuesto' => $_POST['rp_products_id_repuesto'][$key],
									'x' => $_POST['rp_x'][$key],
									'y' => $_POST['rp_y'][$key],
									'alias' => $_POST['rp_alias'][$key],
									'posicion' => $_POST['rp_posicion'][$key],
									'size' => $_POST['rp_size'][$key],
									'attributes' => $attributes,
								);

								tep_db_perform( 'repuesto', $aSql );

							}

							// Crear imagen GD //
							// Obtenemos la imagen
							// Comprobamos si existe imagen si es asi eliminamos antes
							$aImagenes = glob( getcwd() . '/../images/repuestos/' . $products_id . '-imagen.*' );
							$sImagen = $aImagenes[0];

							// Informacion de la imagen
							$aImageInfo = @getimagesize( $sImagen );

							// Segun el mime realizamos una instancia diferente
							switch( $aImageInfo['mime'] )
							{
								case 'image/jpeg':
									$gdImage = imagecreatefromjpeg( $sImagen );
								break;

								case 'image/gif':
									$gdImage = imagecreatefromgif( $sImagen );
								break;

								case 'image/png':
									$gdImage = imagecreatefrompng( $sImagen );
								break;
							}

							// Creamos circulo
							$gdImageCircle = imagecreatefrompng( getcwd() . '/images/repuestos_circle_gd.png' );
							imagealphablending( $gdImageCircle, true );
							imagesavealpha( $gdImageCircle, true );
							$gdCircle = imagecreatetruecolor( 29, 29 );
							imagealphablending( $gdCircle, false );
							imagesavealpha( $gdCircle, true );
							imagecopyresampled( $gdCircle, $gdImageCircle, 0, 0, 0, 0, 29, 29, 29, 29 );

							require_once ( DIR_WS_FUNCTIONS . 'gd.php' );

							// Fuente para el alias
							$gdWhite = imagecolorallocate($gdImage, 0, 0, 0);
							$sFontPath = getcwd() . '/includes/arial.ttf';

							// Recorremos para posicionar los circulos
							foreach( $_POST['rp_alias'] as $key => $value )
							{
								// Variables
								$sX = $_POST['rp_x'][$key];
								$sY = $_POST['rp_y'][$key];
								$sAlias = $_POST['rp_alias'][$key];
								$sPosicion = $_POST['rp_posicion'][$key];
								$sSize = $_POST['rp_size'][$key];

								// Creamos la linea
								switch($sPosicion)
								{
									case 'top':
									case 'bottom':
										$sSize -= 29;
										$gdImageLine = @imagecreate( 1, $sSize );

										/**
										 * Compruebo para que no de errores
										 * #FFA-144-30288
										 * @author Daniel Lucia <daniel.lucia@denox.es>
										 */
										if ($gdImageLine) {
											$imRelleno = imagecolorallocate( $gdImageLine, 183, 183, 183 );
											imagefilledrectangle( $gdImageLine, 0, 0, 1, $sSize, $imRelleno );
											imagecopyresampled( $gdImage, $gdImageLine, $sX + 14.5, ($sPosicion == 'bottom' ? $sY : $sY + 28), 0, 0, 1, $sSize, 1, $sSize );
										}

									break;

									case 'dia_sup_drch':
										// Quitamos tamaños para cuadrar el circulito
										$sSize -= 14.5;
										$sY += 14.5;

										$gdImageLine = @ImageCreateTrueColor( $sSize, $sSize );
										$imRelleno = imagecolorallocate( $gdImageLine, 183, 183, 183 );
										ImageFilledRectangle($gdImageLine, 0, 0, $sSize, $sSize, $imRelleno);
										ImageLine($gdImage, $sX, $sY + $sSize, $sX + $sSize, $sY, $imRelleno);

										// Restauramos tamaños
										$sSize += 14.5;
										$sX += $sSize -29;
										$sY -= 14.5;
									break;

									case 'derecha':
										$sSize -= 14.5;
										$sX -= 14.5;

										$gdImageLine = @imagecreate( $sSize, 1 );
										$imRelleno = imagecolorallocate( $gdImageLine, 183, 183, 183 );
										imagefilledrectangle( $gdImageLine, 0, 0, $sSize, 1, $imRelleno );
										imagecopyresampled( $gdImage, $gdImageLine, $sX + 14.5, $sY + 14.5, 0, 0, $sSize, 1, $sSize, 1 );

										// Restauramos tamaños
										$sX += $sSize;
									break;

									case 'dia_inf_drch':
										$sSize -= 14.5;
										$gdImageLine = @ImageCreateTrueColor( $sSize, $sSize );
										$imRelleno = imagecolorallocate( $gdImageLine, 183, 183, 183 );
										ImageFilledRectangle($gdImageLine, 0, 0, $sSize, $sSize, $imRelleno);
										ImageLine($gdImage, $sX, $sY, $sX + $sSize, $sY + $sSize, $imRelleno);

										// Restauramos tamaños
										$sSize += 14.5;
										$sX += $sSize - 29;
										$sY += $sSize - 29;
									break;

									case 'dia_inf_izqd':
										// Quitamos tamaños para cuadrar el circulito
										$sSize -= 14.5;
										$sX += 14.5;

										$gdImageLine = @ImageCreateTrueColor( $sSize, $sSize );
										$imRelleno = imagecolorallocate( $gdImageLine, 183, 183, 183 );
										ImageFilledRectangle($gdImageLine, 0, 0, $sSize, $sSize, $imRelleno);
										ImageLine($gdImage, $sX, $sY + $sSize, $sX + $sSize, $sY, $imRelleno);

										// Restauramos tamaños
										$sSize += 14.5;
										$sX -= 14.5;
										 $sY += $sSize -29;
									break;

									case 'izquierda':
										$gdImageLine = @imagecreate( $sSize, 1 );
										$imRelleno = imagecolorallocate( $gdImageLine, 183, 183, 183 );
										imagefilledrectangle( $gdImageLine, 0, 0, $sSize, 1, $imRelleno );
										imagecopyresampled( $gdImage, $gdImageLine, $sX + 14.5, $sY + 14.5, 0, 0, $sSize, 1, $sSize, 1 );
									break;

									case 'dia_sup_izqd':
										// Quitamos tamaños para cuadrar el circulito
										$sSize -= 14.5;
										$sX += 14.5;
										$sY += 14.5;

										$gdImageLine = @ImageCreateTrueColor( $sSize, $sSize );
										$imRelleno = imagecolorallocate( $gdImageLine, 183, 183, 183 );
										ImageFilledRectangle($gdImageLine, 0, 0, $sSize, $sSize, $imRelleno);
										ImageLine($gdImage, $sX, $sY, $sX + $sSize, $sY + $sSize, $imRelleno);

										// Restauramos tamaños
										$sSize += 14.5;
										$sX -= 14.5;
										$sY -= 14.5;
									break;
								}

								// Creamos el circulo
								imagecopyresampled( $gdImage, $gdCircle, $sX, ($sPosicion == 'bottom' ? ($sY + $sSize - 1) : $sY), 0, 0, 29, 29, 29, 29 );
								imagettftextbox( $gdImage, 15, 0, $sX, ($sY - 15) + ($sPosicion == 'bottom' ? $sSize : 0), array( 255, 255, 255 ), $sFontPath, $sAlias, 29, 'center', 0, 29 );
							}

							// Guardamos la imagen
							imagejpeg($gdImage, getcwd() . '/../images/repuestos/' . $products_id . '-imagen-gd.jpg', 100);
							imagedestroy($gdImage);
						}
						// Fin, repuestos


						if( USE_CACHE == 'true' )
						{
							tep_reset_cache_block('categories');
							tep_reset_cache_block('also_purchased');
							tep_reset_cache_block('xsell_products');
						}

						if( $_POST['products_specials'] == 1 )
							tep_redirect( tep_href_link('specials.php', 'action=new&pID=' . $products_id . '&cPath=' . $cPath) );
						elseif( $_POST['products_specials'] == 4 )
							tep_redirect( tep_href_link('specials.php', 'sID=' . $_POST['sID'] . '&action=edit&pID=' . $products_id . '&cPath=' . $cPath) );
						elseif( $_POST['products_specials_delete'] == 'delete' )
						{
							// Eliminamos la oferta
							tep_db_query("delete from " . TABLE_SPECIALS . " where specials_id = '" . $_POST['sID'] . "'");

							tep_redirect( tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $products_id) );
                        } elseif (isset( $_POST['ireturn'] ) && $_POST['ireturn'] == 1) {
                            // Si guardamos cambios
                            tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&page=' . $_GET['page'] . '&pID=' . $products_id . '&menu-tab=' . $_POST['menu-tab'] . '&action=new_product'));
                        } else {
								// Calculamos la pagina
								$nCategory = explode('_', (string) $cPath);
								$nCategory = $nCategory[count($nCategory) - 1];
								$sFind     = tep_db_query('SELECT position FROM (SELECT @number := @number + 1 AS position, t2.* FROM (SELECT @number := 0, pd.products_id FROM products_description pd INNER JOIN products_to_categories p2c ON (pd.products_id = p2c.products_id) WHERE pd.language_id = ' . (int) $languages_id . ' AND p2c.categories_id = "' . $nCategory . '" GROUP BY pd.products_id ORDER BY pd.products_name ASC) t2) t3 WHERE products_id = ' . $products_id . ';');
								$aFind     = tep_db_fetch_array($sFind);
								$sPagina   = ceil($aFind['position'] / 50);

								tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&page=' . $sPagina . '&pID=' . $products_id));
							}
					}
				}
            break;

            case 'copy_to_confirm':
                if( isset($_POST['products_id']) && isset($_POST['categories_id']) )
                {
                    $products_id = tep_db_prepare_input($_POST['products_id']);
                    $categories_id = tep_db_prepare_input($_POST['categories_id']);

                    if( $_POST['copy_as'] == 'link' )
                    {
                        if( $categories_id != $current_category_id )
                        {
                            $check_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int)$products_id . "' and categories_id = '" . (int)$categories_id . "'");
                            $check = tep_db_fetch_array($check_query);

                            if( $check['total'] < '1' )
                                tep_db_query("insert into " . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) values ('" . (int)$products_id . "', '" . (int)$categories_id . "')");
                        }
                        else
                            $messageStack->add_session(ERROR_CANNOT_LINK_TO_SAME_CATEGORY, 'error');
                    }
                    elseif( $_POST['copy_as'] == 'duplicate' )
                    {
                        $product_query = tep_db_query("select products_quantity, products_quantity_deseada, exclude_feedmachine, check_stock, products_fileupload, products_pdfupload, products_model, products_youtube, products_image, products_subimages, products_price, products_cost, products_date_available, products_featured_until, products_weight, ISBN, products_tax_class_id, manufacturers_id, products_to_rss, amazon_status, products_liquidacion, product_ean, reference_prov, products_qty_blocks, products_min_order_qty, products_ship_free, shipping_methods, payment_methods, products_bundle, sold_in_bundle_only from " . TABLE_PRODUCTS . " where products_id = '" . (int)$products_id . "'");
                        $product = tep_db_fetch_array($product_query);

                        tep_db_query( "insert into " . TABLE_PRODUCTS . "
						(
							products_fileupload,
							products_pdfupload,
							products_quantity,
							products_quantity_deseada,
							check_stock,
							exclude_feedmachine,
							products_model,
							products_youtube,
							products_price,
							products_cost,
							products_date_added,
							products_date_available,
							products_featured_until,
							products_weight,
							ISBN,
							products_status,
							products_featured,
							products_tax_class_id,
							manufacturers_id,
							products_to_rss,
							amazon_status,
							products_liquidacion,
							product_ean,
							reference_prov,
							products_qty_blocks,
							products_min_order_qty,
							products_ship_free,
							shipping_methods,
							payment_methods,
							products_bundle,
							sold_in_bundle_only
						)
						values
						(
							'" . tep_db_input('products_fileupload') . "',
							'" . tep_db_input('products_pdfupload'). "',
							'" . tep_db_input($product['products_quantity']) . "',
							'" . tep_db_input($product['products_quantity_deseada']) . "',
							'" . tep_db_input($product['check_stock']) . "',
							'" . tep_db_input($product['exclude_feedmachine']) . "',
							'" . tep_db_input($product['products_model']) . "',
							'" . tep_db_input($product['products_youtube']) . "',
							'" . tep_db_input($product['products_price']) . "',
							'" . tep_db_input($product['products_cost']) . "',
							now(),
							'" . tep_db_input($product['products_date_available']) . "',
							'" . tep_db_input($product['products_featured_until']) . "',
							'" . tep_db_input($product['products_weight']) . "',
							'" . tep_db_input($product['ISBN']) . "',
							'1',
							'0',
							'" . (int)$product['products_tax_class_id'] . "',
							'" . (int)$product['manufacturers_id'] . "',
							'" . (int)$product['products_to_rss'] . "',
							'" . (int)$product['amazon_status'] . "',
							'" . (int)$product['products_liquidacion'] . "',
							'" . $product['product_ean'] . "',
							'" . $product['reference_prov'] . "',
							'" . (int)$product['products_qty_blocks'] . "',
							'" . (int)$product['products_min_order_qty'] . "',
							'" . (int)$product['products_ship_free'] . "',
							'" . $product['shipping_methods'] . "',
							'" . $product['payment_methods'] . "',
							'" . tep_db_input($product['products_bundle']) . "',
							'" . tep_db_input($product['sold_in_bundle_only']) . "'
						)");

                        $dup_products_id = tep_db_insert_id();

						// Inicio, imagenes
						$sImagen = $product['products_image'];
						$aAuxImages = array();
						$sFileNameProductsImage = '';

						// Si contiene imagen principal
						if( $sImagen != '' && file_exists( getcwd() . '/../images/productos/' . $sImagen ) )
						{

							$sExtension = preg_replace( '/^.+\./i', '', $sImagen );
							$sFileNameProductsImage = preg_replace( '/\.' . $sExtension . '$/i', '', $sImagen );
							$sIdLast = preg_replace( '/^.+-/i', '', $sFileNameProductsImage );
							$sFileNameProductsImage = preg_replace( '/-' . $sIdLast . '$/i', '', $sFileNameProductsImage ) . '-' . $dup_products_id . '.' . $sExtension;

							// Copiamos la imagen duplicada
							copy( getcwd() . '/../images/productos/' . $sImagen, getcwd() . '/../images/productos/' . $sFileNameProductsImage );
						}

						if( is_string( $product['products_subimages'] ) && is_json( $product['products_subimages'] ) )
						{
							$aImagenes = json_decode( $product['products_subimages'] );

							foreach( $aImagenes as $sImagen )
							{
								$sExtension = preg_replace( '/^.+\./i', '', $sImagen );
								$sNameAux = preg_replace( '/\.' . $sExtension . '$/i', '', $sImagen );
								$sIdLast = preg_replace( '/^.+-/i', '', $sNameAux );
								$sNameAux = preg_replace( '/-' . $sIdLast . '$/i', '', $sNameAux ) . '-' . $dup_products_id . '.' . $sExtension;

								// Copiamos la imagen duplicada
								copy( getcwd() . '/../images/productos/' . $sImagen, getcwd() . '/../images/productos/' . $sNameAux );
								$aAuxImages[] = $sNameAux;
							}
						}

						// Actualizamos
						tep_db_perform( 'products', array( 'products_subimages' => tep_db_input( json_encode( $aAuxImages) ), 'products_image' => $sFileNameProductsImage ), 'update', 'products_id = "' . (int)$dup_products_id . '"'  );
						// Fin, imagenes

						// Inicio, repuestos
						// Consultamos los repuestos
						$aDatos = tep_db_query( 'select * from repuesto where products_id = ' . $products_id );

						if( tep_db_num_rows( $aDatos ) > 0 )
						{
							while( $aDato = tep_db_fetch_array( $aDatos ) )
							{
								$aSql = array(
									'products_id' => $dup_products_id,
									'products_id_repuesto' => $aDato['products_id_repuesto'],
									'x' => $aDato['x'],
									'y' => $aDato['y'],
									'alias' => $aDato['alias'],
									'posicion' => $aDato['posicion'],
									'size' => $aDato['size']
								);

								tep_db_perform( 'repuesto', $aSql );
							}

							$aImagenes = glob( getcwd() . '/../images/repuestos/' . $products_id . '-imagen*' );

							foreach( $aImagenes as $key => $sImagen )
							{
								$sExtension = preg_replace( '/^.+\./i', '', $sImagen );

								copy( $sImagen, getcwd() . '/../images/repuestos/' . $dup_products_id . '-imagen' . (preg_match('/-gd/i', $sImagen) ? '-gd' : '') . '.' . $sExtension );
							}
						}
						// Fin, repuestos

						// bundled products begin
						if ($product['products_bundle'] == 'yes')
						{
							$bundle_query = tep_db_query('select subproduct_id, subproduct_qty from ' . TABLE_PRODUCTS_BUNDLES . ' where bundle_id = ' . (int)$products_id);
							while ($subprod = tep_db_fetch_array($bundle_query))
							{
								tep_db_query('insert into ' . TABLE_PRODUCTS_BUNDLES . " (bundle_id, subproduct_id, subproduct_qty) VALUES ('" . (int)$dup_products_id . "', '" . (int)$subprod['subproduct_id'] . "', '" . (int)$subprod['subproduct_qty'] . "')");
							}
						}
						// bundled products end

                        $description_query = tep_db_query( "select language_id, products_name, products_seo_url, products_description, products_url, products_tab_1, products_tab_2, products_tab_3, products_tab_4, products_tab_5, products_tab_6 from " . TABLE_PRODUCTS_DESCRIPTION . " where products_id = '" . (int)$products_id . "'" );

                        while( $description = tep_db_fetch_array($description_query ) )
                            tep_db_query( "insert into " . TABLE_PRODUCTS_DESCRIPTION . " (products_id, language_id, products_name, products_seo_url, products_description, products_url, products_viewed, products_tab_1, products_tab_2, products_tab_3, products_tab_4, products_tab_5, products_tab_6) values ('" . (int)$dup_products_id . "', '" . (int)$description['language_id'] . "', '" . tep_db_input($description['products_name']) . "', '" . tep_db_input($description['products_seo_url']) . "','" . tep_db_input($description['products_description']) . "', '" . tep_db_input($description['products_url']) . "', '0', '" . tep_db_input ($description['products_tab_1']) . "', '" . tep_db_input ($description['products_tab_2']) . "', '" . tep_db_input ($description['products_tab_3']) . "', '" . tep_db_input ($description['products_tab_4']) . "', '" . tep_db_input ($description['products_tab_5']) . "', '" . tep_db_input ($description['products_tab_6']) . "')" );

                        //Duplicar productos relacionados DENOX
			$related_query = tep_db_query( "select * from products_related_products where pop_products_id_master = '" . (int)$products_id . "'" );

                        while( $related = tep_db_fetch_array( $related_query ) )
                            tep_db_query( "insert into products_related_products (pop_products_id_master, pop_products_id_slave, pop_order_id) values ('" . (int)$dup_products_id . "', '" . tep_db_input($related['pop_products_id_slave']) . "', '" . tep_db_input($related['pop_order_id']) . "')" );
                        //Fin duplicar productos

                        tep_db_query( "insert into " . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) values ('" . (int)$dup_products_id . "', '" . (int)$categories_id . "')" );

						// Start Products Specifications
						$specifications_query = tep_db_query ("select specifications_id, language_id, specification from " . TABLE_PRODUCTS_SPECIFICATIONS . " where products_id = '" . (int)$products_id . "'");

						while ($specifications = tep_db_fetch_array ($specifications_query) )
						{
							tep_db_query ("insert into " . TABLE_PRODUCTS_SPECIFICATIONS . " (products_id, specifications_id, language_id, specification) values ( '" . (int) $dup_products_id . "', '" . (int) $specifications['specification_description_id'] . "', '" . (int)$specifications['language_id'] . "', '" . tep_db_input ($specifications['specification']) . "')");
						} // while ($specifications
						// End Products Specifications

						// Duplicar attributos
						$products_copy_from_query= tep_db_query("select * from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id='" . $products_id . "'");

						while ( $products_copy_from=tep_db_fetch_array($products_copy_from_query) )
						{
							tep_db_query( 'insert into products_attributes(
										products_attributes_id,
										products_id,
										options_id,
										options_values_id,
										options_values_price,
										price_prefix,
										attributes_hide_from_groups,
										products_options_sort_order,
										reference
									)
									values(
										"",
										"' . $dup_products_id . '",
										"' . $products_copy_from['options_id'] . '",
										"' . $products_copy_from['options_values_id'] . '",
										"' . $products_copy_from['options_values_price'] . '",
										"' . $products_copy_from['price_prefix'] . '",
										"' . $products_copy_from['attributes_hide_from_groups'] . '",
										"' . $products_copy_from['products_options_sort_order'] . '",
										"' . $products_copy_from['reference'] . '"
									)' );
						}
						// Duplicar attributos

                        // BOF Separate Pricing Per Customer originally 2006-04-26 by Infobroker
                        $cg_price_query = tep_db_query( "select customers_group_id, customers_group_price, products_qty_blocks, products_min_order_qty from " . TABLE_PRODUCTS_GROUPS . " where products_id = '" . $products_id . "' order by customers_group_id" );

                        // insert customer group prices in table products_groups when there are any for the copied product
                        if( tep_db_num_rows( $cg_price_query ) > 0 )
                        {
                            while( $cg_prices = tep_db_fetch_array( $cg_price_query ) )
                                tep_db_query( "insert into " . TABLE_PRODUCTS_GROUPS . " (customers_group_id, customers_group_price, products_id, products_qty_blocks, products_min_order_qty) values ('" . (int)$cg_prices['customers_group_id'] . "', '" . tep_db_input($cg_prices['customers_group_price']) . "', '" . (int)$dup_products_id . "', '" . (int)$cg_prices['products_qty_blocks'] . "', '" . (int)$cg_prices['products_min_order_qty'] . "')" );
                            // end while ( $cg_prices = tep_db_fetch_array($cg_price_query))
                        } // end if (tep_db_num_rows($cg_price_query) > 0)

                        $price_breaks_query = tep_db_query( "select products_price, products_qty, customers_group_id from " . TABLE_PRODUCTS_PRICE_BREAK . " where products_id = '" . (int)$products_id . "' order by customers_group_id, products_qty" );

                        while( $price_break = tep_db_fetch_array($price_breaks_query) )
                        {
                            $sql_price_break_data_array = array(
                                'products_id' => (int)$dup_products_id,
                                'products_price' => $price_break['products_price'],
                                'products_qty' => $price_break['products_qty'],
                                'customers_group_id' => $price_break['customers_group_id']);

                            tep_db_perform(TABLE_PRODUCTS_PRICE_BREAK, $sql_price_break_data_array);
                        }

                        $current_dc_query = tep_db_query( "select discount_categories_id, customers_group_id from " . TABLE_PRODUCTS_TO_DISCOUNT_CATEGORIES . " where products_id = '" . (int)$products_id . "' order by customers_group_id" );

                        if( tep_db_num_rows($current_dc_query) > 0 )
                        {
                            // insert the new products_id in products_to_discount_categories only
                            // if the cloned product was already in it
                            while( $current_dc = tep_db_fetch_array($current_dc_query) )
                            {
                                $discount_category_result = qpbpp_insert_update_discount_cats( $dup_products_id, '0', $current_dc['discount_categories_id'], $current_dc['customers_group_id'] );

                                if( $discount_category_result == false )
                                    $messageStack->add_session(ERROR_UPDATE_INSERT_DISCOUNT_CATEGORY, 'error');
                            } // end while ($current_dc = ....
                        } // end if (tep_db_num_rows($current_dc_query)

                        // EOF Separate Pricing Per Customer adapted for QPBPP for SPPC
                        $products_id = $dup_products_id;
                    }

                    if (USE_CACHE == 'true')
                    {
                        tep_reset_cache_block( 'categories' );
                        tep_reset_cache_block( 'also_purchased' );
                    }
                }

                tep_redirect( tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $categories_id . '&pID=' . $products_id) );
            break;

            case 'new_product_preview':

            break;
        }
    }

    // check if the catalog image directory exists
    if( is_dir(DIR_FS_CATALOG_IMAGES) )
	{
        if( !is_writeable( DIR_FS_CATALOG_IMAGES . 'productos/' ) )
            $messageStack->add( 'No se puede escribir en el directorio productos', 'error' );

        if( !is_writeable( DIR_FS_CATALOG_IMAGES . 'categorias' ) )
            $messageStack->add( 'No se puede escribir en el directorio categorias', 'error' );
	}
    else
	{
        $messageStack->add(ERROR_CATALOG_IMAGE_DIRECTORY_DOES_NOT_EXIST, 'error');
	}

	//++++ QT Pro: Begin Changed code
	if($product_investigation['any_problems']){
	$messageStack->add('<b>Atención: </b>'. qtpro_doctor_formulate_product_investigation($product_investigation, 'short_suggestion') ,'warning');
	}
	//++++ QT Pro: End Changed code
    include( THEME . 'html/header.php' );
?>
<script language="javascript" src="includes/general.js"></script>
<?php require_once( 'attributeManager/includes/attributeManagerHeader.inc.php' )?>

<table border="0" width="100%" cellspacing="2" cellpadding="2">
    <tr>
        <td width="100%" valign="top">
        <?php
            if( $action == 'new_product' )
            {
                $parameters = array('products_name' => '',
									// Start Products Specifications
									'products_tab_1' => '',
									'products_tab_2' => '',
									'products_tab_3' => '',
									'products_tab_4' => '',
									'products_tab_5' => '',
									'products_tab_6' => '',
									// End Products Specifications
									'products_bundle' => '',
									'sold_in_bundle_only' => 'no',
                                    'products_description' => '',
                                    'products_url' => '',
                                    'products_seo_url' => '',
                                    'products_fileupload' => '',
									'products_pdfupload' => '',
                                    'products_id' => '',
                                    'products_quantity' => '',
									'products_quantity_deseada' => '',
									'check_stock' => '',
									'exclude_feedmachine' => '',
                                    'products_youtube' => '',
                                    'products_model' => '',
                                    'products_image' => '',
									'products_subimages' => '',
                                    'products_price' => '',
                                    'products_cost' => '',
                                    'products_qty_blocks' => '',
                                    'products_min_order_qty' => '',
                                    'products_weight' => '',
									'ISBN' => '',
                                    'products_date_added' => '',
                                    'products_last_modified' => '',
                                    'products_date_available' => '',
									'products_featured_until' => '',
                                    'payment_methods' => '',
                                    'shipping_methods' => '',
                                    'products_status' => '',
									'products_featured' => '',
                                    'products_tax_class_id' => '',
                                    'product_ean' => '',
									'reference_prov' => '',
                                    'products_hide_from_groups' => '',
                                    'manufacturers_id' => '',
                                    'products_to_rss' => '',
									'amazon_status' => '',
									'products_liquidacion' => '',
									'products_ship_free' => '',
									'categoria_ebay' => ''
								);

                $pInfo = new objectInfo( $parameters );

                if( isset( $_GET['pID'] ) && empty( $_POST ) )
                {
                    $product_query = tep_db_query("select p.categoria_ebay, pd.products_name, pd.products_seo_url, pd.products_description, p.products_fileupload, p.products_pdfupload, pd.products_url, p.products_id, p.products_quantity, p.products_quantity_deseada, p.exclude_feedmachine, p.check_stock, p.products_model, p.shipping_methods, p.payment_methods, p.products_youtube, p.products_image, p.products_subimages, p.products_price, p.products_cost, p.products_qty_blocks, p.products_min_order_qty, p.products_hide_from_groups, p.products_weight, p.ISBN, p.products_date_added, p.products_last_modified, date_format(p.products_date_available, '%Y-%m-%d') as products_date_available, date_format(p.products_featured_until, '%Y/%m/%d') as products_featured_until, p.products_status, p.products_featured, p.products_tax_class_id, p.product_ean, p.reference_prov, p.manufacturers_id, p.products_to_rss, p.amazon_status, p.products_liquidacion, p.products_ship_free, p.products_bundle, p.sold_in_bundle_only  from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where p.products_id = '" . (int)$_GET['pID'] . "' and p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "'");
                    $product = tep_db_fetch_array($product_query);

                    $pInfo->addProperties($product);
                    unset($pInfo->products_qty_blocks);

                    $pInfo->products_qty_blocks[0] = $product['products_qty_blocks'];
                    unset($pInfo->products_min_order_qty);

                    $pInfo->products_min_order_qty[0] = $product['products_min_order_qty'];

                    $cg_prices_query = tep_db_query("select customers_group_id, customers_group_price, products_qty_blocks, products_min_order_qty from " . TABLE_PRODUCTS_GROUPS . " where products_id = '" . $pInfo->products_id . "' order by customers_group_id");
                    while( $cg_prices = tep_db_fetch_array( $cg_prices_query ) )
                    {
                        // and adding them to $pInfo for later use
                        if( tep_not_null($cg_prices['customers_group_price'] ) )
                            $pInfo->sppcprice[$cg_prices['customers_group_id']] = $cg_prices['customers_group_price'];

                        $pInfo->products_qty_blocks[$cg_prices['customers_group_id']] = $cg_prices['products_qty_blocks'];
                        $pInfo->products_min_order_qty[$cg_prices['customers_group_id']] = $cg_prices['products_min_order_qty'];
                    } // end while ($cg_prices = tep_db_fetch_array($cg_prices_query))

                    $price_breaks_array = array();
                    $price_breaks_query = tep_db_query("select products_price_break_id, products_price, products_qty, customers_group_id from " . TABLE_PRODUCTS_PRICE_BREAK . " where products_id = '" . tep_db_input($pInfo->products_id) . "' order by customers_group_id, products_qty");

                    while ($price_break = tep_db_fetch_array($price_breaks_query))
                    {
                        $pInfo->products_price_break[$price_break['customers_group_id']][] = $price_break['products_price'];
                        $pInfo->products_qty[$price_break['customers_group_id']][] = $price_break['products_qty'];
                        $pInfo->products_price_break_id[$price_break['customers_group_id']][] = $price_break['products_price_break_id'];
                    }

                    $product_discount_categories = array();
                    $products_discount_query = tep_db_query("select customers_group_id, discount_categories_id from " . TABLE_PRODUCTS_TO_DISCOUNT_CATEGORIES . " where products_id = '" . tep_db_input($pInfo->products_id) . "' order by customers_group_id");

                    while( $products_discount_results = tep_db_fetch_array($products_discount_query) )
                        $pInfo->discount_categories_id[$products_discount_results['customers_group_id']] = $products_discount_results['discount_categories_id'];

                    // EOF QPBPP for SPPC
                }
                elseif( tep_not_null( $_POST ) )
                {
                    $pInfo->addProperties($_POST);
                    $products_name = $_POST['products_name'];
                    $products_description = $_POST['products_description'];
                    $products_url = $_POST['products_url'];
                    $products_seo_url = $_POST['products_seo_url'];
                }

				$bundle_array = array();
				// BOF Bundled Products
				if (isset($pInfo->products_bundle) && $pInfo->products_bundle == "yes")
				{
					// this product is a bundle so get contents data
					$bundle_query = tep_db_query("SELECT pb.attributes, pb.subproduct_id, pb.subproduct_qty, pd.products_name FROM " . TABLE_PRODUCTS_DESCRIPTION . " pd INNER JOIN " . TABLE_PRODUCTS_BUNDLES . " pb ON pb.subproduct_id=pd.products_id WHERE pb.bundle_id = '" . (int)$_GET['pID'] . "' and language_id = '" . (int)$languages_id . "'");
					while ($bundle_contents = tep_db_fetch_array($bundle_query))
					{
						$bundle_array[] = array('id' => $bundle_contents['subproduct_id'], 'qty' => $bundle_contents['subproduct_qty'], 'name' => $bundle_contents['products_name'], 'attributes' => $bundle_contents['attributes']);
					}
				}
				$bundle_count = count($bundle_array);
				// EOF Bundled Products

                $manufacturers_array = array(array('id' => '', 'text' => TEXT_NONE));
                $manufacturers_query = tep_db_query("select manufacturers_id, manufacturers_name from " . TABLE_MANUFACTURERS . " order by manufacturers_name");

                while( $manufacturers = tep_db_fetch_array($manufacturers_query) )
                    $manufacturers_array[] = array('id' => $manufacturers['manufacturers_id'], 'text' => $manufacturers['manufacturers_name']);

                $free_shipping_array = array(array('id' => '0', 'text' => TEXT_NO), array('id' => '1', 'text' => TEXT_YES));

                $tax_class_array[] = array('id' => '0', 'text' => TEXT_NONE);
                $tax_class_query = tep_db_query("select tax_class_id, tax_class_title from " . TABLE_TAX_CLASS . " order by tax_class_title");

                while ($tax_class = tep_db_fetch_array($tax_class_query))
                    $tax_class_array[] = array('id' => $tax_class['tax_class_id'], 'text' => $tax_class['tax_class_title']);



                $discount_categories_array = array(array('id' => '0', 'text' => TEXT_NONE));
                $discount_categories_query = tep_db_query("select discount_categories_id, discount_categories_name from " . TABLE_DISCOUNT_CATEGORIES . " order by discount_categories_name");

                while( $discount_categories = tep_db_fetch_array($discount_categories_query) )
                    $discount_categories_array[] = array('id' => $discount_categories['discount_categories_id'], 'text' => $discount_categories['discount_categories_name']);

                $languages = tep_get_languages();

                if( !isset($pInfo->products_status))
                    $pInfo->products_status = '1';

				switch( $pInfo->products_status)
                {
                    case '0':
						$in_status = false;
						$out_status = true;
						$desc_status = false;
					break;
					case '2':
						$in_status = false;
						$out_status = false;
						$desc_status = true;
					break;
                    case '1':
                    default:
						$in_status = true;
						$out_status = false;
						$desc_status = false;
                }

				if( !isset($pInfo->products_featured))
                    $pInfo->products_featured = '1';

                switch( $pInfo->products_featured)
                {
                    case '0': $in_status1 = false; $out_status1 = true; break;
                    case '1':
                    default: $in_status1 = true; $out_status1 = false;
                }
        ?>

        <script language="javascript"><!--
            var tax_rates = new Array();
            <?php
                for ($i=0, $n=sizeof($tax_class_array); $i<$n; $i++)
                {
                    if( $tax_class_array[$i]['id'] > 0 )
                        echo 'tax_rates["' . $tax_class_array[$i]['id'] . '"] = ' . tep_get_tax_rate_value($tax_class_array[$i]['id']) . ';' . "\n";
                }
            ?>

            function doRound(x, places)
            {
                return Math.round(x * Math.pow(10, places)) / Math.pow(10, places);
            }

            function getTaxRate()
            {
                var selected_value = document.forms["new_product"].products_tax_class_id.selectedIndex;
                var parameterVal = document.forms["new_product"].products_tax_class_id[selected_value].value;

                if( parameterVal > 0 && tax_rates[parameterVal] > 0 )
                {
                    return tax_rates[parameterVal];
                }
                else
                {
                    return 0;
                }
            }

            function updateGross()
            {
                var taxRate = getTaxRate();
                var grossValue = document.forms["new_product"].products_price.value;
                document.forms["new_product"].products_price_gross.value = document.forms["new_product"].products_price.value;

                if( taxRate > 0 )
                {
                    grossValue = grossValue * ((taxRate / 100) + 1);
                }

                document.forms["new_product"].products_price_gross.value = doRound(grossValue, 4);
            }

            function updateNet()
            {
                var taxRate = getTaxRate();
                var netValue = document.forms["new_product"].products_price_gross.value;

                if (taxRate > 0)
                {
                    netValue = netValue / ((taxRate / 100) + 1);
                }

                document.forms["new_product"].products_price.value = doRound(netValue, 4);
                document.forms["new_product"].products_price_retail_net.value = document.forms["new_product"].products_price.value;
            }

			function updateMargin() {
			  var grossValue = document.forms["new_product"].products_price.value;
			  var costValue = document.forms["new_product"].products_cost.value;

			 marginValue = parseInt((100-(costValue*100)/grossValue));

			  document.getElementById('products_price_margins').innerHTML = marginValue + "% beneficio";

			  var grossValue2 = document.forms["new_product"].sppcprice1.value;
			  var costValue2 = document.forms["new_product"].products_cost.value;

			 marginValue2 = parseInt((100-(costValue2*100)/grossValue2));

			  document.getElementById('products_price_margins1').innerHTML = marginValue2 + "% beneficio";

			  var grossValue3 = document.forms["new_product"].sppcprice2.value;
			  var costValue3 = document.forms["new_product"].products_cost.value;

			 marginValue3 = parseInt((100-(costValue3*100)/grossValue3));

			if( document.getElementById('products_price_margins2') )
				document.getElementById('products_price_margins2').innerHTML = marginValue3 + "% beneficio";
			}

			window.onload = function()
			{
				updateGross();
				updateMargin();
			};
        </script>

        <?php
            $sActionForm = (isset($_GET['pID'])) ? 'update_product' : 'insert_product';

			$trueEditPath1 = empty($search) ? 'page='.$page.'&cPath=' . $cPath : 'page='.$page. '&search=' . $search;
        ?>
				<tr>
					<td>
						<?php
							echo tep_draw_form('new_product', FILENAME_CATEGORIES, $trueEditPath1 . '&cPath=' . $cPath . (isset($_GET['pID']) ? '&pID=' . $_GET['pID'] : '') . '&action=' . $sActionForm, 'post', 'enctype="multipart/form-data"');
							echo tep_draw_hidden_field( 'ireturn', 0, 'id="ireturn"' );
							echo tep_draw_hidden_field( 'menu-tab', $_GET['menu-tab'] ?? 1);
						?>
						<div id="box-left">
							<ul class="nav">
								<li><a data-id="1" class="active" href="javascript:void(0);"><img alt="" src="images/icons/productos_datos_generales.png"><span>Datos generales</span></a></li>
								<li><a data-id="2" href="javascript:void(0);"><img alt="" src="images/icons/productos_precio.png"><span>Precios</span></a></li>
								<li><a data-id="3" href="javascript:void(0);"><img alt="" src="images/icons/productos_imagenes.png"><span>Imágenes</span></a></li>
								<li><a data-id="4" href="javascript:void(0);"><img alt="" src="images/icons/productos_opciones.png"><span>Opciones</span></a></li>
								<li><a data-id="5" href="javascript:void(0);"><img alt="" src="images/icons/productos_otros.png"><span>Otros</span></a></li>
								<li><a data-id="6" href="javascript:void(0);"><img alt="" src="images/icons/productos_seo.png"><span>SEO</span></a></li>
								<li><a data-id="9" href="javascript:void(0);"><img alt="" src="images/icons/panel_icon_modules.png"><span>Productos alternativos</span></a></li>
								<li><a data-id="7" href="javascript:void(0);"><img alt="" src="images/icons/cupon_fabricante.png"><span>Repuestos</span></a></li>
								<li><a data-id="8" href="javascript:void(0);"><img alt="" src="images/icons/productos_otros.png"><span>Pack Producto</span></a></li>
							</ul>
						</div>

						<div id="box-right">
							<table border="0" cellspacing="0" width="100%">
								<tr>
									<td>
										<div>
											<div class="toolbarHead">
												<div class="hdr-tlbr"<?php echo (array_key_exists('pID', $_GET) && $pInfo->products_image != '' && file_exists(DIR_WS_CATALOG_IMAGES . 'productos/' . $pInfo->products_image) ? ' style="padding-left: 100px;"' : ''); ?>>
													<h1 class="pageHeading"><?php echo sprintf((array_key_exists('pID', $_GET) ? TEXT_EDIT_PRODUCT: TEXT_NEW_PRODUCT), tep_output_generated_category_path($current_category_id)); ?></h1>
													<?php
														if( array_key_exists('pID', $_GET) )
														{
															if ($pInfo->products_image != '' && file_exists(DIR_WS_CATALOG_IMAGES . 'productos/' . $pInfo->products_image)) {
                                                                echo '<div style="position: absolute; top: 14px; left: 9px;">' . tep_image( DIR_WS_CATALOG_IMAGES . 'productos/' . $pInfo->products_image, $pInfo->products_name, 80, 80, '', false ) . '</div>';
                                                            }

															echo '<h2 style="top: 13px;" class="stitl">#' . $_GET['pID'] . ' - ' . tep_get_products_name($pInfo->products_id, $languages_id) . '</h2>';
														}
													?>
													<div class="btn-right">
														<?php echo isset($_GET['pID']) ? '<a target="_blank" href="' . tep_href_link('../product_info.php', 'products_id=' . $_GET['pID']) . '"><img class="dx-hovr" src="images/icons/icon_view_product' . ($language == 'espanol' ? '' : '_' . $language) . '.png" title="' . TEXT_CATEGORIES_VIEW_PRODUCT_INFO . '"></a>' : ''; ?>
														<a href="#" onclick="$('#ireturn').val('1'); $(this).closest('form').submit()" title="<?php echo (isset($_GET['pID']) ? TEXT_CATEGORIES_SAVE_CHANGES_EDIT : TEXT_CATEGORIES_SAVE_CHANGES_NEW); ?>"><img class="dx-hovr" src="images/icons/icon_save<?php echo ($language == 'espanol' ? '' : '_' . $language); ?>.png"></a>
														<a href="#" onclick="$(this).closest('form').submit()" title="<?php echo (isset($_GET['pID']) ? TEXT_CATEGORIES_SAVE_CHANGES_EDIT : TEXT_CATEGORIES_SAVE_CHANGES_NEW); ?>"><img class="dx-hovr" src="images/icons/icon_save_return<?php echo ($language == 'espanol' ? '' : '_' . $language); ?>.png"></a>
														<a href="<?php echo tep_href_link(FILENAME_CATEGORIES, $trueEditPath1 . 'cPath=' . $cPath . (isset($_GET['pID']) ? '&pID=' . $_GET['pID'] : ''));?>"><img class="dx-hovr" src="images/icons/icon_back<?php echo ($language == 'espanol' ? '' : '_' . $language); ?>.png" title="<?php echo TEXT_CATEGORIES_BACK_NO_SAVE; ?>"></a>
													</div>
												</div>
											</div>
										</div>
									</td>
								</tr>



								<!-- DATOS GENERALES -->
								<tr class="tab-new" style="display: block;" data-id="1">
									<td style="display:block;">

											<div class="fluid grid">
												<div class="box-tbl grid12">
													<div class="box-head">
														<h6>Información general</h6>
														<div class="clear"></div>
													</div>
									                <div id="dxctgrtab">
									                        <div class="grid12">
																<span class="dxctgrtab-title">Idioma:</span>
									                        	<div class="tab-pane" id="tabPane1">
																	<script type="text/javascript">tp1 = new WebFXTabPane( document.getElementById( "tabPane1" ) );</script>
																	<?php for ($i=0; $i<sizeof($languages); $i++) { ?>
																		<div id="<?php echo $languages[$i]['name'];?>">
																			<h2 class="tab"><nobr><?php echo tep_image(DIR_WS_CATALOG_LANGUAGES . $languages[$i]['directory'] . '/images/bandera.png', $languages[$i]['name'],'align="absmiddle"');?></h2>
																			<script type="text/javascript">tp1.addTabPage( document.getElementById( "<?php echo $languages[$i]['name'];?>" ) );</script>
																			 <div class="formRow">
									                        					<div class="grid2"><label><?php echo TEXT_PRODUCTS_NAME; ?></label></div>
									                        					<div class="grid10"><?php echo tep_draw_input_field('products_name[' . $languages[$i]['id'] . ']', (isset($products_name[$languages[$i]['id']]) ? $products_name[$languages[$i]['id']] : tep_get_products_name($pInfo->products_id, $languages[$i]['id'])), 'size="65"') . ( $bErrorNameLang ? ERROR_NOMBRE_PRODUCTO_MULTI : ($bErrorName ? ERROR_NOMBRE_PRODUCTO : '') ); ?></div>
									                       						 <div class="clear"></div>
									               							 </div>
									               							 <div class="formRow">
									                        					<div class="grid2"><label><?php echo TEXT_PRODUCTS_DESCRIPTION; ?></label></div>
									                        					<div class="grid10"><?php echo tep_draw_textarea_field_tinymce('products_description[' . $languages[$i]['id'] . ']', 'soft', '70', '20', (isset($products_description[$languages[$i]['id']]) ? stripslashes($products_description[$languages[$i]['id']]) : tep_get_products_description($pInfo->products_id, $languages[$i]['id']))); ?></div>
									                       						 <div class="clear"></div>
									               							 </div>
																		</div>
																	<?php } ?>
																</div>

																<script type="text/javascript">setupAllTabs();</script>

									                        </div>
									                        <div class="clear"></div>
									                </div>
													<div class="formRow">
														<?php
															// Products Specifications:keldrox
															require (DIR_WS_MODULES . FILENAME_PRODUCTS_SPECIFICATIONS_INPUT);
														?>
													</div>

									                <div class="formRow">
									                        <div class="grid2"><label><?php echo TEXT_PRODUCTS_STATUS; ?></label></div>
															<div class="grid4">
																<span style="float:left;"><?php echo tep_draw_radio_field('products_status', '1', $in_status);?></span> <label style="margin-right: 10px;"><?php echo TEXT_PRODUCT_AVAILABLE;?></label>
																<span style="float:left;"><?php echo tep_draw_radio_field('products_status', '0', $out_status);?></span> <label style="margin-right: 10px;"><?php echo TEXT_PRODUCT_NOT_AVAILABLE; ?></label>
																<span style="float:left;"><?php echo tep_draw_radio_field('products_status', '2', $desc_status);?></span> <label>Descatalogado</label>
															</div>
									                        <div class="grid2"><label><?php echo TEXT_PRODUCTS_DATE_AVAILABLE; ?></label></div>
									                        <div class="grid4">
																<?php
																	$sDate = '';
																	if( $pInfo->products_date_available != '' )
																	{
																		$aAux = explode( '-', $pInfo->products_date_available );
																		$sDate = $aAux[2] . '-' . $aAux[1] . '-' . $aAux[0];
																	}

																	echo tep_draw_input_field( 'products_date_available', $sDate, 'size="2" style="width: 80px !important" maxlength="2" class="dxdatepicker cal-TextBox"' );
																?>
																<span class="note">(DD/MM/YYYY)</span>
															</div>
									                        <div class="clear"></div>
									                </div>

									                <div class="formRow">
									                        <div class="grid2"><label>Categoría:</label></div>
									                        <div class="grid10">
																<?php
																// Construir mapa id→nombre y id→parent_id desde la BD
																$_catNames = [];
																$_catParents = [];
																$_qCat = tep_db_query("SELECT c.categories_id, c.parent_id, cd.categories_name
																	FROM " . TABLE_CATEGORIES . " c
																	INNER JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd ON cd.categories_id = c.categories_id
																	WHERE cd.language_id = '" . (int) $languages_id . "'");
																while ($_rCat = tep_db_fetch_array($_qCat)) {
																	$_catNames[(int) $_rCat['categories_id']] = $_rCat['categories_name'];
																	$_catParents[(int) $_rCat['categories_id']] = (int) $_rCat['parent_id'];
																}
																$_pathCache = [];
																$_buildPath = function ($id) use (&$_buildPath, &$_pathCache, &$_catNames, &$_catParents) {
																	if (isset($_pathCache[$id])) return $_pathCache[$id];
																	if (!isset($_catNames[$id])) return '';
																	$parent = $_catParents[$id] ?? 0;
																	$prefix = $parent > 0 ? $_buildPath($parent) . '>' : '';
																	return $_pathCache[$id] = $prefix . $_catNames[$id];
																};
																$_catList = [];
																$_currentCatLabel = '';
																foreach ($_catNames as $_id => $_name) {
																	$label = $_buildPath($_id);
																	$_catList[] = ['id' => $_id, 'label' => $label, 'value' => $label];
																	if ($_id === (int) $current_category_id) {
																		$_currentCatLabel = $label;
																	}
																}
																usort($_catList, function ($a, $b) { return strcasecmp($a['label'], $b['label']); });
																?>
																<input type="hidden" name="move_to_category_id" id="cat-picker-id" value="<?php echo (int) $current_category_id; ?>">
																<input type="text" id="cat-picker-search" value="<?php echo htmlspecialchars($_currentCatLabel, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Buscar categoría..." autocomplete="off" style="width: 100%; max-width: 600px;" oninput="document.getElementById('cat-picker-id').value=0;">
																<span class="note" style="margin-left: 10px;">Cambiar para mover este producto al guardar.</span>
																<script>
																(function() {
																	var data = <?php echo json_encode($_catList, JSON_UNESCAPED_UNICODE); ?>;
																	function init() {
																		if (!window.jQuery || !jQuery.ui || !jQuery.ui.autocomplete) return setTimeout(init, 200);
																		jQuery('#cat-picker-search').autocomplete({
																			source: data,
																			minLength: 1,
																			delay: 50,
																			select: function(e, ui) {
																				jQuery('#cat-picker-id').val(ui.item.id);
																				jQuery(this).val(ui.item.label);
																				return false;
																			}
																		});
																	}
																	init();
																})();
																</script>
															</div>
									                        <div class="clear"></div>
									                </div>

									                <div class="formRow">
									                        <div class="grid2"><label>Producto destacado:</label></div>
									                        <div class="grid4"><?php echo tep_draw_radio_field('products_featured', '1', $in_status1);?> <label style="margin-right: 10px;"><?php echo TEXT_PRODUCT_AVAILABLE;?></label><?php echo tep_draw_radio_field('products_featured', '0', $out_status1);?> <label><?php echo TEXT_PRODUCT_NOT_AVAILABLE; ?></label></div>
									                        <div class="grid2"><label>Destacado hasta la Fecha:</label></div>
									                        <div class="grid4">
																<?php
																	$sDate = '';
																	if( $pInfo->products_featured_until != '' )
																	{
																		$aAux = explode( '/', $pInfo->products_featured_until );
																		$sDate = $aAux[2] . '/' . $aAux[1] . '/' . $aAux[0];
																	}

																	echo tep_draw_input_field( 'products_featured_until', $sDate, 'size="2" style="width: 80px !important" maxlength="2" class="dxdatepicker cal-TextBox"' );
																?>
																<span class="note">(DD/MM/YYYY)</span>
															</div>
									                        <div class="clear"></div>
									                </div>
									                <?php
														if($product_investigation['has_tracked_options'] or $product_investigation['stock_entries_count'] > 0)
															$qty='<a href="' . tep_href_link("stock.php", 'product_id=' . $pInfo->products_id) . ' " target="_blank">' . tep_image_button('button_stock.png', "Stock") . '</a>';
														else
															$qty=tep_draw_input_field('products_quantity', $pInfo->products_quantity);
													?>
									                <div class="formRow">
									                        <div class="grid2"><label><?php echo TEXT_PRODUCTS_MANUFACTURER; ?></label></div>
									                        <div class="grid4"><?php echo tep_draw_pull_down_menu('manufacturers_id', $manufacturers_array, $pInfo->manufacturers_id); ?></div>
									                        <div class="grid2"><label><?php echo TEXT_PRODUCTS_QUANTITY; ?></label></div>
									                        <div class="grid4"><?php echo $qty; ?></div>
									                        <div class="clear"></div>
									                </div>
													<div class="formRow">
														<div class="grid2"><label>Stock Deseado:</label></div>
														<div class="grid4"><?php echo tep_draw_input_field('products_quantity_deseada', $pInfo->products_quantity_deseada); ?></div>
														<div class="clear"></div>
									                </div>
													<div class="formRow">
														<div class="grid1"><label>Controlar stock:</label></div>
										                <div class="grid2 check"><?php echo tep_draw_checkbox_field('check_stock', '1', $pInfo->check_stock, $pInfo->check_stock); ?></div>
														<div class="grid1">Excluir de<br>feedmachine:</div>
										                <div class="grid2 check"><?php echo tep_draw_checkbox_field('exclude_feedmachine', '1', $pInfo->exclude_feedmachine, $pInfo->exclude_feedmachine); ?></div>
														<div class="grid1"><label>Amazon</div>
														<div class="grid1 check"><?php echo tep_draw_checkbox_field('amazon_status', '1', $pInfo->amazon_status, $pInfo->amazon_status); ?></div>
														<div class="grid1"><label><?php echo TEXT_PRODUCTS_RSS; ?></div>
														<div class="grid1 check"><?php echo tep_draw_checkbox_field('products_to_rss', '1', 'true', $pInfo->products_to_rss); ?></div>
														<div class="clear"></div>
									                </div>
									                <div class="formRow">
														<div class="grid2"><label><?php echo TEXT_PRODUCTS_MODEL; ?></label></div>
														<div class="grid4"><?php echo tep_draw_input_field('products_model', $pInfo->products_model); ?></div>
														<div class="grid2"><label>Ref. Proveedor:</label></div>
														<div class="grid4"><?php echo tep_draw_input_field('reference_prov', $pInfo->reference_prov); ?></div>
														<div class="clear"></div>
									                </div>
									                <div class="formRow">
														<div class="grid2"><label>Código Ean:</label></div>
														<div class="grid4"><?php echo tep_draw_input_field('product_ean', $pInfo->product_ean, ' maxlength="13" '); ?></div>
														<div class="grid2"><label>Adjuntar Video: <span class="note"><img src="images/Youtube-icon.png" width="16" height="16" alt="youtube.com" title="Youtube.com"> <img src="images/vimeo-icon.png" width="16" height="16" alt="vimeo.com" title="Vimeo.com"></span></label></div>
														<div class="grid4"><?php echo tep_draw_input_field('products_youtube', $pInfo->products_youtube); ?></div>
														<div class="clear"></div>
									                </div>
									                <div class="formRow">
														<div class="grid2"><label><?php echo TEXT_PRODUCTS_WEIGHT; ?></label></div>
														<div class="grid4"><?php echo tep_draw_input_field('products_weight', $pInfo->products_weight); ?></div>
														<div class="grid2"><label>ISBN:</div>
														<div class="grid4"><?php echo tep_draw_input_field('ISBN', $pInfo->ISBN); ?></div>
														<div class="clear"></div>
									                </div>

									                <div class="formRow">
														<div class="grid2"><label>Adjuntar PDF:</label></div>
														<div class="grid5">
															<input type="file" name="products_pdfupload" id="products_pdfupload">
															<?php echo tep_draw_hidden_field('products_pdfupload_anterior', $pInfo->products_pdfupload); ?>
														</div>
														<div class="grid3 check" style="margin-left:0; margin-top:5px;"><?php echo tep_draw_checkbox_field('delete_products_pdfupload', 'yes', false) . ' ' . TEXT_DELETE_ARCHIVO; ?></div>
															<?php if ($pInfo->products_pdfupload!='') echo '<span class="note"><a href="../manuals/'.$pInfo->products_pdfupload.'" target="_blank">Ver archivo '.$pInfo->products_pdfupload.'</a></span>';?>
														<div class="clear"></div>
									                </div>
									                <div class="formRow">
														<div class="grid2"><label>Adjuntos Adicionales:</label></div>
														<div class="grid5">
															<input type="file" name="products_fileupload" id="products_fileupload">
															<?php echo tep_draw_hidden_field('products_fileupload_anterior', $pInfo->products_fileupload); ?>
														</div>
														<div class="grid3 check" style="margin-left:0; margin-top:5px;"><?php echo tep_draw_checkbox_field('delete_products_fileupload', 'yes', false) . ' ' . TEXT_DELETE_ARCHIVO; ?></div>
															<?php if ($pInfo->products_fileupload!='') echo '<span class="note"><a href="../images/upload/'.$pInfo->products_fileupload.'" target="_blank">Ver archivo '.$pInfo->products_fileupload.'</a></span>';?>
														<div class="clear"></div>
									                </div>
										         </div>

												<?php if (intval($pInfo->categoria_ebay) > 0): ?>

												 <div class="box-tbl grid12">
												 	<div class="box-head">
														<h6>Características EBAY</h6>
														<div class="clear"></div>
													</div>
													<div class="">
													<?php

													$sSql = tep_db_query('SELECT cfp.id_feature, cf.nombre, cf.tipo, cf.modo, cf.recomendaciones
													FROM ebay_features_categories cfp
													LEFT JOIN ebay_features cf ON cf.id = cfp.id_feature
													WHERE cfp.id_category = ' . intval($pInfo->categoria_ebay));

													$sSqlData = tep_db_query('SELECT id_feature, value FROM ebay_features_products WHERE id_product = ' . intval($pInfo->products_id));
													$aDataFeature = array();
													while ($aData = tep_db_fetch_array($sSqlData)) {
														$aDataFeature[$aData['id_feature']] = $aData['value'];
													}


												if (tep_db_num_rows($sSql) > 0):
													while ($aFeatures = tep_db_fetch_array($sSql)):
												?>
														<?php $aRecomendaciones = json_decode($aFeatures['recomendaciones']); ?>
														<div class="formRow">
															<div class="grid2"><label><?php echo $aFeatures['nombre']; ?></label></div>
															<div class="grid10">
															<?php if($aFeatures['modo'] == 'FreeText'): ?>
																<?php
																/**
																 * Si el campo es "marca" y esta vacia, la llenamos con la marca del producto
																 */
																if ($aFeatures['nombre'] == 'Marca' && !$aDataFeature[$aFeatures['id_feature']]) {
																	$nManufacturerId = intval($pInfo->manufacturers_id);
																	if ($nManufacturerId > 0) {
																		$aManufacturersQuery = tep_db_query("SELECT manufacturers_id, manufacturers_name FROM " . TABLE_MANUFACTURERS . " WHERE manufacturers_id = " . $nManufacturerId);
																		$manufacturer = tep_db_fetch_array($aManufacturersQuery);
																		$aDataFeature[$aFeatures['id_feature']] = $manufacturer['manufacturers_name'];
																	}
																}

																/**
																 * Si el campo es "MPN" y esta vacio, la llenamos con el modelo o el ean.
																 */
																if ($aFeatures['nombre'] == 'MPN' && !$aDataFeature[$aFeatures['id_feature']]) {
																	$aDataFeature[$aFeatures['id_feature']] = ($pInfo->products_model != '' ? $pInfo->products_model : $pInfo->product_ean);
																}
																?>
																<p><?php echo tep_draw_input_field('ebay_features['.$aFeatures['id_feature'].']', $aDataFeature[$aFeatures['id_feature']]); ?></p>
																<?php if (!empty($aRecomendaciones)): ?>
																	<p style="margin-top: 5px; font-size: 11px;">Recomendaciones: <?php echo implode(', ', $aRecomendaciones); ?></p>
																<?php endif; ?>

															<?php else: ?>

																<p>
																	<select name="<?php echo 'ebay_features['.$aFeatures['id_feature'].']'; ?>" style="width: 100%; box-sizing: border-box; padding: 4px;">
																	<option value="">Ninguno</option>
																		<?php foreach($aRecomendaciones as $sRecomendacion): ?>
																			<option value="<?php echo $sRecomendacion; ?>"<?php if ($aDataFeature[$aFeatures['id_feature']] == $sRecomendacion): ?> SELECTED <?php endif; ?>><?php echo $sRecomendacion; ?></option>
																		<?php endforeach; ?>
																	</select>
																</p>

															<?php endif; ?>
															</div>
															<div class="clear"></div>
														</div>
													<?php endwhile; ?>
												<?php endif; ?>
													</div>
												 </div>
													<?php endif; ?>
											</div>





									</td>
								</tr>

								<!-- Pack de Productos -->
								<tr class="tab-new" style="display: none;"  data-id="8">
									<td style="display:block;">
										<div class="fluid grid">
											<div class="box-tbl grid12">
												<div class="box-head">
													<h6>Pack de Producto</h6>
													<div class="clear"></div>
												</div>
												<div class="formRow">
													<div class="grid5"><label><?php echo tep_draw_radio_field('sold_in_bundle_only', 'no', true, $pInfo->sold_in_bundle_only); ?> <?php echo ENTRY_AVAILABLE_SEPARATELY; ?></label></div>
													<div class="grid5"><label><?php echo tep_draw_radio_field('sold_in_bundle_only', 'yes', false, $pInfo->sold_in_bundle_only); ?> <?php echo ENTRY_IN_BUNDLE_ONLY; ?></label></div>
													<div class="clear"></div>
								                </div>
												<?php
													for ($i=0, $n = $bundle_count ? $bundle_count+1:3; $i<$n; $i++)
													{
														echo '<div class="formRow">';
															echo '<div class="grid1"><label>' . TEXT_BUNDLE_HEADING_PRODUCT . '</label></div>';
															echo '<div class="grid5"><input type="text" disabled size="30" name="subproduct_' . $i . '_name" value="' . tep_output_string($bundle_array[$i]['name']) . '"></div>';


															$sql = "SELECT popt.products_options_name, popt.products_options_id, patrib.options_values_id , patrib.products_attributes_id, IF(ps.products_stock_quantity < 0, 0, ps.products_stock_quantity) as products_stock_quantity, pag.options_values_price, pov.products_options_values_name
																			FROM products_options popt
																			LEFT JOIN products_attributes patrib ON patrib.options_id = popt.products_options_id
																			LEFT JOIN products_options_values pov ON pov.products_options_values_id = patrib.options_values_id AND pov.language_id = 3
																			LEFT JOIN products_attributes_groups pag ON pag.products_attributes_id = patrib.products_attributes_id
																			LEFT JOIN products_stock ps ON ps.products_stock_attributes = CONCAT(popt.products_options_id, '-', patrib.options_values_id) AND ps.products_id = '" .  $bundle_array[$i]['id'] . "'
																			WHERE patrib.products_id='" .  $bundle_array[$i]['id'] . "' AND popt.language_id = 3 GROUP BY options_values_id ORDER BY options_values_price asc";
															$query = tep_db_query($sql);
															$attributes = '';

															$attributesActual = [];
															if ($bundle_array[$i]['attributes'] != '') {
																$attributesActual = json_decode(stripslashes($bundle_array[$i]['attributes']), true);
															}

															if (tep_db_num_rows($query) > 0) {

																$attributes .= '<ul style="display: grid;grid-template-columns: repeat(3, 1fr);gap: 3px;">';
																while ($attribute = tep_db_fetch_array($query)) {
																	$id_attribute = $attribute['products_options_id'].'-'.$attribute['options_values_id'];
																	$selected = in_array($id_attribute, $attributesActual) ? ' checked="checked" ' : '';
																	$attributes .= '
																	<li>
																		<label><input type="checkbox" name="packs_attributes['.$i.'][]" value="'.$id_attribute.'" '.$selected.'> '.$attribute['products_options_name'].' '.$attribute['products_options_values_name'].'</label>
																	</li>';
																}
																$attributes .= '<ul>';
															}
															/*echo '<div class="grid3">' . $attributes . '</div>';*/


															echo '<input type="hidden" size="3" name="subproduct_' . $i . '_id" value="' . $bundle_array[$i]['id'] . '">';
															echo '<div class="grid1"><label>' . TEXT_BUNDLE_HEADING_QUANTITY . '</label></div>';
															echo '<div class="grid2"><input type="text" style="width: 80px;" size="3" name="subproduct_' . $i . '_qty" value="' . $bundle_array[$i]['qty'] . '"></div>';
															echo '<div class="grid2"><a href="javascript:clearSubproduct(' . $i . ')">[x] ' . TEXT_REMOVE_PRODUCT . '</a></div>';
															echo '<div class="clear"></div>';
														echo '</div>';
													}
												?>
												<div class="formRow" id="bundled_subproducts"></div>
												<?php echo tep_draw_hidden_field('bundled_subproducts_i', $i,'id="bundled_subproducts_i"'); ?>
												<div class="formRow">
												    <div class="grid2"><label>¿Habilitar Pack?</label></div>
							                        <div class="grid4"><?php echo tep_draw_pull_down_menu('products_bundle', array(array('id'=>'no','text'=>'No'),array('id'=>'yes','text'=>'Si')), $pInfo->products_bundle); ?></div>
							                        <div class="grid2"><label><?php echo '<a href="javascript:" onclick="addSubproduct()">' . TEXT_ADD_LINE . '</a>'; ?></div>
							                        <div class="clear"></div>
							                    </div>

												<div class="formRow">
												    <div class="grid3"><label><?php echo TEXT_ADD_PRODUCT;?></label></div>
							                        <div class="grid5">
							                        <?php
														echo '<select name="subproduct_selector" onChange="fillCodes()" class="select2" style="width: 100%;">';
														/*echo '<option name="null" value="" SELECTED></option>';
														$where_str = '';
														if (isset($_GET['pID'])) {
														  $bundle_check = bundle_avoid($_GET['pID']);
														  if (!empty($bundle_check)) {
															$where_str = ' and (not (p.products_id in (' . implode(',', $bundle_check) . ')))';
														  }
														 }
														$products = tep_db_query("select pd.products_name, p.products_id, p.products_model from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where pd.products_id = p.products_id and pd.language_id = '" . (int)$languages_id . "' and p.products_id <> " . (int)$_GET['pID'] . $where_str . " order by FIELD(p.products_status, 1, 2, 0), p.products_model");
														while($products_values = tep_db_fetch_array($products)) {
														  echo "\n" . '<option name="' . $products_values['products_id'] . '" value="' . $products_values['products_id'] . '">' . $products_values['products_name'] . " (" . $products_values['products_model'] . ')</option>';
														}*/

														echo '</select>';
													?>
													</div>



													<div class="clear"></div>
								                </div>
												<script type="text/javascript">
													var last_product_fill = false;
													function fillCodes() {

														if (last_product_fill != false) {
															var this_subproduct_id = eval("document.new_product.subproduct_" + last_product_fill + "_id")
															var this_subproduct_name = eval("document.new_product.subproduct_" + last_product_fill + "_name")
															var this_subproduct_qty = eval("document.new_product.subproduct_" + last_product_fill + "_qty")

															this_subproduct_id.value = document.new_product.subproduct_selector.value
															this_subproduct_qty.value = "1"
															var name = document.new_product.subproduct_selector[document.new_product.subproduct_selector.selectedIndex].text
																this_subproduct_name.value = name
																document.returnValue = true;
																return true;
														}

													  for (var n=0;n<100;n++) {
														var this_subproduct_id = eval("document.new_product.subproduct_" + n + "_id")
														var this_subproduct_name = eval("document.new_product.subproduct_" + n + "_name")
														var this_subproduct_qty = eval("document.new_product.subproduct_" + n + "_qty")

														if (this_subproduct_id.value == "") {
														  this_subproduct_id.value = document.new_product.subproduct_selector.value
														  this_subproduct_qty.value = "1"
														  var name = document.new_product.subproduct_selector[document.new_product.subproduct_selector.selectedIndex].text
															this_subproduct_name.value = name
															document.returnValue = true;
															last_product_fill = n;
															return true;
														}
													  }
													}

													function clearSubproduct(n) {
														last_product_fill = false;
													  var this_subproduct_id = eval("document.new_product.subproduct_" + n + "_id");
													  var this_subproduct_name = eval("document.new_product.subproduct_" + n + "_name");
													  var this_subproduct_qty = eval("document.new_product.subproduct_" + n + "_qty");
													  this_subproduct_id.value = "";
													  this_subproduct_name.value = "";
													  this_subproduct_qty.value = "";
													}

													function addSubproduct(){
														last_product_fill = false;
													  var n = parseInt(document.getElementById('bundled_subproducts_i').value);
													  var HTML = document.getElementById('bundled_subproducts');
													  currentElement = document.createElement("input");
													  currentElement.setAttribute("disabled","");
													  currentElement.setAttribute("size","30");
													  currentElement.setAttribute("type", "text");
													  currentElement.setAttribute("name", 'subproduct_' + n + '_name');
													  currentElement.setAttribute("value", "");
													  HTML.appendChild(currentElement);
													  currentElement = document.createElement("input");
													  currentElement.setAttribute("size","3");
													  currentElement.setAttribute("type", "hidden");
													  currentElement.setAttribute("name", 'subproduct_' + n + '_id');
													  currentElement.setAttribute("value", "");
													  HTML.appendChild(currentElement);
													  currentElement = document.createTextNode(' ');
													  HTML.appendChild(currentElement);
													  currentElement = document.createElement("input");
													  currentElement.setAttribute("size","3");
													  currentElement.setAttribute("type", "text");
													  currentElement.setAttribute("name", 'subproduct_' + n + '_qty');
													  currentElement.setAttribute("value", "");
													  HTML.appendChild(currentElement);
													  document.createTextNode('&nbsp;');
													  HTML.appendChild(currentElement);
													  var myLink = document.createElement('a');
													  var href = document.createAttribute('href');
													  myLink.setAttribute('href','javascript:');
													  myLink.setAttribute('onclick', 'clearSubproduct(' + n + ')');
													  <?php echo "myLink.innerText = ' [x] " . TEXT_REMOVE_PRODUCT . "';\n"; ?>
													  HTML.appendChild(myLink);
													  currentElement = document.createElement("br");
													  HTML.appendChild(currentElement);
													  document.getElementById('bundled_subproducts_i').value = n + 1;
													}
												</script>
									</td>
								</tr>

								<!-- PRECIOS -->
								<tr class="tab-new" style="display: none;"  data-id="2">
									<td style="display:block;">
										<div class="fluid grid">
											<div class="box-tbl grid6">
												<div class="box-head">
													<h6>Precio base del producto</h6>
													<div class="clear"></div>
												</div>
								                <div class="formRow">
							                        <div class="grid3"><label><?php echo TEXT_PRODUCTS_TAX_CLASS; ?></label></div>
							                        <div class="grid5"><?php echo tep_draw_pull_down_menu('products_tax_class_id', $tax_class_array, $pInfo->products_tax_class_id, 'onchange="updateGross()"'); ?></div>
							                        <div class="clear"></div>
									            </div>
									            <div class="formRow">
							                        <div class="grid3"><label><?php echo TEXT_PRODUCTS_PRICE_COST; ?></label></div>
							                        <div class="grid5"><?php echo tep_draw_input_field('products_cost', $pInfo->products_cost, 'onkeyup="updateMargin()"'); ?></div>
							                        <div class="clear"></div>
									            </div>
									            <div class="formRow">
							                        <div class="grid3"><label><?php echo TEXT_PRODUCTS_PRICE_NET; ?></label></div>
							                        <div class="grid5"><?php echo tep_draw_input_field('products_price', $pInfo->products_price, 'onkeyup="updateGross(), updateMargin()"'); ?><span id='products_price_margins' class="note"></span></div>
							                        <div class="clear"></div>
									            </div>
									            <div class="formRow">
							                        <div class="grid3"><label><?php echo TEXT_PRODUCTS_PRICE_GROSS; ?></label></div>
							                        <div class="grid5"><?php echo tep_draw_input_field('products_price_gross', $pInfo->products_price_gross, 'onkeyup="updateNet(), updateMargin()"'); ?><span id='products_price_margins' class="note"></span></div>
							                        <div class="clear"></div>
									            </div>
									            <?php

													$customers_group_query = tep_db_query("select customers_group_id, customers_group_name from " . TABLE_CUSTOMERS_GROUPS . " where customers_group_id != '0' order by customers_group_id");
													$header = false;

													while ($customers_group = tep_db_fetch_array($customers_group_query)) {

														 if (tep_db_num_rows($customers_group_query) > 0) {
														   $attributes_query = tep_db_query("select customers_group_id, customers_group_price from " . TABLE_PRODUCTS_GROUPS . " where products_id = '" . $pInfo->products_id . "' and customers_group_id = '" . $customers_group['customers_group_id'] . "' order by customers_group_id");
														 } else {
															 $attributes = array('customers_group_id' => 'new');
														 }
														if (!$header) {
															$header = true;
														}
												?>
										             <div class="formRow">
								                        <div class="grid3 check">
									                        <label>Precio
									                        	<?php
									                        		if (isset($pInfo->sppcoption)) {
																    	echo tep_draw_checkbox_field('sppcoption[' . $customers_group['customers_group_id'] . ']', 'sppcoption[' . $customers_group['customers_group_id'] . ']', (isset($pInfo->sppcoption[ $customers_group['customers_group_id']])) ? 1: 0);
																  	} else {
																  		echo tep_draw_checkbox_field('sppcoption[' . $customers_group['customers_group_id'] . ']', 'sppcoption[' . $customers_group['customers_group_id'] . ']', true) . '&nbsp;' . $customers_group['customers_group_name'];
																  	}
																?> (Neto):
															</label>

														</div>
								                        <div class="grid5">
															<?php
															   if ($attributes = tep_db_fetch_array($attributes_query)) {
															   echo tep_draw_input_field('sppcprice' . $customers_group['customers_group_id'], $attributes['customers_group_price'], ' id="sppcprice2" onkeyup="document.getElementById(\'copiar\').value=this.value, updateMargin()"');
															   }  else {
																   if (isset($pInfo->sppcprice[$customers_group['customers_group_id']])) { // when a preview was done and the back button used
																	   $sppc_cg_price = $pInfo->sppcprice[$customers_group['customers_group_id']];
																   } else { // nothing in the db, nothing in the post variables
																	   $sppc_cg_price = '';
																   }
															   echo tep_draw_input_field('sppcprice' . $customers_group['customers_group_id'], $sppc_cg_price, ' id="sppcprice2" onkeyup="document.getElementById(\'copiar\').value=this.value, updateMargin()"').'';

															 }  ?>
															 <span id="products_price_margins<?php echo $customers_group['customers_group_id'];?>" class="note">
														</div>
								                        <div class="clear"></div>
										            </div>
										        <?php } ?>
										         <div class="formRow">
							                        <div class="grid3"><label>Atención:</label></div>
							                        <div class="grid12"><span class="note" style="white-space: normal;"><?php echo TEXT_CUSTOMERS_GROUPS_NOTE;?></span></div>
							                        <div class="clear"></div>
									            </div>
									        </div>
									        <div class="box-tbl grid6">
												<div class="box-head">
													<h6>Opciones extras</h6>
													<div class="clear"></div>
												</div>
								                <div class="formRow">
							                        <div class="grid3"><label>¿Producto en Oferta?</label></div>
							                        <div class="grid6">
							                        	<?php
															$sMaxLiquidacion = '';

															if( $_GET['pID'] )
															{
																// Comprobamos si esta en oferta el producto
																$aProductoOferta = tep_db_query( 'select s.specials_id, s.specials_new_products_price, s.specials_min_price, p.products_tax_class_id, p.products_price
																								  from specials s
																								  inner join products p on (p.products_id = s.products_id)
																								  where p.products_id = ' . (int)$_GET['pID'] );

																if( tep_db_num_rows( $aProductoOferta ) > 0 )
																{
																	$aProductoOferta = tep_db_fetch_array( $aProductoOferta );
																	$sPrecioOferta = $currencies->display_price( $aProductoOferta['specials_new_products_price'], tep_get_tax_rate( $aProductoOferta['products_tax_class_id'] ) );
																	$sPrecioOfertaNOIVA = $currencies->display_price( $aProductoOferta['specials_new_products_price'], 0 );
																	$sPrecio = $currencies->display_price( $aProductoOferta['products_price'], tep_get_tax_rate( $aProductoOferta['products_tax_class_id'] ) );
																	$sPrecioOfertaFloat = str_replace( array('&euro;','.',','), array('','','.'), $sPrecioOferta);
																	$sPrecioFloat = str_replace( array('&euro;','.',','), array('','','.'), $sPrecio);
																	$sPorcentaje = number_format((100 - ( ($sPrecioOfertaFloat * 100) / $sPrecioFloat )), 2) . '%';

																	if( $aProductoOferta['specials_min_price'] > 0 )
																		$sMaxLiquidacion = $aProductoOferta['specials_min_price'];

																	echo '<script type="text/javascript">
																		function editarSpecials()
																		{
																			if( document.getElementById("products_specials_delete").value == "delete" )
																			{
																				document.getElementById("spec2").style.backgroundColor = "transparent";
																				document.getElementById("spec2").style.color = "#e31717";

																				document.getElementById("products_specials_delete").value = "";
																			}

																			if( document.getElementById("products_specials").value == 3 )
																			{
																				document.getElementById("spec1").style.backgroundColor = "#5e9424";
																				document.getElementById("spec1").style.color = "#FFFFFF";

																				document.getElementById("products_specials").value = 4;
																				alert( "El producto ha sido marcado para editar la oferta. Una vez hayas completado la edicion del producto seras redirigido a las opciones de ofertas.\nPuedes desactivarlo haciendo nuevamente click en editar." );
																			}
																			else
																			{
																				document.getElementById("spec1").style.backgroundColor = "transparent";
																				document.getElementById("spec1").style.color = "#5e9424";

																				document.getElementById("products_specials").value = 3;
																			}
																		}

																		function eliminarSpecials()
																		{
																			if( document.getElementById("products_specials").value == 4 )
																			{
																				document.getElementById("spec1").style.backgroundColor = "transparent";
																				document.getElementById("spec1").style.color = "#5e9424";
																				document.getElementById("products_specials").value = 3;
																			}

																			if( document.getElementById("products_specials_delete").value == "" )
																			{
																				document.getElementById("spec2").style.backgroundColor = "#e31717";
																				document.getElementById("spec2").style.color = "#FFF";

																				document.getElementById("products_specials_delete").value = "delete";
																				alert("El producto ha sido marcado para eliminarlo de oferta. Una vez hayas completado la edicion del producto y actualizado el producto desaparecera de las ofertas.");
																			}
																			else
																			{
																				document.getElementById("spec2").style.backgroundColor = "transparent";
																				document.getElementById("spec2").style.color = "#e31717";

																				document.getElementById("products_specials_delete").value = "";
																			}

																		}
																	</script>';

																	echo 'Este producto ya esta en oferta y su oferta es de ' . $sPrecioOferta .' (' . $sPrecioOfertaNOIVA . ' sin iva) (' . $sPorcentaje . '). - <a id="spec1" style="color: #5e9424; font-weight: bold; display: inline-block; padding: 2px;" href="javascript: void(0);" onclick="editarSpecials();">[Editar oferta]</a> | <a id="spec2" style="color: #e31717; font-weight: bold; display: inline-block; padding: 2px;" href="javascript:void(0);" onclick="eliminarSpecials();">[Eliminar oferta]</a>';
																	echo '<input type="hidden" name="products_specials" id="products_specials" value="3" />';
																	echo '<input type="hidden" name="products_specials_delete" id="products_specials_delete" value="" />';
																	echo '<input type="hidden" name="sID" id="sID" value="' . $aProductoOferta['specials_id'] . '" />';
																}
																else
																{
																	echo tep_draw_radio_field('products_specials', '1', null ) . '<label style="margin-right: 10px;">SI</label>' . tep_draw_radio_field('products_specials', '0', true) . '<label>NO</label> <span style="float:left;" class="note">Si seleccionas la opci&oacute;n "SI" ser&aacute;s redirigido a las opciones de ofertas una vez introducido el producto.</span>';
																}
															}
															else
															{
																echo tep_draw_radio_field('products_specials', '1', null ) . '<label style="margin-right: 10px;">SI</label>' . tep_draw_radio_field('products_specials', '0', true) . '<label>&nbsp;NO</label> <span style="float:left;" class="note">Si seleccionas la opci&oacute;n "SI" ser&aacute;s redirigido a las opciones de ofertas una vez introducido el producto.</span>';
															}
														?>
							                        </div>
							                        <div class="clear"></div>
									            </div>
									            <div class="formRow">
							                        <div class="grid3"><label><?php echo TEXT_PRODUCTS_SHIPPING; ?></label></div>
							                        <div class="grid9"><?php echo tep_draw_pull_down_menu('products_ship_free', $free_shipping_array, $pInfo->products_ship_free); ?></div>
							                        <div class="clear"></div>
									            </div>
											</div>

											<div class="box-tbl grid6">
												<div class="box-head">
													<h6>Liquidación</h6>
													<div class="clear"></div>
												</div>
								                <div class="formRow">
							                        <div class="grid3"><label>Rebaja gradual:</div>
														<div class="grid1 check"><?php echo tep_draw_checkbox_field('products_liquidacion', '1', $pInfo->products_liquidacion, $pInfo->products_liquidacion); ?></div>
							                        <div class="clear"></div>
									            </div>
									            <div class="formRow">
							                        <div class="grid3"><label>Mínimo precio rebajado:</label></div>
							                        <div class="grid9"><?php echo tep_draw_input_field('specials_min_price', $sMaxLiquidacion); ?></div>
							                        <div class="clear"></div>
									            </div>
											</div>

										</div>

										<div class="fluid grid">
											<div class="box-tbl grid12">
												<div class="box-head">
													<h6>Rapels de descuentos por cantidades</h6>
													<div class="clear"></div>
												</div>

												<?php
													$customers_group_query = tep_db_query("select customers_group_id, customers_group_name from " . TABLE_CUSTOMERS_GROUPS . " order by customers_group_id");
													$header = false;

													if( !tep_db_num_rows($customers_group_query) > 0 )
														$messageStack->add_session(ERROR_ALL_CUSTOMER_GROUPS_DELETED, 'error');
													else
													{
														while( $customers_group = tep_db_fetch_array($customers_group_query) )
															$_hide_customers_group[] = $customers_group;
													}
												?>
												<div id="qpbpp" class="cgtabs">
													<ul class="tabnav">
														<?php
															foreach( $_hide_customers_group as $key => $cust_groups )
																echo '<li><a href="#pricebreak-' . $cust_groups['customers_group_id'] . '">' . $cust_groups['customers_group_name'] . '</a></li>' ."\n";
														?>
													</ul>
													<?php
														foreach( $_hide_customers_group as $cust_groups )
														{
															$CustGroupID = $cust_groups['customers_group_id']; ?>
															<div id="pricebreak-<?php echo $CustGroupID; ?>" class="tabdiv" <?php echo ($CustGroupID != 0 ? 'style="display: none;"' : ''); ?>>
																<div class="formRow">
																	<div class="grid2 check">
																		<label>
																			<?php echo TEXT_PRODUCTS_PRICE_NET; ?>
																			<?php
																				if( $CustGroupID != 0 ){
																						if( isset($pInfo->sppcoption) )
																							echo tep_draw_checkbox_field('sppcoption[' . $CustGroupID . ']', 'sppcoption[' . $CustGroupID . ']', (isset($pInfo->sppcoption[$CustGroupID])) ? 1: 0);
																						else
																							echo tep_draw_checkbox_field('sppcoption[' . $CustGroupID . ']', 'sppcoption[' . $CustGroupID . ']', true);
																				}
																			?>
																		</label>
																	</div>
																	<div class="grid6">
																		<?php
																				if( $CustGroupID != 0 )
																				{
																					if( isset($pInfo->sppcprice[$CustGroupID] ) )
																						$sppc_cg_price = $pInfo->sppcprice[$CustGroupID];
																					else
																						$sppc_cg_price = ''; // nothing in the db, nothing in the post variables

																					echo '&nbsp;' . tep_draw_input_field('sppcprice[' . $CustGroupID . ']', $sppc_cg_price ,'id="copiar" readonly');
																				}
																				else
																					echo tep_draw_input_field('products_price_retail_net', $pInfo->products_price, 'readonly');
																				// end if/else ($CustGroupID != 0)
																		?>
																	</div>
																	<div class="clear"></div>
																</div>
																<div class="formRow">
																	<div class="grid2"><label><?php echo TEXT_PRODUCTS_QTY_BLOCKS; ?></label></div>
																	<div class="grid9">
																		<?php echo tep_draw_input_field('products_qty_blocks[' . $CustGroupID . ']', $pInfo->products_qty_blocks[$CustGroupID], 'size="10"'); ?>
																		<span class="note"><?php echo TEXT_PRODUCTS_QTY_BLOCKS_HELP; ?></span>
																	</div>
																	<div class="clear"></div>
																</div>
																<div class="formRow">
																	<div class="grid2"><label><?php echo TEXT_PRODUCTS_MIN_ORDER_QTY; ?></label></div>
																	<div class="grid9">
																		<?php echo tep_draw_input_field('products_min_order_qty[' . $CustGroupID . ']', $pInfo->products_min_order_qty[$CustGroupID], 'size="10"'); ?>
																		<span class="note"><?php echo TEXT_PRODUCTS_MIN_ORDER_QTY_HELP; ?></span>
																	</div>
																	<div class="clear"></div>
																</div>

																<?php
																	$i = 0; // for alternate coloring of rows (zebra striping)
																	for( $count = 0; $count <= (PRICE_BREAK_NOF_LEVELS - 1); $count++ )
																	{
																		$bgcolor = ($i++ & 1) ? '#EAF1FA' : '#ffffff'; // for zebra striping ?>
																		 <div class="formRow">
																			<div class="grid2"><label><?php echo TEXT_PRODUCTS_PRICE  . " " . ($count + 1); ?>:</label></div>
																			<div class="grid9 moreFields">
																				<ul>
																					<?php
																						if( is_array( $pInfo->products_price_break[$CustGroupID]) && array_key_exists($count, $pInfo->products_price_break[$CustGroupID]) )
																						{
																							echo '<li style="margin-right: 50px;">'.tep_draw_input_field('products_price_break[' . $CustGroupID .'][' . $count . ']', $pInfo->products_price_break[$CustGroupID][$count], 'size="10"').'</li>';
																							echo '<li>'.TEXT_PRODUCTS_QTY.'</li>';
																							echo '<li>'.tep_draw_input_field('products_qty[' . $CustGroupID .'][' . $count . ']', $pInfo->products_qty[$CustGroupID][$count], 'size="10"').'</li>';
																							echo tep_draw_hidden_field('products_price_break_id[' . $CustGroupID .'][' . $count . ']', $pInfo->products_price_break_id[$CustGroupID][$count]);

																							if( isset($pInfo->products_price_break_id[$CustGroupID][$count]) && tep_not_null($pInfo->products_price_break_id[$CustGroupID][$count]) )
																								echo '<li>'.tep_draw_checkbox_field('products_delete[' . $CustGroupID .'][' . $count . ']', 'y', (isset($pInfo->products_delete[$CustGroupID][$count]) ? 1 : 0)) . TEXT_PRODUCTS_DELETE.'</li>';
																						}
																						else
																						{
																							echo '<li style="margin-right: 50px;">'.tep_draw_input_field('products_price_break[' . $CustGroupID .'][' . $count . ']', '', 'size="10"').'</li>';
																							echo '<li>'.TEXT_PRODUCTS_QTY.'</li>';
																							echo '<li>'.tep_draw_input_field('products_qty[' . $CustGroupID .'][' . $count . ']', '', 'size="10"').'</li>';
																						}
																					?>
																				</ul>
																			 </div>
																			 <div class="clear"></div>
																		 </div>
																<?php } ?>
															</div>
													<?php } ?>
												</div>
										</div>
									</td>
								</tr>


								<!-- IMAGENES -->
								<tr class="tab-new" style="display: none;"  data-id="3">
									<td style="display:block;">
										<div class="fluid grid">
											<div id="products_image_upload" class="box-tbl grid12">
												<div class="box-head">
													<h6>Imágenes del producto</h6>
													<div class="clear"></div>
												</div>
												<div class="plupload_filelist_header">
													<div class="plupload_file_name">Imagen</div>
													<div class="plupload_file_name2">Nombre</div>
													<div class="plupload_file_status">Acción</div>
													<div class="plupload_clearer"></div>
												</div>
												<table class="plupload_filelist" id="uploader_filelist">
													<tbody id="drop-rows-image-prod">
														<?php
															// Imagen principal
															if( $pInfo->products_image != '' )
															{
																echo '<tr>';
																	echo '<td class="plupload_image"><a target="_blank" href="' . DIR_WS_CATALOG_IMAGES . 'productos/' . $pInfo->products_image . '">' . tep_image( DIR_WS_CATALOG_IMAGES . 'productos/' . $pInfo->products_image, $pInfo->products_name, 80, 80 ) . '</a></td>';
																	echo '<td>' . $pInfo->products_image . '</td>';
																	echo '<td class="plupload_accion"><a href="javascript:void(0)" data-id="' . $pInfo->products_id . '" data-image="' . $pInfo->products_image . '" data-type="products" class="plupload_accion_delete dlte-image-catg"><span class="icos-trash"></span></a></td>';
																echo '</tr>';
															}

															// Subimages
															if( is_string( $pInfo->products_subimages ) && is_json( $pInfo->products_subimages ) )
															{
																$aImagenes = json_decode( $pInfo->products_subimages );
																foreach( $aImagenes as $sImagen )
																{
																	echo '<tr>';
																		echo '<td class="plupload_image"><a target="_blank" href="' . DIR_WS_CATALOG_IMAGES . 'productos/' . $pInfo->products_image . '"><img src="'.DIR_WS_CATALOG_IMAGES . 'productos/' . $sImagen.'" width="80" height="80" /></a></td>';
																		echo '<td>' . $sImagen . '</td>';
																		echo '<td class="plupload_accion"><a href="javascript:void(0)" data-id="' . $pInfo->products_id . '" data-image="' . $sImagen . '" data-type="products" class="plupload_accion_delete dlte-image-catg"><span class="icos-trash"></span></a></td>';
																	echo '</tr>';
																}
															}
														?>
													</tbody>
												</table>
												<div class="plupload_filelist_footer">
													<div class="plupload_buttons">
														<div id="dx-file-images"></div>
														<a id="dx-file-images-buttom" class="buttonS bGreen" href="#" style="position: relative; z-index: 0;">Añadir archivos</a>
													</div>
												</div>
											</div>
										</div>
									</td>
								</tr>

								<!-- OPCIONES -->
								<tr class="tab-new" style="display: none;"  data-id="4">
									<td style="display:block;">

										<div class="fluid grid">
											<div class="box-tbl grid12">
												<div class="box-head">
													<h6>Opciones y valores del producto (Atributos)</h6>
													<div class="clear"></div>
												</div>
								                <div class="formRow">
								                	<?php require_once( 'attributeManager/includes/attributeManagerPlaceHolder.inc.php' )?>
												</div>
											</div>
										</div>
									</td>
								</tr>



								<!-- OTROS -->
								<tr class="tab-new" style="display: none;" data-id="5">
									<td style="display:block;">

										<div class="fluid grid">
											<div class="box-tbl grid6">
												<div class="box-head">
													<h6>Formas de Envío disponible para este producto</h6>
													<div class="clear"></div>
												</div>
												<?php
												$module_type = 'shipping';
												$module_directory = DIR_FS_CATALOG_MODULES . 'shipping/';
												$module_key = 'MODULE_SHIPPING_INSTALLED';
												$current_methods = [];
												if (isset($product) && !is_null($product['shipping_methods'])) {
													$current_methods = explode(';', $product['shipping_methods']);
												}

												$file_extension = substr((string) $PHP_SELF, strrpos((string) $PHP_SELF, '.'));
												$directory_array = [];

												if ($dir = @dir($module_directory)) {
													while ($file = $dir->read()) {
														if (!is_dir($module_directory . $file) && substr($file, strrpos($file, '.')) === $file_extension) {
															$directory_array[] = $file;
														}
													}

													sort($directory_array);
													$dir->close();
												}

												for ($i = 0, $n = count($directory_array); $i < $n; $i++) {
													$file = $directory_array[$i];

													include(DIR_FS_CATALOG_LANGUAGES . $language . '/modules/' . $module_type . '/' . $file);
													include($module_directory . $file);

													$class = substr($file, 0, strrpos($file, '.'));

													if (tep_class_exists($class)) {
														$module = new $class;
														if ($module->check() > 0) {
															echo ' <div class="formRow">
																		<div class="grid12 check"><input type="checkbox" name="shipping_methods[]" value="' . $class . '"' . (in_array($class, $current_methods) ? ' CHECKED' : '') . '><label>' . $module->title . '</label></div>
																		<div class="clear"></div>
																	</div>
																';
														}
													}
												}
												?>

									    	</div>


											<div class="box-tbl grid6">
												<div class="box-head">
													<h6>Formas de Pago disponible para este producto</h6>
													<div class="clear"></div>
												</div>
												<?php
												$module_type = 'payment';
												$module_directory = DIR_FS_CATALOG_MODULES . 'payment/';
												$module_key = 'MODULE_PAYMENT_INSTALLED';
												$current_methods = [];
												if (isset($product) && !is_null($product['payment_methods'])) {
													$current_methods = explode(';', $product['payment_methods']);
												}

												$file_extension = substr((string) $PHP_SELF, strrpos((string) $PHP_SELF, '.'));
												$directory_array = [];

												if ($dir = @dir($module_directory)) {
													while ($file = $dir->read()) {
														if (!is_dir($module_directory . $file) && substr($file, strrpos($file, '.')) === $file_extension) {
															$directory_array[] = $file;
														}
													}

													sort($directory_array);
													$dir->close();
												}

												for ($i = 0, $n = count($directory_array); $i < $n; $i++) {
													$file = $directory_array[$i];

													if (file_exists(DIR_FS_CATALOG_LANGUAGES . $language . '/modules/' . $module_type . '/' . $file)) {
														include(DIR_FS_CATALOG_LANGUAGES . $language . '/modules/' . $module_type . '/' . $file);
													}
													include($module_directory . $file);

													$class = substr($file, 0, strrpos($file, '.'));

													if (tep_class_exists($class)) {
														$module = new $class;
														if ($module->check() > 0) {
															echo '<div class="formRow">
																	<div class="grid12 check"><input type="checkbox" name="payment_methods[]" value="' . $class . '"' . (in_array($class, $current_methods) ? ' CHECKED' : '') . '><label>' . $module->title . '</label></div>
																	<div class="clear"></div>
																</div>';
														}
													}
												}
												?>
									    	</div>
									    </div>
									</td>
								</tr>

								<!-- SEO -->
								<tr class="tab-new" style="display: none;" data-id="6">
									<td style="display:block;">

										<div class="fluid grid">
											<div class="box-tbl grid12">
												<div class="box-head">
													<div class="grid9"><h6>SEO</h6></div>
													<div class="grid3">
														<?php
															$aIdiomas = tep_get_languages();

															$aAux = array();

															foreach( $aIdiomas as $aIdioma )
																$aAux[] = array( 'id' => $aIdioma['id'], 'text' => $aIdioma['name'] );

															echo '<p style="position: relative; float: right; top: 3px; right: 43px;">';
																echo '<label>Seleccionar idioma: </label>';
																echo tep_draw_pull_down_menu( 'idioma', $aAux, '', 'id="products_seo_idioma"');
															echo '</p>';
														?>
													</div>
													<div class="clear"></div>
												</div>


												<?php
													$aDatosSeo = array();

													// Obtenemos los valores de los campos seo
													if( $_GET['pID'] )
													{
														$aDatos = tep_db_query( 'select language_id, products_seo_title, products_seo_keywords, products_seo_description
																				 from products_description
																				 where products_id = ' . (int)$_GET['pID'] );

														while( $aDato = tep_db_fetch_array( $aDatos ) )
															$aDatosSeo[$aDato['language_id']] = $aDato;
													}

													// Campos del formulario
													$aCampos = array(
														array( 'title' => 'Título', 'text' => 'Título que se muestra en la cabecera del navegador.', 'row' => 'products_seo_title' ),
														array( 'title' => 'Palabras claves', 'text' => 'Palabras, frases clave o términos de búsqueda que los buscadores usaran para encontrar información.', 'row' => 'products_seo_keywords' ),
														array( 'title' => 'Descripción', 'text' => 'Descripcion que nos ayudará a indicar cúal es el contenido de nuestra página. Recomendamos que tenga entre 70 y 156 caracteres (incluyendo espacios).', 'row' => 'products_seo_description' )
													);

													// Recorremos los idiomas
													foreach( $aIdiomas as $aIdioma )
													{
														echo '<div style="display: none;" class="tab-seo-idma" id="seo-' . $aIdioma['id'] . '">';

														// Recorremos los campos
														foreach( $aCampos as $aCampo )
														{
															echo '<div class="formRow">';
																echo tep_image( DIR_WS_CATALOG_LANGUAGES . $aIdioma['directory'] . '/images/' . $aIdioma['image'], $aIdioma['name'], '', '', 'style="position: absolute; top: 10px; right: 10px;"' );

																echo '<div class="grid3" style="margin: 0px;">';
																	echo '<label>' . $aCampo['title'] . '</label>';
																	echo '<span class="note" style="display: block; width: 100%; float: left; margin-top: -8px; font-style: italic; white-space: normal;">' . $aCampo['text'] . '</span>';
																echo '</div>';
																echo '<div class="grid9">';

																	switch( $aCampo['row'] )
																	{
																		case 'products_seo_description':
																		case 'products_seo_keywords':
																			echo tep_draw_textarea_field( $aCampo['row'] . '[' . $aIdioma['id'] . ']', '', 5, 5, $aDatosSeo[$aIdioma['id']][$aCampo['row']], 'style="margin: 0px 0px 10px;"' );
																		break;

																		default:
																			echo tep_draw_input_field( $aCampo['row'] . '[' . $aIdioma['id'] . ']', $aDatosSeo[$aIdioma['id']][$aCampo['row']], 'style="width: 100%; margin: 0px 0px 10px;"' ) . '<br/>';
																		break;
																	}

																echo '</div>';
																echo '<div class="clear"></div>';
															echo '</div>';
														}

														echo '</div>';
													}
												?>
											</div>
										</div>
									</td>
								</tr>

								<tr class="tab-new" style="display: none;" data-id="9">
									<td style="display:block;">
										<div class="fluid grid">
											<div class="box-tbl grid6">
												<div class="box-head">
													<h6>Productos alternativos</h6>
													<div class="clear"></div>
												</div>
												<?php
												$aDatos = tep_db_query('SELECT pd.products_name, pda.products_id_alt, pda.id FROM products_descat_alternativos pda LEFT JOIN products_description pd ON pd.products_id = pda.products_id_alt  WHERE pda.products_id = '. (int)$_GET['pID'] .' AND pd.language_id = ' . (int)$languages_id );
												while( $aDato = tep_db_fetch_array( $aDatos ) ):
												?>
												<div class="formRow">
													<div class="grid8"><?php echo $aDato['products_name']; ?></div>
													<div class="grid2" style="text-align: right;"><a class="borrarElemento buttonS bRed" href="<?php echo tep_href_link('categories.php', 'action=remove-alternative&pid='.$aDato['id']); ?>">Borrar</a></div>
													<div class="clear"></div>
												</div>
												<?php endwhile; ?>

											</div>
											<div class="box-tbl grid6">
												<div class="box-head">
													<h6>Buscar productos</h6>
													<div class="clear"></div>
												</div>
												<div class="formRow">
													<p>
														<input type="text" required name="nombre" class="autocomplete" <?php echo ($_GET['add'] ? 'autofocus' : ''); ?> placeholder="Nombre del producto"/>
															<div id="multiples"></div>
														<?php if(!empty($productos)): ?>
															<?php foreach ($productos as $producto): ?>
																<input type="hidden" name="exclude[]" value="<?php echo $producto['products_id']; ?>" class="Exclude" />
															<?php endforeach; ?>
														<?php endif; ?>
													</p>

													<?php $sJavascript .= '<script type="text/javascript">
														$(function() {
															$(".autocomplete").autocomplete(
															{
																source: "' . tep_href_link( 'categories.php' ) . '?action=autocomplete&" + $(".Exclude").serialize(),
																minLength: 3,
																select: function( event, ui )
																{
																	var sID = ui.item.id;
																	$(this).parent().find("#products_id").val( sID );
																},
																response: function( event, ui ) {
																	//alert(ui.toSource())
																	$("#multiples").html("")
																	$.each(ui.content, function( index, value ) {
																	  $("<p><label><input type=\'checkbox\' name=\'products_alt_id[]\' value=\'" + value.id + "\'> " + value.label + " " + "</label></p>").appendTo("#multiples")
																	});
																}
															});
															$("#addAllProducts").click(function() {
																$("#multiples input").prop("checked", true);
																$(this).parents("form").submit();
															})
															$(".borrarElemento").click(function() {
																href = $(this).attr("href")
																if (confirm("¿Estás seguro que deseas borrar el elemento?")) {
																	location.href = href
																}
																return false;
															})
														})
														</script>
														<style type="text/css">
														/*.ui-autocomplete
														{
															display: none!important;
														}*/
													</style>
													'; ?>
												</div>
										</div>
																													</div>
									</td>
								</tr>


								<!-- REPUESTOS -->

								<tr class="tab-new" style="display: none;" data-id="7">
									<td style="display:block;">
										<?php
											// Obtenemos un select que sera el alias de los repuestos
											$aArraySelectAlias = array( array( 'id' => '', 'text' => '' ) );

											for( $nCont = 1; $nCont <= 99; $nCont++ )
												$aArraySelectAlias[] = array( 'id' => $nCont, 'text' => $nCont );

											for( $nCont = 65; $nCont <= 90; $nCont++ )
												$aArraySelectAlias[] = array( 'id' => chr( $nCont ), 'text' => chr( $nCont ) );

											echo '<div id="select_alias_molde" style="display: none;">' . tep_draw_pull_down_menu( '', $aArraySelectAlias, '' ) . '</div>';

											// Array de posicion
											$aArraySelectPosicion = array(
												array( 'id' => 'top', 'text' => '&#8593; Arriba' ),
												array( 'id' => 'dia_sup_drch', 'text' => '&#8599; Diagonal superior derecha' ),
												array( 'id' => 'derecha', 'text' => '&#8594; Derecha' ),
												array( 'id' => 'dia_inf_drch', 'text' => '&#8600; Diagonal inferior derecha' ),
												array( 'id' => 'bottom', 'text' => '&#8595; Abajo' ),
												array( 'id' => 'dia_inf_izqd', 'text' => '&#8601; Diagonal inferior izquierda' ),
												array( 'id' => 'izquierda', 'text' => '&#8592; Izquierda' ),
												array( 'id' => 'dia_sup_izqd', 'text' => '&#8598; Diagonal superior izquierda' ),
											);

											// Obtenemos la imagen de repuesto
											$products_id = tep_db_prepare_input( $_GET['pID'] );
											$aImagenes = glob( getcwd() . '/../images/repuestos/' . $products_id . '-imagen.*' );

											// Si no tenemos imagen mostramos un mensaje
											if( count( $aImagenes ) == 0 )
												echo $messageStack->show( array('class' => 'info', 'text' => 'Se debe subir una imagen para poder añadir repuestos') );
										?>

										<div id="repuestos_autocomplete" class="fluid grid" style="display: <?php echo (count($aImagenes) > 0 ? 'block' : 'none'); ?>">
											<div class="box-tbl grid12">
												<div class="box-head">
													<div class="grid12"><h6>Repuestos</h6></div>
													<div class="clear"></div>
												</div>
												<div class="formRow">
													<div class="grid3">
														<label>Buscar repuesto:</label>
													</div>
													<div class="grid9">
														<input type="text" id="repuesto-autocomplete" />
													</div>
													<div class="clear"></div>
												</div>
												<div class="formRow box-tbl" style="margin-top: 0px; border: 0px none; padding: 0px;">
													<table id="table-repuestos" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">
														<thead style="display: table-row-group;">
															<tr>
																<td width="110" style="text-align: center;">Alias</td>
																<td width="110" style="text-align: center;">Posicion</td>
																<td width="110" style="text-align: center;">Tamaño</td>
																<td width="110" style="text-align: center;">Imagen</td>
																<td style="text-align: left;">Producto</td>
																<td style="text-align: left;"></td>
																<td width="110" style="text-align: center;">Acciones</td>
															</tr>
														</thead>
														<tbody>
															<?php
																$sPuntosImagen = '';

																if( isset( $products_id ) )
																{
																	$aDatos = tep_db_query( 'select rp.attributes, rp.id_repuesto, rp.products_id, rp.products_id_repuesto, rp.x, rp.y, rp.alias, rp.posicion, rp.size, pd.products_name, p.products_image
																							 from repuesto rp
																							 inner join products p on (p.products_id = rp.products_id_repuesto)
																							 inner join products_description pd on (pd.products_id = rp.products_id_repuesto)
																							 where pd.language_id = 3 and rp.products_id = ' . $products_id );

																	$nCont = 1;
																	$contadorAttributos = 0;
																	while( $aDato = tep_db_fetch_array( $aDatos ) )
																	{
																		echo "<tr data-id='" . $nCont . "'>";
																			echo "<td width='110' style='text-align: center;'>";
																				echo tep_draw_pull_down_menu( 'rp_alias[]', $aArraySelectAlias, $aDato['alias'] );
																				echo "<input name='rp_products_id_repuesto[]' value='" . $aDato['products_id_repuesto'] . "' type='hidden'>";
																				echo "<input class='rp_x' name='rp_x[]' value='" . $aDato['x'] . "' type='hidden'>";
																				echo "<input class='rp_y' name='rp_y[]' value='" . $aDato['y'] . "' type='hidden'>";
																			echo "</td>";
																			echo "<td width='110' style='text-align: center;'>" . tep_draw_pull_down_menu( 'rp_posicion[]', $aArraySelectPosicion, $aDato['posicion'], 'class="rp_posicion"' ) . "</td>";
																			echo "<td width='110' style='text-align: center;'><input readonly class='rp_size' name='rp_size[]' value='" . $aDato['size'] . "'/></td>";
																			echo "<td width='110' style='text-align: center;'>" . tep_image(DIR_WS_CATALOG_IMAGES . 'productos/' . $aDato['products_image'], '', 50, 50 ) . "</td>";
																			echo "<td style='text-align: left;'>" . $aDato['products_name'] . "</td>";



																			$sql = "SELECT popt.products_options_name, popt.products_options_id, patrib.options_values_id , patrib.products_attributes_id, IF(ps.products_stock_quantity < 0, 0, ps.products_stock_quantity) as products_stock_quantity, pag.options_values_price, pov.products_options_values_name
																			FROM products_options popt
																			LEFT JOIN products_attributes patrib ON patrib.options_id = popt.products_options_id
																			LEFT JOIN products_options_values pov ON pov.products_options_values_id = patrib.options_values_id AND pov.language_id = 3
																			LEFT JOIN products_attributes_groups pag ON pag.products_attributes_id = patrib.products_attributes_id
																			LEFT JOIN products_stock ps ON ps.products_stock_attributes = CONCAT(popt.products_options_id, '-', patrib.options_values_id) AND ps.products_id = '" . $aDato['products_id_repuesto'] . "'
																			WHERE patrib.products_id='" . $aDato['products_id_repuesto'] . "' AND popt.language_id = 3 GROUP BY options_values_id ORDER BY options_values_price asc";
																			$query = tep_db_query($sql);
																			$attributes = '';

																			$attributesActual = [];
																			if ($aDato['attributes'] != '') {
																				$attributesActual = json_decode(stripslashes($aDato['attributes']), true);
																			}

																			if (tep_db_num_rows($query) > 0) {

																				$attributes .= '<ul style="display: grid;grid-template-columns: repeat(3, 1fr);gap: 3px;">';
																				while ($attribute = tep_db_fetch_array($query)) {
																					$id_attribute = $attribute['products_options_id'].'-'.$attribute['options_values_id'];
																					$selected = in_array($id_attribute, $attributesActual) ? ' checked="checked" ' : '';
																					$attributes .= '
																					<li>
																						<label><input type="checkbox" name="respuestos_attributes['.$contadorAttributos.'][]" value="'.$id_attribute.'" '.$selected.'> '.$attribute['products_options_name'].' '.$attribute['products_options_values_name'].'</label>
																					</li>';
																				}
																				$attributes .= '<ul>';
																			}

																			echo "<td style='text-align: left;'>" . $attributes . "</td>";
																			echo "<td width='110' style='text-align: center;'><a href='javascript: void(0)' class='buttonS bRed'>Eliminar</a></td>";
																		echo "</tr>";

																		switch( $aDato['posicion'] )
																		{
																			case 'top':
																			case 'bottom':
																				$sPuntosImagen .= '<div style="left: ' . $aDato['x'] . 'px; top: ' . $aDato['y'] . 'px; height: ' . $aDato['size'] . 'px" class="pnto ' . $aDato['posicion'] . '" data-id="' . $nCont . '"><span>' . $aDato['alias'] . '</span><div></div></div>';
																			break;

																			case 'dia_sup_drch':
																			case 'dia_inf_drch':
																			case 'dia_inf_izqd':
																			case 'dia_sup_izqd':
																				$nDiagonal = sqrt( pow($aDato['size'],2) + pow($aDato['size'],2) );
																				$sPuntosImagen .= '<div style="left: ' . $aDato['x'] . 'px; top: ' . $aDato['y'] . 'px; height: ' . $aDato['size'] . 'px; width: ' . $aDato['size'] . 'px;" class="pnto ' . $aDato['posicion'] . '" data-id="' . $nCont . '"><span>' . $aDato['alias'] . '</span><div style="width: 1px; height: ' . $nDiagonal . 'px;"></div></div>';
																			break;

																			case 'izquierda':
																			case 'derecha':
																				$sPuntosImagen .= '<div style="left: ' . $aDato['x'] . 'px; top: ' . $aDato['y'] . 'px; height: 27px; width: ' . $aDato['size'] . 'px;" class="pnto ' . $aDato['posicion'] . '" data-id="' . $nCont . '"><span>' . $aDato['alias'] . '</span><div style="width: ' . $aDato['size'] . 'px; height: 1px;"></div></div>';
																			break;
																		}

																		$nCont++;
																		$contadorAttributos++;
																	}
																}
															?>
														</tbody>
													</table>
												</div>
											</div>
										</div>

										<div class="fluid grid">
											<div class="box-tbl grid12" style="min-width: 785px;">
												<div class="box-head">
													<div class="grid12"><h6>Imagen</h6></div>
													<div class="clear"></div>
												</div>
												<div class="formRow">
													<div id="repuesto-imagen" style="width: 750px; margin: 0px auto !important; float: none; overflow:hidden;" class="grid12">
														<div class="imge">
															<?php
																if( count( $aImagenes ) > 0 )
																	echo '<img src="' . str_replace( getcwd() . '/', '', $aImagenes[0] ) . '" />';
															?>
														</div>

														<?php echo $sPuntosImagen; ?>
													</div>
													<div class="clear"></div>
												</div>
												<div class="formRow">
													<div class="grid12">
														<?php if( count( $aImagenes ) > 0 ): ?>
															<a id="repuesto-boton-eliminar-imagen" class="buttonS bRed" href="#" style="position: relative; z-index: 0; margin-right: 5px;">Eliminar imagen</a>
														<?php endif; ?>
														<a id="repuesto-boton-upload-imagen" class="buttonS bGreen" href="#" style="position: relative; z-index: 0; margin-right: 15px;">Añadir imagen</a>
														<span class="note" style="white-space: inherit; display: inline-block;">El tamaño de la imagen debe ser de 750x450 y se aconseja tener bastante fondo blanco para poder añadir los números de repuesto</span>
														<div style="visibility: hidden; width: 1px; height: 1px; display: none; opacity: 0;"><input id="repuesto-input-upload-imagen" name="repuesto-input-upload-imagen" type="file" /></div>
													</div>
													<div class="clear"></div>
												</div>
											</div>
										</div>
									</td>
								</tr>

								<?php echo tep_draw_hidden_field('products_date_added', (tep_not_null($pInfo->products_date_added) ? $pInfo->products_date_added : date('Y-m-d'))); ?>

							</table>
						</div>
					</form>
					</td>
				</tr>

            <?php
            }
            elseif ($action == 'new_product_preview')
            {
				// begin bundled products
				  function display_bundle($bundle_id, $bundle_price, $lid) {
					global $pInfo, $currencies, $_POST, $_GET;
				  ?>
				  <table border="0" width="95%" cellspacing="1" cellpadding="2" class="columnLeft">
					<tr class="menuBoxContent">
					  <td>
						<table border="0" width="100%" cellspacing="0" cellpadding="2">
						  <tr>
							<td class="main" colspan="5"><b>
							<?php
						  $bundle_sum = 0;
						  $bdata = array();
							  echo TEXT_PRODUCTS_BY_BUNDLE . "</b></td></tr>\n";
						  if ((isset($_GET['read']) && ($_GET['read'] == 'only')) || (isset($_GET['pID']) && ($_GET['pID'] != $bundle_id)) || (!isset($_GET['pID']) && is_numeric($bundle_id))) {
							  $bundle_query = tep_db_query(" SELECT pd.products_name, pb.*, p.products_bundle, p.products_id, p.products_model, p.products_price, p.products_image FROM " . TABLE_PRODUCTS . " p INNER JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON p.products_id=pd.products_id INNER JOIN " . TABLE_PRODUCTS_BUNDLES . " pb ON pb.subproduct_id=pd.products_id WHERE pb.bundle_id = " . (int)$bundle_id . " and language_id = '" . (int)$lid . "'");
							  while ($bundle_data = tep_db_fetch_array($bundle_query)) {
								$bdata[] = $bundle_data;
							  }
							} else {
							for ($i=0, $n=100; $i<$n; $i++) {
							  if (isset($_POST['subproduct_' . $i . '_qty']) && $_POST['subproduct_' . $i . '_qty'] > 0) {
								$tmp = array('bundle_id' => $bundle_id,
										   'subproduct_id' => (int)$_POST['subproduct_' . $i . '_id'],
										   'subproduct_qty' => (int)$_POST['subproduct_' . $i . '_qty']);
								$bundle_query = tep_db_query(" SELECT pd.products_name, p.products_bundle, p.products_id, p.products_model, p.products_price, p.products_image FROM " . TABLE_PRODUCTS . " p INNER JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON p.products_id=pd.products_id WHERE p.products_id = " . (int)$_POST['subproduct_' . $i . '_id'] . " and language_id = '" . (int)$lid . "'");
								  while ($bundle_data = tep_db_fetch_array($bundle_query)) {
									$bdata[] = array_merge($tmp, $bundle_data);
								  }
							  }
							}
							}
							foreach ($bdata as $bundle_data) {
							  echo "<tr><td class=main valign=top>" ;
							  echo tep_image(DIR_WS_CATALOG_IMAGES . $bundle_data['products_image'], $bundle_data['products_name'], intval(SMALL_IMAGE_WIDTH / 2), intval(SMALL_IMAGE_HEIGHT / 2), 'hspace="1" vspace="1"') . '</td>';
							  // comment out the following line to hide the subproduct qty
							  echo "<td class=main align=right><b>" . $bundle_data['subproduct_qty'] . "&nbsp;x&nbsp;</b></td>";
							  echo  '<td class=main><a href="' . tep_catalog_href_link('product_info.php', 'products_id=' . (int)$bundle_data['products_id']) . '" target="_blank"><b>&nbsp;(' . $bundle_data['products_model'] . ') '  . $bundle_data['products_name'] . '</b></a>';
							  if ($bundle_data['products_bundle'] == "yes") display_bundle($bundle_data['subproduct_id'], $bundle_data['products_price'], $lid);
							  echo '</td>';
							  echo '<td align=right class=main><b>&nbsp;' .  $currencies->display_price($bundle_data['products_price'], tep_get_tax_rate($pInfo->products_tax_class_id)) . "</b></td></tr>\n";
							  $bundle_sum += $bundle_data['products_price']*$bundle_data['subproduct_qty'];
							  }
							  $bundle_saving = $bundle_sum - $bundle_price;
							  $bundle_sum = $currencies->display_price($bundle_sum, tep_get_tax_rate($pInfo->products_tax_class_id));
							  $bundle_saving =  $currencies->display_price($bundle_saving, tep_get_tax_rate($pInfo->products_tax_class_id));
							  // comment out the following line to hide the "saving" text
							  echo "<tr><td colspan=5 class=main><p><b>" . TEXT_RATE_COSTS . '&nbsp;' . $bundle_sum . '</b></td></tr><tr><td class=main colspan=5><font color="red"><b>' . TEXT_IT_SAVE . '&nbsp;' . $bundle_saving . "</font></b></td></tr>\n";
							?>
					  </table></td>
					</tr>
				  </table>
				  <?php
				  }
				// end bundled products


                if( tep_not_null( $_POST ) )
                {
                    $pInfo = new objectInfo($_POST);
                    $products_name = $_POST['products_name'];
                    $products_description = $_POST['products_description'];
                    $products_url = $_POST['products_url'];
                    $products_seo_url = $_POST['products_seo_url'];
                    $products_to_rss = $_POST['products_to_rss'];
					$amazon_status = $_POST['amazon_status'];
                    // BOF QPBPP for SPPC
                    $price_breaks_array = array();

                    if( isset($_POST['products_price_break'][0]) && isset($_POST['products_qty'][0]) )
                    {
                        foreach( $_POST['products_price_break'][0] as $index => $products_price )
                        {
                            if( tep_not_null($products_price) && tep_not_null($_POST['products_qty'][0][$index]) && !isset($_POST['products_delete'][0][$index]) )
                            {
                                $price_breaks_array[] = array('products_price' => $products_price, 'products_qty' => $_POST['products_qty'][0][$index]);
                            }
                        } // end foreach ($_POST['products_price_break'][0] as ...
                        usort($price_breaks_array, "sortByQty");
                    } // end if (isset($_POST['products_price_break'][0]) && ...
                    // EOF QPBPP for SPPC
                }
                else
                {
                    $product_query = tep_db_query("select p.products_id, p.products_fileupload, p.products_pdfupload, pd.language_id, pd.products_name, pd.products_seo_url, pd.products_description, pd.products_url, p.products_quantity, p.products_quantity_deseada, p.exclude_feedmachine, p.check_stock, p.products_model, p.shipping_methods, p.payment_methods, p.products_youtube, p.products_image, p.products_price, p.products_cost, p.products_weight, p.ISBN, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_import_exclude, p.products_import_origin, p.products_featured_until, p.products_status, p.products_featured, p.manufacturers_id, p.products_to_rss, p.amazon_status, p.products_qty_blocks, p.products_min_order_qty, p.products_ship_free, p.products_bundle, p.sold_in_bundle_only from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where p.products_id = pd.products_id and p.products_id = '" . (int)$_GET['pID'] . "'");

                    $product = tep_db_fetch_array($product_query);
                    $pInfo = new objectInfo($product);

                    $products_image_name = $pInfo->products_image;
                    $products_fileupload = $pInfo->products_fileupload;
					$products_pdfupload = $pInfo->products_pdfupload;

                    // by customer_group_id, like we get back in $_POST values
                    unset($pInfo->products_qty_blocks);
                    $pInfo->products_qty_blocks[0] = $product['products_qty_blocks'];
                    unset($pInfo->products_min_order_qty);
                    $pInfo->products_min_order_qty[0] = $product['products_min_order_qty'];
                    // price_breaks_array is taken care of by PriceFormatterAdmin.php
                    // EOF QPBPP for SPPC
                }

                $form_action = (isset($_GET['pID'])) ? 'update_product' : 'insert_product';

                echo tep_draw_form($form_action, FILENAME_CATEGORIES, 'cPath=' . $cPath . (isset($_GET['pID']) ? '&pID=' . $_GET['pID'] : '') . '&action=' . $form_action, 'post', 'enctype="multipart/form-data"');

                $languages = tep_get_languages();

                for ($i=0, $n=sizeof($languages); $i<$n; $i++)
                {
                    if( isset($_GET['read']) && ($_GET['read'] == 'only') )
                    {
                        $pInfo->products_name = tep_get_products_name($pInfo->products_id, $languages[$i]['id']);
                        $pInfo->products_description = tep_get_products_description($pInfo->products_id, $languages[$i]['id']);
                        $pInfo->products_url = tep_get_products_url($pInfo->products_id, $languages[$i]['id']);
                        $pInfo->products_seo_url = tep_get_products_seo_url($pInfo->products_id, $languages[$i]['id']);
                    }
                    else
                    {
                        $pInfo->products_name = tep_db_prepare_input($products_name[$languages[$i]['id']]);
                        $pInfo->products_description = tep_db_prepare_input($products_description[$languages[$i]['id']]);
                        $pInfo->products_url = tep_db_prepare_input($products_url[$languages[$i]['id']]);
                        $pInfo->products_seo_url = tep_db_prepare_input($products_seo_url[$languages[$i]['id']]);
                    } ?>

                    <table border="0" width="100%" cellspacing="0" cellpadding="2">
                        <tr>
                            <td>
                                <table border="0" width="100%" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td class="pageHeading"><?php echo tep_image(DIR_WS_CATALOG_LANGUAGES . $languages[$i]['directory'] . '/images/' . $languages[$i]['image'], $languages[$i]['name']) . '&nbsp;' . $pInfo->products_name; ?></td>
                                        <td class="pageHeading" align="right"><?php
                                            // BOF QPBPP for SPPC
                                            $pf->loadProduct((int)$_GET['pID'], $pInfo->products_price, $pInfo->products_tax_class_id, (int)$pInfo->products_qty_blocks[0], $price_breaks_array, (int)$pInfo->products_min_order_qty[0]);
                                            echo $pf->getPriceString();
                                            // EOF QPBPP for SPPC ?>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
                        </tr>
                        <tr>
                            <td class="main"><?php echo tep_image(DIR_WS_CATALOG_IMAGES . $products_image_name, $pInfo->products_name, SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT, 'align="right" hspace="5" vspace="5"') . $pInfo->products_description; ?></td>
                        </tr>

						  <!-- BOF Bundled Products-->
						  <tr>
							<td class="main">
							  <?php
							  $pid = (isset($_GET['pID']) ? $_GET['pID'] : $pInfo->products_id);
							  if ($pInfo->products_bundle == "yes") {
								display_bundle($pid, $pInfo->products_price, $languages[$i]['id'], $languages[$i]['directory']);
							  }
							  ?>
							</td>
						  </tr>
						  <tr>
							<td class="main">
							  <?php
							  if ($pInfo->sold_in_bundle_only == "yes") {
								echo '<b>' . TEXT_SOLD_IN_BUNDLE . '</b><blockquote>';
								$bquery = tep_db_query('select bundle_id from ' . TABLE_PRODUCTS_BUNDLES . ' where subproduct_id = ' . (int)$pid);
								while ($bid = tep_db_fetch_array($bquery)) {
								  $binfo_query = tep_db_query('select p.products_model, pd.products_name from ' . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where p.products_id = '" . (int)$bid['bundle_id'] . "' and pd.products_id = p.products_id and pd.language_id = " . (int)$languages[$i]['id']);
								  $binfo = tep_db_fetch_array($binfo_query);
								  echo '<a href="' . tep_catalog_href_link('product_info.php', 'products_id=' . (int)$bid['bundle_id']) . '" target="_blank">[' . $binfo['products_model'] . '] ' . $binfo['products_name'] . '</a><br />';
								}
								echo '</blockquote>';
							  }
							  ?>
							</td>
						  </tr>
						  <!-- EOF Bundled Products-->

                        <?php if ($pInfo->products_url) { ?>
                            <tr>
                                <td><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
                            </tr>
                            <tr>
                                <td class="main"><?php echo sprintf(TEXT_PRODUCT_MORE_INFORMATION, $pInfo->products_url); ?></td>
                            </tr>
                        <?php } ?>
                            <tr>
                                <td><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
                            </tr>

                        <?php if ($pInfo->products_date_available > date('Y-m-d')) { ?>
                            <tr>
                                <td align="center" class="smallText"><?php echo sprintf(TEXT_PRODUCT_DATE_AVAILABLE, tep_date_long($pInfo->products_date_available)); ?></td>
                            </tr>
                        <?php } else { ?>
                            <tr>
                                <td align="center" class="smallText"><?php echo sprintf(TEXT_PRODUCT_DATE_ADDED, tep_date_long($pInfo->products_date_added)); ?></td>
                            </tr>
                        <?php } ?>
                        <tr>
                            <td><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
                        </tr>
                        <?php
                    }

                    if( isset($_GET['read']) && ($_GET['read'] == 'only') )
                    {
                        if( isset($_GET['origin']) )
                        {
                            $pos_params = strpos($_GET['origin'], '?', 0);

                            if ($pos_params != false)
                            {
                                $back_url = substr($_GET['origin'], 0, $pos_params);
                                $back_url_params = substr($_GET['origin'], $pos_params + 1);
                            }
                            else
                            {
                                $back_url = $_GET['origin'];
                                $back_url_params = '';
                            }
                        }
                        else
                        {
                            $back_url = FILENAME_CATEGORIES;
                            $back_url_params = 'cPath=' . $cPath . '&pID=' . $pInfo->products_id;
                        }
                    ?>
                        <tr>
                            <td align="right"><?php echo '<a href="' . tep_href_link($back_url, $back_url_params, 'NONSSL') . '">' . tep_image_button('button_back.png', IMAGE_BACK) . '</a>'; ?></td>
                        </tr>
            <?php } else { ?>
                        <tr>
                            <td align="right" class="smallText">
                                <?php
                                    /* Re-Post all POST'ed variables */
                                    foreach( $_POST as $key => $value )
                                    {
                                        // BOF Separate Pricing per Customer adapted for QPBPP for SPPC
                                        if( is_array($value) )
                                        {
											foreach( $value as $k => $v )
                                            {
                                                if( is_array($v) )
                                                {
                                                    foreach ($v as $subkey => $subvalue)
                                                        echo tep_draw_hidden_field($key . '[' . $k . '][' . $subkey . ']', htmlspecialchars(stripslashes($subvalue)));
                                                }
                                                else
                                                    echo tep_draw_hidden_field($key . '[' . $k . ']', htmlspecialchars(stripslashes($v)));
                                            }
                                        }
                                        else
                                        {
                                            // EOF Separate Pricing per Customer
                                            echo tep_draw_hidden_field($key, htmlspecialchars(stripslashes($value)));
                                        }
                                    }

                                    $languages = tep_get_languages();

                                    for ($i=0, $n=sizeof($languages); $i<$n; $i++)
                                    {
                                        echo tep_draw_hidden_field('products_name[' . $languages[$i]['id'] . ']', htmlspecialchars(stripslashes($products_name[$languages[$i]['id']])));
                                        echo tep_draw_hidden_field('products_description[' . $languages[$i]['id'] . ']', htmlspecialchars(stripslashes($products_description[$languages[$i]['id']])));
                                        echo tep_draw_hidden_field('products_url[' . $languages[$i]['id'] . ']', htmlspecialchars(stripslashes($products_url[$languages[$i]['id']])));
                                        echo tep_draw_hidden_field('products_seo_url[' . $languages[$i]['id'] . ']', htmlspecialchars(stripslashes($products_seo_url[$languages[$i]['id']])));
                                    }

                                    echo tep_draw_hidden_field('products_fileupload', stripslashes($nombre_products_fileupload));
									echo tep_draw_hidden_field('products_pdfupload', stripslashes($nombre_products_pdfupload));
                                    echo tep_draw_hidden_field('products_image', stripslashes($products_image_name));

                                    // PSM BEGIN 4g
                                    if (is_array($_POST['payment_methods']) )
                                    {
                                        foreach($_POST['payment_methods'] as $val)
                                        {
                                            echo tep_draw_hidden_field('payment_methods[]', $val);
                                        }
                                    }

                                    if(is_array($_POST['shipping_methods']))
                                    {
                                        foreach($_POST['shipping_methods'] as $val)
                                        {
                                            echo tep_draw_hidden_field('shipping_methods[]', $val);
                                        }
                                    }
                                    // PSM END 4g

                                    echo tep_image_submit('button_back.png', IMAGE_BACK, 'name="edit"') . '&nbsp;&nbsp;';

                                    if( isset($_GET['pID']) )
                                    {
                                        echo tep_image_submit('button_update.png', IMAGE_UPDATE);
                                    }
                                    else
                                    {
                                        echo tep_image_submit('button_insert.png', IMAGE_INSERT);
                                    }

                                    echo '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . (isset($_GET['pID']) ? '&pID=' . $_GET['pID'] : '')) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>';
                                ?>
                            </td>
                        </tr>
                    </table>
                </form>
            <?php } } else { ?>
                <table border="0" width="100%" cellspacing="0" cellpadding="2">
                    <tr>
                        <td>
                            <table border="0" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
                                    <td align="right">
                                        <table class="table-sech" border="0" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td class="smallText" align="right">
                                                    <?php
														echo tep_draw_form('search', FILENAME_CATEGORIES, '', 'get');
														echo HEADING_TITLE_SEARCH . ' ' . tep_draw_input_field('search');
														echo tep_hide_session_id() . '</form>';
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="smallText" align="right">
                                                    <?php
                                                        if( is_array($admin_cat_access_array_cats) && (in_array("ALL",$admin_cat_access_array_cats)== false) && (pos($admin_cat_access_array_cats)!= "") )
                                                        {
                                                           echo '';
                                                        }
                                                        else if( in_array("ALL",$admin_cat_access_array_cats)== true )
                                                        {
                                                            echo tep_draw_form('goto', FILENAME_CATEGORIES, '', 'get');
                                                            echo HEADING_TITLE_GOTO . ' ' . tep_draw_pull_down_menu('cPath', tep_get_category_tree(), $current_category_id, 'onChange="this.form.submit();"');
                                                            echo tep_hide_session_id() . '</form>';
                                                        }
                                                    ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table border="0" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
                                        <tr class="dataTableHeadingRow">
                                            <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_CATEGORIES_PRODUCTS; ?></td>
                                            <td class="dataTableHeadingContent" align="center">Referencia</td>
											<td class="dataTableHeadingContent" align="center">Ref. Proveedor</td>
                                            <td class="dataTableHeadingContent" align="center">Cód. EAN</td>
                                            <td class="dataTableHeadingContent" align="center"><?php echo TABLE_HEADING_STATUS; ?></td>
											<td class="dataTableHeadingContent" align="center">Amazon</td>
											<?php
												// Comprobamos si existen productos para mostrar los cabeceras
												$aDatos = tep_db_query( "select c.categories_id from categories c where c.parent_id = '" . (int)$current_category_id . "'" );

												if( tep_db_num_rows( $aDatos ) == 0 || isset($_GET['search']) )
												{
													echo '<td class="dataTableHeadingContent" align="center">Ordenar</td>';
													echo '<td class="dataTableHeadingContent" align="center" title="Los productos marcados no actualizarán el título, descripción ni campos SEO" style="cursor: pointer;">Importadores <span  style="
													background-color: #fff;
													display: inline-flex;
													justify-content: center;
													align-items: center;
													width: 15px;
													height: 15px;
													border-radius: 15px;
													color: #000;
												">?</span></td>';
												}
											?>
                                            <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ACTION; ?></td>
                                        </tr>
                                        <?php
                                            $categories_count = 0;
                                            $rows = 0;

                          					if (isset($_GET['search']))
                                            {
                                                $search = tep_db_prepare_input($_GET['search']);
                                                $products_query = tep_db_query("select p.products_fileupload, p.products_pdfupload, p.products_id, pd.products_name, p.product_ean, p.reference_prov, pd.products_seo_url, p.products_quantity, p.products_quantity_deseada, p.exclude_feedmachine, p.check_stock, p.products_image, p.products_price, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_import_exclude, p.products_import_origin, p.products_featured_until, p.products_status, p.amazon_status, p.products_featured, p.products_model, p2c.categories_id from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd, " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c where p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "' and p.products_id = p2c.products_id and (pd.products_name like '%" . tep_db_input($search) . "%' or p.products_model like '%" . tep_db_input($search) . "%') order by FIELD(p.products_status, 1, 2, 0), p.products_model");
                                                // EOF: More Pics 6
                                            }
                                            else
                                            {
                                                if ($admin_cat_access == "ALL")
                                                    $categories_query = tep_db_query("select c.categories_id, cd.categories_name, cad.categories_name as categories_name_amazon, c.categories_image, c.parent_id, c.sort_order, c.date_added, c.last_modified, c.categories_status from " . TABLE_CATEGORIES . " c INNER JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd on( c.categories_id = cd.categories_id ) LEFT JOIN categories_amazon_description cad on (c.categories_amazon_id = cad.categories_id and cad.language_id = '" . (int)$languages_id . "') where c.parent_id = '" . (int)$current_category_id . "' and cd.language_id = '" . (int)$languages_id . "' order by c.sort_order, cd.categories_name");
                                                //else if ($admin_cat_access == "")
                                                    //$categories_query = tep_db_query("");
                                                else
                                                    $categories_query = tep_db_query("select c.categories_id, cd.categories_name, cad.categories_name as categories_name_amazon, c.categories_image, c.parent_id, c.sort_order, c.date_added, c.last_modified, c.categories_status from " . TABLE_CATEGORIES . " c INNER JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd on( c.categories_id = cd.categories_id ) LEFT JOIN categories_amazon_description cad on (c.categories_amazon_id = cad.categories_id and cad.language_id = '" . (int)$languages_id . "') where c.parent_id = '" . (int)$current_category_id . "' and (c.parent_id or c.categories_id in (" . $admin_cat_access . ")) and cd.language_id = '" . (int)$languages_id . "' order by c.sort_order, cd.categories_name");


											while( $categories = tep_db_fetch_array($categories_query) )
                                            {
                                                $categories_count++;
                                                $rows++;

                                                // Get parent_id for subcategories if search
                                                if( isset($_GET['search']) )
                                                    $cPath= $categories['parent_id'];

                                                if( (!isset($_GET['cID']) && !isset($_GET['pID']) || (isset($_GET['cID']) && ($_GET['cID'] == $categories['categories_id']))) && !isset($cInfo) && (substr($action, 0, 3) != 'new') )
                                                {
                                                    $category_childs = array('childs_count' => tep_childs_in_category_count($categories['categories_id']));
                                                    $category_products = array('products_count' => tep_products_in_category_count($categories['categories_id']));

                                                    $cInfo_array = array_merge($categories, $category_childs, $category_products);
                                                    $cInfo = new objectInfo($cInfo_array);
                                                }

                                                if( $admin_groups_id == 1 || in_array($categories['categories_id'],$admin_cat_access_array_cats) )
                                                {
                                                    if( $admin_groups_id == 1 || in_array($_GET['cPath'],$admin_cat_access_array_cats) || in_array($categories['categories_id'],$admin_cat_access_array_cats) )
                                                    {
                                                        if (isset($cInfo) && is_object($cInfo) && ($categories['categories_id'] == $cInfo->categories_id) )
                                                        {
                                                            echo '<tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_CATEGORIES, tep_get_path($categories['categories_id'])) . '\'">' . "\n";
                                                        }
                                                        else
                                                        {
                                                            echo '<tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $categories['categories_id']) . '\'">' . "\n";
                                                        }
                                                        ?>
															<td class="dataTableContent"><?php echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, tep_get_path($categories['categories_id'])) . '">' . tep_image(DIR_WS_ICONS . 'folder.png', ICON_FOLDER) . '</a>'; ?> <?php echo $categories['categories_name']; ?></td>
															<td class="dataTableContent" colspan="3"></td>
															<td class="dataTableContent" align="center" >
																<?php   // CATEGORY STATUS
																	if ((int)$categories['categories_status'] == 1)
			                                                        {
			                                                            echo tep_image(DIR_WS_IMAGES . 'icon_status_green.png', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;'.
																		'<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setcats&flag=0&cID=' . $categories['categories_id'] . '&cPath=' . $cPath) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' .
																		'<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setcats&flag=2&cID=' . $categories['categories_id'] . '&cPath=' . $cPath) . '">' .tep_image(DIR_WS_IMAGES . 'icon_status_orange_light.png', 'Descatalogado', 10, 10).'</a>';
			                                                        }
			                                                        elseif ((int)$categories['categories_status'] == 0)
			                                                        {
			                                                            echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setcats&flag=1&cID=' . $categories['categories_id'] . '&cPath=' . $cPath . ($truePath ?? '')) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' .
																		tep_image(DIR_WS_IMAGES . 'icon_status_red.png', IMAGE_ICON_STATUS_RED, 10, 10).'&nbsp;&nbsp;'.
																		'<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setcats&flag=2&cID=' . $categories['categories_id'] . '&cPath=' . $cPath) . '">' .tep_image(DIR_WS_IMAGES . 'icon_status_orange_light.png', 'Descatalogado', 10, 10).'</a>';
			                                                        } else {
																		echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setcats&flag=1&cID=' . $categories['categories_id'] . '&cPath=' . $cPath . ($truePath ?? '')) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' .
																		'<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setcats&flag=0&cID=' . $categories['categories_id'] . '&cPath=' . $cPath) . '">' .tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_RED, 10, 10).'</a>&nbsp;&nbsp;'.
																		tep_image(DIR_WS_IMAGES . 'icon_status_orange.png', 'Descatalogado', 10, 10);
																	}
																?>
															</td>
															<td class="dataTableContent" align="center" >
																<?php echo $categories['categories_name_amazon']; ?>
															</td>
															<td class="dataTableContent" align="right">
																<?php echo '<a href="' . tep_href_link('../categories.php', 'cPath=' . $categories['categories_id']) . '" target="_blank">' . tep_image(DIR_WS_ICONS . 'preview.png', ICON_PREVIEW) . '</a>'; ?>
																<?php echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $categories['categories_id']) . '&action=edit_category">' . tep_image(DIR_WS_ICONS . 'edit.png', ICON_EDIT) . '</a>'; ?>
																<?php echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $categories['categories_id']) . '&action=move_category">' . tep_image(DIR_WS_ICONS . 'move.png', ICON_MOVE) . '</a>'; ?>
																<?php echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $categories['categories_id']) . '&action=delete_category">' . tep_image(DIR_WS_ICONS . 'delete.png', ICON_DELETE) . '</a>'; ?>
                                                            </td>
                                                        </tr>
                                            <?php  } // BOF: KategorienAdmin / OLISWISS
                                                }
                                            }
											}

                                            $products_count = 0;

                                            if (isset($_GET['search']))
                                            {
                                               $search = strtolower(tep_db_prepare_input($_GET['search']));
                                               $search_query = " and p.products_id = p2c.products_id and (LOWER(pd.products_name) like '%" . tep_db_input($search) . "%' or LOWER(p.products_model) like '%" . tep_db_input($search) . "%' or LOWER(p.reference_prov) like '%" . tep_db_input($search) . "%' or LOWER(p.product_ean) like '%" . tep_db_input($search) . "%')";
											}else{
												$search_query = " and p.products_id = p2c.products_id and p2c.categories_id = '" . (int)$current_category_id . "' ";
											}

                                                $products_query = tep_db_query("select p.products_fileupload, p.products_sort_order, p.products_import_exclude, p.products_import_origin, p.products_featured, p.products_pdfupload, p.products_id, pd.products_name, p.products_model, p.product_ean, p.reference_prov, pd.products_seo_url, p.products_quantity, p.products_cost, p.products_image, p.products_subimages, p.products_price, p.products_tax_class_id, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_import_exclude, p.products_import_origin, p.products_featured_until, p.products_status, p.amazon_status, p.products_featured, p.products_model, p2c.categories_id from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd, " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c where p.products_id = pd.products_id " . $search_query . " and pd.language_id = '" . (int)$languages_id . "' order by FIELD(p.products_status, 1, 2, 0), p.products_model");

                                            while( $products = tep_db_fetch_array($products_query) )
                                            {
                                                $products_count++;
                                                $rows++;

                                                // Get categories_id for product if search
                                                if( isset($_GET['search']) )
                                                    $cPath = $products['categories_id'];

                                                if( (!isset($_GET['pID']) && !isset($_GET['cID']) || (isset($_GET['pID']) && ($_GET['pID'] == $products['products_id']))) && !isset($pInfo) && !isset($cInfo) && (substr($action, 0, 3) != 'new'))
                                                {
                                                    // find out the rating average from customer reviews
                                                    $reviews_query = tep_db_query("select (avg(reviews_rating) / 5 * 100) as average_rating from " . TABLE_REVIEWS . " where products_id = '" . (int)$products['products_id'] . "'");
                                                    $reviews = tep_db_fetch_array($reviews_query);
                                                    $pInfo_array = array_merge($products, $reviews);
                                                    $pInfo = new objectInfo($pInfo_array);
                                                }

                                                if ($admin_groups_id == 1 || in_array($categories['categories_id'],$admin_cat_access_array_cats) || $cPath != 0)
                                                {
                                                    if (isset($pInfo) && is_object($pInfo) && ($products['products_id'] == $pInfo->products_id) )
                                                        echo '<tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)">' . "\n";
                                                    elseif( isset( $_GET['search'] ) )
													{
														echo '<tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" data-href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $products['products_id']) . '&search=' . $_GET['search'] . '">' . "\n";
													}
                                                    else
													{
                                                        echo '<tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" data-href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $products['products_id']) . '">' . "\n";
													}
                                                ?>

                                                <td class="dataTableContent">
													<?php echo tep_image(DIR_WS_CATALOG_IMAGES . 'productos/' . $products['products_image'], $products['products_name'], 50, 50, '', false); ?>
													<?php echo $products['products_name']; ?></td>
												<td class="dataTableContent" align="center"><?php echo $products['products_model']; ?></td>

												<td class="dataTableContent" align="center"><?php echo $products['reference_prov']; ?></td>
												<td class="dataTableContent" align="center"><?php echo $products['product_ean']; ?></td>
                                                <td class="dataTableContent noclick product-status-cell" align="center" data-pid="<?php echo (int)$products['products_id']; ?>" data-cpath="<?php echo htmlspecialchars($cPath, ENT_QUOTES); ?>" data-status="<?php echo (int)$products['products_status']; ?>">
                                                    <?php
														$_pid_pf = (int)$products['products_id'];
														$_cpath_pf = htmlspecialchars($cPath, ENT_QUOTES);
														$_link0 = tep_href_link(FILENAME_CATEGORIES, 'action=setflag&flag=0&pID=' . $_pid_pf . '&cPath=' . $cPath);
														$_link1 = tep_href_link(FILENAME_CATEGORIES, 'action=setflag&flag=1&pID=' . $_pid_pf . '&cPath=' . $cPath);
														$_link2 = tep_href_link(FILENAME_CATEGORIES, 'action=setflag&flag=2&pID=' . $_pid_pf . '&cPath=' . $cPath);
														if ((int)$products['products_status'] == 1)
															echo tep_image(DIR_WS_IMAGES . 'icon_status_green.png', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . $_link0 . '" class="js-setflag" data-flag="0" data-pid="' . $_pid_pf . '" data-cpath="' . $_cpath_pf . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;<a href="' . $_link2 . '" class="js-setflag" data-flag="2" data-pid="' . $_pid_pf . '" data-cpath="' . $_cpath_pf . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_orange_light.png', 'Descatalogado', 10, 10) . '</a>';
														elseif ((int)$products['products_status'] == 0)
															echo '<a href="' . $_link1 . '" class="js-setflag" data-flag="1" data-pid="' . $_pid_pf . '" data-cpath="' . $_cpath_pf . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.png', IMAGE_ICON_STATUS_RED, 10, 10) . '&nbsp;&nbsp;<a href="' . $_link2 . '" class="js-setflag" data-flag="2" data-pid="' . $_pid_pf . '" data-cpath="' . $_cpath_pf . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_orange_light.png', 'Descatalogado', 10, 10) . '</a>';
														else
															echo '<a href="' . $_link1 . '" class="js-setflag" data-flag="1" data-pid="' . $_pid_pf . '" data-cpath="' . $_cpath_pf . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;<a href="' . $_link0 . '" class="js-setflag" data-flag="0" data-pid="' . $_pid_pf . '" data-cpath="' . $_cpath_pf . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_RED, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_orange.png', 'Descatalogado', 10, 10);
                                                    ?>
                                                </td>
												<td class="dataTableContent" align="center">
                                                    <?php
                                                        if ($products['amazon_status'] == '1')
                                                        {
                                                            echo tep_image(DIR_WS_IMAGES . 'icon_status_green.png', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setflagAmazon&flag=0&pID=' . $products['products_id'] . '&cPath=' . $cPath) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
                                                        }
                                                        else
                                                        {
                                                            echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setflagAmazon&flag=1&pID=' . $products['products_id'] . '&cPath=' . $cPath) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.png', IMAGE_ICON_STATUS_RED, 10, 10);
                                                        }
                                                    ?>
                                                </td>
												 <td class="dataTableContent noclick" align="center">
													<?php
														echo tep_draw_form('setsortorder', FILENAME_CATEGORIES, 'action=setsortorder'); ?><input type="hidden" name="cPath" value="<?php echo $cPath; ?>">
														<?php    echo tep_draw_input_field('sortorder[]', $products['products_sort_order'],  'SIZE="3" class="products_sort_order"') . tep_draw_hidden_field('products_id[]', $products['products_id']) . '</form>';
													?>
												</td>

												<td class="dataTableContent" align="center" >
													<?php
														if ($products['products_import_origin'] != '') {
															if( $products['products_import_exclude'] == '1' ) {
																echo tep_image(DIR_WS_IMAGES . 'icon_status_green.png', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setflag_import_exclude&flag=0&pID=' . $products['products_id'] . '&cPath=' . $cPath) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
															} else {
																echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setflag_import_exclude&flag=1&pID=' . $products['products_id'] . '&cPath=' . $cPath) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.gif', IMAGE_ICON_STATUS_RED, 10, 10);
															}
														} else {
															echo '--';
														}
													?>
												</td>

                                                <td class="dataTableContent" align="right">
                                                    <?php echo '<a href="' . tep_href_link('../product_info.php', 'products_id=' . $products['products_id']) . '" target="_blank">' . tep_image(DIR_WS_ICONS . 'preview.png', ICON_PREVIEW) . '</a>'; ?>
													<?php echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $products['products_id']) . '&action=new_product">' . tep_image(DIR_WS_ICONS . 'edit.png', ICON_EDIT) . '</a>'; ?>
													<?php echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $products['products_id']) . '&action=copy_to">' . tep_image(DIR_WS_ICONS . 'duplicate.png', ICON_DUPLICATE) . '</a>'; ?>
													<?php echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $products['products_id']) . '&action=move_product">' . tep_image(DIR_WS_ICONS . 'move.png', ICON_MOVE) . '</a>'; ?>
													<?php echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $products['products_id']) . '&action=delete_product">' . tep_image(DIR_WS_ICONS . 'delete.png', ICON_DELETE) . '</a>'; ?>
													<?php echo '<a href="' . tep_href_link('stats_products_orders.php', 'reference_selected=' . $products['products_model']) . '&month=ALL&year=ALL&no_status=&status=">' . tep_image(DIR_WS_ICONS . 'icon_stats_sold.png', 'Ver quien ha comprado este producto') . '</a>'; ?>
                                                </td>
                                            </tr>
                                        <?php
                                              }
                                            }

                                            $cPath_back = '';
                                            if( isset( $cPath_array ) && sizeof($cPath_array) > 0 )
                                            {
                                                for ($i=0, $n=sizeof($cPath_array)-1; $i<$n; $i++)
                                                {
                                                    if( empty($cPath_back) )
                                                    {
                                                        $cPath_back .= $cPath_array[$i];
                                                    }
                                                    else
                                                    {
                                                        $cPath_back .= '_' . $cPath_array[$i];
                                                    }
                                                }
                                            }

                                            $cPath_back = (tep_not_null($cPath_back)) ? 'cPath=' . $cPath_back . '&' : '';
                                        ?>
                                        <tr>
                                            <td colspan="910">
                                                <table class="table-page" border="0" width="100%" cellspacing="0" cellpadding="2">
                                                    <tr>
                                                        <?php // BOF: KategorienAdmin / OLISWISS
                                                            if($admin_groups_id == 1)
                                                            {
                                                                ?>
                                                                <td class="smallText"><?php echo TEXT_CATEGORIES . '&nbsp;' . $categories_count . '<br>' . TEXT_PRODUCTS . '&nbsp;' . $products_count; ?></td>
                                                                <td style="vertical-align: middle;" align="right" class="smallText"><?php if (isset($cPath_array) && sizeof($cPath_array) > 0) echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, $cPath_back . 'cID=' . $current_category_id) . '">' . tep_image_button('button_back.png', IMAGE_BACK) . '</a>&nbsp;'; if (!isset($_GET['search'])) echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&action=new_category') . '">' . tep_image_button('button_new_category.png', IMAGE_NEW_CATEGORY) . '</a>&nbsp;<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&action=new_product') . '">' . tep_image_button('button_new_product.png', IMAGE_NEW_PRODUCT) . '</a>'; ?>&nbsp;</td>
                                                                <?php
                                                            }
                                                            else
                                                            {
                                                        ?>
                                                                <td></td>
                                                                <td style="vertical-align: middle;" align="right" class="smallText">
                                                                    <?php
                                                                        if (!empty($cPath_array) && sizeof($cPath_array) > 0) echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, $cPath_back . 'cID=' . $current_category_id) . '">' . tep_image_button('button_back.png', IMAGE_BACK) . '</a>&nbsp;';
                                                                        if (!isset($_GET['search']) && strstr($admin_right_access,"CNEW")) echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&action=new_category') . '">' . tep_image_button('button_new_category.png', IMAGE_NEW_CATEGORY) . '</a>&nbsp;';
                                                                        if (!isset($_GET['search']) && strstr($admin_right_access,"PNEW") && $cInfo->parent_id !='0') echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&action=new_product') . '">' . tep_image_button('button_new_product.png', IMAGE_NEW_PRODUCT) . '</a>'; ?>&nbsp;
                                                                </td>
                                                        <?php } // EOF: KategorienAdmin / OLISWISS ?>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                                <?php
                                    $heading = array();
                                    $contents = array();
                                    switch ($action)
                                    {
										case 'seo_category':
											$heading[] = array('text' => '<b>Opciones seo</b>');

											$sHtml = tep_draw_form( 'categories', FILENAME_CATEGORIES, 'action=update_seo_category&cPath=' . $cPath, 'post', '' );
												$sHtml .= tep_draw_hidden_field( 'categories_id', $cInfo->categories_id );

												// Obtenemos los datos
												$aDatos = tep_db_query( 'select cd.language_id, cd.categories_seo_title, cd.categories_seo_keywords, cd.categories_seo_description, cd.categories_seo_text_landing_page
																		 from categories_description cd
																		 where categories_id = ' . $cInfo->categories_id );
												$aAux = array();

												// Lo guardamos por idioma
												while( $aDato = tep_db_fetch_array( $aDatos ) )
												{
													// Creamos el indice de idioma
													$aAux[$aDato['language_id']] = array();

													// Guardamos el dato
													$aAux[$aDato['language_id']] = $aDato;
												}

												// Obtenemos los idiomas disponibles
												$aIdiomas = tep_get_languages();

												// Campos del formulario
												$aCampos = array(
													array( 'title' => 'Título', 'text' => 'Título que se muestra en la cabecera del navegador.', 'row' => 'categories_seo_title' ),
													array( 'title' => 'Palabras claves', 'text' => 'Palabras, frases clave o términos de búsqueda que los buscadores usaran para encontrar información.', 'row' => 'categories_seo_keywords' ),
													array( 'title' => 'Descripción', 'text' => 'Descripcion que nos ayudará a indicar cúal es el contenido de nuestra página. Recomendamos que tenga entre 70 y 156 caracteres (incluyendo espacios).', 'row' => 'categories_seo_description' ),
													array( 'title' => 'Texto para landing page', 'text' => 'Landing page o página de entrada es aquella página a la cual un usuario llega después de haber hecho click en algún enlace. Añade la descripción de que vera en esta categoría.', 'row' => 'categories_seo_text_landing_page' ),
												);

												// Recorremos los campos
												foreach( $aCampos as $aCampo )
												{
													$sHtml .= '<b>' . $aCampo['title'] . '</b><br/>';
													$sHtml .= '<i style="display: block; text-align: justify; margin-bottom: 5px;">' . $aCampo['text'] . '</i>';

													$sHtml .= '<table border="0" width="100%" cellspacing="0" cellpadding="0">';

													// Recorremos los idiomas para mostrar los datos
													foreach( $aIdiomas as $aIdioma )
													{
														$sHtml .= '<tr>';
														$sHtml .= '<td style="vertical-align: top; width: 30px;">' . tep_image( DIR_WS_CATALOG_LANGUAGES . $aIdioma['directory'] . '/images/' . $aIdioma['image'], $aIdioma['name'], '', '', 'style="margin-top: 6px;"' ) . ' </td>';

														$sHtml .= '<td>';

														switch( $aCampo['row'] )
														{
															case 'categories_seo_description':
															case 'categories_seo_keywords':
																$sHtml .= tep_draw_textarea_field( $aCampo['row'] . '[' . $aIdioma['id'] . ']', '', 5, 5, $aAux[$aIdioma['id']][$aCampo['row']], 'style="margin: 0px 0px 10px;"' );
															break;

															case 'categories_seo_text_landing_page':
																$sHtml .= tep_draw_textarea_field_tinymce( 'categories_seo_text_landing_page[' . $aIdioma['id'] . ']', 'soft', '70', '20', $aAux[$aIdioma['id']][$aCampo['row']] );
															break;


															default:
																$sHtml .= tep_draw_input_field( $aCampo['row'] . '[' . $aIdioma['id'] . ']', $aAux[$aIdioma['id']][$aCampo['row']], 'style="width: 100%; margin: 0px 0px 10px;"' ) . '<br/>';
															break;
														}

														$sHtml .= '</td>';

														$sHtml .= '</tr>';
													}

													$sHtml .= '</table>';

													$sHtml .= '<br/>';
												}

												// Botones
												$sHtml .= '<br/><center>' . tep_image_submit('button_save.png', IMAGE_SAVE) . ' <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $cInfo->categories_id) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a></center>';
											$sHtml .= '</form>';


											$contents[] = array( 'text' => $sHtml );
										break;

										case 'new_category':
                                        case 'edit_category':
											// Variables
											$category_inputs_string = '';
											$languages = tep_get_languages();
											$sHtml = '';
											$aImagesActuales = array();

											// Obtenemos las imagenes actuales
											if( $cInfo->categories_image != '' )
												$aImagesActuales = json_decode( $cInfo->categories_image, true );

											// Titular y form
											$heading[] = array('text' => '<b>' . TEXT_INFO_HEADING_EDIT_CATEGORY . '</b>');
											$contents = array('form' => tep_draw_form('categories', FILENAME_CATEGORIES, 'action=' . ($action == 'new_category' ? 'insert_category' : 'update_category') . '&cPath=' . $cPath, 'post', 'enctype="multipart/form-data"') );
											$contents[] = array('text' => TEXT_EDIT_INTRO);

											// Recorremos los idiomas para definir los nombres de la categoría por idioma
											for ($i = 0, $n = sizeof($languages); $i < $n; $i++)
												$category_inputs_string .= '<br>' . tep_image(DIR_WS_CATALOG_LANGUAGES . $languages[$i]['directory'] . '/images/' . $languages[$i]['image'], $languages[$i]['name'], 22, 17, 'style="position: relative; top: 5px;"') . '&nbsp;' . tep_draw_input_field('categories_name[' . $languages[$i]['id'] . ']', tep_get_category_name($cInfo->categories_id, $languages[$i]['id']));

											// Si estamos editando añadimos el input hidden de la id_categoria
											if( $action == 'edit_category' )
												$category_inputs_string .= tep_draw_hidden_field('categories_id', $cInfo->categories_id);

											// Nombre de la categoria
											$contents[] = array('text' => '<br/><b>' . TEXT_EDIT_CATEGORIES_NAME . '</b>' . $category_inputs_string);

											// Recorremos los tipo de imagenes
											foreach( $aTipoImagesCategoria as $key => $aTipo )
											{
												$sHtml .= '&nbsp;&nbsp;' . $aTipo[0] . ':<br/>';
												$sHtml .= '&nbsp;&nbsp;<i style="color: #666;">' . $aTipo[1] . '</i><br/>';

												// Recorremos los idiomas para hacer las imagenes por tipo de idioma
												foreach( $languages as $aLanguge )
												{
													$sHtml .= '<div style="width: 330px; margin: 2px 0px;">&nbsp;&nbsp;' . tep_image( DIR_WS_CATALOG_LANGUAGES . $aLanguge['directory'] . '/images/' . $aLanguge['image'], $aLanguge['name'], 22, 17, 'style="position: relative; top: 6px;"' );
													$sHtml .= ' ' . tep_draw_file_field( 'categories_image_' . $key . '[' . $aLanguge['id'] . ']', '' );

													// Si existe alguna imagen mostramos para eliminar o previsualizarla
													if( array_key_exists( $key, $aImagesActuales) && array_key_exists( $aLanguge['id'], $aImagesActuales[$key] ) && $aImagesActuales[$key][$aLanguge['id']] != '' )
													{
														$sHtml .= '<a class="dlte-image-catg" style="position: relative; top: 7px;" title="Elminar" href="javascript:void(0);" data-type="categories" data-id="' . $cInfo->categories_id . '" data-image="' . $aImagesActuales[$key][$aLanguge['id']] . '"><img width="19" height="18" border="0" title="Eliminar" alt="Eliminar" src="images/borrar_noticia.gif" /></a>';
														$sHtml .= '<a style="position: relative; top: 7px;" title="Previsualizar" target="_blank" href="' . DIR_WS_CATALOG_IMAGES .  'categorias/' . $aImagesActuales[$key][$aLanguge['id']] . '"><img width="19" height="18" border="0" title="Previsualizar" alt="Previsualizar" src="images/icons/preview.png" /></a>';
													}

													$sHtml .= '</div>';
												}

												$sHtml .= '<br/>';
											}

											// Imagenes
											$contents[] = array( 'text' => '<br/><b>Imagenes:</b> <br/><i style="color: #666;">Si no subes una imagen en algun idioma por defecto se usara la imagen en español</i></br>' . $sHtml );

											// Si estamos editando mostramos el estado
											if( $action == 'edit_category' )
												$contents[] = array('text' => '<b>' . TABLE_HEADING_STATUS . ': </b> <br/>' . ($cInfo->categories_status ? IMAGE_ICON_STATUS_GREEN : IMAGE_ICON_STATUS_RED));

											// Ordenar la categoria
											$contents[] = array('text' => '<br>' . TEXT_EDIT_SORT_ORDER . '<br>' . tep_draw_input_field('sort_order', $cInfo->sort_order, 'size="2"'));

											// Si estamos editando mostramos si queremos cambiar los estados de los productos
											if( $action == 'edit_category' )
											{
												$select_array[] = array('id' => 9, 'text' => 'No Cambiar');
												$select_array[] = array('id' => 0, 'text' => 'Desactivar Todos');
												$select_array[] = array('id' => 1, 'text' => 'Activar Todos');
												$contents[] = array('text' => '<br>' . TEXT_CHANGE_PRODUCTS_STATUS . $cInfo->categories_name . '<br>' );
												$contents[] = array('align' => 'center', 'text' => tep_draw_pull_down_menu('cxstat', $select_array, 9));
											}

											/**
											 * XCC-313-91043
											 * @author Daniel Lucia <daniel.lucia@denox.es>
											 */

											$contents[] = array(
												'text' =>
												'<br>Comisión afiliados:<br>' . tep_draw_input_field('comission', (string)Affiliates::adminGetComissionFromCategory(intval($cInfo->categories_id), 'comission')) .
												'<br>Comisión afiliados(EU):<br>' . tep_draw_input_field('comission_eu', (string)Affiliates::adminGetComissionFromCategory(intval($cInfo->categories_id), 'comission_eu'))
											);

											// Boton de guardar y volver
											$contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_save.png', IMAGE_SAVE) . ' <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath) . ($action == 'edit_category' ? '&cID=' . $cInfo->categories_id : '') . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>');
                                        break;

                                        case 'delete_category':
                                            $heading[] = array('text' => '<b>' . TEXT_INFO_HEADING_DELETE_CATEGORY . '</b>');
                                            $contents = array('form' => tep_draw_form('categories', FILENAME_CATEGORIES, 'action=delete_category_confirm&cPath=' . $cPath) . tep_draw_hidden_field('categories_id', $cInfo->categories_id));
                                            $contents[] = array('text' => TEXT_DELETE_CATEGORY_INTRO);
                                            $contents[] = array('text' => '<br><b>' . $cInfo->categories_name . '</b>');

                                            if ($cInfo->childs_count > 0)
                                                    $contents[] = array('text' => '<br>' . sprintf(TEXT_DELETE_WARNING_CHILDS, $cInfo->childs_count));

                                            if ($cInfo->products_count > 0)
                                                    $contents[] = array('text' => '<br>' . sprintf(TEXT_DELETE_WARNING_PRODUCTS, $cInfo->products_count));

                                            $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_delete.png', IMAGE_DELETE) . ' <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $cInfo->categories_id) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>');
                                        break;

                                        case 'move_category':
                                            $heading[] = array('text' => '<b>' . TEXT_INFO_HEADING_MOVE_CATEGORY . '</b>');

                                            $contents = array('form' => tep_draw_form('categories', FILENAME_CATEGORIES, 'action=move_category_confirm&cPath=' . $cPath) . tep_draw_hidden_field('categories_id', $cInfo->categories_id));
                                            $contents[] = array('text' => sprintf(TEXT_MOVE_CATEGORIES_INTRO, $cInfo->categories_name));
                                            $contents[] = array('text' => '<br>' . sprintf(TEXT_MOVE, $cInfo->categories_name) . '<br>' . tep_draw_pull_down_menu('move_to_category_id', tep_get_category_tree(), $current_category_id));
                                            $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_move.png', IMAGE_MOVE) . ' <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $cInfo->categories_id) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>');
                                        break;

										case 'select_category_amazon':
											$sAmazon = tep_db_query('SELECT categories_amazon_id FROM categories WHERE categories_id = ' . $cInfo->categories_id);
											$sAmazon = tep_db_fetch_array( $sAmazon );
											$heading[] = array('text' => '<b>Seleccionar Categoría de Amazon</b>');

											$contents = array('form' => tep_draw_form('categories_amazon', FILENAME_CATEGORIES, 'action=select_category_amazon_confirm&cPath=' . $cPath) . tep_draw_hidden_field('categories_id', $cInfo->categories_id));
											$contents[] = array('text' => sprintf('Seleccione una categoría de Amazon', $cInfo->categories_name));
											$contents[] = array('text' => '<br>' . sprintf('Seleccionar', $cInfo->categories_name) . '<br>' . tep_draw_pull_down_menu('move_to_category_id', tep_get_category_amazon_tree(), $sAmazon['categories_amazon_id']));
											$contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_confirm.png', IMAGE_MOVE) . ' <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $cInfo->categories_id) . '">' . tep_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>');
										break;

                                        case 'delete_product':
                                            $heading[] = array('text' => '<b>' . TEXT_INFO_HEADING_DELETE_PRODUCT . '</b>');

                                            $contents = array('form' => tep_draw_form('products', FILENAME_CATEGORIES, 'action=delete_product_confirm&cPath=' . $cPath) . tep_draw_hidden_field('products_id', $pInfo->products_id));
                                            $contents[] = array('text' => TEXT_DELETE_PRODUCT_INTRO);
                                            $contents[] = array('text' => '<br><b>' . $pInfo->products_name . '</b>');

                                            $product_categories_string = '';
                                            $product_categories = tep_generate_category_path($pInfo->products_id, 'product');

                                            for( $i = 0, $n = sizeof($product_categories); $i < $n; $i++ )
                                            {
                                                $category_path = '';

                                                for ($j = 0, $k = sizeof($product_categories[$i]); $j < $k; $j++)
                                                {
                                                    $category_path .= $product_categories[$i][$j]['text'] . '&nbsp;&gt;&nbsp;';
                                                }

                                                $category_path = substr($category_path, 0, -16);
                                                $product_categories_string .= tep_draw_checkbox_field('product_categories[]', $product_categories[$i][sizeof($product_categories[$i])-1]['id'], true) . '&nbsp;' . $category_path . '<br>';
                                            }

                                            $product_categories_string = substr($product_categories_string, 0, -4);
                                            $contents[] = array('text' => '<br>' . $product_categories_string);
                                            $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_delete.png', IMAGE_DELETE) . ' <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $pInfo->products_id) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>');
                                        break;

                                        case 'move_product':
                                            $heading[] = array('text' => '<b>' . TEXT_INFO_HEADING_MOVE_PRODUCT . '</b>');

                                            $contents = array('form' => tep_draw_form('products', FILENAME_CATEGORIES, 'action=move_product_confirm&cPath=' . $cPath) . tep_draw_hidden_field('products_id', $pInfo->products_id));
                                            $contents[] = array('text' => sprintf(TEXT_MOVE_PRODUCTS_INTRO, $pInfo->products_name));
                                            $contents[] = array('text' => '<br>' . TEXT_INFO_CURRENT_CATEGORIES . '<br><b>' . tep_output_generated_category_path($pInfo->products_id, 'product') . '</b>');

                                            // BOF: KategorienAdmin / OLISWISS
                                            if( is_array($admin_cat_access_array_cats) && (in_array("ALL",$admin_cat_access_array_cats)== false) && (pos($admin_cat_access_array_cats)!= "") )
                                            {
                                                $contents[] = array('text' => '<br>' . sprintf(TEXT_MOVE, $pInfo->products_name) . '<br>' . tep_draw_pull_down_menu('move_to_category_id', tep_get_category_tree('','','0','',$admin_cat_access_array_cats), $current_category_id));
                                            }
                                            else if( in_array("ALL",$admin_cat_access_array_cats)== true)
                                            {
                                                ////nur Top-ADMIN
                                                $contents[] = array('text' => '<br>' . sprintf(TEXT_MOVE, $pInfo->products_name) . '<br>' . tep_draw_pull_down_menu('move_to_category_id', tep_get_category_tree(), $current_category_id));
                                            }
                                            // EOF: KategorienAdmin / OLISWISS

                                            $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_move.png', IMAGE_MOVE) . ' <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $pInfo->products_id) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>');
                                        break;

                                        case 'copy_to':
                                            $heading[] = array('text' => '<b>' . TEXT_INFO_HEADING_COPY_TO . '</b>');

                                            $contents = array('form' => tep_draw_form('copy_to', FILENAME_CATEGORIES, 'action=copy_to_confirm&cPath=' . $cPath) . tep_draw_hidden_field('products_id', $pInfo->products_id));
                                            $contents[] = array('text' => TEXT_INFO_COPY_TO_INTRO);
                                            $contents[] = array('text' => '<br>' . TEXT_INFO_CURRENT_CATEGORIES . '<br><b>' . tep_output_generated_category_path($pInfo->products_id, 'product') . '</b>');

                                            // BOF: KategorienAdmin / OLISWISS
                                            if( is_array($admin_cat_access_array_cats) && (in_array("ALL",$admin_cat_access_array_cats)== false) && (pos($admin_cat_access_array_cats)!= "") )
                                            {
                                                $contents[] = array('text' => '<br>' . TEXT_CATEGORIES . '<br>' . tep_draw_pull_down_menu('categories_id', tep_get_category_tree('','','0','',$admin_cat_access_array_cats), $current_category_id));
                                            }
                                            else if( in_array("ALL",$admin_cat_access_array_cats)== true )
                                            {
                                                //nur Top-ADMIN
                                                $contents[] = array('text' => '<br>' . TEXT_CATEGORIES . '<br>' . tep_draw_pull_down_menu('categories_id', tep_get_category_tree(), $current_category_id));
                                            }
                                            // EOF: KategorienAdmin / OLISWISS

                                            $contents[] = array('text' => '<br>' . TEXT_HOW_TO_COPY . '<br>' . tep_draw_radio_field('copy_as', 'link', true) . ' ' . TEXT_COPY_AS_LINK . '<br>' . tep_draw_radio_field('copy_as', 'duplicate') . ' ' . TEXT_COPY_AS_DUPLICATE);
                                            $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_copy.png', IMAGE_COPY) . ' <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $pInfo->products_id) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>');
                                        break;

                                        default:
                                            if( $rows > 0)
                                            {
                                                if( isset($cInfo) && is_object($cInfo) )
                                                {
                                                    // category info box contents
                                                    $category_path_string = '';
                                                    $category_path = tep_generate_category_path($cInfo->categories_id);

                                                    for ($i=(sizeof($category_path[0])-1); $i>0; $i--)
                                                        $category_path_string .= $category_path[0][$i]['id'] . '_';

                                                    $category_path_string = substr($category_path_string, 0, -1);

                                                    $heading[] = array('text' => '<b>' . $cInfo->categories_name . '</b>');

                                                    if( $admin_groups_id == 1 )
                                                    {
                                                        $contents[] = array('align' => 'center', 'text' => '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&cID=' . $cInfo->categories_id . '&action=edit_category') . '">' . tep_image_button('button_edit.png', IMAGE_EDIT) . '</a> <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&cID=' . $cInfo->categories_id . '&action=delete_category') . '">' . tep_image_button('button_delete.png', IMAGE_DELETE) . '</a> <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&cID=' . $cInfo->categories_id . '&action=move_category') . '">' . tep_image_button('button_move.png', IMAGE_MOVE) . '</a>' . ($cInfo->products_count > 0 && $cInfo->childs_count <= 0 ? ' <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $cInfo->categories_id . '&action=select_category_amazon') . '">' . tep_image_button('button_amazon.png', IMAGE_MOVE) . '</a>' : ''));
														$contents[] = array( 'align' => 'center', 'text' => '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&cID=' . $cInfo->categories_id . '&action=seo_category') . '">' . tep_image_button('button_seo.png', 'SEO') . '</a>' );
                                                    }
                                                    else
                                                    {
                                                        if (strstr($admin_right_access,"CEDIT"))
                                                            $c_right_string .= '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&cID=' . $cInfo->categories_id . '&action=edit_category') . '">' . tep_image_button('button_edit.png', IMAGE_EDIT) . '</a>';

                                                        if (strstr($admin_right_access,"CDELETE"))
                                                            $c_right_string .= '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&cID=' . $cInfo->categories_id . '&action=delete_category') . '">' . tep_image_button('button_delete.png', IMAGE_DELETE) . '</a>';

                                                        if (strstr($admin_right_access,"CMOVE"))
                                                            $c_right_string .= '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&cID=' . $cInfo->categories_id . '&action=move_category') . '">' . tep_image_button('button_move.png', IMAGE_MOVE) . '</a>';

                                                        $contents[] = array('align' => 'center', 'text' => $c_right_string);
                                                    }

                                                    $contents[] = array('text' => '<br>' . TEXT_DATE_ADDED . ' ' . tep_date_short($cInfo->date_added));
                                                    $contents[] = array('text' => '<br>' . TABLE_HEADING_STATUS . ': <b>' . ($cInfo->categories_status ? IMAGE_ICON_STATUS_GREEN : IMAGE_ICON_STATUS_RED) . '</b>');

                                                    if( tep_not_null($cInfo->last_modified) )
                                                        $contents[] = array('text' => TEXT_LAST_MODIFIED . ' ' . tep_date_short($cInfo->last_modified));

													// Mostramos las imagenes
													$aImagesActuales = array();
													if( $cInfo->categories_image != '' )
														$aImagesActuales = json_decode( $cInfo->categories_image, true );

													$sHtml = '<table width="135px">';

													// Recorremos los tipo de imagenes
													foreach( $aTipoImagesCategoria as $key => $aTipo )
													{
														$sHtml .= '<tr><td colspan="2">' . $aTipo[0] . ':<br/><br></td></tr>';

														// Recorremos los idiomas para hacer las imagenes por tipo de idioma
														foreach( $languages as $aLanguge )
														{
															$sHtml .= '<tr>';
																$sHtml .= '<td style="width: 25px; vertical-align: middle;">' . tep_image( DIR_WS_CATALOG_LANGUAGES . $aLanguge['directory'] . '/images/' . $aLanguge['image'], $aLanguge['name'], 22, 17, 'style="display: block;"' ) . '</td>';

																if( array_key_exists( $key, $aImagesActuales) && array_key_exists( $aLanguge['id'], $aImagesActuales[$key] ) && $aImagesActuales[$key][$aLanguge['id']] != '' )
																{
																	$sHtml .= '<td style="text-align: center; vertical-align: middle; border: 1px solid rgb(204, 204, 204); background: none repeat scroll 0% 0% white;">';
																	$sHtml .= tep_image( DIR_WS_CATALOG_IMAGES . 'categorias/' . $aImagesActuales[$key][$aLanguge['id']], $cInfo->categories_name, 100, 100, 'display: block; margin-left: 4px;', false );
																}
																else
																	$sHtml .= '<td style="text-align: center;"><span>- Sin imagen -</span>';
																$sHtml .= '</td>';

															$sHtml .= '</tr>';
														}

														$sHtml .= '<tr><td colspan="2"><br/><br></td></tr>';
													}

													$sHtml .= '</table>';

													$contents[] = array( 'text' => '<div>' . $sHtml . '</div>' );

                                                    $contents[] = array('text' => '<br>' . TEXT_SUBCATEGORIES . ' ' . $cInfo->childs_count . '<br>' . TEXT_PRODUCTS . ' ' . $cInfo->products_count);

													/**
													 * XCC-313-91043
													 * @author Daniel Lucia <daniel.lucia@denox.es>
													 */

													$contents[] = array(
														'text' =>
														'<br>Comisión afiliados: <strong>' . (string)Affiliates::adminGetComissionFromCategory($cInfo->categories_id, 'comission') . '%</strong>' .
														'<br>Comisión afiliados(EU): <strong>' .  (string)Affiliates::adminGetComissionFromCategory($cInfo->categories_id, 'comission_eu') . '%</strong>'
													);

                                                }
                                                elseif (isset($pInfo) && is_object($pInfo))
                                                {
													$category_path_string = '';
                                                    $category_path = tep_generate_category_path($pInfo->categories_id);

                                                    for ($i=(sizeof($category_path[0])-1); $i>0; $i--)
                                                        $category_path_string .= $category_path[0][$i]['id'] . '_';

                                                    $category_path_string .= $pInfo->categories_id;

													if( $category_path_string == '' )
														$category_path_string = $cPath;
													else
													{
														$aAux = explode( '_', $category_path_string );
														$category_path_string = $aAux[count($aAux) - 1];
													}

                                                    // product info box contents
                                                    $heading[] = array('text' => '<b>' . tep_get_products_name($pInfo->products_id, $languages_id) . '</b>');

                                                    if ($admin_groups_id == 1)
                                                    {
                                                        $contents[] = array('align' => 'center', 'text' => '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&pID=' . $pInfo->products_id . '&action=new_product') . '">' . tep_image_button('button_edit.png', IMAGE_EDIT) . '</a> <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&pID=' . $pInfo->products_id . '&action=delete_product') . '">' . tep_image_button('button_delete.png', IMAGE_DELETE) . '</a> <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&pID=' . $pInfo->products_id . '&action=move_product') . '">' . tep_image_button('button_move.png', IMAGE_MOVE) . '</a>
                                                            <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&pID=' . $pInfo->products_id . '&action=copy_to') . '">' . tep_image_button('button_copy_to.png', IMAGE_COPY_TO) . '</a> <a href="' . tep_href_link(FILENAME_RELATED_PRODUCTS, 'products_id_view=' . $pInfo->products_id) . '" target="_new">' . tep_image_button('button_related_products.png', 'Relacionar') . '</a> <a href="' . tep_href_link(FILENAME_STOCK, 'product_id=' . $pInfo->products_id) . '" target="_new">' . tep_image_button('button_stock.png', 'Stock') . '</a>');
                                                    }
                                                    else
                                                    {
                                                        if (strstr($admin_right_access,"PEDIT"))
                                                        {
                                                            $p_right_string .= ' <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&pID=' . $pInfo->products_id . '&action=new_product') . '">' . tep_image_button('button_edit.png', IMAGE_EDIT) . '</a>';
                                                        }

                                                        if (strstr($admin_right_access,"PDELETE"))
                                                        {
                                                            $p_right_string .= ' <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&pID=' . $pInfo->products_id . '&action=delete_product') . '">' . tep_image_button('button_delete.png', IMAGE_DELETE) . '</a>';
                                                        }

                                                        if (strstr($admin_right_access,"PMOVE"))
                                                        {
                                                            $p_right_string .= ' <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&pID=' . $pInfo->products_id . '&action=move_product') . '">' . tep_image_button('button_move.png', IMAGE_MOVE) . '</a>';
                                                        }

                                                        if (strstr($admin_right_access,"PCOPY"))
                                                        {
                                                            $p_right_string .= ' <a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&pID=' . $pInfo->products_id . '&action=copy_to') . '">' . tep_image_button('button_copy_to.png', IMAGE_COPY_TO) . '</a>';
                                                        }

                                                        $contents[] = array('align' => 'center', 'text' => $p_right_string);
                                                    }
                                                    // EOF: KategorienAdmin / OLISWISS



                                                    $contents[] = array('text' => '<br>' . TEXT_DATE_ADDED . ' ' . tep_date_short($pInfo->products_date_added));

                                                    if (tep_not_null($pInfo->products_last_modified))
                                                        $contents[] = array('text' => TEXT_LAST_MODIFIED . ' ' . tep_date_short($pInfo->products_last_modified));

                                                    if( date('Y-m-d') < $pInfo->products_date_available )
                                                        $contents[] = array('text' => TEXT_DATE_AVAILABLE . ' ' . tep_date_short($pInfo->products_date_available));

													$contents[] = array('text' => '<br><b>' . 'Código EAN:' . '</b> ' . $pInfo->product_ean);
													$contents[] = array('text' => '<b>' . TEXT_PRODUCTS_QUANTITY_INFO . '</b> ' . $pInfo->products_quantity);
                                                    $contents[] = array('text' => '<b>' . TEXT_PRODUCTS_PRICE_INFO . '</b> ' . $currencies->display_price($pInfo->products_price, tep_get_tax_rate( $pInfo->products_tax_class_id) ).'<br><b>' . TEXT_PRODUCTS_COST_INFO . '</b> ' . $currencies->format($pInfo->products_cost));

													// Mostramos imagenes
													$sHtml = '';

													// Imagen principal
													if( $pInfo->products_image != '' )
														$sHtml .= '<a class="image-show-prod" href="' . DIR_WS_CATALOG_IMAGES . 'productos/' . $pInfo->products_image . '" target="_blank">' . tep_image( DIR_WS_CATALOG_IMAGES . 'productos/' . $pInfo->products_image, $pInfo->products_name, 150, 150, '', false ) . $pInfo->products_image . '</a>';

													// Subimages
													if( is_json( $pInfo->products_subimages ) )
													{
														$aImagenes = json_decode( $pInfo->products_subimages );
														foreach( $aImagenes as $sImagen )
															$sHtml .= '<a class="image-show-prod" href="' . DIR_WS_CATALOG_IMAGES . 'productos/' . $sImagen . '" target="_blank">' . tep_image( DIR_WS_CATALOG_IMAGES . 'productos/' . $sImagen, $pInfo->products_name, 150, 150, '', false ) . $sImagen . '</a>';
													}

													if( $sHtml != '' )
														$contents[] = array( 'text' => '<div style="overflow: hidden; width: 414px; margin: 0px auto;">' .  $sHtml . '</div>' );

                                                    //BOF QPBPP for SPPC
                                                    $retail_price = $pInfo->products_price;
                                                    unset($pInfo->products_price);
                                                    $pInfo->products_price[0] = $retail_price;
                                                    $retail_products_qty_blocks = $pInfo->products_qty_blocks ?? 1;
                                                    unset($pInfo->products_qty_blocks);
                                                    $pInfo->products_qty_blocks[0] = $retail_products_qty_blocks;
                                                    $retail_products_min_order_qty = $pInfo->products_min_order_qty ?? 1;
                                                    unset($pInfo->products_min_order_qty);
                                                    $pInfo->products_min_order_qty[0] = $retail_products_min_order_qty;
                                                    // query the customer groups together with discount categories first, then products_groups
                                                    // for group prices, quantity blocks and min order quantities and lastly for price breaks.
                                                    // the first query needs minimum MySQL version to be 4.1 (release date february 2003...)
                                                    $customer_groups_dc_query = tep_db_query("select cg.customers_group_id, cg.customers_group_name, dc.discount_categories_name from " . TABLE_CUSTOMERS_GROUPS . " cg left join (select customers_group_id, discount_categories_id from " . TABLE_PRODUCTS_TO_DISCOUNT_CATEGORIES . " ptdc where ptdc.products_id = '" . $pInfo->products_id. "') as p2dc on p2dc.customers_group_id = cg.customers_group_id left join " . TABLE_DISCOUNT_CATEGORIES . " dc on p2dc.discount_categories_id = dc.discount_categories_id order by customers_group_id");

                                                    while ($customer_groups_dc_results =  tep_db_fetch_array($customer_groups_dc_query))
                                                    {
                                                        $customer_groups[$customer_groups_dc_results['customers_group_id']] = $customer_groups_dc_results['customers_group_name'];
                                                        $discount_categories[$customer_groups_dc_results['customers_group_id']] = $customer_groups_dc_results['discount_categories_name'];
                                                    }

                                                    if (count($customer_groups) > 1)
                                                    {
                                                        $cg_group_price_query = tep_db_query("select customers_group_id, customers_group_price, products_qty_blocks, products_min_order_qty from " . TABLE_PRODUCTS_GROUPS . " where products_id = '" . $pInfo->products_id. "'");

                                                        while ($cg_group_price_results = tep_db_fetch_array($cg_group_price_query))
                                                        {
                                                            $pInfo->products_price[$cg_group_price_results['customers_group_id']] = $cg_group_price_results['customers_group_price'];
                                                            $pInfo->products_qty_blocks[$cg_group_price_results['customers_group_id']] = $cg_group_price_results['products_qty_blocks'];
                                                            $pInfo->products_min_order_qty[$cg_group_price_results['customers_group_id']] = $cg_group_price_results['products_min_order_qty'];
                                                        }
                                                    } // end if (count($customer_groups) > 1)


                                                }
                                            }
                                            else
                                            {
                                                // create category/product info
                                                $heading[] = array('text' => '<b>' . EMPTY_CATEGORY . '</b>');
                                                $contents[] = array('text' => TEXT_NO_CHILD_CATEGORIES_OR_PRODUCTS);
                                            }
                                        break;
                                    }

                                    if( (tep_not_null($heading)) && (tep_not_null($contents)) )
                                    {
                                            echo '<td width="25%" style="vertical-align: top;" valign="top">' . "\n";
                                            $box = new box;
                                            echo $box->infoBox($heading, $contents);
                                            echo '</td>' . "\n";
                                    }
                                ?>
                            </tr>
                    </table>
                </td>
            </tr>
    </table>
<?php
  }
?>
    </td>
  </tr>
</table>


<div id="dxload" style="display:none; background: url(theme/web/images/load-all.gif) no-repeat scroll 21px 20px rgb(255, 255, 255); border-radius: 10px 10px 10px 10px; z-index: 2; height: 100px; left: 50%; position: fixed; top: 50%; width: 100px; margin: -50px;"></div>
<div id="dxbg" style="display:none; position: fixed; top: 0px; width: 100%; background: none repeat scroll 0px 0px rgb(102, 102, 102); height: 100%; left: 0px; opacity: 0.3; z-index: 1;"></div>

<?php require(THEME . 'html/footer.php'); ?>

<script type="text/javascript">
	$(".products_sort_order").focusout(function()
	{
		var dmForm = $(this).parent();

		$.ajax( {
			type: "POST",
			url: dmForm.attr("action"),
			data: dmForm.serialize()
		} );
	});

	$("tr[data-href] td").not(".noclick").click(function(e)
	{
		e.isImmediatePropagationStopped();

		document.location.href=$(this).closest("tr").data("href");
	});
</script>

<link rel="stylesheet" type="text/css" href="<?php echo THEME; ?>css/select2.min.css"/>
<script src="theme/web/js/select2.full.min.js" type="text/javascript"></script>
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
	$(".select2").select2({
		ajax: {
			url: '<?php echo tep_href_link('categories.php', 'action=subproduct_selector&pID=' . intval($_GET['pID'])); ?>',
			dataType: 'json'
		},
		language: "es",
		placeholder: 'Busque el producto a añadir',
		minimumInputLength: 3
	});
}, false);

</script>

<script type="text/javascript">
(function() {
	function buildIconsHtml(pid, status, cpath) {
		var base = 'categories.php?action=setflag&pID=' + encodeURIComponent(pid) + '&cPath=' + encodeURIComponent(cpath);
		var img = function(file, alt) {
			return '<img src="images/' + file + '" border="0" alt="' + alt + '" title="' + alt + '" width="10" height="10">';
		};
		var aOpen = function(flag) {
			return '<a href="' + base + '&flag=' + flag + '" class="js-setflag" data-flag="' + flag + '" data-pid="' + pid + '" data-cpath="' + String(cpath).replace(/"/g, '&quot;') + '">';
		};
		if (status === 1 || status === '1') {
			return img('icon_status_green.png', 'Activo') + '&nbsp;&nbsp;'
				+ aOpen(0) + img('icon_status_red_light.png', 'Desactivar') + '</a>&nbsp;&nbsp;'
				+ aOpen(2) + img('icon_status_orange_light.png', 'Descatalogado') + '</a>';
		} else if (status === 0 || status === '0') {
			return aOpen(1) + img('icon_status_green_light.png', 'Activar') + '</a>&nbsp;&nbsp;'
				+ img('icon_status_red.png', 'Inactivo') + '&nbsp;&nbsp;'
				+ aOpen(2) + img('icon_status_orange_light.png', 'Descatalogado') + '</a>';
		} else {
			return aOpen(1) + img('icon_status_green_light.png', 'Activar') + '</a>&nbsp;&nbsp;'
				+ aOpen(0) + img('icon_status_red_light.png', 'Desactivar') + '</a>&nbsp;&nbsp;'
				+ img('icon_status_orange.png', 'Descatalogado');
		}
	}

	document.addEventListener('click', function(e) {
		var a = e.target.closest ? e.target.closest('a.js-setflag') : null;
		if (!a) return;
		e.preventDefault();
		e.stopPropagation();

		var td = a.closest('td.product-status-cell');
		if (!td) return;

		var pid = a.getAttribute('data-pid');
		var flag = a.getAttribute('data-flag');
		var cpath = a.getAttribute('data-cpath') || '';

		var url = 'categories.php?action=setflag&pID=' + encodeURIComponent(pid)
			+ '&flag=' + encodeURIComponent(flag)
			+ '&cPath=' + encodeURIComponent(cpath)
			+ '&ajax=1';

		td.style.opacity = '0.5';
		td.style.pointerEvents = 'none';

		fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function(r) { return r.json(); })
			.then(function(data) {
				td.style.opacity = '';
				td.style.pointerEvents = '';
				if (!data || !data.ok) return;
				var newStatus = parseInt(data.status, 10);
				td.setAttribute('data-status', newStatus);
				td.innerHTML = buildIconsHtml(pid, newStatus, cpath);
			})
			.catch(function() {
				td.style.opacity = '';
				td.style.pointerEvents = '';
			});
	}, true);
})();
</script>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
