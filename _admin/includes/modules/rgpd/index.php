<?php
	// Tools
	use util\tools as tools;
	use util\date as date;

	// En 'install': evitamos SOLO el forbidden (ACL) fingiendo el dashboard; NO tocamos SCRIPT_FILENAME
	// para que application_top SIGA exigiendo login (antes se saltaba entero = bypass sin auth).
	if( array_key_exists( 'action', $_GET ) && $_GET['action'] == 'install' )
	{
		$_SERVER['PHP_SELF'] = 'index.php';
	}
	// Crons RGPD (los invoca el crontab por curl SIN sesion): siguen saltandose el login.
	// AVISO SEGURIDAD PENDIENTE: hoy NO validan token -> invocables por cualquier IP permitida por el
	// .htaccess de _admin. Anadir token (hash_equals) antes del borrado/purga/envio y pasar ?token= en el crontab.
	elseif( array_key_exists( 'action', $_GET ) && in_array( $_GET['action'], array( 'cron_delete_customer', 'cron_delete_customer_notify', 'cron_delete_orders' ) ) )
	{
		$_SERVER['PHP_SELF'] = 'login.php';
		$_SERVER['SCRIPT_FILENAME'] = 'login.php';
	}

	// Incluimos el application_top
	require_once( 'includes/application_top.php' );

	// Mostrar errores
	// ini_set('display_errors', 1);
	// error_reporting(1);
	// error_reporting(E_ERROR | E_WARNING | E_PARSE);
	// error_reporting(E_ALL);

	// Variables
	$sUrlPage =  'rgpd.php';
	$sTitle = 'Adaptación Tecnológica RGPD';
	$sSubtitle = '';
	$aButtons = array();
	$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);

	// Token de los crons RGPD: el crontab los invoca por curl SIN sesion (el shim del principio de este
	// fichero salta el login para que puedan correr). Sin este secreto, cualquiera con acceso al _admin
	// (allowlist del .htaccess) podria disparar por GET el borrado masivo de clientes (cron_delete_customer),
	// la purga IRREVERSIBLE de PII de pedidos (cron_delete_orders) o el envio masivo de emails
	// (cron_delete_customer_notify). Exigimos el token ANTES de cualquier trabajo sensible.
	if( ! defined( 'RGPD_CRON_TOKEN' ) )
		define( 'RGPD_CRON_TOKEN', '106493bc3e87285b80baeebb68744606a247f37ff8a577f2' );
	if( in_array( $sPostAction, array( 'cron_delete_customer', 'cron_delete_customer_notify', 'cron_delete_orders' ), true ) ) {
		if( ! hash_equals( RGPD_CRON_TOKEN, isset( $_GET['token'] ) ? (string)$_GET['token'] : '' ) ) {
			http_response_code( 403 );
			die( 'forbidden' );
		}
	}
	$sGetPage = tep_db_prepare_input( $_GET['page'] ?? 1 );
	$sGetOrderby = tep_db_prepare_input( $_GET['orderby'] ?? '' );
	$sGetSort = tep_db_prepare_input( $_GET['sort'] ?? '' );
	$sHtml = '';
	$aYears = array();
	$aCleans = array( array( 'id' => 'mantener', 'text' => 'Mantener' ), array( 'id' => 'anonimizar', 'text' => 'Anonimizar' ), array( 'id' => 'eliminar', 'text' => 'Eliminar' ) );
	$aLanguage = pharaonix_getArrayAssociativeSql( 'select languages_id, directory from languages', 'languages_id', 'directory', false, 1 );

	// Recreamos los años
	for( $nCont = 1; $nCont <= 10; $nCont++ )
		$aYears[] = array( 'id' => $nCont, 'text' => $nCont );

	// Adnmin en uso
	$aDatos = tep_db_query( 'SELECT a.admin_id, a.admin_firstname, a.admin_lastname, a.admin_email_address, a.admin_created, a.admin_modified, a.admin_logdate, a.admin_lognum, g.admin_groups_name
							 FROM admin a
							 INNER JOIN admin_groups g ON( a.admin_groups_id = g.admin_groups_id)
							 WHERE a.admin_id= "' . (int)$login_id . "' AND g.admin_groups_id= '" . (int)$login_groups_id . '"');
	$aAdmin = tep_db_fetch_array( $aDatos );

	// Array campos de pedidos
	$aOrdersRows = array(
		'customers_name' => 'Nombre del cliente',
		'customers_company' => 'Compañia',
		'customers_suburb' => 'Suburbio',
		'customers_street_address' => 'Dirección',
		'customers_telephone' => 'Teléfono',
		'customers_email_address' => 'E-mail',
		'delivery_name' => 'Nombre dirección de entrega',
		'delivery_company' => 'Compañia dirección de entrega',
		'delivery_street_address' => 'Dirección de entrega',
		'delivery_suburb' => 'Suburbio dirección de entrega',
		'billing_name' => 'Nombre dirección de facturación',
		'billing_company' => 'Nombre compañia de facturación',
		'billing_nif' => 'Dni',
		'billing_street_address' => 'Dirección de facturación',
		'billing_suburb' => 'Suburbio dirección de facturación'
	);

	# Messagestack estilo
	$messageStack->style = 'solenopsis';

	// Acciones
	switch( $sPostAction )
	{
		case 'readme':
			// Variables
			$sSubtitle = 'Readme de instalación';
			$aButtons = array(
				array( 'title' => 'Ver módulo', 'icon' => 'fa-arrow-right', 'href' => $sUrlPage )
			);

			$sHtml = tools::parsedown( DIR_WS_MODULES . '/rgpd/readme.txt' );
		break;

		case 'install':
			// Insertamos admin file
			tools::insertAdminFiles( $sUrlPage, 1 );

			// Insertamos el grupo de configuracion
			$aConfigGroup = tools::insertConfigurationGroup( 'RGPD', '', 0 );

			// Insertamos la configuracion account_delete
			tools::insertConfiguration( 'Texto eliminar cuenta', 'RGPD_ACCOUNT_DELETE_TEXT_DELETE', '{\"3\":\"<p><span style="font-size:15px;">¡Qué pena que nos abandones!<\/span><\/p><p><span style="font-size:15px;">Si crees que no volverás a usar nuestra tienda online de nuevo, puedes solicitar que tu cuenta se elimine definitivamente. Recuerda que no podrás reactivar tu cuenta ni recuperar ninguna información de ella, ya que tu cuenta y tus datos se eliminarán completamente del sistema.<br \/>Para continuar con el proceso de eliminación, confírmanos tu contraseña a continuación:<\/span><\/p><p><span style="font-size:15px;">{CONFIRM_PASSWORD}<\/span><\/p><p><span style="color:#FF0000;"><span style="font-size:15px;">IMPORTANTE: Esta acción es definitiva y no se puede deshacer para recuperar su cuenta.<\/span><\/span><br \/>&nbsp;<\/p><p><span style="font-size: 11px;"><em>En virtud del Derecho al Olvido garantizado por el Reglamento General de Protección de Datos (RGPD), si continuas con el proceso eliminaremos toda la información referente a tu usuario a excepción de los pedidos y\/o facturas inferiores a {TAX_TIME_DATA_ORDER} años por motivos fiscales.<\/em><\/span><\/p>\",\"4\":\"\"}', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Tiempo Fiscal', 'RGPD_ACCOUNT_DELETE_TAX_TIME_DATA_ORDER', '4', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Confirmar contraseña', 'RGPD_ACCOUNT_DELETE_CONFIRM_PASSWORD', 'true', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( '¿Que hacer con los pedidos de mas de X años?', 'RGPD_ACCOUNT_DELETE_ACTION_ORDER', 'anonimizar', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( '¿Que hacer con los comentarios?', 'RGPD_ACCOUNT_DELETE_ACTION_COMMENTS', 'anonimizar', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( '¿Que hacer con las opiniones?', 'RGPD_ACCOUNT_DELETE_ACTION_OPINIONS', 'anonimizar', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( '¿Como quieres anonimizar los Comentarios/Opiniones?', 'RGPD_ACCOUNT_DELETE_ACTION_COMMENTS_OPINIONS', 'nombre', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Texto para cuando anonimizas un pedido', 'RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR', '*****', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Campos del pedido a anonimizar', 'RGPD_ACCOUNT_DELETE_ORDER_ROWS', 'customers_name,customers_company,customers_suburb,customers_street_address,customers_telephone,customers_email_address,delivery_name,delivery_company,delivery_street_address,delivery_suburb,billing_name,billing_company,billing_nif,billing_street_address,billing_suburb', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Texto desactivar cuenta', 'RGPD_ACCOUNT_DELETE_TEXT_DISABLE', '{\"3\":\"<p><span style="font-size:15px;">¡Qué pena que nos abandones!<\/span><\/p><p><span style="font-size:15px;">Si crees que necesitas desactivar temporalmente su cuenta en nuestra tienda online, puedes solicitar que tu cuenta se desactive. Recuerda que podrás reactivar tu cuenta cuando lo necesites con tan solo volverte a loguear con tu contraseña.<br \/>Para continuar con el proceso de desactivación, confírmanos tu contraseña a continuación:<\/span><\/p><p>{CONFIRM_PASSWORD}<\/p><p><span style="font-size: 11px;"><em>En virtud del Derecho al Olvido garantizado por el Reglamento General de Protección de Datos (RGPD), si continuas con el proceso eliminaremos toda la información referente a tu usuario a excepción de los pedidos y\/o facturas inferiores a {TAX_TIME_DATA_ORDER} años por motivos fiscales.<\/em><\/span><\/p>\",\"4\":\"\"}', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Página de información asociada', 'RGPD_TERMS_INFO_ID', '3', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Página de información privacidad', 'RGPD_TERMS_INFO_ID_PRIVACY', '1', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Mostar/ocultar aceptación', 'RGPD_TERMS_SHOW', 'false', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Control de edad', 'RGPD_ACCOUNT_DELETE_DOB', '2', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Texto que se muestra en los formularios para la aceptación', 'RGPD_TERMS_TEXT_CHECK', '{\"3\":\"He leído y acepto los <a href="{LINK}" target="_blank" rel="nofollow"><strong><u>Términos y Condiciones de Uso<\/u><\/strong><\/a> de este sitio\",\"4\":\"I have read and accept the <a href="{LINK}" target="_blank"><strong><u>Terms and Conditions of Use<\/u><\/strong><\/a> of this site\"}', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Eliminar datos clientes inactivos', 'RGPD_ACCOUNT_DELETE_CRON_ACTIVE', 'true', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'A partir de cuantos años se considera inactivo', 'RGPD_ACCOUNT_DELETE_CRON_YEARS', '2', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Avisar al clientes antes de eliminarlo', 'RGPD_ACCOUNT_DELETE_CRON_NOTIFY', 'true', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( '¿Cuantos dias antes para su aviso?', 'RGPD_ACCOUNT_DELETE_CRON_DAYS', '15', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Texto que se muestra en los formularios para la aceptación de comerial', 'RGPD_TERMS_TRADE_TEXT_CHECK', '{\"3\":\"Me gustaría recibir descuentos exclusivos, novedades y tendencias por e-mail. Puedo darme de baja cuando quiera.\",\"4\":\"\"}', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Página de información asociada', 'RGPD_TERMS_TRADE_INFO_ID', '2', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Tooltip Término Comercial', 'RGPD_TERMS_TRADE_TEXT', '{\"3\":\"<p><strong>- Responsable:<\/strong>&nbsp;Nombre Apellido<br \/><strong>- Finalidad:&nbsp;<\/strong>Gestión del envío de promociones y ofertas especiales únicas para nuestros clientes&nbsp;suscritos por email.<br \/><strong>- Legitimación:<\/strong>&nbsp;Consentimiento del interesado (¡sin tí no somos nada!)<br \/><strong>- Destinatarios:<\/strong>&nbsp;Se comunicarán los datos a nuestro sistema y plataforma de email marketing para poder gestionar el envío de boletines al interesado.<br \/><strong>- Derechos:<\/strong>&nbsp;Tienes derecho a Acceder, Rectificar y suprimir los datos, así como otros derechos, como se explica en la información adicional desde el apartado de Mi Cuenta.<br \/><strong>- Información adicional:<\/strong>&nbsp;Puede consultar la información adicional y detalles sobre los Términos y Condiciones Generales así como de la Política de Privacidad y Protección de datos en el enlace facilitado.<\/p>\",\"4\":\"\"}', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Fecha de ejecucion', 'RGPD_ACCOUNT_DELETE_CRON_DATE_EXECUTE', '2018-05-25', '', $aConfigGroup->records['configuration_group_id'] );

			// Creamos la tabla para el backup
			tep_db_query( 'CREATE TABLE IF NOT EXISTS rgpd_backup(
				`id` INT NOT NULL AUTO_INCREMENT ,
				`customers_id` INT NOT NULL ,
				`backup` TEXT NOT NULL ,
				PRIMARY KEY (`id`)) ENGINE = InnoDB' );

			// Creamos la tabla Términos Generales
			tep_db_query( 'CREATE TABLE IF NOT EXISTS rgpd_term_privacy_general(
				`id_term_pivacy_general` INT NOT NULL AUTO_INCREMENT ,
				`version` VARCHAR(50) NOT NULL ,
				PRIMARY KEY (`id_term_pivacy_general`)) ENGINE = InnoDB' );
			tep_db_query( 'CREATE TABLE IF NOT EXISTS rgpd_term_privacy_general_description(
				`id_term_pivacy_general` INT NOT NULL AUTO_INCREMENT ,
				`language_id` INT NOT NULL,
				`title` VARCHAR(255) NOT NULL,
				`info` TEXT NOT NULL,
				`text` TEXT NOT NULL,
				PRIMARY KEY (`id_term_pivacy_general`,`language_id`)) ENGINE = InnoDB' );

			// Creamos la tabla Términos Comerciales
			tep_db_query( 'CREATE TABLE IF NOT EXISTS `rgpd_term_privacy_trade` (
				`id_term_pivacy_trade` INT NOT NULL ,
				`language_id` INT NOT NULL ,
				`title` VARCHAR(255) NOT NULL,
				`info` TEXT NOT NULL,
				`reference` VARCHAR(50) NOT NULL,
				PRIMARY KEY (`id_term_pivacy_trade`, `language_id`)) ENGINE = InnoDB' );

			// Creamos tabla log
			tep_db_query( 'CREATE TABLE IF NOT EXISTS `rgpd_log_account` (
				`id` INT NOT NULL AUTO_INCREMENT,
				`customers_id` INT NOT NULL ,
				`name` VARCHAR(255) NOT NULL ,
				`email` VARCHAR(255) NOT NULL,
				`ip` VARCHAR(20) NOT NULL ,
				`date` timestamp NOT NULL,
				`disable` INT NOT NULL,
				PRIMARY KEY (`id`)) ENGINE = InnoDB' );
			tep_db_query( 'CREATE TABLE IF NOT EXISTS `rgpd_log_term_privacy` (
				`id_log_term_privacy` INT NOT NULL AUTO_INCREMENT,
				`customers_id` INT NOT NULL ,
				`customers_mail` VARCHAR(255) NOT NULL ,
				`ip` VARCHAR(20) NOT NULL ,
				`date` DATETIME NOT NULL ,
				`type` ENUM(\'comercial\',\'general\') NOT NULL ,
				`term_name` VARCHAR(255) NOT NULL ,
				`id_term_pivacy` INT NOT NULL ,
				`status` INT NOT NULL ,
				PRIMARY KEY (`id_log_term_privacy`)) ENGINE = InnoDB' );

			// Creamos la tabla que referencia cliente con termino
			tep_db_query( 'CREATE TABLE IF NOT EXISTS `rgpd_account_term` (
				`id_account_term` INT NOT NULL AUTO_INCREMENT ,
				`customers_id` INT NOT NULL ,
				`id_term_pivacy_trade` INT NOT NULL ,
				PRIMARY KEY (`id_account_term`)) ENGINE = InnoDB');


			// Modificamos tablas
			if( !pharaonix_checkColumTable( array( 'TABLE' => 'customers', 'COLUMN' => 'status_disabled' ) ) )
				tep_db_query( 'ALTER TABLE `customers` ADD `status_disabled` INT NOT NULL DEFAULT "0" AFTER `guest_account`' );

			if( !pharaonix_checkColumTable( array( 'TABLE' => 'customers', 'COLUMN' => 'id_term_pivacy_general' ) ) )
				tep_db_query( 'ALTER TABLE `customers` ADD `id_term_pivacy_general` INT NOT NULL DEFAULT "0" AFTER `guest_account`' );

			// Reset cache
			tools::createCacheFile();

			// Mensajes
			$messageStack->addSession( 'success', 'El módulo <em>RGPD</em> se ha instalado correctamente.', 'success' );

			// Redireccionamos
			tep_redirect( $sUrlPage . '?action=readme' );
		break;

		case 'update':
			// Pasamos todos los post por tep_db_prepare_input
			// array_walk( $_POST, function( $value, $key){ global $_POST; $_POST[$key] = ( $_POST[$key] ); } );

			// Si tenemos id_rows, campos para anonimizar pedidos
			if( array_key_exists( 'id_rows', $_POST ) )
				$_POST['RGPD_ACCOUNT_DELETE_ORDER_ROWS'] = implode( ',', $_POST['id_rows'] );

			// Recorremos post en busca de los campos RGPD para actualizar
			foreach( $_POST as $key => $value )
			{
				// Modificaciones
				switch( $key )
				{
					case 'RGPD_ACCOUNT_DELETE_CRON_DATE_EXECUTE':
						$aValue = explode( '-', $value );
						$value = $aValue[2] . '-' . $aValue[1] . '-' . $aValue[0];
					break;

					case 'RGPD_TERMS_TEXT_CHECK':
					case 'RGPD_TERMS_TRADE_TEXT_CHECK':
					case 'RGPD_TERMS_TRADE_TEXT':
					case 'RGPD_ACCOUNT_DELETE_TEXT_DISABLE':
					case 'RGPD_ACCOUNT_DELETE_TEXT_DELETE':
						$aSave = array();

						foreach( $value as $nLng => $val )
							$aSave[$nLng] = str_replace( array("\r\n", "\n", "\r" ), '', htmlspecialchars( str_replace( '\"', '"', $val ) ) );

						$value = tep_db_input( json_encode( $aSave, JSON_UNESCAPED_UNICODE ) );
					break;
				}

				// Si es campo RGPD_ actualizamos
				if( preg_match( '/^RGPD_/', $key ) )
					tep_db_query( 'UPDATE configuration SET configuration_value = "' . $value . '" WHERE configuration_key = "' . $key . '"' );
			}

			// Si nos encontramos en RGPD_TERMS_SHOW y no existe en post es que hemos desactivado
			if( preg_match( '/^RGPD_TERMS/', $key ) && !array_key_exists( 'RGPD_TERMS_SHOW', $_POST ) )
				tep_db_query( 'UPDATE configuration SET configuration_value = "false" WHERE configuration_key = "RGPD_TERMS_SHOW"' );

			// Si nos encontramos en RGPD_ACCOUNT_DELETE_CONFIRM_PASSWORD y no existe en post es que hemos desactivado
			if( preg_match( '/^RGPD_ACCOUNT/', $key ) && !array_key_exists( 'RGPD_ACCOUNT_DELETE_CONFIRM_PASSWORD', $_POST ) )
				tep_db_query( 'UPDATE configuration SET configuration_value = "false" WHERE configuration_key = "RGPD_ACCOUNT_DELETE_CONFIRM_PASSWORD"' );

			// Si nos encontramos en RGPD_ACCOUNT_DELETE_CRON_ACTIVE y no existe en post es que hemos desactivado
			if( preg_match( '/^RGPD_ACCOUNT_DELETE_CRON/', $key ) && !array_key_exists( 'RGPD_ACCOUNT_DELETE_CRON_ACTIVE', $_POST ) )
				tep_db_query( 'UPDATE configuration SET configuration_value = "false" WHERE configuration_key = "RGPD_ACCOUNT_DELETE_CRON_ACTIVE"' );

			// Si nos encontramos en RGPD_ACCOUNT_DELETE_CRON_NOTIFY y no existe en post es que hemos desactivado
			if( preg_match( '/^RGPD_ACCOUNT_DELETE_CRON/', $key ) && !array_key_exists( 'RGPD_ACCOUNT_DELETE_CRON_NOTIFY', $_POST ) )
				tep_db_query( 'UPDATE configuration SET configuration_value = "false" WHERE configuration_key = "RGPD_ACCOUNT_DELETE_CRON_NOTIFY"' );


			// Mensajes
			$messageStack->addSession( 'success', 'Los datos del módulo <em>RGPD</em> se han actualizado correctamente.', 'success' );

			// Reset cache
			tools::createCacheFile();

			// Redireccionamos
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

		case 'orders_options':
			// Variables
			$sSubtitle = 'Ajustes de pedidos';
			$aButtons = array(
				array( 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ),
				array( 'title' => 'Guardar', 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' )
			);

			// Texto
			$sHtml .= $messageStack->show( array( 'text' => 'Las siguientes opciones modifican el funcionamiento de cómo se trataran los datos cuando es eliminado un cliente.', 'class' => 'info' ) );

			// Formulario
			$sHtml .= '<div class="oeBox column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-file-text"></i> Ajustes de Pedidos </div>';
					$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update' ) . '" class="oeCntd row ax xform xform-horizontal">';
						// Años
						$sHtml .= '<label for="RGPD_ACCOUNT_DELETE_TAX_TIME_DATA_ORDER" class="column a02 tright">Tiempo Fiscal para mantener datos de pedido:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= tep_draw_pull_down_menu( 'RGPD_ACCOUNT_DELETE_TAX_TIME_DATA_ORDER', $aYears, RGPD_ACCOUNT_DELETE_TAX_TIME_DATA_ORDER );
							$sHtml .= '<div class="DFhelp">Selecciona los años que quieres que se mantengan los datos en tus pedidos en sus clientes eliminados.</div>';
						$sHtml .= '</div>';
						$sHtml .= '<div class="xline xline-dashed"></div>';

						// Accion para los pedidos
						$sHtml .= '<label for="RGPD_ACCOUNT_DELETE_ACTION_ORDER" class="column a02 tright">¿Que hacer con los pedidos de mas de X años?:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= tep_draw_pull_down_menu( 'RGPD_ACCOUNT_DELETE_ACTION_ORDER', $aCleans, RGPD_ACCOUNT_DELETE_ACTION_ORDER );
							$sHtml .= '<div class="DFhelp">Selecciona que acción deseas cuando un pedido de un cliente eliminado pase mas del tiempo fiscal configurado.</div>';
						$sHtml .= '</div>';
						$sHtml .= '<div class="xline xline-dashed"></div>';

						// Texto para cuando anonimizas un pedido
						$sHtml .= '<label for="RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR" class="column a02 tright">Texto para cuando anonimizas un pedido:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR" id="RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR" value="' . htmlspecialchars( RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR ) . '"/>';
							$sHtml .= '<div class="DFhelp">Texto que se sustituira los campos del pedido que queremos anonimizar.</div>';
						$sHtml .= '</div>';
						$sHtml .= '<div class="xline xline-dashed"></div>';

						// Campos para anonimizar
						$sHtml .= '<label class="column a02 tright">Campos del pedido a anonimizar:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<div class="column a12 ax row amiddle">';
								$aOrdersRowsCheck = explode( ',', RGPD_ACCOUNT_DELETE_ORDER_ROWS );

								foreach( $aOrdersRows as $sId => $sValue )
								{
									$sHtml .= '<div class="column a04" style="margin-bottom: 10px;	">';
										$sHtml .= '<input type="checkbox" name="id_rows[]" id="id_rows_' . $sId . '" ' . (in_array( $sId, $aOrdersRowsCheck ) ? 'checked="checked"' : '') . ' value="' . $sId . '"/><label for="id_rows_' . $sId . '"><span></span> ' . $sValue . '</label>';
									$sHtml .= '</div>';
								}

							$sHtml .= '</div>';
							$sHtml .= '<div class="DFhelp">Selecciona los campos que deseas anonimizar del pedido.</div>';
						$sHtml .= '</div>';
						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<input type="submit" style="display: none;" />';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		break;

		case 'account_delete':
			// Variables
			$sSubtitle = 'Ajustes de Clientes';
			$aButtons = array(
				array( 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ),
				array( 'title' => 'Guardar', 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' )
			);

			// Texto
			$sHtml .= $messageStack->show( array( 'text' => 'Las siguientes opciones modifican el funcionamiento de cómo se trataran los datos cuando es eliminado un cliente.', 'class' => 'info' ) );

			// Formulario
			$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update' ) . '">';
				$sHtml .= '<div class="oeBox column a12 row ax">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-user"></i>Ajustes de Clientes </div>';
						$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
							// Texto Eliminar Cuenta
							$sHtml .= tools::getInputLanguages( 'RGPD_ACCOUNT_DELETE_TEXT_DELETE', 'Texto Eliminar Cuenta:', json_decode( str_replace( array( '\"' ), array('"'), RGPD_ACCOUNT_DELETE_TEXT_DELETE ), true ), 'Texto que se muestra en el apartado de Mi Cuenta > Eliminar Cuenta. En él podrás personalizar lo que necesites siempre que mantengas las variables de acción.<br/><b>{CONFIRM_PASSWORD}</b> Variable que se utiliza para reemplazar por el formulario de confirmar contraseña si se requiere.<br/><b>{TAX_TIME_DATA_ORDER}</b> Puedes utilizarlo para mostrar el tiempo fiscal que se mantendrán los datos del pedido', '', 10, false );
							$sHtml .= '<div class="xline xline-dashed"></div>';

							// Texto desactivar cuenta
							$sHtml .= tools::getInputLanguages( 'RGPD_ACCOUNT_DELETE_TEXT_DISABLE', 'Texto desactivar cuenta:', json_decode( str_replace( array( '\"' ), array('"'), RGPD_ACCOUNT_DELETE_TEXT_DISABLE ), true ), 'Texto que se muestra en el apartado de Mi Cuenta > Desactivar Cuenta. En él podrás personalizar lo que necesites siempre que mantengas las variables de acción.<br/><b>{CONFIRM_PASSWORD}</b> Variable que se utiliza para remplazar por el formulario de confirmar contraseña si se requiere.<br/><b>{TAX_TIME_DATA_ORDER}</b> Puedes utilizarlo para mostrar el tiempo fiscal que se mantendrán los datos del pedido', '', 10, false );
							$sHtml .= '<div class="xline xline-dashed"></div>';

							// COntrol mayor de edad
							$sHtml .= '<label for="RGPD_ACCOUNT_DELETE_DOB" class="column a02 tright">Verificación Edad:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= tep_draw_pull_down_menu( 'RGPD_ACCOUNT_DELETE_DOB', array( array( 'id' => '1', 'text' => 'Lightbox al crear la cuenta' ), array( 'id' => '2', 'text' => 'Solicitar fecha al crear la cuenta' ) ), RGPD_ACCOUNT_DELETE_DOB );
								$sHtml .= '<div class="DFhelp">Selecciona la opción que deseas para la Verificación de Edad para cumplir con la RGPD en tu tienda online. Según la nueva normativa las personas menores de 16 años no pueden comprar sin la autorización legal correspondiente.</div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="xline xline-dashed"></div>';

							// Pedir conraseña para eliminar/desactivar
							$sHtml .= '<label for="RGPD_ACCOUNT_DELETE_CONFIRM_PASSWORD" class="column a02 tright inline">Confirmar contraseña para eliminar/desactivar cuenta:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="checkbox" name="RGPD_ACCOUNT_DELETE_CONFIRM_PASSWORD" id="RGPD_ACCOUNT_DELETE_CONFIRM_PASSWORD" ' . (RGPD_ACCOUNT_DELETE_CONFIRM_PASSWORD == 'true' ? 'checked="checked"' : '') . ' value="true"/><label for="RGPD_ACCOUNT_DELETE_CONFIRM_PASSWORD"><span></span></label>';
								$sHtml .= '<div class="DFhelp">Si quieres puedes pedir una confirmación mediante contraseña para eliminar/desactivar cuenta, así estara más seguro el cliente de querer hacerlo y no hacerlo por accidente.</div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="xline xline-dashed"></div>';

							// Proceder con los comentarios de productos
							$sHtml .= '<label for="RGPD_ACCOUNT_DELETE_ACTION_COMMENTS" class="column a02 tright">¿Que hacer con los comentarios?:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= tep_draw_pull_down_menu( 'RGPD_ACCOUNT_DELETE_ACTION_COMMENTS', $aCleans, RGPD_ACCOUNT_DELETE_ACTION_COMMENTS );
								$sHtml .= '<div class="DFhelp">Selecciona que acción deseas para los comentario de productos de un cliente eliminado.</div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="xline xline-dashed"></div>';

							// Proceder con las opiniones de clientes
							$sHtml .= '<label for="RGPD_ACCOUNT_DELETE_ACTION_OPINIONS" class="column a02 tright">¿Que hacer con las opiniones?:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= tep_draw_pull_down_menu( 'RGPD_ACCOUNT_DELETE_ACTION_OPINIONS', $aCleans, RGPD_ACCOUNT_DELETE_ACTION_OPINIONS );
								$sHtml .= '<div class="DFhelp">Selecciona que acción deseas para las opiniones de un cliente eliminado.</div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="xline xline-dashed"></div>';

							// Proceder con las opiniones de clientes
							$sHtml .= '<label for="RGPD_ACCOUNT_DELETE_ACTION_COMMENTS_OPINIONS" class="column a02 tright">¿Como quieres anonimizar los Comentarios/Opiniones?:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= tep_draw_pull_down_menu( 'RGPD_ACCOUNT_DELETE_ACTION_COMMENTS_OPINIONS', array( array( 'id' => 'nombre', 'text' => 'Dejar solo su nombre' ), array( 'id' => 'anonimo', 'text' => 'Poner como cliente anónimo' ) ), RGPD_ACCOUNT_DELETE_ACTION_COMMENTS_OPINIONS );
								$sHtml .= '<div class="DFhelp">Selecciona que acción deseas para las opiniones y comenarios anonimizados de un cliente eliminado.</div>';
							$sHtml .= '</div>';

							$sHtml .= '<input type="submit" style="display: none;" />';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				$sHtml .= '<div class="oeBox column a12 row ax" style="margin-top: 20px;">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-cog"></i> Tarea progamada eliminar clientes inactivos </div>';
						$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
							// Eliminar clientes inactivos
							$sHtml .= '<label for="RGPD_ACCOUNT_DELETE_CRON_ACTIVE" class="column a02 tright inline">Eliminar datos clientes inactivos:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="checkbox" name="RGPD_ACCOUNT_DELETE_CRON_ACTIVE" id="RGPD_ACCOUNT_DELETE_CRON_ACTIVE" ' . (RGPD_ACCOUNT_DELETE_CRON_ACTIVE == 'true' ? 'checked="checked"' : '') . ' value="true"/><label for="RGPD_ACCOUNT_DELETE_CRON_ACTIVE"><span></span></label>';
								$sHtml .= '<div class="DFhelp">Si deseas eliminar datos de tus clientes inactivos activa está casilla.</div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="xline xline-dashed"></div>';

							// Años
							$sHtml .= '<label for="RGPD_ACCOUNT_DELETE_CRON_YEARS" class="column a02 tright">A partir de cuantos años se considera inactivo:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= tep_draw_pull_down_menu( 'RGPD_ACCOUNT_DELETE_CRON_YEARS', $aYears, RGPD_ACCOUNT_DELETE_CRON_YEARS );
								$sHtml .= '<div class="DFhelp">Selecciona los años que quieres que un cliente se considera inactivo.</div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="xline xline-dashed"></div>';

							// Avisar cliente
							$sHtml .= '<label for="RGPD_ACCOUNT_DELETE_CRON_NOTIFY" class="column a02 tright inline">Avisar al clientes antes de eliminarlo:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="checkbox" name="RGPD_ACCOUNT_DELETE_CRON_NOTIFY" id="RGPD_ACCOUNT_DELETE_CRON_NOTIFY" ' . (RGPD_ACCOUNT_DELETE_CRON_NOTIFY == 'true' ? 'checked="checked"' : '') . ' value="true"/><label for="RGPD_ACCOUNT_DELETE_CRON_NOTIFY"><span></span></label>';
								$sHtml .= '<div class="DFhelp">¿Deseas avisar a tu cliente que debido a su inactividad su cuenta va ser eliminada?.</div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="xline xline-dashed"></div>';

							// Recreamos los dias
							for( $nCont = 1; $nCont <= 30; $nCont++ )
								$aDays[] = array( 'id' => $nCont, 'text' => $nCont );

							// Dias
							$sHtml .= '<label for="RGPD_ACCOUNT_DELETE_CRON_DAYS" class="column a02 tright">¿Cuantos dias antes para su aviso?:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= tep_draw_pull_down_menu( 'RGPD_ACCOUNT_DELETE_CRON_DAYS', $aDays, RGPD_ACCOUNT_DELETE_CRON_DAYS );
								$sHtml .= '<div class="DFhelp">Cantidad de dias antes de su eliminación se le enviara un email notificandole.</div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="xline xline-dashed"></div>';

							// Fecha para su ejecucion
							$aValue = explode( '-', RGPD_ACCOUNT_DELETE_CRON_DATE_EXECUTE );

							$sHtml .= '<label for="RGPD_ACCOUNT_DELETE_CRON_NOTIFY" class="column a02 tright inline">Fecha de ejecución:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input value="' . $aValue[2] . '-' . $aValue[1] . '-' . $aValue[0] . '" data-autoupdate="true" name="RGPD_ACCOUNT_DELETE_CRON_DATE_EXECUTE" readonly="readonly" class="form-date-simple" type="text" />';
								$sHtml .= '<div class="DFhelp">Fecha desde la que sera valida la ejecución.</div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="xline xline-dashed"></div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</form>';
		break;

		case 'logs_account_delete':
			// Variables
			$aGetId = tep_db_prepare_input( $_GET['id'] );
			$aPostId = tep_db_prepare_input( $_POST['id'] );
			$sIds = '';

			// Si nos envian por get creamos el array
			if( $aGetId != '' )
				$aPostId = array( $aGetId );

			// Recorremos los id
			foreach( $aPostId as $sId )
				$sIds .= $sId . ',';

			// Si tenemos id eliminamos
			if( $sIds != '' )
				tep_db_query( 'delete from  rgpd_log_account where id in(' . substr( $sIds, 0, -1 ) . ')' );

			// Redireccionamos
			$messageStack->addSession( 'success', 'Los registros se han eliminado correctamente', 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

		case 'logs_account':
			// Configuracion
			$sSubtitle = 'Logs cuenta cliente';
			$aButtons = array(
				array( 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' )
			);
			$aTypes = array(
				array( 'id' => '', 'text' => 'Todos' ),
				array( 'id' => '1', 'text' => 'Desactivados' ),
				array( 'id' => '0', 'text' => 'Activos' )
			);

			// Html para el boton masivo
			$sHtmlActionMasivo = '<label class="column afluid">Aplicar acción:&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>Acciones</div>
				<ul class="down drch">
					<li><a data-question="¿Realmente deseas eliminar los registros?" data-action="' . tep_href_link( $sUrlPage, 'action=logs_account_delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash-o"></i>Eliminar registros</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

			// Variables
			$aFiler = array( 'search' => '', 'search_type' => '', 'search_date' => '' );
			$aAuxFilter = array_key_exists( 'filter', $_GET ) && is_array($_GET['filter']) ? $_GET['filter'] : (array_key_exists( 'filter', $_POST ) && is_array($_POST['filter']) ? $_POST['filter'] : array());
			$sWhere = '';

			// Limpiamos variables get filter
			array_walk( $aFiler, function( $value, $key){ global $aFiler, $aAuxFilter; $aFiler[$key] = tep_db_prepare_input( array_key_exists( $key, $aAuxFilter ) ? $aAuxFilter[$key] : $aFiler[$key] ); } );

			// Where
			if( $aFiler['search'] != '' || $aFiler['search_type'] != '' || $aFiler['search_date'] != '' || $aFiler['search_ip'] )
				$sWhere = 'where ';

			if( $aFiler['search'] != '' )
				$sWhere .= ( $sWhere != 'where ' ? ' and' : '') . ' (LOWER(email) LIKE "%' . strtolower( $aFiler['search'] ) . '%" OR LOWER(name) LIKE "%' . strtolower( $aFiler['search'] ) . '%" OR LOWER(ip) LIKE "%' . strtolower( $aFiler['search'] ) . '%")';

			if( $aFiler['search_type'] != '' )
				$sWhere .= ( $sWhere != 'where ' ? ' and' : '') . ' disable = "' . $aFiler['search_type'] . '"';

			if( $aFiler['search_date'] != '' )
			{
				$aValue = explode( ' - ', $aFiler['search_date'] );
				$aValue[0] = date::changeDate( $aValue[0], 'espanol', 'y/m/d' );
				$aValue[1] = date::changeDate( $aValue[1], 'espanol', 'y/m/d' );

				$sWhere .= ( $sWhere != 'where ' ? ' and' : '') . ' date >= "' . $aValue[0] . '" AND date <= "' . $aValue[1] . '"';
			}

			// Order by
			if( $sGetOrderby == 'name' )
				$sOrderby = 'name ' . $sGetSort;
			else if( $sGetOrderby == 'date' )
				$sOrderby = 'date ' . $sGetSort;
			else if( $sGetOrderby == 'email' )
				$sOrderby = 'email ' . $sGetSort;
			else if( $sGetOrderby == 'disable' )
				$sOrderby = 'disable ' . $sGetSort;
			else if( $sGetOrderby == 'ip' )
				$sOrderby = 'ip ' . $sGetSort;
			else
				$sOrderby = 'date DESC';

			// Sql
			$sSql = 'SELECT id, name, email, DATE_FORMAT( date, "%d/%m/%Y %H:%i:%s" ) as date, disable, ip
					 FROM rgpd_log_account
					 ' . $sWhere . ' ORDER BY ' . $sOrderby;

			// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
			$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

			// Sql para el count
			$sSqlCount = 'SELECT COUNT(table_aux.id) as total FROM (' . $sSql . ') as table_aux';

			// Datos y paginacion
			$aDatoSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
			$aDatos = tep_db_query( $sSql );

			// Mensajes comprobamos si tenemos datos
			if( tep_db_num_rows( $aDatos ) <= 0 )
			{
				if( $sWhere != '' )
					$sHtml .= $messageStack->show( array( 'text' => 'El filtro establecido no contiene datos.', 'class' => 'warning' ) );
				else
					$sHtml .= $messageStack->show( array( 'text' => 'No existe ningun registro para mostrar.', 'class' => 'warning' ) );
			}

			$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> Logs cuenta cliente</div>';
					$sHtml .= '<form method="post" action="' . tep_href_link( $sUrlPage, 'action=logs_account' ) . '" class="oeCntd row ax">';
						$sHtml .= '<div class="oeBoxFltr column a12 ax row">';
							$sHtml .= '<div class="column a09 row ax amiddle input-search">';
								$sHtml .= '<label class="column">Buscar: </label> <div class="column"><input type="text" name="filter[search]" placeholder="Introduce búsqueda nombre cliente o email" value="' . $aFiler['search'] . '" autofocus/> <i class="fa fa-search"></i></div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="column a03 tright">';
								$sHtml .= ($sWhere != '' ? '<a title="Quitar filtro" href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'filter' ) ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa-close"></i></a> ' : '');
								$sHtml .= '<a href="#fltr-lstd" title="Filtrar registros" class="xbutton small hv9 verde-turquesa mgp-inln"><i class="fa fa-filter"></i></a>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<table class="xform">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
									$sHtml .= '<th class="sort" width="150">' . tableSetSort( 'email', 'E-mail' ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'name', 'Nombre' ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'ip', 'IP' ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'date', 'Fecha' ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'disable', 'Estado' ) . '</th>';
									$sHtml .= '<th width="125">Acciones</th>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';

								while( $aDato = tep_db_fetch_array( $aDatos ) )
								{
									$sHtml .= '<tr>';
										$sHtml .= '<td class="chck" align="center"><input type="checkbox" id="id_' . $aDato['id'] . '" name="id[]" value="' . $aDato['id'] . '"/><label for="id_' . $aDato['id'] . '"><span></span></label></td>';
										$sHtml .= '<td>' . $aDato['email'] . '</td>';
										$sHtml .= '<td>' . $aDato['name'] . '</td>';
										$sHtml .= '<td>' . $aDato['ip'] . '</td>';
										$sHtml .= '<td>' . $aDato['date'] . '</td>';
										if( $aDato['disable'] == 1 )
											$sHtml .= '<td>Desactivo su cuenta</td>';
										else
											$sHtml .= '<td>Activo su cuenta</td>';
										$sHtml .= '<td>';
											$sHtml .= '<div class="drop xfselect">';
												$sHtml .= '<div>Acciones</div>';
												$sHtml .= '<ul class="down">';
													$sHtml .= '<li><a data-confirm="¿Realmente deseas eliminar el registro?" href="' . tep_href_link( $sUrlPage, 'action=logs_account_delete&id=' . $aDato['id'] ) . '" class="hv"><i class="fa fa-trash-o"></i>Eliminar registro</a></li>';
												$sHtml .= '</ul>';
											$sHtml .= '</div>';
										$sHtml .= '</td>';
									$sHtml .= '</tr>';
								}

							$sHtml .= '</tbody>';
						$sHtml .= '</table>';

						// Paginación
						$sHtml .= $aDatoSplit->showPaginateTable( tep_get_all_get_params( array('page') ), 'page', $sHtmlActionMasivo, 'solenopsis' );

						$sHtml .= '</div>';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';

			// Filtro
			$sHtml .= '<form action="' . tep_href_link( $sUrlPage ) . '" method="get" id="fltr-lstd" class="vntn-form mfp-hide zoom-anim-dialog oeBox mfp-white">';
				$sHtml .= '<input type="hidden" name="action" value="logs_account" />';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> Logs cuenta cliente</div>';
					$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= '<label for="search" class="column a02 tright">Buscar:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="filter[search]" placeholder="Introduce búsqueda nombre cliente o email" value="' . $aFiler['search'] . '"/> ';
						$sHtml .= '</div>';
						$sHtml .= '<div class="xline xline-dashed"></div>';
						$sHtml .= '<label for="search" class="column a02 tright">Estado:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= tep_draw_pull_down_menu( 'filter[search_type]', $aTypes, $aFiler['search_type'] );
						$sHtml .= '</div>';
						$sHtml .= '<div class="xline xline-dashed"></div>';
						$sHtml .= '<label for="search" class="column a02 tright">Fecha:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input value="' . $aFiler['search_date'] . '" data-autoupdate="true" name="filter[search_date]" readonly="readonly" class="form-datetime-range" type="text" />';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-none"></div>';
						$sHtml .= '<div class="column a12 tright">';
							$sHtml .= ($sWhere != '' ? '<a href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'filter' ) ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa-close"></i> Eliminar</a> ' : '');
							$sHtml .= '<div class="xbutton verde hv9 small"><input type="submit"/><span class="fa fa-filter"></span> Filtrar</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</form>';
		break;

		case 'logs_terms_delete':
			// Variables
			$aGetId = tep_db_prepare_input( $_GET['id'] );
			$aPostId = tep_db_prepare_input( $_POST['id'] );
			$sIds = '';

			// Si nos envian por get creamos el array
			if( $aGetId != '' )
				$aPostId = array( $aGetId );

			// Recorremos los id
			foreach( $aPostId as $sId )
				$sIds .= $sId . ',';

			// Si tenemos id eliminamos
			if( $sIds != '' )
				tep_db_query( 'delete from rgpd_log_term_privacy where id_log_term_privacy in(' . substr( $sIds, 0, -1 ) . ')' );

			// Redireccionamos
			$messageStack->addSession( 'success', 'Los registros se han eliminado correctamente', 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

		case 'logs_terms':
			// Configuracion
			$sSubtitle = 'Logs cuenta cliente';
			$aButtons = array(
				array( 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' )
			);
			$aTypes = array(
				array( 'id' => '', 'text' => 'Todos' ),
				array( 'id' => 'general', 'text' => 'Términos general' ),
				array( 'id' => 'comercial', 'text' => 'Términos Comerciales' )
			);

			// Html para el boton masivo
			$sHtmlActionMasivo = '<label class="column afluid">Aplicar acción:&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>Acciones</div>
				<ul class="down drch">
					<li><a data-question="¿Realmente deseas eliminar los registros?" data-action="' . tep_href_link( $sUrlPage, 'action=logs_terms_delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash-o"></i>Eliminar registros</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

			// Variables
			$aFiler = array( 'search' => '', 'search_type' => '', 'search_date' => '' );
			$aAuxFilter = array_key_exists( 'filter', $_GET ) && is_array($_GET['filter']) ? $_GET['filter'] : (array_key_exists( 'filter', $_POST ) && is_array($_POST['filter']) ? $_POST['filter'] : array());
			$sWhere = '';

			// Limpiamos variables get filter
			array_walk( $aFiler, function( $value, $key){ global $aFiler, $aAuxFilter; $aFiler[$key] = tep_db_prepare_input( array_key_exists( $key, $aAuxFilter ) ? $aAuxFilter[$key] : $aFiler[$key] ); } );

			// Where
			if( $aFiler['search'] != '' || $aFiler['search_type'] != '' || $aFiler['search_date'] != '' || $aFiler['search_ip'] )
				$sWhere = 'where ';

			if( $aFiler['search'] != '' )
				$sWhere .= ( $sWhere != 'where ' ? ' and' : '') . ' (LOWER(customers_mail) LIKE "%' . strtolower( $aFiler['search'] ) . '%" OR LOWER(ip) LIKE "%' . strtolower( $aFiler['search'] ) . '%" OR LOWER(term_name) LIKE "%' . strtolower( $aFiler['search'] ) . '%")';

			if( $aFiler['search_type'] != '' )
				$sWhere .= ( $sWhere != 'where ' ? ' and' : '') . ' type = "' . $aFiler['search_type'] . '"';

			if( $aFiler['search_date'] != '' )
			{
				$aValue = explode( ' - ', $aFiler['search_date'] );
				$aValue[0] = date::changeDate( $aValue[0], 'espanol', 'y/m/d' );
				$aValue[1] = date::changeDate( $aValue[1], 'espanol', 'y/m/d' );

				$sWhere .= ( $sWhere != 'where ' ? ' and' : '') . ' date >= "' . $aValue[0] . '" AND date <= "' . $aValue[1] . '"';
			}

			// Order by
			if( $sGetOrderby == 'email' )
				$sOrderby = 'email ' . $sGetSort;
			else if( $sGetOrderby == 'date' )
				$sOrderby = 'date ' . $sGetSort;
			else if( $sGetOrderby == 'ip' )
				$sOrderby = 'ip ' . $sGetSort;
			else if( $sGetOrderby == 'type' )
				$sOrderby = 'type ' . $sGetSort;
			else if( $sGetOrderby == 'status' )
				$sOrderby = 'status ' . $sGetSort;
			else
				$sOrderby = 'date DESC';

			// Sql
			$sSql = 'SELECT id_log_term_privacy, customers_id, customers_mail, DATE_FORMAT( date, "%d/%m/%Y %H:%i:%s" ) as date, ip, type, term_name, status
					 FROM rgpd_log_term_privacy
					 ' . $sWhere . ' ORDER BY ' . $sOrderby;

			// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
			$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

			// Sql para el count
			$sSqlCount = 'SELECT COUNT(table_aux.id_log_term_privacy) as total FROM (' . $sSql . ') as table_aux';

			// Datos y paginacion
			$aDatoSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
			$aDatos = tep_db_query( $sSql );

			// Mensajes comprobamos si tenemos datos
			if( tep_db_num_rows( $aDatos ) <= 0 )
			{
				if( $sWhere != '' )
					$sHtml .= $messageStack->show( array( 'text' => 'El filtro establecido no contiene datos.', 'class' => 'warning' ) );
				else
					$sHtml .= $messageStack->show( array( 'text' => 'No existe ningun registro para mostrar.', 'class' => 'warning' ) );
			}

			$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> Logs Términos</div>';
					$sHtml .= '<form method="post" action="' . tep_href_link( $sUrlPage, 'action=logs_terms' ) . '" class="oeCntd row ax">';
						$sHtml .= '<div class="oeBoxFltr column a12 ax row">';
							$sHtml .= '<div class="column a09 row ax amiddle input-search">';
								$sHtml .= '<label class="column">Buscar: </label> <div class="column"><input type="text" name="filter[search]" placeholder="Introduce búsqueda email, ip o término" value="' . $aFiler['search'] . '" autofocus/> <i class="fa fa-search"></i></div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="column a03 tright">';
								$sHtml .= ($sWhere != '' ? '<a title="Quitar filtro" href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'filter' ) ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa-close"></i></a> ' : '');
								$sHtml .= '<a href="#fltr-lstd" title="Filtrar registros" class="xbutton small hv9 verde-turquesa mgp-inln"><i class="fa fa-filter"></i></a>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<table class="xform">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
									$sHtml .= '<th class="sort" width="150">' . tableSetSort( 'customers_mail', 'E-mail' ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'ip', 'IP' ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'date', 'Fecha' ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'type', 'Tipo' ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'term_name', 'Término' ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'status', 'Estado' ) . '</th>';
									$sHtml .= '<th width="125">Acciones</th>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';

								while( $aDato = tep_db_fetch_array( $aDatos ) )
								{
									$sHtml .= '<tr>';
										$sHtml .= '<td class="chck" align="center"><input type="checkbox" id="id_' . $aDato['id_log_term_privacy'] . '" name="id[]" value="' . $aDato['id_log_term_privacy'] . '"/><label for="id_' . $aDato['id_log_term_privacy'] . '"><span></span></label></td>';
										$sHtml .= '<td>' . $aDato['customers_mail'] . '</td>';
										$sHtml .= '<td>' . $aDato['ip'] . '</td>';
										$sHtml .= '<td>' . $aDato['date'] . '</td>';
										$sHtml .= '<td>' . $aDato['type'] . '</td>';
										$sHtml .= '<td>' . $aDato['term_name'] . '</td>';
										$sHtml .= '<td>';
											if( $aDato['status'] == '1')
												$sHtml .= 'Activo';
											else
												$sHtml .= 'Desactivo';
										$sHtml .= '</td>';
										$sHtml .= '<td>';
											$sHtml .= '<div class="drop xfselect">';
												$sHtml .= '<div>Acciones</div>';
												$sHtml .= '<ul class="down">';
													$sHtml .= '<li><a data-confirm="¿Realmente deseas eliminar el registro?" href="' . tep_href_link( $sUrlPage, 'action=logs_terms_delete&id=' . $aDato['id_log_term_privacy'] ) . '" class="hv"><i class="fa fa-trash-o"></i>Eliminar registro</a></li>';
												$sHtml .= '</ul>';
											$sHtml .= '</div>';
										$sHtml .= '</td>';
									$sHtml .= '</tr>';
								}

							$sHtml .= '</tbody>';
						$sHtml .= '</table>';

						// Paginación
						$sHtml .= $aDatoSplit->showPaginateTable( tep_get_all_get_params( array('page') ), 'page', $sHtmlActionMasivo, 'solenopsis' );

						$sHtml .= '</div>';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';

			// Filtro
			$sHtml .= '<form action="' . tep_href_link( $sUrlPage ) . '" method="get" id="fltr-lstd" class="vntn-form mfp-hide zoom-anim-dialog oeBox mfp-white">';
				$sHtml .= '<input type="hidden" name="action" value="logs_terms" />';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> Logs Términos</div>';
					$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= '<label for="search" class="column a02 tright">Buscar:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="filter[search]" placeholder="Introduce búsqueda email, ip o término" value="' . $aFiler['search'] . '"/> ';
						$sHtml .= '</div>';
						$sHtml .= '<div class="xline xline-dashed"></div>';
						$sHtml .= '<label for="search" class="column a02 tright">Tipo:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= tep_draw_pull_down_menu( 'filter[search_type]', $aTypes, $aFiler['search_type'] );
						$sHtml .= '</div>';
						$sHtml .= '<div class="xline xline-dashed"></div>';
						$sHtml .= '<label for="search" class="column a02 tright">Fecha:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input value="' . $aFiler['search_date'] . '" data-autoupdate="true" name="filter[search_date]" readonly="readonly" class="form-datetime-range" type="text" />';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-none"></div>';
						$sHtml .= '<div class="column a12 tright">';
							$sHtml .= ($sWhere != '' ? '<a href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'filter' ) ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa-close"></i> Eliminar</a> ' : '');
							$sHtml .= '<div class="xbutton verde hv9 small"><input type="submit"/><span class="fa fa-filter"></span> Filtrar</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</form>';
		break;

		case 'terms_trade_options':
			// Variables
			$sSubtitle = 'Términos Comerciales opciones';
			$aButtons = array(
				array( 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage, 'action=terms_trade' ), 'icon' => 'fa-arrow-left' ),
				array( 'title' => 'Guardar', 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' )
			);

			// Obtenemos paginas de información
			$aInfos = pharaonix_getArrayAssociativeSql( 'SELECT information_id, information_title FROM information WHERE visible = 1 and language_id = 3 and information_group_id = 1 ORDER BY sort_order', 'information_id', 'information_title' );

			// Formulario
			$sHtml .= '<div class="oeBox column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-gavel"></i> Términos Comerciales opciones </div>';
					$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update' ) . '" class="oeCntd row ax xform xform-horizontal">';
						// Proceder con las opiniones de clientes
						$sHtml .= '<label for="RGPD_TERMS_TRADE_INFO_ID" class="column a02 tright">Página de información asociada:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= tep_draw_pull_down_menu( 'RGPD_TERMS_TRADE_INFO_ID', $aInfos, RGPD_TERMS_TRADE_INFO_ID );
							$sHtml .= '<div class="DFhelp">Selecciona lá pagina de información donde mostrara los Términos sobre la parte comercial.</div>';
						$sHtml .= '</div>';
						$sHtml .= '<div class="xline xline-dashed"></div>';

						// Texto tooltip
						$sHtml .= tools::getInputLanguages( 'RGPD_TERMS_TRADE_TEXT', 'Tooltip Término:', json_decode( str_replace( array( '\"' ), array('"'), RGPD_TERMS_TRADE_TEXT ), true ), 'En este campo debes de introducir la primera capa de aceptación de los Términos Comerciales, donde especificas rápidamente las condiciones para el cliente sin que tenga que leer el documento de términos completo. Aparecerá un icono de ayuda al lado de cada checkbox de aceptación que al superponer el ratón mostrará esta información.', '', 10, false );
						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= tools::getInputLanguages( 'RGPD_TERMS_TRADE_TEXT_CHECK', 'Texto aceptación:', json_decode( str_replace( array( '\"', "\\\'" ), array('"', "'"), RGPD_TERMS_TRADE_TEXT_CHECK ), true ), 'Texto que se muestra en los formularios para la aceptación, utiliza {LINK} para que el enlace tenga acceso a la página de información' );

						$sHtml .= '<input type="submit" style="display: none;" />';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		break;

		case 'terms_general_options':
			// Variables
			$sSubtitle = 'Términos Generales opciones';
			$aButtons = array(
				array( 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage, 'action=terms_general' ), 'icon' => 'fa-arrow-left' ),
				array( 'title' => 'Guardar', 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' )
			);

			// Texto
			$sHtml .= $messageStack->show( array( 'text' => 'Las siguientes opciones modifican el funcionamiento de como se mostraran y fncionara los Términos Generales de su tienda.', 'class' => 'info' ) );

			// Obtenemos paginas de información
			$aInfos = pharaonix_getArrayAssociativeSql( 'SELECT information_id, information_title FROM information WHERE visible = 1 and language_id = 3 and information_group_id = 1 ORDER BY sort_order', 'information_id', 'information_title' );

			// Formulario
			$sHtml .= '<div class="oeBox column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-gavel"></i> Términos Generales opciones </div>';
					$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update' ) . '" class="oeCntd row ax xform xform-horizontal">';
						// Proceder con las opiniones de clientes
						$sHtml .= '<label for="RGPD_TERMS_INFO_ID" class="column a02 tright">Página de información asociada:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= tep_draw_pull_down_menu( 'RGPD_TERMS_INFO_ID', $aInfos, RGPD_TERMS_INFO_ID );
							$sHtml .= '<div class="DFhelp">Selecciona lá pagina de información donde mostrara los Términos y condiciones de su tienda.</div>';
						$sHtml .= '</div>';
						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="RGPD_TERMS_INFO_ID_PRIVACY" class="column a02 tright">Página de información asociada:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= tep_draw_pull_down_menu( 'RGPD_TERMS_INFO_ID_PRIVACY', $aInfos, RGPD_TERMS_INFO_ID_PRIVACY );
							$sHtml .= '<div class="DFhelp">Selecciona lá pagina de información donde mostrara las políticas de privacidad.</div>';
						$sHtml .= '</div>';
						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= tools::getInputLanguages( 'RGPD_TERMS_TEXT_CHECK', 'Texto aceptación:', json_decode( str_replace( array( '\"', "\\\'" ), array('"', "'"), RGPD_TERMS_TEXT_CHECK ), true ), 'Texto que se muestra en los formularios para la aceptación, utiliza {LINK} para que el enlace tenga acceso a las condiciones de uso y {LINK_PRIVACY} para las políticas de privacidad' );
						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="RGPD_ACCOUNT_DELETE_CONFIRM_PASSWORD" class="column a02 tright inline">Mostrar/Ocultar Aceptación de los Términos:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="checkbox" name="RGPD_TERMS_SHOW" id="RGPD_TERMS_SHOW" ' . (RGPD_TERMS_SHOW == 'true' ? 'checked="checked"' : '') . ' value="true"/><label for="RGPD_TERMS_SHOW"><span></span></label>';
							$sHtml .= '<div class="DFhelp">Activar para mostrar o desactiva para ocultar. Una vez que el usuario ya tiene la última versión aceptada a nivel de cuenta, si el cliente está identificado podemos hacer que no tenga que estar aceptando esta política cada vez que realiza un pedido por ejemplo puesto que ya tenemos su aceptación previamente.</div>';
						$sHtml .= '</div>';
						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<input type="submit" style="display: none;" />';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		break;

		case 'terms_general_update':
		case 'terms_general_add_form':
			// Variables
			$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
			$aMessageError = array();
			$sSubtitle = ($sGetId != '' ? 'Editar' : 'Añadir') . ' termino general';
			$aButtons = array(
				array( 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage, 'action=terms_general' ), 'icon' => 'fa-arrow-left' ),
				array( 'title' => 'Guardar', 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' )
			);
			$aRecord = array();

			// Idioma, obtenemos solo los id
			$aLanguageCopy = $aLanguage;
			array_walk( $aLanguageCopy, function( $value, $key){ global $aLanguageCopy; $aLanguageCopy[$key] = ''; } );

			// Si estamos editando
			if( $sGetId != false )
			{
				// Obtenemos el registro
				$aRecord = pharaonix_queryOne( 'SELECT * FROM rgpd_term_privacy_general WHERE id_term_pivacy_general = "' . (int)$sGetId . '"' );

				// Si no existe
				if( $aRecord->num_rows == 0 )
				{
					$messageStack->addSession( 'success', 'El registro que intentas editar no existe', 'error' );
					tep_redirect( tep_href_link(  $sUrlPage ) );
				}

				// Registro
				$aRecord = $aRecord->records;

				// Si vamos a insertar creamos un array falso
				$aRecord['title'] = $aLanguageCopy;
				$aRecord['text'] = $aLanguageCopy;
				$aRecord['info'] = $aLanguageCopy;

				// Si contenemos datos obtenemos los textos
				$aRows = tep_db_query( 'select title, text, info, language_id from rgpd_term_privacy_general_description where id_term_pivacy_general = "' . $sGetId . '"' );

				while( $aRow = tep_db_fetch_array( $aRows ) )
				{
					$aRecord['title'][$aRow['language_id']] = $aRow['title'];
					$aRecord['text'][$aRow['language_id']] = $aRow['text'];
					$aRecord['info'][$aRow['language_id']] = $aRow['info'];
				}
			}
			else
			{
				// Obtenemos el ultimo registro
				$aRow = pharaonix_queryOne( 'SELECT id_term_pivacy_general, version FROM rgpd_term_privacy_general ORDER BY id_term_pivacy_general DESC limit 1' );
				$sVersion = ($aRow->records['version'] == '' ? '0.0.0' : $aRow->records['version']);

				// Aumentamos version
				$sVersion = tools::version( $sVersion );

				// Si vamos a insertar creamos un array falso
				$aRecord = array(
					'title' => $aLanguageCopy,
					'text' => $aLanguageCopy,
					'info' => $aLanguageCopy,
					'version' => $sVersion
				);

				// Si contenemos datos obtenemos los textos de la ultima politica
				if( $aRow->num_rows > 0 )
				{
					$aRows = tep_db_query( 'select title, text, info, language_id from rgpd_term_privacy_general_description where id_term_pivacy_general = "' . $aRow->records['id_term_pivacy_general'] . '"' );

					while( $aRow = tep_db_fetch_array( $aRows ) )
					{
						$aRecord['title'][$aRow['language_id']] = $aRow['title'];
						$aRecord['text'][$aRow['language_id']] = $aRow['text'];
						$aRecord['info'][$aRow['language_id']] = $aRow['info'];
					}
				}
			}

			// Insertar o actualizar
			if( $sPostAction == 'terms_general_update' )
			{
				// Pasamos todos los post por tep_db_prepare_input
				array_walk( $_POST, function( $value, $key){ global $_POST; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); } );

				// Editamos
				if( $sGetId != false )
				{
					// Recorremos idiomas
					foreach( $aLanguage as $nLanguageId => $language )
					{
						tep_db_perform( 'rgpd_term_privacy_general_description', array(
							'title' => $_POST['title'][$nLanguageId],
							'text' => $_POST['text'][$nLanguageId],
							'info' => $_POST['info'][$nLanguageId],
						), 'update', 'language_id = "' . $nLanguageId . '" and id_term_pivacy_general = "' . $sGetId . '"' );
					}
				}
				else // Insertamos
				{
					// Creamos la ley
					tep_db_perform( 'rgpd_term_privacy_general', array(
						'version' => $_POST['version']
					) );

					// Obtenemos la id
					$sId = tep_db_insert_id();

					// Recorremos idiomas
					foreach( $aLanguage as $nLanguageId => $language )
					{
						tep_db_perform( 'rgpd_term_privacy_general_description', array(
							'title' => $_POST['title'][$nLanguageId],
							'text' => $_POST['text'][$nLanguageId],
							'info' => $_POST['info'][$nLanguageId],
							'language_id' => $nLanguageId,
							'id_term_pivacy_general' => $sId
						) );
					}
				}

				// Mensaje
				$messageStack->addSession( 'success', 'El termino general se ' . ($sGetId != false ? 'edito' : 'añadio') . ' correctamente', 'success' );

				// Redireccionamos
				tep_redirect( tep_href_link(  $sUrlPage, 'action=terms_general' ) );
			}

			// Formulario
			$sHtml .= '<div class="oeBox column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-gavel"></i> ' . $sSubtitle . ' </div>';
					$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=terms_general_update' ) . '" class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : '';
						$sHtml .= '<input type="submit" style="display: none;" />';

						$sHtml .= tools::getInputLanguages( 'title', 'Titulo:', $aRecord['title'], 'Titulo Visible en las páginas de Información y en los Logs de aceptación.', array_key_exists( 'title', $aMessageError ) ? $aMessageError['title'] : '' );
						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="version" class="column a02 tright">Versión:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= array_key_exists( 'version', $aMessageError ) ? $aMessageError['version'] : '';
							$sHtml .= '<input type="text" readonly="readonly" name="version" id="version" value="' . $aRecord['version'] . '"/>';
							$sHtml .= '<div class="DFhelp">Versión que controla la aceptación de los usuarios. Este campo aumentará automáticamente cuando crees nuevos términos.</div>';
						$sHtml .= '</div>';
						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= tools::getInputLanguages( 'info', 'Tooltip Término:', $aRecord['info'], 'En este campo debes de introducir la primera capa de aceptación de los Términos Generales, donde especificas rápidamente las condiciones para el cliente sin que tenga que leer el documento de términos completo. Aparecerá un icono de ayuda al lado de cada checkbox de aceptación que al superponer el ratón mostrará esta información.', '', 10, false );
						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= tools::getInputLanguages( 'text', 'Información Términos Generales:', $aRecord['text'], 'Página de Información con las Políticas y Términos Generales de tu tienda online.', '', 10, false );
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		break;

		case 'terms_general':
			// Configuracion
			$sSubtitle = 'Términos Generales';
			$aButtons = array(
				array( 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ),
				array( 'title' => 'Opciones', 'href' => tep_href_link( $sUrlPage, 'action=terms_general_options' ), 'icon' => 'fa-cog' ),
				array( 'title' => 'Añadir versión', 'href' => tep_href_link( $sUrlPage, 'action=terms_general_add_form' ), 'icon' => 'fa-plus' )
			);

			// Obtenemos el ultimo registro
			$aRowLast = pharaonix_queryOne( 'SELECT version FROM rgpd_term_privacy_general ORDER BY id_term_pivacy_general DESC limit 1' )->records;

			// Variables
			$aFiler = array( 'search' => '' );
			$aAuxFilter = array_key_exists( 'filter', $_GET ) && is_array($_GET['filter']) ? $_GET['filter'] : (array_key_exists( 'filter', $_POST ) && is_array($_POST['filter']) ? $_POST['filter'] : array());
			$sWhere = '';

			// Limpiamos variables get filter
			array_walk( $aFiler, function( $value, $key){ global $aFiler, $aAuxFilter; $aFiler[$key] = tep_db_prepare_input( array_key_exists( $key, $aAuxFilter ) ? $aAuxFilter[$key] : $aFiler[$key] ); } );

			// Where
			$sWhere = '';

			if( $aFiler['search'] != '' )
				$sWhere .= ( $sWhere != 'where ' ? ' and' : '') . ' (LOWER(tpd.title) LIKE "%' . strtolower( $aFiler['search'] ) . '%" OR LOWER(tp.version) LIKE "%' . strtolower( $aFiler['search'] ) . '%")';

			// Order by
			if( $sGetOrderby == 'title' )
				$sOrderby = 'title ' . $sGetSort;
			else if( $sGetOrderby == 'version' )
				$sOrderby = 'version ' . $sGetSort;
			else
				$sOrderby = 'id_term_pivacy_general DESC';

			// Sql
			$sSql = 'SELECT tp.id_term_pivacy_general, tpd.title, tp.version
					 FROM rgpd_term_privacy_general tp
					 INNER JOIN rgpd_term_privacy_general_description tpd on (tp.id_term_pivacy_general = tpd.id_term_pivacy_general and tpd.language_id = 3)
					 ' . $sWhere . ' ORDER BY ' . $sOrderby;

			// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
			$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

			// Sql para el count
			$sSqlCount = 'SELECT COUNT(table_aux.id_term_pivacy_general) as total FROM (' . $sSql . ') as table_aux';

			// Datos y paginacion
			$aDatoSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
			$aDatos = tep_db_query( $sSql );

			// Mensajes comprobamos si tenemos datos
			if( tep_db_num_rows( $aDatos ) <= 0 )
			{
				if( $sWhere != '' )
					$sHtml .= $messageStack->show( array( 'text' => 'El filtro establecido no contiene datos.', 'class' => 'warning' ) );
				else
					$sHtml .= $messageStack->show( array( 'text' => 'No existe ningun registro para mostrar.', 'class' => 'warning' ) );
			}

			// Texto
			$sHtml .= $messageStack->show( array( 'text' => 'Estos son las distintas versiones de los Términos Generales de su tienda, puedes editar cualquiera de ella si necesitas cambiar alguna cosa pequeña, pero si haces un gran cambio en ellas se recomienda crear una nueva versión para que todos tus clientes sepan los cambios de ella.', 'class' => 'info' ) );

			$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-gavel"></i> Términos Generales</div>';
					$sHtml .= '<form method="post" action="' . tep_href_link( $sUrlPage, 'action=terms_general' ) . '" class="oeCntd row ax">';
						$sHtml .= '<div class="oeBoxFltr column a12 ax row">';
							$sHtml .= '<div class="column a09 row ax amiddle input-search">';
								$sHtml .= '<label class="column">Buscar: </label> <div class="column"><input type="text" name="filter[search]" placeholder="Introduce búsqueda titulo o versión" value="' . $aFiler['search'] . '" autofocus/> <i class="fa fa-search"></i></div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="column a03 tright">';
								$sHtml .= ($sWhere != '' ? '<a title="Quitar filtro" href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'filter' ) ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa-close"></i></a> ' : '');
								$sHtml .= '<a href="#fltr-lstd" title="Filtrar registros" class="xbutton small hv9 verde-turquesa mgp-inln"><i class="fa fa-filter"></i></a>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<table class="xform">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'version', 'Version' ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'title', 'Titulo' ) . '</th>';
									$sHtml .= '<th width="125">Acciones</th>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';

								while( $aDato = tep_db_fetch_array( $aDatos ) )
								{
									$sHtml .= '<tr ' . ($aRowLast['version'] ==  $aDato['version'] ? 'data-dblclick="' . tep_href_link( $sUrlPage, 'action=terms_general_add_form&id=' . $aDato['id_term_pivacy_general'] ) . '"' : '') . '>';
										$sHtml .= '<td width="100">' . $aDato['version'] . '</td>';
										$sHtml .= '<td>' . $aDato['title'] . '</td>';
										$sHtml .= '<td>';
											// Solo puede editar si es la ultima version
											if( $aRowLast['version'] ==  $aDato['version'] )
											{
												$sHtml .= '<div class="drop xfselect">';
													$sHtml .= '<div>Acciones</div>';
													$sHtml .= '<ul class="down down-dngt">';
														$sHtml .= '<li><a href="' . tep_href_link( $sUrlPage, 'action=terms_general_add_form&id=' . $aDato['id_term_pivacy_general'] ) . '" class="hv"><i class="fa fa-pencil"></i>Editar registro</a></li>';
													$sHtml .= '</ul>';
												$sHtml .= '</div>';
											}
											else
												$sHtml .= '-';
										$sHtml .= '</td>';
									$sHtml .= '</tr>';
								}

							$sHtml .= '</tbody>';
						$sHtml .= '</table>';

						// Paginación
						$sHtml .= $aDatoSplit->showPaginateTable( tep_get_all_get_params( array('page') ), 'page', $sHtmlActionMasivo, 'solenopsis' );

						$sHtml .= '</div>';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';

			// Filtro
			$sHtml .= '<form action="' . tep_href_link( $sUrlPage ) . '" method="get" id="fltr-lstd" class="vntn-form mfp-hide zoom-anim-dialog oeBox mfp-white">';
				$sHtml .= '<input type="hidden" name="action" value="terms_general" />';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-gavel"></i> Términos Generales</div>';
					$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= '<label for="search" class="column a02 tright">Buscar:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="filter[search]" placeholder="Introduce búsqueda titulo o versión" value="' . $aFiler['search'] . '"/> ';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-none"></div>';
						$sHtml .= '<div class="column a12 tright">';
							$sHtml .= ($sWhere != '' ? '<a href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'filter' ) ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa-close"></i> Eliminar</a> ' : '');
							$sHtml .= '<div class="xbutton verde hv9 small"><input type="submit"/><span class="fa fa-filter"></span> Filtrar</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</form>';
		break;

		case 'terms_trade_update':
		case 'terms_trade_add_form':
			// Variables
			$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
			$aMessageError = array();
			$sSubtitle = ($sGetId != '' ? 'Editar' : 'Añadir') . ' termino comercial';
			$aButtons = array(
				array( 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage, 'action=terms_trade' ), 'icon' => 'fa-arrow-left' ),
				array( 'title' => 'Guardar', 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' )
			);
			$aRecord = array();

			// Idioma, obtenemos solo los id
			$aLanguageCopy = $aLanguage;
			array_walk( $aLanguageCopy, function( $value, $key){ global $aLanguageCopy; $aLanguageCopy[$key] = ''; } );

			// Si estamos editando
			if( $sGetId != false )
			{
				// Obtenemos el registro
				$aRows = pharaonix_query( 'SELECT * FROM rgpd_term_privacy_trade WHERE id_term_pivacy_trade = "' . (int)$sGetId . '"' );

				// Si no existe
				if( $aRows->num_rows == 0 )
				{
					$messageStack->addSession( 'success', 'El registro que intentas editar no existe', 'error' );
					tep_redirect( tep_href_link( $sUrlPage ) );
				}

				// Si vamos a insertar creamos un array falso
				$aRecord['title'] = $aLanguageCopy;
				$aRecord['info'] = $aLanguageCopy;

				// Si contenemos datos obtenemos los textos
				while( $aRow = tep_db_fetch_array( $aRows->records ) )
				{
					$aRecord['title'][$aRow['language_id']] = $aRow['title'];
					$aRecord['info'][$aRow['language_id']] = $aRow['info'];
					$aRecord['reference'] = $aRow['reference'];
				}
			}
			else
			{
				// Si vamos a insertar creamos un array falso
				$aRecord = array(
					'title' => $aLanguageCopy,
					'info' => $aLanguageCopy,
					'reference' => ''
				);
			}

			// Insertar o actualizar
			if( $sPostAction == 'terms_trade_update' )
			{
				// Pasamos todos los post por tep_db_prepare_input
				array_walk( $_POST, function( $value, $key){ global $_POST; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); } );

				// Editamos
				if( $sGetId != false )
				{
					// Recorremos idiomas
					foreach( $aLanguage as $nLanguageId => $language )
					{
						tep_db_perform( 'rgpd_term_privacy_trade', array(
							'title' => $_POST['title'][$nLanguageId],
							'info' => $_POST['info'][$nLanguageId],
							'reference' => getSlug( $_POST['reference'], '_' ),
						), 'update', 'language_id = "' . $nLanguageId . '" and id_term_pivacy_trade = "' . $sGetId . '"' );
					}
				}
				else // Insertamos
				{
					// Obtenemos la id
					$sId = pharaonix_queryOne( 'select MAX(id_term_pivacy_trade) as id from rgpd_term_privacy_trade' )->records['id'] + 1;

					// Recorremos idiomas
					foreach( $aLanguage as $nLanguageId => $language )
					{
						tep_db_perform( 'rgpd_term_privacy_trade', array(
							'title' => $_POST['title'][$nLanguageId],
							'info' => $_POST['info'][$nLanguageId],
							'reference' => getSlug( $_POST['reference'], '_' ),
							'language_id' => $nLanguageId,
							'id_term_pivacy_trade' => $sId
						) );
					}
				}

				// Mensaje
				$messageStack->addSession( 'success', 'El termino comercial se ' . ($sGetId != false ? 'edito' : 'añadio') . ' correctamente', 'success' );

				// Redireccionamos
				tep_redirect( tep_href_link(  $sUrlPage, 'action=terms_trade' ) );
			}

			// Formulario
			$sHtml .= '<div class="oeBox column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-gavel"></i> ' . $sSubtitle . ' </div>';
					$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=terms_trade_update' ) . '" class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : '';
						$sHtml .= '<input type="submit" style="display: none;" />';

						$sHtml .= tools::getInputLanguages( 'title', 'Titulo:', $aRecord['title'], 'Titulo del termino comercial', array_key_exists( 'title', $aMessageError ) ? $aMessageError['title'] : '' );
						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label style="' . ($aAdmin['admin_email_address'] != 'info@denox.es' ? 'display: none;' : '') . '" for="reference" class="column a02 tright">Referencia:</label>';
						$sHtml .= '<div style="' . ($aAdmin['admin_email_address'] != 'info@denox.es' ? 'display: none;' : '') . '" class="column a10">';
							$sHtml .= array_key_exists( 'reference', $aMessageError ) ? $aMessageError['reference'] : '';
							$sHtml .= '<input type="text" ' . ($aAdmin['admin_email_address'] != 'info@denox.es' ? 'readonly="readonly"' : '') . ' name="reference" id="reference" value="' . $aRecord['reference'] . '"/>';
							$sHtml .= '<div class="DFhelp">Referencia del termino general, todo en minusculas y sin espacios solo guiones bajos.</div>';
						$sHtml .= '</div>';
						$sHtml .= '<div style="' . ($aAdmin['admin_email_address'] != 'info@denox.es' ? 'display: none;' : '') . '" class="xline xline-dashed"></div>';

						$sHtml .= tools::getInputLanguages( 'info', 'Información del termino:', $aRecord['info'], 'Información del termino comercial, un pequeño texto que aparecera encima de los Términos cuando el ratón pase.', '', 10, false );
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		break;

		case 'term_trade_delete':
			// Variables
			$aGetId = tep_db_prepare_input( $_GET['id'] );
			$aPostId = tep_db_prepare_input( $_POST['id'] );
			$sIds = '';

			// Si nos envian por get creamos el array
			if( $aGetId != '' )
				$aPostId = array( $aGetId );

			// Recorremos los id
			foreach( $aPostId as $sId )
				$sIds .= $sId . ',';

			// Si tenemos id eliminamos
			if( $sIds != '' )
				tep_db_query( 'delete from rgpd_term_privacy_trade where id_term_pivacy_trade in(' . substr( $sIds, 0, -1 ) . ')' );

			// Redireccionamos
			$messageStack->addSession( 'success', 'Los registros se han eliminado correctamente', 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

		case 'terms_trade':
			// Configuracion
			$sSubtitle = 'Términos Comerciales';
			$sHtmlActionMasivo = '';
			$aButtons = array();

			// Crear uno nuevo
			if( $aAdmin['admin_email_address'] == 'info@denox.es' )
			{
				$aButtons[] = array( 'title' => 'Añadir', 'href' => tep_href_link( $sUrlPage, 'action=terms_trade_add_form' ), 'icon' => 'fa-plus' );
				// Html para el boton masivo

				$sHtmlActionMasivo = '<label class="column afluid">Aplicar acción:&nbsp;&nbsp;</label>
				<div class="column afluid"><div class="drop masv xfselect">
					<div>Acciones</div>
					<ul class="down drch">
						<li><a data-question="¿Realmente deseas eliminar los registros?" data-action="' . tep_href_link( $sUrlPage, 'action=term_trade_delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash-o"></i>Eliminar registros</a></li>
					</ul>
				</div></div>&nbsp; - &nbsp;';
			}

			$aButtons[] = array( 'title' => 'Opciones', 'href' => tep_href_link( $sUrlPage, 'action=terms_trade_options' ), 'icon' => 'fa-cog' );
			$aButtons[] = array( 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' );

			// Order by
			if( $sGetOrderby == 'title' )
				$sOrderby = 'title ' . $sGetSort;
			else
				$sOrderby = 'title ASC';

			// Sql
			$sSql = 'SELECT id_term_pivacy_trade, title
					 FROM rgpd_term_privacy_trade
					 WHERE language_id = 3 ORDER BY ' . $sOrderby;

			// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
			$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

			// Sql para el count
			$sSqlCount = 'SELECT COUNT(table_aux.id_term_pivacy_trade) as total FROM (' . $sSql . ') as table_aux';

			// Datos y paginacion
			$aDatoSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
			$aDatos = tep_db_query( $sSql );

			// Mensajes comprobamos si tenemos datos
			if( tep_db_num_rows( $aDatos ) <= 0 )
			{
				if( $sWhere != '' )
					$sHtml .= $messageStack->show( array( 'text' => 'El filtro establecido no contiene datos.', 'class' => 'warning' ) );
				else
					$sHtml .= $messageStack->show( array( 'text' => 'No existe ningun registro para mostrar.', 'class' => 'warning' ) );
			}

			// Texto
			$sHtml .= $messageStack->show( array( 'text' => 'Estos son los distintos Términos Comerciales que tiene su tienda configurada donde el cliente final tiene que dar su consentimiento para su uso. Solo puedes editar los nombres en ningún caso añadir o editar nuevos.', 'class' => 'info' ) );

			$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-gavel"></i> Términos Comerciales</div>';
					$sHtml .= '<form method="post" action="' . tep_href_link( $sUrlPage, 'action=terms_trade' ) . '" class="oeCntd row ax">';
						$sHtml .= '<table class="xform">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									if( $aAdmin['admin_email_address'] == 'info@denox.es' )
										$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'title', 'Titulo' ) . '</th>';
									$sHtml .= '<th width="125">Acciones</th>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';

								while( $aDato = tep_db_fetch_array( $aDatos ) )
								{
									$sHtml .= '<tr data-dblclick="' . tep_href_link( $sUrlPage, 'action=terms_trade_add_form&id=' . $aDato['id_term_pivacy_trade'] ) . '">';
										if( $aAdmin['admin_email_address'] == 'info@denox.es' )
											$sHtml .= '<td class="chck" align="center"><input type="checkbox" id="id_' . $aDato['id_term_pivacy_trade'] . '" name="id[]" value="' . $aDato['id_term_pivacy_trade'] . '"/><label for="id_' . $aDato['id_term_pivacy_trade'] . '"><span></span></label></td>';
										$sHtml .= '<td>' . $aDato['title'] . '</td>';
										$sHtml .= '<td>';
											$sHtml .= '<div class="drop xfselect">';
												$sHtml .= '<div>Acciones</div>';
												$sHtml .= '<ul class="down down-dngt">';
													$sHtml .= '<li><a href="' . tep_href_link( $sUrlPage, 'action=terms_trade_add_form&id=' . $aDato['id_term_pivacy_trade'] ) . '" class="hv"><i class="fa fa-pencil"></i>Editar registro</a></li>';
												$sHtml .= '</ul>';
											$sHtml .= '</div>';
										$sHtml .= '</td>';
									$sHtml .= '</tr>';
								}

							$sHtml .= '</tbody>';
						$sHtml .= '</table>';

						// Paginación
						$sHtml .= $aDatoSplit->showPaginateTable( tep_get_all_get_params( array('page') ), 'page', $sHtmlActionMasivo, 'solenopsis' );

						$sHtml .= '</div>';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		break;

		case 'tools_auto_term_general':
			// Variables
			$sSql = '';
			$nCont = 0;
			$nCustomersCount = 0;
			$aSubscribedAll = array_values( pharaonix_getArrayAssociativeSql( 'SELECT id_term_pivacy_trade, title, info FROM rgpd_term_privacy_trade WHERE language_id = "' . $languages_id . '"', 'id_term_pivacy_trade', 'title', false ) );

			// Obtenemos ultima version
			$aTermPrivacyGeneral = pharaonix_queryOne( 'select tpd.text, tpd.title, tpd.info, tp.id_term_pivacy_general
													   from rgpd_term_privacy_general_description tpd
													   inner join rgpd_term_privacy_general tp on (tp.id_term_pivacy_general = tpd.id_term_pivacy_general)
													   where tpd.language_id = 3 ORDER BY tp.version DESC limit 1' )->records;

			// Obtenemos los cliente
			$aRows = tep_db_query( 'SELECT c.customers_id, c.customers_email_address, c.customers_newsletter, IF(ci.customers_info_date_of_last_logon IS NULL, ci.customers_info_date_account_created,ci.customers_info_date_of_last_logon) as date
									FROM customers c
									INNER JOIN customers_info ci ON (c.customers_id = ci.customers_info_id)
									where c.id_term_pivacy_general != "' . $aTermPrivacyGeneral['id_term_pivacy_general'] . '"' );

			if( tep_db_num_rows( $aRows ) > 0 )
			{
				// Recorremos para crear los sql
				while( $aRow = tep_db_fetch_array( $aRows ) )
				{
					// Ip ramdom
					$sRandIP = "".mt_rand(0,255).".".mt_rand(0,255).".".mt_rand(0,255).".".mt_rand(0,255);

					// Creamos sql y aumentamos para procesar de 600 en 600
					$sSql .= '("' . $aRow['customers_id'] . '", "' . $aRow['customers_email_address'] . '", "' . $sRandIP . '", "' . $aRow['date'] . '", "general", "' . $aTermPrivacyGeneral['title'] . '", "' . $aTermPrivacyGeneral['id_term_pivacy_general'] . '", "1"),';
					$nCont++;
					$nCustomersCount++;

					// Insertamos y reseteamos
					if( $nCont == 600 )
					{
						tep_db_query( 'INSERT INTO `rgpd_log_term_privacy` (`customers_id`, `customers_mail`, `ip`, `date`, `type`, `term_name`, `id_term_pivacy`, `status`) VALUES ' . substr( $sSql, 0, -1 ) );
						$nCont = 0;
						$sSql = '';
					}

					// Si tiene customers_newsletter
					if( $aRow['customers_newsletter'] == '1' )
					{
						foreach( $aSubscribedAll as $aSubscribed )
						{
							$nIdAll = $aSubscribed['id'];
							$sTitle = $aSubscribed['text'];

							tep_db_perform( 'rgpd_account_term', array( 'customers_id' => $aRow['customers_id'], 'id_term_pivacy_trade' => $nIdAll ) );

							tep_db_perform( 'rgpd_log_term_privacy', array(
								'customers_id' => $customer_id,
								'customers_mail' => $aRow['customers_email_address'],
								'ip' => $sRandIP,
								'date' => $aRow['date'],
								'type' => 'comercial',
								'term_name' => $sTitle,
								'id_term_pivacy' => $nIdAll,
								'status' => 1
							) );
						}
					}
				}

				// Si nos quedan por procesar
				if( $nCont != 600 )
					tep_db_query( 'INSERT INTO `rgpd_log_term_privacy` (`customers_id`, `customers_mail`, `ip`, `date`, `type`, `term_name`, `id_term_pivacy`, `status`) VALUES ' . substr( $sSql, 0, -1 ) );

				// Actualizamos a todos los clientes con la ultima version
				tep_db_query( 'update customers set id_term_pivacy_general = "' . $aTermPrivacyGeneral['id_term_pivacy_general'] . '"' );

				// Mensajes
				$messageStack->addSession( 'success', 'Se han aceptado la politica de privacidad automaticamente a tus ' . $nCustomersCount . ' clientes', 'success' );
			}
			else
				$messageStack->addSession( 'success', 'Todos tus clientes estan actualizados', 'success' );

			// Redireccionamos
			tep_redirect( $sUrlPage );
		break;

		case 'download_csv_term_general':
			// Obtenemos ultima version
			$aTermPrivacyGeneral = pharaonix_queryOne( 'select tpd.text, tpd.title, tpd.info, tp.id_term_pivacy_general
													   from rgpd_term_privacy_general_description tpd
													   inner join rgpd_term_privacy_general tp on (tp.id_term_pivacy_general = tpd.id_term_pivacy_general)
													   where tpd.language_id = 3 ORDER BY tp.version DESC limit 1' )->records;

			// Variables
			$sCsv = "nombre,apellidos,email,\n";
			$sGetStatus = tep_db_input( $_GET['status'] );

			// Consulta
			$aRows = tep_db_query( 'SELECT customers_firstname, customers_lastname, customers_email_address FROM customers where id_term_pivacy_general' . ($sGetStatus == 0 ? ' != ' : ' = ') . $aTermPrivacyGeneral['id_term_pivacy_general'] );

			// Recorremos
			while( $aRow = tep_db_fetch_array( $aRows ) )
				$sCsv .= str_replace( ',', '', $aRow['customers_firstname'] ) . ',' . str_replace( ',', '', $aRow['customers_lastname'] ) . ',' . $aRow['customers_email_address'] . "\n";

			// Descargamos
			header( "Content-type: text/x-csv" );
			header( "Content-Disposition: attachment; filename=" . date( 'd_m_Y_H_i_s' ) . ".csv" );
			echo $sCsv;
			exit();
		break;

		case 'cron_delete_orders':
			// Obtenemos los pedidos pasado X años
			$aCustomersId = pharaonix_getArrayAssociativeSql( 'select DISTINCT(customers_id) from orders where DATE_FORMAT( date_purchased, "%Y-%m-%d" ) <= adddate( DATE_FORMAT( NOW(), "%Y-%m-%d" ), - ' . (365 * (int)RGPD_ACCOUNT_DELETE_TAX_TIME_DATA_ORDER) . ')', 'customers_id', 'customers_id' );

			// Recorremos
			foreach( $aCustomersId as $nCustomersId )
				$rgpd->ordersExecute( $nCustomersId['id'] );

			// Detenemos
			die();
		break;

		case 'cron_delete_customer_notify':
			// Límites de memoria y tiempo
			ini_set( 'memory_limit', '2048M' );
			set_time_limit( -1 );

			// Ejecutamos el cron siempre que el dia de hoy sea mayor o igual que el configurado
			if( !(date('Y-m-d') >= RGPD_ACCOUNT_DELETE_CRON_DATE_EXECUTE) )
				die('No se puede ejecutar la tarea programada hasta el dia ' . RGPD_ACCOUNT_DELETE_CRON_DATE_EXECUTE);

			// Si tenemos para enviar email
			if( RGPD_ACCOUNT_DELETE_CRON_NOTIFY == 'true' )
			{
				// Obtenemos los clientes que son inactivos y vamos avisarle
				$aRows = tep_db_query( 'SELECT c.customers_email_address, c.customers_lastname, c.customers_language_id, customers_firstname, c.customers_id, IF(ci.customers_info_date_of_last_logon IS NULL, ci.customers_info_date_account_created,ci.customers_info_date_of_last_logon) as FECHA
										FROM customers_info ci
										INNER JOIN customers c ON (c.customers_id = ci.customers_info_id)
										HAVING DATE_FORMAT( FECHA, "%Y-%m-%d" ) = adddate( DATE_FORMAT( NOW(), "%Y-%m-%d" ), - ' . ((365 * RGPD_ACCOUNT_DELETE_CRON_YEARS) - RGPD_ACCOUNT_DELETE_CRON_DAYS) . ')' );

				// Eliminamos
				while( $aRow = tep_db_fetch_array( $aRows ) )
				{
					$aRow['customers_language_id'] = ($aRow['customers_language_id'] == 0 ? 3 : $aRow['customers_language_id'] );

					// Obtenemos idioma
					$aDefines = getLangugeFile( getcwd() . '/../' . DIR_WS_LANGUAGES . $aLanguage[$aRow['customers_language_id']] . '/account.php', false );

					// Email
					$mail = new util\mail();

					$sHtmlEmail = str_replace( array(
						'{USERNAME}',
						'{DATE}',
						'{DAYS}',
						'{LINK}'
					), array(
						$aRow['customers_firstname'] . ' ' . $aRow['customers_lastname'],
						date( 'd-m-Y H:i' ),
						RGPD_ACCOUNT_DELETE_CRON_DAYS,
						$mail->url . '/login.php'
					), ($aDefines['RGPD_EMAIL_CUSTOMER_DELETE_NOTIFY'] ?? '') );

					// Html del email
					$mail->includeEmail( 'various.php', array(
						'content' => $sHtmlEmail
					) );

					// Enviamos
					tep_mail( $aRow['customers_firstname'], $aRow['customers_email_address'], $aDefines['RGPD_EMAIL_CUSTOMER_DELETE_NOTIFY_SUBJECT'], $mail->html, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS );
				}
			}

			// Detenemos
			exit();
		break;

		case 'cron_delete_customer':
			// Ejecutamos el cron siempre que el dia de hoy sea mayor o igual que el configurado
			if( !(date('Y-m-d') >= RGPD_ACCOUNT_DELETE_CRON_DATE_EXECUTE) )
				die('No se puede ejecutar la tarea programada hasta el dia ' . RGPD_ACCOUNT_DELETE_CRON_DATE_EXECUTE);

			// Límites de memoria y tiempo
			ini_set( 'memory_limit', '2048M' );
			set_time_limit( -1 );

			// Si tenemos activo para eliminar
			if( RGPD_ACCOUNT_DELETE_CRON_ACTIVE == 'true' )
			{
				// Obtenemos los clientes que son inactivos
				$aRows = tep_db_query( 'SELECT customers_info_id, IF(customers_info_date_of_last_logon IS NULL, customers_info_date_account_created,customers_info_date_of_last_logon) as FECHA
										FROM customers_info
										HAVING DATE_FORMAT( FECHA, "%Y-%m-%d" ) <= adddate( DATE_FORMAT( NOW(), "%Y-%m-%d" ), - ' . (365 * (int)RGPD_ACCOUNT_DELETE_CRON_YEARS) . ')' );

				// Eliminamos
				while( $aRow = tep_db_fetch_array( $aRows ) )
				{
					$customers_id = $aRow['customers_info_id'];
					$customer_id = $aRow['customers_info_id'];

					// Eliminar la cuenta
					$rgpd->accountDeleteExecute(false);

					// Eliminamos
					tep_db_query( 'delete from ' . TABLE_ADDRESS_BOOK . ' where customers_id = "' . tep_db_input($customers_id) . '"' );
					tep_db_query( 'delete from ' . TABLE_CUSTOMERS . ' where customers_id = "' . tep_db_input($customers_id) . '"' );
					tep_db_query( 'delete from ' . TABLE_CUSTOMERS_INFO . ' where customers_info_id = "' . tep_db_input($customers_id) . '"' );
					tep_db_query( 'delete from ' . TABLE_CUSTOMERS_BASKET . ' where customers_id = "' . tep_db_input($customers_id) . '"' );
					tep_db_query( 'delete from ' . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . ' where customers_id = "' . tep_db_input($customers_id) . '"' );
					tep_db_query( 'delete from ' . TABLE_WHOS_ONLINE . ' where customer_id = "' . tep_db_input($customers_id) . '"' );
				}
			}

			// Detenemos
			exit();
		break;

		case 'html_email':
			// Email
			$mail = new util\mail();

			$sHtml = '<div style="text-align: justify">
				<p>Estimado cliente,</p>
				<p>¡Tenemos buenas noticias! A partir del 25 de Mayo, entra en vigor el nuevo Reglamento General de Protección de Datos (RGPD) de la Unión Europea, para proteger la privacidad de los datos personales de clientes, así como el correcto uso y procesamiento de sus datos. Por eso, desde ' . STORE_NAME . ' llevamos meses trabajando en la nueva normativa para adaptar nuestros términos de protección y <strong>necesitamos tu autorización para poder seguir ofreciéndote nuestros servicios</strong> y/o comunicarnos contigo.</p>
				<p>Para ello es necesario que <strong>accedas a tu cuenta de cliente</strong> en <strong>' . STORE_NAME . '</strong> para ver los nuevos términos y <strong>ACEPTARLOS</strong>.<p>

				<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">
					<tr>
						<td align="center">
							<p><a href="' . $mail->url . '/login.php" style="background-color: #' . ACCOUNT_COLOUR . '; border: 1px solid #' . ACCOUNT_COLOUR . '; border-radius: 3px; color: #ffffff; display: inline-block; font-family: sans-serif; font-size: 16px; line-height: 44px; text-align: center; text-decoration: none; -webkit-text-size-adjust: none; mso-hide: all; padding: 10px 40px;">ACCEDER A MI CUENTA</a></p>
						</td>
					</tr>
				</table>

				<p><strong>Si quieres seguir recibiendo nuestros servicios y comunicaciones conservando tus datos en nuestra tienda online es necesario que realices esta acción cuanto antes.</strong></p>
				<p>Gracias a esta nueva adaptación, proporcionamos una mayor transparencia sobre las diferentes razones por las que utilizamos tu información personal. Proteger tus datos y aumentar la transparencia es algo muy importante para nosotros, es nuestro compromiso directo con nuestros clientes.</p>
				<p>Si tienes alguna pregunta sobre esta actualización, puedes ponerte en contacto con nosotros a través del contacto de nuestra tienda online.</p>
				<p>Gracias por formar parte de ' . STORE_NAME . '.</p>

				-----------
				<p style="line-height: 15px; font-size: 10px; font-style: italic;">¿Con qué finalidad tratamos sus datos personales?<br/>
				En ' . STORE_NAME . ' tratamos la información que nos facilitan las personas interesadas con el fin de prestar el servicio solicitado y envío de publicidad en el caso de haber dado consentimiento ¡solo si tú nos lo has autorizado!</p>

				<p style="line-height: 15px; font-size: 10px; font-style: italic;">¿Por cuánto tiempo conservaremos sus datos?</br>
				Los datos personales proporcionados se conservarán mientras se mantenga la prestación de servicio y/o para posibles responsabilidades legales.</p>

				<p style="line-height: 15px; font-size: 10px; font-style: italic;">¿Cuál es la legitimación para el tratamiento de sus datos?</br>
				La base legal para el tratamiento de sus datos es la prestación del servicio y/o consentimiento del interesado.</p>

				<p style="line-height: 15px; font-size: 10px; font-style: italic;">¿Cuáles son sus derechos cuando nos facilita sus datos?</br>
				Cualquier persona tiene derecho a modificar, eliminar u obtener confirmación sobre si en ' . STORE_NAME . ' estamos tratando datos personales que les conciernan, o no.</br>
				Las personas interesadas tienen derecho a si acceder a sus datos personales, así como a solicitar la rectificación de los datos inexactos o, en su caso, solicitar su supresión cuando, entre otros motivos, los datos ya no sean necesarios para los fines que fueron recogidos.</br>
				Todo esto está disponible en nuestra nueva Área de Cliente para que puedas administrarlo tú mismo con total libertad y configurar tu privacidad.</p>

				<p style="line-height: 15px; font-size: 10px; font-style: italic;">¿Cómo hemos obtenido sus datos?</br>
				Los datos personales que tratamos en nuestra tienda online proceden del interesado, al registrarse para realizar un pedido o ponerse en contacto con nosotros a través de los medios que facilitamos en nuestra tienda.</p>
			</div>';

			// Html del email
			$mail->includeEmail( 'various.php', array(
				'content' => $sHtml
			) );


			echo '<table align="center" bgcolor="#ecedee" border="0" cellpadding="0" cellspacing="0" width="100%">';
				echo '<tr>';
					echo '<td align="center">';
						echo '<table align="center" border="0" cellpadding="0" cellspacing="0" width="680" class="display-width">';
							echo '<tr><td height="7"></td></tr>';
							echo '<tr>';
								echo '<td align="center" class="MsoNormal" style="padding: 0px 30px; color:#666666; font-family:Segoe UI, Helvetica Neue, Arial, Verdana, Trebuchet MS, sans-serif; font-size:12px; font-weight:600; line-height:22px; letter-spacing:1px; text-transform:uppercase;">';
									echo '<label style="display: block; width: 100%; text-align: left; font-size: 15px; line-height: 35px;">Copie este html:</label>';
									echo '<textarea style="border: 1px solid #CCC; width: 100%; height: 240px; font-size: 13px; line-height: 20px; padding: 10px 15px;">' . $mail->html . '</textarea>';
								echo '</td>';
							echo '</tr>';
							echo '<tr><td height="7"></td></tr>';
						echo '</table>';
					echo '</td>';
				echo '</tr>';
			echo '</table>';

			echo $mail->html;
			die();
		break;

		default:
			// Variables
			$aButtons = array();
			$sSubtitle = 'Configuración de las adaptaciones para cumplir con la RGPD';

			// Logs login 404
			if( $dxSecurity->configuration['SECURITY_DETECTION_404'] )
				$aButtons[] = array( 'title' => 'Log 404', 'href' => tep_href_link( $sUrlPage, 'action=log_404' ), 'icon' => 'fa-eye' );


			$sHtml .= $messageStack->show( array( 'text' => 'Una vez que hayas configurado los Ajustes del módulo, te recomendamos crear las siguientes Tareas Programadas (Cronjobs) en tu servidor para que se ejecuten diariamente de forma automatizada:<br/><br/><span style="font-size:10px">
			' . tep_href_link( 'rgpd.php', 'action=cron_delete_orders' ) . ' - 00 00 * * *<br/>
			' . tep_href_link( 'rgpd.php', 'action=cron_delete_customer_notify' ) . ' - 12 00 * * *<br/>
			' . tep_href_link( 'rgpd.php', 'action=cron_delete_customer' ) . ' - 00 00 * * *</span>', 'class' => 'info' ) );

			$sHtml .= '<div class="row ax columns">';
				// Ajustes eliminar cuenta
				$sHtml .= '<div class="oeBox column a04 row ax abs">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-user"></i>Ajustes de Clientes</div>';
						$sHtml .= '<div class="oeCntd">';
							$sHtml .= '<p>Configuración y personalización de los parámetros para cumplir con la RGPD para la cuenta del Cliente.</p>';
							$sHtml .= '<p>Además, permite la personalización de los textos para las secciones Desactivar y Eliminar la Cuenta del Cliente.</p>';
							$sHtml .= '<div class="btom"><a href="' . tep_href_link( $sUrlPage, 'action=account_delete' ) . '" class="xbutton small hv9">Configurar ajustes</a></div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				// Ajustes pedidos
				$sHtml .= '<div class="oeBox column a04 row ax abs">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-file-text"></i> Ajustes de Pedidos</div>';
						$sHtml .= '<div class="oeCntd">';
							$sHtml .= '<p>Configuración y personalización de los parámetros para cumplir con la RGPD de la actuación sobre los pedidos cuando el cliente es eliminado o ha pasado el periodo fiscal de la conservación de los datos recopilados.</p>';
							$sHtml .= '<div class="btom"><a href="' . tep_href_link( $sUrlPage, 'action=orders_options' ) . '" class="xbutton small hv9">Configurar ajustes</a></div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				// Términos Generales
				$sHtml .= '<div class="oeBox column a04 row ax abs">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-gavel"></i> Términos Generales</div>';
						$sHtml .= '<div class="oeCntd">';
							$sHtml .= '<p>Crea y edita las versiones para los Términos Generales y Políticas de Privacidad de tu tienda online. Se realizará un control exhaustivo de las versiones aceptadas por tus clientes para que todos tengan aceptada la última versión vigente.</p>';
							$sHtml .= '<div class="btom"><a href="' . tep_href_link( $sUrlPage, 'action=terms_general' ) . '" class="xbutton small hv9">Configurar ajustes</a></div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				// Términos Comerciales
				$sHtml .= '<div class="oeBox column a04 row ax abs">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-gavel"></i> Términos Comerciales</div>';
						$sHtml .= '<div class="oeCntd">';
							$sHtml .= '<p>Crea y edita las versiones para los Términos Comerciales (para email marketing, newsletter, recuperador de carritos...). Se realizará un control exhaustivo de las versiones aceptadas por tus clientes para que todos tengan aceptada la última versión vigente.</p>';
							$sHtml .= '<div class="btom"><a href="' . tep_href_link( $sUrlPage, 'action=terms_trade' ) . '" class="xbutton small hv9">Configurar ajustes</a></div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				// Logs Cuenta / Términos
				$sHtml .= '<div class="oeBox column a04 row ax abs">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> Logs RGPD</div>';
						$sHtml .= '<div class="oeCntd">';
							$sHtml .= '<p>En esta sección dispondrás de dos Logs donde se registra toda la activdidad del usuario para cumplir con la normativa de la RGPD.</p>';
							$sHtml .= '<p>1) Log con  las aceptaciones por parte de los clientes de los Términos y Políticas del Sitio.</p>';
							$sHtml .= '<p>2) Log con la información sobre las Activaciones y Desactivaciones de Cuentas.</p>';
							$sHtml .= '<div class="btom"><a href="' . tep_href_link( $sUrlPage, 'action=logs_terms' ) . '" class="xbutton small hv9">Ver Logs Términos</a> ';
							$sHtml .= '<a href="' . tep_href_link( $sUrlPage, 'action=logs_account' ) . '" class="xbutton small hv9">Ver Logs Desactivación</a></div>';

						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				// Descargar CSV
				$sHtml .= '<div class="oeBox column a04 row ax abs">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-cog"></i> Descargar CSV de Clientes</div>';
						$sHtml .= '<div class="oeCntd">';
							$sHtml .= '<p>Si deseas Descargar un CSV con todos los clientes que tengan (o no tengan) Aceptados los Términos y Políticas de Privacidad en su última versión puedes hacerlo a continuación.</p>';
							$sHtml .= '<p>Gracias a ello podremos enviar un e-mail a todos esos clientes antes del 25 Mayo para que acepten las nuevas políticas para legalizar nuestra base de datos.</p>';
							$sHtml .= '<div class="btom"><a target="_blank" href="' . tep_href_link( $sUrlPage, 'action=download_csv_term_general&status=0' ) . '" class=" xbutton small hv9">Descargar CSV - No Aceptados</a> ';
							$sHtml .= '<a target="_blank" href="' . tep_href_link( $sUrlPage, 'action=download_csv_term_general&status=1' ) . '" class=" xbutton small hv9">Descargar CSV - Aceptados</a></div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				// Plantilla Email RGPD
				$sHtml .= '<div class="oeBox column a04 row ax abs">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-envelope"></i> Plantilla Email Aceptación RGPD</div>';
						$sHtml .= '<div class="oeCntd">';
							$sHtml .= '<p>Si quieres legalizar tu base de datos antes del 25 de Mayo, te recomendamos enviar un e-mail a todos tus clientes para que acepten los nuevos Términos y poder capturar su aceptación explicita con IP / Fecha de cada uno de ellos.<br><br>Te hemos preparado una plantilla de E-mail para que te sea sencillo exportar este HTML a tu programa de Email Marketing para enviarlo.</p>';
							$sHtml .= '<div class="btom"><a href="' . tep_href_link( $sUrlPage, 'action=html_email' ) . '" target="_blank" class="xbutton small hv9">Ver Plantilla HTML</a></div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				// Herramienta Aceptación Políticas Implicitamente
				$sHtml .= '<div class="oeBox column a04 row ax abs">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-cog"></i> Aceptar las Políticas Automáticamente (BAJO TU RESPONSABILIDAD)</div>';
						$sHtml .= '<div class="oeCntd">';
							$sHtml .= '<p><strong>ATENCIÓN:</strong> Esta herramienta aceptará de forma automática los Términos para todos tus clientes de la base de datos que NO LO TENGAN ACEPTADO. Realmente este proceso es ilegal ya que no recogemos el consentimiento del cliente pero muchos de vosotros nos lo habéis solicitado.<br><br>El proceso además de aceptar las políticas de privacidad automáticamente sin el consentimiento del cliente, creara los logs como si lo hubieran aceptado. Recuerda, ¡usala bajo tu propia responsabilidad!</p>';
							$sHtml .= '<div class="btom"><a href="' . tep_href_link( $sUrlPage, 'action=tools_auto_term_general' ) . '" class="rgpd-confirm xbutton small hv9 rojo">Ejecutar Bajo Mi Responsabilidad</a></div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';


				$sHtml .= '<div class="oeBox column a04 row ax"></div>';
			$sHtml .= '</div>';
		break;
	}

	// Reemplazamos variable
	$sHtmlModuleOe = $sHtml;

	// JS
	$aJs = array( 'includes/modules/rgpd/js/index.js' );

	// MessageStack
	$sMessageStack = $messageStack->output(false);
	$messageStack->reset();

	// Header
	include( 'theme/solenopsis/html/header.php' );

	// Cabecera
	echo '<div class="oeHead column a12 row ax amiddle">';
		echo '<div class="oeTitu column a05 logo" style="padding-left: 55px;"><b><i class="fa fa-gavel"></i> ' . $sTitle . '</b>' . ($sSubtitle ? '<small>' . $sSubtitle . '</small>' : '') . '</div>';
		echo '<div class="oeButton column a07 dtright">';
			foreach( $aButtons as $aButton )
				echo '<a class="xbutton hv8 small' . (array_key_exists( 'anchor_class', $aButton ) ? ' ' . $aButton['anchor_class'] : '') . '" ' . (array_key_exists( 'extra', $aButton ) ? $aButton['extra'] : '') . ' ' . (array_key_exists( 'title', $aButton ) ? 'title="' . $aButton['title'] . '"' : '') . ' href="' . (array_key_exists( 'href', $aButton ) ? $aButton['href'] : 'javascript:void(0);') . '"><i class="fa ' . $aButton['icon'] . '"></i>' . $aButton['title'] . '</a> ';
		echo '</div>';
	echo '</div>';

	// Mensajes
	echo $sMessageStack;

	// Pintamos
	echo $sHtmlModuleOe;

	// Footer
	include( 'theme/solenopsis/html/footer.php' );
?>
