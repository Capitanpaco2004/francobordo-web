<?php
/**
 * Modulo Fecha Estimada de Entrega — clase ADMIN
 *
 * Contiene todo lo que sólo se ejecuta desde el admin:
 *   - Instalación / eliminación (install, remove)
 *   - Claves de configuración (keys)
 *   - Plantillas de email por idioma (ensureEmailTemplates, getEmailTemplates, saveEmailTemplate)
 *   - Edición manual desde la ficha del pedido (saveManualUpdate) + envío de email (sendUpdateEmail)
 *   - Histórico de cambios (getHistory)
 *
 * La lógica de cálculo automático y la consulta de fecha vigente (calculateForOrder, getCurrent)
 * viven en la clase frontend: includes/modules/delivery_estimate/delivery_estimate.php
 */
class delivery_estimate_admin {

	public function __construct() {
		// nada de estado
	}

	/**
	 * Devuelve true si las tablas y la configuracion estan creadas.
	 */
	public function isInstalled() {
		$check = tep_db_query( "show tables like 'orders_delivery_estimate'" );
		if( tep_db_num_rows( $check ) == 0 )
			return false;

		$check = tep_db_query( "show tables like 'delivery_estimate_email'" );
		if( tep_db_num_rows( $check ) == 0 )
			return false;

		$check = tep_db_query( "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'DELIVERY_ESTIMATE_ENABLED'" );
		return ( tep_db_num_rows( $check ) > 0 );
	}

