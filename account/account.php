<?php
	// Aplicacion
	include( 'includes/application.php' );

	// Header
	include( 'account/includes/header.php' );

	echo '<div class="ccHeadWelcome dx row dflex tx tflex">';
		echo '<div class="column ccName">' . tep_customer_greeting() . '<div>' . $sEmailCustomer . '</div></div>';
		echo '<div class="column dfixed TFIXED ccCstm"><i class="fa fa-user"></i></div>';
		echo '<div class="column ccOrder"><div>' . $nTotalOrders . '</div> pedidos en curso</div>';
	echo '</div>';

	// DATOS DEL CLIENTE
	// Obtenemos el registro
	$aDatos = tep_db_query( 'select c.customers_gender, c.customers_firstname, c.customers_lastname, date_format(c.customers_dob,"%d/%m/%Y") as customers_dob, c.customers_email_address, c.customers_telephone, c.customers_fax, entry_company_tax_id, a.entry_nif
							 from ' . TABLE_CUSTOMERS . ' c
							 left join ' . TABLE_ADDRESS_BOOK . ' a on c.customers_default_address_id = a.address_book_id
							 where a.customers_id = c.customers_id and c.customers_id = "' . (int)$customer_id . '"' );
	$aDato = tep_db_fetch_array( $aDatos );

	echo '<div class="ccTitle ccM0 clearfix bg"><span class="fleft">' . MY_ACCOUNT_INFORMATION . '</span><a href="' . tep_href_link(FILENAME_ACCOUNT_EDIT, '', 'SSL') . '" class="fright cclink trojo">' . VER_MODIFICAR . '</a></div>';
	echo '<div class="ccCnt ccInfo1">';
		echo '<div class="column">' . ENTRY_FIRST_NAME . ' &nbsp;<strong>' . $aDato['customers_firstname'] . '</strong></div>';
		echo '<div class="column">' . ENTRY_EMAIL_ADDRESS . ' &nbsp;<strong>' . $aDato['customers_email_address'] . '</strong></div>';
		echo '<div class="column">' . ENTRY_DATE_OF_BIRTH . ' &nbsp;<strong>' . ($aDato['customers_dob'] == '00/00/0000' ? '-' : $aDato['customers_dob']) . '</strong></div>';
	echo '</div>';

	// CONTRASEÑA
	echo '<div class="ccTitle ccM0 clearfix bg"><span class="fleft">' . MY_ACCOUNT_PASSWORD . '</span></div>';
	echo '<form action="' . tep_href_link( FILENAME_ACCOUNT_PASSWORD, '', 'SSL' ) . '" method="post" class="ccCnt ccInfo2">';
		echo '<div class="column">';
			echo '<label>' . ENTRY_PASSWORD_CURRENT . '</label>';
			echo tep_draw_input_field( 'password_current', '', 'required="" data-parsley-minlength="' . ENTRY_PASSWORD_MIN_LENGTH . '" data-parsley-trigger="change"', false, 'password' );
		echo '</div>';
		echo '<div class="column">';
			echo '<label>' . ENTRY_PASSWORD_NEW . '</label>';
			echo tep_draw_input_field( 'password_new', '', 'required="" data-parsley-minlength="' . ENTRY_PASSWORD_MIN_LENGTH . '" data-parsley-trigger="change"', false, 'password' );
		echo '</div>';
		echo '<div class="column">';
			echo '<label>' . ENTRY_PASSWORD_CONFIRMATION . '</label>';
			echo tep_draw_input_field( 'password_confirmation', '', 'required="" data-parsley-minlength="' . ENTRY_PASSWORD_MIN_LENGTH . '" data-parsley-trigger="change"', false, 'password' );
		echo '</div>';
		echo '<div class="column fotr clearfix">';
			echo '<div class="fleft">' . USA_6_CARACTERES . '</div>';
			echo '<input class="button fright" type="submit" value="' . IMAGE_BUTTON_UPDATE . '">';
		echo '</div>';
	echo '</form>';

	// MIS DIRECCIONES
	// Consulta
	$aDatos = tep_db_query( 'select c.name as city, entry_city_id, address_book_id, entry_firstname as firstname, entry_lastname as lastname, entry_company as company, entry_nif as nif, entry_street_address as street_address, entry_suburb as suburb, entry_city as city, entry_postcode as postcode, entry_state as state, entry_zone_id as zone_id, entry_country_id as country_id
							from ' . TABLE_ADDRESS_BOOK . ' a
							LEFT JOIN cities c ON c.id = a.entry_city_id
							where customers_id = "' . (int)$customer_id . '"
							order by firstname, lastname' );

	echo '<div class="ccTitle ccM0 clearfix bg"><span class="fleft">' . MY_ACCOUNT_ADDRESS_BOOK . '</span><a href="' . tep_href_link(FILENAME_ADDRESS_BOOK, '', 'SSL') . '" class="fright cclink trojo">' . VER_MODIFICAR . '</a></div>';
	echo '<div class="ccCnt ccInfo3">';
		while( $aDato = tep_db_fetch_array( $aDatos ) )
		{
			echo '<div class="column">';
				$format_id = tep_get_address_format_id( $aDato['country_id'] );

				echo '<strong>';
					if( $aDato['address_book_id'] == $customer_default_address_id )
						echo '<i class="fa fa-star"></i>';

					echo tep_output_string_protected($aDato['firstname'] . ' ' . $aDato['lastname']);
				echo '</strong>';

				echo '<div class="ccText">' . tep_address_format( $format_id, $aDato, true, ' ', ' ' ) . '</div>';

				echo '<a class="button small" style="text-transform: uppercase; margin-bottom: 0px;" href="' . tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS, 'edit=' . $aDato['address_book_id'], 'SSL') . '">' . SMALL_IMAGE_BUTTON_VIEW . '</a>';
			echo '</div>';
		}

		// Boton añadir dirección
		if( tep_count_customer_address_book_entries() < MAX_ADDRESS_BOOK_ENTRIES )
			echo '<div class="tleft"><a href="' . tep_href_link( FILENAME_ADDRESS_BOOK_PROCESS, '', 'SSL' ) . '" class="ccaddadr"><i>+</i> ' . IMAGE_BUTTON_ADD_ADDRESS . '</a></div>';
	echo '</div>';


	// PEDIDOS
	if( tep_count_customer_orders() > 0 )
	{
		echo '<div class="ccTitle ccM0 clearfix bg"><span class="fleft">' . OVERVIEW_TITLE . ' ' . OVERVIEW_PREVIOUS_ORDERS . '</span><a href="' . tep_href_link(FILENAME_ACCOUNT_HISTORY, '', 'SSL') . '" class="fright cclink trojo">' . OVERVIEW_SHOW_ALL_ORDERS . '</a></div>';
		echo '<div class="ccCnt ccInfo4 hsty">';
			$aDatos = tep_db_query( 'select o.orders_id, o.date_purchased, o.delivery_name, o.delivery_country, o.billing_name, o.billing_country, ot.text as order_total, s.orders_status_name
									 from ' . TABLE_ORDERS . ' o
									 inner join ' . TABLE_ORDERS_TOTAL . ' ot on(o.orders_id = ot.orders_id)
									 inner join ' . TABLE_ORDERS_STATUS . ' s on(o.orders_status = s.orders_status_id)
									 where o.customers_id = "' . (int)$customer_id . '" and ot.class = "ot_total" and s.language_id = "' . (int)$languages_id . '" and s.public_flag = "1"
									 order by orders_id desc limit 3' );

			echo '<table class="hover" border="0" width="100%">';
				while( $aDato = tep_db_fetch_array($aDatos) )
				{
					$order_name = $aDato['billing_name'];
					$order_country = $aDato['billing_country'];

					if( tep_not_null( $aDato['delivery_name'] ) )
					{
						$order_name = $aDato['delivery_name'];
						$order_country = $aDato['delivery_country'];
					}

					echo '<tr onClick="document.location.href=\'' . tep_href_link( FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $aDato['orders_id'], 'SSL' ) . '\'">';
						echo '<td width="80">' . tep_date_short( $aDato['date_purchased'] ) . '</td>';
						echo '<td width="10">#' . $aDato['orders_id'] . '</td>';
						echo '<td class="mhide">' . tep_output_string_protected( $order_name ) . ', ' . $order_country . '</td>';
						echo '<td class="mhide">' . $aDato['orders_status_name'] . '</td>';
						echo '<td class="tright">' . $aDato['order_total'] . '</td>';
						echo '<td class="tright" width="150">
							<a class="button small" style="display: block !important; text-transform: uppercase; margin-bottom: 5px;" href="' . tep_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $aDato['orders_id'], 'SSL') . '">' . SMALL_IMAGE_BUTTON_VIEW . '</a>
							<a class="button small mhide" style="background: #4daa15 !important; display: block !important; text-transform: uppercase; margin-bottom: 0px; height: auto !important; padding-bottom: 10px !important; " href="' . tep_href_link(FILENAME_SHOPPING_CART, 'buy_all=' . $aDato['orders_id'], 'SSL') . '">' . REORDER . '</a>
						</td>';
					echo '</tr>';
				}
			echo '</table>';
		echo '</div>';
	}

	// NOTIFICACIONES POR EMAIL
	// Obtenemos el registro
	$aDatos = tep_db_query( 'select rpt.id_term_pivacy_trade, rpt.title
							from rgpd_account_term rat
							inner join rgpd_term_privacy_trade rpt on(rpt.id_term_pivacy_trade = rat.id_term_pivacy_trade)
							where rat.customers_id = "' . (int)$customer_id . '"  and rpt.language_id = "' . $languages_id . '" ORDER BY rpt.title ASC' );

	echo '<div class="ccTitle ccM0 clearfix bg"><span class="fleft">' . NOTIFICACIONES_EMAIL . '</span><a href="' . tep_href_link(FILENAME_ACCOUNT_NEWSLETTERS, '', 'SSL') . '" class="fright cclink trojo">' . VER_MODIFICAR . '</a></div>';
	echo '<div class="ccCnt ccInfo5">';
		while( $aDato = tep_db_fetch_array( $aDatos ) )
			echo '<div class="column"><i class="fa fa-check-square"></i>' . $aDato['title'] . '</div>';
	echo '</div>';

	echo '<div class="ccTitle ccM0 clearfix bg">' . MY_RGPD_TITLE . '<a href="' . tep_href_link(FILENAME_ACCOUNT_NEWSLETTERS, '', 'SSL') . '" class="fright cclink trojo">' . VER_MAS . '</a></div>';
	echo '<div class="ccCnt ccInfo5">';
		// Obtenemos todas los historiales de suscripciones y mostramos
		$aRows = tep_db_query( 'SELECT id_log_term_privacy, customers_id, customers_mail, DATE_FORMAT( date, "%d/%m/%Y %H:%i" ) as date, ip, type, term_name, status
								FROM rgpd_log_term_privacy
								WHERE customers_id = "' . (int)$customer_id . '" ORDER BY date DESC LIMIT 5');

		while( $aRow = tep_db_fetch_array( $aRows ) )
			echo '<div class="column"><i class="fa ' . ($aRow['status'] == '1' ? 'fa-check' : 'fa-times') . '"></i>' . str_replace( array( '{TYPE}', '{TERM}', '{DATE}' ), array( ($aRow['status'] == '1' ? MY_RGPD_ACCEPT : MY_RGPD_DENEY), $aRow['term_name'], $aRow['date'] ), MY_RGPD_TEXT_TRADE ) . '</div>';
	echo '</div>';

	// Comprobamos si el modulo de puntos está permitido para el grupo de cliente del usuario
	$aTotalAllowed = tep_db_query("select cg.group_order_total_allowed from " . TABLE_CUSTOMERS . " c inner join customers_groups cg on(c.customers_group_id = cg.customers_group_id) where c.customers_id = '" . (int) $customer_id . "'");
	$aTotalAllowed = tep_db_fetch_array( $aTotalAllowed );

	if( USE_POINTS_SYSTEM == 'true' && ($aTotalAllowed['group_order_total_allowed'] == '' || ($aTotalAllowed['group_order_total_allowed'] != '' && preg_match( '/ot_redemptions/i', $aTotalAllowed['group_order_total_allowed'] == '' ))) )
	{
		$nPoint = tep_get_shopping_points( $customer_id );

		echo '<div class="ccTitle ccM0 clearfix bg">' . MY_POINTS_TITLE . '</div>';
		echo '<div class="ccCnt ccInfo5">';
			echo '<div class="column"><i class="fa fa-th"></i>' . sprintf( MY_POINTS_CURRENT_BALANCE, number_format((float)$nPoint,POINTS_DECIMAL_PLACES),$currencies->format(tep_calc_shopping_pvalue($nPoint))) . '</div>';
			echo '<div class="column"><a href="' . tep_href_link(FILENAME_MY_POINTS, '', 'SSL') . '"><i class="fa fa-money-bill-alt"></i>' . MY_POINTS_VIEW . '</a></div>';
			echo '<div class="column"><a href="' . tep_href_link(FILENAME_MY_POINTS, '', 'SSL') . '"><i class="fa fa-question-circle"></i>' . MY_POINTS_VIEW_HELP . '</a></div>';
		echo '</div>';
	}

	// Footer
	include( 'account/includes/footer.php' );
?>