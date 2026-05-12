<div id="web-izqd-bner">
	<?php
		if( $banner = tep_banner_exists( 'dynamic', 'left-1' ) )
			echo tep_display_banner( 'static', $banner );
			
		if( $banner = tep_banner_exists( 'dynamic', 'left-2' ) )
			echo tep_display_banner( 'static', $banner );
			
		if( $banner = tep_banner_exists( 'dynamic', 'left-3' ) )
			echo tep_display_banner( 'static', $banner );
			
		if( $banner = tep_banner_exists( 'dynamic', 'left-4' ) )
			echo tep_display_banner( 'static', $banner );
	?>
</div>