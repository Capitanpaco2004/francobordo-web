<?php
	// Variables

/**
 * Note: This file may contain artifacts of previous malicious infection.
 * However, the dangerous code has been removed, and the file is now safe to use.
 */

	$gdImage        = null;
	$gdThumb        = null;
	$aImageInfo     = null;
	$sHttpProtocol  = null;
	$bCacheControl  = true;
	$sSprite        = 'theme/web/images/custom/sprite.png';
	$aInfoOferta    = array( 'WIDTH' => 47, 'HEIGHT' => 45, 'X_SPRITE' => 135, 'Y_SPRITE' => 676, 'X_THUMB' => 0, 'Y_THUMB' => 0 );
	$aInfoEnvio     = array( 'WIDTH' => 47, 'HEIGHT' => 34, 'X_SPRITE' => 294, 'Y_SPRITE' => 80, 'X_THUMB' => 119, 'Y_THUMB' => 125 );

	// Dado un tamaño de imagen y un tamaño maximo, tanto ancho como alto
	// escala las dimensiones si sobrepasan el maximo permitido
	function scaleSize($nWidth, $nHeight, $nWidthMax, $nHeightMax)
	{
		// Casts defensivos: si los máximos llegan vacíos (''), PHP 8 lanza
		// "Unsupported operand types: string / int". 0 = sin límite en ese eje.
		$nWidth     = (int)$nWidth;
		$nHeight    = (int)$nHeight;
		$nWidthMax  = (int)$nWidthMax;
		$nHeightMax = (int)$nHeightMax;

		// Si el alto supera lo permitido reducimos
		if( $nHeightMax > 0 && $nHeight > $nHeightMax )
		{
			$nWidth  = (int)( ( $nHeightMax / $nHeight ) * $nWidth );
			$nHeight = $nHeightMax;
		}

		// Si el ancho supera lo permitido reducimos
		if( $nWidthMax > 0 && $nWidth > $nWidthMax )
		{
			$nHeight = (int)( ( $nWidthMax / $nWidth ) * $nHeight);
			$nWidth  = $nWidthMax;
		}

		return array( 'WIDTH' => $nWidth, 'HEIGHT' => $nHeight );
	}

	// Si no tienes imagen
