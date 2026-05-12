<?php
	class option_text
	{
		public function __construct()
		{
		}


		//////////////////////
		// MÉTODOS PÚBLICOS //
		//////////////////////

		// Metodo que muestra en el frontend el html
		public function frontendGetHtml($aDatos, $aDatoOption, $sPlantilla, $aOpcionesSelected)
		{
			// Variables
			$sHtmlText = '';
			$sValue = '';

			// Obtenemos el dato
			$aDato = tep_db_fetch_array( $aDatos );

			if( array_key_exists( $aDatoOption['products_options_id'], $aOpcionesSelected ) && $aOpcionesSelected[$aDatoOption['products_options_id']] != 0 )
				$sValue = $aOpcionesSelected[$aDatoOption['products_options_id']];

			// Html text
			$sHtmlText = '<input data-oid="' . $aDatoOption['products_options_id'] . '" name="id[' . $aDatoOption['products_options_id'] . ']" type="text" value="' . $sValue . '" />';

			// Retornamos
			return str_replace( array(
				'%REPLACE_TEXT%',
				'%REPLACE_OPTION_NAME%'
			),
			array(
				$sHtmlText,
				$aDatoOption['products_options_name']
			), $sPlantilla );
		}

		// Método que verifica si se puede añadir valores a la opción
		public function getAllowValues()
		{
			return false;
		}
	}
?>