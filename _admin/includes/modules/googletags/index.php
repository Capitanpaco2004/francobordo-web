<?php
/**
 * Addons Google Tags - oscDenox
 *
 * @author    Denox - Israel.Gavino
 * @copyright Copyright(c) 2019 Denox Global Services SL
 *
 * Project Name : Addon para Google Tags
 * Created By  : Denox Global Services SL
 * Created On  : 01-03-2019
 * Support: www.denox.es
 *
 * Basado para su desarrollo en la Documentación de Google Analytics para el Seguimiento Global Gtag.js
 *  - https://developers.google.com/analytics/devguides/collection/gtagjs/?hl=es-419
 */

 	// Tools
	use util\tools as tools;
	use util\date as date;

	// Si nos mandan a instalar cambiamos el modulo por login para que forbidden no salte y podamos instalarlo
	if( array_key_exists( 'action', $_GET ) && in_array( $_GET['action'], array( 'install' ) ) )
	{
		$_SERVER['PHP_SELF'] = 'login.php';
		$_SERVER['SCRIPT_FILENAME'] = 'login.php';
	}

	// Incluimos el application_top
	require( 'includes/application_top.php' );

	// Variables
	$sUrlPage =  'googletags.php';
	$sTitle = 'Google Tags: Configuración';
	$sSubtitle = '';
	$aButtons = array();
	$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);

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

			$sHtml = tools::parsedown( DIR_WS_MODULES . '/googletags/readme.txt' );
		break;

		case 'install':
			// Insertamos admin file
			tools::insertAdminFiles( $sUrlPage, 1 );

			// Insertamos el grupo de configuracion
			$aConfigGroup = tools::insertConfigurationGroup( 'Google Tags', '', 0 );

			// Insertamos la configuracion
			tools::insertConfiguration( 'Google Tag por dominio', 'GOOGLETAG_DOMAINS', '', 'Activa/desactiva los googletags por dominio.', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Google Analytics ID', 'GOOGLETAG_ANALYTICS_ID', '', 'Introduzca el ID de Google Analytics de la Etiqueta de sitio web global (gtag.js) para activar el seguimiento de las visitas de tu tienda online.<br>Este código lo obtendrás en tu cuenta de Analytics en <i>Información de seguimiento --> Código de Seguimiento</i>.<br>Tan solo deberás de introducir el ID, como te mostramos en el siguiente ejemplo: <strong>UA-XXXXXXXX-X</strong>', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Google Analytics - Comercio Electrónico Mejorado', 'GOOGLETAG_ANALYTICS_ECOMMERCE_ENHACED', '', '¿Quieres activar el seguimiento de Comercio Electrónico Mejorado en tu cuenta de Google Analytics?<br>Recuerda que debes de activar esto en la configuración de tu cuenta de Google Analytics.', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Google Ads ID', 'GOOGLETAG_ADS_ID', '', 'Si realizas Campañas de Publicidad SEM en Google Ads, necesitarás realizar un seguimiento de tus conversiones y usuarios que entran en tu tienda desde la publicidad. Para ello, introduzca el ID de Google Ads de la Etiqueta de sitio web global (gtag.js) para activar el seguimiento.<br>Este código lo obtendrás en tu cuenta de Adwords en <i>Medición --> Conversiones --> Código de Conversión de Ventas</i>.<br>Tan solo deberás de introducir el ID, como te mostramos en el siguiente ejemplo: <strong>AW-XXXXXXXXXX</strong>', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Google Ads Conversion Label', 'GOOGLETAG_ADS_CONVERSION_LABEL', '', 'Además, para realizar el correcto seguimiento del Valor de las conversiones es recomendable configurar el label que obtendrás en el segundo código de Event snippet que te habrá dado al sacar el valor de configuración de Conversiones.<br>En este caso, tan solo deberás de introducir la parte del Label como te mostramos en el siguiente ejemplo del código que te dará la herramienta de Google Ads: <strike>AW-1019909588</strike>/<strong>QAzKCPyK8gEQ1Kuq5gM</strong>', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Google Search Console - Etiqueta HTML', 'GOOGLETAG_SEARCHCONSOLE_ID', '', 'Si deseas verificar la propiedad en Google Search Console, será necesario que introduzcas el código de la Etiqueta HTML que te facilitan al dar de alta tu propiedad.<br>Para ello debes de introducir solo la parte que te señalamos en el ejemplo a continuación: <meta name="google-site-verification" content="<strong>XXXXXXXXXXXXXXXXXXXXXXXXXXX</strong>" />', $aConfigGroup->records['configuration_group_id'] );

			// Reset cache
			tools::createCacheFile();

			// Mensajes
			$messageStack->addSession( 'success', 'El módulo <em>Google Tags</em> se ha instalado correctamente.', 'success' );

			// Redireccionamos
			tep_redirect( $sUrlPage . '?action=readme' );
		break;

		case 'update':
			// Pasamos todos los post por tep_db_prepare_input
			array_walk( $_POST, function( $value, $key){ global $_POST; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); } );

			// Recorremos post en busca de los campos GOOGLETAG para actualizar
			foreach( $_POST as $key => $value )
			{
				// Si es campo GOOGLETAG actualizamos
				if( preg_match( '/^GOOGLETAG/', $key ) )
					tep_db_query( 'UPDATE configuration SET configuration_value = "' . $value . '" WHERE configuration_key = "' . $key . '"' );
				elseif( $key == 'tag_id' ) {
					tep_db_query( "UPDATE configuration SET configuration_value = '" . json_encode( $value ) . "' WHERE configuration_key = 'GOOGLETAG_ANALYTICS_ID'" );
				}
				elseif( $key == 'ads_id' ) {
					tep_db_query( "UPDATE configuration SET configuration_value = '" . json_encode( $value ) . "' WHERE configuration_key = 'GOOGLETAG_ADS_ID'" );
				}
				elseif( $key == 'ads_label' ) {
					tep_db_query( "UPDATE configuration SET configuration_value = '" . json_encode( $value ) . "' WHERE configuration_key = 'GOOGLETAG_ADS_CONVERSION_LABEL'" );
				}
				elseif( $key == 'search_id' ) {
					tep_db_query( "UPDATE configuration SET configuration_value = '" . json_encode( $value ) . "' WHERE configuration_key = 'GOOGLETAG_SEARCHCONSOLE_ID'" );
				}
			}

			// Si nos encontramos en GOOGLETAG_DOMAINS y no existe en post es que hemos desactivado
			if( empty( $_POST['GOOGLETAG_DOMAINS'] ) )
				tep_db_query( 'UPDATE configuration SET configuration_value = "false" WHERE configuration_key = "GOOGLETAG_DOMAINS"' );

			// Mensajes
			$messageStack->addSession( 'success', 'Los datos de la configuración de seguimiento de <em>Google Tags</em> se han actualizado correctamente.', 'success' );

			// Reset cache
			tools::createCacheFile();

			// Si en el servidor está instalado OPCache, reseteamos valores
			if( tools::checkOpcache() == 'true' )
			{
				opcache_reset();
			}

			// Redireccionamos
			tep_redirect( $sUrlPage );
		break;

		default:
			// Variables
			$sSubtitle = 'Ajustes de configuración';
			$sHtml = '';
			$aButtons = array( array( 'title' => 'Guardar', 'icon' => 'fa-floppy-o', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ) );
			$aGTagEcommerce = array( array( 'id' => 'si', 'text' => 'Si' ), array( 'id' => 'no', 'text' => 'No' ) );

			// Javascript
			//$aJs = array( 'includes/modules/googletags/js/index.js' );

			// Descomponemos GOOGLETAG_ANALYTICS_ID
			$aGoogleAnalyticsID = json_decode( stripslashes( GOOGLETAG_ANALYTICS_ID ), true );
			// Descomponemos GOOGLETAG_ADS_ID
			$aGoogleAdsID = json_decode( stripslashes( GOOGLETAG_ADS_ID ), true );
			// Descomponemos GOOGLETAG_ADS_CONVERSION_LABEL
			$aGoogleAdsLabel = json_decode( stripslashes( GOOGLETAG_ADS_CONVERSION_LABEL ), true );
			// Descomponemos GOOGLETAG_SEARCHCONSOLE_ID
			$aGoogleSearchID = json_decode( stripslashes( GOOGLETAG_SEARCHCONSOLE_ID ), true );

			// Formulario
			$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update' ) . '" class="row ax sp10">';
				$sHtml .= '<div class="oeBox column a12 row ax">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa fa-cog"></i> Configuración general</div>';
						$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
							$sHtml .= '<label for="GOOGLETAG_DOMAINS" class="column a02 tright">Google Tags por dominios:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="checkbox" name="GOOGLETAG_DOMAINS" id="GOOGLETAG_DOMAINS" ' . (defined( 'GOOGLETAG_DOMAINS' ) && GOOGLETAG_DOMAINS == 'true' ? 'checked="checked"' : '') . ' value="true"/><label for="GOOGLETAG_DOMAINS"><span></span></label>';

								$sHtml .= '<div class="DFhelp">Marca esta opción si dispone de un Google Tag ID por cada dominio de su web. En caso de no marcarse, será el mismo para todos los idiomas.</div>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				$sHtml .= '<div class="oeBox column a12 row ax">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa fa-cog"></i> Google Analytics</div>';
						$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
							$sHtml .= tools::getInputLanguages( 'tag_id', 'Google Analytics ID:', $aGoogleAnalyticsID, 'Introduzca el ID de Google Analytics de la Etiqueta de sitio web global (gtag.js) para activar el seguimiento de las visitas de tu tienda online.<br>Este código lo obtendrás en tu cuenta de Analytics en <strong>Información de seguimiento --> Código de Seguimiento</strong>.<br><br>Tan solo deberás de introducir el ID, como te mostramos en el siguiente ejemplo: <strong>UA-XXXXXXXX-X</strong>' );

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="GOOGLETAG_ANALYTICS_ECOMMERCE_ENHACED" class="column a02 tright inline">Seguimiento de Comercio Electrónico Mejorado:</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= tep_draw_pull_down_menu( 'GOOGLETAG_ANALYTICS_ECOMMERCE_ENHACED', $aGTagEcommerce, GOOGLETAG_ANALYTICS_ECOMMERCE_ENHACED );
								$sHtml .= '<div class="DFhelp">¿Quieres activar el seguimiento de Comercio Electrónico Mejorado en tu cuenta de Google Analytics?<br>Recuerda que debes de activar esto en la configuración de tu cuenta de Google Analytics.</strong></div>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				$sHtml .= '<div class="oeBox column a12 row ax">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa fa-cog"></i> Google Adwords</div>';
						$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
							$sHtml .= tools::getInputLanguages( 'ads_id', 'Google Adwords:', $aGoogleAdsID, 'Si realizas Campañas de Publicidad SEM en Google Ads, necesitarás realizar un seguimiento de tus conversiones y usuarios que entran en tu tienda desde la publicidad. Para ello, introduzca el ID de Google Ads de la Etiqueta de sitio web global (gtag.js) para activar el seguimiento.<br>Este código lo obtendrás en tu cuenta de Adwords en <i>Medición --> Conversiones --> Código de Conversión de Ventas</i>.<br><br>Tan solo deberás de introducir el ID, como te mostramos en el siguiente ejemplo: <strong>AW-XXXXXXXXXX</strong>' );

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= tools::getInputLanguages( 'ads_label', 'Google Ads Conversion Label:', $aGoogleAdsLabel, 'Además, para realizar el correcto seguimiento del Valor de las conversiones es recomendable configurar el label que obtendrás en el segundo código de Event snippet que te habrá dado al sacar el valor de configuración de Conversiones.<br><br>En este caso, tan solo deberás de introducir la parte del Label como te mostramos en el siguiente ejemplo: <strike>AW-1019909588</strike>/<strong>QAzKCPyK8gEQ1Kuq5gM</strong>' );
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				$sHtml .= '<div class="oeBox column a12 row ax">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa fa-cog"></i> Google Search Console</div>';
						$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
							$sHtml .= tools::getInputLanguages( 'search_id', 'Etiqueta HTML ID:', $aGoogleSearchID, 'Si deseas verificar la propiedad en Google Search Console, será necesario que introduzcas el código de la Etiqueta HTML que te facilitan al dar de alta tu propiedad.<br>Para ello debes de introducir solo la parte que te señalamos en el ejemplo a continuación:<br><br>Ejemplo: meta name="google-site-verification" content="<strong>XXXXXXXXXXXXXXXXXXXXXXXXXXX' );

							$sHtml .= '<input type="submit" style="display: none;" />';
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
		echo '<div class="oeTitu column a03 logo" style="padding-left:55px;"><b><i style=" font-size: 36px; line-height: 36px;margin-top: -18px;" class="fa fa-analytics"></i> ' . $sTitle . '</b>' . ($sSubtitle ? '<small>' . $sSubtitle . '</small>' : '') . '</div>';
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