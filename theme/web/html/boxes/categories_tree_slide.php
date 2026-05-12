<div id="menu-cat" data-id="<?php echo $nIdCategoriaPrincipalWeb; ?>">
	<a class="titl" title="<?php echo $sCategoriaPrincipalWeb; ?>" href="<?php echo tep_href_link( FILENAME_CATEGORIES, 'cPath=' . $nIdCategoriaPrincipalWeb); ?>"><?php echo $sCategoriaPrincipalWeb; ?></a>
	<?php
		if( count( $aFabricantes ) > 0 )
		{
			echo '<form action="' . tep_href_link( FILENAME_MANUFACTURERS )	. '">';
				echo tep_draw_pull_down_menu( 'manufacturers_id', $aFabricantes, '', 'onchange="this.form.submit();"' );
			echo '</form>';
		}
	?>
	<ul>
		<?php
			// Variables
			$sHref = '';

			echo getSubCategoriesTreeSlide( $aCategorias, $nIdCategoriaPrincipalWeb );

			function getSubCategoriesTreeSlide($aCategorias, $nParent)
			{
				// Variables
				$sHTML = '';
				global $aCategoriasActivas;

				// Recorremos los elementos
				foreach( $aCategorias as $aCategoria )
				{
					// Si el padre coincide
					if( $aCategoria['parent_id'] == $nParent )
					{
						if( !is_array( $aCategoriasActivas ) )
							$aCategoriasActivas = array();
						
						// Agregamos a la lista
						$sHTML .= '<li>';
							$sHTML .= '<a ' . (in_array( $aCategoria['categories_id'], $aCategoriasActivas ) ? 'class="actv"': '') . ' href="' . tep_href_link( FILENAME_CATEGORIES, 'cPath=' . $aCategoria['categories_id'] ) . '" title="' . $aCategoria['categories_name'] . '">· ' . $aCategoria['categories_name'] . '</a>';
						
							// Buscamos hijos
							if( in_array( $aCategoria['categories_id'], $aCategoriasActivas ) )
								$sSubCategoria = getSubCategoriesTreeSlide( $aCategorias, $aCategoria['categories_id'] );

							// Pintamos sus hijos si tuviera
							if( $sSubCategoria != '' )
								$sHTML .= '<ul>' . $sSubCategoria . '</ul>';

							$sSubCategoria = '';
						
						$sHTML .= '</li>';
					}
				}

				return $sHTML;
			}
		?>
	</ul>
</div>

<div id="web-izqd-pred">
	<a target="_blank" href="http://www.aemet.es/es/eltiempo/prediccion/maritima" title="Predicción marítima" rel="nofollow" class="pred-mrti hovr2"></a>
	<a target="_blank" href="pdfdocs/pdfs_importantes/Equipo segun Zona de Navegacion.pdf" rel="nofollow" title="Equipo de seguridad" class="pred-sgrd hovr2"></a>
	<a href="<?php echo tep_href_link( 'information.php', 'info_id=8' ); ?>" rel="nofollow" class="pred-oblg hovr2"></a>
	<a target="_blank" href="descargas/NOTA_INFORMATIVA_1_2011_ACLARACIONES RD1435_2010.pdf" rel="nofollow" class="pred-equp hovr2"></a>
	<a href="<?php echo tep_href_link( 'information.php', 'info_id=7' ); ?>" rel="nofollow" class="pred-mtrc hovr2"></a>
	<a href="<?php echo tep_href_link( 'information.php', 'info_id=9' ); ?>" rel="nofollow" class="pred-titu hovr2"></a>
</div>