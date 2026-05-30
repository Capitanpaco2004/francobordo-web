<?php

// Application top
require( 'includes/application_top.php' );

// Si estamos en una acción
if( isset( $_GET['action'] ) )
{
	// Si la acción es para recrear el cupón
	if( $_GET['action'] == 'refresh_coupon' )
	{
		// Bucle infinito hasta que tengamos un cupón válido
		while( true )
		{
			// Variables
			$sChars = "ABCDEFGHJKLMNPQRTUVWXYZ023456789";
			$sCoupon = '';

			// Valores aleatorios
			srand( (int) ((float) microtime() * 1000000) );

			// Recorremos y recreamos el cupón
			for( $nCont = 0; $nCont < MODULE_ORDER_TOTAL_DISCOUNT_COUPON_RANDOM_CODE_LENGTH; ++$nCont )
				$sCoupon .= substr( $sChars, ( rand() % 33 ), 1 );

			// Comprobamos que el cupón no exista
			$aCoupon = tep_db_query( 'SELECT coupons_id FROM ' . TABLE_DISCOUNT_COUPONS . ' WHERE coupons_id = "' . $sCoupon . '";' );

			// Pintamos el cupón si no existe
			if( tep_db_num_rows( $aCoupon ) == 0 )
				die( $sCoupon );
		}
	}
	// Obtener categories
	else if( $_GET['action'] == 'get_categories' )
	{
		// Obtenemos las categorías según el texto enviado
		$aElements = tep_db_query( 'SELECT c.parent_id, c.categories_id, cd.categories_name FROM categories c INNER JOIN categories_description cd ON (c.categories_id = cd.categories_id) WHERE LCASE( cd.categories_name ) LIKE "%' . strtolower( $_GET['text'] ) . '%" AND cd.language_id = 3 order by cd.categories_name asc' );

		// Función recursiva para obtener las categorias padre
		function getCategoriesByParent($nCategory, $sCategory)
		{
			// Obtenemos la categoria padre
			$aAux = tep_db_query( 'SELECT c.parent_id, cd.categories_name FROM categories c INNER JOIN categories_description cd ON (c.categories_id = cd.categories_id) WHERE c.categories_id = ' . $nCategory . ' AND cd.language_id = 3 order by cd.categories_name asc' );
			$aAux = tep_db_fetch_array( $aAux );

			// Nombre de categoria
			if( $aAux['categories_name'] != '' )
				$sCategory = $aAux['categories_name'] . ' => ' . $sCategory;

			// Si tenemos padre
			if( $aAux['parent_id'] != 0 && $aAux['parent_id'] != '' )
				$sCategory = getCategoriesByParent( $aAux['parent_id'], $sCategory );

			// Retornamos la categoria
			return $sCategory;
		}

		// Rellenamos de elementos
		$sElements = '';
		while( $aElement = tep_db_fetch_array( $aElements ) )
		{
			$sAux = getCategoriesByParent( $aElement['parent_id'], $aElement['categories_name'] );
			$sElements .= '<li class="ui-draggable" id="' . $aElement['categories_id'] . '">' . $sAux . '</li>';
		}

		// Pintamos el resultado
		die( $sElements );
	}
	// Obtener productos
	else if( $_GET['action'] == 'get_products' )
	{
		// Si enviamos un texto largo
		if( strlen( $_GET['text'] ) > 4 )
		{
			// Obtenemos los productos según el texto enviado
			$aElements = tep_db_query( 'SELECT products_id, products_name FROM products_description WHERE LCASE( products_name ) LIKE "%' . strtolower( $_GET['text'] ) . '%" AND language_id = 3;' );

			// Rellenamos de elementos
			$sElements = '';
			while( $aElement = tep_db_fetch_array( $aElements ) )
				$sElements .= '<li class="ui-draggable" id="' . $aElement['products_id'] . '">' . $aElement['products_name'] . '</li>';

			// Pintamos el resultado
			die( $sElements );
		}

		die( '' );
	}
	// Obtener fabricantes
	else if( $_GET['action'] == 'get_manufacturers' )
	{
		// Si enviamos un texto largo
		if( strlen( $_GET['text'] ) > 3 )
		{
			// Obtenemos los productos según el texto enviado
			$aElements = tep_db_query( 'SELECT manufacturers_id, manufacturers_name FROM manufacturers WHERE LCASE( manufacturers_name ) LIKE "%' . strtolower( $_GET['text'] ) . '%";' );

			// Rellenamos de elementos
			$sElements = '';
			while( $aElement = tep_db_fetch_array( $aElements ) )
				$sElements .= '<li class="ui-draggable" id="' . $aElement['manufacturers_id'] . '">' . $aElement['manufacturers_name'] . '</li>';

			// Pintamos el resultado
			die( $sElements );
		}

		die( '' );
	}
	// Obtener clientes
	else if( $_GET['action'] == 'get_customers' )
	{
		// Si enviamos un texto largo
		if( strlen( $_GET['text'] ) > 3 )
		{
			// Obtenemos los productos según el texto enviado
			$aElements = tep_db_query( 'SELECT customers_id, customers_firstname, customers_lastname, customers_email_address FROM customers WHERE LCASE( CONCAT( customers_firstname, " ", customers_lastname ) ) LIKE "%' . strtolower( $_GET['text'] ) . '%" OR LCASE( customers_email_address ) LIKE "%' . strtolower( $_GET['text'] ) . '%";' );

			// Rellenamos de elementos
			$sElements = '';
			while( $aElement = tep_db_fetch_array( $aElements ) )
				$sElements .= '<li class="ui-draggable" id="' . $aElement['customers_id'] . '">' . $aElement['customers_firstname'] . ' ' . $aElement['customers_lastname'] . ' (' . $aElement['customers_email_address'] . ')</li>';

			// Pintamos el resultado
			die( $sElements );
		}

		die( '' );
	}else if( $_GET['action'] == 'getGroups' )
	{
		// Si enviamos un texto largo
		if( strlen( $_GET['text'] ) > 3 )
		{
			$aElements = tep_db_query("SELECT customers_group_id, customers_group_name FROM customers_groups");

			// Rellenamos de elementos
			$sElements = '';
			while( $aElement = tep_db_fetch_array( $aElements ) )
				$sElements .= '<li class="ui-draggable" id="' . $aElement['customers_group_id'] . '">' . $aElement['customers_group_name'] . '</li>';

			// Pintamos el resultado
			die( $sElements );
		}

		die( '' );
	}
}

