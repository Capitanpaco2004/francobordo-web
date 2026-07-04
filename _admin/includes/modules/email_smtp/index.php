<?php
	// Tools
	use util\tools as tools;
	use PHPMailer\PHPMailer\SMTP;
	use PHPMailer\PHPMailer\Exception;

	// Si nos mandan a instalar cambiamos el modulo por login para que forbidden no salte y podamos instalarlo
	if( array_key_exists( 'action', $_GET ) && $_GET['action'] == 'install' )
	{
		// FIX bypass sin auth: PHP_SELF='index.php' (FILENAME_DEFAULT) hace que tep_admin_check_login
		// salte SOLO el ACL de pagina. NO tocar SCRIPT_FILENAME: asi el login SIGUE exigiendose.
		$_SERVER['PHP_SELF'] = 'index.php';
	}

	// Incluimos el application_top
	require( 'includes/application_top.php' );
	include( 'includes/classes/currencies.php' );

	// Mostrar errores
	// ini_set('display_errors', 1);
	// error_reporting(1);
	// error_reporting(E_ERROR | E_WARNING | E_PARSE);
	// error_reporting(E_ALL);

	// Defines
	include(DIR_WS_LANGUAGES . $language . '/email_smtp.php');

	// Variables
	$sUrlPage =  'email_smtp.php';
	$sTitle = EMAIL_SMTP_TITLE;
	$sSubtitle = '';
	$aButtons = [];
	$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);
    	$sPostSubAction = array_key_exists( 'subaction', $_POST ) ? tep_db_input( $_POST['subaction'] ) : (array_key_exists( 'subaction', $_GET ) ? tep_db_input( $_GET['subaction'] ) : false);
	$aSecciones = [ 'contact_us.php', 'orders.php', 'checkout_process.php', 'cron_sistema_opiniones.php', 'cron_recover_cart.php', 'cron_discount_coupons.php' ];
	$aTypeEmail = [
		[ 'id' => 'smtp', 'text' => 'SMTP' ],
		[ 'id' => 'mail', 'text' => 'Mail' ]
	];
	$sHtml = '';

	# Messagestack estilo
	$messageStack->style = 'solenopsis';

	// Acciones
	switch( $sPostAction )
	{
		case 'email':
		case 'update_options':
			include( 'options.php' );
			exit();

		case 'readme':
			// Variables
			$sSubtitle = 'Readme de instalación';
			$aButtons = [
				[ 'title' => 'Ver módulo', 'icon' => 'fa-arrow-right', 'href' => $sUrlPage ]
			];

			$sHtml = tools::parsedown( DIR_WS_MODULES . '/email_smtp/readme.txt' );
		break;

		case 'install':
			// Insertamos admin file
			tools::insertAdminFiles( $sUrlPage, 1 );

			// Insertamos el grupo de configuracion
			$aConfigGroup = tools::insertConfigurationGroup( 'Configurar Emails', '', 0 );

			// Insertamos la configuracion global
			tools::insertConfiguration( 'Emails SMTP', 'STORE_OWNER_EMAIL_ADDRESS_GROUP', '', '', $aConfigGroup->records['configuration_group_id'] );

			// Insertamos variables smtp para email principal
			tools::insertConfiguration( 'Email SMTP', 'SMTP_ACTIVE', 'mail', '',  $aConfigGroup->records['configuration_group_id'], '99' );
			tools::insertConfiguration( 'Email Host', 'SMTP_HOST', '', '',  $aConfigGroup->records['configuration_group_id'], '100' );
			tools::insertConfiguration( 'Email Puerto', 'SMTP_PUERTO', '', '',  $aConfigGroup->records['configuration_group_id'], '101' );
			tools::insertConfiguration( 'Email Contraseña', 'SMTP_PASS', '', '',  $aConfigGroup->records['configuration_group_id'], '102' );

			// Insertamos el grupo de configuracion
			$aConfigGroup = tools::insertConfigurationGroup( 'Opciones email', '', 0 );

			// Insertamos la configuracion global
			tools::insertConfiguration( 'Url Facebook', 'EMAIL_URL_FACEBOOK', '', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Url Instagram', 'EMAIL_URL_INSTAGRAM', '', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Url Twitter', 'EMAIL_URL_TWITTER', '', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Url Youtube', 'EMAIL_URL_YOUTUBE', '', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Texto pie', 'EMAIL_TEXT_FOOTER', '', '', $aConfigGroup->records['configuration_group_id'] );

			// Reset cache
			tools::createCacheFile();

			// Mensajes
			$messageStack->addSession( 'success', sprintf(EMAIL_SMTP_INSTALL_SUCCESS, $sTitle), 'success' );

			// Redireccionamos
			tep_redirect( $sUrlPage . '?action=readme' );
		break;

		case 'delete_email':
			// Variables
			$aGetId = tep_db_prepare_input( $_GET['id'] );
			$aPostId = tep_db_prepare_input( $_POST['id'] );
			$sEmails = '';
			$aEmails = json_decode( stripslashes( STORE_OWNER_EMAIL_ADDRESS_GROUP ), true );

			// Si nos envian por get creamos el array
			if ($aGetId != '') {
                $aPostId = [ $aGetId ];
            }

			// Recorremos los id
			foreach( $aPostId as $sId )
				unset( $aEmails[$sId] );

			if (count( $aEmails ) > 0) {
                $sEmails = json_encode( $aEmails );
            }

			tep_db_query( "update " . TABLE_CONFIGURATION . " set configuration_value = '" . $sEmails . "' where configuration_key = 'STORE_OWNER_EMAIL_ADDRESS_GROUP'" );
			tep_configuration_update( STORE_OWNER_EMAIL_ADDRESS_GROUP, $sEmails );

			require ('includes/configuration_cache.php');

			// Redireccionamos
			$messageStack->addSession( 'success', EMAIL_SMTP_DELETE_SUCCESS, 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

		case 'update':
		case 'add_email':
			// css
			$aStyle = [ 'includes/modules/email_smtp/css/style.css' ];

			// Variables
			$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
			$bStoreEmail = ($sGetId == 'store_email');
			$aMessageError = [];
			$sSubtitle = ($sGetId != '' ? TEXT_EDIT : TEXT_ADD) . ' ' . EMAIL_SMTP_TEXT_CONFIGURE_EMAIL . ($sGetId == 'store_email' ? ' ' . EMAIL_SMTP_TEXT_MAIN : '');
			$aButtons = [
				[ 'title' => TEXT_BACK, 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ],
				['title' => TEXT_VERIFY_CONECTION, 'href' => tep_href_link($sUrlPage, $_SERVER['QUERY_STRING']
					. '&subaction=verifysmtp'), 'icon' => 'fa-sync', 'anchor_class' => 'amarillo'],
				[ 'title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];

			$sEmails = '';
			$aEmails = json_decode( stripslashes( STORE_OWNER_EMAIL_ADDRESS_GROUP ), true );
			$aEmails = is_array($aEmails) ? $aEmails : [];

			// Insertar o actualizar
			if( $sPostAction == 'update' )
			{
				// Pasamos todos los post por tep_db_prepare_input
				array_walk( $_POST, function( $value, $key){ global $_POST; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); } );

				// Comprobamos que nos hayan enviado todos los campos
				if (! $bStoreEmail && ((array_key_exists( 'seccion', $_POST ) && count( $_POST['seccion'] ) <= 0) || !array_key_exists( 'seccion', $_POST ))) {
                    $aMessageError['seccion'] = $messageStack->show( [ 'text' => EMAIL_SMTP_ERROR_SECTION, 'class' => 'error' ] );
                }

				if ((array_key_exists( 'user', $_POST ) && $_POST['user'] == '') || !array_key_exists( 'user', $_POST )) {
                    $aMessageError['user'] = $messageStack->show( [ 'text' => EMAIL_SMTP_ERROR_EMAIL, 'class' => 'error' ] );
                }

				if( ! $bStoreEmail || ($bStoreEmail && $_POST['type'] == 'smtp') )
				{
					if ((array_key_exists( 'pass', $_POST ) && $_POST['pass'] == '') || !array_key_exists( 'pass', $_POST )) {
                        $aMessageError['pass'] = $messageStack->show( [ 'text' => EMAIL_SMTP_ERROR_PASSWORD, 'class' => 'error' ] );
                    }

					if ((array_key_exists( 'host', $_POST ) && $_POST['host'] == '') || !array_key_exists( 'host', $_POST )) {
                        $aMessageError['host'] = $messageStack->show( [ 'text' => EMAIL_SMTP_ERROR_HOST, 'class' => 'error' ] );
                    }

					if ((array_key_exists( 'port', $_POST ) && $_POST['port'] == '') || !array_key_exists( 'port', $_POST )) {
                        $aMessageError['port'] = $messageStack->show( [ 'text' => EMAIL_SMTP_ERROR_PORT, 'class' => 'error' ] );
                    }
				}

				// Si no existe errores actualizamos/insertamos
				if( count( $aMessageError ) == 0 )
				{
					if ($sGetId != true) {
                        $sGetId = $_POST['user'];
                    }

					if( $bStoreEmail )
					{
						tep_db_query( "update " . TABLE_CONFIGURATION . " set configuration_value = '" . $_POST['user'] . "' where configuration_key = 'STORE_OWNER_EMAIL_ADDRESS'" );
						tep_configuration_update( STORE_OWNER_EMAIL_ADDRESS, $sEmails );

						tep_db_query( "update " . TABLE_CONFIGURATION . " set configuration_value = '" . $_POST['type'] . "' where configuration_key = 'SMTP_ACTIVE'" );
						tep_configuration_update( SMTP_ACTIVE, $sEmails );

						if( $_POST['type'] == 'smtp' )
						{
							tep_db_query( "update " . TABLE_CONFIGURATION . " set configuration_value = '" . tools::encrypt( $_POST['pass'] ) . "' where configuration_key = 'SMTP_PASS'" );
							tep_configuration_update( SMTP_PASS, $sEmails );

							tep_db_query( "update " . TABLE_CONFIGURATION . " set configuration_value = '" . $_POST['host'] . "' where configuration_key = 'SMTP_HOST'" );
							tep_configuration_update( SMTP_HOST, $sEmails );

							tep_db_query( "update " . TABLE_CONFIGURATION . " set configuration_value = '" . $_POST['port'] . "' where configuration_key = 'SMTP_PUERTO'" );
							tep_configuration_update( SMTP_PUERTO, $sEmails );

										tep_db_query( "update " . TABLE_CONFIGURATION . " set configuration_value = '" . $_POST['smtp_user'] . "' where configuration_key = 'SMTP_USER'" );
										if( defined('SMTP_USER') ) tep_configuration_update( SMTP_USER, $_POST['smtp_user'] );
						}
					}
					else
					{
						$aEmails[$sGetId] = [ $_POST['host'], $_POST['port'], implode( ',', $_POST['seccion'] ), tools::encrypt( $_POST['pass'] ) ];
						$sEmails = json_encode( $aEmails );

						tep_db_query( "update " . TABLE_CONFIGURATION . " set configuration_value = '" . $sEmails . "' where configuration_key = 'STORE_OWNER_EMAIL_ADDRESS_GROUP'" );
						tep_configuration_update( STORE_OWNER_EMAIL_ADDRESS_GROUP, $sEmails );
					}

					require ('includes/configuration_cache.php');

					// Mensaje
					$messageStack->addSession( 'success', ($sGetId != false ? EMAIL_SMTP_EDIT_SUCCESS : EMAIL_SMTP_INSERT_SUCCESS), 'success' );

					// Redireccionamos
					tep_redirect( tep_href_link(  $sUrlPage ) );
				}
			}
            // Verificar la Conexión SMTP
            if( $sPostSubAction == 'verifysmtp' ) {
			if($bStoreEmail == '1') {
				$smtpHost = SMTP_HOST;
				$smtpUser = (defined('SMTP_USER') && SMTP_USER != '' ? SMTP_USER : STORE_OWNER_EMAIL_ADDRESS);
				$smtpPass = tools::decrypt(SMTP_PASS);
				$smtpPort = SMTP_PUERTO;
			} else {
				$smtpHost = $aEmails[$sGetId][0];
				$smtpUser = $_GET['id'];
				$smtpPass = tools::decrypt($aEmails[$sGetId][3]);
				$smtpPort = $aEmails[$sGetId][1];
			}

			$smtp = new SMTP();
			$smtp->setDebugLevel(SMTP::DEBUG_SERVER);

			// 🔹 Capturamos el log dentro de una variable, no en pantalla
			$logSMTP = '';
			$smtp->setDebugOutput(function ($str, $level) use (&$logSMTP) {
				$logSMTP .= htmlspecialchars($str) . "<br>";
			});

			try {
				$smtpHostConnect = ($smtpPort == 465) ? 'ssl://' . $smtpHost : $smtpHost;
				if (!$smtp->connect($smtpHostConnect, $smtpPort)) {
					throw new Exception('La conexión ha fallado. Revise <strong>Host</strong> y <strong>Puerto</strong>.');
				}
				if (!$smtp->hello(gethostname())) {
					throw new Exception('EHLO failed: ' . $smtp->getError()['error']);
				}

				$e = $smtp->getServerExtList();

				if (is_array($e) && array_key_exists('STARTTLS', $e)) {
					if (!$smtp->startTLS()) {
						throw new Exception('Ha fallado STARTTLS: ' . $smtp->getError()['error']);
					}
					if (!$smtp->hello(gethostname())) {
						throw new Exception('EHLO (2) failed: ' . $smtp->getError()['error']);
					}
					$e = $smtp->getServerExtList();
				}

				if (is_array($e) && array_key_exists('AUTH', $e)) {
					if ($smtp->authenticate($smtpUser, $smtpPass)) {
						$messageVerifyInformation = $messageStack->show([
																			'class' => 'success',
																			'text'  => sprintf(EMAIL_SMTP_VERIFY_SUCCESS, $smtpUser)
																				. '<br><br><details open style="margin-top:5px">'
																				. '<summary style="cursor:pointer;font-weight:bold;">Ver log SMTP</summary>'
																				. '<div style="background:#f7f7f7;border:1px solid #ccc;padding:10px;margin-top:5px;max-height:300px;overflow:auto;">'
																				. $logSMTP . '</div></details>'
																		]);
					} else {
						$messageVerifyInformation = $messageStack->show([
																			'class' => 'error',
																			'text'  => sprintf(EMAIL_SMTP_VERIFY_ERROR, $smtpUser, $smtp->getError()['error'])
																				. '<br><br><details open style="margin-top:5px">'
																				. '<summary style="cursor:pointer;font-weight:bold;">Ver log SMTP</summary>'
																				. '<div style="background:#fee;border:1px solid #f99;padding:10px;margin-top:5px;max-height:300px;overflow:auto;">'
																				. $logSMTP . '</div></details>'
																		]);
						throw new Exception('Error de Autentificación: ' . $smtp->getError()['error']);
					}
				}
			} catch (Exception $e) {
				$messageVerifyInformation = $messageStack->show([
																	'class' => 'error',
																	'text'  => $e->getMessage()
																		. '<br><br><details open style="margin-top:5px">'
																		. '<summary style="cursor:pointer;font-weight:bold;">Ver log SMTP</summary>'
																		. '<div style="background:#fee;border:1px solid #f99;padding:10px;margin-top:5px;max-height:300px;overflow:auto;">'
																		. $logSMTP . '</div></details>'
																]);
                }
                $smtp->quit();
            }
            $sHtml .= $messageVerifyInformation;

			// Formulario
			$sHtml .= '<div class="oeBox column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-shield"></i> ' . EMAIL_SMTP_TEXT_CONFIGURATION . ' </div>';
					$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update' ) . '" class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : '';
						$sHtml .= '<input type="submit" style="display: none;" />';

						if( $bStoreEmail )
						{
							$sHtml .= '<label for="type" class="column a02 tright">' . EMAIL_SMTP_ADD_TYPE . ':</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= tep_draw_pull_down_menu( 'type', $aTypeEmail, ($_POST['type'] != '' ? $_POST['type'] : SMTP_ACTIVE), 'id="chgsmtp"' );
								$sHtml .= '<div class="DFhelp">' . EMAIL_SMTP_ADD_TYPE_HELP . '</div>';
							$sHtml .= '</div>';

							$sHtml .= '<div class="xline xline-dashed"></div>';
						}

						$sHtml .= '<label for="user" class="column a02 tright">' . EMAIL_SMTP_ADD_EMAIL . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= array_key_exists( 'user', $aMessageError ) ? $aMessageError['user'] : '';
							$sHtml .= '<input type="text" name="user" id="user" value="' . ($bStoreEmail ? STORE_OWNER_EMAIL_ADDRESS : $sGetId) . '" ' . ($sGetId != true || $bStoreEmail ? '' : 'readonly="readonly"') . '/>';
							$sHtml .= '<div class="DFhelp">' . EMAIL_SMTP_ADD_EMAIL_HELP . '</div>';
						$sHtml .= '</div>';


						if( $bStoreEmail )
						{
							$sHtml .= '<div class="xline xline-dashed ndsmpt"></div>';
							$sHtml .= '<label for="smtp_user" class="column a02 tright ndsmpt">' . EMAIL_SMTP_ADD_SMTP_USER . ':</label>';
							$sHtml .= '<div class="column a10 ndsmpt">';
								$sHtml .= '<input type="text" name="smtp_user" id="smtp_user" value="' . (defined('SMTP_USER') ? SMTP_USER : '') . '"/>';
								$sHtml .= '<div class="DFhelp">' . EMAIL_SMTP_ADD_SMTP_USER_HELP . '</div>';
							$sHtml .= '</div>';

						}
						$sHtml .= '<div class="xline xline-dashed ndsmpt"></div>';

						$sHtml .= '<label for="pass" class="column a02 tright ndsmpt">' . EMAIL_SMTP_ADD_PASSWORD . ':</label>';
						$sHtml .= '<div class="column a10 ndsmpt">';
							$sHtml .= array_key_exists( 'pass', $aMessageError ) ? $aMessageError['pass'] : '';
							$sHtml .= '<input type="password" name="pass" id="pass" value="' . ($bStoreEmail ? (SMTP_PASS != '' || $_POST['pass'] != '' ? tools::decrypt( ($_POST['pass'] != '' ? $_POST['pass'] : SMTP_PASS) ) : '') : ($aEmails[$sGetId][3] != '' ? tools::decrypt( $aEmails[$sGetId][3] ) : '')) . '"/>';
							$sHtml .= '<div class="DFhelp">' . EMAIL_SMTP_ADD_PASSWORD_HELP . '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed ndsmpt"></div>';

						$sHtml .= '<label for="host" class="column a02 tright ndsmpt">' . EMAIL_SMTP_ADD_HOST . ':</label>';
						$sHtml .= '<div class="column a10 ndsmpt">';
							$sHtml .= array_key_exists( 'host', $aMessageError ) ? $aMessageError['host'] : '';
							$sHtml .= '<input type="text" name="host" id="host" value="' . ($bStoreEmail ? ($_POST['host'] != '' ? $_POST['host'] : SMTP_HOST) : $aEmails[$sGetId][0]) . '"/>';
							$sHtml .= '<div class="DFhelp">' . EMAIL_SMTP_ADD_HOST_HELP . '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed" class="ndsmpt" ></div>';

						$sHtml .= '<label for="port" class="column a02 tright ndsmpt">' . EMAIL_SMTP_ADD_PORT . ':</label>';
						$sHtml .= '<div class="column a10 ndsmpt">';
							$sHtml .= array_key_exists( 'port', $aMessageError ) ? $aMessageError['port'] : '';
							$sHtml .= '<input type="text" name="port" id="port" value="' . ($bStoreEmail ? ($_POST['port'] != '' ? $_POST['port'] : SMTP_PUERTO) : $aEmails[$sGetId][1]) . '"/>';
							$sHtml .= '<div class="DFhelp">' . EMAIL_SMTP_ADD_PORT_HELP . '</div>';
						$sHtml .= '</div>';

						if( ! $bStoreEmail )
						{
							$sHtml .= '<div class="xline xline-dashed"></div>';

							$aUsedSeccion = [];
							foreach( $aEmails as $sUser => $aEmail )
									$aUsedSeccion[$sUser] = explode( ',', (string) $aEmail[2] );

							$sHtml .= '<label for="seccion" class="column a02 tright">' . EMAIL_SMTP_ADD_SECTIONS . ':</label>';
							$sHtml .= '<div class="column a10 ax row">';
								$sHtml .= array_key_exists( 'seccion', $aMessageError ) ? $aMessageError['seccion'] : '';
								foreach( $aSecciones as $sSeccion )
								{
									$bUsed = false;
									$sUsed = '';

									foreach( $aUsedSeccion as $sEmailUsed => $aUsed )
										{
											if( in_array( $sSeccion, $aUsed ) && $sEmailUsed != $sGetId )
											{
												$bUsed = true;
												$sUsed = EMAIL_SMTP_ASSIGNED_TO . ' ' . $sEmailUsed;
											}
										}

									$sHtml .= '<div class="column a04"><div class="chcksc' . ($bUsed ? ' tooltip' : '') . '">';
										$sHtml .= '<input ' . ($bUsed ? 'disabled="disabled"' : '') . ' type="checkbox" name="seccion[]" id="sec_' . $sSeccion . '" value="' . $sSeccion . '"';

										// Verifica si $aEmails[$sGetId][2] no es nulo antes de llamar a preg_match
                                        if ($aEmails[$sGetId][2] !== null && preg_match('/' . $sSeccion . '/i', (string) $aEmails[$sGetId][2])) {
											$sHtml .= ' checked="checked"';
										}

										$sHtml .= '/><label for="sec_' . $sSeccion . '"><span></span>' . $sSeccion . '</label>';
										$sHtml .= '<font>' . $sUsed . '</font>';
									$sHtml .= '</div></div>';
								}
								$sHtml .= '<div class="column a12 DFhelp">' . EMAIL_SMTP_ADD_SECTIONS_HELP . '</div>';
							$sHtml .= '</div>';
						}

					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';

			$sJavascript .= '
				<script type="text/javascript">
					if( jQuery("#chgsmtp").length > 0 )
					{
						jQuery("#chgsmtp").change( function()
						{
							if( jQuery(this).val() == "smtp" )
								jQuery(".ndsmpt").css( "display", "block" );
							else
								jQuery(".ndsmpt").css( "display", "none" );
						});

						jQuery("#chgsmtp").trigger("change");
					}
				</script>';
		break;


		default:
			// Configuracion
			$sSubtitle = EMAIL_SMTP_SUBTITLE;

			$aButtons[] = [ 'title' => EMAIL_SMTP_MENU_MAIN_EMAIL, 'href' => tep_href_link( $sUrlPage, 'action=add_email&id=store_email' ), 'icon' => 'fa-archive' ];
			$aButtons[] = [ 'title' => TEXT_OPTIONS, 'href' => tep_href_link( $sUrlPage, 'action=email' ), 'icon' => 'fa-cog' ];
			$aButtons[] = [ 'title' => TEXT_ADD, 'href' => tep_href_link( $sUrlPage, 'action=add_email' ), 'icon' => 'fa-plus' ];

			// Html para el boton masivo
			$sHtmlActionMasivo = '';

			$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> ' . EMAIL_SMTP_CONFIGURED_EMAILS . '</div>';
					$sHtml .= '<form method="post" action="' . tep_href_link( $sUrlPage ) . '" class="oeCntd row ax">';
						$sHtml .= '<table class="xform">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
									$sHtml .= '<td class="sort">' . EMAIL_SMTP_TABLE_EMAIL . '</td>';
									$sHtml .= '<td class="sort">' . EMAIL_SMTP_TABLE_HOST . '</td>';
									$sHtml .= '<td class="sort" width="70">' . EMAIL_SMTP_TABLE_PORT . '</td>';
									$sHtml .= '<td class="sort">' . EMAIL_SMTP_TABLE_SECTIONS . '</td>';
									$sHtml .= '<td width="125">' . EMAIL_SMTP_TABLE_ACTIONS . '</td>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';

								$aEmails = json_decode( stripslashes( STORE_OWNER_EMAIL_ADDRESS_GROUP ), true );

								if( is_array( $aEmails ) && count( $aEmails ) > 0 )
								{
									foreach( $aEmails as $sUser => $aEmail )
									{
										$sHtml .= '<tr data-dblclick="' . tep_href_link( $sUrlPage, 'action=add_email&id=' . $sUser ) . '">';
											$sHtml .= '<td class="chck" align="center"><input type="checkbox" id="id_' . $sUser . '" name="id[]" value="' . $sUser . '"/><label for="id_' . $sUser . '"><span></span></label></td>';

											$sHtml .= '<td>' . $sUser . '</td>';
											$sHtml .= '<td>' . $aEmail[0] . '</td>';
											$sHtml .= '<td>' . $aEmail[1] . '</td>';
											$sHtml .= '<td>' . $aEmail[2] . '</td>';
											$sHtml .= '<td>';
												$sHtml .= '<div class="drop xfselect">';
													$sHtml .= '<div>' . EMAIL_SMTP_TABLE_ACTIONS . '</div>';
													$sHtml .= '<ul class="down down-dngt">';
														$sHtml .= '<li><a href="' . tep_href_link( $sUrlPage, 'action=add_email&id=' . $sUser ) . '" class="hv"><i class="fa fa-server"></i>' . EMAIL_SMTP_TABLE_EDIT_CONFIGURATION . '</a></li>';
														$sHtml .= '<li><a data-confirm="' . EMAIL_SMTP_TABLE_SEND_TEST_MAIL_CONFIGURATION_CONFIRM . '" href="' . tep_href_link( $sUrlPage, 'action=test_email&id=' . $sUser ) . '" class="hv"><i class="fa fa-mail-bulk"></i>' . EMAIL_SMTP_TABLE_SEND_TEST_MAIL_CONFIGURATION . '</a></li>';
														$sHtml .= '<li><a data-confirm="' . EMAIL_SMTP_TABLE_DELETE_CONFIGURATION_CONFIRM . '" href="' . tep_href_link( $sUrlPage, 'action=delete_email&id=' . $sUser ) . '" class="hv"><i class="fa fa-trash"></i>' . EMAIL_SMTP_TABLE_DELETE_CONFIGURATION . '</a></li>';
													$sHtml .= '</ul>';
												$sHtml .= '</div>';
											$sHtml .= '</td>';
										$sHtml .= '</tr>';
									}
								}

							$sHtml .= '</tbody>';
						$sHtml .= '</table>';

						$sHtml .= '<div class="column a12 ax row xform oeTableBottom amiddle">';
							$sHtml .= '<div class="column a06 ax row aflex amiddle">';
								$sHtml .= '<label class="column afluid">' . EMAIL_SMTP_APPLY_ACTION . ':&nbsp;&nbsp;</label>';
								$sHtml .= '<div class="column afluid">';
									$sHtml .= '<div class="drop masv xfselect">';
										$sHtml .= '<div>' . EMAIL_SMTP_TABLE_ACTIONS . '</div>';
										$sHtml .= '<ul class="down drch">';
											$sHtml .= '<li><a data-question="' . EMAIL_SMTP_TABLE_DELETE_CONFIGURATION_CONFIRM . '" data-error="' . EMAIL_SMTP_DELETE_ERROR . '" data-action="' . tep_href_link( $sUrlPage, 'action=delete_email' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . EMAIL_SMTP_TABLE_DELETE_CONFIGURATION . '</a></li>';
										$sHtml .= '</ul>';
									$sHtml .= '</div>';
								$sHtml .= '</div>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';

						$sHtml .= '</div>';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		break;

	}

	// Reemplazamos variable
	$sHtmlModuleOe = $sHtml;

	// MessageStack
	$sMessageStack = $messageStack->output(false);
	$messageStack->reset();

	// Header
	include( 'theme/solenopsis/html/header.php' );

	// Cabecera
	echo '<div class="oeHead column a12 row ax amiddle aflex">';
		echo '<div class="oeTitu column logo afixed" style="padding-left: 59px;"><b><i class="fas fa-envelope"></i> ' . $sTitle . '</b>' . ($sSubtitle !== '' && $sSubtitle !== '0' ? '<small>' . $sSubtitle . '</small>' : '') . '</div>';
		echo '<div class="oeButton column dtright">';
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
