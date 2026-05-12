<?php
	$sOptional = $sOptional ?? '';
	if( count($aProductos) > 0 )
	{
		$sOptional .= '<div class="wrpr-col">';
			$sOptional .= '<div class="titu1 d-flex align-items-center small">' . TEXT_OPTIONAL_RELATED . '</div>';
		
			$sOptional .= '<div class="d-flex prdt-sldr-cntd prdt-cntd">';
			while( $aProducto = eachProducts() )
				$sOptional .= _product( array( 'CLASS' => 'a06', 'VISTA' => false ) );
			$sOptional .= '</div>';
		$sOptional .= '</div>';

		$aBlocks['optional'] = $sOptional;
	}
?>