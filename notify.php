<?php
	use util\tools as tools;

	// No indexar
	header( 'X-Robots-Tag: noindex,nofollow' );

	require( 'includes/application_top.php' );
	require(DIR_WS_LANGUAGES . $language . '/notify.php');
	
	// Variables
	$sError = '<div class="msje msje-eror"><div class="msje-icon"></div><ul>';
	$bError = false;
	$sMensaje = '';
	$sProductsId = $_GET['id'];
	$aTermsTrade = $rgpd->postFormCheckTermsTrade( 3 );

	if( $sProductsId == '' )
		tep_redirect( tep_href_link( 'index.php' ) );
	
	if( array_key_exists('action', $_GET) )
	{
		// Comprobamos nombre
		if( isset( $_POST['nombre'] ) && $_POST['nombre'] == '' )
		{
			$bError = true;
			$sError .= '<li>' . NOTIFY_ERROR_NOMBRE . '</li>';
		}		
		
		// Terminos comerciales
		if( $aTermsTrade['RESULT'] == false )
		{
			$bError = true;
			$sError .= '<li>' . $aTermsTrade['MESSAGE_ERROR'] . '</li>';
		}

		// Comprobamos email
		if( isset( $_POST['email'] ) && !tep_validate_email( $_POST['email'] ) )
		{
			$bError = true;
			$sError .= '<li>' . NOTIFY_ERROR_EMAIL . '</li>';
		}
		
		// Si todo esta correcto
		if( isset( $_POST['nombre'] ) && ! $bError )
		{
			// Atributos
			$sAtributos = '';

			// Recremos los atributos
			if( $_POST['id'] )
			{
				foreach( $_POST['id'] as $key => $value )
					$sAtributos .= $key . '-' . $value . ',';
			}
			
			// Atributos
			$sAtributos = substr( $sAtributos, 0, -1 );
		
			// Comprobamos si esta insertado
			$aDatos = tep_db_query( 'select id from products_notifications where products_id = ' . (int)$sProductsId . ' and atributo="' . $sAtributos . '" and idioma = ' . $languages_id . ' and customers_email_address = "' . $_POST['email'] . '"' );

			if( tep_db_num_rows( $aDatos ) == 0 )
			{
				$aSql = array( 'customers_firstname' => $_POST['nombre'],
							   'customers_email_address' => $_POST['email'],
							   'date_added' => date('Y-m-d H:i:s'),
							   'products_id' => (int)$sProductsId,
							   'atributo' => $sAtributos,
							   'idioma' => $languages_id );

				tep_db_perform( 'products_notifications', $aSql );
			}

			$sMensaje = TEXT_NOTIFY_SUCCESS;
			
			// Si estamos logueados nos suscribimos al avisame si esta disponible automaticamente
			if( tep_session_is_registered('customer_id') )
			{
				$aRows = tep_db_query( 'select id_term_pivacy_trade from rgpd_account_term where id_term_pivacy_trade = 3 and customers_id = "' . $customer_id . '"' );
				
				if( tep_db_num_rows( $aRows ) == 0 )
				{				
					tep_db_perform( 'rgpd_account_term', array( 'customers_id' => $customer_id, 'id_term_pivacy_trade' => 3 ) );

					tep_db_perform( 'rgpd_log_term_privacy', array(
						'customers_id' => $customer_id,
						'customers_mail' => $sEmail,
						'ip' => tools::getIP(),
						'date' => date( 'Y-m-d H:i:s' ),
						'type' => 'comercial',
						'term_name' => $aTermsTrade['TERM']['title'],
						'id_term_pivacy' => 3,
						'status' => 1
					) );
				}
			}
		}

	}
	
	if( tep_session_is_registered('customer_id') )
	{
		$account_query = tep_db_query("select customers_firstname, customers_lastname, customers_email_address from " . TABLE_CUSTOMERS . " where customers_id = '" . (int)$customer_id . "'");
		$account = tep_db_fetch_array($account_query);
		$_POST['email'] = $account['customers_email_address'];
	}

	echo '<div id="ltbx-cnsl">';
		echo '<div class="titl">' . TEXT_NOTIFY_TITULO . '</div>';

		if( $sMensaje == '' )
		{
			// Comprobamos si tenemos errores
			if( $bError )
				echo $sError . '</ul></div>'; 

			echo '<div id="rcmd-emil-cntd" style="position: relative;">';
				echo '<form method="post" action="' . tep_href_link( 'notify.php?id=' . $_GET['id'] ) . '&action=true" onsubmit="return app.get(\'alert\').formAjax(this);">';
					echo '<p>
						<label for="nombre">' . ENTRY_FIRST_NAME . '</label>
						<input value="' . $_POST['nombre'] . '" type="text" id="nombre" tabindex="1" autocomplete="off" name="nombre" />
					</p>';

					echo '<p>
						<label for="email">' . ENTRY_EMAIL_ADDRESS . '</label>
						<input value="' . $_POST['email'] . '" type="text" id="email" tabindex="2" autocomplete="off" name="email" />
					</p>';
					
					echo '<div style="margin: 10px 0px 20px;">' . $rgpd->formCheckTermsTrade( 3 ) . '</div>';
										
					echo '<input type="submit" class="sbmt" tabindex="5" value="' . NOTIFY_BOTON . '">';
					
					echo '<div class="clear"></div>';
				echo '</form>';
			echo '</div>';
		}
		else
			echo $messageStack->show( array( 'class' => 'crrt', 'text' => TEXT_NOTIFY_SUCCESS ) )  . '</br>';
	echo '</div>';
?>