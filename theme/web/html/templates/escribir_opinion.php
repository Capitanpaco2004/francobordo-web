<h1 class="pageHeading"><?php echo ESCRIBIR_OPINION_TITULO . $aOpinon['orders_id']; ?></h1>

<?php
	// Si existe algun error mostramos y detenemos
	switch( $sError )
	{
		case OPINION_YA_ESCRITA_ANTERIORMENTE:
			echo $messageStack->show( array( 'class' => 'eror', 'text' => ESCRIBIR_OPINION_YA_ESCRITA_ANTERIORMENTE ) );
			echo '<div class="botonera"><a href="/"><img height="26" width="109" border="0" title="Volver" alt="Volver" src="includes/languages/espanol/images/buttons/button_continue.gif"></a></div>';
			return;
		break;
	}
	
	// Si hemos insertado correctamente la opinion mostramos y detenemos
	if( $messageStack->size('correcto') > 0 )
	{
		echo '<div class="msje msje-crrt"><div class="msje-icon"></div>' . $messageStack->output('correcto') . '</div>';
		echo '<div class="botonera"><a href="/"><img height="26" width="109" border="0" title="Volver" alt="Volver" src="includes/languages/espanol/images/buttons/button_continue.gif"></a></div>';
		return;
	}
?>

<div class="txt-info">
	<?php echo ESCRIBIR_OPINION_INTRODUCCION; ?>
</div>

<form id="form-opin" method="post">
	<div class="form-lnea"><div class="form-lnea-txt"><?php echo ESCRIBIR_OPINION_TITULO_FORM; ?></div></div>

	<?php if( $sError != '' ): ?>
		<?php echo $messageStack->show( array( 'class' => 'eror', 'text' => $sError ) ); ?>
	<?php endif; ?>
	
	<?php echo $sHtml; ?>

	<input type="submit" value="Enviar Opinión" class="form-sbmt"/>
</form>