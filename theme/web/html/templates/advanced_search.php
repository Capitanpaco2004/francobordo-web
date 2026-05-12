<h1 class="pageHeading"><?php echo ADVANCED_SEARCH_TITLE; ?></h1>

<?php
	if( $messageStack->check('error_search') )
		echo $messageStack->show( 'error_search' );
?>

<?php echo tep_draw_form( 'advanced_search', tep_href_link( FILENAME_ADVANCED_SEARCH_RESULT, '', 'NONSSL', false ), 'get', 'id="advanced_search" onSubmit="return check_form(this);"' ); ?>
	<div class="form-lnea">
		<div class="form-lnea-txt"><?php echo ADVANCED_SEARCH_SUBTITLE_INTRODUCE_BUSQUEDA; ?></div>
	</div>
	<div class="form-pddg">
		<?php echo tep_draw_input_field( 'buscar' ); ?>
	</div>

	<div class="form-avcd-srch-btom">
		<label for="description"><?php echo ADVANCED_SEARCH_DESCRIPTION; ?></label>
		<?php echo tep_draw_checkbox_field( 'description', 1, true ); ?>
		<input class="form-sbmt" type="submit" value="<?php echo IMAGE_BUTTON_SEARCH; ?>" />
	</div>

	<div class="form-lnea form-lnea-sepa">
		<div class="form-lnea-txt"><?php echo ADVANCED_SEARCH_SUBTITLE_PRECIO_DESDE_HASTA; ?></div>
	</div>
	<div class="slde-rnge-price">
		<div class="slde-rnge" id="slde-rnge" rel="0_<?php echo $nPrecioMax; ?>,0_<?php echo $nPrecioMax; ?>">
			<div id="slde-rnge-bg" class="slde-rnge-bg"></div>
			<div class="slde-rnge-flxa" id="slde-rnge-izqd"></div>
			<div class="slde-rnge-flxa" id="slde-rnge-drch"></div>
		</div>
	</div>
	
	<div class="form-lnea form-lnea-sepa">
		<div class="form-lnea-txt"><?php echo ADVANCED_SEARCH_SUBTITLE_MAS; ?></div>
	</div>
	
	<?php echo tep_draw_input_field( 'precio_desde', '', 'style="display:none;"' ); ?>
	<?php echo tep_draw_input_field( 'precio_hasta', '', 'style="display:none;"' ); ?>
	
	<label class="form-lbel" for="categoria"><?php echo ADVANCED_SEARCH_CATEGORIES; ?></label>
	<div class="form-pddg">
		<?php echo tep_draw_pull_down_menu( 'categoria', tep_get_categories( array( array( 'id' => '', 'text' => TEXT_ALL_CATEGORIES ) ) ) ); ?>
	</div>

	<label class="form-lbel" for="fabricante"><?php echo ADVANCED_SEARCH_MANUFACTURERS; ?></label>
	<div class="form-pddg">
		<?php echo tep_draw_pull_down_menu( 'fabricante', tep_get_manufacturers( array( array( 'id' => '', 'text' => TEXT_ALL_MANUFACTURERS) ) ) ); ?>
	</div>
</form>