<ul id="cbcr-menu" class="cntd-cntr" data-responsive="false">
	<li class="home"><a title="Francobordo" href="<?php echo tep_href_link(FILENAME_DEFAULT); ?>"><span class="icon"></span> </a></li><?php
		foreach( $aCategorias as $aCategoria )
		{
			echo '<li data-id="' . $aCategoria['categories_id'] . '">';
				echo '<a href="' . tep_href_link( 'categories.php', 'cPath=' . $aCategoria['categories_id'] ) . '" title="' . $aCategoria['categories_name'] . '">';
					echo '<span class="icon"></span>';
					echo $aCategoria['categories_name'];
				echo '</a>';
				
				if( tep_db_num_rows( $aCategoria['subcategorias'] ) )
				{
					echo '<div class="cntd">';
						echo '<ul>';
							while( $aDato = tep_db_fetch_array( $aCategoria['subcategorias'] ) )
								echo '<li><a href="' . tep_href_link( 'categories.php', 'cPath=' . $aDato['categories_id'] ) . '" title="' . $aDato['categories_name'] . '">· ' . $aDato['categories_name'] . '</a></li>';
						echo '</ul>';
					echo '</div>';
				}
				
			echo '</li>';
		}
	?>
</ul>