<?php
	// Tools
	use util\tools as tools;
	use util\date as date;

	// En 'install' evitamos SOLO el forbidden (ACL) fingiendo el dashboard; NO tocamos SCRIPT_FILENAME
	// para que application_top SIGA exigiendo login. 'sleep_mode' retirada: pantalla estatica sin efectos.
	if( array_key_exists( 'action', $_GET ) && $_GET['action'] == 'install' )
	{
		$_SERVER['PHP_SELF'] = 'index.php';
	}

	// Incluimos el application_top
	require_once( 'includes/application_top.php' );

	// Mostrar errores
	// ini_set('display_errors', 1);
	// error_reporting(1);
	// error_reporting(E_ERROR | E_WARNING | E_PARSE);
	// error_reporting(E_ALL);

	// Variables
	$sUrlPage =  'security.php';
	$sTitle = 'Sistema de seguridad';
	$sSubtitle = '';
	$aButtons = [];
	$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);
	$sGetPage = tep_db_prepare_input( $_GET['page'] ?? '' );
	$sGetOrderby = tep_db_prepare_input( $_GET['orderby'] ?? '' );
	$sGetSort = tep_db_prepare_input( $_GET['sort'] ?? '' );
	$sHtml = '';

	# Messagestack estilo
	$messageStack->style = 'solenopsis';

	// Acciones
	switch( $sPostAction )
	{
		case 'sleep_mode':
			include( 'sleep_mode.php' );
			exit();

		case 'readme':
			// Variables
			$sSubtitle = 'Readme de instalación';
			$aButtons = [
				[ 'title' => 'Ver módulo', 'icon' => 'fa-arrow-right', 'href' => $sUrlPage ]
			];

			$sHtml = tools::parsedown( DIR_WS_MODULES . '/security/readme.txt' );
		break;

		case 'install':
			// Insertamos admin file
			tools::insertAdminFiles( $sUrlPage, 1 );

			// Insertamos el grupo de configuracion
			$aConfigGroup = tools::insertConfigurationGroup( 'Seguridad', '', 0 );

			// Insertamos la configuracion global
			tools::insertConfiguration( 'Escribir en htaccess', 'SECURITY_GLOBAL_WRITE_HTACCESS', 'true', 'Poder escribir automaticamente en el htaccess', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Email de notificación', 'SECURITY_GLOBAL_EMAIL_NOTIFICATION', STORE_OWNER_EMAIL_ADDRESS, 'Email donde se notificara los avisos de seguridad', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Solo un email con resumen', 'SECURITY_GLOBAL_EMAIL_SUMMARY', 'false', 'Solo enviar un email con resumen en vez de varios', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Email donde se envia el backup de la base de datos', 'SECURITY_GLOBAL_EMAIL_DATABASE', STORE_OWNER_EMAIL_ADDRESS, 'Emails para backups', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Mensaje de bloqueo en el servidor', 'SECURITY_GLOBAL_MESSAGE_LOCKOUT_SERVER', 'Este sitio web está utilizando un servicio de seguridad para protegerse de los ataques en línea. Alguna acción que ha realizado activó el control de seguridad. Existen varias acciones que podrían desencadenar este bloqueo como ataque por fuerza bruta.', 'Mensaje de bloqueo', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Mensaje de bloqueo en el servidor, que hacer para solucionarlo', 'SECURITY_GLOBAL_MESSAGE_LOCKOUT_SERVER_RESOLVE', 'Usted puede contactar por correo electrónico al dueño del sitio para hacerles saber que estas bloqueado. Por favor incluya lo que estaba haciendo cuando apareció esta página y su  IP que se encuentra al final de esta página para poder ser desbloqueado', 'Mensaje de bloqueo', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Mensaje de bloqueo en login de usuario', 'SECURITY_GLOBAL_MESSAGE_LOCKOUT_LOGIN', 'Has sido bloqueado durante {TIME} minutos debido a que has intentado iniciar sesión de forma no válida demasiadas veces.', 'Mensaje de bloqueo', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Lista blanca de bloqueo', 'SECURITY_GLOBAL_WHITELIST', '', 'Ips que nunca seran bloqueadas', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Lista negra de bloqueo', 'SECURITY_GLOBAL_BLACKLIST', '', 'Ips que estan bloqueadas', $aConfigGroup->records['configuration_group_id'] );

			// Insertamos la configuracion de fuerza bruta
			tools::insertConfiguration( 'Activar fuerza bruta', 'SECURITY_BRUTEFORCE', 'false', 'Activar fuerza bruta', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Umbral de lista negra', 'SECURITY_BRUTEFORCE_BLACKLIST_COUNT', 3, 'Si falla X veces sera baneado la IP', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Período de bloqueo', 'SECURITY_BRUTEFORCE_LOGIN_PERIOD', 15, 'Tiempo de espera para ser desbloqueado antes del maximo de X bloqueos', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Máximo número de intentos de conexión por usuario', 'SECURITY_BRUTEFORCE_BLACKLIST_TOTAL', 5, 'El numero de intentos de acceso que hace un usuario antes de que el sistema bloquee su IP', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Período retroactivo Lista negra', 'SECURITY_BRUTEFORCE_BLACKLIST_PERIOD', 7, 'Dias que se llevara en la lista negra', $aConfigGroup->records['configuration_group_id'] );

			// Insertamos la configuracion de detección 404
			tools::insertConfiguration( 'Activar detección 404', 'SECURITY_DETECTION_404', 'false', 'Activar detección 404', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Minutos para recordar 404', 'SECURITY_DETECTION_404_PERIOD', '5', 'El número de minutos en el que errores 404 deben ser recordados y contads hacia los bloqueos.', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Umbral de errores 404', 'SECURITY_DETECTION_404_COUNT', '20', 'El número de errores (dentro del período de tiempo de la verificación) que dará lugar a un bloqueo. ', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Lista blanca de archivos 404', 'SECURITY_DETECTION_404_FILES_WHITELIST', '', 'Lista blanca de archivos', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Tipos de archivo ignorados', 'SECURITY_DETECTION_404_FILES_WHITELIST_EXTENSION', '.jpg,.jpeg,.png,.gif,.css', 'Los tipos de archivo listados aquí se guardarán como errores 404 pero no llevarán a bloqueos.', $aConfigGroup->records['configuration_group_id'] );

			// Insertamos la configuracion de modo reposo admin
			tools::insertConfiguration( 'Activar modo reposo', 'SECURITY_ADMIN_AWAY', 'false', 'Activar modo reposo en la administración', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Hora de inicio', 'SECURITY_ADMIN_AWAY_START', '', 'Inicio de hora cuando empezará a estar disponible su acceso.', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Hora de fin', 'SECURITY_ADMIN_AWAY_END', '', 'Fin de hora cuando empezara a no estar disponible su acceso.', $aConfigGroup->records['configuration_group_id'] );

			// Insertamos la configuracion de baneados
			tools::insertConfiguration( 'Banear agentes de usuario', 'SECURITY_BANED_AGENT', 'false', 'Agentes de usuario que estan bloqueados. Introduce cada agente de usuario en una nueva linea.', $aConfigGroup->records['configuration_group_id'] );

			// Reset cache
			tools::createCacheFile();

			// Mensajes
			$messageStack->addSession( 'success', 'El módulo <em>Security</em> se ha instalado correctamente.', 'success' );

			// Redireccionamos
			tep_redirect( $sUrlPage . '?action=readme' );
		break;

		case 'update':
			// Pasamos todos los post por tep_db_prepare_input
			array_walk( $_POST, function( $value, $key){ global $_POST; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); } );

			// Recorremos post en busca de los campos SECURITY para actualizar
			foreach( $_POST as $key => $value )
			{
				// Modificaciones
				switch( $key )
				{
					case 'SECURITY_GLOBAL_BLACKLIST':
					case 'SECURITY_GLOBAL_WHITELIST':
					case 'SECURITY_GLOBAL_EMAIL_DATABASE':
					case 'SECURITY_GLOBAL_EMAIL_NOTIFICATION':
					case 'SECURITY_DETECTION_404_FILES_WHITELIST':
					case 'SECURITY_DETECTION_404_FILES_WHITELIST_EXTENSION':
					case 'SECURITY_BANED_AGENT':
						$value = str_replace( "\n", ',', $value );
					break;
				}

				// Si es campo SECURITY_ actualizamos
				if (preg_match( '/^SECURITY_/', $key )) {
                    tep_db_query( 'UPDATE configuration SET configuration_value = "' . $value . '" WHERE configuration_key = "' . $key . '"' );
                }
			}

			// Si nos encontramos en SECURITY_BRUTEFORCE y no existe en post es que hemos desactivado
			if (preg_match( '/^SECURITY_BRUTEFORCE/', (string) $key ) && !array_key_exists( 'SECURITY_BRUTEFORCE', $_POST )) {
                tep_db_query( 'UPDATE configuration SET configuration_value = "false" WHERE configuration_key = "SECURITY_BRUTEFORCE"' );
            }

			// Si nos encontramos en SECURITY_DETECTION_404 y no existe en post es que hemos desactivado
			if (preg_match( '/^SECURITY_DETECTION_404/', (string) $key ) && !array_key_exists( 'SECURITY_DETECTION_404', $_POST )) {
                tep_db_query( 'UPDATE configuration SET configuration_value = "false" WHERE configuration_key = "SECURITY_DETECTION_404"' );
            }

			// Si nos encontramos en SECURITY_ADMIN_AWAY y no existe en post es que hemos desactivado
			if (preg_match( '/^SECURITY_ADMIN_AWAY/', (string) $key ) && !array_key_exists( 'SECURITY_ADMIN_AWAY', $_POST )) {
                tep_db_query( 'UPDATE configuration SET configuration_value = "false" WHERE configuration_key = "SECURITY_ADMIN_AWAY"' );
            }

			// Mensajes
			$messageStack->addSession( 'success', 'Los datos del módulo <em>Security</em> se han actualizado correctamente.', 'success' );

			// Reset cache
			tools::createCacheFile();

			// Redireccionamos
			tep_redirect( $sUrlPage );
		break;

		case 'log_404_delete':
			// Variables
			$aGetId = tep_db_prepare_input( $_GET['id'] );
			$aPostId = tep_db_prepare_input( $_POST['id'] );
			$sIds = '';

			// Si nos envian por get creamos el array
			if ($aGetId != '') {
                $aPostId = [ $aGetId ];
            }

			// Recorremos los id
			foreach( $aPostId as $sId )
				$sIds .= $sId . ',';

			// Si tenemos id eliminamos
			if ($sIds !== '') {
                tep_db_query( 'delete from security_log where security_log_id in(' . substr( $sIds, 0, -1 ) . ')' );
            }

			// Redireccionamos
			$messageStack->addSession( 'success', 'Los registros se han eliminado correctamente', 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

		case 'log_404':
			// Configuracion
			$sSubtitle = 'Logs de errores 404';
			$aButtons = [
				[ 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ]
			];

			// Html para el boton masivo
			$sHtmlActionMasivo = '<label class="column afluid">Aplicar acción:&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>Acciones</div>
				<ul class="down drch">
					<li><a data-question="¿Realmente deseas eliminar los registros?" data-action="' . tep_href_link( $sUrlPage, 'action=log_404_delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash-o"></i>Eliminar registros</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

			// Variables
			$aFiler = [ 'search' => '', 'search_date' => '' ];
			$aAuxFilter = array_key_exists( 'filter', $_GET ) && is_array($_GET['filter']) ? $_GET['filter'] : (array_key_exists( 'filter', $_POST ) && is_array($_POST['filter']) ? $_POST['filter'] : []);
			$sWhere = '';

			// Limpiamos variables get filter
			array_walk( $aFiler, function( $value, $key){ global $aFiler, $aAuxFilter; $aFiler[$key] = tep_db_prepare_input( array_key_exists( $key, $aAuxFilter ) ? $aAuxFilter[$key] : $aFiler[$key] ); } );

			// Where
			if ($aFiler['search'] !== '' || $aFiler['search_date'] !== '') {
                $sWhere = 'where ';
            }

			if ($aFiler['search'] !== '') {
                $sWhere .= ( $sWhere !== 'where ' ? ' and' : '') . ' LOWER(security_log_ip) LIKE "%' . strtolower( $aFiler['search'] ) . '%"';
            }

			if( $aFiler['search_date'] !== '' )
			{
				$aValue = explode( ' - ', $aFiler['search_date'] );
				$aValue[0] = date::changeDate( $aValue[0], 'espanol', 'y/m/d' );
				$aValue[1] = date::changeDate( $aValue[1], 'espanol', 'y/m/d' );

				$sWhere .= ( $sWhere !== 'where ' ? ' and' : '') . ' security_log_date >= "' . $aValue[0] . '" AND security_log_date <= "' . $aValue[1] . '"';
			}

			// Order by
			if ($sGetOrderby == 'date') {
                $sOrderby = 'security_log_date ' . $sGetSort;
            } elseif ($sGetOrderby == 'ip') {
                $sOrderby = 'security_log_ip ' . $sGetSort;
            } elseif ($sGetOrderby == 'url') {
                $sOrderby = 'security_log_url ' . $sGetSort;
            } elseif ($sGetOrderby == 'referer') {
                $sOrderby = 'security_log_referer ' . $sGetSort;
            } else {
                $sOrderby = 'security_log_date DESC';
            }

			// Sql
			$sSql = 'SELECT security_log_id, DATE_FORMAT( security_log_date, "%d/%m/%Y %H:%i:%s" ) as security_log_date, security_log_ip, security_log_url, security_log_referer
					 FROM security_log
					 ' . $sWhere . ' ORDER BY ' . $sOrderby;

			// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
			$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

			// Sql para el count
			$sSqlCount = 'SELECT COUNT(table_aux.security_log_id) as total FROM (' . $sSql . ') as table_aux';

			// Datos y paginacion
			$aDatoSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
			$aDatos = tep_db_query( $sSql );

			// Mensajes comprobamos si tenemos datos
			if( tep_db_num_rows( $aDatos ) <= 0 )
			{
				if ($sWhere !== '') {
                    $sHtml .= $messageStack->show( [ 'text' => 'El filtro establecido no contiene datos.', 'class' => 'warning' ] );
                } else {
                    $sHtml .= $messageStack->show( [ 'text' => 'No existe ningun registro para mostrar.', 'class' => 'warning' ] );
                }
			}

			$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> Logs de errores 404</div>';
					$sHtml .= '<form method="post" action="' . tep_href_link( $sUrlPage, 'action=log_404' ) . '" class="oeCntd row ax">';
						$sHtml .= '<div class="oeBoxFltr column a12 ax row">';
							$sHtml .= '<div class="column a09 row ax amiddle input-search">';
								$sHtml .= '<label class="column">Buscar: </label> <div class="column"><input type="text" name="filter[search]" placeholder="Introduce búsqueda IP" value="' . $aFiler['search'] . '" autofocus/> <i class="fa fa-search"></i></div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="column a03 tright">';
								$sHtml .= ($sWhere !== '' ? '<a title="Quitar filtro" href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( [ 'filter' ] ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa fa-close"></i></a> ' : '');
								$sHtml .= '<a href="#fltr-lstd" title="Filtrar registros" class="xbutton small hv9 verde-turquesa mgp-inln"><i class="fa fa-filter"></i></a>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<table class="xform">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
									$sHtml .= '<th class="sort" width="150">' . tableSetSort( 'date', 'Fecha' ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'ip', 'IP' ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'url', 'Url' ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'referer', 'Referer' ) . '</th>';
									$sHtml .= '<th width="125">Acciones</th>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';

								while( $aDato = tep_db_fetch_array( $aDatos ) )
								{
									$sHtml .= '<tr>';
										$sHtml .= '<td class="chck" align="center"><input type="checkbox" id="id_' . $aDato['security_log_id'] . '" name="id[]" value="' . $aDato['security_log_id'] . '"/><label for="id_' . $aDato['security_log_id'] . '"><span></span></label></td>';
										$sHtml .= '<td>' . $aDato['security_log_date'] . '</td>';
																				$sHtml .= '<td><a class="tazul" href="http://www.traceip.net/?query=' . $aDato['security_log_ip'] . '" target="_blank">' . $aDato['security_log_ip'] . '</a></td>';
										$sHtml .= '<td>' . $aDato['security_log_url'] . '</td>';
										$sHtml .= '<td>' . $aDato['security_log_referer'] . '</td>';
										$sHtml .= '<td>';
											$sHtml .= '<div class="drop xfselect">';
												$sHtml .= '<div>Acciones</div>';
												$sHtml .= '<ul class="down">';
													$sHtml .= '<li><a data-confirm="¿Realmente deseas añadir la ip ' . $aDato['security_log_ip'] . ' a la lista negra?" href="' . tep_href_link( $sUrlPage, 'action=log_login_ip_blacklist&ip=' . $aDato['security_log_ip'] ) . '" class="hv"><i class="fa fa-server"></i>Asignar IP a lista negra</a></li>';
													$sHtml .= '<li><a data-confirm="¿Realmente deseas eliminar el registro?" href="' . tep_href_link( $sUrlPage, 'action=log_404_delete&id=' . $aDato['security_log_id'] ) . '" class="hv"><i class="fa fa-trash-o"></i>Eliminar registro</a></li>';
												$sHtml .= '</ul>';
											$sHtml .= '</div>';
										$sHtml .= '</td>';
									$sHtml .= '</tr>';
								}

							$sHtml .= '</tbody>';
						$sHtml .= '</table>';

						// Paginación
						$sHtml .= $aDatoSplit->showPaginateTable( tep_get_all_get_params( ['page'] ), 'page', $sHtmlActionMasivo, 'solenopsis' );

						$sHtml .= '</div>';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';

			// Filtro
			$sHtml .= '<form action="' . tep_href_link( $sUrlPage ) . '" method="get" id="fltr-lstd" class="vntn-form mfp-hide zoom-anim-dialog oeBox mfp-white">';
				$sHtml .= '<input type="hidden" name="action" value="log_404" />';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-filter"></i> Filtro logs error 404</div>';
					$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= '<label for="search" class="column a02 tright">Buscar:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="filter[search]" placeholder="Introduce búsqueda IP" value="' . $aFiler['search'] . '"/> ';
						$sHtml .= '</div>';
						$sHtml .= '<div class="xline xline-dashed"></div>';
						$sHtml .= '<label for="search" class="column a02 tright">Fecha:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input value="' . $aFiler['search_date'] . '" data-autoupdate="true" autocomplete="off" name="filter[search_date]" readonly="readonly" class="form-datetime-range" type="text" />';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-none"></div>';
						$sHtml .= '<div class="column a12 tright">';
							$sHtml .= ($sWhere !== '' ? '<a href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( [ 'filter' ] ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa fa-close"></i> Eliminar</a> ' : '');
							$sHtml .= '<div class="xbutton verde hv9 small"><input type="submit"/><span class="fa fa fa-filter"></span> Filtrar</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</form>';
		break;

		case 'log_login_delete':
			// Variables
			$aGetId = tep_db_prepare_input( $_GET['id'] );
			$aPostId = tep_db_prepare_input( $_POST['id'] );
			$sIds = '';

			// Si nos envian por get creamos el array
			if ($aGetId != '') {
                $aPostId = [ $aGetId ];
            }

			// Recorremos los id
			foreach( $aPostId as $sId )
				$sIds .= $sId . ',';

			// Si tenemos id eliminamos
			if ($sIds !== '') {
                tep_db_query( 'delete from security_lockouts where security_lockouts_id in(' . substr( $sIds, 0, -1 ) . ')' );
            }

			// Redireccionamos
			$messageStack->addSession( 'success', 'Los registros se han eliminado correctamente', 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

		case 'log_login_ip_blacklist':
			// Variables
			$sGetIp = tep_db_prepare_input( $_GET['ip'] );

			// Si contenemos ip
			if ($sGetIp != '') {
                $dxSecurity->addIPBlackList( $sGetIp );
            }

			// Reset cache
			tools::createCacheFile();

			// Redireccionamos
			$messageStack->addSession( 'success', 'La ip ' . $sGetIp . ' se ha bloqueado correctamente.', 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

		case 'log_login':
			// Configuracion
			$sSubtitle = 'Logs de login fallidos';
			$aButtons = [
				[ 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ]
			];

			// Html para el boton masivo
			$sHtmlActionMasivo = '<label class="column afluid">Aplicar acción:&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>Acciones</div>
				<ul class="down drch">
					<li><a data-question="¿Realmente deseas eliminar los registros?" data-action="' . tep_href_link( $sUrlPage, 'action=log_login_delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash-o"></i>Eliminar registros</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

			// Variables
			$aFiler = [ 'search' => '', 'search_type' => '', 'search_date' => '' ];
			$aAuxFilter = array_key_exists( 'filter', $_GET ) && is_array($_GET['filter']) ? $_GET['filter'] : (array_key_exists( 'filter', $_POST ) && is_array($_POST['filter']) ? $_POST['filter'] : []);
			$sWhere = '';
			$aTypes = [
				[ 'id' => '', 'text' => 'Todos' ],
				[ 'id' => 'login', 'text' => 'Login fallido' ],
				[ 'id' => 'login_period', 'text' => 'Bloqueo en espera X minutos' ],
				[ 'id' => 'ip_black_list', 'text' => 'Bloqueo de IP' ]
			];

			// Limpiamos variables get filter
			array_walk( $aFiler, function( $value, $key){ global $aFiler, $aAuxFilter; $aFiler[$key] = tep_db_prepare_input( array_key_exists( $key, $aAuxFilter ) ? $aAuxFilter[$key] : $aFiler[$key] ); } );

			// Where
			if ($aFiler['search'] !== '' || $aFiler['search_type'] !== '' || $aFiler['search_date'] !== '' || $aFiler['search_ip']) {
                $sWhere = 'where ';
            }

			if ($aFiler['search'] !== '') {
                $sWhere .= ( $sWhere !== 'where ' ? ' and' : '') . ' (LOWER(security_lockouts_ip) LIKE "%' . strtolower( $aFiler['search'] ) . '%" OR LOWER(security_lockouts_user) LIKE "%' . strtolower( $aFiler['search'] ) . '%")';
            }

			if ($aFiler['search_type'] !== '') {
                $sWhere .= ( $sWhere !== 'where ' ? ' and' : '') . ' security_lockouts_type = "' . $aFiler['search_type'] . '"';
            }

			if( $aFiler['search_date'] !== '' )
			{
				$aValue = explode( ' - ', $aFiler['search_date'] );
				$aValue[0] = date::changeDate( $aValue[0], 'espanol', 'y/m/d' );
				$aValue[1] = date::changeDate( $aValue[1], 'espanol', 'y/m/d' );

				$sWhere .= ( $sWhere !== 'where ' ? ' and' : '') . ' security_lockouts_start >= "' . $aValue[0] . '" AND security_lockouts_start <= "' . $aValue[1] . '"';
			}

			// Order by
			if ($sGetOrderby == 'type') {
                $sOrderby = 'security_lockouts_type ' . $sGetSort;
            } elseif ($sGetOrderby == 'start') {
                $sOrderby = 'security_lockouts_start ' . $sGetSort;
            } elseif ($sGetOrderby == 'end') {
                $sOrderby = 'security_lockouts_end ' . $sGetSort;
            } elseif ($sGetOrderby == 'ip') {
                $sOrderby = 'security_lockouts_ip ' . $sGetSort;
            } elseif ($sGetOrderby == 'user') {
                $sOrderby = 'security_lockouts_user ' . $sGetSort;
            } else {
                $sOrderby = 'security_lockouts_start DESC';
            }

			// Sql
			$sSql = 'SELECT security_lockouts_id, security_lockouts_type, DATE_FORMAT( security_lockouts_start, "%d/%m/%Y %H:%i:%s" ) as security_lockouts_start, DATE_FORMAT( security_lockouts_end, "%d/%m/%Y %H:%i:%s" ) as security_lockouts_end, security_lockouts_ip, security_lockouts_user
					 FROM security_lockouts
					 ' . $sWhere . ' ORDER BY ' . $sOrderby;

			// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
			$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

			// Sql para el count
			$sSqlCount = 'SELECT COUNT(table_aux.security_lockouts_id) as total FROM (' . $sSql . ') as table_aux';

			// Datos y paginacion
			$aDatoSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
			$aDatos = tep_db_query( $sSql );

			// Mensajes comprobamos si tenemos datos
			if( tep_db_num_rows( $aDatos ) <= 0 )
			{
				if ($sWhere !== '') {
                    $sHtml .= $messageStack->show( [ 'text' => 'El filtro establecido no contiene datos.', 'class' => 'warning' ] );
                } else {
                    $sHtml .= $messageStack->show( [ 'text' => 'No existe ningun registro para mostrar.', 'class' => 'warning' ] );
                }
			}

			$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> Logs de login fallidos</div>';
					$sHtml .= '<form method="post" action="' . tep_href_link( $sUrlPage, 'action=log_login' ) . '" class="oeCntd row ax">';
						$sHtml .= '<div class="oeBoxFltr column a12 ax row">';
							$sHtml .= '<div class="column a09 row ax amiddle input-search">';
								$sHtml .= '<label class="column">Buscar: </label> <div class="column"><input type="text" name="filter[search]" placeholder="Introduce búsqueda IP o usuario" value="' . $aFiler['search'] . '" autofocus/> <i class="fa fa-search"></i></div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="column a03 tright">';
								$sHtml .= ($sWhere !== '' ? '<a title="Quitar filtro" href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( [ 'filter' ] ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa fa-close"></i></a> ' : '');
								$sHtml .= '<a href="#fltr-lstd" title="Filtrar registros" class="xbutton small hv9 verde-turquesa mgp-inln"><i class="fa fa-filter"></i></a>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<table class="xform">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
									$sHtml .= '<th class="sort" width="150">' . tableSetSort( 'type', 'Tipo' ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'start', 'Inicio' ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'end', 'Fin' ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'ip', 'IP' ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'user', 'Usuario' ) . '</th>';
									$sHtml .= '<th width="125">Acciones</th>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';

								while( $aDato = tep_db_fetch_array( $aDatos ) )
								{
									$sHtml .= '<tr>';
										$sHtml .= '<td class="chck" align="center"><input type="checkbox" id="id_' . $aDato['security_lockouts_id'] . '" name="id[]" value="' . $aDato['security_lockouts_id'] . '"/><label for="id_' . $aDato['security_lockouts_id'] . '"><span></span></label></td>';
										$sHtml .= '<td>' . $aDato['security_lockouts_type'] . '</td>';
										$sHtml .= '<td>' . $aDato['security_lockouts_start'] . '</td>';
										$sHtml .= '<td>' . $aDato['security_lockouts_end'] . '</td>';
										$sHtml .= '<td><a class="tazul" href="http://www.traceip.net/?query=' . $aDato['security_lockouts_ip'] . '" target="_blank">' . $aDato['security_lockouts_ip'] . '</a></td>';
										$sHtml .= '<td>' . $aDato['security_lockouts_user'] . '</td>';
										$sHtml .= '<td>';
											$sHtml .= '<div class="drop xfselect">';
												$sHtml .= '<div>Acciones</div>';
												$sHtml .= '<ul class="down">';
													$sHtml .= '<li><a data-confirm="¿Realmente deseas añadir la ip ' . $aDato['security_lockouts_ip'] . ' a la lista negra?" href="' . tep_href_link( $sUrlPage, 'action=log_login_ip_blacklist&ip=' . $aDato['security_lockouts_ip'] ) . '" class="hv"><i class="fa fa-server"></i>Asignar IP a lista negra</a></li>';
													$sHtml .= '<li><a data-confirm="¿Realmente deseas eliminar el registro?" href="' . tep_href_link( $sUrlPage, 'action=log_login_delete&id=' . $aDato['security_lockouts_id'] ) . '" class="hv"><i class="fa fa-trash-o"></i>Eliminar registro</a></li>';
												$sHtml .= '</ul>';
											$sHtml .= '</div>';
										$sHtml .= '</td>';
									$sHtml .= '</tr>';
								}

							$sHtml .= '</tbody>';
						$sHtml .= '</table>';

						// Paginación
						$sHtml .= $aDatoSplit->showPaginateTable( tep_get_all_get_params( ['page'] ), 'page', $sHtmlActionMasivo, 'solenopsis' );

						$sHtml .= '</div>';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';

			// Filtro
			$sHtml .= '<form action="' . tep_href_link( $sUrlPage ) . '" method="get" id="fltr-lstd" class="vntn-form mfp-hide zoom-anim-dialog oeBox mfp-white">';
				$sHtml .= '<input type="hidden" name="action" value="log_login" />';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> Logs de login fallidos</div>';
					$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= '<label for="search" class="column a02 tright">Buscar:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="filter[search]" placeholder="Introduce búsqueda IP o usuario" value="' . $aFiler['search'] . '"/> ';
						$sHtml .= '</div>';
						$sHtml .= '<div class="xline xline-dashed"></div>';
						$sHtml .= '<label for="search" class="column a02 tright">Tipo:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= tep_draw_pull_down_menu( 'filter[search_type]', $aTypes, $aFiler['search_type'] );
						$sHtml .= '</div>';
						$sHtml .= '<div class="xline xline-dashed"></div>';
						$sHtml .= '<label for="search" class="column a02 tright">Fecha:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input value="' . $aFiler['search_date'] . '" data-autoupdate="true" autocomplete="off" name="filter[search_date]" readonly="readonly" class="form-datetime-range" type="text" />';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-none"></div>';
						$sHtml .= '<div class="column a12 tright">';
							$sHtml .= ($sWhere !== '' ? '<a href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( [ 'filter' ] ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa fa-close"></i> Eliminar</a> ' : '');
							$sHtml .= '<div class="xbutton verde hv9 small"><input type="submit"/><span class="fa fa fa-filter"></span> Filtrar</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</form>';
		break;

		case 'baned':
			// Variables
			$sSubtitle = 'Usuarios baneados';
			$aButtons = [
				[ 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ],
				[ 'title' => 'Guardar', 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];

			// Texto
			$sHtml .= $messageStack->show( [ 'text' => 'Esta característica te permite banear totalmente clientes, servidores y agentes de usuario de tu sitio sin tener que gestionar cualquier configuración de tu servidor. A las direcciones IP o los agentes de usuario que se encuentran en la lista de abajo no se les permitirá ninguna visita a tu sitio.', 'class' => 'info' ] );

			// Formulario
			$sHtml .= '<div class="oeBox column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-shield"></i> Configuración </div>';
					$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update' ) . '" class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= '<label for="SECURITY_GLOBAL_BLACKLIST" class="column a02 tright">Lista negra de bloqueo:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<textarea name="SECURITY_GLOBAL_BLACKLIST" id="SECURITY_GLOBAL_BLACKLIST">' . implode( "\n", $dxSecurity->configuration['SECURITY_GLOBAL_BLACKLIST'] ) . '</textarea>';
							$sHtml .= '<div class="DFhelp">Ips que estan bloqueadas. Introduce cada ip en una nueva linea.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="SECURITY_BANED_AGENT" class="column a02 tright">Lista negra de bloqueo:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<textarea name="SECURITY_BANED_AGENT" id="SECURITY_BANED_AGENT">' . implode( "\n", $dxSecurity->configuration['SECURITY_BANED_AGENT'] ) . '</textarea>';
							$sHtml .= '<div class="DFhelp">Agentes de usuario que estan bloqueados. Introduce cada agente de usuario en una nueva linea.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<input type="submit" style="display: none;" />';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		break;

		case 'malware_scanner':
			// Variables
			$aFiles = [];

			// Libreria escaner
			include( 'includes/modules/security/includes/classes/malwareScanner/malwareScanner.php' );

			// Escaneamos
			$malwareScanner = new MalwareScanner( getcwd() . '/../' );

			// Recorremos
			foreach( $malwareScanner->files as $sFile )
			{
				// Si no contenemos errores procedemos
				if (!preg_match( '/\-\>/i', (string) $sFile )) {
                    continue;
                }

				// Separamos lo que es el archivo con lo que ha encontrado sospechoso
				$aAux = explode( '->', (string) $sFile );

				// Limpiamos
				$aAux[0] = trim( (string) preg_replace( '/^.+\[0m/i', '', $aAux[0] ) );
				$aAux[1] = trim( (string) preg_replace( '/ [a-z0-9]+$/i', '', $aAux[1] ) );

				// Añadimos
				$aFiles[] = $aAux;
			}

			$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-lock"></i> Archivos con posible malware</div>';
					$sHtml .= '<div class="oeCntd row ax">';
						$sHtml .= '<table class="xform">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= '<th>Ruta</th>';
									$sHtml .= '<th>Código</th>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';

								foreach( $aFiles as $aFile )
								{
									$sHtml .= '<tr>';
										$sHtml .= '<td>' . $aFile[0] . '</td>';
										$sHtml .= '<td>' . $aFile[1] . '</td>';
									$sHtml .= '</tr>';
								}

							$sHtml .= '</tbody>';
						$sHtml .= '</table>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		break;

		case 'permissions_files':
			// Configuracion
			$sSubtitle = 'Permisos de archivos';
			$aButtons = [
				[ 'title' => 'Recargar', 'href' => tep_href_link( $sUrlPage, 'action=permissions_files' ), 'icon' => 'fa fa-refresh' ],
				[ 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ]
			];

			// Variables
			$sPathBackend = getcwd();
			$sPathFrontend = realpath( $sPathBackend . '/../' );
			$aFiles = [
				[ $sPathFrontend . '/.htaccess', 444 ],
				[ $sPathFrontend . '/includes/configure.php', 444 ],
				[ $sPathFrontend . '/includes/', 755 ],
				[ $sPathFrontend . '/includes/', 755 ],
				[ $sPathFrontend . '/includes/.htaccess', 444 ],
				[ $sPathFrontend . '/images/', 777 ],
				[ $sPathFrontend . '/images/atributos/', 777 ],
				[ $sPathFrontend . '/images/banners/', 777 ],
				[ $sPathFrontend . '/images/banners_destacados/', 777 ],
				[ $sPathFrontend .  '/images/categorias/', 777 ],
				[ $sPathFrontend .  '/images/icons/', 777 ],
				[ $sPathFrontend .  '/images/productos/', 777 ],
				[ $sPathFrontend .  '/images/thumbnails/', 777 ],
				[ $sPathFrontend .  '/images/upload/', 777 ],
				[ $sPathFrontend .  '/images/userfiles/', 777 ],
				[ $sPathFrontend .  '/cache/', 777 ],
				[ $sPathFrontend .  '/sitemap.xml', 777 ],
				[ $sPathFrontend .  '/sitemapcategories.xml', 777 ],
				[ $sPathFrontend .  '/sitemapimages.xml', 777 ],
				[ $sPathFrontend .  '/sitemapindex.xml', 777 ],
				[ $sPathFrontend .  '/sitemapmanufacturers.xml', 777 ],
				[ $sPathFrontend .  '/sitemappages.xml', 777 ],
				[ $sPathFrontend .  '/sitemapproducts.xml', 777 ],
				[ $sPathFrontend .  '/sitemapspecials.xml', 777 ],
				[ $sPathFrontend .  '/includes/logs/', 777 ],
				[ $sPathBackend .  '/images/graphs/', 777 ],
				[ $sPathBackend .  '/backups/', 777 ],
				[ $sPathBackend .  '/iae/', 777 ],
			];

			$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-lock"></i> Permisos de archivos</div>';
					$sHtml .= '<div class="oeCntd row ax">';
						$sHtml .= '<table class="xform">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= '<th>Ruta</th>';
									$sHtml .= '<th>Sugerencia</th>';
									$sHtml .= '<th>Valor</th>';
									$sHtml .= '<th>Resultado</th>';
									$sHtml .= '<th width="100">Estado</th>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';

								foreach( $aFiles as $aFile )
								{
									// Resultado
									$sResult = 'OK';

									// Permisos
									$sPerms = preg_replace( '/^0/', '', substr( sprintf( '%o', fileperms( $aFile[0] ) ), -4 ) );

									// Resultado
									if ($sPerms != $aFile[1]) {
                                        $sResult = 'WARNING';
                                    }

									$sHtml .= '<tr>';
										$sHtml .= '<td>' . $aFile[0] . '</td>';
										$sHtml .= '<td>' . $aFile[1] . '</td>';
										$sHtml .= '<td>' . $sPerms . '</td>';
										$sHtml .= '<td>' . $sResult . '</td>';
										$sHtml .= '<td><div style="width: 100%; height: 20px; background: #' . ($sResult === 'OK' ? '22EE5B' : 'FEFF7F') . ';"></div></td>';
									$sHtml .= '</tr>';
								}

							$sHtml .= '</tbody>';
						$sHtml .= '</table>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		break;

		case 'admin_away':
			// Variables
			$sSubtitle = 'Modo reposo para la administración';
			$aButtons = [
				[ 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ],
				[ 'title' => 'Guardar', 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];

			// Texto
			$sHtml .= $messageStack->show( [ 'text' => 'Como la mayoría de las tiendas sólo se actualizan en determinados momentos del día no siempre es necesario proporcionar acceso a la zona de administración 24 horas al día, 7 días a la semana. Las siguientes opciones te permitirán desactivar el acceso a la administración de su tienda durante el período especificado. Esto limitara la exposición de atacantes bloqueando el acceso al sitio dependiendo del horario establecido.', 'class' => 'info' ] );

			// Formulario
			$sHtml .= '<div class="oeBox column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-shield"></i> Configuración </div>';
					$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update' ) . '" class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= '<label for="" class="column a02 tright">Hora actual de su servidor:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" readonly="readonly" name="" value="' . date( 'H:i:s' ) . '"/>';
							$sHtml .= '<div class="DFhelp">Está es la hora exacta de su servidor, comprueba que sea la correcta antes de configurar el modo reposo.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="SECURITY_ADMIN_AWAY" class="column a02 tright inline">Activar modo reposo:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="checkbox" name="SECURITY_ADMIN_AWAY" id="SECURITY_ADMIN_AWAY" ' . ($dxSecurity->configuration['SECURITY_ADMIN_AWAY'] ? 'checked="checked"' : '') . ' value="true"/><label for="SECURITY_ADMIN_AWAY"><span></span></label>';
							$sHtml .= '<div class="DFhelp">Activar modo reposo en la administración.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="SECURITY_ADMIN_AWAY_START" class="column a02 tright">Hora de inicio:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input value="' . $dxSecurity->configuration['SECURITY_ADMIN_AWAY_START'] . '" name="SECURITY_ADMIN_AWAY_START" readonly="readonly" class="form-datetime-time" type="text" />';
							$sHtml .= '<div class="column a12 DFhelp">Inicio de hora cuando empezará a estar disponible su acceso.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="SECURITY_ADMIN_AWAY_END" class="column a02 tright">Hora de fin:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input value="' . $dxSecurity->configuration['SECURITY_ADMIN_AWAY_END'] . '" name="SECURITY_ADMIN_AWAY_END" readonly="readonly" class="form-datetime-time" type="text" />';
							$sHtml .= '<div class="DFhelp">Fin de hora cuando empezara a no estar disponible su acceso.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<input type="submit" style="display: none;" />';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		break;

		case 'detection_404':
			// Variables
			$sSubtitle = 'Detección 404';
			$aButtons = [
				[ 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ],
				[ 'title' => 'Guardar', 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];

			// Texto
			$sHtml .= $messageStack->show( [ 'text' => 'La detección 404 vigila usuarios que estén llegando a una gran cantidad de páginas no existentes y que reciban una gran cantidad de errores 404. La detección 404 asume que un usuario que obtenga un montón de errores 404 en un corto periodo de tiempo está escaneando algo (presumiblemente una vulnerabilidad) y le bloquea en consecuencia. Esto también ofrece el beneficio añadido de ayudarte a detectar problemas ocultos que estén provocando errores 404 en partes no visibles de tu sitio. Todos los errores se guardan en la página de <a href="' . tep_href_link( $sUrlPage, 'action=log_404' ) . '">Log 404</a>.', 'class' => 'info' ] );

			// Formulario
			$sHtml .= '<div class="oeBox column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-shield"></i> Configuración </div>';
					$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update' ) . '" class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= '<label for="SECURITY_DETECTION_404" class="column a02 tright inline">Activar detección 404:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="checkbox" name="SECURITY_DETECTION_404" id="SECURITY_DETECTION_404" ' . ($dxSecurity->configuration['SECURITY_DETECTION_404'] ? 'checked="checked"' : '') . ' value="true"/><label for="SECURITY_DETECTION_404"><span></span></label>';
							$sHtml .= '<div class="DFhelp">Si deseas activar o no la detección 404.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="SECURITY_DETECTION_404_PERIOD" class="column a02 tright">Minutos para recordar 404:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="SECURITY_DETECTION_404_PERIOD" id="SECURITY_DETECTION_404_PERIOD" value="' . $dxSecurity->configuration['SECURITY_DETECTION_404_PERIOD'] . '"/>';
							$sHtml .= '<div class="DFhelp">El número de minutos en el que errores 404 deben ser recordados y contados hacia los bloqueos.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="SECURITY_DETECTION_404_COUNT" class="column a02 tright">Umbral de errores 404:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="SECURITY_DETECTION_404_COUNT" id="SECURITY_DETECTION_404_COUNT" value="' . $dxSecurity->configuration['SECURITY_DETECTION_404_COUNT'] . '"/>';
							$sHtml .= '<div class="DFhelp">El número de errores (dentro del período de tiempo de la verificación) que dará lugar a un bloqueo.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="SECURITY_DETECTION_404_FILES_WHITELIST" class="column a02 tright">Lista blanca de archivos 404:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<textarea name="SECURITY_DETECTION_404_FILES_WHITELIST" id="SECURITY_DETECTION_404_FILES_WHITELIST">' . implode( "\n", $dxSecurity->configuration['SECURITY_DETECTION_404_FILES_WHITELIST'] ) . '</textarea>';
							$sHtml .= '<div class="DFhelp">Utiliza la lista blanca de arriba para evitar guardar errores 404 comunes. Si sabes de un archivo normal de tu sitio que no esté disponible y no quieres que se guarde en los registros apúntalo ahí. Debes listar la ruta completa, comenzando con "/".</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="SECURITY_DETECTION_404_FILES_WHITELIST_EXTENSION" class="column a02 tright">Lista blanca de archivos 404:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<textarea name="SECURITY_DETECTION_404_FILES_WHITELIST_EXTENSION" id="SECURITY_DETECTION_404_FILES_WHITELIST_EXTENSION">' . implode( "\n", $dxSecurity->configuration['SECURITY_DETECTION_404_FILES_WHITELIST_EXTENSION'] ) . '</textarea>';
							$sHtml .= '<div class="DFhelp">Los tipos de archivo listados aquí se guardarán como errores 404 pero no llevarán a bloqueos.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<input type="submit" style="display: none;" />';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		break;

		case 'global':
			// Variables
			$sSubtitle = 'Ajustes globales';
			$aButtons = [
				[ 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ],
				[ 'title' => 'Guardar', 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];

			// Texto
			$sHtml .= $messageStack->show( [ 'text' => 'Las siguientes opciones modifican el funcionamiento de los ajustes globales ofrecidas por el módulo de seguridad.', 'class' => 'info' ] );

			// Formulario
			$sHtml .= '<div class="oeBox column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-shield"></i> Configuración </div>';
					$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update' ) . '" class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= '<label for="SECURITY_GLOBAL_WRITE_HTACCESS" class="column a02 tright inline">Escribir en htaccess:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="checkbox" name="SECURITY_GLOBAL_WRITE_HTACCESS" id="SECURITY_GLOBAL_WRITE_HTACCESS" ' . ($dxSecurity->configuration['SECURITY_GLOBAL_WRITE_HTACCESS'] ? 'checked="checked"' : '') . ' value="true"/><label for="SECURITY_GLOBAL_WRITE_HTACCESS"><span></span></label>';
							$sHtml .= '<div class="DFhelp">Poder escribir automáticamente las reglas en el arcivo .htaccess. Deberás tener permisos de escritura en dicho archivo para poder activar esta característica.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="SECURITY_GLOBAL_EMAIL_NOTIFICATION" class="column a02 tright">Email de notificación:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<textarea name="SECURITY_GLOBAL_EMAIL_NOTIFICATION" id="SECURITY_GLOBAL_EMAIL_NOTIFICATION">' . implode( "\n", $dxSecurity->configuration['SECURITY_GLOBAL_EMAIL_NOTIFICATION'] ) . '</textarea>';
							$sHtml .= '<div class="DFhelp">Email donde se notificara los avisos de seguridad. Podras escribir todos los emails que necesites cada uno en una linea.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="SECURITY_GLOBAL_EMAIL_SUMMARY" class="column a02 tright inline">Solo un email con resumen:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="checkbox" name="SECURITY_GLOBAL_EMAIL_SUMMARY" id="SECURITY_GLOBAL_EMAIL_SUMMARY" ' . ($dxSecurity->configuration['SECURITY_GLOBAL_EMAIL_SUMMARY'] ? 'checked="checked"' : '') . ' value="true"/><label for="SECURITY_GLOBAL_EMAIL_SUMMARY"><span></span></label>';
							$sHtml .= '<div class="DFhelp">Solo enviar un email con resumen de todo una vez al día, en vez de X emails según las necesidades del módulo de seguridad.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="SECURITY_GLOBAL_EMAIL_DATABASE" class="column a02 tright">Email donde se envia el backup de la base de datos:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<textarea name="SECURITY_GLOBAL_EMAIL_DATABASE" id="SECURITY_GLOBAL_EMAIL_DATABASE">' . implode( "\n", $dxSecurity->configuration['SECURITY_GLOBAL_EMAIL_DATABASE'] ) . '</textarea>';
							$sHtml .= '<div class="DFhelp">Emails donde se enviaran los backups. Podras escribir todos los emails que necesites cada uno en una linea.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="SECURITY_GLOBAL_MESSAGE_LOCKOUT_SERVER" class="column a02 tright">Mensaje de bloqueo en el servidor:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<textarea name="SECURITY_GLOBAL_MESSAGE_LOCKOUT_SERVER" id="SECURITY_GLOBAL_MESSAGE_LOCKOUT_SERVER">' . $dxSecurity->configuration['SECURITY_GLOBAL_MESSAGE_LOCKOUT_SERVER'] . '</textarea>';
							$sHtml .= '<div class="DFhelp">El mensaje que se mostrará cuando una IP ha sido totalmente bloqueada.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="SECURITY_GLOBAL_MESSAGE_LOCKOUT_SERVER_RESOLVE" class="column a02 tright">Mensaje de bloqueo en el servidor:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<textarea name="SECURITY_GLOBAL_MESSAGE_LOCKOUT_SERVER_RESOLVE" id="SECURITY_GLOBAL_MESSAGE_LOCKOUT_SERVER_RESOLVE">' . $dxSecurity->configuration['SECURITY_GLOBAL_MESSAGE_LOCKOUT_SERVER_RESOLVE'] . '</textarea>';
							$sHtml .= '<div class="DFhelp">El mensaje que se mostrará cuando una IP ha sido totalmente bloqueada.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="SECURITY_GLOBAL_MESSAGE_LOCKOUT_LOGIN" class="column a02 tright">Mensaje de bloqueo en login de usuario:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<textarea name="SECURITY_GLOBAL_MESSAGE_LOCKOUT_LOGIN" id="SECURITY_GLOBAL_MESSAGE_LOCKOUT_LOGIN">' . $dxSecurity->configuration['SECURITY_GLOBAL_MESSAGE_LOCKOUT_LOGIN'] . '</textarea>';
							$sHtml .= '<div class="DFhelp">El mensaje que se mostrará cuando un usuario ha sido bloqueado al intentar hacer login. Puedes utilizar <b>{TIME}</b> para remplazarlo por la variable en minutos que tendrá que esperar el usuario para poder intentar loguearse de nuevo.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="SECURITY_BRUTEFORCE_WHITELIST" class="column a02 tright">Lista blanca de bloqueo:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<textarea name="SECURITY_BRUTEFORCE_WHITELIST" id="SECURITY_BRUTEFORCE_WHITELIST">' . implode( "\n", $dxSecurity->configuration['SECURITY_GLOBAL_WHITELIST'] ) . '</textarea>';
							$sHtml .= '<div class="DFhelp">Ips que nunca seran bloqueadas. Introduce cada ip en una nueva linea.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="SECURITY_GLOBAL_BLACKLIST" class="column a02 tright">Lista negra de bloqueo:</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<textarea name="SECURITY_GLOBAL_BLACKLIST" id="SECURITY_GLOBAL_BLACKLIST">' . implode( "\n", $dxSecurity->configuration['SECURITY_GLOBAL_BLACKLIST'] ) . '</textarea>';
							$sHtml .= '<div class="DFhelp">Ips que estan bloqueadas. Introduce cada ip en una nueva linea.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<input type="submit" style="display: none;" />';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		break;

		case 'bruteforce':
			// Variables
			$sSubtitle = 'Fuerza bruta';
			$aButtons = [
				[ 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ],
				[ 'title' => 'Guardar', 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];

			$sHtml .= '<div class="row ax">';
				// Texto
				$sHtml .= $messageStack->show( [ 'text' => 'Si alguien dispone de tiempo ilimitado y quisiera probar con un ilimitado número de combinaciones de contraseñas para acceder a tu sitio, finalmente lo conseguiría, ¿verdad? Este método de ataque, conocido como ataque por fuerza bruta, es factible en su tienda ya que, por defecto, al sistema no le importan cuántos intentos emplee un usuario para acceder a su cuenta: siempre permite volver a intentarlo. Al activar un límite a los intentos de acceso se prohíbe que el usuario de un servidor pueda intentar iniciar sesión de nuevo tras alcanzar un número determinado de accesos erróneos.', 'class' => 'info' ] );

				// Formulario
				$sHtml .= '<div class="oeBox column a12 row ax">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-shield"></i> Configuración </div>';
						$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update' ) . '" class="oeCntd row ax xform xform-horizontal">';
							$sHtml .= '<label for="SECURITY_BRUTEFORCE" class="column a02 tright inline">Activar fuerza fruta:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="checkbox" name="SECURITY_BRUTEFORCE" id="SECURITY_BRUTEFORCE" ' . ($dxSecurity->configuration['SECURITY_BRUTEFORCE'] ? 'checked="checked"' : '') . ' value="true"/><label for="SECURITY_BRUTEFORCE"><span></span></label>';
								$sHtml .= '<div class="DFhelp">Si deseas activar o no la fuerza bruta.</div>';
							$sHtml .= '</div>';

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="SECURITY_BRUTEFORCE_BLACKLIST_COUNT" class="column a02 tright">Umbral de lista negra:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="text" name="SECURITY_BRUTEFORCE_BLACKLIST_COUNT" id="SECURITY_BRUTEFORCE_BLACKLIST_COUNT" value="' . $dxSecurity->configuration['SECURITY_BRUTEFORCE_BLACKLIST_COUNT'] . '"/>';
								$sHtml .= '<div class="DFhelp">El número de bloqueos por IP antes de que banear permanentemente de este sitio al servidor.</div>';
							$sHtml .= '</div>';

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="SECURITY_BRUTEFORCE_LOGIN_PERIOD" class="column a02 tright">Período de bloqueo:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="text" name="SECURITY_BRUTEFORCE_LOGIN_PERIOD" id="SECURITY_BRUTEFORCE_LOGIN_PERIOD" value="' . $dxSecurity->configuration['SECURITY_BRUTEFORCE_LOGIN_PERIOD'] . '"/>';
								$sHtml .= '<div class="DFhelp">Tiempo de espera en minutos entre bloqueo y bloqueo para ser desbloqueado antes del maximo de X bloqueos por IP.</div>';
							$sHtml .= '</div>';

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="SECURITY_BRUTEFORCE_BLACKLIST_TOTAL" class="column a02 tright">Máximo número de intentos de conexión por usuario:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="text" name="SECURITY_BRUTEFORCE_BLACKLIST_TOTAL" id="SECURITY_BRUTEFORCE_BLACKLIST_TOTAL" value="' . $dxSecurity->configuration['SECURITY_BRUTEFORCE_BLACKLIST_TOTAL'] . '"/>';
								$sHtml .= '<div class="DFhelp">El numero de intentos de login que hace un usuario antes de que el sistema bloquee su IP X minutos.</div>';
							$sHtml .= '</div>';

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="SECURITY_BRUTEFORCE_BLACKLIST_PERIOD" class="column a02 tright">Período retroactivo Lista negra:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="text" name="SECURITY_BRUTEFORCE_BLACKLIST_PERIOD" id="SECURITY_BRUTEFORCE_BLACKLIST_PERIOD" value="' . $dxSecurity->configuration['SECURITY_BRUTEFORCE_BLACKLIST_PERIOD'] . '"/>';
								$sHtml .= '<div class="DFhelp">Dias que se llevara la IP en la lista negra.</div>';
							$sHtml .= '</div>';

							$sHtml .= '<input type="submit" style="display: none;" />';
						$sHtml .= '</form>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		break;

		default:
			// Variables
			$aButtons = [];

			// Logs login de fuerza bruta
			if ($dxSecurity->configuration['SECURITY_BRUTEFORCE']) {
                $aButtons[] = [ 'title' => 'Log login', 'href' => tep_href_link( $sUrlPage, 'action=log_login' ), 'icon' => 'fa-eye' ];
            }

			// Logs login 404
			if ($dxSecurity->configuration['SECURITY_DETECTION_404']) {
                $aButtons[] = [ 'title' => 'Log 404', 'href' => tep_href_link( $sUrlPage, 'action=log_404' ), 'icon' => 'fa-eye' ];
            }

			$sHtml .= '<div class="row ax columns">';
				// Ajustes globales
				$sHtml .= '<div class="oeBox column a04 row ax">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-shield"></i> Ajustes globales</div>';
						$sHtml .= '<div class="oeCntd">';
							$sHtml .= '<p>Configurar los parámetros básicos que controlan la forma de funcionar la seguridad.</p>';
							$sHtml .= '<a href="' . tep_href_link( $sUrlPage, 'action=global' ) . '" class="xbutton small hv9">Configurar ajustes</a>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				// Detección 404
				$sHtml .= '<div class="oeBox column a04 row ax' . ($dxSecurity->configuration['SECURITY_DETECTION_404'] ? ' live' : '') . '">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-exclamation-triangle"></i> Detección 404</div>';
						$sHtml .= '<div class="oeCntd">';
							$sHtml .= '<p>Automáticamente bloquea a usuarios que están buscando páginas para explotar.</p>';
							$sHtml .= '<a href="' . tep_href_link( $sUrlPage, 'action=detection_404' ) . '" class="xbutton small hv9">Configurar ajustes</a>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				// Modo de reposo
				$sHtml .= '<div class="oeBox column a04 row ax' . ($dxSecurity->configuration['SECURITY_ADMIN_AWAY'] ? ' live' : '') . '">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-bed"></i> Modo de reposo</div>';
						$sHtml .= '<div class="oeCntd">';
							$sHtml .= '<p>Inhabilita el acceso al admin de la tienda en un periodo de tiempo programado.</p>';
							$sHtml .= '<a href="' . tep_href_link( $sUrlPage, 'action=admin_away' ) . '" class="xbutton small hv9">Configurar ajustes</a>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				// Usuarios baneados
				$sHtml .= '<div class="oeBox column a04 row ax">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-ban"></i> Usuarios baneados</div>';
						$sHtml .= '<div class="oeCntd">';
							$sHtml .= '<p>Bloquea direcciones IP específicas y agentes de usuario para que no accedan a este sitio.</p>';
							$sHtml .= '<a href="' . tep_href_link( $sUrlPage, 'action=baned' ) . '" class="xbutton small hv9">Configurar ajustes</a>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				// Activar protección contra fuerza bruta
				$sHtml .= '<div class="oeBox column a04 row ax' . ($dxSecurity->configuration['SECURITY_BRUTEFORCE'] ? ' live' : '') . '">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-user-secret"></i> Protección contra fuerza bruta</div>';
						$sHtml .= '<div class="oeCntd">';
							$sHtml .= '<p>Protege tu sitio contra atacantes que intenten acceder aleatoriamente a tu sitio.</p>';
							$sHtml .= '<a href="' . tep_href_link( $sUrlPage, 'action=bruteforce' ) . '" class="xbutton small hv9">Configurar ajustes</a>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				// Detección de cambios de archivo
				// $sHtml .= '<div class="oeBox column a04 row ax">';
					// $sHtml .= '<div class="oeWrpr">';
						// $sHtml .= '<div class="oeTitu"><i class="fa fa-files-o"></i> Detección de cambios de archivo</div>';
						// $sHtml .= '<div class="oeCntd">';
							// $sHtml .= '<p>Monitoriza el sitio para buscar cambios inesperados en archivos.</p>';
							// $sHtml .= '<a href="#" class="xbutton small hv9">Configurar ajustes</a>';
						// $sHtml .= '</div>';
					// $sHtml .= '</div>';
				// $sHtml .= '</div>';

				// Permisos de archivo
				$sHtml .= '<div class="oeBox column a04 row ax">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-lock"></i> Permisos de archivo</div>';
						$sHtml .= '<div class="oeCntd">';
							$sHtml .= '<p>Lista los permisos de los archivos y directorios en las áreas clave del sitio.</p>';
							$sHtml .= '<a href="' . tep_href_link( $sUrlPage, 'action=permissions_files' ) . '" class="xbutton small hv9">Configurar ajustes</a>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				// Ocultar admin
				$sHtml .= '<div class="oeBox column a04 row ax">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-folder"></i> Cambiar el nombre al directorio admin</div>';
						$sHtml .= '<div class="oeCntd">';
							$sHtml .= '<p>Ocultar la página de acceso al admin cambiando su nombre y evitando el acceso.</p>';
							$sHtml .= '<a href="#" class="xbutton small hv9">Configurar ajustes</a>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				// Reglas de configuración del servidor
				// $sHtml .= '<div class="oeBox column a04 row ax">';
					// $sHtml .= '<div class="oeWrpr">';
						// $sHtml .= '<div class="oeTitu"><i class="fa fa-server"></i> Reglas de configuración del servidor</div>';
						// $sHtml .= '<div class="oeCntd">';
							// $sHtml .= '<p>Si necesitas añadir manualmente reglas generadas para tu servidor, podrás encontrarlas aquí.</p>';
							// $sHtml .= '<a href="#" class="xbutton small hv9">Configurar ajustes</a>';
						// $sHtml .= '</div>';
					// $sHtml .= '</div>';
				// $sHtml .= '</div>';

				// Programación del escaneo de malware
				$sHtml .= '<div class="oeBox column a04 row ax">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-search"></i> Programación del escaneo de malware</div>';
						$sHtml .= '<div class="oeCntd">';
							$sHtml .= '<p>Proteja su tienda con escaneos automatizados de malware. Activando esta funcionalidad, su tienda será escaneada automáticamente cada dia. Si se encuentra un problema, un correo electrónico se enviará a los usuarios seleccionados.</p>';
							$sHtml .= '<a href="' . tep_href_link( $sUrlPage, 'action=malware_scanner' ) . '" class="xbutton small hv9">Configurar ajustes</a>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				// reCAPTCHA
				// $sHtml .= '<div class="oeBox column a04 row ax">';
					// $sHtml .= '<div class="oeWrpr">';
						// $sHtml .= '<div class="oeTitu"><i class="fa fa-keyboard-o"></i> reCAPTCHA</div>';
						// $sHtml .= '<div class="oeCntd">';
							// $sHtml .= '<p>Protege tu tienda de los bots verificando que la persona que envía comentarios o intenta acceder es efectivamente humano.</p>';
							// $sHtml .= '<a href="#" class="xbutton small hv9">Configurar ajustes</a>';
						// $sHtml .= '</div>';
					// $sHtml .= '</div>';
				// $sHtml .= '</div>';

				// Nuestra red de Phising
				$sHtml .= '<div class="oeBox column a04 row ax">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> Nuestro bot de Phising</div>';
						$sHtml .= '<div class="oeCntd">';
							$sHtml .= '<p>Unete a nuestro bot de Phising que escanera su checkout en busca de alguna url que este realizando Phising.</p>';
							$sHtml .= '<a href="#" class="xbutton small hv9">Configurar ajustes</a>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				$sHtml .= '<div class="oeBox column a04 row ax"></div>';
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
	echo '<div class="oeHead column a12 row ax amiddle">';
		echo '<div class="oeTitu column a03 logo"><b><i class="fa fa-shield"></i> ' . $sTitle . '</b>' . ($sSubtitle !== '' && $sSubtitle !== '0' ? '<small>' . $sSubtitle . '</small>' : '') . '</div>';
		echo '<div class="oeButton column a09 dtright">';
			foreach( $aButtons as $aButton )
				echo '<a class="xbutton hv8 small' . (array_key_exists( 'anchor_class', $aButton ) ? ' ' . $aButton['anchor_class'] : '') . '" ' . (array_key_exists( 'extra', $aButton ) ? $aButton['extra'] : '') . ' ' . (array_key_exists( 'title', $aButton ) ? 'title="' . $aButton['title'] . '"' : '') . ' href="' . (array_key_exists( 'href', $aButton ) ? $aButton['href'] : 'javascript:void(0);') . '"><i class="fa ' . $aButton['icon'] . '"></i>' . $aButton['title'] . '</a> ';
		echo '</div>';
	echo '</div>';

	// Comprobamos si estamos en lista blanca
	if (!in_array( $dxSecurity->ip, $dxSecurity->configuration['SECURITY_GLOBAL_WHITELIST'] )) {
        echo $messageStack->show( [ 'text' => 'La ip ' . $dxSecurity->ip . ' es la actual de este ordenador y no se encuentra en lista blanca si es su IP y es frecuentada muchas veces se aconseja <a href="' . tep_href_link( $sUrlPage, 'action=global' ) . '">añadirla</a>.', 'class' => 'info' ] );
    }

	// Mensajes
	echo $sMessageStack;

	// Pintamos
	echo $sHtmlModuleOe;

	// Footer
	include( 'theme/solenopsis/html/footer.php' );
?>
