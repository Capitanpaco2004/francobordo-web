<?php
	// Tools
	use util\tools as tools;
	use util\date as date;

	// Mostrar errores
	// ini_set('display_errors', 1);
	// error_reporting(1);
	// error_reporting(E_ERROR | E_WARNING | E_PARSE);
	// error_reporting(E_ALL);

	// Variables
	$sUrlPage =  'email_smtp.php';
	$sTitle = 'Emails';
	$sSubtitle = '';
	$aButtons = [];
	$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);
	$sHtml = '';

	# Input file
	$dmInputFile = new inputFile( [
		'name' => 'EMAIL_IMAGE_HEADER_LOGO',
		'path_upload' => getcwd() . '/../includes/modules/email/images',
		'file_name' => 'logo_header.png'
	] );

	# Messagestack estilo
	$messageStack->style = 'solenopsis';

	// Acciones
	switch( $sPostAction )
	{
		case 'update_options':
			// Pasamos todos los post por tep_db_prepare_input
			array_walk( $_POST, function( $value, $key){ global $_POST; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); } );

			// Imagen si valida, subimos
			if ($dmInputFile->validate()) {
                $dmInputFile->upload();
            } else
			{
				$messageStack->addSession( 'success', $dmInputFile->error, 'error' );
				tep_redirect( tep_href_link( $sUrlPage ) );
			}

			// Recorremos post en busca de los campos EMAIL para actualizar
			foreach( $_POST as $key => $value )
			{
				if ($key === 'EMAIL_TEXT_FOOTER') {
                    $value = tep_db_input( json_encode( $value, JSON_UNESCAPED_UNICODE ) );
                }

				// Si es campo EMAIL_ actualizamos
				if (preg_match( '/^EMAIL_/', $key )) {
                    tep_db_query( 'UPDATE configuration SET configuration_value = "' . $value . '" WHERE configuration_key = "' . $key . '"' );
                }
			}

			// Checkbox false
			foreach( [ 'EMAIL_URL_FACEBOOK', 'EMAIL_URL_INSTAGRAM', 'EMAIL_URL_TWITTER', 'EMAIL_URL_YOUTUBE'] as $key )
			{
				if (!isset( $_POST[$key] )) {
                    tep_db_query( 'UPDATE configuration SET configuration_value = "false" WHERE configuration_key = "' . $key . '"' );
                }
			}

			// Mensajes
			$messageStack->addSession( 'success', sprintf(EMAIL_SMTP_OPTIONS_SUCCESS, $sTitle), 'success' );

			// Reset cache
			tools::createCacheFile();

			// Redireccionamos
			tep_redirect( tep_href_link( $sUrlPage, 'action=email' ) );
		break;

		case 'email':
			// Variables
			$aButtons = [
				[ 'title' => TEXT_BACK, 'icon' => 'fa fa-arrow-left', 'href' => tep_href_link( $sUrlPage ) ],
				[ 'title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];
			$sSubtitle = TEXT_OPTIONS;

			// Formulario
			$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update_options' ) . '" enctype="multipart/form-data" class="oeBox column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-shield"></i> ' . EMAIL_SMTP_OPTIONS_SOCIAL_MEDIA . ' </div>';
					$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';

						$sHtml .= '<label for="EMAIL_URL_FACEBOOK" class="column a02 tright inline">' . EMAIL_SMTP_OPTIONS_ENABLE_FACEBOOK . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="checkbox" name="EMAIL_URL_FACEBOOK" id="EMAIL_URL_FACEBOOK" ' . (EMAIL_URL_FACEBOOK == 'true' ? 'checked="checked"' : '') . ' value="true"/><label for="EMAIL_URL_FACEBOOK"><span></span></label>';
							$sHtml .= '<div class="DFhelp">' . EMAIL_SMTP_OPTIONS_ENABLE_FACEBOOK_HELP . '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="EMAIL_URL_INSTAGRAM" class="column a02 tright inline">' . EMAIL_SMTP_OPTIONS_ENABLE_INSTAGRAM . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="checkbox" name="EMAIL_URL_INSTAGRAM" id="EMAIL_URL_INSTAGRAM" ' . (EMAIL_URL_INSTAGRAM == 'true' ? 'checked="checked"' : '') . ' value="true"/><label for="EMAIL_URL_INSTAGRAM"><span></span></label>';
							$sHtml .= '<div class="DFhelp">' . EMAIL_SMTP_OPTIONS_ENABLE_INSTAGRAM_HELP . '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="EMAIL_URL_TWITTER" class="column a02 tright inline">' . EMAIL_SMTP_OPTIONS_ENABLE_TWITTER . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="checkbox" name="EMAIL_URL_TWITTER" id="EMAIL_URL_TWITTER" ' . (EMAIL_URL_TWITTER == 'true' ? 'checked="checked"' : '') . ' value="true"/><label for="EMAIL_URL_TWITTER"><span></span></label>';
							$sHtml .= '<div class="DFhelp">' . EMAIL_SMTP_OPTIONS_ENABLE_TWITTER_HELP . '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="EMAIL_URL_YOUTUBE" class="column a02 tright inline">' . EMAIL_SMTP_OPTIONS_ENABLE_YOUTUBE . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="checkbox" name="EMAIL_URL_YOUTUBE" id="EMAIL_URL_YOUTUBE" ' . (EMAIL_URL_YOUTUBE == 'true' ? 'checked="checked"' : '') . ' value="true"/><label for="EMAIL_URL_YOUTUBE"><span></span></label>';
							$sHtml .= '<div class="DFhelp">' . EMAIL_SMTP_OPTIONS_ENABLE_YOUTUBE_HELP . '</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				$sHtml .= '<div class="oeWrpr" style="margin-top: 20px;">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-file-image-o"></i> ' . EMAIL_SMTP_OPTIONS_IMAGES . ' </div>';
					$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= '<label for="EMAIL_IMAGE_HEADER_LOGO" class="column a02 tright">' . EMAIL_SMTP_OPTIONS_HEAD_LOGO . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= $dmInputFile->show();
							$sHtml .= '<div class="DFhelp">' . EMAIL_SMTP_OPTIONS_HEAD_LOGO_HELP . '</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				$sHtml .= '<div class="oeWrpr" style="margin-top: 20px;">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-text-width"></i> ' . EMAIL_SMTP_OPTIONS_TEXTS . ' </div>';
					$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= tools::getInputLanguages( 'EMAIL_TEXT_FOOTER', EMAIL_SMTP_OPTIONS_FOOTER_TEXT . ':', json_decode( tep_db_prepare_input( EMAIL_TEXT_FOOTER ), true ), EMAIL_SMTP_OPTIONS_FOOTER_TEXT_HELP );
					$sHtml .= '</div>';
				$sHtml .= '</div>';
				$sHtml .= '<input type="submit" style="display: none;" />';
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
	echo '<div class="oeHead column a12 row ax amiddle aflex">';
		echo '<div class="oeTitu column logo afixed" style="padding-left: 52px;"><b><i class="fa fa-envelope"></i> ' . $sTitle . '</b>' . ($sSubtitle !== '' && $sSubtitle !== '0' ? '<small>' . $sSubtitle . '</small>' : '') . '</div>';
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
