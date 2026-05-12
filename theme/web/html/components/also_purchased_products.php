<?php
	$sAlso .= '<div class="wrpr-col">';
		$sAlso .= '<div class="titu1 d-flex align-items-center small">' . TEXT_CLIENTES_COMPRARON . '</div>';
	
		$sAlso .= '<div class="d-flex prdt-sldr-cntd prdt-cntd">';
		while( $aProducto = eachProducts($aProductos) )
			$sAlso .= _product( array( 'PRODUCTO' => $aProducto, 'CLASS' => ($aAux['TOTAL'] == 1 ? 'a12' : 'a06'), 'VISTA' => false ) );
		$sAlso .= '</div>';
	$sAlso .= '</div>';
?>