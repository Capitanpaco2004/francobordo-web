<?php
	if( $sSearch == '' || strlen($sSearch) <= 1 )
	{
		echo '<div class="web-cntd">' . $messageStack->show( array( 'class' => 'eror', 'text' => ERROR_AT_LEAST_ONE_INPUT ) ) . '</div>';
		return;
	}

	if( $nProductosTotal > 0 )
	{
		echo _getFiltro( array( 'EXTRA' => (isset($sHtmlFiltro) ? array( 'POSITION' => 'top', 'HTML' => $sHtmlFiltro ) : false) ) );
	
		echo '<div class="web-cntd prdt-cntd">';
			if( (!isset( $_GET['number'] ) || (isset( $_GET['number'] ) && $_GET['number'] == '')) && $aPaginador->current_page_number > 1 )
			{
				echo '<div class="col a12">';
					echo '<div id="less-prdt" class="blog-more" data-param="' . tep_get_all_get_params( array('page', 'info', 'x', 'y' ) )  . '" data-url="' . $_SERVER['SCRIPT_NAME'] . '" data-page="' . (isset( $_GET[$aPaginador->page_name] ) ? $_GET[$aPaginador->page_name] : 1) . '" data-maxpage="' . $aPaginador->number_of_pages . '">' . TEXT_VER_ANTER . '</div>';
				echo '</div>';
			}

			echo '<div class="contentScroll ax rows" data-url="' . tep_href_link( basename($PHP_SELF), '' . tep_get_all_get_params( array('type', 'info', 'x', 'y' ) ) ) . '">';
				while( $aProducto = eachProducts() )
					echo _product();
			echo '</div>';
			
			echo '<div class="col a12">';
				if( (!isset( $_GET['number'] ) || (isset( $_GET['number'] ) && $_GET['number'] == '')) && $aPaginador->number_of_rows > $aPaginador->from )
					echo '<div id="more-prdt" class="blog-more" data-param="' . tep_get_all_get_params( array('page', 'info', 'x', 'y' ) )  . '" data-url="' . $_SERVER['SCRIPT_NAME'] . '" data-page="' . (isset( $_GET[$aPaginador->page_name] ) ? $_GET[$aPaginador->page_name] : 1) . '" data-maxpage="' . $aPaginador->number_of_pages . '">' . TEXT_VER_MAS_PRODUCT . '</div>';

				echo '<div style="display: none">' . $aPaginador->display_links( MAX_DISPLAY_PAGE_LINKS, tep_get_all_get_params( array('page', 'info', 'x', 'y' ) ), '' ) . '</div>';
			echo '</div>';
		echo '</div>';
	}
	else
		echo '<div class="web-cntd">' . $messageStack->show( array( 'class' => 'wrng', 'text' => ERROR_NO_FOUND ) ); echo maybeYouWantedToSay() . '</div>';
?>