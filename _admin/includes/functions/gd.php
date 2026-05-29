<?php
function imagettftextbox(&$gdImage, $sFontSize, $nAngle, $nLeft, $nTop, $aColor, $sFont, $sText, $nMaxWidth, $sAlign = '', $nSeparate = 3, $nHeight = false)
	{
		// Explotamos la cadena por saltos de linea para recorrerla
		$aLines = explode( "\n", $sText );

		// Arrray que guarda las lineas que vamos a crear y encho de cada una
		$aLinesCreate = array();
		$aLinesWidth = array();

		// Va guardando el
		$nAltoFijo = 0;
		$nWidthMayor = 0;

		foreach($aLines as $sLine)
		{   
			// Separamos la linea en palabras
			$aWords = explode(' ', $sLine);

			// Reseteamos
			$bFistWord = true;
			$nTotalWords = count($aWords);
			$nLastWidth = 0;

			// Variable que va guardando palabra a palabra para crear nuevas lineas si la palabra no entrara en el ancho
			$sCurrentLine = '';

			// Recorremos las palabras de la linea
			for( $nCont = 0; $nCont < $nTotalWords; $nCont++ )
			{
				// Guardamos la palabra
				$sWord = $aWords[$nCont];

				// Creamos la palabra junto a las demas palabra de la linea para ver la dimension que ocupa
				$aInfo = imagettfbbox( $sFontSize, $nAngle, $sFont, $sCurrentLine . ($bFistWord ? '' : ' ') . $sWord);
				
				// Creamos de nuevo la palabra pero esta vez en mayusculas para obtener el alto ya que los caracteres con "rabillo" como p, y, etc tieen problemas
				$aInfoAux = imagettfbbox( $sFontSize, $nAngle, $sFont, $sCurrentLine . ($bFistWord ? '' : ' ') . strtoupper( $sWord ) );
				// Replazamos el alto
				$aInfo[1] = $aInfoAux[1];
				$aInfo[7] = $aInfoAux[7];

				// Calculamos
				$nLineWidth = $aInfo[2] - $aInfo[0];
				$nLineHeight = $aInfo[1] - $aInfo[7] + $nSeparate;

				// Vamos a guardar el alto fijo mayor que tendra una de las filas creadas
				if( $nLineHeight > $nAltoFijo )
					$nAltoFijo = $nLineHeight;

				// Si el ancho es mayor guardamos
				if( $nLineWidth > $nWidthMayor )
					$nWidthMayor = $nLineWidth;
					
				// Si el ancho de la linea nueva creada excede el ancho permitido y no es la primera palabra de linea
				if( $nLineWidth >= $nMaxWidth && !$bFistWord )
				{
					// A�adimos al array de lineas nuevas la actual linea que aun no tiene concatenado la ultima palabra ya que no entra en el ancho maximo
					$aLinesCreate[] = $sCurrentLine;

					// Si contenemos el ancho anterior lo a�adimos si no sera el ancho que ocupe la linea
					$aLinesWidth[] = $nLastWidth ? $nLastWidth : $nLineWidth;

					// Como hemos excedido el maximo la nueva linea estara vacio y empezados por la palabra anterior
					$sCurrentLine = '';
					$nCont--;
					$bFistWord = true;
					continue;
				}
				// Si la palabra ha entrado en al linea actual la concatenamos junto a un espacio si no es la primera palabra
				else
					$sCurrentLine .= ($bFistWord ? '' : ' ') . $sWord;

				// Si hemos terminado de recorrer las palabras de la fila y aun no hemos guardado la fila, la guardamos
				if( $nCont == $nTotalWords - 1 )
				{
					$aLinesCreate[] = $sCurrentLine;
					$aLinesWidth[] = $nLineWidth;
				}

				// Guardamos el ancho actual para la proxima vuelta
				$nLastWidth = $nLineWidth;

				// Le decimos al sistema que la siguiente vuelta no es la primera palabra
				$bFistWord = false;
			}
		}
		
		// Decidimos el alto
		if( $nHeight !== false && $nHeight !== NULL)
			$nTop += ((60 / 2) - (($nAltoFijo * count($aLinesCreate)) / 2));
		
		// Recorremos las lineas que hemos creado
		foreach( $aLinesCreate as $key => $line )
		{
			switch($sAlign)
			{
				case 'center':
					$nMarginLeft = ($nMaxWidth - $aLinesWidth[$key]) / 2;
				break;

				case 'right':
					$nMarginLeft = ($nMaxWidth - $aLinesWidth[$key]);
				break;
				
				default:
					$nMarginLeft = 0;
				break;
			}
			
			// Debug fondo
			// $imDebug = @imagecreate( $aLinesWidth[$key], $nAltoFijo );
			// $imRelleno = imagecolorallocate( $imDebug, 0, 0, 0 );
			// imagefilledrectangle( $imDebug, 0, 0, $aLinesWidth[$key], $nAltoFijo, $imRelleno );
			// imagecopyresampled( $gdImage, $imDebug, $nLeft + $nMarginLeft, $nTop + ($nAltoFijo * $key), 0, 0,$aLinesWidth[$key], $nAltoFijo, $aLinesWidth[$key], $nAltoFijo );
			
			imagettftext( $gdImage, $sFontSize, $nAngle, (int) ($nLeft + $nMarginLeft), (int) ($nTop + $nAltoFijo + ($nAltoFijo * $key)), imagecolorallocate($gdImage, (int)$aColor[0], (int)$aColor[1], (int)$aColor[2] ), $sFont, $line );
		}
		
		return array(
			'width' => $nWidthMayor,
			'height' => $nAltoFijo * count($aLinesCreate)
		);
	}
?>