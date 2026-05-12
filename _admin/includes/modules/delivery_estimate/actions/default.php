<?php
	// Action: default — muestra el formulario de configuración del módulo.
	//
	// Variables disponibles (inyectadas por index.php):
	//   $sUrlPage, $sPathTemplate, $module (delivery_estimate_admin)

	$aButtons = [
		[ 'title' => 'Enviar prueba', 'icon' => 'fa-paper-plane', 'extra' => 'id="delivery-estimate-sendtest-btn"', 'href' => 'javascript:void(0);' ],
		[ 'title' => 'Vista previa', 'icon' => 'fa-eye', 'extra' => 'id="delivery-estimate-preview-btn"', 'href' => 'javascript:void(0);' ],
		[ 'title' => 'Guardar cambios', 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ],
	];
	$sSubtitle = 'Configuración del cálculo y notificación al cliente';

	// Valores actuales de configuracion
	$aValues = [];
	foreach( $module->keys() as $sKey ) {
		$q = tep_db_query( "SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . tep_db_input( $sKey ) . "'" );
		$aValues[$sKey] = ( tep_db_num_rows( $q ) > 0 ) ? tep_db_fetch_array( $q )['configuration_value'] : '';
	}

	// Plantillas de email por idioma (indexadas por languages_id)
	$aTemplates = delivery_estimate_admin::getEmailTemplates();
	$aSubjects = [];
	$aBodies   = [];
	foreach( $aTemplates as $nLid => $aTpl ) {
		$aSubjects[$nLid] = $aTpl['subject'];
		$aBodies[$nLid]   = $aTpl['body'];
	}

	// Shortcodes disponibles (se usan en el template form.php)
	$sShortcodes = 'Shortcodes disponibles: <code>{ORDERS_ID}</code> <code>{CUSTOMER_NAME}</code> <code>{FECHA_ENTREGA}</code> <code>{COMENTARIO}</code> <code>{COMENTARIO_BLOCK}</code> <code>{STORE_NAME}</code> <code>{LINK_PEDIDO}</code><br/><small><i>{COMENTARIO_BLOCK} imprime automáticamente "Comentarios: ..." sólo si hay comentario, en el idioma del cliente. Si está vacío, no aparece nada.</i></small>';

	// Template del formulario
	$sHtmlModule = includeTemplate( $sPathTemplate . '/form.php' );
?>
