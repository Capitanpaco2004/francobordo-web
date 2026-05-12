<?php
	echo '<div class="prdt-bner d-flex none">';
		echo '<div class="web-cntd">';
			echo '<div class="titleDescatalogados"><p>' . PRODUCTS_DESCATALOGADO . '</p></div>';

			if( !empty( $aProductsDescatalogado ) )
			{
				echo '<div class="wrpr flex-grow-1">';
					echo '<div class="titu1 d-flex align-items-center">';
						echo '<div class="titu">' . PRODUCTS_DESCATALOGADO_2 . '</div>';

						echo '<div class="xarrow d-flex"></div>';
					echo '</div>';
				
					echo '<div class="prdt-sldr-cntd d-flex" data-column="3">';
						$aProductoBackup = $aProductoInfo;

						while( $aProducto = eachProducts($aProductsDescatalogado) )
							echo _product( array( 'VISTA' => false ) );

						$aProductoInfo = $aProductoBackup;
					echo '</div>';
				echo '</div>';
			}
		echo '</div>';
	echo '</div>';
?>