// Variables
$sCoupon = '';
$sDescription = '';
$sDiscountAmount = '';
$sDiscountType = '';
$sDateStart = '';
$sDateEnd = '';
$sMaxUse = '';
$sMinOrder = '';
$sMinOrderType = '';
$sNumberAvailable = '';
$bError = false;
$sErrorCoupon = false;
$sErrorDescription = false;
$sErrorDiscountAmount = false;
$sErrorDiscountType = false;
$sErrorDateStart = false;
$sErrorDateEnd = false;
$sErrorMaxUse = false;
$sErrorMinOrder = false;
$sErrorMinOrderType = false;
$sErrorNumberAvailable = false;
$aFilters = array( 'categories' => array(), 'products' => array(), 'customers' => array(), 'manufacturers' => array(), 'groups' => array() );

// Método POST
if( $_SERVER['REQUEST_METHOD'] == 'POST' )
{
	// Obtenemos los valores
	$sCoupon = $_POST['coupon'];
	$sDescription = $_POST['coupons_description'];
	$sDiscountAmount = $_POST['coupons_discount_amount'];
	$sDiscountType = $_POST['coupons_discount_type'];
	$sDateStart = $_POST['coupons_date_start'];
	$sDateEnd = $_POST['coupons_date_end'];
	$sMaxUse = $_POST['coupons_max_use'];
	$sMinOrder = $_POST['coupons_min_order'];
	$sMinOrderType = $_POST['coupons_min_order_type'];
	$sNumberAvailable = $_POST['coupons_number_available'];


	// Comprobamos si hay errores //

	// Cupón
	if( $sCoupon == '' )
	{
		$sErrorCoupon = 'El código del cupón de descuento es requerido';
		$bError = true;
	}
	// Descripción
	if( $sDescription == '' )
	{
		$sErrorDescription = 'La descripción del cupón de descuento es requerida';
		$bError = true;
	}
	// Cantidad del cupón de descuento
	if( $sDiscountAmount == '' )
	{
		$sErrorDiscountAmount = 'El valor del cupón de descuento es requerido';
		$bError = true;
	}
	else if( ! is_numeric( $sDiscountAmount ) )
	{
		$sErrorDiscountAmount = 'El valor del cupón de descuento debe de ser una cantidad numérica válida';
		$bError = true;
	}
	// Fecha inicio y fecha fin
	if( $sDateStart != '' && ! preg_match( '/^([0-9]{2})\/([0-9]{2})\/([0-9]{4})$/i', $sDateStart ) )
	{
		$sErrorDateStart = 'El valor de la fecha de inicio debe de ser una fecha válida (formato DD/MM/AAAA, por ejemplo ' . date( 'd/m/Y' ) . ')';
		$bError = true;
	}
	if( $sDateEnd != '' && ! preg_match( '/^([0-9]{2})\/([0-9]{2})\/([0-9]{4})$/i', $sDateEnd ) )
	{
		$sErrorDateEnd = 'El valor de la fecha de fin debe de ser una fecha válida (formato DD/MM/AAAA, por ejemplo ' . date( 'd/m/Y' ) . ')';
		$bError = true;
	}
	// Número máximo de usos
	if( $sMaxUse != '' && ! is_numeric( $sMaxUse ) )
	{
		$sErrorMaxUse = 'El valor de la cantidad máxima de usos debe de ser un número entero';
		$bError = true;
	}
	// Pedido mínimo
	if( $sMinOrder != '' && ! is_numeric( $sMinOrder ) )
	{
		$sErrorMinOrder = 'El valor de la cantidad mínima del pedido debe de ser una cantidad numérica válida';
		$bError = true;
	}
	// Pedido mínimo
	if( $sNumberAvailable != '' && ! is_numeric( $sNumberAvailable ) )
	{
		$sErrorNumberAvailable = 'El valor de la cantidad de cupones disponibles debe de ser una cantidad numérica válida';
		$bError = true;
	}

	// Inserción de cupón en BD //

	// Si no tenemos errores
	if( $bError === false )
	{
		// Descomponemos las fechas
		if( $sDateStart != '' ) $aAuxDateStart = explode( '/', $sDateStart );
		if( $sDateEnd != '' ) $aAuxDateEnd = explode( '/', $sDateEnd );

		// Preparamos los datos
		$aDatas = array(
			'coupons_id' => $sCoupon,
			'coupons_description' => $sDescription,
			'coupons_discount_amount' => $sDiscountAmount,
			'coupons_discount_type' => $sDiscountType,
			'coupons_date_start' => ($sDateStart == '' ? 'null' : $aAuxDateStart[2] . '-' . $aAuxDateStart[1] . '-' . $aAuxDateStart[0] ),
			'coupons_date_end' => ($sDateEnd == '' ? 'null' : $aAuxDateEnd[2] . '-' . $aAuxDateEnd[1] . '-' . $aAuxDateEnd[0]),
			'coupons_max_use' => ($sMaxUse == '' || $sMaxUse == 0 ? 'null' : $sMaxUse),
			'coupons_min_order' => ($sMinOrder == '' || $sMinOrder == 0 ? 'null' : $sMinOrder),
			'coupons_min_order_type' => $sMinOrderType,
			'coupons_number_available' => ($sNumberAvailable == '' || $sNumberAvailable == 0 ? 'null' : $sNumberAvailable),
		);

		// Si estamos editando
		if( isset( $_GET['coupon'] ) )
		{
			// Variables
			$bExists = false;

			// Si el cupón introducido es diferente al que estamos editando
			if( $sCoupon != $_GET['coupon'] )
			{
				// Comprobamos que el cupón no exista
				$aCoupon = tep_db_query( 'SELECT coupons_id FROM ' . TABLE_DISCOUNT_COUPONS . ' WHERE coupons_id = "' . $sCoupon . '";' );
				$bExists = tep_db_num_rows( $aCoupon ) > 0;
			}

			// Si cupón NO existe actualizamos
			if( ! $bExists )
				tep_db_perform( TABLE_DISCOUNT_COUPONS, $aDatas, 'update', 'coupons_id = "' . $_GET['coupon'] . '"' );
			// Si el cupón SI existe
			else
			{
				// Mensaje de error
				$sErrorCoupon = 'El cupón que estás intentando utilizar ya existe en la base de datos';
				$bError = true;
			}
		}
		// Si estamos insertando
		else
		{
			// Comprobamos que el cupón no exista
			$aCoupon = tep_db_query( 'SELECT coupons_id FROM ' . TABLE_DISCOUNT_COUPONS . ' WHERE coupons_id = "' . $sCoupon . '";' );

			// Si el cupón NO existe insertamos
			if( tep_db_num_rows( $aCoupon ) == 0 )
				tep_db_perform( TABLE_DISCOUNT_COUPONS, $aDatas );
			// Si el cupón SI existe
			else
			{
				// Mensaje de error
				$sErrorCoupon = 'El cupón que estás intentando utilizar ya existe en la base de datos';
				$bError = true;
			}
		}
	}

	// Inserción de filtros //

	// Si no tenemos error
	if( $bError === false )
	{
		// Eliminamos todos los filtros del cupón
		tep_db_query( 'DELETE FROM discount_coupons_filters WHERE coupons_id = "' . $sCoupon . '";' );

		// Vamos añadiendo los filtros //

		// Categorias
		addFilter( 'categories', $_POST['row-cat'], $sCoupon );
		// Productos
		addFilter( 'products', $_POST['row-prd'], $sCoupon );
		// Fabricantes
		addFilter( 'manufacturers', $_POST['row-mnf'], $sCoupon );
		// Clientes
		addFilter( 'customers', $_POST['row-ctm'], $sCoupon );
		// Grupos de clientes
		addFilter( 'groups', $_POST['row-grp'], $sCoupon );
	}

	// Redireccionamos
	if( $bError === false )
		tep_redirect( FILENAME_DISCOUNT_COUPONS );

	// Rellenamos el array de filtros por POST //

	// Variables
	$sSQLFilters = '';
	$nCont = 0;

	// Categorias
	if( isset( $_POST['row-cat'] ) )
		$sSQLFilters .= '( SELECT categories_id AS id, "categories" AS type, categories_name AS name FROM categories_description WHERE categories_id IN (' . implode( ', ', $_POST['row-cat'] ) . ') AND language_id = 3 ) UNION ';
	// Productos
	if( isset( $_POST['row-prd'] ) )
		$sSQLFilters .= '( SELECT products_id AS id, "products" AS type, products_name AS name FROM products_description WHERE products_id IN (' . implode( ', ', $_POST['row-prd'] ) . ') AND language_id = 3 ) UNION ';
	// Fabricantes
	if( isset( $_POST['row-mnf'] ) )
		$sSQLFilters .= '( SELECT manufacturers_id AS id, "manufacturers" AS type, manufacturers_name AS name FROM manufacturers WHERE manufacturers_id IN (' . implode( ', ', $_POST['row-mnf'] ) . ') ) UNION ';
	// Clientes
	if( isset( $_POST['row-ctm'] ) )
		$sSQLFilters .= '( SELECT customers_id AS id, "customers" AS type, CONCAT( customers_firstname, " ", customers_lastname ) AS name FROM customers WHERE customers_id IN (' . implode( ', ', $_POST['row-ctm'] ) . ') ) UNION ';
	// Grupos de clientes
	if( isset( $_POST['row-grp'] ) )
		$sSQLFilters .= '( SELECT customers_group_id AS id, "groups" AS type, "" AS name FROM customers_group WHERE customers_group_id IN (' . implode( ', ', $_POST['row-grp'] ) . ') ) UNION ';

	// Si tenemos consulta
	if( $sSQLFilters != '' )
	{
		// Eliminamos el ultimo UNION
		$sSQLFilters = substr( $sSQLFilters, 0, -7 );

		// Ejecutamos la consulta
		$aAuxs = tep_db_query( $sSQLFilters );


		// Rellenamos el array de filtros
		while( $aAux = tep_db_fetch_array( $aAuxs ) )
		{
			// Valores del filtro
			$aFilters[$aAux['type']][$nCont]['id'] = $aAux['id'];
			$aFilters[$aAux['type']][$nCont]['name'] = $aAux['name'];

			++$nCont;
		}
	}
}
// Método GET
else
{
	// Si estamos editando
	if( isset( $_GET['coupon'] ) )
	{
		// Variables
		$nCont = 0;

		// Obtenemos los datos del cupón
		$aCoupon = tep_db_query( 'SELECT coupons_id, coupons_description, coupons_discount_amount, coupons_discount_type, DATE_FORMAT(coupons_date_start, "%d/%m/%Y") AS coupons_date_start, DATE_FORMAT(coupons_date_end, "%d/%m/%Y") AS coupons_date_end, coupons_max_use, coupons_min_order, coupons_min_order_type, coupons_number_available FROM ' . TABLE_DISCOUNT_COUPONS . ' WHERE coupons_id = "' . $_GET['coupon'] . '";' );
		$aCoupon = tep_db_fetch_array( $aCoupon );

		// Obtenemos los valores
		$sCoupon = $aCoupon['coupons_id'];
		$sDescription = $aCoupon['coupons_description'];
		$sDiscountAmount = $aCoupon['coupons_discount_amount'];
		$sDiscountType = $aCoupon['coupons_discount_type'];
		$sDateStart = $aCoupon['coupons_date_start'];
		$sDateEnd = $aCoupon['coupons_date_end'];
		$sMaxUse = $aCoupon['coupons_max_use'];
		$sMinOrder = $aCoupon['coupons_min_order'];
		$sMinOrderType = $aCoupon['coupons_min_order_type'];
		$sNumberAvailable = $aCoupon['coupons_number_available'];

		// Obtenemos los filtros del cupón
		$aAuxs = tep_db_query( 'SELECT cg.customers_group_name, f.filter_id, f.filter_type, cd.categories_name, pd.products_name, m.manufacturers_name, CONCAT( c.customers_firstname, " ", c.customers_lastname ) AS customers_name FROM discount_coupons_filters f LEFT OUTER JOIN categories_description cd ON (f.filter_id = cd.categories_id AND f.filter_type = "categories" AND cd.language_id = 3) LEFT OUTER JOIN products_description pd ON (f.filter_id = pd.products_id AND f.filter_type = "products" AND pd.language_id = 3) LEFT OUTER JOIN manufacturers m ON (f.filter_id = m.manufacturers_id AND f.filter_type = "manufacturers") LEFT OUTER JOIN customers c ON (f.filter_id = c.customers_id AND f.filter_type = "customers")
								LEFT OUTER JOIN customers_groups cg ON (f.filter_id = cg.customers_group_id AND f.filter_type = "groups") WHERE coupons_id = "' . $_GET['coupon'] . '";' );

		// Rellenamos el array de filtros
		while( $aAux = tep_db_fetch_array( $aAuxs ) )
		{
			// ID del filtro
			$aFilters[$aAux['filter_type']][$nCont]['id'] = $aAux['filter_id'];

			// Nombre del filtro
			switch( $aAux['filter_type'] )
			{
				case 'categories': $aFilters[$aAux['filter_type']][$nCont]['name'] = $aAux['categories_name']; break;
				case 'products': $aFilters[$aAux['filter_type']][$nCont]['name'] = $aAux['products_name']; break;
				case 'manufacturers': $aFilters[$aAux['filter_type']][$nCont]['name'] = $aAux['manufacturers_name']; break;
				case 'customers': $aFilters[$aAux['filter_type']][$nCont]['name'] = $aAux['customers_name']; break;
				case 'groups': $aFilters[$aAux['filter_type']][$nCont]['name'] = $aAux['customers_group_name']; break;
			}

			++$nCont;
		}
	}
}


?>

<?php require( THEME . 'html/header.php' ); ?>
<style type="text/css">
.img-load
{
	display: none;
	left: -26px;
    position: absolute;
    top: 9px;
}

#drop-rows-cat, #drop-rows-prd, #drop-rows-mnf, #drop-rows-ctm
{
	padding: 0px;
}
#rows-drag-prd li, #rows-drag-cat li, #rows-drag-mnf li, #rows-drag-ctm li,#rows-drag-grp li,
#drop-rows-cat li, #drop-rows-prd li, #drop-rows-mnf li, #drop-rows-ctm li,#drop-rows-grp li
{
	background-image: url("images/icons/cupones_sprite.png");
    background-repeat: no-repeat;
    margin-bottom: 6px;
    padding: 6px 0 3px 35px;
	-ms-filter: "progid:DXImageTransform.Microsoft.Alpha(Opacity=80)";
	filter: alpha(opacity=80);
	-moz-opacity: 0.8;
	-khtml-opacity: 0.8;
	opacity: 0.8;
	cursor: move;
	list-style: none;
}

#rows-drag-prd li:hover
{
	-ms-filter: "progid:DXImageTransform.Microsoft.Alpha(Opacity=90)";
	filter: alpha(opacity=100);
	-moz-opacity: 1;
	-khtml-opacity: 1;
	opacity: 1;
}

#rows-drag-prd li, #drop-rows-prd li{ background-position: 0px 0px; }
#rows-drag-cat li, #drop-rows-cat li{ background-position: 0px -94px; }
#rows-drag-mnf li, #drop-rows-mnf li{ background-position: 0 -47px; }
#rows-drag-ctm li, #drop-rows-ctm li{ background-position: 0 -141px; }

.drop-empty
{
	min-height: 50px;
	border: 1px dashed #CCCCCC;
    display: block;
    height: 35px;
	background: url("images/icons/cupones_drop.png") no-repeat center center;
}

.drop-empty.drop-hovr
{
	border: 1px dashed #5DADE2;
}

.drag-sltc
{
	-ms-filter: "progid:DXImageTransform.Microsoft.Alpha(Opacity=20)" !important;
	filter: alpha(opacity=40) !important;
	-moz-opacity: 0.4 !important;
	-khtml-opacity: 0.4 !important;
	opacity: 0.4 !important;
}
</style>

<table border="0" width="100%"><tr><td>
<div id="box-left">
	<ul class="nav">
		<li><a href="javascript:void(0);" class="active" data-id="1"><img src="images/icons/cupon.png" alt=""><span>Cupón</span></a></li>
		<li><a href="javascript:void(0);" data-id="2" class=""><img src="images/icons/cupon_categoria.png" alt=""><span>Categorías</span></a></li>
		<li><a href="javascript:void(0);" data-id="3" class=""><img src="images/icons/cupon_producto.png" alt=""><span>Productos</span></a></li>
		<li><a href="javascript:void(0);" data-id="4" class=""><img src="images/icons/cupon_fabricante.png" alt=""><span>Fabricantes</span></a></li>
		<li><a href="javascript:void(0);" data-id="5" class=""><img src="images/icons/cupon_cliente.png" alt=""><span>Clientes</span></a></li>
		<li><a href="javascript:void(0);" data-id="6" class=""><img src="images/icons/cupon_grupo.png" alt=""><span>Grupos de clientes</span></a></li>
	</ul>
</div>
<div style="position:relative;">
	<div id="box-right">
		<?php echo tep_draw_form( 'coupons_create', FILENAME_DISCOUNT_COUPONS_CREATE . (isset( $_GET['coupon'] ) ? '?coupon=' . $_GET['coupon'] : '') . (isset( $_GET['page'] ) ? (isset( $_GET['coupon'] ) ? '&' : '?') . 'page=' . $_GET['page'] : ''), '', 'post', 'id="coupons_create"', '' ); ?>
			<table width="100%" cellspacing="0" border="0">
				<tbody>
					<tr>
						<td>
							<div>
								<div class="toolbarHead">
									<div class="hdr-tlbr">
										<h1 class="pageHeading" style="top: 12px;">Cupones descuento</h1>
										<div class="btn-right">
											<a title="Guardar cambios" onclick="$('#coupons_create').submit();" href="javascript:void(0);"><img src="images/icons/icon_save.png" class="dx-hovr"></a>
											<a href="<?php echo FILENAME_DISCOUNT_COUPONS . (isset( $_GET['page'] ) ? '?page=' . $_GET['page'] : ''); ?>"><img title="Volver sin guardar" src="images/icons/icon_back.png" class="dx-hovr"></a>
										</div>
									</div>
								</div>
							</div>
						</td>
					</tr>

					<!-- CUPÓN -->
					<tr data-id="1" style="display: block;" class="tab-new">
						<td style="display:block;">
							<div class="fluid grid">
								<div class="box-tbl grid12">
									<div class="box-head">
										<h6>Datos del cupón</h6>
										<div class="clear"></div>
									</div>

									<div class="formRow">
										<div class="grid2"><label>Cupón:</label></div>
										<div class="grid2"><input type="text" id="coupon" value="<?php echo $sCoupon; ?>" name="coupon" style="width: 120px;" /><?php echo '<a href="javascript:void(0);" style="display: inline-block; left: 7px; position: relative; top: 6px;" title="Recrear cupón" onclick="refresh_coupon();"><img src="images/icons/new_coupon.png" alt="Recrear cupón" class="new-cpn-img" /></a>' . '<span class="note">' . ( $sErrorCoupon !== false ? '<font color="red">' . $sErrorCoupon . '</font>' : 'Escribe el código del cupón o haz click en el icono para recrear un código de cupón automáticamente' ) . '</span>'; ?></div>
										<div class="clear"></div>
									</div>
								</div>
							</div>

							<div class="fluid grid">
								<div class="box-tbl grid12">
									<div class="box-head">
										<h6>Datos del cupón</h6>
										<div class="clear"></div>
									</div>

									<div class="formRow">
										<div class="grid2"><label>Descripción:</label></div>
										<div class="grid10"><?php echo tep_draw_input_field( 'coupons_description', $sDescription ) . ( $sErrorDescription !== false ? '<span class="note"><font color="red">' . $sErrorDescription . '</font></span>' : '' ); ?></div>
										<div class="clear"></div>
									</div>
									<div class="formRow">
										<div class="grid2"><label>Descuento de:</label></div>
										<div class="grid2"><?php echo tep_draw_input_field( 'coupons_discount_amount', strval($sDiscountAmount) ) . ( $sErrorDiscountAmount !== false ? '<span class="note"><font color="red">' . $sErrorDiscountAmount . '</font></span>' : '' ); ?></div>
										<div class="grid1"><label>del tipo</label></div>
										<div class="grid3"><?php echo tep_draw_pull_down_menu( 'coupons_discount_type', array( array( 'id' => 'fixed', 'text' => 'Cantidad fija (€)' ), array( 'id' => 'percent', 'text' => 'Porcentaje de descuento (%)' ), array( 'id' => 'shipping', 'text' => 'Descuento sobre el envío (€)' ) ), $sDiscountType ) . ( $sErrorDiscountType !== false ? '<span class="note">' . $sErrorDiscountType . '</span>' : '' ); ?></div>
										<div class="clear"></div>
									</div>
									<div class="formRow">
										<div class="grid2"><label>Comienza el:</label></div>
										<div class="grid2"><?php echo tep_draw_input_field( 'coupons_date_start', $sDateStart, 'id="coupons_date_start"' ) . '<span class="note">' . ( $sErrorDateStart !== false ? '<font color="red">' . $sErrorDateStart . '</font>' : 'Déjalo en blanco si quieres que esté ya disponible' ) . '</span>'; ?></div>
										<div class="clear"></div>
									</div>
									<div class="formRow">
										<div class="grid2"><label>Finaliza el:</label></div>
										<div class="grid2"><?php echo tep_draw_input_field( 'coupons_date_end', $sDateEnd, 'id="coupons_date_end"' ) . '<span class="note">' . ( $sErrorDateEnd !== false ? '<font color="red">' . $sErrorDateEnd . '</font>' : 'Déjalo en blanco si quieres que no tenga fecha límite' ) . '</span>'; ?></div>
										<div class="clear"></div>
									</div>
									<div class="formRow">
										<div class="grid2"><label>Máximo de usos por cliente:</label></div>
										<div class="grid2"><?php echo tep_draw_input_field( 'coupons_max_use', $sMaxUse ) . '<span class="note">' . ( $sErrorMaxUse !== false ? '<font color="red">' . $sErrorMaxUse . '</font>' : 'Déjalo en blanco para usos ilimitados' ) . '</span>'; ?></div>
										<div class="clear"></div>
									</div>
									<div class="formRow">
										<div class="grid2"><label>Pedido mínimo:</label></div>
										<div class="grid2"><?php echo tep_draw_input_field( 'coupons_min_order', strval($sMinOrder) ) . '<span class="note">' . ( $sErrorMinOrder !== false ? '<font color="red">' . $sErrorMinOrder . '</font>' : 'Déjalo en blanco para no especificar un pedido mínimo' ) . '</span>'; ?></div>
										<div class="grid1"><label>sobre el/la</label></div>
										<div class="grid3"><?php echo tep_draw_pull_down_menu( 'coupons_min_order_type', array( array( 'id' => 'price', 'text' => 'Precio total (€)' ), array( 'id' => 'quantity', 'text' => 'Cantidad de productos (unidades)' ) ), $sMinOrderType ) . ( $sErrorMinOrderType !== false ? '<span class="note">' . $sErrorMinOrderType . '</span>' : '' ); ?></div>
										<div class="clear"></div>
									</div>
									<div class="formRow">
										<div class="grid2"><label>Cantidad de cupones:</label></div>
										<div class="grid2"><?php echo tep_draw_input_field( 'coupons_number_available', strval($sNumberAvailable) ) . '<span class="note">' . ( $sErrorNumberAvailable !== false ? '<font color="red">' . $sErrorNumberAvailable . '</font>' : 'Déjalo en blanco para usos ilimitados' ) . '</span>'; ?></div>
										<div class="clear"></div>
									</div>
								</div>
							</div>
						</td>
					</tr>
					<!-- FIN CUPÓN -->

					<!-- FILTRO CATEGORIAS -->
					<tr data-id="2" style="display: none;" class="tab-new">
						<td style="display:block;">
							<div class="fluid grid">
								<div class="box-tbl grid12">
									<div class="box-head">
										<h6>Incluir solo las categorías</h6>
										<div class="clear"></div>
									</div>

									<div class="formRow">
										<div class="grid2"><label>Buscar categorías:</label></div>
										<div class="grid10">
											<img class="img-load" src="images/loading_small.gif" />
											<input type="text" id="categories_search" value="" name="categories_search"><span class="note">Escribe el nombre de la categoría que quieres añadir</span>
										</div>
										<div class="clear"></div>
									</div>
								</div>
							</div>

							<div class="fluid grid">
								<div class="box-tbl grid6">
									<div class="box-head">
										<h6>Categorías</h6>
										<div class="clear"></div>
									</div>

									<div class="formRow" id="campos-cat">
										<ul id="rows-drag-cat">
										</ul>
									</div>
								</div>

								<div class="box-tbl grid6">
									<div class="box-head">
										<h6>Categorias que se añadirán</h6>
										<div class="clear"></div>
									</div>
									<div class="formRow" id="drop-rows-cntd-cat">
										<ul id="drop-rows-cat" class="ui-droppable ui-sortable <?php echo (count( $aFilters['categories'] ) > 0 ? '' : 'drop-empty'); ?>">
										<?php
											foreach( $aFilters['categories'] as $aElement )
												echo '<li data-id="' . $aElement['id'] . '" data-drop="true">' . $aElement['name'] . '<input type="hidden" name="row-cat[]" value="' . $aElement['id'] . '"></li>';
										?>
										</ul>
									</div>
								</div>
							</div>
						</td>
					</tr>
					<!-- FIN FILTRO CATEGORIAS -->

					<!-- FILTRO PRODUCTOS -->
					<tr data-id="3" style="display: none;" class="tab-new">
						<td style="display:block;">
							<div class="fluid grid">
								<div class="box-tbl grid12">
									<div class="box-head">
										<h6>Incluir solo los productos</h6>
										<div class="clear"></div>
									</div>

									<div class="formRow">
										<div class="grid2"><label>Buscar productos:</label></div>
										<div class="grid10">
											<img class="img-load" src="images/loading_small.gif" />
											<input type="text" id="products_search" value="" name="products_search"><span class="note">Escribe el nombre del producto que quieres añadir</span>
										</div>
										<div class="clear"></div>
									</div>
								</div>
							</div>

							<div class="fluid grid">
								<div class="box-tbl grid6">
									<div class="box-head">
										<h6>Productos</h6>
										<div class="clear"></div>
									</div>

									<div class="formRow" id="campos-prd">
										<ul id="rows-drag-prd">
										</ul>
									</div>
								</div>

								<div class="box-tbl grid6">
									<div class="box-head">
										<h6>Productos que se añadirán</h6>
										<div class="clear"></div>
									</div>
									<div class="formRow" id="drop-rows-cntd-prd">
										<ul id="drop-rows-prd" class="ui-droppable ui-sortable <?php echo (count( $aFilters['products'] ) > 0 ? '' : 'drop-empty'); ?>">
											<?php
												foreach( $aFilters['products'] as $aElement )
													echo '<li data-id="' . $aElement['id'] . '" data-drop="true">' . $aElement['name'] . '<input type="hidden" name="row-prd[]" value="' . $aElement['id'] . '"></li>';
											?>
										</ul>
									</div>
								</div>
							</div>
						</td>
					</tr>
					<!-- FIN FILTRO PRODUCTOS -->

					<!-- FILTRO FABRICANTES -->
					<tr data-id="4" style="display: none;" class="tab-new">
						<td style="display:block;">
							<div class="fluid grid">
								<div class="box-tbl grid12">
									<div class="box-head">
										<h6>Incluir solo los fabricantes</h6>
										<div class="clear"></div>
									</div>

									<div class="formRow">
										<div class="grid2"><label>Buscar fabricantes:</label></div>
										<div class="grid10">
											<img class="img-load" src="images/loading_small.gif" />
											<input type="text" id="manufacturers_search" value="" name="manufacturers_search"><span class="note">Escribe el nombre del fabricante que quieres añadir</span>
										</div>
										<div class="clear"></div>
									</div>
								</div>
							</div>

							<div class="fluid grid">
								<div class="box-tbl grid6">
									<div class="box-head">
										<h6>Fabricantes</h6>
										<div class="clear"></div>
									</div>

									<div class="formRow" id="campos-mnf">
										<ul id="rows-drag-mnf">
										</ul>
									</div>
								</div>

								<div class="box-tbl grid6">
									<div class="box-head">
										<h6>Fabricantes que se añadirán</h6>
										<div class="clear"></div>
									</div>
									<div class="formRow" id="drop-rows-cntd-mnf">
										<ul id="drop-rows-mnf" class="ui-droppable ui-sortable <?php echo (count( $aFilters['manufacturers'] ) > 0 ? '' : 'drop-empty'); ?>">
										<?php
											foreach( $aFilters['manufacturers'] as $aElement )
												echo '<li data-id="' . $aElement['id'] . '" data-drop="true">' . $aElement['name'] . '<input type="hidden" name="row-mnf[]" value="' . $aElement['id'] . '"></li>';
										?>
										</ul>
									</div>
								</div>
							</div>
						</td>
					</tr>
					<!-- FIN FILTRO FABRICANTES -->

					<!-- FILTRO CLIENTES -->
					<tr data-id="5" style="display: none;" class="tab-new">
						<td style="display:block;">
							<div class="fluid grid">
								<div class="box-tbl grid12">
									<div class="box-head">
										<h6>Incluir solo los clientes</h6>
										<div class="clear"></div>
									</div>

									<div class="formRow">
										<div class="grid2"><label>Buscar clientes:</label></div>
										<div class="grid10">
											<img class="img-load" src="images/loading_small.gif" />
											<input type="text" id="customers_search" value="" name="customers_search"><span class="note">Escribe el nombre del cliente que quieres añadir</span>
										</div>
										<div class="clear"></div>
									</div>
								</div>
							</div>

							<div class="fluid grid">
								<div class="box-tbl grid6">
									<div class="box-head">
										<h6>Clientes</h6>
										<div class="clear"></div>
									</div>

									<div class="formRow" id="campos-ctm">
										<ul id="rows-drag-ctm">
										</ul>
									</div>
								</div>

								<div class="box-tbl grid6">
									<div class="box-head">
										<h6>Clientes que se añadirán</h6>
										<div class="clear"></div>
									</div>
									<div class="formRow" id="drop-rows-cntd-ctm">
										<ul id="drop-rows-ctm" class="ui-droppable ui-sortable <?php echo (count( $aFilters['customers'] ) > 0 ? '' : 'drop-empty'); ?>">
										<?php
											foreach( $aFilters['customers'] as $aElement )
												echo '<li data-id="' . $aElement['id'] . '" data-drop="true">' . $aElement['name'] . '<input type="hidden" name="row-ctm[]" value="' . $aElement['id'] . '"></li>';
										?>
										</ul>
									</div>
								</div>
							</div>
						</td>
					</tr>
					<!-- FIN FILTRO CLIENTES -->

					<!-- FILTRO GRUPO DE CLIENTES -->
					<tr data-id="6" style="display: none;" class="tab-new">
						<td style="display:block;">

							<div class="fluid grid">
								<div class="box-tbl grid6">
									<div class="box-head">
										<h6>Grupos de clientes</h6>
										<div class="clear"></div>
									</div>

									<div class="formRow" id="campos-grp">
										<ul id="rows-drag-grp">

										</ul>
									</div>
								</div>

								<div class="box-tbl grid6">
									<div class="box-head">
										<h6>Grupos de clientes que se añadirán</h6>
										<div class="clear"></div>
									</div>
									<div class="formRow" id="drop-rows-cntd-grp">
										<ul id="drop-rows-grp" class="ui-droppable ui-sortable <?php echo (count( $aFilters['customers'] ) > 0 ? '' : 'drop-empty'); ?>">
											<?php
											foreach( $aFilters['groups'] as $aElement )
												echo '<li data-id="' . $aElement['id'] . '" data-drop="true">' . $aElement['name'] . '<input type="hidden" name="row-grp[]" value="' . $aElement['id'] . '"></li>';
											?>
										</ul>
									</div>
								</div>
							</div>
						</td>
					</tr>
					<!-- FIN FILTRO GRUPO DE CLIENTES -->
				</tbody>
			</table>
		</form>
	</div>
</div>

<?php
	// Función para añadir los filtros al cupón
	function addFilter($sType, $aElements, $sCoupon)
	{
		// Vamos añadiendo los filtros
		if( isset( $aElements ) )
			foreach( $aElements as $aElement )
				tep_db_query( 'INSERT INTO discount_coupons_filters (coupons_id, filter_id, filter_type) VALUES ("' . $sCoupon . '", ' . $aElement . ', "' . $sType . '");' );
	}

	require(THEME . 'html/footer.php');
?>

<script type="text/javascript">
	// Variables
	var ajx = undefined;
	var tmSearch;

	// Cupones de descuento //
	if( $('#coupons_date_start') )
		$('#coupons_date_start').datepicker({ dateFormat: 'dd/mm/yy' });
	if( $('#coupons_date_end') )
		$('#coupons_date_end').datepicker({ dateFormat: 'dd/mm/yy' });
	// Cupones de descuento //

	// Buscador categorias
	if( $('#categories_search') ) droped_elements( 'categories_search', 'get_categories', 'cat' );
	// Buscador productos
	if( $('#products_search') ) droped_elements( 'products_search', 'get_products', 'prd' );
	// Buscador fabricantes
	if( $('#manufacturers_search') ) droped_elements( 'manufacturers_search', 'get_manufacturers', 'mnf' );
	// Buscador clientes
	if( $('#customers_search') ) droped_elements( 'customers_search', 'get_customers', 'ctm' );

	$(function() {
		droped_elements('grp', 'getGroups', 'grp')
	})

	// Refrescar cupones
	function refresh_coupon()
	{
		$.ajax({ url: 'coupons_create.php?action=refresh_coupon' }).done(function(v){ $('#coupon').val(v); });
	}

	// Overflow
	$("#cntd").css("overflow", "hidden");

	function getElementsForList(obj, action, prefix){
		// Mostramos loadding
		$("#" + obj).parent().find(".img-load").css("display", "block");

		// Borramos la llamada a funcion search
		clearTimeout( tmSearch );

		// Si estamos usando ajax abortamos
		if( ajx != undefined )
			ajx.abort();

		// Lanzamos la funcion search cuando pase X segundos
		tmSearch = setTimeout(function()
		{
			// Petición
			ajx = $.ajax({
					url: 'coupons_create.php?action=' + action + '&text=' + $('#' + obj).val(),
					cache: false
				}
			).done(function(v)
			{
				// Ocultamos loadding
				$("#" + obj).parent().find(".img-load").css("display", "none");

				// Cargamos html
				$('#rows-drag-' + prefix).html(v);

				// Lista de campos para hacer drag
				jQuery( "#rows-drag-" + prefix + " li" ).draggable({
					scroll: true,
					cursorAt: { top: 8, left: 8 },
					cursor: "move",
					cancel: '.drag-sltc',
					revert: function(bDrop)
					{
						if( bDrop )
						{
							return false;
						} else
						{
							jQuery(this).removeClass( "drag-sltc" );
							return true;
						}
					},
					start: function(e)
					{
						var dmElement = jQuery(e.currentTarget);
						dmElement.addClass("drag-sltc");
					},
					helper: function()
					{
						return $("<div style='width:" + $(this).width() + "px; z-index: 100;'></div>").append($(this).clone());
					}
				});

				jQuery("#drop-rows-" + prefix + " li").each(function(index,elmt)
				{
					if( jQuery("#rows-drag-" + prefix + " #" + jQuery(elmt).data("id")).length > 0 )
						jQuery("#rows-drag-" + prefix + " #" + jQuery(elmt).data("id")).addClass("drag-sltc");
				});
			} );
		}, 190);
	}
	// Drop admin
	function droped_elements(obj, action, prefix)
	{
		if(obj === 'grp'){
			console.log("AAAA")
			getElementsForList(obj, action, prefix)
		}
		$('#' + obj).keyup(function(e)
		{
			getElementsForList(obj, action, prefix)
		});

		// Zona drop para añadir campos
		$("#drop-rows-" + prefix).droppable(
		{
			cursorAt: { top: 8, left: 8 },
			greedy: true,
			drop: function(e, ui)
			{
				// Obtenemos el elemento
				var dmElement = $(ui.draggable);

				// Si el emento se hace drop asi mismo en el caso de ordenar la lista no se inserte de nuevo
				if( !dmElement.data( "drop" ) )
				{
					// Creamos el elemento
					var dmDiv = $( "<li/>",
					{
						"data-id": $(dmElement).attr("id"),
						"data-drop": "true",
						class: "",
						html: dmElement.text().replace("::", "") + '<input value="' + $(dmElement).attr("id") + '" type="hidden" name="row-' + prefix + '[]"/>'
					});

					// Insertamos
					dmDiv.appendTo( this );

					// Eliminamos la clase hover
					$(this).removeClass("drop-hovr");
				}

				// Mostramos el titular y añadimos el borde si no existen campos
				if( $(this).find("li").length >= 1 )
					$(this).removeClass("drop-empty");
			},
			over: function(e, ui )
			{
				$(this).addClass("drop-hovr");
			},
			out: function(e, ui )
			{
				$(this).removeClass("drop-hovr");
			}
		}).sortable(
		{
			cursor: "move",
			over: function(e, ui)
			{
				sortableIn = 1;
			},
			out: function(e, ui)
			{
				sortableIn = 0;
			},
			receive: function(e, ui)
			{
				sortableIn = 1;
			},
			beforeStop: function(e, ui)
			{
				if (sortableIn == 0)
				{
					var dmElement = ui.item;

					// Si estamos en la tabla devolvemos el campo
					if( $("#rows-drag-" + prefix + " #" + dmElement.data("id")).length > 0 )
						$("#rows-drag-" + prefix + " #" + dmElement.data("id")).removeClass("drag-sltc");

					// Eliminamos el elemento
					ui.item.remove();
				}
			},
			stop: function()
			{
				// Mostramos el titular y añadimos el borde si no existen campos
				if( $(this).find("li").length == 0 )
					$(this).addClass("drop-empty");
			}
		});
	}
</script>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
