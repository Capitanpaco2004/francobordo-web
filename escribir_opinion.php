<?php
	// Libreria
    include( 'includes/application_top.php' );

	// Variables
	$nIdOpinion = tep_db_prepare_input( $_GET['o'] );
	$sNombreArchivo = basename(__FILE__);
	$aOpinones = null;
	$aOpinon = null;
	$nCampos = null;
	$nCont = null;
	$sError = '';
	$sForm = '';

	// Definiciones
	define( 'OPINION_YA_ESCRITA_ANTERIORMENTE', 1 );
	
	// Idioma
	require( DIR_WS_LANGUAGES . $language . '/' . $sNombreArchivo );

	// Breadcrumb
	$breadcrumb->add( ESCRIBIR_OPINION_BREADCRUMB, tep_href_link( $sNombreArchivo ) );

	// Comprobamos si nos envian un id de opinion
	if( $nIdOpinion == '' )
		tep_redirect( FILENAME_DEFAULT );

	# Inicio, comprobamos que nos pertenezca la opinion y que aun no lo hayamos escrito
	// Consulta para obtener el id de usuario y estado de la opinion
	$aOpinones = tep_db_query( 'select customers_id, orders_id, status
								from opinion where uniqid = "' . $nIdOpinion . '"' );

	// Comprobamos si existe opinion
	if( tep_db_num_rows( $aOpinones ) == 0 )
		tep_redirect( FILENAME_DEFAULT );

	// Cargamos la opinion
	$aOpinon = tep_db_fetch_array( $aOpinones );

	// Comprobamos que la opinion no haya sido escrita anteriormente
	// Si existe el mensaje de correcto es que acabamos de insertar nuestra opinion, no comprobamos su estado
	if( $aOpinon['status'] == 'true' && ! $messageStack->check( 'correcto' ) )
		$sError = OPINION_YA_ESCRITA_ANTERIORMENTE;
	# Fin, comprobamos que nos pertenezca la opinion y que aun no lo hayamos escrito


	# Inicio, creamos el form automatico
	$aCampos = tep_db_query( 'SHOW COLUMNS FROM opinion' );

	while( $aCampo = tep_db_fetch_array( $aCampos ) )
	{
        // Pasamos de los campos que no se mostraran en el formulario
		if( in_array( $aCampo['Field'], array( 'id_opinion', 'customers_id', 'orders_id', 'fecha_envio', 'email_primero_enviado', 'email_posterior_enviado', 'status', 'status_aprobado', 'uniqid' ) ) )
			continue;

		// Obtenemos el label
		@eval( '$sAux = ESCRIBIR_OPINION_LABEL_' . strtoupper( $aCampo['Field'] ) . ';' );

		// Obtenemos si tiene alguna nota el campo
		@eval( '$sNota = ESCRIBIR_OPINION_NOTA_' . strtoupper( $aCampo['Field'] ) . ';' );
		
		if( $sNota == 'ESCRIBIR_OPINION_NOTA_' . strtoupper( $aCampo['Field'] ) )
			$sNota = '';

		// Si no es un campo hidden creamos un bloque
		if( ! preg_match( '/^h_/i', $aCampo['Field'] ) )
			$sHtml .= '<div class="form-bopi">';

		// Comprobamos el tipo de dato
		switch( $aCampo['Type'] )
		{
			// Si es tipo entero mostramos estrellas
			case 'int(11)':
				$sHtml .= '<div class="form-star">
					<label class="form-lbel form-lbel-noblock">' . $sAux . '</label>
					<div class="xform-star" data-type="star" data-value="' . (array_key_exists($aCampo['Field'], $_POST) ? $_POST[$aCampo['Field']] : '') . '" data-name="' . $aCampo['Field'] . '"></div>
				</div>';
			break;
			
			// Si es tipo text y no empieza con "a_" de array, mostramos un textarea
			case $aCampo['Type'] == 'text' && (preg_match('/^a_/i', $aCampo['Field']) ? false : true):
				$sHtml .= '<label class="form-lbel" form="' . $aCampo['Field'] . '">' . $sAux . '</label>
				<div class="form-pddg">
					' . tep_draw_textarea_field( $aCampo['Field'], '', '0', '5' ) . '
				</div>';
			break;

			// Si es tipo text y  empieza con "a_" de array, mostramos checkbox
			case $aCampo['Type'] == 'text' && (preg_match('/^a_/i', $aCampo['Field']) ? true : false):
				$sHtml .= '<label class="form-lbel" form="' . $aCampo['Field'] . '">' . $sAux . '</label>';
				$sHtml .= '<input type="hidden" value="" name="' . $aCampo['Field'] . '[]"/>';
				
				// Recorremos su array
				@eval( '$aAuxs = ' . preg_replace( '/^a_/i', 'array_', $aCampo['Field'] ) . ';' );
				$aAuxs = explode( '[dxsepare]', $aAuxs );
				
				foreach( $aAuxs as $aAux )
					$sHtml .= '<input ' . ((key_exists($aCampo['Field'], $_POST) && in_array($aAux, $_POST[$aCampo['Field']])) ? 'checked="checked"' : '') . ' type="checkbox" name="' . $aCampo['Field'] . '[]" value="' . $aAux . '" />' . $aAux . '<br />';
			break;
			
			// Si es un tipo enum, creamos radio
			case (preg_match('/^enum\(/i', $aCampo['Type']) ? true : false):
				$aEnums = explode( "','", preg_replace( '/^enum\(\'|\'\)$/i', '', $aCampo['Type'] ) );

				$sHtml .= '<label class="form-lbel" form="' . $aCampo['Field'] . '">' . $sAux . '</label>';
				$sHtml .= '<input type="hidden" value="" name="' . $aCampo['Field'] . '"/>';

				foreach( $aEnums as $aEnum )
					$sHtml .= '<input ' . ((key_exists($aCampo['Field'], $_POST) && tep_db_prepare_input($_POST[$aCampo['Field']]) == $aEnum) ? 'checked="checked"' : '') . ' type="radio" name="' . $aCampo['Field'] . '" value="' . $aEnum . '" />' . $aEnum . '<br />';
			break;
			
			// Si es un tipo varchar y es hidden
			case preg_match( '/^h_/i', $aCampo['Field'] ) && preg_match('/^varchar\(/i', $aCampo['Type']):
				$sHtml = preg_replace( '/<\/div>$/i', '<div class="form-pddg" style="display:none;">
					' . tep_draw_input_field( $aCampo['Field'], tep_db_prepare_input( $_POST[$aCampo['Field']] ), 'size="255"' ) . '
				</div>', $sHtml );
			break;
		}
		
		$sHtml .= ($sNota ? '<div class="form-nota">' . $sNota . '</div>' : '' );
		
		$sHtml .= '</div>';
    }
	# Fin, creamos el form automatico

	# Inicio, hemos obtenido respuesta
	if( $_POST )
	{	
		// Comprobamos si existen errores
		foreach( $_POST as $key => $value )
		{
			// Limpiamos lo que viene por POST
			$value = tep_db_prepare_input( $value );
			
			// Obtenemos si el campo tiene error
			@eval( '$sAux = ESCRIBIR_OPINION_ERROR_' . strtoupper( $key ) . ';' );
			if( $sAux == 'ESCRIBIR_OPINION_ERROR_' . strtoupper( $key ) )
				$sAux = '';

			// Si contiene error y esta vacio el campo
			if( $sAux != '' && $value == '' )
				$sError .= $sAux;
		}
	
		// Si no hemos tenido errores insertamos
		if( $sError == '' )
		{
			$aSql = array();

			// Recreamos la consulta por los campos POST
			foreach( $_POST as $key => $value )
			{
				if( preg_match( '/^a_/i', $key ) )
					$value =  preg_replace( '/^,/i', '', implode( '[dxsepare]', tep_db_prepare_input( $value ) ) );

				$aSql[$key] = tep_db_prepare_input( $value );
			}

			// Añadimos status
			$aSql['status'] = 'true';

			// Actualizamos la opinion
			tep_db_perform( 'opinion', $aSql, 'update', 'uniqid = "' . $nIdOpinion . '"' );

			// Mensaje y redireccionamos
			$messageStack->add( 'correcto', ESCRIBIR_OPINION_EXITO, 'success' );
			// tep_redirect( $sNombreArchivo . '?o=' . $nIdOpinion );
		}
	}
	# Fin, hemos obtenido respuesta
	
	// Cabecera y columna
	require(DIR_THEME. 'html/header.php');
	require(DIR_THEME. 'html/column_left.php');

	// Theme
	include( DIR_THEME_ROOT . 'html/templates/' . $sNombreArchivo );

	// Pie
	include( DIR_THEME. 'html/column_right.php' );
	include( DIR_THEME. 'html/footer.php' );

	// Libreria
	include( DIR_WS_INCLUDES . 'application_bottom.php' );
?>