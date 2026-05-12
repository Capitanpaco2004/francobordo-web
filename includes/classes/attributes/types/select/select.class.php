<?php
	class option_select
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
			global $currencies;
			$sHtmlOption = '';
			$nCont = 0;
			$sHtmlExtra = false;
			$sTemplateExtra = false;

			// Si es array es que tenemos html extra, el primer elemento sera el template basico y el 1 el extra
			if( is_array( $sPlantilla ) )
			{
				$sTemplateExtra = $sPlantilla[1];
				$sPlantilla = $sPlantilla[0];
			}

			// Recorremos los datos
			while( $aDato = tep_db_fetch_array( $aDatos ) )
			{
				// Variables
				$bSelected = false;

				// Obtenemos el valor que estara seleccionado
				if( array_key_exists( $aDatoOption['products_options_id'], $aOpcionesSelected ) && $aOpcionesSelected[$aDatoOption['products_options_id']] == $aDato['products_options_values_id'] )
					$bSelected = true;

				$sHtmlOption .= '<option ' . ($nCont == 0 ? '%REPLACE_FIRST_ELEMENT%' : '') . ($bSelected ? 'selected="selected"' : '') . ' data-reference="' . $aDato['reference'] . '" data-ean="' . $aDato['products_attributes_ean'] . '" value="' . $aDato['products_options_values_id'] . '">' . $aDato['products_options_values_name'] . '</option>';

				if( $sTemplateExtra != false )
					$sHtmlExtra .= str_replace( array( '%REPLACE_VALUE%' ), array( $aDato['products_options_values_name'] ), $sTemplateExtra );

				// Aumentamos
				$nCont++;
			}

			// Si no contenemos ningun valor seleccionado seleccionamos el primer valor por defecto
			$sHtmlOption = str_replace( '%REPLACE_FIRST_ELEMENT%', (!preg_match( '/selected="selected"/i', $sHtmlOption ) ? 'selected="selected"' : '' ), $sHtmlOption );

			// Retornamos
			return str_replace( array(
				'%REPLACE_VALUE_SELECT%',
				'%REPLACE_OPTION_NAME%',
				'%REPLACE_HTML_EXTRA%'
			),
			array(
				'<select' . ($aDatoOption['products_options_track_stock'] == 1 ? ' data-track="1" data-name="' . $aDatoOption['products_options_name'] . '" data-required="true"' : '') . ' name="id[' . $aDatoOption['products_options_id'] . ']" data-oid="' . $aDatoOption['products_options_id'] . '">' . $sHtmlOption . '</select>',
				$aDatoOption['products_options_name'],
				$sHtmlExtra
			), $sPlantilla );
		}

		// Método que verifica si se puede añadir valores a la opción
		public function getAllowValues()
		{
			return true;
		}
	}
?>
