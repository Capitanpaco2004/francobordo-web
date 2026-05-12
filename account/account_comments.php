<?php
	// Aplicacion
	include( 'includes/application.php' );

	// Breadcrumb
	$breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_ACCOUNT_OPINION, '', 'SSL'));
	include( DIR_WS_LANGUAGES . $language . '/comentarios.php' );

	// Header
	include( 'account/includes/header.php' );

	// Consulta
	$sSql = 'select r.reviews_id, p.products_image, DATE_FORMAT(r.date_added, "%d/%m/%Y") as date_added, pd.products_name, r.approved, r.reviews_rating, rd.reviews_text
			from reviews r
			inner join products p on (p.products_id = r.products_id)
			inner join products_description pd on (p.products_id = pd.products_id)
			left join reviews_description rd on (r.reviews_id = rd.reviews_id and rd.languages_id = ' . $languages_id . ')
			where customers_id = "' . (int)$customer_id . '" and pd.language_id = "' . $languages_id . '"
			order by date_added DESC';

	$sSqlCount = 'select count(*) as total from ( ' . $sSql . ') as taux';

	$aDatosSlipt = new splitPageResults( $sSql, 25, '*', 'page', $sSqlCount );

	$aDatos = tep_db_query( $aDatosSlipt->sql_query );

	// Direcciones
	echo '<div class="ccTitle">' . ACCOUNT_OPINION_TITLE . ' (' . $aDatosSlipt->number_of_rows . ')</div>';

	if( tep_db_num_rows( $aDatos ) > 0 )
	{
		echo '<div class="ccCnt ccInfo6">';
			while( $aDato = tep_db_fetch_array( $aDatos ) )
			{
				echo '<div class="column">';
					echo '<div class="user">';
						echo tep_image( DIR_WS_IMAGES . 'productos/' . $aDato['products_image'], $aDato['products_name'], 139, 153, '', false );
					echo '</div>';
					echo '<strong class="date">' . $aDato['products_name'] . ' | ' . $aDato['date_added'] . '</strong>';
					echo '<div class="Rating">';
						echo '<span class="stars st' . $aDato['reviews_rating'] . '"><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i></span>';
					echo '</div>';
					echo '<div class="dscrp">' . $aDato['reviews_text'] . '</div>';

					if( $aDato['approved'] == 0 )
						echo '<br/>' . $messageStack->show( array( 'text' => ACCOUNT_OPINION_PENDIENTE, 'class' => 'wrng' ) );
				echo '</div>';
			}
		echo '</div>';

		// Paginacion
		echo '<div class="pgnc" style="width: 100%;">' . str_replace( basename( FILENAME_ACCOUNT_OPINION ), FILENAME_ACCOUNT_OPINION, $aDatosSlipt->display_links( MAX_DISPLAY_PAGE_LINKS, tep_get_all_get_params( array( 'page', 'info', 'x', 'y' ) ) ) ) . '</div>';
	}
	else
		echo $messageStack->show( array( 'text' => ACCOUNT_OPINION_NO, 'class' => 'eror' ) );



	// Footer
	include( 'account/includes/footer.php' );
?>