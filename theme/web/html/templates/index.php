<?php
	echo '<div id="banrs-home" class="web-cntd">';
		echo '<div class="d-flex">';
			echo '<div id="home-slde" class="box-1">';
				include( DIR_WS_COMPONENTS . 'banners_destacados.php' );
			echo '</div>';
			echo '<div class="box-2 d-flex flex-column">';
				echo '<div class="box-2-1 d-flex">';
					echo '<div class="box-2-1-1">';
						if( $banner = tep_banner_exists( 'dynamic', 'idx1' ) )
							echo tep_display_banner( 'static', $banner );
					echo '</div>';
					echo '<div class="box-2-1-2 d-flex flex-column">';
						echo '<div class="box-2-2-1">';
							if( $banner = tep_banner_exists( 'dynamic', 'idx2' ) )
								echo tep_display_banner( 'static', $banner );
						echo '</div>';
						echo '<div class="box-2-2-2 mt-auto">';
							if( $banner = tep_banner_exists( 'dynamic', 'idx3' ) )
								echo tep_display_banner( 'static', $banner );
						echo '</div>';
					echo '</div>';
				echo '</div>';
				echo '<div class="box-2-2 mt-auto">';
					if( $banner = tep_banner_exists( 'dynamic', 'idx4' ) )
						echo tep_display_banner( 'static', $banner );
				echo '</div>';
			echo '</div>';
		echo '</div>';
	echo '</div>';

	// Ofertas y novedades
	include( DIR_WS_COMPONENTS . FILENAME_NEW_PRODUCTS );
	include( DIR_WS_COMPONENTS . FILENAME_SPECIALS );
include(DIR_WS_COMPONENTS . 'opinions.php');
	echo '<div id="home-text">';
		echo '<div class="web-cntd">';
			echo '<div class="wrpr">';
				echo '<img class="logo" src="theme/web/images/custom/4.png"/>';
				echo '<div class="text">' . getInformationByID( 29 ) . '</div>';
				if( $banner = tep_banner_exists( 'dynamic', 'idx5' ) )
					echo tep_display_banner( 'static', $banner, 'class="baner"' );
			echo '</div>';
			echo '<div class="flot flot-index"><div class="layer"></div>';
				if( $banner = tep_banner_exists( 'dynamic', 'idx6' ) )
					echo tep_display_banner( 'static', $banner, '', false );
			echo '</div>';
		echo '</div>';
	echo '</div>';
?>