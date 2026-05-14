<?php
	$nBanner = 1;
	while( $aBanner = tep_db_fetch_array( $aBanners ) )
	{
		echo '<a title="' . $aBanner['titulo'] . '" href="' . $aBanner['enlace'] . '">';
			echo tep_image( getImagenBannerDestacado( $aBanner['banner_destacados_id'], $languages_id, 'web' ), $aBanner['titulo'], 649,539, '', true, ($nBanner == 0 ? false :true) );
		echo '</a>';

		++$nBanner;
	}
?>
