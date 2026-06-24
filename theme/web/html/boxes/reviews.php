<div class="box b3">
    <div class="box-top"><?php echo BOX_HEADING_REVIEWS; ?></div>
    <div class="box-cntd box-revw">
		<?php
			// Si hemos encontrado datos mostramos
			if( tep_db_num_rows( $aDatos ) > 0 )
			{
				while( $aDato = tep_db_fetch_array( $aDatos ) )
				{
					// 2026-06-23: product_reviews_info.php eliminado (404) -> enlazamos a la ficha del producto.
					echo '<a href="' . tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $aDato['products_id']) . '">'
						. tep_image(DIR_WS_IMAGES . $aDato['products_image'], $aDato['products_name'], 110, 122) . 
					'</a>';

					echo '<p>' . truncate( $aDato['reviews_text'], array( 'SIZE' => 60, 'CLEAR' => true ) ) . '</p>';

					echo tep_image( DIR_WS_IMAGES . 'stars_' . $aDato['reviews_rating'] . '.gif' , sprintf(BOX_REVIEWS_TEXT_OF_5_STARS, $aDato['reviews_rating']) );
				}
			}
			// Si no encontramos datos pero estamos viendo un producto mostramos la opcion de escribir comentario
			// 2026-06-23: product_reviews_write.php eliminado (404) -> apuntamos a la ficha del producto, cuya
			// pestaña Comentarios contiene el formulario AJAX que SI funciona (escribir_comentarios.php).
			elseif( isset($_GET['products_id'] ) )
				echo '<div class="reviews_box"><a href="' . tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $_GET['products_id']) . '" class="escribir_comentario">' . tep_image(DIR_WS_IMAGES . 'box_write_review.png', IMAGE_BUTTON_WRITE_REVIEW) . '</a> <a href="' . tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $_GET['products_id']) . '">' . BOX_REVIEWS_WRITE_REVIEW .'</a></div>';
		?>  
    </div>
    <div class="box-fotr"></div>
</div>