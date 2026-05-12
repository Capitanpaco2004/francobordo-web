<?php if( ! isAjax() ): ?>
	<h1 class="pageHeading"><?php echo ADVANCED_SEARCH_TITLE; ?></h1>

	<form id="asrch" <?php echo ($sTags != '' ? 'style="margin-bottom:0px;"' : ''); ?> method="get" action="<?php echo tep_href_link( FILENAME_ADVANCED_SEARCH_RESULT ); ?>">
		<input type="submit" style="visibility:hidden;"/>
		<div id="asrch-order">
			<?php echo ADVANCED_SEARCH_FILTRO_ORDENAR_POR; ?> <?php echo tep_draw_pull_down_menu( 'order', $aOrders, $sOrden, 'id="order" onChange="this.form.submit();"' ); ?>
		</div>
		
		<div id="asrch-vsta">
			<a class="<?php echo (!empty($_SESSION['vista']) && $_SESSION['vista'] == 'chng-vsta-hrzt' ? 'chng-vsta-hrzt' : 'chng-vsta-vrtl'); ?>" href="javascript:void(0);"><?php echo ADVANCED_SEARCH_FILTRO_VISTA; ?></a>
		</div>
		
		<div id="asrch-advc">
			<a id="asrch-advc-achr" class="asrch-advc-achr" href="javascript:void(0);"><?php echo ADVANCED_SEARCH_FILTRO_CAMBIAR_RESULTADO; ?></a>
			<div id="asrch-advc-box">
				<?php include( DIR_THEME_ROOT . 'html/templates/advanced_search_ajax.php' ); ?>
			</div>
		</div>
	</form>

	<?php if( $sTags != ''): ?>
		<div class="asrch-tags" style="margin-bottom: 20px;">
			<?php echo $sTags;  ?>
		</div>
	<?php endif; ?>

	<?php
		if( $nProductosTotal == 0 )
			echo $messageStack->show( array( 'class' => 'wrng', 'text' => ERROR_NO_FOUND ) );
	?>
			
<?php endif; ?>



<?php
	while( $aProducto = eachProducts() )
		echo _product( array( 'SIZE_DESCRIPTION' => 250 ) );
?>


<?php if( is_object( $aPaginador ) && !isAjax() ): ?>
	<div class="pgnc pgnc-bttm">
		<?php echo $aPaginador->display_links( MAX_DISPLAY_PAGE_LINKS, tep_get_all_get_params( array('page', 'info', 'x', 'y' ) ) ); ?>
	</div>
<?php endif; ?>