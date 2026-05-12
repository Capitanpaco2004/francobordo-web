<?php
		echo '</div>';
	echo '</div>';

	// Paramos la salida de php
	ob_start();

	// Incluimos archivo del template
	include( DIR_THEME. 'html/column_right.php' );
	include( DIR_THEME. 'html/footer.php' );
	include( DIR_WS_INCLUDES . 'application_bottom.php' );

	// Contenido obtenido
	$sHtml = ob_get_contents();

	// Continuamos con la salida por donde ibamos
	ob_end_clean();
    include_once($_SERVER['DOCUMENT_ROOT'] . '/' . DIR_WS_CLASSES . 'language.php');
    $lng = new language();
	// Obtenemos el codigo de lenguaje
	if (is_array($lng->catalog_languages) && !empty($lng->catalog_languages)) {
		foreach ($lng->catalog_languages as $key => $value) {
			if ($value['directory'] == $language) {
				$sLngKey = $key;
			}
		}
	}

	// Remplazamos
	$sHtml = str_replace(
	array(
		'</body>'
	),
	array(
		'<script src="account/js/parsley/parsley.js" type="text/javascript"></script>
		<script src="account/js/parsley/i18n/' . (isset($sLngKey) ? $sLngKey : 'en') . '.js" type="text/javascript"></script>
		<script src="account/js/javascript.js" type="text/javascript"></script>
		</body>'
	), $sHtml );

	// Pintamos
	echo $sHtml;
?>
