<?php
// Tools
use util\tools as tools;
use util\date as date;

// Si nos mandan a instalar cambiamos el modulo por login para que forbidden no salte y podamos instalarlo
if( array_key_exists( 'action', $_GET ) && $_GET['action'] == 'install' )
{
	// FIX bypass sin auth: PHP_SELF='index.php' (FILENAME_DEFAULT) hace que tep_admin_check_login
	// salte SOLO el ACL de pagina. NO tocar SCRIPT_FILENAME: asi el login SIGUE exigiendose.
	$_SERVER['PHP_SELF'] = 'index.php';
}

// Incluimos el application_top
require( 'includes/application_top.php' );
include( 'includes/classes/currencies.php' );
include( 'includes/modules/reviews/includes/functions/functions.php' );

// Variables
$sUrlPage =  'reviews.php';
$sTitle = HEADING_TITLE;
$sSubtitle = '';
$aButtons = [];
$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);

$sGetPage = (isset( $_GET['page'] ) ? tep_db_prepare_input( $_GET['page'] ) : 1);
$sGetOrderby = (isset( $_GET['orderby'] ) ? tep_db_prepare_input( $_GET['orderby'] ) : '');
$sGetSort = (isset( $_GET['sort'] ) ? tep_db_prepare_input( $_GET['sort'] ) : '');

$aRecomendado = [];
$aRecomendado[] = [ 'id' => 0, 'text' => TEXT_NO ];
$aRecomendado[] = [ 'id' => 1, 'text' => TEXT_YES ];

$aRatings = [];
$aRatings[] = [ 'id' => 1, 'text' => '1 ' . REVIEWS_TEXT_STAR ];
$aRatings[] = [ 'id' => 2, 'text' => '2 ' . REVIEWS_TEXT_STARS ];
$aRatings[] = [ 'id' => 3, 'text' => '3 ' . REVIEWS_TEXT_STARS ];
$aRatings[] = [ 'id' => 4, 'text' => '4 ' . REVIEWS_TEXT_STARS ];
$aRatings[] = [ 'id' => 5, 'text' => '5 ' . REVIEWS_TEXT_STARS ];

$sHtml = '';

# Messagestack estilo
$messageStack->style = 'solenopsis';

