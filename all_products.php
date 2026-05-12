<?php
	require('includes/application_top.php');

	// Si no es ajax mostramos todo. Esto se usa para la paginación mediante ajax
	if( ! isAjax() )
	{
		include(DIR_WS_LANGUAGES . $language . '/' . FILENAME_ALLPRODS);

		$breadcrumb->add(HEADING_TITLE, tep_href_link(FILENAME_ALLPRODS, '', 'NONSSL'));

		require(DIR_THEME. 'html/header.php');
		require(DIR_THEME. 'html/column_left.php');

		$sTitular = HEADING_TITLE;

		$sHtmlFiltro=
			'<ul class="abecedario">
			<li ' . ($_GET['fl'] == 'A' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=A', 'NONSSL') . '"> A </a></li>' .
			'<li ' . ($_GET['fl'] == 'B' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=B', 'NONSSL') . '"> B </a></li>' .
			'<li ' . ($_GET['fl'] == 'C' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=C', 'NONSSL') . '"> C </a></li>' .
			'<li ' . ($_GET['fl'] == 'D' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=D', 'NONSSL') . '"> D </a></li>' .
			'<li ' . ($_GET['fl'] == 'E' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=E', 'NONSSL') . '"> E </a></li>' .
			'<li ' . ($_GET['fl'] == 'F' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=F', 'NONSSL') . '"> F </a></li>' .
			'<li ' . ($_GET['fl'] == 'G' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=G', 'NONSSL') . '"> G </a></li>' .
			'<li ' . ($_GET['fl'] == 'H' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=H', 'NONSSL') . '"> H </a></li>' .
			'<li ' . ($_GET['fl'] == 'I' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=I', 'NONSSL') . '"> I </a></li>' .
			'<li ' . ($_GET['fl'] == 'J' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=J', 'NONSSL') . '"> J </a></li>' .
			'<li ' . ($_GET['fl'] == 'K' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=K', 'NONSSL') . '"> K </a></li>' .
			'<li ' . ($_GET['fl'] == 'L' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=L', 'NONSSL') . '"> L </a></li>' .
			'<li ' . ($_GET['fl'] == 'M' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=M', 'NONSSL') . '"> M </a></li>' .
			'<li ' . ($_GET['fl'] == 'N' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=N', 'NONSSL') . '"> N </a></li>' .
			'<li ' . ($_GET['fl'] == 'O' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=O', 'NONSSL') . '"> O </a></li>' .
			'<li ' . ($_GET['fl'] == 'P' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=P', 'NONSSL') . '"> P </a></li>' .
			'<li ' . ($_GET['fl'] == 'Q' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=Q', 'NONSSL') . '"> Q </a></li>' .
			'<li ' . ($_GET['fl'] == 'R' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=R', 'NONSSL') . '"> R </a></li>' .
			'<li ' . ($_GET['fl'] == 'S' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=S', 'NONSSL') . '"> S </a></li>' .
			'<li ' . ($_GET['fl'] == 'T' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=T', 'NONSSL') . '"> T </a></li>' .
			'<li ' . ($_GET['fl'] == 'U' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=U', 'NONSSL') . '"> U </a></li>' .
			'<li ' . ($_GET['fl'] == 'V' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=V', 'NONSSL') . '"> V </a></li>' .
			'<li ' . ($_GET['fl'] == 'W' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=W', 'NONSSL') . '"> W </a></li>' .
			'<li ' . ($_GET['fl'] == 'X' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=X', 'NONSSL') . '"> X </a></li>' .
			'<li ' . ($_GET['fl'] == 'Y' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=Y', 'NONSSL') . '"> Y </a></li>' .
			'<li ' . ($_GET['fl'] == 'Z' ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  'fl=Z', 'NONSSL') . '"> Z</a></li>'   .
			'<li ' . (!$_GET['fl'] ? 'class="actv"' : '') . '><a href="' . tep_href_link(FILENAME_ALLPRODS,  '',     'NONSSL') . '">'. HEADING_TITLE .'</a></li>
			</ul>';
	}

	$sSql = 'select p.products_id, p.products_ship_free, products_weight, p.products_quantity, p.products_model, pd.products_name, pd.products_description, p.products_image, p.products_price, p.products_tax_class_id, IF(s.status, s.specials_new_products_price, NULL) as specials_new_products_price, IF(s.status, s.specials_new_products_price, p.products_price) as final_price, p.products_date_added, m.manufacturers_name
			 from products p
			 left join manufacturers m on (p.manufacturers_id = m.manufacturers_id)
			 left join products_description pd on (p.products_id = pd.products_id)
			 left join specials s on (p.products_id = s.products_id)
			 where pd.products_name like "' . tep_db_prepare_input( $_GET['fl'] ) . '%" and p.products_status = 1 and pd.language_id = ' . $languages_id . '
			 order by pd.products_name';

	// Cambiamos el SQL si existe un filtro
	changeFilter( $sSql );

	// Obtenemos el paginador y los productos
	$aAux = changePriceCustomer( $sSql );
	$aProductos = $aAux['PRODUCTOS'];
	$aPaginador = $aAux['PAGE_PRODUCTOS'];
	$nProductosTotal = $aAux['TOTAL'];

	// Theme
	include( DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__) );

	// Liberamos
	unset( $aAux, $aProductos, $aPaginador, $nProductosTotal, $sSql, $sHtmlFiltro );

	// Si no es ajax mostramos todo. Esto se usa para la paginación mediante ajax
	if( ! isAjax() )
	{
		include( DIR_THEME. 'html/column_right.php' );
		include( DIR_THEME. 'html/footer.php' );
		include( DIR_WS_INCLUDES . 'application_bottom.php' );
	}
?>