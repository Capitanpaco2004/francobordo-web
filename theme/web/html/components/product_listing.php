<?php
	// Si es una peticon ajax mostramos solo los productos. Esto se usa para la paginación mediante ajax
	if( isAjax() )
	{
		echo '<div>';
			foreach( $aProductos as $nCont => $aProducto )
				echo _product( array( 'SIZE_DESCRIPTION' => 250 ) );
		echo '</div>';

		return;
	}
?>

<h1 class="pageHeading">
	<?php echo $sTitular; ?>
	<a class="<?php echo (!empty($_SESSION['vista']) && $_SESSION['vista'] == 'chng-vsta-hrzt' ? 'chng-vsta-hrzt' : 'chng-vsta-vrtl'); ?>" href="javascript:void(0);" id="chng-vsta">Cambiar vista</a>
</h1>

<?php if( $nProductosTotal > 0 ): ?>
	<?php echo _getFiltro( array( 'EXTRA' => (isset($sHtmlFiltro) ? array( 'POSITION' => 'top', 'HTML' => $sHtmlFiltro ) : false) ) );  ?>

	<?php foreach( $aProductos as $nCont => $aProducto ): ?>
		<?php echo _product( array( 'DESCRIPTION_SIZE' => 250 ) ); ?>
	<?php endforeach; ?>

	<?php if( tep_db_prepare_input( $_GET['numero'] ) != '*' ):  ?>
		<div class="pgnc">
			<?php echo $aPaginador->display_links( MAX_DISPLAY_PAGE_LINKS, tep_get_all_get_params( array('page', 'info', 'x', 'y' ) ) ); ?>
		</div>
	<?php else: ?>
		<?php echo $aPaginador->ajax(); ?>
	<?php endif; ?>
<?php else: ?>
	<div class="mensaje">No existen productos que correspondan con el filtro seleccionado.</div>
<?php endif; ?>