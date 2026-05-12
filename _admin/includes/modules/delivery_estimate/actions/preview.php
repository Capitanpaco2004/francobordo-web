<?php
	// Action: preview — devuelve el HTML del email final envuelto en el template UHtmlEmails,
	// con los shortcodes sustituidos por datos ficticios. Se usa en la vista previa (iframe).
	//
	// Variables disponibles (inyectadas por index.php):
	//   $module (delivery_estimate_admin)

	// Subject y body en vivo (POST desde la form)
	$sSubject = tep_db_prepare_input( $_POST['subject'] ?? '' );
	$sBody    = tep_db_prepare_input( $_POST['body']    ?? '' );

	// Datos ficticios de ejemplo
	$nSampleOrderId = 12345;
	$sSampleCustomer = 'Juan Pérez';
	$sSampleDateYmd  = date( 'Y-m-d', strtotime( '+7 days' ) );
	$sSampleDateHuman = date( 'd/m/Y', strtotime( $sSampleDateYmd ) );
	$sSampleComment  = 'Hemos recibido la reposición de stock y lo enviaremos en cuanto llegue a nuestro almacén.';
	$sSampleStoreName = defined('STORE_NAME') ? STORE_NAME : 'Tu tienda';
	$sSampleLink = ( defined('HTTP_CATALOG_SERVER') ? HTTP_CATALOG_SERVER : '' )
	             . ( defined('DIR_WS_CATALOG') ? DIR_WS_CATALOG : '/' )
	             . 'account_history_info.php?order_id=' . $nSampleOrderId;

	// Bloque "Comentarios: ..." renderizado según idioma activo (detectado en el form desde el idioma del subject)
	// Para la preview usamos el idioma por defecto de la tienda.
	$nPreviewLangId = defined('DEFAULT_LANGUAGE_ID') ? (int)DEFAULT_LANGUAGE_ID : 0;
	$sSampleCommentBlock = '';
	if( $sSampleComment !== '' ) {
		$sLabel = 'Comentarios:';
		if( $nPreviewLangId > 0 ) {
			$q = tep_db_query( "select code from " . TABLE_LANGUAGES . " where languages_id = '" . $nPreviewLangId . "'" );
			if( tep_db_num_rows( $q ) > 0 ) {
				$r = tep_db_fetch_array( $q );
				$labels = [ 'es' => 'Comentarios:', 'en' => 'Comments:', 'fr' => 'Commentaires :', 'it' => 'Commenti:', 'pt' => 'Comentários:' ];
				$code = strtolower( (string)$r['code'] );
				$sLabel = $labels[$code] ?? 'Comentarios:';
			}
		}
		$sSampleCommentBlock = '<p style="margin:0 0 18px 0;"><strong>' . htmlspecialchars( $sLabel, ENT_QUOTES, 'UTF-8' ) . '</strong> ' . htmlspecialchars( $sSampleComment, ENT_QUOTES, 'UTF-8' ) . '</p>';
	}

	$aReplace = [
		'{ORDERS_ID}'       => (string)$nSampleOrderId,
		'{CUSTOMER_NAME}'   => $sSampleCustomer,
		'{FECHA_ENTREGA}'   => $sSampleDateHuman,
		'{COMENTARIO}'      => $sSampleComment,
		'{COMENTARIO_BLOCK}' => $sSampleCommentBlock,
		'{STORE_NAME}'      => $sSampleStoreName,
		'{LINK_PEDIDO}'     => $sSampleLink,
	];

	$sSubjectFinal = strtr( $sSubject, $aReplace );
	$sBodyFinal    = strtr( $sBody,    $aReplace );

	// Si el body es texto plano (sin tags), aplicamos nl2br + escape. Si ya trae HTML (TinyMCE), passthrough.
	$sBodyHtml = ( strip_tags( $sBodyFinal ) === $sBodyFinal )
		? nl2br( htmlspecialchars( $sBodyFinal, ENT_QUOTES, 'UTF-8' ) )
		: $sBodyFinal;

	// Variables que consume el template UHtmlEmails
	$oID = $nSampleOrderId;
	$check_status = [
		'customers_name'          => $sSampleCustomer,
		'customers_email_address' => 'juan.perez@ejemplo.com',
		'date_purchased'          => date( 'Y-m-d H:i:s' ),
	];
	$delivery_subject        = $sSubjectFinal;
	$delivery_html_body      = $sBodyHtml;
	$delivery_estimated_date = $sSampleDateYmd;

	$sLayout = defined('ULTIMATE_HTML_EMAIL_LAYOUT') ? ULTIMATE_HTML_EMAIL_LAYOUT : 'Stripes';
	$sLayoutFile = DIR_FS_CATALOG_MODULES . 'UHtmlEmails/' . $sLayout . '/delivery_estimate.php';

	if( ! file_exists( $sLayoutFile ) ) {
		// Fallback: plantilla mínima si el layout UHtmlEmails no existe
		$html_email = '<html><body style="font-family:Arial;padding:20px;"><h3>' . htmlspecialchars( $sSubjectFinal, ENT_QUOTES, 'UTF-8' ) . '</h3><hr/>' . $sBodyHtml . '</body></html>';
	}
	else {
		require( $sLayoutFile );
		// $html_email lo define el template
	}

	// Respondemos sólo el HTML del email — el popup lo carga en un iframe.
	// Nada de base.php, nada de footer admin.
	header( 'Content-Type: text/html; charset=utf-8' );
	echo $html_email;
	exit;
?>
