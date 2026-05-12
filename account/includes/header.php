<?php
	// Paramos la salida de php
	ob_start();

	// Incluimos archivo del template
	include( DIR_THEME. 'html/header.php' );
	include( DIR_THEME. 'html/column_left.php' );

	// Contenido obtenido
	$sHtml = ob_get_contents();

	// Continuamos con la salida por donde ibamos
	ob_end_clean();

	// Remplazamos
	$sHtml = str_replace(
		array(
			'</head>'
		),
		array(
			'<link rel="stylesheet" type="text/css" href="account/css/style.css?v=0.0.02" /></head>'
		), $sHtml
	);

	// Pintamos
	echo $sHtml;

	// Colores
	echo '<style>';
		echo '.ccMenu > li a:hover, .ccMenu > li a.actv, .ccInfo3 .ccaddadr, .ccInfo5 .column .fa';
		echo '{';
			echo 'color: #' . ACCOUNT_COLOUR . ';';
		echo '}';

		echo '#ccDelete > div.ccRow.actv:after, .ccInfo4 .button, .ccInfo3 .column .button, .ccInfo2 > div.fotr .button';
		echo '{';
			echo 'background: #' . ACCOUNT_COLOUR . ';';
		echo '}';

		echo '.button.ccbutton.verde{';
			echo 'background: #' . ACCOUNT_COLOUR . ' !important;';
		echo '}';
	echo '</style>';

	// Cambiamos estilo
	$messageStack->style = 'solenopsis';

	// Obtenemos email del cliente
	$sEmailCustomer = pharaonix_queryOne( 'select customers_email_address from customers where customers_id = "' . $customer_id . '"' )->records['customers_email_address'];

	// Obtenemos los pedidos del cliente
	$nTotalOrders = pharaonix_queryOne( 'select count(orders_id) as total from orders where customers_id = "' . $customer_id . '"' )->records['total'];

	echo '<div class="row dx tx ccAccount dflex tflex">';
		echo '<div class="column ccNav dfixed TFIXED">';

			if( USE_POINTS_SYSTEM == 'true' )
			{
				$nPoint = tep_get_shopping_points( $customer_id );

				echo '<a href="' . tep_href_link(FILENAME_MY_POINTS, '', 'SSL') . '" class="ccHead clearfix mhide">';
					echo '<em class="fleft">' . MY_POINTS_TITLE . '<b>: ' . $nPoint . '</b></em>';
					echo '<span class="fright">ver</span>';
				echo '</a>';
			}

			echo '<div class="ccMenuFake">';
				echo '<div class="ccMenuFakeLink dhide thide"><i class="fa fa-bars"></i> Menú</div>';
				echo '<div class="ccMenuFakeContent">';
					echo '<ul class="ccMenu">';
						echo '<li>';
							echo '<a href="' . tep_href_link(FILENAME_ACCOUNT, '', 'SSL') . '" class="ccParent ' . (preg_match( '/account\.php/i', $_SERVER['SCRIPT_NAME'] ) ? 'actv' : '') . '"><i class="fa fa fa-user"></i> ' . MY_ACCOUNT_TITLE . '</a>';
							echo '<ul>';
								echo '<li><a class="' . (preg_match( '/account_edit\.php/i', $_SERVER['SCRIPT_NAME'] ) ? 'actv' : '') . '" href="' . tep_href_link(FILENAME_ACCOUNT_EDIT, '', 'SSL') . '">' . MY_ACCOUNT_INFORMATION . '</a></li>';
								echo '<li><a class="' . (preg_match( '/address_book/i', $_SERVER['SCRIPT_NAME'] ) ? 'actv' : '') . '" href="' . tep_href_link(FILENAME_ADDRESS_BOOK, '', 'SSL') . '">' . MY_ACCOUNT_ADDRESS_BOOK . '</a></li>';
								echo '<li><a class="' . (preg_match( '/account_password\.php/i', $_SERVER['SCRIPT_NAME'] ) ? 'actv' : '') . '" href="' . tep_href_link(FILENAME_ACCOUNT_PASSWORD, '', 'SSL') . '">' . MY_ACCOUNT_PASSWORD . '</a></li>';
								echo '<li><a class="' . (preg_match( '/account_downloads\.php/i', $_SERVER['SCRIPT_NAME'] ) ? 'actv' : '') . '" href="' . tep_href_link('account/account_downloads.php', '', 'SSL') . '">' . MY_DOWNLOADS . '</a></li>';

								if(isset($customer_id)) {
									$aCards = tep_db_query('SELECT * FROM customers_cards WHERE customers_id = ' . (int)$customer_id);
									if (tep_db_num_rows($aCards)) {
										echo '<li><a class="' . (preg_match( '/account_cards\.php/i', $_SERVER['SCRIPT_NAME'] ) ? 'actv' : '') . '" href="' . tep_href_link('account/account_cards.php', '', 'SSL') . '">Mis tarjetas</a></li>';
									}
								}
								echo '<li><a class="' . (preg_match( '/account_disable\.php/i', $_SERVER['SCRIPT_NAME'] ) ? 'actv' : '') . '" href="' . tep_href_link('account/account_disable.php', '', 'SSL') . '">' . MY_DISABLE . '</a></li>';
								echo '<li><a class="' . (preg_match( '/account_delete\.php/i', $_SERVER['SCRIPT_NAME'] ) ? 'actv' : '') . '" href="' . tep_href_link(FILENAME_ACCOUNT_DELETE, '', 'SSL') . '">' . MY_ACCOUNT_DELETE . '</a></li>';
							echo '</ul>';
						echo '</li>';
						
						/**
						 * #XCC-313-91043
						 * @author Daniel Lucia <daniel.lucia@denox.es>
						 */
						if (Affiliates::customerIsAffiliate(intval($customer_id), false)) {
							echo '<li><a href="' . tep_href_link(FILENAME_ACCOUNT_AFFILIATE, '') . '" class="ccParent hv8 ' . (preg_match( '/account_affiliate\.php/i', $_SERVER['SCRIPT_NAME'] ) ? 'actv' : '') . '"><i class="fa fa-suitcase"></i> ' . EMAIL_AFFILIATE_TITLE . '</a></li>';
						}
						
						echo '<li><a href="' . tep_href_link(FILENAME_ACCOUNT_HISTORY, '', 'SSL') . '" class="ccParent hv8 ' . (preg_match( '/account_history/i', $_SERVER['SCRIPT_NAME'] ) ? 'actv' : '') . '"><i class="fa fa-folder-open"></i> ' . MY_ORDERS_TITLE . '</a></li>';
						echo '<li><a href="' . tep_href_link('favoritos.php', '', 'SSL') . '" class="ccParent hv8 ' . (preg_match( '/favoritos\.php/i', $_SERVER['SCRIPT_NAME'] ) ? 'actv' : '') . '"><i class="fa fa-star"></i> ' . MY_WISH . '</a></li>';
						echo '<li>';
							echo '<a href="' . tep_href_link('account/account_comments.php', '', 'SSL') . '" class="ccParent hv8 ' . (preg_match( '/account_comments\.php/i', $_SERVER['SCRIPT_NAME'] ) ? 'actv' : '') . '"><i class="fa fa-comment-alt"></i> ' . MY_COMMENTS . '</a>';
							echo '<ul>';
								echo '<li><a class="' . (preg_match( '/account_reviews\.php/i', $_SERVER['SCRIPT_NAME'] ) ? 'actv' : '') . '" href="' . tep_href_link('account/account_reviews.php', '', 'SSL') . '">' . MY_REVIEWS . '</a></li>';
								echo '<li><a class="' . (preg_match( '/account_comments\.php/i', $_SERVER['SCRIPT_NAME'] ) ? 'actv' : '') . '" href="' . tep_href_link('account/account_comments.php', '', 'SSL') . '">' . MY_COMMENTS . '</a></li>';
							echo '</ul>';
						echo '</li>';
						echo '<li><a href="' . tep_href_link(FILENAME_ACCOUNT_NEWSLETTERS, '', 'SSL') . '" class="ccParent hv8 ' . (preg_match( '/account_newsletters\.php/i', $_SERVER['SCRIPT_NAME'] ) ? 'actv' : '') . '"><i class="fa fa-envelope"></i> ' . EMAIL_NOTIFICATIONS_TITLE . '</a></li>';
					echo '</ul>';
					echo '<a href="' . tep_href_link(FILENAME_LOGOFF, '', 'SSL') . '" class="ccLogout button hv9 "><i class="fa fa-sign-in"></i>' . HEADER_TITLE_LOGOFF . '</a>';
				echo '</div>';
			echo '</div>';
		echo '</div>';
		echo '<div class="column">';

				if( $messageStack->size('account') > 0 )
					echo '<div class="mensaje">' . $messageStack->output('account') . '</div>';
?>