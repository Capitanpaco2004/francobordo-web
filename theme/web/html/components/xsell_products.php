<?php
	$sSell .= '<div class="wrpr-col">';
		$sSell .= '<div class="titu1 d-flex align-items-center small">' . TEXT_OTROS_ARTICULOS . '</div>';

		$sSell .= '<div class="d-flex prdt-sldr-cntd prdt-cntd">';
		while( $aProducto = eachProducts($aProductos) )
			$sSell .= _product( array( 'PRODUCTO' => $aProducto, 'CLASS' => ($aAux['TOTAL'] == 1 ? 'a12' : 'a06'), 'VISTA' => false ) );
		$sSell .= '</div>';
	$sSell .= '</div>';
?>