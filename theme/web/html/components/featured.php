<?php
	echo '<div class="prdt-bner d-flex none">';
		echo '<div class="web-cntd">';
			echo '<div class="wrpr flex-grow-1">';
				echo '<div class="titu1 d-flex align-items-center">';
					echo '<div class="titu">' . TEXT_DESTACADOS_EN . ' <span class="cl">' . $_aAllDatos[$current_category_id]['categories_name'] . '</span></div>';
					echo '<a class="stitu ml-auto" href="' . tep_href_link( 'products_featured.php', 'c=' . $current_category_id ) . '" title="' . TEXT_VER_DESTACADOS . $_aAllDatos[$current_category_id]['categories_name'] . '">' . TEXT_VER_DESTACADOS2 . '</a>';
					echo '<div class="xarrow d-flex"></div>';
				echo '</div>';

				echo '<div class="prdt-sldr-cntd d-flex" data-column="3">';
					while( $aProducto = eachProducts() )
						echo _product( array( 'VISTA' => false ) );
				echo '</div>';
			echo '</div>';
		echo '</div>';
	echo '</div>';
?>