// Acciones
switch( $sPostAction )
{
	case 'readme':
		// Variables
		$sSubtitle = 'Readme de instalación';
		$aButtons = [
			[ 'title' => 'Ver módulo', 'icon' => 'fa-arrow-right', 'href' => $sUrlPage ]
		];

		$sHtml = tools::parsedown( DIR_WS_MODULES . '/reviews/readme.txt' );
		break;

	case 'autocomplete':
		// Variables
		$currencies = new currencies();
		$sHtml = '';

		// Buscamos por producto
		$sSql = getSqlSearchProducts_bd( strtolower( array_key_exists( 'term', $_POST ) ? tep_db_prepare_input( $_POST['term'] ) : '' ) );

		// Lanzamos la consulta
		$aDatos = tep_db_query( $sSql );

		// Si encontramos productos
		if( tep_db_num_rows( $aDatos ) > 0 )
		{
			while( $aDato = tep_db_fetch_array( $aDatos ) )
			{
				$sDataLang = '';

				// Obtenemos todos los nombres del producto por idiomas
				$aLangProducts = tep_db_query( 'SELECT products_name, language_id FROM products_description WHERE language_id != ' . (int) $languages_id . ' AND products_id = ' . $aDato['products_id'] );

				// Si tenemos nombre por idiomas
				if( tep_db_num_rows( $aLangProducts ) > 0 )
				{
					while( $aLangProduct = tep_db_fetch_array( $aLangProducts ) )
						$sDataLang .= ' data-lang' . $aLangProduct['language_id'] . '="' . str_replace( '"', '&#34;', $aLangProduct['products_name'] ) . '"';
				}

				$sHtml .= '<li data-id="' . $aDato['products_id'] . '"' . $sDataLang . ' data-price="' . str_replace( '.', '', $currencies->display_price( $aDato['products_price'], tep_get_tax_rate( $aDato['products_tax_class_id'] ) ) ) . '" >' . $aDato['products_name'] . '</li>';
			}
		}

		// Pintamos
		die( $sHtml !== '' ? '<ul>' . $sHtml . '</ul>' : '' );

	case 'setflag':
		// Variables
		$nId = tep_db_prepare_input( $_GET['id'] );
		$nFlag = tep_db_prepare_input( $_GET['flag'] );

		tep_db_query("update " . TABLE_REVIEWS . " set approved = '" . (int)$nFlag . "' where reviews_id = '" . $nId . "'");

		tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

	case 'approve':
	case 'decline':
			// Variables
			$aGetId = tep_db_prepare_input( $_GET['id'] );
			$aPostId = tep_db_prepare_input( $_POST['id'] );
			$status = $sPostAction == 'approve' ? 1 : 0;

			// Si nos envian por get creamos el array
			if ($aGetId != '') {
                $aPostId = [ $aGetId ];
            }

			// Si tenemos id eliminamos
			if( !empty($aPostId) ) {
				tep_db_query(sprintf('UPDATE reviews SET approved = %d WHERE reviews_id IN(%s)', $status, implode(', ', $aPostId)));
				$messageStack->addSession( 'success', 'Los comentarios se han actualizado correctamente', 'success' );
			} else {
				$messageStack->addSession( 'success', 'No habia comentarios seleccionados', 'success' );
			}

			// Redireccionamos
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;
	case 'delete':
		// Variables
		$aGetId = tep_db_prepare_input( $_GET['id'] );
		$aPostId = tep_db_prepare_input( $_POST['id'] );
		$sIds = '';

		// Si nos envian por get creamos el array
		if ($aGetId != '') {
            $aPostId = [ $aGetId ];
        }

		// Recorremos los id
		foreach( $aPostId as $sId )
			$sIds .= $sId . ',';

		// Si tenemos id eliminamos
		if( $sIds !== '' )
		{
			tep_db_query( 'DELETE FROM reviews WHERE reviews_id IN(' . substr( $sIds, 0, -1 ) . ')' );
		}

		// Redireccionamos
		$messageStack->addSession( 'success', REVIEWS_TEXT_DELETE_OK, 'success' );
		tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

	case 'update':
	case 'add_form':
		// Variables
		$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
		$aMessageError = [];
		$sSubtitle = ($sGetId != '' ? REVIEWS_TEXT_EDIT : REVIEWS_TEXT_ADD) . REVIEWS_TEXT_REVIEW;
		$aButtons = [
			[ 'title' => IMAGE_BACK, 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ],
			[ 'title' => IMAGE_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
		];
		$aRecord = [];

		// Si estamos editando
		if( $sGetId != false )
		{
			// Obtenemos el comentario
			$aRecord = pharaonix_queryOne( 'SELECT *
												FROM reviews r
												INNER JOIN reviews_description rd ON( r.reviews_id = rd.reviews_id )
												INNER JOIN products_description pd ON( r.products_id = pd.products_id AND pd.language_id = "' . $languages_id . '" )
												WHERE r.reviews_id = "' . (int)$sGetId . '"' );

			// Si no existe
			if( $aRecord->num_rows == 0 )
			{
				$messageStack->addSession( 'success', REVIEWS_EDIT_ERROR, 'error' );
				tep_redirect( tep_href_link(  $sUrlPage ) );
			}

			// comentario
			$aRecord = $aRecord->records;
		}

		// Insertar o actualizar
		if( $sPostAction == 'update' )
		{
			// Pasamos todos los post por tep_db_prepare_input
			array_walk( $_POST, function( $value, $key){ global $_POST; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); } );

			// Comprobamos que se haya seleccionado un producto
			if ($sGetId == false && (! isset( $_POST['products_id'] ) || (isset( $_POST['products_id'] ) && $_POST['products_id'] <= 0))) {
                $aMessageError['products_id'] = $messageStack->show( [ 'text' => 'Debes seleccionar un producto.', 'class' => 'error' ] );
            }

			// Comprobamos que nos hayan indicado autor
			if ((isset( $_POST['customers_name'] ) && $_POST['customers_name'] == '') || ! isset( $_POST['customers_name'] )) {
                $aMessageError['customers_name'] = $messageStack->show( [ 'text' => REVIEWS_TABLE_AUTHOR_ERROR, 'class' => 'error' ] );
            }

			// Comprobamos que nos hayan indicado fecha
			if ((isset( $_POST['date_added'] ) && $_POST['date_added'] == '') || ! isset( $_POST['date_added'] )) {
                $aMessageError['date_added'] = $messageStack->show( [ 'text' => REVIEWS_TABLE_DATE_ERROR, 'class' => 'error' ] );
            }

			// Comprobamos que nos hayan indicado comentario
			if ((isset( $_POST['reviews_text'] ) && $_POST['reviews_text'] == '') || ! isset( $_POST['reviews_text'] )) {
                $aMessageError['reviews_text'] = $messageStack->show( [ 'text' => REVIEWS_TABLE_REVIEW_ERROR, 'class' => 'error' ] );
            }

			// Si no existe errores actualizamos/insertamos
			if( count( $aMessageError ) == 0 )
			{
				if ($_POST['date_added'] != '') {
                    $_POST['date_added'] = date::changeDate( $_POST['date_added'], 'espanol', 'y/m/d' );
                }

				$aSql = [
					'customers_name' => $_POST['customers_name'],
					'reviews_rating' => $_POST['reviews_rating'],
					'reviews_recomendar' => $_POST['reviews_recomendar'],
					'last_modified' => 'now()',
					'date_added' => ($_POST['date_added'] == '' ? '' : $_POST['date_added']),
					'approved' => $_POST['approved']
				];

				$aSqlInfo = [
					'reviews_text' => $_POST['reviews_text'],
					'reviews_pros' => $_POST['reviews_pros'],
					'reviews_contras' => $_POST['reviews_contras']
				];

				if( $sGetId != false )
				{
					tep_db_perform( 'reviews', $aSql, 'update', 'reviews_id = "' . (int)$sGetId . '"' );
					tep_db_perform( 'reviews_description', $aSqlInfo, 'update', 'reviews_id = "' . (int)$sGetId . '"' );
				}
				else
				{
					tep_db_perform( 'reviews', array_merge( $aSql, [ 'products_id' => $_POST['products_id'] ] ) );
					$sGetId = tep_db_insert_id();

					tep_db_perform( 'reviews_description', array_merge( $aSqlInfo, [ 'reviews_id' => (int)$sGetId, 'languages_id' => $languages_id ] ) );
				}

				// Mensaje
				$messageStack->addSession( 'success', ($sGetId != false ? REVIEWS_TEXT_EDIT_OK : REVIEWS_TEXT_NEW_OK), 'success' );

				// Redireccionamos
				tep_redirect( tep_href_link(  $sUrlPage ) );
			}
		}

		$aJs = [ 'includes/modules/reviews/js/index.js' ];
		$aStyle = [ 'includes/modules/reviews/css/style.css' ];

		// Formulario
		$sHtml .= '<div class="oeBox column a12 row ax">';
		$sHtml .= '<div class="oeWrpr">';
		$sHtml .= '<div class="oeTitu"><i class="fa fa-shield"></i> ' . REVIEWS_TEXT_CONFIGURATION . ' </div>';
		$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update' ) . '" class="oeCntd row ax xform xform-horizontal">';
		$sHtml .= $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : '';
		$sHtml .= '<input type="submit" style="display: none;" />';

		$sHtml .= '<label for="producto" class="column a02 tright">' . REVIEWS_TABLE_PRODUCT . ':</label>';
		$sHtml .= '<div class="column a10" style="position: relative;">';
		if ($sGetId != false) {
            $sHtml .= '<span style="padding-top: 7px; display: block;">' . $aRecord['products_name'] . '</span>';
        } else
		{
			$sHtml .= '<input name="term" autocomplete="off" type="text" ' . (isset( $_POST['products_id'] ) ? 'value="' . getNameProductsById( (int)$_POST['products_id'] ) . '" ' : '') . 'placeholder="' . TEXT_SEARCH . '..." id="autocomplete" class="column" style="border-right: 0px;" />';
			$sHtml .= '<div id="autocomplete-target"></div>';
			$sHtml .= tep_draw_hidden_field('products_id', '', 'id="products_id"');
		}
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="autor" class="column a02 tright">' . REVIEWS_TABLE_AUTHOR . ':</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= array_key_exists( 'customers_name', $aMessageError ) ? $aMessageError['customers_name'] : '';
		$sHtml .= '<input type="text" name="customers_name" id="autor" value="' . (array_key_exists( 'customers_name', $aRecord ) ? $aRecord['customers_name'] : $_POST['customers_name']) . '"/>';
		$sHtml .= '<div class="DFhelp">' . REVIEWS_TABLE_AUTHOR_HELP . '</div>';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="fecha" class="column a02 tright">' . REVIEWS_TABLE_DATE . ':</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= array_key_exists( 'date_added', $aMessageError ) ? $aMessageError['date_added'] : '';
		$sHtml .= '<input type="text" class="form-datetime-simple" readonly="readonly" id="fecha" name="date_added" data-autoupdate="true" autocomplete="off" value="' . (array_key_exists( 'date_added', $aRecord ) && $aRecord['date_added'] != '0000-00-00 00:00:00' && $aRecord['date_added'] != '' ? date::changeDate( $aRecord['date_added'], 'standar', 'd-m-y' ) : $_POST['date_added']) . '">';
		$sHtml .= '<div class="DFhelp">' . REVIEWS_TABLE_DATE_HELP . '</div>';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="comentario" class="column a02 tright">' . REVIEWS_TABLE_REVIEW . ':</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= array_key_exists( 'reviews_text', $aMessageError ) ? $aMessageError['reviews_text'] : '';
		$sHtml .= '<textarea name="reviews_text" row="3" id="comentario" style="min-height: 90px;">' . (array_key_exists( 'reviews_text', $aRecord ) ? $aRecord['reviews_text'] : $_POST['reviews_text']) . '</textarea>';
		$sHtml .= '<div class="DFhelp">' . REVIEWS_TABLE_REVIEW_HELP . '</div>';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="ventajas" class="column a02 tright">' . REVIEWS_TABLE_ADVANTAGES . ':</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= '<textarea name="reviews_pros" row="3" id="ventajas" style="min-height: 90px;">' . (array_key_exists( 'reviews_pros', $aRecord ) ? $aRecord['reviews_pros'] : $_POST['reviews_pros']) . '</textarea>';
		$sHtml .= '<div class="DFhelp">' . REVIEWS_TABLE_ADVANTAGES_HELP . '</div>';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="desventajas" class="column a02 tright">' . REVIEWS_TABLE_DISADVANTAGES . ':</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= '<textarea name="reviews_contras" row="3" id="desventajas" style="min-height: 90px;">' . (array_key_exists( 'reviews_contras', $aRecord ) ? $aRecord['reviews_contras'] : $_POST['reviews_contras']) . '</textarea>';
		$sHtml .= '<div class="DFhelp">' . REVIEWS_TABLE_DISADVANTAGES_HELP . '</div>';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="rating" class="column a02 tright">' . REVIEWS_TABLE_RECOMMENDED . ':</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= tep_draw_pull_down_menu( 'reviews_recomendar', $aRecomendado, (array_key_exists( 'reviews_recomendar', $aRecord ) ? $aRecord['reviews_recomendar'] : $_POST['reviews_recomendar']) );
		$sHtml .= '<div class="DFhelp">' . REVIEWS_TABLE_RECOMMENDED_HELP . '</div>';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="rating" class="column a02 tright">' . REVIEWS_TABLE_APPROVED . ':</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= tep_draw_pull_down_menu( 'approved', $aRecomendado, (array_key_exists( 'approved', $aRecord ) ? $aRecord['approved'] : $_POST['approved']) );
		$sHtml .= '<div class="DFhelp">' . REVIEWS_TABLE_APPROVED_HELP . '.</div>';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="rating" class="column a02 tright">' . REVIEWS_TABLE_EVALUATION . ':</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= tep_draw_pull_down_menu( 'reviews_rating', $aRatings, (array_key_exists( 'reviews_rating', $aRecord ) ? $aRecord['reviews_rating'] : $_POST['reviews_rating']) );
		$sHtml .= '<div class="DFhelp">' . REVIEWS_TABLE_EVALUATION_HELP . '</div>';
		$sHtml .= '</div>';
		$sHtml .= '</form>';
		$sHtml .= '</div>';
		$sHtml .= '</div>';
		break;

	default:
		// Variables
		$sSubtitle = HEADING_SUBTITLE;
		$aButtons[] = [ 'title' => REVIEWS_TEXT_ADD, 'href' => tep_href_link( $sUrlPage, 'action=add_form' ), 'icon' => 'fa-plus' ];

		// Html para el boton masivo
		$sHtmlActionMasivo = '<label class="column afluid">' . REVIEWS_TABLE_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . REVIEWS_TABLE_ACTIONS . '</div>
				<ul class="down drch">
					<li><a data-question="' . REVIEWS_DELETE_REVIEWS_CONFIRM . '" data-error="' . REVIEWS_DELETE_ERROR . '" data-action="' . tep_href_link( $sUrlPage, 'action=delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . REVIEWS_DELETE_REVIEWS . '</a></li>
					<li><a data-question="¿Realmente deseas aprobar estos comentarios?" data-action="' . tep_href_link( $sUrlPage, 'action=approve' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-toggle-on"></i>Aprobar comentarios</a></li>
					<li><a data-question="¿Realmente deseas aprobar estos comentarios?" data-action="' . tep_href_link( $sUrlPage, 'action=decline' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-toggle-off"></i>Rechazar comentarios</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

		// Filtros
		$aFiler = [ 'search' => '', 'search_date' => '', 'search_rating' => '', 'search_status' => '' ];
		$aAuxFilter = array_key_exists( 'filter', $_GET ) && is_array($_GET['filter']) ? $_GET['filter'] : (array_key_exists( 'filter', $_POST ) && is_array($_POST['filter']) ? $_POST['filter'] : []);
		$sWhere = '';

		// Limpiamos variables get filter
		array_walk( $aFiler, function( $value, $key){ global $aFiler, $aAuxFilter; $aFiler[$key] = tep_db_prepare_input( array_key_exists( $key, $aAuxFilter ) ? $aAuxFilter[$key] : $aFiler[$key] ); } );

		// Where
		if ($aFiler['search'] !== '' || $aFiler['search_date'] !== '' || $aFiler['search_rating']) {
            $sWhere = 'where ';
        }

		if ($aFiler['search'] !== '') {
            $sWhere .= ( $sWhere !== 'where ' ? ' and' : '') . ' (LOWER(pd.products_name) LIKE "%' . strtolower( $aFiler['search'] ) . '%" OR LCASE( r.customers_name ) LIKE "%' . strtolower( $aFiler['search'] ) . '%")';
        }

		if ($aFiler['search_rating'] !== '') {
            $sWhere .= ( $sWhere !== 'where ' ? ' and' : '') . ' reviews_rating = ' . $aFiler['search_rating'];
        }

		if( $aFiler['search_status'] !== '' )
		{
			if ($aFiler['search_status'] === '2') {
                $aFiler['search_status'] = '0';
            }
			$sWhere .= ( $sWhere !== 'where ' ? ' and' : '') . ' approved = ' . $aFiler['search_status'];
		}

		if( $aFiler['search_date'] !== '' )
		{
			$aValue = explode( ' - ', $aFiler['search_date'] );
			$aValue[0] = date::changeDate( $aValue[0], 'espanol', 'y-m-d' );
			$aValue[1] = date::changeDate( $aValue[1], 'espanol', 'y-m-d' );

			$sWhere .= ( $sWhere !== 'where ' ? ' and' : '') . ' date_added >= "' . $aValue[0] . '" AND (date_added <= "' . $aValue[1] . '")';
		}

		// Order by
		$sOrderby = $sGetOrderby != '' ? $sGetOrderby . ' ' . $sGetSort : 'date_added DESC';

		// Sql
		$sSql = 'SELECT r.reviews_id, rd.reviews_text, rd.reviews_pros, rd.reviews_contras, p.products_image, pd.products_name, customers_name, date_added, approved, reviews_rating
					 FROM reviews r
					 INNER JOIN reviews_description rd ON( r.reviews_id = rd.reviews_id )
					 INNER JOIN products p ON( r.products_id = p.products_id )
					 INNER JOIN products_description pd ON( p.products_id = pd.products_id AND pd.language_id = ' . $languages_id . ' )
					 ' . $sWhere . ' ORDER BY ' . $sOrderby;

		// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
		$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

		// Sql para el count
		$sSqlCount = 'SELECT COUNT(table_aux.reviews_id) as total FROM (' . $sSql . ') as table_aux';

		// Datos y paginacion
		$aDatoSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
		$aDatos = tep_db_query( $sSql );

		// Mensajes comprobamos si tenemos datos
		if( tep_db_num_rows( $aDatos ) <= 0 )
		{
			if ($sWhere !== '') {
                $sHtml .= $messageStack->show( [ 'text' => REVIEWS_ERROR_NO_REVIEWS, 'class' => 'warning' ] );
            } else {
                $sHtml .= $messageStack->show( [ 'text' => REVIEWS_ERROR_NO_FILTER_RESULT, 'class' => 'warning' ] );
            }
		}

		// Tabla
		$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
		$sHtml .= '<div class="oeWrpr">';
		$sHtml .= '<form method="post" action="' . tep_href_link( $sUrlPage ) . '" class="oeCntd row ax">';
		$sHtml .= '<div class="oeBoxFltr column a12 ax row">';
		$sHtml .= '<div class="column a09 row ax amiddle input-search">';
		$sHtml .= '<label class="column">' . TEXT_SEARCH . ': </label> <div class="column"><input type="text" name="filter[search]" placeholder="' . REVIEWS_SEARCH_PLACEHOLDER . '" value="' . $aFiler['search'] . '" autofocus/> <i class="fa fa-search"></i></div>';
		$sHtml .= '</div>';
		$sHtml .= '<div class="column a03 tright">';
		$sHtml .= ($sWhere !== '' ? '<a title="' . REVIEWS_REMOVE_FILTER . '" href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( [ 'filter' ] ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa fa-close"></i></a> ' : '');
		$sHtml .= '<a href="#fltr-lstd" title="' . REVIEWS_FILTER_REVIEWS . '" class="xbutton small hv9 verde-turquesa mgp-inln"><i class="fa fa-filter"></i> ' . REVIEWS_FILTER_RESULTS . '</a>';
		$sHtml .= '</div>';
		$sHtml .= '</div>';

		$sHtml .= '<table class="xform">';
		$sHtml .= '<thead>';
		$sHtml .= '<tr>';
		$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
		$sHtml .= '<th>' . REVIEWS_TABLE_IMAGE . '</th>';
		$sHtml .= '<th class="sort" width="150">' . tableSetSort( 'products_name', REVIEWS_TABLE_PRODUCT ) . '</th>';
		$sHtml .= '<th class="sort" width="150">' . tableSetSort( 'customers_name', REVIEWS_TABLE_AUTHOR ) . '</th>';
		$sHtml .= '<th class="sort">' . tableSetSort( 'date_added', REVIEWS_TABLE_DATE ) . '</th>';
		$sHtml .= '<th class="sort">' . REVIEWS_TABLE_REVIEW . '</th>';
		$sHtml .= '<th class="sort">' . tableSetSort( 'reviews_rating', REVIEWS_TABLE_EVALUATION ) . '</th>';
		$sHtml .= '<th>' . REVIEWS_TABLE_APPROVED . '</th>';
		$sHtml .= '<th width="125">' . REVIEWS_TABLE_ACTIONS . '</th>';
		$sHtml .= '</tr>';
		$sHtml .= '</thead>';
		$sHtml .= '<tbody>';

		while( $aDato = tep_db_fetch_array( $aDatos ) )
		{
			// Fila
			$sHtml .= '<tr data-dblclick="' . tep_href_link( $sUrlPage, 'action=add_form&id=' . $aDato['reviews_id'] ) . '">';
			$sHtml .= '<td class="chck" align="center"><input type="checkbox" id="id_' . $aDato['reviews_id'] . '" name="id[]" value="' . $aDato['reviews_id'] . '"/><label for="id_' . $aDato['reviews_id'] . '"><span></span></label></td>';
			$sHtml .= '<td>' . tep_image( '../'.DIR_WS_IMAGES . 'productos/'. $aDato['products_image'], $aDato['products_name'], 70, 70, 'hspace="5" vspace="5"', false) . '</td>';
			$sHtml .= '<td>' . $aDato['products_name'] . '</td>';
			$sHtml .= '<td>' . $aDato['customers_name'] . '</td>';
			$sHtml .= '<td>' . ($aDato['date_added'] != '' && $aDato['date_added'] != '0000-00-00 00:00:00' ? date( 'd-m-Y', strtotime( (string) $aDato['date_added'] ) ) : '-') . '</td>';
			$sHtml .= '<td>' . strip_tags((string) $aDato['reviews_text']) . '</td>';
			$sHtml .= '<td><span class="star st' . $aDato['reviews_rating'] . '"><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i></span></td>';
			$sHtml .= '<td>';
			if ($aDato['approved'] == '1') {
                $sHtml .= tep_image(DIR_WS_IMAGES . 'icon_status_green.png', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link($sUrlPage, 'action=setflag&flag=0&id=' . $aDato['reviews_id'], 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
            } else {
                $sHtml .= '<a href="' . tep_href_link($sUrlPage, 'action=setflag&flag=1&id=' . $aDato['reviews_id'], 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.png', IMAGE_ICON_STATUS_RED, 10, 10);
            }
			$sHtml .= '</td>';
			$sHtml .= '<td>';
			$sHtml .= '<div class="drop xfselect">';
			$sHtml .= '<div>Acciones</div>';
			$sHtml .= '<ul class="down down-dngt">';
			$sHtml .= '<li><a href="' . tep_href_link( $sUrlPage, 'action=add_form&id=' . $aDato['reviews_id'] ) . '" class="hv"><i class="fa fa-pencil"></i>' . REVIEWS_TEXT_EDIT . '</a></li>';
			$sHtml .= '<li><a data-confirm="' . REVIEWS_DELETE_REVIEW_CONFIRM . '" href="' . tep_href_link( $sUrlPage, 'action=delete&id=' . $aDato['reviews_id'] ) . '" class="hv"><i class="fa fa-trash-alt"></i>' . REVIEWS_TEXT_DELETE . '</a></li>';
			$sHtml .= '</ul>';
			$sHtml .= '</div>';
			$sHtml .= '</td>';
			$sHtml .= '</tr>';
		}

		$sHtml .= '</tbody>';
		$sHtml .= '</table>';

		// Paginación
		$sHtml .= $aDatoSplit->showPaginateTable( tep_get_all_get_params( ['page'] ), 'page', $sHtmlActionMasivo, 'solenopsis' );

		$sHtml .= '</div>';
		$sHtml .= '</form>';
		$sHtml .= '</div>';
		$sHtml .= '</div>';

		// Filtro
		$sHtml .= '<form action="' . tep_href_link( $sUrlPage ) . '" method="get" id="fltr-lstd" class="vntn-form mfp-hide zoom-anim-dialog oeBox mfp-white">';
		$sHtml .= '<div class="oeWrpr">';
		$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> ' . HEADING_SUBTITLE . '</div>';
		$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
		$sHtml .= '<label for="search" class="column a02 tright">' . TEXT_SEARCH . ':</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= '<input type="text" name="filter[search]" placeholder="' . REVIEWS_SEARCH_PLACEHOLDER . '" value="' . $aFiler['search'] . '"/> ';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="search_rating" class="column a02 tright">' . REVIEWS_TABLE_EVALUATION . ':</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= tep_draw_pull_down_menu( 'filter[search_rating]', array_merge( [ [ 'id' => '', 'text' => TEXT_ALLS ] ], $aRatings ), $aFiler['search_rating'] );
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="search_rating" class="column a02 tright">' . REVIEWS_TABLE_STATUS . ':</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= tep_draw_pull_down_menu( 'filter[search_status]', [[ 'id' => '', 'text' => TEXT_ALLS ], [ 'id' => '1', 'text' => REVIEWS_TEXT_APPROVED ], [ 'id' => '2', 'text' => REVIEWS_TEXT_PENDANT ]], $aFiler['search_status'] );
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="search_date" class="column a02 tright">' . REVIEWS_TABLE_DATE . ':</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= '<input value="' . $aFiler['search_date'] . '" data-autoupdate="true" autocomplete="off" name="filter[search_date]" readonly="readonly" class="form-datetime-range" type="text" />';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-none"></div>';
		$sHtml .= '<div class="column a12 tright">';
		$sHtml .= ($sWhere !== '' ? '<a href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( [ 'filter' ] ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa fa-close"></i> ' . REVIEWS_TEXT_DELETE . '</a> ' : '');
		$sHtml .= '<div class="xbutton verde hv9 small"><input type="submit"/><span class="fa fa fa-filter"></span> ' . REVIEWS_TEXT_FILTER . '</div>';
		$sHtml .= '</div>';
		$sHtml .= '</div>';
		$sHtml .= '</div>';
		$sHtml .= '</form>';
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
echo '<div class="oeTitu column logo afixed" style="padding-left: 59px;"><b><i class="fas fa-comments"></i> ' . $sTitle . '</b>' . ($sSubtitle !== '' && $sSubtitle !== '0' ? '<small>' . $sSubtitle . '</small>' : '') . '</div>';
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
