<?php
	use util\tools as tools;

	// Aplicacion
	include( 'includes/application.php' );

	// Breadcrumb
	$breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_ACCOUNT_NEWSLETTERS, '', 'SSL'));

	// Consulta
	$newsletter_query = tep_db_query( 'select customers_newsletter from ' . TABLE_CUSTOMERS . ' where customers_id = "' . (int)$customer_id . '"' );
	$newsletter = tep_db_fetch_array( $newsletter_query );

	// Si nos envian el formulario
	if( $_SERVER['REQUEST_METHOD'] == 'POST' )
	{
		// Obtenemos los id que tenemos asignados
		$aSubscribed = array_values( pharaonix_getArrayAssociativeSql( 'select id_term_pivacy_trade from rgpd_account_term where customers_id = "' . (int)$customer_id . '"', 'id_term_pivacy_trade', 'id_term_pivacy_trade', false, 1 ) );
		$aSubscribedNow = $_POST['id'];
		$aSubscribedAll = array_values( pharaonix_getArrayAssociativeSql( 'SELECT id_term_pivacy_trade, title, info FROM rgpd_term_privacy_trade WHERE language_id = 3', 'id_term_pivacy_trade', 'id_term_pivacy_trade', false, 1 ) );

		// Obtenemos email del cliente
		$sEmail = pharaonix_queryOne( 'select customers_email_address from customers where customers_id = "' . $customer_id . '"' )->records['customers_email_address'];

		// Recorremos todo
		foreach( $aSubscribedAll as $nIdAll )
		{
			// Si lo teniamos y ya no esta seleccionado, eliminamos
			if( in_array( $nIdAll, $aSubscribed ) && !in_array( $nIdAll, $aSubscribedNow ) )
			{
				// Eliminamos
				tep_db_query( 'delete from rgpd_account_term where id_term_pivacy_trade = "' . (int)$nIdAll . '" and customers_id = "' . (int)$customer_id . '"' );

				// Añadimos log termino
				tep_db_perform( 'rgpd_log_term_privacy', array(
					'customers_id' => $customer_id,
					'customers_mail' => $sEmail,
					'ip' => tools::getIP(),
					'date' => date( 'Y-m-d H:i:s' ),
					'type' => 'comercial',
					'term_name' => $_POST['title_' . $nIdAll],
					'id_term_pivacy' => $nIdAll,
					'status' => 0
				) );

				// Si es boletin general tocamos campo newsletter
				if( $nIdAll == 1 )
					tep_db_query( 'update customers set customers_newsletter = 0 where customers_id = "' . (int)$customer_id . '"' );


				// Continuamos
				continue;
			}

			// Si lo tenemos no hacemos nada
			// Si no hemos seleccionado no hacemos nada tampoco
			if( in_array( $nIdAll, $aSubscribed ) || !in_array( $nIdAll, $aSubscribedNow ))
				continue;

			// Si llegamos aqui es que no lo tenemos con lo cual insertamos
			tep_db_perform( 'rgpd_account_term', array( 'customers_id' => $customer_id, 'id_term_pivacy_trade' => $nIdAll ) );

			// Añadimos log termino
			tep_db_perform( 'rgpd_log_term_privacy', array(
				'customers_id' => $customer_id,
				'customers_mail' => $sEmail,
				'ip' => tools::getIP(),
				'date' => date( 'Y-m-d H:i:s' ),
				'type' => 'comercial',
				'term_name' => $_POST['title_' . $nIdAll],
				'id_term_pivacy' => $nIdAll,
				'status' => 1
			) );

			// Si es boletin general tocamos campo newsletter
			if( $nIdAll == 1 )
				tep_db_query( 'update customers set customers_newsletter = 1 where customers_id = "' . (int)$customer_id . '"' );
		}

		$messageStack->addSession( 'newsletter', SUCCESS_NEWSLETTER_UPDATED, 'success' );
		tep_redirect( tep_href_link(FILENAME_ACCOUNT_NEWSLETTERS, '', 'SSL') );
	}
	
	// Header
	include( 'account/includes/header.php' );

	echo '<div class="ccTitle">' . MY_NEWSLETTERS_TITLE . '</div>';
	echo '<div class="ccCnt">';
		echo $messageStack->show( 'newsletter' );
		echo $messageStack->show( array( 'text' => MY_NEWSLETTERS_GENERAL_NEWSLETTER_DESCRIPTION, 'class' => 'wrng' ) );
		// Formulario
		echo '<form class="xform check ccCnt ccInfo5 ccInfo5-nslt" name="account_newsletter" action="' . $sAction . '" method="post">';
			// Variables
			$sHtml1 = '';
			$sHtml2 = '';
			$sHtmlTitle = '';
			$nCont = 0;

			// Obtenemos lo que tenemos suscrito
			$aSubscribed = array_values( pharaonix_getArrayAssociativeSql( 'select id_term_pivacy_trade from rgpd_account_term where customers_id = "' . (int)$customer_id . '"', 'id_term_pivacy_trade', 'id_term_pivacy_trade', false, 1 ) );

			// Consultamos
			$aRows = tep_db_query( 'SELECT id_term_pivacy_trade, title, info
									FROM rgpd_term_privacy_trade
									WHERE language_id = "' . $languages_id . '" ORDER BY title ASC' );

			while( $aRow = tep_db_fetch_array( $aRows ) )
			{
				echo '<div class="column rgpd-check">' . tep_draw_checkbox_field( 'id[]', $aRow['id_term_pivacy_trade'], (in_array($aRow['id_term_pivacy_trade'], $aSubscribed) ? true : false), 'id="id_' . $aRow['id_term_pivacy_trade'] . '"' ) . '<label style="margin-right: 0px;" for="id_' . $aRow['id_term_pivacy_trade'] . '"><span></span> ' . $aRow['title'] . '</label> <i title="' . htmlentities($aRow['info']) . '" class="fa fa-info-circle"></i></div>';
				echo '<input type="hidden" type="hidden" name="title_' . $aRow['id_term_pivacy_trade'] . '" value="' . $aRow['title'] . '" />';
			}

			// Submit
			echo '<div class="column d12 tright" style="border: 0px;">';
				echo '<input class="button verde small ccbutton" type="submit" value="' . IMAGE_BUTTON_UPDATE . '">';
			echo '</div>';
		echo '</form>';

		echo '<div class="ccTitle ccM0 clearfix bg">' . MY_RGPD_TITLE . '</div>';
		echo '<div class="ccCnt ccInfo5">';
			// Obtenemos todas los historiales de suscripciones y mostramos
			$aRows = tep_db_query( 'SELECT id_log_term_privacy, customers_id, customers_mail, DATE_FORMAT( date, "%d/%m/%Y %H:%i" ) as date, DATE_FORMAT( date, "%d/%m/%Y %H:%i:%s" ) as date_order, ip, type, term_name, status
									FROM rgpd_log_term_privacy
									WHERE customers_id = "' . (int)$customer_id . '" ORDER BY date_order DESC');

			while( $aRow = tep_db_fetch_array( $aRows ) )
				echo '<div class="column"><i class="fa ' . ($aRow['status'] == '1' ? 'fa-check' : 'fa-times') . '"></i>' . str_replace( array( '{TYPE}', '{TERM}', '{DATE}' ), array( ($aRow['status'] == '1' ? MY_RGPD_ACCEPT : MY_RGPD_DENEY), $aRow['term_name'], $aRow['date'] ), MY_RGPD_TEXT_TRADE ) . '</div>';
		echo '</div>';
	echo '</div>';

	// Footer
	include( 'account/includes/footer.php' );
?>