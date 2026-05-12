<?php
	use util\event;

	echo '</tbody>';

	// Paramos la salida de php
	ob_start();

	// Incluimos archivo del template
	include( DIR_THEME. 'html/footer.php' );
	include( DIR_WS_INCLUDES . 'application_bottom.php' );

	// Contenido obtenido
	$sHtml = ob_get_contents();

	// Continuamos con la salida por donde ibamos
	ob_end_clean();

	// Obtenemos JS
	$sJs = '';

	if( isset( $aJs ) )
	{
		foreach( $aJs as $sScript )
			$sJs .= '<script type="text/javascript" src="' . $sScript . '"></script>';
	}
	
	// Remplazamos
	$sHtml = str_replace(
	[
		'order_edit/',
		'<script src="theme/web/js/jquery.uniform.js" type="text/javascript"></script>',
		'$(".check, .check :checkbox, input:radio").uniform();$("select:not(.skip)").uniform();$(\'input:file[class!="skip"]\').uniform();',
		'</body>'
	],
	[
		'',
		'',
		'',
		'<div id="dxload" style="display:none; background: url(theme/web/images/load-all.gif) no-repeat scroll 21px 20px rgb(255, 255, 255); border-radius: 10px 10px 10px 10px; z-index: 61; height: 100px; left: 50%; position: fixed; top: 50%; width: 100px; margin: -50px;"></div>
		<div id="dxbg" style="display:none; position: fixed; top: 0px; width: 100%; background: #0b0b0b none repeat scroll 0 0; height: 100%; left: 0px; opacity: 0.8; z-index: 60;"></div>
		<script src="theme/solenopsis/js/functions.js" type="text/javascript"></script>
		<script src="theme/solenopsis/js/parsley/parsley.js" type="text/javascript"></script>
		<script src="theme/solenopsis/js/parsley/i18n/es.js" type="text/javascript"></script>
		<script src="theme/solenopsis/js/accordion.js" type="text/javascript"></script>
		<script src="theme/solenopsis/js/form.js" type="text/javascript"></script>
		<script src="theme/solenopsis/js/json2.js" type="text/javascript"></script>
		<script src="theme/solenopsis/js/magnific-popup.js" type="text/javascript"></script>
		<script src="theme/solenopsis/js/dropdown.js" type="text/javascript"></script>
		<script src="theme/solenopsis/js/dynamicTable.js" type="text/javascript"></script>
		<script src="theme/solenopsis/js/moment.js" type="text/javascript"></script>
		<script src="theme/solenopsis/js/daterangepicker.js" type="text/javascript"></script>
		<script src="theme/solenopsis/js/growl.js" type="text/javascript"></script>
		<script src="theme/solenopsis/js/event.js" type="text/javascript"></script>
		<script src="theme/solenopsis/js/alert.js" type="text/javascript"></script>
		' . $sJs . '
		<script src="theme/solenopsis/js/init.js" type="text/javascript"></script>
		' . implode('', event::getInstance()->execute('back_office_footer_after_scripts_solenopsis')) . '
		</body>'
	], $sHtml );

	// Pintamos
	echo $sHtml;
?>