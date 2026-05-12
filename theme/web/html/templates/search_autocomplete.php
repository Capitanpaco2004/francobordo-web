<?php
	// Mostramos resumen
	echo '<div class="auto-prdt-row rsmn">';
		echo '<div class="auto-prdt-td td1">Productos</div>';
		echo '<div class="auto-prdt-td td2">Mostrando ' . $aPaginador->from . ' de ' . $aPaginador->number_of_rows . ' productos encontrados.</div>';
		echo '<div class="auto-prdt-td td3"><a href="javascript:void(0);" title="Ver todos" onclick="$(\'form-srch\').submit();">Ver todos</a></div>';
	echo '</div>';

	// Mostramos productos
	while( $aProducto = eachProducts() )
	{
		echo '<div class="auto-prdt-row rsmn-prdt" data-href="' . $aProducto['HREF'] . '">';
			echo '<div class="auto-prdt-td td1"><a title="' . $aProducto['TITLE'] . '" href="' . $aProducto['HREF'] . '">' . tep_image( DIR_WS_IMAGES . 'productos/' . $aProducto['products_image'], $aProducto['TITLE'], 50, 50, '', false, false, false ) . '</a></div>';
			echo '<div class="auto-prdt-td td2">';
				echo '<a title="' . $aProducto['TITLE'] . '" href="' . $aProducto['HREF'] . '">';
					echo '<strong>' . $aProducto['products_name'] . '</strong>';

					if( $aProducto['CLASS_OFERTA'] != '' )
						echo '<s>' . $aProducto['PRECIO_ANTERIOR'] . '</s>';

					echo '<span>' . $aProducto['PRECIO'] . '</span>';
				echo '</a>';
				echo '</div>';
			echo '<div class="auto-prdt-td td3"><a class="bton-dflt " href="' . $aProducto['HREF'] . '">' . ($aProducto['products_quantity'] <= -900 ? 'Agotado' : 'Comprar') . '</a></div>';
		echo '</div>';
	}

	// Mostramos categorias
	if( !empty( $aFilters['categories'] ) )
	{
		echo '<div class="auto-prdt-row extra">';
			echo '<div class="auto-prdt-td td1">Categorias</div>';
			echo '<div class="auto-prdt-td td2">';
				$sHtml = '';
				$nCont = 1;

				foreach( $aFilters['categories'] as $nID => $aFilter )
				{
					if( $nCont == 10 )
						break;

					$sHtml .= '<a href="' . tep_href_link( 'search.php', 'search=' . $_GET['search'] . '&categories=' . $nID ) . '" title="' . $aFilter['name'] . '">' . $aFilter['name'] . '</a>, ';

					$nCont++;
				}

				echo preg_replace( '/, $/', '', $sHtml );
			echo '</div>';
		echo '</div>';
	}

	// Mostramos fabricantes
	if( !empty( $aFilters['manufacturers'] ) )
	{
		echo '<div class="auto-prdt-row extra">';
			echo '<div class="auto-prdt-td td1">Marcas</div>';
			echo '<div class="auto-prdt-td td2">';
				$sHtml = '';
				$nCont = 1;

				foreach( $aFilters['manufacturers'] as $nID => $aFilter )
				{
					if( $nCont == 10 )
						break;

					$sHtml .= '<a href="' . tep_href_link( 'search.php', 'search=' . $_GET['search'] . '&manufacturers=' . $nID ) . '" title="' . $aFilter['name'] . '">' . $aFilter['name'] . '</a>, ';

					$nCont++;
				}

				echo preg_replace( '/, $/', '', $sHtml );
			echo '</div>';
		echo '</div>';
	}
?>