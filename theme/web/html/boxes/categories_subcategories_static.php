<?php

	// Recorremos las categorias padres
	foreach( $aCategoriesParent as $key => $value )
	{
		// Creamos box principal
		echo '<div class="box">';

			// Pintamos la cabecera que sera el titulo de la categoria padre
			echo '<div class="box_cabecera">' . $value . '</div>';
			
			// Si contiene subcategorias
			if( array_key_exists( $key, $aSubCategories ) )
			{
				// Creamos la lista
				echo '<ul>';

				// Recorremos las subcategorias
				foreach( $aSubCategories[$key] as $aSubCategorie )
					echo '<li ' . ($cPath == $key . '_' . $aSubCategorie['ID'] ? 'class="actv"' : '') . '><a href="' . tep_href_link( FILENAME_DEFAULT, 'cPath=' . $key . '_' . $aSubCategorie['ID'] ) . '" title="' . $aSubCategorie['TEXT'] . '"></a>' . $aSubCategorie['TEXT'] . '</li>';

				// Cerramos la lista
				echo '</ul>';
			}
		
		// Cerramos box pricipal
		echo '</div>';
	}

?>