	/**
	 * Instala tablas y configuracion. Idempotente.
	 */
	public function install() {

		// Tabla de historico de fechas estimadas por pedido
		tep_db_query( "CREATE TABLE IF NOT EXISTS orders_delivery_estimate (
			delivery_estimate_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			orders_id INT UNSIGNED NOT NULL,
			estimated_date DATE NOT NULL,
			rule_applied VARCHAR(32) NOT NULL DEFAULT '',
			comment TEXT NULL,
			is_manual TINYINT(1) NOT NULL DEFAULT 0,
			admin_user VARCHAR(100) NULL,
			email_sent TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			INDEX idx_orders_id (orders_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8" );

		// Tabla de plantillas de email por idioma
		tep_db_query( "CREATE TABLE IF NOT EXISTS delivery_estimate_email (
			delivery_estimate_email_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			languages_id INT UNSIGNED NOT NULL,
			subject VARCHAR(255) NOT NULL DEFAULT '',
			body TEXT NULL,
			UNIQUE KEY uq_lang (languages_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8" );

		// Plantillas por defecto por idioma disponible
		$this->ensureEmailTemplates();

		// Configuraciones (reglas)
		$group_id = $this->resolveGroupId();

		$defaults = array(
			'DELIVERY_ESTIMATE_ENABLED'        => array( 'Activar fecha estimada', 'True', 'Mostrar y calcular la fecha estimada de entrega en los pedidos. Valores: True / False', 'tep_cfg_select_option(array(\'True\', \'False\'), ', null ),
			'DELIVERY_ESTIMATE_DAYS_IN_STOCK'  => array( 'Dias si hay stock de todo', '2', 'Dias a sumar a la fecha de compra cuando todos los productos tienen stock disponible', null, null ),
			'DELIVERY_ESTIMATE_DAYS_NO_STOCK'  => array( 'Dias si algun producto sin stock', '14', 'Dias a sumar cuando algun producto tiene stock < 0 y > -800, o la cantidad pedida supera el stock', null, null ),
			'DELIVERY_ESTIMATE_DAYS_BACKORDER' => array( 'Dias si algun producto bajo pedido', '30', 'Dias a sumar cuando algun producto tiene stock = -800 (bajo pedido)', null, null ),
			'DELIVERY_ESTIMATE_BUSINESS_DAYS'  => array( 'Contar dias laborables', 'False', 'Si esta a True se excluyen sabados y domingos al sumar los dias', 'tep_cfg_select_option(array(\'True\', \'False\'), ', null ),
		);

		foreach( $defaults as $key => $data ) {
			$exists = tep_db_query( "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = '" . tep_db_input( $key ) . "'" );
			if( tep_db_num_rows( $exists ) > 0 )
				continue;

			list( $title, $value, $descr, $setFn, $useFn ) = $data;

			$cols = "configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added";
			$vals = "'" . tep_db_input( $title ) . "', '" . tep_db_input( $key ) . "', '" . tep_db_input( $value ) . "', '" . tep_db_input( $descr ) . "', '" . (int)$group_id . "', '1', now()";

			if( $setFn !== null ) {
				$cols .= ", set_function";
				$vals .= ", '" . tep_db_input( $setFn ) . "'";
			}
			if( $useFn !== null ) {
				$cols .= ", use_function";
				$vals .= ", '" . tep_db_input( $useFn ) . "'";
			}

			tep_db_query( "insert into " . TABLE_CONFIGURATION . " (" . $cols . ") values (" . $vals . ")" );
		}
	}

	/**
	 * Crea una fila por idioma disponible en delivery_estimate_email con valores por defecto
	 * (si esa fila aun no existe).
	 */
	public function ensureEmailTemplates() {

		// Obtenemos los idiomas instalados
		$aLangs = tep_db_query( "select languages_id, code from " . TABLE_LANGUAGES );
		while( $l = tep_db_fetch_array( $aLangs ) ) {

			$lid = (int)$l['languages_id'];
			$code = strtolower( $l['code'] );

			// Si ya existe fila para este idioma, no tocamos
			$check = tep_db_query( "select delivery_estimate_email_id from delivery_estimate_email where languages_id = '" . $lid . "'" );
			if( tep_db_num_rows( $check ) > 0 )
				continue;

			list( $subject, $body ) = $this->defaultTemplate( $code );

			tep_db_query( "insert into delivery_estimate_email (languages_id, subject, body) values ('" . $lid . "', '" . tep_db_input( $subject ) . "', '" . tep_db_input( $body ) . "')" );
		}
	}

	/**
	 * Elimina configuracion (no borra tablas para no perder datos historicos).
	 */
	public function remove() {
		tep_db_query( "delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode( "', '", $this->keys() ) . "')" );
	}

	/**
	 * Claves de configuracion que gestiona este modulo.
	 */
	public function keys() {
		return array(
			'DELIVERY_ESTIMATE_ENABLED',
			'DELIVERY_ESTIMATE_DAYS_IN_STOCK',
			'DELIVERY_ESTIMATE_DAYS_NO_STOCK',
			'DELIVERY_ESTIMATE_DAYS_BACKORDER',
			'DELIVERY_ESTIMATE_BUSINESS_DAYS',
		);
	}

	/**
	 * Guarda una edicion manual (desde admin) y opcionalmente notifica al cliente.
	 */
	public function saveManualUpdate( $orders_id, $estimated_date, $comment, $notify, $admin_user ) {

		$orders_id = (int)$orders_id;
		if( $orders_id <= 0 )
			return false;

		if( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string)$estimated_date ) )
			return false;

		$notify  = $notify ? 1 : 0;
		$comment = (string)$comment;

		$email_sent = 0;
		if( $notify ) {
			if( $this->sendUpdateEmail( $orders_id, $estimated_date, $comment ) )
				$email_sent = 1;
		}

		tep_db_query( "insert into orders_delivery_estimate (orders_id, estimated_date, rule_applied, comment, is_manual, admin_user, email_sent, created_at) values ('" . $orders_id . "', '" . tep_db_input( $estimated_date ) . "', 'manual', '" . tep_db_input( $comment ) . "', 1, '" . tep_db_input( (string)$admin_user ) . "', " . $email_sent . ", now())" );

		return true;
	}

	/**
	 * Devuelve el histórico completo de fechas estimadas de un pedido (más reciente primero).
	 */
	public static function getHistory( $orders_id ) {
		$orders_id = (int)$orders_id;
		if( $orders_id <= 0 )
			return array();

		$check = tep_db_query( "show tables like 'orders_delivery_estimate'" );
		if( tep_db_num_rows( $check ) == 0 )
			return array();

		$out = array();
		$q = tep_db_query( "select * from orders_delivery_estimate where orders_id = '" . $orders_id . "' order by created_at desc, delivery_estimate_id desc" );
		while( $r = tep_db_fetch_array( $q ) )
			$out[] = $r;

		return $out;
	}

	/**
	 * Devuelve las plantillas de email indexadas por languages_id.
	 *   array( lang_id => array('subject' => ..., 'body' => ...), ... )
	 */
	public static function getEmailTemplates() {
		$check = tep_db_query( "show tables like 'delivery_estimate_email'" );
		if( tep_db_num_rows( $check ) == 0 )
			return array();

		$out = array();
		$q = tep_db_query( "select languages_id, subject, body from delivery_estimate_email" );
		while( $r = tep_db_fetch_array( $q ) )
			$out[(int)$r['languages_id']] = array( 'subject' => $r['subject'], 'body' => $r['body'] );

		return $out;
	}

	/**
	 * Guarda (upsert) una plantilla de email para un idioma concreto.
	 */
	public function saveEmailTemplate( $language_id, $subject, $body ) {
		$language_id = (int)$language_id;
		$check = tep_db_query( "select delivery_estimate_email_id from delivery_estimate_email where languages_id = '" . $language_id . "'" );

		if( tep_db_num_rows( $check ) > 0 ) {
			tep_db_query( "update delivery_estimate_email set subject = '" . tep_db_input( (string)$subject ) . "', body = '" . tep_db_input( (string)$body ) . "' where languages_id = '" . $language_id . "'" );
		}
		else {
			tep_db_query( "insert into delivery_estimate_email (languages_id, subject, body) values ('" . $language_id . "', '" . tep_db_input( (string)$subject ) . "', '" . tep_db_input( (string)$body ) . "')" );
		}
		return true;
	}

	/**
	 * Envia un email de PRUEBA a la dirección indicada, usando datos ficticios y la plantilla del
	 * idioma solicitado (o la primera disponible si no se especifica). Útil para testear el diseño.
	 *
	 * @param string $to_email    Dirección destino del email de prueba
	 * @param int    $language_id Idioma de la plantilla a usar (0 = primera disponible)
	 * @return bool               true si se envió, false en caso de error
	 */
	public function sendTestEmail( $to_email, $language_id = 0 ) {
		$to_email = trim( (string)$to_email );
		if( $to_email === '' || ! filter_var( $to_email, FILTER_VALIDATE_EMAIL ) )
			return false;

		// Seleccionamos plantilla
		$templates = self::getEmailTemplates();
		$tpl = null;
		$language_id = (int)$language_id;

		if( $language_id > 0 && isset( $templates[$language_id] ) ) {
			$tpl = $templates[$language_id];
		}
		elseif( ! empty( $templates ) ) {
			$defaultId = defined('DEFAULT_LANGUAGE_ID') ? (int)DEFAULT_LANGUAGE_ID : 0;
			if( $defaultId > 0 && isset( $templates[$defaultId] ) )
				$tpl = $templates[$defaultId];
			else
				$tpl = reset( $templates );
		}

		if( $tpl === null )
			return false;

		// Datos ficticios
		$sampleOrderId   = 12345;
		$sampleCustomer  = 'Juan Pérez (prueba)';
		$sampleDateYmd   = date( 'Y-m-d', strtotime( '+7 days' ) );
		$sampleDateHuman = date( 'd/m/Y', strtotime( $sampleDateYmd ) );
		$sampleComment   = 'Email de prueba — datos ficticios para comprobar cómo queda el diseño.';
		$sampleStoreName = defined('STORE_NAME') ? STORE_NAME : 'Tu tienda';
		$sampleLink      = $this->buildOrderLink( $sampleOrderId );

		$replace = array(
			'{ORDERS_ID}'       => (string)$sampleOrderId,
			'{CUSTOMER_NAME}'   => $sampleCustomer,
			'{FECHA_ENTREGA}'   => $sampleDateHuman,
			'{COMENTARIO}'      => $sampleComment,
			'{COMENTARIO_BLOCK}' => $this->renderCommentBlock( $sampleComment, $language_id > 0 ? $language_id : ( defined('DEFAULT_LANGUAGE_ID') ? (int)DEFAULT_LANGUAGE_ID : 0 ) ),
			'{STORE_NAME}'      => $sampleStoreName,
			'{LINK_PEDIDO}'     => $sampleLink,
		);

		$subject = '[PRUEBA] ' . strtr( (string)$tpl['subject'], $replace );
		$body    = strtr( (string)$tpl['body'], $replace );

		$bodyHtml = ( strip_tags( $body ) === $body ) ? nl2br( htmlspecialchars( $body, ENT_QUOTES, 'UTF-8' ) ) : $body;

		$html_email = $bodyHtml; // fallback

		$layoutTpl = DIR_FS_CATALOG_MODULES . 'UHtmlEmails/' . ( defined('ULTIMATE_HTML_EMAIL_LAYOUT') ? ULTIMATE_HTML_EMAIL_LAYOUT : 'Stripes' ) . '/delivery_estimate.php';
		if( file_exists( $layoutTpl ) ) {
			$oID                     = $sampleOrderId;
			$check_status            = [
				'customers_name'          => $sampleCustomer,
				'customers_email_address' => $to_email,
				'date_purchased'          => date( 'Y-m-d H:i:s' ),
			];
			$delivery_subject        = $subject;
			$delivery_html_body      = $bodyHtml;
			$delivery_estimated_date = $sampleDateYmd;

			require( $layoutTpl );
		}

		tep_mail(
			'Destinatario prueba',
			$to_email,
			$subject,
			$html_email,
			defined('STORE_OWNER') ? STORE_OWNER : '',
			defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : ''
		);

		return true;
	}

	// ---------------------------------------------------------------
	// Helpers internos
	// ---------------------------------------------------------------

	/**
	 * Envia email al cliente usando la plantilla del idioma del pedido, envuelto en el layout
	 * UHtmlEmails (misma cabecera, footer y logo que los emails de cambio de estado del pedido).
	 */
	private function sendUpdateEmail( $orders_id, $estimated_date, $comment ) {

		$q = tep_db_query( "select customers_name, customers_email_address, customers_language_id, date_purchased from " . TABLE_ORDERS . " where orders_id = '" . (int)$orders_id . "'" );
		if( tep_db_num_rows( $q ) == 0 )
			return false;
		$row = tep_db_fetch_array( $q );

		$langId = (int)$row['customers_language_id'];

		// Buscamos plantilla del idioma del cliente
		$templates = self::getEmailTemplates();
		$tpl = null;

		if( isset( $templates[$langId] ) ) {
			$tpl = $templates[$langId];
		}
		else {
			// Fallback: idioma por defecto de la tienda o la primera disponible
			$defaultId = defined('DEFAULT_LANGUAGE_ID') ? (int)DEFAULT_LANGUAGE_ID : 0;
			if( $defaultId > 0 && isset( $templates[$defaultId] ) ) {
				$tpl = $templates[$defaultId];
			}
			elseif( ! empty( $templates ) ) {
				$tpl = reset( $templates );
			}
		}

		if( $tpl === null )
			return false;

		$replace = array(
			'{ORDERS_ID}'       => (int)$orders_id,
			'{CUSTOMER_NAME}'   => $row['customers_name'],
			'{FECHA_ENTREGA}'   => $this->formatDate( $estimated_date ),
			'{COMENTARIO}'      => (string)$comment,
			'{COMENTARIO_BLOCK}' => $this->renderCommentBlock( $comment, $langId ),
			'{STORE_NAME}'      => defined('STORE_NAME') ? STORE_NAME : '',
			'{LINK_PEDIDO}'     => $this->buildOrderLink( (int)$orders_id ),
		);

		$subject = strtr( (string)$tpl['subject'], $replace );
		$body    = strtr( (string)$tpl['body'], $replace );

		// Si el body no tiene tags (legacy plantillas planas), aplicamos nl2br + escape para respetar saltos.
		// Si ya trae HTML (TinyMCE), lo dejamos pasar tal cual.
		$bodyHtml = ( strip_tags( $body ) === $body ) ? nl2br( htmlspecialchars( $body, ENT_QUOTES, 'UTF-8' ) ) : $body;

		// Envolvemos con el layout UHtmlEmails (mismo look que los emails de cambio de estado)
		$html_email = $bodyHtml; // fallback si no existe el layout

		$layoutTpl = DIR_FS_CATALOG_MODULES . 'UHtmlEmails/' . ( defined('ULTIMATE_HTML_EMAIL_LAYOUT') ? ULTIMATE_HTML_EMAIL_LAYOUT : 'Stripes' ) . '/delivery_estimate.php';
		if( file_exists( $layoutTpl ) ) {
			// Variables que consume el template
			$oID                     = (int)$orders_id;
			$check_status            = $row;
			$delivery_subject        = $subject;
			$delivery_html_body      = $bodyHtml;
			$delivery_estimated_date = $estimated_date;

			require( $layoutTpl );
			// el template redefine $html_email con el wrapper completo
		}

		tep_mail(
			$row['customers_name'],
			$row['customers_email_address'],
			$subject,
			$html_email,
			defined('STORE_OWNER') ? STORE_OWNER : '',
			defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : ''
		);

		return true;
	}

	private function formatDate( $ymd ) {
		$ts = strtotime( (string)$ymd );
		if( $ts === false )
			return (string)$ymd;
		return date( 'd/m/Y', $ts );
	}

	/**
	 * Construye la URL absoluta del detalle del pedido en la cuenta del cliente.
	 */
	private function buildOrderLink( $orders_id ) {
		$orders_id = (int)$orders_id;
		$base = ( defined('HTTP_CATALOG_SERVER') ? HTTP_CATALOG_SERVER : '' )
		      . ( defined('DIR_WS_CATALOG') ? DIR_WS_CATALOG : '/' );
		return $base . 'account_history_info.php?order_id=' . $orders_id;
	}

	/**
	 * Renderiza el bloque "Comentarios: <texto>" en el idioma indicado, o string vacío
	 * si no hay comentario. Se usa para sustituir el shortcode {COMENTARIO_BLOCK}.
	 */
	private function renderCommentBlock( $comment, $language_id ) {
		$comment = trim( (string)$comment );
		if( $comment === '' )
			return '';

		// Buscamos el código de idioma
		$code = '';
		$nLid = (int)$language_id;
		if( $nLid > 0 ) {
			$q = tep_db_query( "select code from " . TABLE_LANGUAGES . " where languages_id = '" . $nLid . "'" );
			if( tep_db_num_rows( $q ) > 0 ) {
				$r = tep_db_fetch_array( $q );
				$code = strtolower( (string)$r['code'] );
			}
		}

		$labels = array(
			'es' => 'Comentarios:',
			'en' => 'Comments:',
			'fr' => 'Commentaires :',
			'it' => 'Commenti:',
			'pt' => 'Comentários:',
		);
		$label = isset( $labels[$code] ) ? $labels[$code] : $labels['es'];

		return '<p style="margin:0 0 18px 0;"><strong>' . htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' ) . '</strong> ' . htmlspecialchars( $comment, ENT_QUOTES, 'UTF-8' ) . '</p>';
	}

	/**
	 * Plantilla por defecto segun codigo de idioma (es, en, fr, ...). Contenido HTML (para TinyMCE).
	 */
	private function defaultTemplate( $code ) {
		$code = strtolower( (string)$code );

		// Estilos inline para emails (compatibles con la mayoría de clientes)
		$pStyle   = 'margin:0 0 18px 0;';
		$btnStyle = 'display:inline-block;padding:12px 28px;background:#2bb0e2;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:bold;font-family:Arial,Helvetica,sans-serif;font-size:14px;';
		$btnWrap  = 'margin:28px 0;';

		// El wrapper UHtmlEmails muestra ya el Nº pedido, fecha compra y nueva fecha estimada en
		// el banner superior, así que el body NO repite esa información — sólo lleva el mensaje.
		$map = array(
			'es' => array(
				'Actualizacion de la fecha de entrega de tu pedido #{ORDERS_ID}',
				"<p style=\"$pStyle\">Hola {CUSTOMER_NAME},</p>\n<p style=\"$pStyle\">Te informamos de que la fecha estimada de entrega de tu pedido se ha actualizado. Arriba puedes ver la nueva fecha prevista.</p>\n{COMENTARIO_BLOCK}\n<p style=\"$btnWrap\"><a href=\"{LINK_PEDIDO}\" style=\"$btnStyle\">Ver detalles del pedido</a></p>\n<p style=\"$pStyle\">Un saludo,<br/>{STORE_NAME}</p>",
			),
			'en' => array(
				'Delivery date update for your order #{ORDERS_ID}',
				"<p style=\"$pStyle\">Hello {CUSTOMER_NAME},</p>\n<p style=\"$pStyle\">We are letting you know that the estimated delivery date for your order has been updated. You can see the new estimated date at the top.</p>\n{COMENTARIO_BLOCK}\n<p style=\"$btnWrap\"><a href=\"{LINK_PEDIDO}\" style=\"$btnStyle\">View order details</a></p>\n<p style=\"$pStyle\">Best regards,<br/>{STORE_NAME}</p>",
			),
			'fr' => array(
				'Mise a jour de la date de livraison de votre commande #{ORDERS_ID}',
				"<p style=\"$pStyle\">Bonjour {CUSTOMER_NAME},</p>\n<p style=\"$pStyle\">Nous vous informons que la date estimee de livraison de votre commande a ete mise a jour. Vous pouvez voir la nouvelle date estimee en haut.</p>\n{COMENTARIO_BLOCK}\n<p style=\"$btnWrap\"><a href=\"{LINK_PEDIDO}\" style=\"$btnStyle\">Voir les details de la commande</a></p>\n<p style=\"$pStyle\">Cordialement,<br/>{STORE_NAME}</p>",
			),
			'it' => array(
				'Aggiornamento della data di consegna del tuo ordine #{ORDERS_ID}',
				"<p style=\"$pStyle\">Ciao {CUSTOMER_NAME},</p>\n<p style=\"$pStyle\">Ti informiamo che la data di consegna stimata del tuo ordine e stata aggiornata. Puoi vedere la nuova data stimata in alto.</p>\n{COMENTARIO_BLOCK}\n<p style=\"$btnWrap\"><a href=\"{LINK_PEDIDO}\" style=\"$btnStyle\">Vedi dettagli dell ordine</a></p>\n<p style=\"$pStyle\">Cordiali saluti,<br/>{STORE_NAME}</p>",
			),
			'pt' => array(
				'Atualizacao da data de entrega da sua encomenda #{ORDERS_ID}',
				"<p style=\"$pStyle\">Ola {CUSTOMER_NAME},</p>\n<p style=\"$pStyle\">Informamos que a data estimada de entrega da sua encomenda foi atualizada. Pode ver a nova data estimada em cima.</p>\n{COMENTARIO_BLOCK}\n<p style=\"$btnWrap\"><a href=\"{LINK_PEDIDO}\" style=\"$btnStyle\">Ver detalhes da encomenda</a></p>\n<p style=\"$pStyle\">Cumprimentos,<br/>{STORE_NAME}</p>",
			),
		);

		return isset( $map[$code] ) ? $map[$code] : $map['es'];
	}

	private function resolveGroupId() {
		$q = tep_db_query( "select configuration_group_id from " . TABLE_CONFIGURATION . " where configuration_key like 'DELIVERY_ESTIMATE_%' limit 1" );
		if( tep_db_num_rows( $q ) > 0 ) {
			$row = tep_db_fetch_array( $q );
			return (int)$row['configuration_group_id'];
		}

		$q = tep_db_query( "select max(configuration_group_id) as max_id from configuration_group" );
		if( tep_db_num_rows( $q ) > 0 ) {
			$row = tep_db_fetch_array( $q );
			$newId = (int)$row['max_id'] + 1;
		}
		else {
			$newId = 10000;
		}

		tep_db_query( "insert into configuration_group (configuration_group_id, configuration_group_title, configuration_group_description, sort_order, visible) values ('" . $newId . "', 'Fecha estimada de entrega', 'Configuracion del modulo de fecha estimada de entrega', '1', '0')" );

		return $newId;
	}
}
