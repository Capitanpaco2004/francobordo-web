<?php
	// Tools
	use util\tools as tools;
	use util\date as date;

	// Si nos mandan a instalar cambiamos el modulo por login para que forbidden no salte y podamos instalarlo
	if( array_key_exists( 'action', $_GET ) && in_array( $_GET['action'], [ 'install', 'lockouts_blacklist' ] ) )
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
	$sUrlPage =  '500.php';
	$sTitle = TRANSLATE_500_TITLE;
	$sSubtitle = '';
	$aButtons = [];
	$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);

	# Messagestack estilo
	$messageStack->style = 'solenopsis';

	// Acciones
	switch( $sPostAction )
	{
		case 'readme':
			// Variables
			$sSubtitle = 'Readme de instalación';
			$aButtons = [
				[ 'title' => 'Ver módulo', 'icon' => 'fa-arrow-right', 'href' => $sUrlPage ]
			];

			$sHtml = tools::parsedown( DIR_WS_MODULES . '/500/readme.txt' );
		break;

		case 'install':
			// Insertamos admin file
			tools::insertAdminFiles( $sUrlPage, 1 );

			// Insertamos el grupo de configuracion
			$aConfigGroup = tools::insertConfigurationGroup( 'Página error 500', '', 0 );

			// Insertamos la configuracion
			tools::insertConfiguration( 'Activar logo principal', 'ERROR_500_LOGO', 'true', 'Activar o desactivar el logo principal de nuestra tienda para que se muestre', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Color de fondo', 'ERROR_500_COLOR_BACKGROUND', '#eaeaea', 'Color para el fondo de la pantalla', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Color de texto', 'ERROR_500_COLOR_TEXT', '#c33a2c', 'Color para el texto', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Enlace para llevarlo a una nueva página', 'ERROR_500_LINK_NEW_PAGE', '/', 'Enlace para llevarte directamente a una nueva página al hacer click en el link', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Activar buscador', 'ERROR_500_SEARCH', 'true', 'Activar o desactivar para que aparezca el buscador', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Enlace para el buscador', 'ERROR_500_LINK_SEARCH', 'search.php', 'Enlace para el buscador', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Color para el boton buscar', 'ERROR_500_COLOR_SEARCH_BUTTON', '#c33a2c', 'Color de fondo para el botón buscar', $aConfigGroup->records['configuration_group_id'] );

			// Reset cache
			tools::createCacheFile();

			// Mensajes
			$messageStack->addSession( 'success', TRANSLATE_500_INSTALL_SUCCESS, 'success' );

			// Redireccionamos
			tep_redirect( $sUrlPage . '?action=readme' );
		break;

		case 'update':
			// Pasamos todos los post por tep_db_prepare_input
			array_walk( $_POST, function( $value, $key){ global $_POST; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); } );

			// Recorremos post en busca de los campos ERROR_500 para actualizar
			foreach( $_POST as $key => $value )
			{
				// Si es campo ERROR_500 actualizamos
				if (preg_match( '/^ERROR_500/', $key )) {
                    tep_db_query( 'UPDATE configuration SET configuration_value = "' . $value . '" WHERE configuration_key = "' . $key . '"' );
                }
			}

			// Si nos encontramos en ERROR_500_LOGO y no existe en post es que hemos desactivado
			if (preg_match( '/^ERROR_500/', (string) $key ) && !array_key_exists( 'ERROR_500_LOGO', $_POST )) {
                tep_db_query( 'UPDATE configuration SET configuration_value = "false" WHERE configuration_key = "ERROR_500_LOGO"' );
            }

			// Si nos encontramos en ERROR_500_SEARCH y no existe en post es que hemos desactivado
			if (preg_match( '/^ERROR_500/', (string) $key ) && !array_key_exists( 'ERROR_500_SEARCH', $_POST )) {
                tep_db_query( 'UPDATE configuration SET configuration_value = "false" WHERE configuration_key = "ERROR_500_SEARCH"' );
            }

			// Mensajes
			$messageStack->addSession( 'success', TRANSLATE_500_EDIT_SUCCESS, 'success' );

			// Reset cache
			tools::createCacheFile();

			// Redireccionamos
			tep_redirect( $sUrlPage );
		break;

		default:
			// Variables
			$sSubtitle = TRANSLATE_500_SUBTITLE;
			$aButtons = [
				[ 'title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];

			// Formulario
			$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update' ) . '" class="row ax sp10">';
				$sHtml .= '<div class="oeBox column a12 row ax">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa fa-cog"></i> ' . TRANSLATE_500_GLOBAL_CONFIG . '</div>';
						$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
							$sHtml .= '<label for="ERROR_500_LOGO" class="column a02 tright inline">' . TRANSLATE_500_LOGO_ENABLED . ':</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="checkbox" name="ERROR_500_LOGO" id="ERROR_500_LOGO" ' . (ERROR_500_LOGO == 'true' ? 'checked="checked"' : '') . ' value="true"/><label for="ERROR_500_LOGO"><span></span></label>';
								$sHtml .= '<div class="DFhelp">' . TRANSLATE_500_LOGO_ENABLED_HELP . '</div>';
							$sHtml .= '</div>';

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="ERROR_500_COLOR_BACKGROUND" class="column a02 tright">' . TRANSLATE_500_BACKGROUND_COLOR . ':</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="color" name="ERROR_500_COLOR_BACKGROUND" id="ERROR_500_COLOR_BACKGROUND" value="' . ERROR_500_COLOR_BACKGROUND . '"/>';
								$sHtml .= '<div class="DFhelp">' . TRANSLATE_500_BACKGROUND_COLOR_HELP . '</div>';
							$sHtml .= '</div>';

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="ERROR_500_COLOR_TEXT" class="column a02 tright">' . TRANSLATE_500_TEXT_COLOR . ':</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="color" name="ERROR_500_COLOR_TEXT" id="ERROR_500_COLOR_TEXT" value="' . ERROR_500_COLOR_TEXT . '"/>';
								$sHtml .= '<div class="DFhelp">' . TRANSLATE_500_TEXT_COLOR_HELP . '</div>';
							$sHtml .= '</div>';

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="ERROR_500_LINK_NEW_PAGE" class="column a02 tright">' . TRANSLATE_500_LINK . ':</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="text" name="ERROR_500_LINK_NEW_PAGE" id="ERROR_500_LINK_NEW_PAGE" value="' . ERROR_500_LINK_NEW_PAGE . '"/>';
								$sHtml .= '<div class="DFhelp">' . TRANSLATE_500_LINK_HELP . '</div>';
							$sHtml .= '</div>';

							$sHtml .= '<input type="submit" style="display: none;" />';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				$sHtml .= '<div class="oeBox column a12 row ax">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa fa-cog"></i> ' . TRANSLATE_500_SEARCH_SETTINGS . '</div>';
						$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
							$sHtml .= '<label for="ERROR_500_SEARCH" class="column a02 tright inline">' . TRANSLATE_500_SEARCH_ENABLED . ':</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="checkbox" name="ERROR_500_SEARCH" id="ERROR_500_SEARCH" ' . (ERROR_500_SEARCH == 'true' ? 'checked="checked"' : '') . ' value="true"/><label for="ERROR_500_SEARCH"><span></span></label>';
								$sHtml .= '<div class="DFhelp">' . TRANSLATE_500_SEARCH_ENABLED . '</div>';
							$sHtml .= '</div>';

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="ERROR_500_LINK_SEARCH" class="column a02 tright">' . TRANSLATE_500_SEARCH_LINK . ':</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="text" name="ERROR_500_LINK_SEARCH" id="ERROR_500_LINK_SEARCH" value="' . ERROR_500_LINK_SEARCH . '"/>';
								$sHtml .= '<div class="DFhelp">' . TRANSLATE_500_SEARCH_LINK_HELP . '</div>';
							$sHtml .= '</div>';

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="ERROR_500_COLOR_SEARCH_BUTTON" class="column a02 tright">' . TRANSLATE_500_SEARCH_BUTTON_COLOR . ':</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="color" name="ERROR_500_COLOR_SEARCH_BUTTON" id="ERROR_500_COLOR_SEARCH_BUTTON" value="' . ERROR_500_COLOR_SEARCH_BUTTON . '"/>';
								$sHtml .= '<div class="DFhelp">' . TRANSLATE_500_SEARCH_BACKGROUND_COLOR . '</div>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</form>';
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
		echo '<div class="oeTitu column a03 logo"><b><i style=" font-size: 36px; line-height: 36px;margin-top: -18px;" class="fa fa-bug"></i> ' . $sTitle . '</b>' . ($sSubtitle !== '' && $sSubtitle !== '0' ? '<small>' . $sSubtitle . '</small>' : '') . '</div>';
		echo '<div class="oeButton column a09 dtright">';
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
