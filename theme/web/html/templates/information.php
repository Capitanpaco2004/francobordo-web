<div class="information_contenido<?php echo ($classid == '' ? '  ax row' : ' fced'); ?>">
	<?php
		echo $page_description;

		if( $information['information_id'] == 23 )
		{
			$aLandings = tep_db_query(
				'SELECT *
				   FROM promotions l
				INNER JOIN ' . TABLE_LANDINGS_DESCRIPTION . ' ld
				      ON (l.promotion_id = ld.landing_id AND ld.language_id = ' . (int)$languages_id . ')
				  WHERE DATE(NOW()) >= DATE(l.promotion_start)
				    AND (DATE(NOW()) < DATE(l.promotion_end) OR l.promotion_end = "0000-00-00 00:00:00")
				    AND l.promotion_status = 1
				    AND l.promotion_banner = 1
			   ORDER BY l.promotion_id DESC'
			);


			while( $aLanding = tep_db_fetch_array( $aLandings ) )
			{
				echo '<a class="prmo" ' . (isAjax() ? ' target="_blank"' : '') . 'href="' . tep_href_link( FILENAME_LANDINGS, 'landing_id=' . (int)$aLanding['promotion_id'] . '&language=' . $language_code ) . '">';
					if( file_exists( DIR_WS_IMAGES . 'landings/' . $aLanding['landing_image'] ) )
						echo '<img src="' . DIR_WS_IMAGES . 'landings/' . $aLanding['landing_image'] . '" alt="' . $aLanding['landing_title'] . '" width="100%" />';
					else
						echo $aLanding['landing_title'];
				echo '</a>';
			}
		}
	?>
	<div class="clear"></div>
</div>