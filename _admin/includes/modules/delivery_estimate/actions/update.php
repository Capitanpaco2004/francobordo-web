<?php
	use util\tools as tools;

	// Action: update — guarda la configuración y las plantillas de email, y redirige.
	//
	// Variables disponibles (inyectadas por index.php):
	//   $sUrlPage, $module (delivery_estimate_admin), $messageStack

	// Campos booleanos: checkbox presente = True, ausente = False
	$bEnabled      = isset( $_POST['DELIVERY_ESTIMATE_ENABLED'] )       ? 'True' : 'False';
	$bBusinessDays = isset( $_POST['DELIVERY_ESTIMATE_BUSINESS_DAYS'] ) ? 'True' : 'False';

	// Campos numericos (saneados como entero >= 0)
	$nInStock   = max( 0, (int)( $_POST['DELIVERY_ESTIMATE_DAYS_IN_STOCK']  ?? 2 ) );
	$nNoStock   = max( 0, (int)( $_POST['DELIVERY_ESTIMATE_DAYS_NO_STOCK']  ?? 14 ) );
	$nBackorder = max( 0, (int)( $_POST['DELIVERY_ESTIMATE_DAYS_BACKORDER'] ?? 30 ) );

	$aConfigs = [
		'DELIVERY_ESTIMATE_ENABLED'        => $bEnabled,
		'DELIVERY_ESTIMATE_BUSINESS_DAYS'  => $bBusinessDays,
		'DELIVERY_ESTIMATE_DAYS_IN_STOCK'  => (string)$nInStock,
		'DELIVERY_ESTIMATE_DAYS_NO_STOCK'  => (string)$nNoStock,
		'DELIVERY_ESTIMATE_DAYS_BACKORDER' => (string)$nBackorder,
	];

	foreach( $aConfigs as $sKey => $sValue ) {
		tep_db_query( 'UPDATE ' . TABLE_CONFIGURATION . ' SET configuration_value = "' . tep_db_input( $sValue ) . '" WHERE configuration_key = "' . tep_db_input( $sKey ) . '"' );
	}

	// Plantillas de email por idioma (subject + body)
	$aPostSubjects = ( isset( $_POST['email_subject'] ) && is_array( $_POST['email_subject'] ) ) ? tep_db_prepare_input( $_POST['email_subject'] ) : [];
	$aPostBodies   = ( isset( $_POST['email_body'] )    && is_array( $_POST['email_body'] ) )    ? tep_db_prepare_input( $_POST['email_body'] )    : [];

	$aLanguages = tep_get_languages();
	foreach( $aLanguages as $aLanguage ) {
		$nLangId  = (int)$aLanguage['id'];
		$sSubject = (string)( $aPostSubjects[$nLangId] ?? '' );
		$sBody    = (string)( $aPostBodies[$nLangId]   ?? '' );
		$module->saveEmailTemplate( $nLangId, $sSubject, $sBody );
	}

	// Reset cache de configuracion
	require( 'includes/configuration_cache.php' );
	tools::createCacheFile();

	// Mensaje + redirect
	$messageStack->addSession( 'success', 'La configuración se guardó correctamente.', 'success' );
	tep_redirect( tep_href_link( $sUrlPage ) );
?>
