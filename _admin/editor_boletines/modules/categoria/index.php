<?php
	// Variables
	$sAction = tep_db_prepare_input( $_GET['a'] );

	switch( $sAction )
	{
		case 'select_categories':
			// Variables
			$aCategorias = json_decode( tep_db_prepare_input( $_POST['categorias'] ), true );

			// Incluimos el theme
			include( DIR_EDITOR_BOLETINES_THEME . 'email/' . $sThemeBoletin . '/theme.php' );
			
			// Recorremos las categorias
			foreach( $aCategorias as $sId => $aCategoria )
			{
				$aArgumentos = array(
					'titulo' => $aCategoria['value'],
					'imagen' => $aCategoria['parent'],
					'enlace' => preg_replace( '/(http\:)/i', 'https:', HTTP_SERVER ) . '/categories.php?cPath=' . $sId,
					'id' => $sId,
					'path' => $aCategoria['path']
				);

				// Si contiene imagen a true solo mostramos la imagen
				if( array_key_exists( 'imagen', $aCategoria ) && $aCategoria['imagen'] )
					echo theme_category_imagen( $aArgumentos );
				else
					echo theme_category( $aArgumentos );
			}

			exit();
		break;
	
		case 'search':
			// Variables
			$sBuscar = tep_db_prepare_input( $_GET['term'] );

			// Obtenemos las categorías según el texto enviado
			$aElements = tep_db_query( 'SELECT c.parent_id, c.categories_id, cd.categories_name
										FROM categories c
										INNER JOIN categories_description cd ON (c.categories_id = cd.categories_id)
										WHERE LCASE( cd.categories_name ) LIKE "%' . strtolower( $sBuscar ) . '%" AND cd.language_id = 3;' );

			// Función recursiva para obtener las categorias padre
			function getCategoriesByParent($nCategory, $sCategory)
			{
				// Obtenemos la categoria padre
				$aAux = tep_db_query( 'SELECT c.parent_id, cd.categories_name, c.categories_id FROM categories c INNER JOIN categories_description cd ON (c.categories_id = cd.categories_id) WHERE c.categories_id = ' . $nCategory . ' AND cd.language_id = 3;' );
				$aAux = tep_db_fetch_array( $aAux );
				$sIdCategoriaPadre = $aAux['categories_id'];

				// Nombre de categoria
				$sCategory = $aAux['categories_name'] . ' => ' . $sCategory;

				// Si tenemos padre
				if( $aAux['parent_id'] != 0 && $aAux['parent_id'] != '' )
				{
					$aAux2 = getCategoriesByParent( $aAux['parent_id'], $sCategory );
					$sCategory = $aAux2['text'];
					$sIdCategoriaPadre = $aAux2['parent_id'];
				}

				// Retornamos la categoria
				return array( 'text' => $sCategory, 'parent_id' => $sIdCategoriaPadre );
			}

			// Rellenamos de elementos
			$aReturn = array();
			while( $aElement = tep_db_fetch_array( $aElements ) )
			{
				$aAux = getCategoriesByParent( $aElement['parent_id'], $aElement['categories_name'] );
				
				if( $aAux['text'] == ' => ' . $aElement['categories_name'] )
				{
					$aAux['text'] = $aElement['categories_name'];
					$aAux['parent_id'] = $aElement['categories_id'];
				}
				
				$aReturn[] = array( 'id' => $aElement['categories_id'], 'id_parent' => $aAux['parent_id'], 'value' => $aAux['text'], 'name' => $aElement['categories_name'] );
			}
		
			echo json_encode( $aReturn );
			exit();
		break;
	}
?>

<?php if( $sAction == 'edit_categoria' ): ?>
	<div id="lgbox-izqd">
		<form id="form-categoria-edit" method="post" action="editor_boletines.php?m=<?php echo $sModule; ?>&a=select_categories">
			<input type="text" id="categoria" name="categoria" placeholder="Escribe el nombre de la categoría" style="margin-bottom: 216px;"/>
			<button class="bton bton-vrde" type="submit">Aceptar</button>
		</form>
	</div>
	<div id="lgbox-drch">
		<div class="box-info">
			<div class="icon"></div>
			Escribe el nuevo nombre que deseas para la categoría.
		</div>
	</div>
<?php else: ?>
	<div id="lgbox-izqd">
		<form id="form-categoria" method="post" action="editor_boletines.php?m=<?php echo $sModule; ?>&a=select_categories">
			<input type="text" id="categoria" name="categoria" placeholder="Escribe el nombre de la categoría"/>
			<div class="dual-list">
				<select style="height:331px;" class="multiple" multiple="multiple" id="box1View"></select>
						
				<div class="dualControl">
					<button class="bton bsmal" type="button" id="slct-dlte">Eliminar seleccionados</button>
					<button class="bton bsmal" type="button" style="float: right;" id="all-dlte">Eliminar todos</button>
				</div>
			</div>
			<button class="bton bton-vrde" type="submit">Aceptar</button>
		</form>
	</div>
	<div id="lgbox-drch">
		<div class="box-info">
			<div class="icon"></div>
			Escribe el nombre de la categoría a buscar. Esta te filtrara un listado de categorías disponibles, añade tantas como quieras.
		</div>
		<div class="box-info" style="margin-top: 20px;">
			<div class="icon"></div>
			Para seleccionar varias categorías en el listado pulsa la tecla ctrl más click en cada categoría que desees seleccionar.
		</div>
	</div>
<? endif; ?>

<script type="text/javascript" src="<?php echo DIR_EDITOR_BOLETINES . 'modules/' . $sModule; ?>/js/functions.js"></script>
<?php
	if( $sAction == 'edit_categoria' )
	{
		echo '<script type="text/javascript">
			editForm();
		</script>';
	}
?>