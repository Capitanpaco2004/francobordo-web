<?php

use util\event;
$auxProducts = array();
$sResult = $sResult ?? '';
$sTitular = $sTitular ?? '';

if( $nProductosTotal == 0 && $nCategoriasTotal > 0 )
		{
			echo '<div class="web-cntd">';
				echo '<div id="ctgrs" class="ax rows">';
				foreach( $_aAllCategorias[$current_category_id] as $aCategoria )
				{
					echo '<a title="' . $aCategoria['categories_name'] . '" href="' . tep_href_link( 'categories.php', 'cPath=' . $aCategoria['categories_id'] ) . '" class="col a03 t04 m12">';
						$sImagenCategoria = getImagenCategoria( $aCategoria['categories_image'], 'categoria', '', false );

						echo '<span class="imge">' . tep_image( DIR_WS_IMAGES . 'categorias/' . ($sImagenCategoria && file_exists( DIR_WS_IMAGES . 'categorias/' . $sImagenCategoria ) ? $sImagenCategoria : 'no-image.png'), $aCategoria['categories_name'], 200, 100, '', false ) . '</span>';
						echo '<span class="titu">' . $aCategoria['categories_name'] . '</span>';
					echo '</a>';
				}
				echo '</div>';

				include( DIR_WS_COMPONENTS . 'featured.php' );
			echo '</div>';
		}
		elseif( $nProductosTotal > 0 )
		{
			if( ! isset( $_GET['type'] ) || $_GET['type'] != 'json' )
			{
				echo _getFiltro();
				include(DIR_WS_MODULES . 'products_filter.php');

				echo '<div class="web-cntd prdt-cntd">';
			}

			$sResult .= '<div class="contentScroll ax rows" data-url="' . tep_href_link( basename($PHP_SELF), '' . tep_get_all_get_params( array('type', 'info', 'x', 'y' ) ) ) . '" data-pagination="' . htmlentities( $aPaginador->display_links( 99999, tep_get_all_get_params( array('page', 'type', 'info', 'x', 'y' ) ) ) ) . '">';
				while( $aProducto = eachProducts() ) {
					$auxProducts[] = $aProducto;
					$sResult .= _product();
				}
			$sResult .= '</div>';

			if( ! isset( $_GET['type'] ) || $_GET['type'] != 'json' )
				echo $sResult . '<div class="PageNav" style="display: none;"><div class="BX Row HdSm"><div class="NumPro"><strong>' . $aPaginador->number_of_rows . '</strong> 350 <strong>' . $sTitular . '</strong></div></div><div class="Nav">' . $aPaginador->display_links( MAX_DISPLAY_PAGE_LINKS, tep_get_all_get_params( array('page', 'info', 'x', 'y' ) ) ) . '</div></div>';
			else
				echo json_encode( array( 'next_data_url' => ($sNextUrl != '' ? preg_replace( '/https\:\/\/www.francobordo\.com/i', '', $sNextUrl ) : ''), 'prev_data_url' => ($sPrevUrl != '' ? preg_replace( '/https\:\/\/www.francobordo\.com/i', '', $sPrevUrl ) : ''), 'current_url' => tep_href_link( basename($PHP_SELF), '' . tep_get_all_get_params( array('type', 'info', 'x', 'y' ) ) ), 'next_url' => preg_replace( '/\&type\=json/i', '', $sNextUrl ), 'prev_url' => preg_replace( '/\&type\=json/i', '', $sPrevUrl ), 'response' => preg_replace( '/https\:\/\/www.francobordo\.com/i', '', $sResult ) ), JSON_FORCE_OBJECT );

			if( ! isset( $_GET['type'] ) || $_GET['type'] != 'json' ) {
				echo '</div>';
			}
		}
		elseif( $nCategoriasTotal == 0 )
			echo '<div class="mensaje web-cntd">' . FILTRO_NO_EXISTEN . '</div>';

if( ! isset( $_GET['type'] ) || $_GET['type'] != 'json' )
{
	event::getInstance()->execute('after_products_listing', array($auxProducts));
}
?>
